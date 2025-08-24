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
        $modelInfo = $modelClass ? " for {$modelClass}" : '';
        $configType = is_object($config) ? get_class($config) : gettype($config);
        $message = "Invalid state machine configuration for field '{$field}'{$modelInfo}. " .
                  "Expected string or array with 'machine' key, got {$configType}.";

        parent::__construct($message);
    }
}
