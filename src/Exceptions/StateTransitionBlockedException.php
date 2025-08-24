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
        $modelInfo = $modelClass ? sprintf(' for %s', $modelClass) : '';
        $guardClass = get_class($guard);
        $reasonInfo = $reason ? sprintf(': %s', $reason) : '';
        $fromText = $from ?? 'null (new model)';

        $message = sprintf(
            "State transition from '%s' to '%s' for field '%s'%s was blocked by guard %s%s.",
            $fromText,
            $to,
            $field,
            $modelInfo,
            $guardClass,
            $reasonInfo
        );

        parent::__construct($message);
    }
}
