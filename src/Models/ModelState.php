<?php

namespace NathanDunn\StateHistory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use NathanDunn\StateHistory\Support\StateMachineConfig;

class ModelState extends Model
{
    protected $table = 'model_states';

    protected $fillable = [
        'model_type',
        'model_id',
        'field',
        'from',
        'to',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    /**
     * Get the casts for this model, including dynamic ones from the related model
     */
    public function getCasts(): array
    {
        $casts = parent::getCasts();

        if (! data_get($this->attributes, 'field') || ! data_get($this->attributes, 'model_type') || ! data_get($this->attributes, 'model_id')) {
            return $casts;
        }

        if (! $this->relationLoaded('model')) {
            return $casts;
        }

        try {
            $relatedModel = $this->getRelation('model');

            if (! $relatedModel || ! method_exists($relatedModel, 'stateMachine')) {
                return $casts;
            }

            $config = $relatedModel->stateMachine();
            $fieldName = data_get($this->attributes, 'field');

            if (! data_get($config, $fieldName)) {
                return $casts;
            }

            $fieldConfig = StateMachineConfig::parse($config, $fieldName, $relatedModel);

            if (! $fieldConfig->hasCasts()) {
                return $casts;
            }

            $casts['from'] = $fieldConfig->getCastType('from') ?: 'string';
            $casts['to'] = $fieldConfig->getCastType('to') ?: 'string';

        } catch (\Exception $e) {
        }

        return $casts;
    }

    /**
     * Get the related model instance
     */
    public function getModelAttribute(): ?Model
    {
        if (! $this->relationLoaded('model')) {
            $this->load('model');
        }

        return $this->getRelation('model');
    }

    /**
     * Get the model that owns this state.
     */
    public function model(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Scope to filter by field.
     */
    public function scopeForField($query, string $field)
    {
        return $query->where('field', $field);
    }
}
