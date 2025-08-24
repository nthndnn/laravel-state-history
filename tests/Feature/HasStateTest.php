<?php

namespace NathanDunn\StateHistory\Tests\Feature;

use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use NathanDunn\StateHistory\Contracts\StateMachine;
use NathanDunn\StateHistory\Tests\TestCase;
use NathanDunn\StateHistory\Traits\HasState;
use NathanDunn\StateHistory\TransitionMap;
use PHPUnit\Framework\Attributes\Test;

class HasStateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createTables();
    }

    private function createTables(): void
    {
        $this->app['db']->connection()->getSchemaBuilder()->create('test_models', function (Blueprint $table) {
            $table->id();
            $table->string('state', 191)->nullable();
            $table->string('payment_status', 191)->nullable();
            $table->string('current_state', 191)->nullable()->index();
            $table->string('current_payment_status', 191)->nullable()->index();
            $table->timestamps();
        });

        $this->app['db']->connection()->getSchemaBuilder()->create('test_models_no_current', function (Blueprint $table) {
            $table->id();
            $table->string('state', 191)->nullable();
            $table->timestamps();
        });

        $this->app['db']->connection()->getSchemaBuilder()->create('model_states', function (Blueprint $table) {
            $table->id();
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->string('field');
            $table->string('from')->nullable();
            $table->string('to');
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    #[Test]
    public function it_can_transition_to_new_state()
    {
        $model = new TestModel;
        $model->current_state = TestState::Draft->value;
        $model->save();

        $result = $model->transitionTo('state', TestState::Published, ['editor' => 'alice']);

        $this->assertTrue($result);
        $this->assertEquals(TestState::Published->value, $model->fresh()->current_state);
    }

    #[Test]
    public function it_records_status_history()
    {
        $model = new TestModel;
        $model->current_state = TestState::Draft->value;
        $model->save();

        $model->transitionTo('state', TestState::Published, ['editor' => 'alice']);

        $status = $model->latestState('state');

        $this->assertNotNull($status);
        $this->assertEquals(TestState::Draft->value, $status->from);
        $this->assertEquals(TestState::Published->value, $status->to);
        $this->assertEquals(['editor' => 'alice'], $status->meta);
    }

    #[Test]
    public function it_records_status_history_for_new_model_without_current_state()
    {
        $model = new TestModel;
        $model->save();

        $model->transitionTo('state', TestState::Draft, ['editor' => 'alice']);

        $status = $model->latestState('state');

        $this->assertNotNull($status);
        $this->assertNull($status->from);
        $this->assertEquals(TestState::Draft->value, $status->to);
        $this->assertEquals(['editor' => 'alice'], $status->meta);
    }

    #[Test]
    public function it_prevents_invalid_transitions()
    {
        $model = new TestModel;
        $model->current_state = TestState::Draft->value;
        $model->save();

        $this->expectException(\NathanDunn\StateHistory\Exceptions\InvalidStateTransitionException::class);

        $model->transitionTo('state', TestState::Archived);
    }

    #[Test]
    public function it_can_check_current_state()
    {
        $model = new TestModel;
        $model->current_state = TestState::Published->value;
        $model->save();

        $this->assertTrue($model->isInState('state', TestState::Published));
        $this->assertFalse($model->isInState('state', TestState::Draft));
    }

    #[Test]
    public function it_can_get_allowed_transitions()
    {
        $model = new TestModel;
        $model->current_state = TestState::Draft->value;
        $model->save();

        $allowed = $model->getAllowedTransitions('state');

        $this->assertContains(TestState::Draft->value, $allowed);
        $this->assertContains(TestState::Published->value, $allowed);
    }

    #[Test]
    public function it_can_query_by_state()
    {
        $draft = new TestModel;
        $draft->current_state = TestState::Draft->value;
        $draft->save();

        $published = new TestModel;
        $published->current_state = TestState::Published->value;
        $published->save();

        $publishedModels = TestModel::whereState('state', TestState::Published)->get();

        $this->assertCount(1, $publishedModels);
        $this->assertEquals('published', $publishedModels->first()->current_state);
    }

    #[Test]
    public function it_updates_current_columns_during_transition()
    {
        $model = new TestModel;
        $model->current_state = TestState::Draft->value;
        $model->current_payment_status = TestPaymentStatus::Pending->value;
        $model->save();

        $model->transitionTo('state', TestState::Published);

        $model->transitionTo('payment_status', TestPaymentStatus::Paid);

        $freshModel = $model->fresh();

        $this->assertEquals('published', $freshModel->current_state);
        $this->assertEquals(TestPaymentStatus::Paid->value, $freshModel->current_payment_status);
    }

    #[Test]
    public function it_uses_current_columns_for_queries_when_available()
    {
        $draft = new TestModel;
        $draft->current_state = TestState::Draft->value;
        $draft->save();

        $published = new TestModel;
        $published->current_state = TestState::Published->value;
        $published->save();

        $publishedModels = TestModel::whereState('state', TestState::Published)->get();

        $this->assertCount(1, $publishedModels);
        $this->assertEquals('published', $publishedModels->first()->current_state);
    }

    #[Test]
    public function it_falls_back_to_base_column_when_current_column_missing()
    {
        $model = new TestModelNoCurrent;
        $model->state = TestState::Published->value;
        $model->save();

        $this->assertTrue($model->isInState('state', TestState::Published));

        $publishedModels = TestModelNoCurrent::whereState('state', TestState::Published)->get();
        $this->assertCount(1, $publishedModels);
    }

    #[Test]
    public function it_handles_multiple_state_fields()
    {
        $model = new TestModel;
        $model->current_state = TestState::Draft->value;
        $model->current_payment_status = TestPaymentStatus::Pending->value;
        $model->save();

        $model->transitionTo('state', TestState::Published);
        $model->transitionTo('payment_status', TestPaymentStatus::Paid);

        $freshModel = $model->fresh();

        $this->assertEquals('published', $freshModel->current_state);
        $this->assertEquals(TestPaymentStatus::Paid->value, $freshModel->current_payment_status);

        $this->assertTrue($freshModel->isInState('state', TestState::Published));
        $this->assertTrue($freshModel->isInState('payment_status', TestPaymentStatus::Paid));
    }

    #[Test]
    public function it_returns_casted_state_values()
    {
        $model = new TestModel;
        $model->current_state = TestState::Draft->value;
        $model->save();

        // getState should return the casted enum instance
        $castedState = $model->getState('state');
        $this->assertInstanceOf(TestState::class, $castedState);
        $this->assertEquals(TestState::Draft, $castedState);

        // getCurrentState should return the raw string (backward compatibility)
        $rawState = $model->getCurrentState('state');
        $this->assertIsString($rawState);
        $this->assertEquals('draft', $rawState);

        // After transition, both should reflect the new state
        $model->transitionTo('state', TestState::Published);
        $freshModel = $model->fresh();

        $castedState = $freshModel->getState('state');
        $this->assertInstanceOf(TestState::class, $castedState);
        $this->assertEquals(TestState::Published, $castedState);

        $rawState = $freshModel->getCurrentState('state');
        $this->assertIsString($rawState);
        $this->assertEquals('published', $rawState);
    }

    #[Test]
    public function it_falls_back_to_raw_string_when_no_casting_configured()
    {
        $model = new TestModelNoCurrent;
        $model->state = 'custom_state';
        $model->save();

        // getState should return raw string when no casting is configured
        $state = $model->getState('state');
        $this->assertIsString($state);
        $this->assertEquals('custom_state', $state);

        // getCurrentState should also return raw string
        $rawState = $model->getCurrentState('state');
        $this->assertIsString($rawState);
        $this->assertEquals('custom_state', $rawState);
    }

    #[Test]
    public function it_uses_config_based_model_resolution()
    {
        // Test that the package uses the configured model class
        $this->assertEquals(
            \NathanDunn\StateHistory\Models\ModelState::class,
            config('state-history.model')
        );

        // Test that the states relationship works with the configured model
        $model = new TestModel;
        $model->current_state = TestState::Draft->value;
        $model->save();

        $states = $model->states('state');
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\MorphMany::class, $states);

        // Verify the related model class is correct
        $relatedModel = $states->getRelated();
        $this->assertInstanceOf(config('state-history.model'), $relatedModel);
    }
}

enum TestState: string
{
    case Draft = 'draft';
    case Review = 'review';
    case Published = 'published';
    case Archived = 'archived';
}

enum TestPaymentStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';
    case Refunded = 'refunded';
}

