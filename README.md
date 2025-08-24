# 🗃️ Laravel State History

A Laravel package for managing enum-based model states with enforced transitions and automatic status history tracking.

## Features

- **Native PHP Enums**: Use PHP 8.2+ enums as your state contract
- **Enforced Transitions**: Pluggable state machine backend prevents invalid state changes
- **Automatic History**: Append-only status history with metadata and correlation IDs
- **Smart State Casting**: Automatic casting of `from` and `to` values in state history to enums or other types
- **Atomic Operations**: State changes and history updates happen in a single database transaction
- **Current State Columns**: Optional `current_{field}` columns for better indexing and querying
- **Laravel 11-12 Support**: Compatible with Laravel 11 and 12

## Installation

```bash
composer require nathandunn/laravel-state-history
```

## Quick Start

### 1. Define Your States

```php
enum ArticleState: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';
}
```

### 2. Create Your State Machine

```php
use NathanDunn\StateHistory\Contracts\StateMachine;
use NathanDunn\StateHistory\TransitionMap;

class ArticleStateMachine implements StateMachine
{
    public function getTransitions(): TransitionMap
    {
        return TransitionMap::build(ArticleState::class)
            ->allowFromNull(ArticleState::Draft)
            ->allow(ArticleState::Draft, ArticleState::Published)
            ->allow(ArticleState::Published, ArticleState::Archived)
            ->allowAnyTo(ArticleState::Draft);
    }

    public function canTransition(BackedEnum|string|null $from, BackedEnum|string $to): bool
    {
        return $this->getTransitions()->isAllowed($from, $to);
    }

    public function getAllowedTransitions(BackedEnum|string|null $from): array
    {
        return $this->getTransitions()->getAllowedTransitions($from);
    }
}
```

### 3. Configure Your Model

```php
use NathanDunn\StateHistory\Traits\HasState;

class Article extends Model
{
    use HasState;

    protected $casts = [
        'state' => ArticleState::class,
    ];

    // Simple format - auto-detects casting from $casts property
    protected function stateMachine(): array
    {
        return ['state' => ArticleStateMachine::class];
    }
}
```

### 4. Run Migrations

```bash
php artisan vendor:publish --provider="NathanDunn\StateHistory\StateHistoryServiceProvider" --tag="migrations"
php artisan migrate
```

## Configuration

### Publishing Files

```bash
# Migrations
php artisan vendor:publish --provider="NathanDunn\StateHistory\StateHistoryServiceProvider" --tag="migrations"

# Configuration
php artisan vendor:publish --provider="NathanDunn\StateHistory\StateHistoryServiceProvider" --tag="config"
```

### State Machine Configuration

The `TransitionMap` provides several methods to define allowed transitions:

```php
TransitionMap::build(ArticleState::class)
    ->allowFromNull(ArticleState::Draft)        // Allow new models to start as Draft
    ->allow(ArticleState::Draft, ArticleState::Review)     // Specific transition
    ->allow(ArticleState::Review, ArticleState::Published) // Specific transition
    ->allowAnyTo(ArticleState::Draft);         // Allow any state to return to Draft
```

- **`allowFromNull(ArticleState::Draft)`**: Allows new models (with no previous state) to transition to Draft
- **`allow(ArticleState::Draft, ArticleState::Review)`**: Allows transition from Draft to Review
- **`allowAnyTo(ArticleState::Draft)`**: Allows transition to Draft from any existing state

### Extended Configuration

For more control, you can explicitly configure casting:

```php
class ArticleExtended extends Model
{
    use HasState;

    protected function stateMachine(): array
    {
        return [
            'state' => [
                'machine' => ArticleStateMachine::class,
                'cast' => ArticleState::class, // Single cast for both from and to
            ]
        ];
    }
}
```

## Usage

### Basic State Transitions

```php
$article = Article::create(['state' => ArticleState::Draft]);

// Transition with metadata
$article->transitionTo(
    'state',
    ArticleState::Published,
    meta: ['editor' => 'alice']
);
```

### Query Helpers

