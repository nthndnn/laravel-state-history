<?php

namespace NathanDunn\StateHistory\Tests\Unit;

use NathanDunn\StateHistory\Tests\TestCase;
use NathanDunn\StateHistory\TransitionMap;
use PHPUnit\Framework\Attributes\Test;

enum TestTransitionState: string
{
    case Draft = 'draft';
    case Review = 'review';
    case Published = 'published';
    case Archived = 'archived';
}

class TransitionMapTest extends TestCase
{
    #[Test]
    public function it_builds_empty_transition_map()
    {
        $map = TransitionMap::build('TestEnum');

        $this->assertInstanceOf(TransitionMap::class, $map);
        $this->assertEmpty($map->getTransitions());
        $this->assertEmpty($map->getAnyToStates());
    }

    #[Test]
    public function it_allows_specific_transitions()
    {
        $map = TransitionMap::build(TestTransitionState::class)
            ->allow(TestTransitionState::Draft, TestTransitionState::Published)
            ->allow(TestTransitionState::Published, TestTransitionState::Archived);

        $this->assertTrue($map->isAllowed(TestTransitionState::Draft->value, TestTransitionState::Published->value));
        $this->assertTrue($map->isAllowed(TestTransitionState::Published->value, TestTransitionState::Archived->value));
        $this->assertFalse($map->isAllowed(TestTransitionState::Draft->value, TestTransitionState::Archived->value));
    }

    #[Test]
    public function it_allows_any_to_transitions()
    {
        $map = TransitionMap::build(TestTransitionState::class)
            ->allowAnyTo(TestTransitionState::Draft);

        $this->assertTrue($map->isAllowed(TestTransitionState::Published->value, TestTransitionState::Draft->value));
        $this->assertTrue($map->isAllowed(TestTransitionState::Archived->value, TestTransitionState::Draft->value));
        $this->assertTrue($map->isAllowed('any_state', TestTransitionState::Draft->value));
    }

    #[Test]
    public function it_handles_null_from_state_for_new_models()
    {
        $map = TransitionMap::build(TestTransitionState::class)
            ->allowAnyTo(TestTransitionState::Draft)
            ->allow(TestTransitionState::Draft, TestTransitionState::Published);

        $this->assertTrue($map->isAllowed(null, TestTransitionState::Draft->value));
        $this->assertTrue($map->isAllowed(null, TestTransitionState::Draft));

        $this->assertFalse($map->isAllowed(null, TestTransitionState::Published->value));
        $this->assertFalse($map->isAllowed(null, TestTransitionState::Published));

        $allowed = $map->getAllowedTransitions(null);
        $this->assertContains(TestTransitionState::Draft->value, $allowed);
        $this->assertCount(1, $allowed);
    }

    #[Test]
    public function it_allows_transitions_from_null_with_allow_from_null()
    {
        $map = TransitionMap::build(TestTransitionState::class)
            ->allowFromNull(TestTransitionState::Draft)
            ->allowFromNull(TestTransitionState::Review)
            ->allow(TestTransitionState::Draft, TestTransitionState::Published);

        $this->assertTrue($map->isAllowed(null, TestTransitionState::Draft->value));
        $this->assertTrue($map->isAllowed(null, TestTransitionState::Review->value));

        $this->assertFalse($map->isAllowed(null, TestTransitionState::Published->value));

        $allowed = $map->getAllowedTransitions(null);
        $this->assertContains(TestTransitionState::Draft->value, $allowed);
        $this->assertContains(TestTransitionState::Review->value, $allowed);
        $this->assertCount(2, $allowed);
    }

    #[Test]
    public function it_combines_specific_and_any_to_transitions()
    {
        $map = TransitionMap::build(TestTransitionState::class)
            ->allow(TestTransitionState::Draft, TestTransitionState::Published)
            ->allowAnyTo(TestTransitionState::Draft);

        $this->assertTrue($map->isAllowed(TestTransitionState::Draft->value, TestTransitionState::Published->value));
        $this->assertTrue($map->isAllowed(TestTransitionState::Published->value, TestTransitionState::Draft->value));
        $this->assertTrue($map->isAllowed(TestTransitionState::Archived->value, TestTransitionState::Draft->value));
    }

    #[Test]
    public function it_gets_allowed_transitions()
    {
        $map = TransitionMap::build(TestTransitionState::class)
            ->allow(TestTransitionState::Draft, TestTransitionState::Published)
            ->allow(TestTransitionState::Draft, TestTransitionState::Review)
            ->allowAnyTo(TestTransitionState::Draft);

        $allowed = $map->getAllowedTransitions(TestTransitionState::Draft->value);

        $this->assertContains(TestTransitionState::Published->value, $allowed);
        $this->assertContains(TestTransitionState::Review->value, $allowed);
        $this->assertContains(TestTransitionState::Draft->value, $allowed);
        $this->assertCount(3, $allowed);
    }

    #[Test]
    public function it_gets_any_to_states()
    {
        $map = TransitionMap::build(TestTransitionState::class)
            ->allowAnyTo(TestTransitionState::Draft)
            ->allowAnyTo(TestTransitionState::Archived);

        $anyToStates = $map->getAnyToStates();

        $this->assertContains(TestTransitionState::Draft->value, $anyToStates);
        $this->assertContains(TestTransitionState::Archived->value, $anyToStates);
        $this->assertCount(2, $anyToStates);
    }
}