class TestStateMachine implements StateMachine
{
    public function getTransitions(): TransitionMap
    {
        return TransitionMap::build(TestState::class)
            ->allowFromNull(TestState::Draft)
            ->allowFromNull(TestState::Published)
            ->allow(TestState::Draft, TestState::Review)
            ->allow(TestState::Draft, TestState::Published)
            ->allow(TestState::Review, TestState::Published)
            ->allow(TestState::Published, TestState::Archived)
            ->allowAnyTo(TestState::Draft);
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

class TestPaymentStatusMachine implements StateMachine
{
    public function getTransitions(): TransitionMap
    {
        return TransitionMap::build(TestPaymentStatus::class)
            ->allowFromNull(TestPaymentStatus::Pending)
            ->allowFromNull(TestPaymentStatus::Paid)
            ->allow(TestPaymentStatus::Pending, TestPaymentStatus::Paid)
            ->allow(TestPaymentStatus::Pending, TestPaymentStatus::Failed)
            ->allow(TestPaymentStatus::Failed, TestPaymentStatus::Pending)
            ->allow(TestPaymentStatus::Paid, TestPaymentStatus::Refunded)
            ->allow(TestPaymentStatus::Refunded, TestPaymentStatus::Pending);
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

class TestModel extends Model
{
    use HasState;

    protected $table = 'test_models';

    protected $fillable = ['state', 'payment_status', 'current_state', 'current_payment_status'];

    protected $casts = [
        'state' => TestState::class,
        'payment_status' => TestPaymentStatus::class,
    ];

    protected function stateMachine(): array
    {
        return [
            'state' => TestStateMachine::class,
            'payment_status' => TestPaymentStatusMachine::class,
        ];
    }
}

class TestModelNoCurrent extends Model
{
    use HasState;

    protected $table = 'test_models_no_current';

    protected $fillable = ['state'];

    protected function stateMachine(): array
    {
        return ['state' => TestStateMachine::class];
    }
}
