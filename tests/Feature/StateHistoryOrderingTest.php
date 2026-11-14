<?php

namespace NathanDunn\StateHistory\Tests\Feature;

use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use NathanDunn\StateHistory\Contracts\StateMachine;
use NathanDunn\StateHistory\Models\StateHistory;
use NathanDunn\StateHistory\Tests\TestCase;
use NathanDunn\StateHistory\Traits\HasState;
use NathanDunn\StateHistory\TransitionMap;
use PHPUnit\Framework\Attributes\Test;

class StateHistoryOrderingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createTables();
    }

    private function createTables(): void
    {
        $this->app['db']->connection()->getSchemaBuilder()->create('ordering_test_models', function (Blueprint $table) {
            $table->id();
            $table->string('state', 191)->nullable();
            $table->timestamps();
        });

        $this->app['db']->connection()->getSchemaBuilder()->create('state_histories', function (Blueprint $table) {
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

    private function collapseHistoryTimestamps(?Carbon $timestamp = null): Carbon
    {
        $identicalTimestamp = $timestamp ?? Carbon::parse('2024-01-15 12:00:00');

        StateHistory::query()->update([
            'created_at' => $identicalTimestamp,
            'updated_at' => $identicalTimestamp,
        ]);

        return $identicalTimestamp;
    }

    /**
     * @param  list<int>|null  $ids
     * @return list<array{id: int, from: ?string, to: string, created_at: string}>
     */
    private function historySnapshot(?array $ids = null): array
    {
        $query = StateHistory::query()->orderBy('id');

        if ($ids !== null) {
            $query->whereIn('id', $ids);
        }

        return $query
            ->get(['id', 'from', 'to', 'created_at'])
            ->map(fn (StateHistory $row) => [
                'id' => $row->id,
                'from' => $row->getRawOriginal('from'),
                'to' => $row->getRawOriginal('to'),
                'created_at' => $row->created_at->toDateTimeString(),
            ])
            ->all();
    }

    #[Test]
    public function it_resolves_latest_state_by_id_when_created_at_timestamps_are_identical()
    {
        $model = new OrderingTestModel;
        $model->state = OrderingState::Draft->value;
        $model->save();

        $model->transitionTo('state', OrderingState::Submitted);
        $model->transitionTo('state', OrderingState::Received);

        $this->collapseHistoryTimestamps();

        $history = $model->states('state')->get();

        $this->assertCount(2, $history);
        $this->assertTrue($history[0]->created_at->eq($history[1]->created_at));
        $this->assertGreaterThan($history[1]->id, $history[0]->id);
        $this->assertEquals(OrderingState::Received->value, $history[0]->getRawOriginal('to'));
        $this->assertEquals(OrderingState::Submitted->value, $history[1]->getRawOriginal('to'));
        $this->assertEquals($history[0]->id, $model->latestState('state')->id);
    }

    #[Test]
    public function it_resolves_current_state_after_three_transitions_with_identical_timestamps()
    {
        $model = new OrderingTestModel;
        $model->save();

        $model->transitionTo('state', OrderingState::Draft);
        $model->transitionTo('state', OrderingState::Submitted);
        $model->transitionTo('state', OrderingState::Received);

        $this->collapseHistoryTimestamps();

        $history = $model->states('state')->get();

        $this->assertCount(3, $history);
        $this->assertTrue($history[0]->created_at->eq($history[1]->created_at));
        $this->assertTrue($history[1]->created_at->eq($history[2]->created_at));
        $this->assertEquals([
            OrderingState::Received->value,
            OrderingState::Submitted->value,
            OrderingState::Draft->value,
        ], $history->map(fn (StateHistory $row) => $row->getRawOriginal('to'))->all());

        // Null the base column so HasState::getCurrentState() reads history.
        $model->state = null;
        $model->save();
        $model->refresh();

        $this->assertEquals(OrderingState::Received->value, $model->getCurrentState('state'));
        $this->assertEquals($history[0]->id, $model->latestState('state')->id);
    }

    #[Test]
    public function it_allows_a_subsequent_valid_transition_when_history_timestamps_collide()
    {
        $model = new OrderingTestModel;
        $model->state = OrderingState::Draft->value;
        $model->save();

        $model->transitionTo('state', OrderingState::Submitted);
        $model->transitionTo('state', OrderingState::Received);

        $this->collapseHistoryTimestamps();

        $existingHistory = $this->historySnapshot();

        // StateManager prefers history over the base column. Without id ordering,
        // an earlier row (Submitted) could be treated as current and reject Accepted.
        $result = $model->fresh()->transitionTo('state', OrderingState::Accepted);

        $this->assertTrue($result);

        $accepted = $model->fresh()->latestState('state');

        $this->assertEquals(OrderingState::Received->value, $accepted->getRawOriginal('from'));
        $this->assertEquals(OrderingState::Accepted->value, $accepted->getRawOriginal('to'));
        $this->assertEquals(OrderingState::Accepted->value, $model->fresh()->state);
        $this->assertEquals(
            $existingHistory,
            $this->historySnapshot(array_column($existingHistory, 'id'))
        );
    }

    #[Test]
    public function it_does_not_modify_existing_history_records_with_duplicate_timestamps()
    {
        $model = new OrderingTestModel;
        $model->state = OrderingState::Draft->value;
        $model->save();

        $identicalTimestamp = Carbon::parse('2024-01-15 12:00:00');

        $first = StateHistory::query()->create([
            'model_type' => OrderingTestModel::class,
            'model_id' => $model->getKey(),
            'field' => 'state',
            'from' => OrderingState::Draft->value,
            'to' => OrderingState::Submitted->value,
            'meta' => null,
        ]);

        $second = StateHistory::query()->create([
            'model_type' => OrderingTestModel::class,
            'model_id' => $model->getKey(),
            'field' => 'state',
            'from' => OrderingState::Submitted->value,
            'to' => OrderingState::Received->value,
            'meta' => null,
        ]);

        StateHistory::query()->whereIn('id', [$first->id, $second->id])->update([
            'created_at' => $identicalTimestamp,
            'updated_at' => $identicalTimestamp,
        ]);

        $snapshot = $this->historySnapshot();

        $model->state = null;
        $model->save();
        $model->refresh();

        $this->assertEquals(OrderingState::Received->value, $model->getCurrentState('state'));
        $this->assertEquals($second->id, $model->latestState('state')->id);
        $this->assertEquals($snapshot, $this->historySnapshot());
        $this->assertEquals($identicalTimestamp->toDateTimeString(), $snapshot[0]['created_at']);
        $this->assertEquals($identicalTimestamp->toDateTimeString(), $snapshot[1]['created_at']);
        $this->assertEquals($first->id, $snapshot[0]['id']);
    }
}

enum OrderingState: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Received = 'received';
    case Accepted = 'accepted';
}

class OrderingStateMachine implements StateMachine
{
    public function getTransitions(): TransitionMap
    {
        return TransitionMap::build(OrderingState::class)
            ->allowFromNull(OrderingState::Draft)
            ->allow(OrderingState::Draft, OrderingState::Submitted)
            ->allow(OrderingState::Submitted, OrderingState::Received)
            ->allow(OrderingState::Received, OrderingState::Accepted);
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

class OrderingTestModel extends Model
{
    use HasState;

    protected $table = 'ordering_test_models';

    protected $fillable = ['state'];

    protected function stateMachine(): array
    {
        return ['state' => OrderingStateMachine::class];
    }
}