```php
// Filter by state
$published = Article::whereState('state', ArticleState::Published)->get();

// Check current state
if ($article->isInState('state', ArticleState::Published)) {
    // Article is published
}

// Get allowed transitions
$allowed = $article->getAllowedTransitions('state');
```

### Current State Access

```php
// Get current state as casted value (e.g., enum instance)
$currentState = $article->getState('state'); // ArticleState enum instance
echo $currentState->name; // "Published"
echo $currentState->value; // "published"

// Get current state as raw string (backward compatibility)
$rawState = $article->getCurrentState('state'); // "published"

// Check current state
if ($article->isInState('state', ArticleState::Published)) {
    // Article is published
}
```

**Note**: If no casting is configured for a field, `getState()` will fall back to returning the raw string value, maintaining backward compatibility.

### History Access

```php
// Get all status changes
$history = $article->states('state');

foreach ($history as $state) {
    // Casted values (when configured)
    $fromState = $state->from; // ArticleState enum instance
    $toState = $state->to;     // ArticleState enum instance

    // Raw values (always available)
    $rawFrom = $state->getRawOriginal('from'); // String value
    $rawTo = $state->getRawOriginal('to');     // String value
}

// Get latest status
$latest = $article->latestState('state');

// Access metadata
$meta = $latest->meta; // ['editor' => 'alice']
```

### Multiple State Fields

```php
class Order extends Model
{
    use HasState;

    protected $casts = [
        'status' => OrderStatus::class,
        'payment_status' => PaymentStatus::class,
    ];

    protected function stateMachine(): array
    {
        return [
            'status' => OrderStateMachine::class,
            'payment_status' => PaymentStateMachine::class,
        ];
    }
}

// Transition different fields
$order->transitionTo('status', OrderStatus::Processing);
$order->transitionTo('payment_status', PaymentStatus::Paid);
```

## Advanced Features

### Guards

Guards run before transitions and can block them:

```php
use NathanDunn\StateHistory\Contracts\Guard;

class PublishedArticleGuard implements Guard
{
            public function allows($model, $from, $to, $meta = [], $context = []): bool
    {
        if ($to === ArticleState::Archived && $model->published_at < now()->subDays(30)) {
            throw new \Exception('Articles must be published for at least 30 days before archiving');
        }

        return true;
    }
}

// Add to your state machine
$manager = $this->getStateManager('state');
$manager->addGuard(new PublishedArticleGuard());
```

### Effects

Effects run after successful transitions:

```php
use NathanDunn\StateHistory\Contracts\Effect;

class PublishArticleEffect implements Effect
{
            public function execute($model, $from, $to, $meta = [], $context = []): void
    {
        if ($to === ArticleState::Published) {
            $model->update(['published_at' => now()]);
        }
    }
}

// Add to your state machine
$manager = $this->getStateManager('state');
$manager->addEffect(new PublishArticleEffect());
```

## Current State Columns

The package supports optional `current_{field}` columns that provide several benefits:

- **Better Indexing**: String columns are more efficient for database indexes than enum columns
- **Analytics**: Easier to query and aggregate state data
- **Safer Refactoring**: Current columns store the enum value as a string, making schema changes safer

### Configuration

The package configuration file `config/state-history.php` contains several options:

#### Current State Columns

Configure in `config/state-history.php`:

```php
return [
    'use_current_columns' => true,
    'prefix' => 'current_',
    'log_fallback_warnings' => true,
    'model' => \App\Models\CustomModelState::class, // Optional: custom model class
];
```

#### Custom Model Class

You can extend the default `ModelState` model to add custom functionality:

```php
<?php

namespace App\Models;

use NathanDunn\StateHistory\Models\ModelState;

class CustomModelState extends ModelState
{
    // Add your custom methods and relationships here

    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }
}
```

Then update your config:

```php
'model' => \App\Models\CustomModelState::class,
```

### Adding Current Columns

**For new tables:**

```php
Schema::create('articles', function (Blueprint $table) {
    $table->id();
    $table->string('current_state', 191)->nullable()->index();
    $table->timestamps();
});
```

