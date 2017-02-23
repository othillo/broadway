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

use Broadway\Domain\AggregateRoot;
use Broadway\Domain\DomainMessage;
use Broadway\EventHandling\EventBusInterface;
use Broadway\EventSourcing\AggregateFactory\AggregateFactoryInterface;
use Broadway\EventStore\SnapshottingEventStoreInterface;
use Broadway\Snapshot\Snapshot;
use Broadway\Snapshot\SnapshotNotFoundException;
use Broadway\Snapshot\SnapshotRepository;

class SnapshottingEventSourcingRepository extends EventSourcingRepository
{
    private $snapshotRepository;

    /**
     * @param SnapshottingEventStoreInterface $eventStore
     * @param EventBusInterface               $eventBus
     * @param string                          $aggregateClass
     * @param AggregateFactoryInterface       $aggregateFactory
     * @param SnapshotRepository              $snapshotRepository
     * @param EventStreamDecoratorInterface[] $eventStreamDecorators
     */
    public function __construct(
        SnapshottingEventStoreInterface $eventStore,
        EventBusInterface $eventBus,
        $aggregateClass,
        AggregateFactoryInterface $aggregateFactory,
        SnapshotRepository $snapshotRepository,
        $eventStreamDecorators = []
    ) {
        $this->snapshotRepository = $snapshotRepository;

        parent::__construct($eventStore, $eventBus, $aggregateClass, $aggregateFactory, $eventStreamDecorators);
    }

    /**
     * {@inheritdoc}
     */
    public function load($id)
    {
        try {
            $snapshot = $this->snapshotRepository->load($id);
        } catch (SnapshotNotFoundException $exception) {
            return parent::load($id);
        }

        $snapshot
            ->getAggregateRoot()
            ->initializeState(
                $this->eventStore->loadFromPlayhead($id, $snapshot->getPlayhead())
            )
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function save(AggregateRoot $aggregate)
    {
        $clonedAggregate = clone $aggregate;

        parent::save($aggregate);

        $takeSnaphot = false;
        foreach ($clonedAggregate->getUncommittedEvents() as $domainMessage) {
            if ($domainMessage->getPlayhead() % 99 === 0) {
                $takeSnaphot = true;
            }
        }

        if ($takeSnaphot) {
            $this->snapshotRepository->save(
                new Snapshot($aggregate->getPlayhead(), $aggregate)
            );
        }
    }
}
