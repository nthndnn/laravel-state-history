<?php

namespace NathanDunn\StateHistory\Exceptions;

use Exception;

class InvalidStateTransitionException extends Exception
{
    public function __construct(
        ?string $from,
        string $to,
        string $field,
        ?string $modelClass = null
    ) {
        $modelInfo = $modelClass ? " for {$modelClass}" : '';
        $fromText = $from ?? 'null (new model)';
        $message = "Invalid state transition from '{$fromText}' to '{$to}' for field '{$field}'{$modelInfo}. This transition is not allowed by the state machine.";

        parent::__construct($message);
    }
}
