<?php

namespace NathanDunn\StateHistory\Contracts;

use Illuminate\Database\Eloquent\Model;

interface Effect
{
    /**
     * Execute an effect after a successful transition.
     *
     * @param  Model  $model  The model that was transitioned
     * @param  string  $from  The previous state
     * @param  string  $to  The new state
     * @param  array  $meta  Additional metadata for the transition
     * @param  array  $context  Additional context for the transition
     */
    public function execute(
        Model $model,
        string $from,
        string $to,
        array $meta = [],
        array $context = []
    ): void;
}
