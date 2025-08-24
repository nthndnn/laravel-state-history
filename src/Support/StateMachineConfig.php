<?php

namespace NathanDunn\StateHistory\Support;

use Illuminate\Database\Eloquent\Model;
use NathanDunn\StateHistory\Exceptions\InvalidStateMachineConfigurationException;

class StateMachineConfig
{
    public function __construct(
        public readonly string $machineClass,
        public readonly ?string $castFrom = null,
        public readonly ?string $castTo = null
    ) {}

    /**
     * Parse the state machine configuration from a model
     */
    public static function parse(array $config, string $field, ?Model $model = null): self
    {
        $fieldConfig = data_get($config, $field);

        if (is_string($fieldConfig)) {
            $castType = null;
            if ($model) {
                $castType = data_get($model->getCasts(), $field);
            }

            return new self($fieldConfig, $castType, $castType);
        }

        if (is_array($fieldConfig)) {
            if (isset($fieldConfig['machine'])) {
                $castType = data_get($fieldConfig, 'cast');

                return new self(
                    data_get($fieldConfig, 'machine'),
                    $castType,
                    $castType
                );
            }

            return new self(data_get($fieldConfig, 'machine'));
        }

        throw new InvalidStateMachineConfigurationException(
            $field,
            $fieldConfig,
            $model?->getMorphClass()
        );
    }

    /**
     * Check if casting is configured for this field
     */
    public function hasCasts(): bool
    {
        return $this->castFrom !== null || $this->castTo !== null;
    }

    /**
     * Get the cast type for a specific field (from/to)
     */
    public function getCastType(string $field): ?string
    {
        return match ($field) {
            'from' => $this->castFrom,
            'to' => $this->castTo,
            default => null,
        };
    }
}
