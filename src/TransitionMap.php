<?php

namespace NathanDunn\StateHistory;

use BackedEnum;

class TransitionMap
{
    private array $transitions = [];

    private array $anyToStates = [];

    private function __construct() {}

    /**
     * Build a new transition map for the given enum class.
     */
    public static function build(string $enumClass): self
    {
        return new self;
    }

    /**
     * Allow a transition from one state to another.
     */
    public function allow(BackedEnum $from, BackedEnum $to): self
    {
        if (! isset($this->transitions[$from->value])) {
            $this->transitions[$from->value] = [];
        }

        $this->transitions[$from->value][] = $to->value;

        return $this;
    }

    /**
     * Allow a transition from null (new models) to a specific state.
     */
    public function allowFromNull(BackedEnum $to): self
    {
        if (! isset($this->transitions[null])) {
            $this->transitions[null] = [];
        }

        $this->transitions[null][] = $to->value;

        return $this;
    }

    /**
     * Allow transitions from any state to the specified state.
     */
    public function allowAnyTo(BackedEnum $to): self
    {
        $this->anyToStates[] = $to->value;

        return $this;
    }

    /**
     * Check if a transition is allowed.
     */
    public function isAllowed(BackedEnum|string|null $from, BackedEnum|string $to): bool
    {
        $fromValue = $from instanceof BackedEnum ? $from->value : $from;
        $toValue = $to instanceof BackedEnum ? $to->value : $to;

        if (in_array($toValue, $this->anyToStates)) {
            return true;
        }

        if ($fromValue === null && isset($this->transitions[null])) {
            return in_array($toValue, $this->transitions[null]);
        }

        if ($fromValue !== null && isset($this->transitions[$fromValue])) {
            return in_array($toValue, $this->transitions[$fromValue]);
        }

        return false;
    }

    /**
     * Get all allowed transitions from a given state.
     */
    public function getAllowedTransitions(BackedEnum|string|null $from): array
    {
        $allowed = $this->anyToStates;
        $fromValue = $from instanceof BackedEnum ? $from->value : $from;

        if (isset($this->transitions[null])) {
            $allowed = array_merge($allowed, $this->transitions[null]);
        }

        if ($fromValue !== null && isset($this->transitions[$fromValue])) {
            $allowed = array_merge($allowed, $this->transitions[$fromValue]);
        }

        return array_unique($allowed);
    }

    /**
     * Get all transitions defined in this map.
     */
    public function getTransitions(): array
    {
        return $this->transitions;
    }

    /**
     * Get all states that can be transitioned to from any state.
     */
    public function getAnyToStates(): array
    {
        return $this->anyToStates;
    }
}
