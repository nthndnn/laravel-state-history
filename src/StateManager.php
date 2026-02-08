<?php

namespace NathanDunn\StateHistory;

use BackedEnum;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use NathanDunn\StateHistory\Contracts\Effect;
use NathanDunn\StateHistory\Contracts\Guard;
use NathanDunn\StateHistory\Contracts\StateMachine;
use NathanDunn\StateHistory\Events\StateTransitioned;
use NathanDunn\StateHistory\Events\StateTransitioning;
use NathanDunn\StateHistory\Exceptions\InvalidStateTransitionException;
use NathanDunn\StateHistory\Exceptions\StateTransitionBlockedException;
use NathanDunn\StateHistory\Exceptions\StateTransitionFailedException;
use NathanDunn\StateHistory\Models\StateHistory;
use NathanDunn\StateHistory\Support\StateMachineConfig;

class StateManager
{
    public function __construct(
        protected StateMachine $stateMachine,
        protected ?StateMachineConfig $config = null,
        protected array $guards = [],
        protected array $effects = []
    ) {}

    /**
     * Extract the string value from a BackedEnum.
     */
    protected function extractStateValue(BackedEnum $state): string
    {
        return $state->value;
    }

    /**
     * Transition a model to a new state.
     */
    public function transition(
        Model $model,
        string $field,
        BackedEnum $to,
        array $meta = [],
        array $context = []
    ): bool {
        $from = $this->getCurrentState($model, $field);
        $toValue = $this->extractStateValue($to);

        if ($from === $toValue) {
            return true;
        }

        if (! $this->stateMachine->canTransition($from, $toValue)) {
            throw new InvalidStateTransitionException($from, $toValue, $field, get_class($model));
        }

        foreach ($this->guards as $guard) {
            if (! $guard->allows($model, $from, $toValue, $meta, $context)) {
                throw new StateTransitionBlockedException($from, $toValue, $field, $guard, get_class($model));
            }
        }

        Event::dispatch(new StateTransitioning($model, $field, $from, $toValue, $meta, $context));

        $transitionLogic = function () use ($model, $field, $from, $toValue, $meta, $context) {
            $model->refresh();

            $this->syncStateColumn($model, $field, $toValue);
            $model->save();
            $this->recordStatusChange($model, $field, $from, $toValue, $meta, $context);
        };

        try {
            DB::transaction($transitionLogic);

            Event::dispatch(new StateTransitioned($model, $field, $from, $toValue, $meta, $context));

            foreach ($this->effects as $effect) {
                $effect->execute($model, $from, $toValue, $meta, $context);
            }

            return true;
        } catch (\Exception $e) {
            if ($e instanceof InvalidStateTransitionException || $e instanceof StateTransitionBlockedException) {
                throw $e;
            }

            throw new StateTransitionFailedException($model, $field, $from, $toValue, $e);
        }
    }

    /**
     * Sync the state column (current column or base column depending on config).
     */
    protected function syncStateColumn(Model $model, string $field, string $toValue): void
    {
        if (! config('state-history.use_current_columns', true)) {
            $model->setAttribute($field, $toValue);
            return;
        }

        $currentColumn = sprintf('%s%s', config('state-history.prefix', 'current_'), $field);

        if (Schema::hasColumn($model->getTable(), $currentColumn)) {
            $model->setAttribute($currentColumn, $toValue);
        } else {
            // Fallback to base column if current column doesn't exist
            $model->setAttribute($field, $toValue);
        }
    }

    /**
     * Get the current state for a field, checking current column first, then falling back to latest history.
     */
    protected function getCurrentState(Model $model, string $field): ?string
    {
        if (config('state-history.use_current_columns', true)) {
            $currentColumn = sprintf('%s%s', config('state-history.prefix', 'current_'), $field);

            if (Schema::hasColumn($model->getTable(), $currentColumn)) {
                $currentState = $model->getRawOriginal($currentColumn);
                if ($currentState !== null) {
                    return $currentState;
                }
            }
        }

        $latestState = app(StateHistory::class)
            ->where('model_type', get_class($model))
            ->where('model_id', $model->getKey())
            ->where('field', $field)
            ->latest('created_at')
            ->value('to');

        if ($latestState !== null) {
            return $latestState;
        }

        // Add fallback support
        if (config('state-history.fallback_to_base_column', true)) {
            $baseState = $model->getRawOriginal($field);
            return $baseState !== null && $baseState !== '' ? $baseState : null;
        }

        return null;
    }

    protected function recordStatusChange(
        Model $model,
        string $field,
        ?string $from,
        string $to,
        array $meta,
        array $context
    ): void {
        app(config('state-history.model'))->create([
            'model_type' => get_class($model),
            'model_id' => $model->getKey(),
            'field' => $field,
            'from' => $from,
            'to' => $to,
            'meta' => $meta,
        ]);
    }

    public function addGuard(Guard $guard): self
    {
        $this->guards[] = $guard;

        return $this;
    }

    public function addEffect(Effect $effect): self
    {
        $this->effects[] = $effect;

        return $this;
    }

    public function canTransition(BackedEnum|string|null $from, BackedEnum|string $to): bool
    {
        return $this->stateMachine->canTransition($from, $to);
    }

    public function getAllowedTransitions(BackedEnum|string|null $from): array
    {
        return $this->stateMachine->getAllowedTransitions($from);
    }
}