**For existing tables:**

```bash
php artisan vendor:publish --provider="NathanDunn\StateHistory\StateHistoryServiceProvider" --tag="migrations"
```

Then modify the generated migration:

```php
Schema::table('articles', function (Blueprint $table) {
    $table->string('current_state', 191)->nullable()->index();
});
```

### How It Works

When current columns are enabled and present:

1. **Transitions**: The `current_{field}` column is updated when present, otherwise falls back to the base column
2. **Queries**: `whereState()` and `isInState()` automatically use `current_{field}` if available
3. **Fallback**: If `current_{field}` doesn't exist, the package falls back to the base column

```php
// This query automatically uses current_state if available
$published = Article::whereState('state', ArticleState::Published)->get();

// This check also uses current_state when available
if ($article->isInState('state', ArticleState::Published)) {
    // Article is published
}
```

## State Casting

The package provides intelligent casting of state history values, allowing you to work with strongly-typed enums instead of raw strings when accessing historical data.

### How It Works

When you access `from` and `to` values from state history records, they are automatically cast based on your model's configuration:

```php
$history = $article->states('state')->get();
foreach ($history as $state) {
    // These are automatically cast to ArticleState enum instances
    $fromState = $state->from; // ArticleState enum
    $toState = $state->to;     // ArticleState enum

    echo "Changed from {$fromState->name} to {$toState->name}";
    echo "From value: {$fromState->value}, To value: {$toState->value}";
}
```

### Supported Cast Types

The casting system supports all Laravel cast types:

- **Enums**: `ArticleState::class` - Casts to enum instances
- **Dates**: `'datetime'`, `'date'` - Casts to Carbon instances
- **Primitives**: `'int'`, `'string'`, `'bool'` - Casts to appropriate types
- **Custom Casts**: Any class implementing Laravel's casting interface

### Configuration Options

#### Auto-Detection (Simple Format)

When using the simple format, casting is automatically detected from your model's `$casts` property:

```php
class Article extends Model
{
    protected $casts = [
        'state' => ArticleState::class, // Enables casting for state history
    ];

    protected function stateMachine(): array
    {
        return ['state' => ArticleStateMachine::class];
    }
}
```

#### Explicit Configuration (Extended Format)

For more control, you can explicitly configure casting:

```php
class Article extends Model
{
    protected function stateMachine(): array
    {
        return [
            'state' => [
                'machine' => ArticleStateMachine::class,
                'cast' => ArticleState::class, // Single cast for both from and to
            ]
        ];
    }
}
```

## Events

The package fires two events during transitions:

- `StateTransitioning`: Fired before the transition (can be used for logging)
- `StateTransitioned`: Fired after successful transition (can be used for side effects)

```php
use NathanDunn\StateHistory\Events\StateTransitioned;

Event::listen(StateTransitioned::class, function (StateTransitioned $event) {
    Log::info("Model {$event->model->getKey()} transitioned from {$event->from} to {$event->to}");
});
```

## Testing

The package includes a generic `StateModelFactory` that provides a flexible foundation for creating test models in specific states:

```php
use NathanDunn\StateHistory\StateModelFactory;

class ArticleFactory extends StateModelFactory
{
    protected $model = Article::class;

    public function definition(): array
    {
        return [
            'title' => fake()->sentence(),
            'content' => fake()->paragraphs(3, true),
        ];
    }

    // Add your own helper methods
    public function draft(): static
    {
        return $this->inState('state', ArticleState::Draft);
    }

    public function published(): static
    {
        return $this->inState('state', ArticleState::Published);
    }
}

// Use your custom methods
$draftArticle = Article::factory()->draft()->create();
$publishedArticle = Article::factory()->published()->create();

// Or use the core inState method directly
$reviewArticle = Article::factory()->inState('state', ArticleState::Review)->create();
```

The factory provides one core method that works with any field and state:

- **`inState(string $field, BackedEnum|string $state)`** - Sets the `current_{field}` column to the specified state

This gives you complete flexibility to define your own helper methods based on your specific enums and state names.
