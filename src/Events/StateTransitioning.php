<?php

namespace NathanDunn\StateHistory\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StateTransitioning
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Model $model,
        public string $field,
        public ?string $from,
        public string $to,
        public array $meta,
        public array $context
    ) {}
}
