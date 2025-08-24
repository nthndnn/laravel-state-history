<?php

namespace NathanDunn\StateHistory\Exceptions;

use Exception;

class InvalidStateMachineConfigurationException extends Exception
{
    public function __construct(
        string $field,
        mixed $config,
        ?string $modelClass = null
    ) {
        $modelInfo = $modelClass ? sprintf(' for %s', $modelClass) : '';
        $configType = is_object($config) ? get_class($config) : gettype($config);
        $message = sprintf(
            "Invalid state machine configuration for field '%s'%s. Expected string or array with 'machine' key, got %s.",
            $field,
            $modelInfo,
            $configType
        );

        parent::__construct($message);
    }
}
