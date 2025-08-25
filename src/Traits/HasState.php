<?php

namespace NathanDunn\StateHistory\Traits;

use BackedEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Schema;
use NathanDunn\StateHistory\Exceptions\NoStateMachineConfiguredException;
use NathanDunn\StateHistory\Models\StateHistory;
use NathanDunn\StateHistory\StateManager;
use NathanDunn\StateHistory\Support\StateMachineConfig;

trait HasState
{
    /**
     * Get the state machine configuration for this model.
     */
    abstract protected function stateMachine(): array;

    /**
     * Get the state manager for a specific field.
     */
    protected function getStateManager(string $field): StateManager
    {
        $config = $this->stateMachine();

        if (! data_get($config, $field)) {
            throw new NoStateMachineConfiguredException($field, $this->getMorphClass());
        }

        $fieldConfig = StateMachineConfig::parse($config, $field, $this);
        $machine = new $fieldConfig->machineClass;

        return app(StateManager::class, [
            'stateMachine' => $machine,
            'config' => $fieldConfig,
        ]);
    }

    /**
     * Transition to a new state.
     */
    public function transitionTo(
        string $field,
        BackedEnum $to,
        array $meta = [],
        array $context = []
    ): bool {
        $manager = $this->getStateManager($field);

        return $manager->transition($this, $field, $to, $meta, $context);
    }

    /**
     * Get all state history for a specific field.
     */
    public function states(string $field): MorphMany
    {
        return $this->morphMany(config('state-history.model'), 'model')
            ->forField($field)
            ->orderBy('created_at', 'desc');
    }

    /**
     * Get the latest state for a specific field.
     */
    public function latestState(string $field): ?StateHistory
    {
        return $this->states($field)->first();
    }

    /**
     * Get the preferred column name for state queries.
     * Prefers current_{field} if it exists, otherwise falls back to {field}.
     */
    protected function getStateQueryColumn(string $field): string
    {
        if (! config('state-history.use_current_columns', true)) {
            return $field;
        }

        $currentColumn = sprintf('%s%s', config('state-history.prefix', 'current_'), $field);

        if (Schema::hasColumn($this->getTable(), $currentColumn)) {
            return $currentColumn;
        }

        return $field;
    }

    /**
     * Scope to filter models by state.
     */
    public function scopeWhereState(Builder $query, string $field, BackedEnum $state): Builder
    {
        $column = $this->getStateQueryColumn($field);

        return $query->where($column, $state->value);
    }

    /**
     * Check if the model is in a specific state.
     */
    public function isInState(string $field, BackedEnum $state): bool
    {
        $column = $this->getStateQueryColumn($field);

        return $this->getRawOriginal($column) === $state->value;
    }

    /**
     * Check if the model can transition to a specific state.
     */
    public function canTransitionTo(string $field, BackedEnum $to): bool
    {
        $manager = $this->getStateManager($field);
        $from = $this->getCurrentState($field);

        return $manager->canTransition($from, $to);
    }

    /**
     * Get all allowed transitions from the current state.
     */
    public function getAllowedTransitions(string $field): array
    {
        $manager = $this->getStateManager($field);
        $from = $this->getCurrentState($field);

        return $manager->getAllowedTransitions($from);
    }

    /**
     * Get the current state for a field, checking current column first, then falling back to latest history.
     */
    public function getCurrentState(string $field): ?string
    {
        if (config('state-history.use_current_columns', true)) {
            $currentColumn = sprintf('%s%s', config('state-history.prefix', 'current_'), $field);

            if (Schema::hasColumn($this->getTable(), $currentColumn)) {
                $currentState = $this->getRawOriginal($currentColumn);
                if ($currentState !== null) {
                    return $currentState;
                }
            }
        }

        $baseState = $this->getRawOriginal($field);
        if ($baseState !== null) {
            return $baseState;
        }

        $latestState = $this->latestState($field);

        return $latestState ? $latestState->getRawOriginal('to') : null;
    }

    /**
     * Get the current state for a field as a casted value (e.g., enum instance).
     * Falls back to string if casting is not configured.
     */
    public function getCurrentStateCasted(string $field): mixed
    {
        $rawState = $this->getCurrentState($field);

        if ($rawState === null) {
            return null;
        }

        try {
            $config = $this->stateMachine();
            if (data_get($config, $field)) {
                $fieldConfig = StateMachineConfig::parse($config, $field, $this);
                if ($fieldConfig->hasCasts()) {
                    $castType = $fieldConfig->getCastType('to') ?: 'string';

                    if (is_subclass_of($castType, \BackedEnum::class)) {
                        return $castType::from($rawState);
                    }

                    return $rawState;
                }
            }
        } catch (\Exception $e) {
        }

        $latestState = $this->latestState($field);
        if ($latestState) {
            return $latestState->to;
        }

        return $rawState;
    }

    /**
     * Get the current state for a field as a casted value (e.g., enum instance).
     * This is a convenience method that calls getCurrentStateCasted.
     */
    public function getState(string $field): mixed
    {
        return $this->getCurrentStateCasted($field);
    }
}
