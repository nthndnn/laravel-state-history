<?php

namespace NathanDunn\StateHistory\Exceptions;

use Exception;
use Illuminate\Database\Eloquent\Model;
use Throwable;

class StateTransitionFailedException extends Exception
{
    public function __construct(
        Model $model,
        string $field,
        ?string $from,
        string $to,
        Throwable $previous
    ) {
        $modelClass = get_class($model);
        $modelId = $model->getKey();
        $fromText = $from ?? 'null (new model)';
        
        $message = sprintf(
            "State transition failed for %s (ID: %s) from '%s' to '%s' for field '%s'. Original error: %s",
            $modelClass,
            $modelId,
            $fromText,
            $to,
            $field,
            $previous->getMessage()
        );

        parent::__construct($message, $previous->getCode(), $previous);
    }
}
