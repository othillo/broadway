<?php

/*
 * This file is part of the broadway/broadway package.
 *
 * (c) Qandidate.com <opensource@qandidate.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Broadway\EventSourcing;

use Broadway\EventHandling\TraceableEventBus;
use Broadway\EventSourcing\AggregateFactory\PublicConstructorAggregateFactory;
use Broadway\EventSourcing\Testing\TestEventSourcedAggregate;
use Broadway\EventStore\TraceableEventStore;
use Broadway\Snapshot\SnapshotRepository;

class SnapshottingEventSourcingRepositoryTest extends AbstractEventSourcingRepositoryTest
{
    /**
     * {@inheritdoc}
     */
    protected function createEventSourcingRepository(
        TraceableEventStore $eventStore,
        TraceableEventBus $eventBus,
        array $eventStreamDecorators
    ) {
        return new SnapshottingEventSourcingRepository(
            $eventStore,
            $eventBus,
            TestEventSourcedAggregate::class,
            new PublicConstructorAggregateFactory(),
            $this->prophesize(SnapshotRepository::class)->reveal(),
            $eventStreamDecorators
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function createAggregate()
    {
        return new TestEventSourcedAggregate();
    }
}
