<?php

/*
 * This file is part of the broadway/broadway package.
 *
 * (c) 2020 Broadway project
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Broadway\EventStore\Management\Testing;

use Broadway\Domain\DateTime;
use Broadway\Domain\DomainEventStream;
use Broadway\Domain\DomainMessage;
use Broadway\Domain\Metadata;
use Broadway\EventStore\EventStore;
use Broadway\EventStore\EventVisitor;
use Broadway\EventStore\Management\Criteria;
use Broadway\EventStore\Management\CriteriaNotSupportedException;
use Broadway\EventStore\Management\EventStoreManagement;
use Broadway\Serializer\Serializable;
use PHPUnit\Framework\TestCase;

abstract class EventStoreManagementTest extends TestCase
{
    /**
     * @var EventStore|EventStoreManagement
     */
    protected $eventStore;

    /**
     * @var DateTime
     */
    protected $now;

    protected function setUp(): void
    {
        $this->now = DateTime::now();
        $this->eventStore = $this->createEventStore();
        $this->createAndInsertEventFixtures();
    }

    protected function visitEvents(Criteria $criteria = null): array
    {
        $eventVisitor = new RecordingEventVisitor();

        $this->eventStore->visitEvents($criteria, $eventVisitor);

        return $eventVisitor->getVisitedEvents();
    }

    abstract protected function createEventStore(): EventStore;

    /** @test */
    public function it_visits_all_events(): void
    {
        $visitedEvents = $this->visitEvents(Criteria::create());

        $this->assertVisitedEventsArEquals($this->getEventFixtures(), $visitedEvents);
    }

    /** @test */
    public function it_visits_aggregate_root_ids(): void
    {
        $visitedEvents = $this->visitEvents(Criteria::create()->withAggregateRootIds([
            $this->getId(1),
            $this->getId(3),
        ]));

        $this->assertVisitedEventsArEquals([
            $this->createDomainMessage(1, 0, new Start()),
            $this->createDomainMessage(1, 1, new Middle('a')),
            $this->createDomainMessage(1, 2, new Middle('b')),
            $this->createDomainMessage(1, 3, new Middle('c')),
            $this->createDomainMessage(3, 0, new Start()),
            $this->createDomainMessage(3, 1, new Middle('a')),
            $this->createDomainMessage(3, 2, new Middle('b')),
            $this->createDomainMessage(3, 3, new Middle('c')),
            $this->createDomainMessage(1, 4, new Middle('d')),
            $this->createDomainMessage(3, 4, new Middle('d')),
            $this->createDomainMessage(1, 5, new End()),
            $this->createDomainMessage(3, 5, new End()),
        ], $visitedEvents);
    }

    /** @test */
    public function it_visits_event_types(): void
    {
        $visitedEvents = $this->visitEvents(Criteria::create()
            ->withEventTypes([
                'Broadway.EventStore.Management.Testing.Start',
                'Broadway.EventStore.Management.Testing.End',
            ])
        );

        $this->assertVisitedEventsArEquals([
            $this->createDomainMessage(1, 0, new Start()),
            $this->createDomainMessage(2, 0, new Start()),
            $this->createDomainMessage(2, 5, new End()),
            $this->createDomainMessage(3, 0, new Start()),
            $this->createDomainMessage(4, 0, new Start()),
            $this->createDomainMessage(4, 5, new End()),
            $this->createDomainMessage(1, 5, new End()),
            $this->createDomainMessage(3, 5, new End()),
        ], $visitedEvents);
    }

    /**
     * @test
     */
    public function it_visits_aggregate_root_types(): void
    {
        $this->expectException(CriteriaNotSupportedException::class);

        $this->visitEvents(Criteria::create()
            ->withAggregateRootTypes([
                'Broadway.EventStore.Management.Testing.AggregateTypeOne',
                'Broadway.EventStore.Management.Testing.AggregateTypeTwo',
            ])
        );
    }

    private function createAndInsertEventFixtures(): void
    {
        foreach ($this->getEventFixtures() as $domainMessage) {
            $this->eventStore->append($domainMessage->getId(), new DomainEventStream([$domainMessage]));
        }
    }

    /**
     * @return mixed[]
     */
    protected function getEventFixtures(): array
    {
        return [
            $this->createDomainMessage(1, 0, new Start()),
            $this->createDomainMessage(1, 1, new Middle('a')),
            $this->createDomainMessage(1, 2, new Middle('b')),

            $this->createDomainMessage(2, 0, new Start()),
            $this->createDomainMessage(2, 1, new Middle('a')),
            $this->createDomainMessage(2, 2, new Middle('b')),
            $this->createDomainMessage(2, 3, new Middle('c')),
            $this->createDomainMessage(2, 4, new Middle('d')),
            $this->createDomainMessage(2, 5, new End()),

            $this->createDomainMessage(1, 3, new Middle('c')),

            $this->createDomainMessage(3, 0, new Start()),
            $this->createDomainMessage(3, 1, new Middle('a')),
            $this->createDomainMessage(3, 2, new Middle('b')),
            $this->createDomainMessage(3, 3, new Middle('c')),

            $this->createDomainMessage(1, 4, new Middle('d')),

            $this->createDomainMessage(4, 0, new Start()),
            $this->createDomainMessage(4, 1, new Middle('a')),
            $this->createDomainMessage(4, 2, new Middle('b')),
            $this->createDomainMessage(4, 3, new Middle('c')),
            $this->createDomainMessage(4, 4, new Middle('d')),
            $this->createDomainMessage(4, 5, new End()),

            $this->createDomainMessage(3, 4, new Middle('d')),

            $this->createDomainMessage(1, 5, new End()),

            $this->createDomainMessage(3, 5, new End()),
        ];
    }

    /**
     * @param mixed $id
     * @param mixed $event
     */
    private function createDomainMessage($id, int $playhead, $event): DomainMessage
    {
        $id = $this->getId($id);

        return new DomainMessage((string) $id, $playhead, new Metadata([]), $event, $this->now);
    }

    /**
     * @param mixed $id
     */
    private function getId($id): string
    {
        return sprintf('%08d-%04d-4%03d-%04d-%012d', $id, $id, $id, $id, $id);
    }

    private function assertVisitedEventsArEquals(array $expectedEvents, array $actualEvents): void
    {
        $this->assertEquals(
            $this->groupEventsByAggregateTypeAndId($expectedEvents),
            $this->groupEventsByAggregateTypeAndId($actualEvents)
        );
    }

    /**
     * @param DomainMessage[] $events
     */
    private function groupEventsByAggregateTypeAndId(array $events): array
    {
        $eventsByAggregateTypeAndId = [];
        foreach ($events as $event) {
            $type = $event->getType();
            $id = $event->getId();

            if (!array_key_exists($type, $eventsByAggregateTypeAndId)) {
                $eventsByAggregateTypeAndId[$type] = [];
            }

            if (!array_key_exists($id, $eventsByAggregateTypeAndId[$type])) {
                $eventsByAggregateTypeAndId[$type][$id] = [];
            }

            $eventsByAggregateTypeAndId[$type][$id][] = $event;
        }

        return $eventsByAggregateTypeAndId;
    }
}

class RecordingEventVisitor implements EventVisitor
{
    /**
     * @var DomainMessage[]
     */
    private $visitedEvents;

    public function doWithEvent(DomainMessage $domainMessage): void
    {
        $this->visitedEvents[] = $domainMessage;
    }

    /**
     * @return DomainMessage[]
     */
    public function getVisitedEvents(): array
    {
        return $this->visitedEvents;
    }

    public function clearVisitedEvents(): void
    {
        $this->visitedEvents = [];
    }
}

class Event implements Serializable
{
    public static function deserialize(array $data): \Broadway\EventStore\Management\Testing\Event
    {
        return new self();
    }

    /**
     * @return mixed[]
     */
    public function serialize(): array
    {
        return [];
    }
}

class Start extends Event
{
}

class Middle extends Event
{
    /**
     * @var string
     */
    public $position;

    public function __construct(string $position)
    {
        $this->position = $position;
    }

    public static function deserialize(array $data): \Broadway\EventStore\Management\Testing\Middle
    {
        return new self($data['position']);
    }

    /**
     * @return mixed[]
     */
    public function serialize(): array
    {
        return [
            'position' => $this->position,
        ];
    }
}

class End extends Event
{
}
