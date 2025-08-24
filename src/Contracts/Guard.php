<?php

namespace NathanDunn\StateHistory\Contracts;

use Illuminate\Database\Eloquent\Model;

interface Guard
{
    /**
     * Check if a transition is allowed.
     *
     * @param  Model  $model  The model being transitioned
     * @param  string  $from  The current state
     * @param  string  $to  The target state
     * @param  array  $meta  Additional metadata for the transition
     * @param  array  $context  Additional context for the transition
     * @return bool True if transition is allowed, false otherwise
     *
     * @throws \Exception If the transition should be blocked with a specific reason
     */
    public function allows(
        Model $model,
        string $from,
        string $to,
        array $meta = [],
        array $context = []
    ): bool;
}
