<?php

namespace NathanDunn\StateHistory\Exceptions;

use Exception;

class NoStateMachineConfiguredException extends Exception
{
    public function __construct(
        string $field,
        ?string $modelClass = null
    ) {
        $modelInfo = $modelClass ? sprintf(' for %s', $modelClass) : '';
        $message = sprintf("No state machine configured for field '%s'%s.", $field, $modelInfo);

        parent::__construct($message);
    }
}
