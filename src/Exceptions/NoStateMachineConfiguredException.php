<?php

namespace NathanDunn\StateHistory\Exceptions;

use Exception;

class NoStateMachineConfiguredException extends Exception
{
    public function __construct(
        string $field,
        ?string $modelClass = null
    ) {
        $modelInfo = $modelClass ? " for {$modelClass}" : '';
        $message = "No state machine configured for field '{$field}'{$modelInfo}.";

        parent::__construct($message);
    }
}
