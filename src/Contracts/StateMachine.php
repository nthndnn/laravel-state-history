<?php

namespace NathanDunn\StateHistory\Contracts;

use BackedEnum;
use NathanDunn\StateHistory\TransitionMap;

interface StateMachine
{
    /**
     * Get the transition map for this state machine.
     */
    public function getTransitions(): TransitionMap;

    /**
     * Check if a transition from one state to another is allowed.
     *
     * @param  BackedEnum|string|null  $from  The current state (null for new models)
     * @param  BackedEnum|string  $to  The target state
     */
    public function canTransition(BackedEnum|string|null $from, BackedEnum|string $to): bool;

    /**
     * Get all allowed transitions from a given state.
     *
     * @param  BackedEnum|string|null  $from  The current state (null for new models)
     */
    public function getAllowedTransitions(BackedEnum|string|null $from): array;
}
