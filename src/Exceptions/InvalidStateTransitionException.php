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
        $modelInfo = $modelClass ? sprintf(' for %s', $modelClass) : '';
        $fromText = $from ?? 'null (new model)';
        $message = sprintf(
            "Invalid state transition from '%s' to '%s' for field '%s'%s. This transition is not allowed by the state machine.",
            $fromText,
            $to,
            $field,
            $modelInfo
        );

        parent::__construct($message);
    }
}
