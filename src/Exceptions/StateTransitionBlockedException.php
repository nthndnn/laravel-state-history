<?php

namespace NathanDunn\StateHistory\Exceptions;

use Exception;
use NathanDunn\StateHistory\Contracts\Guard;

class StateTransitionBlockedException extends Exception
{
    public function __construct(
        ?string $from,
        string $to,
        string $field,
        Guard $guard,
        ?string $modelClass = null,
        ?string $reason = null
    ) {
        $modelInfo = $modelClass ? " for {$modelClass}" : '';
        $guardClass = get_class($guard);
        $reasonInfo = $reason ? ": {$reason}" : '';
        $fromText = $from ?? 'null (new model)';

        $message = "State transition from '{$fromText}' to '{$to}' for field '{$field}'{$modelInfo} was blocked by guard {$guardClass}{$reasonInfo}.";

        parent::__construct($message);
    }
}
