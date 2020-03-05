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

namespace Broadway\EventStore\Testing;

use Broadway\Domain\DateTime;
use Broadway\Domain\DomainEventStream;
use Broadway\Domain\DomainMessage;
use Broadway\Domain\Metadata;
use Broadway\EventStore\EventStore;
use Broadway\EventStore\EventStreamNotFoundException;
use Broadway\EventStore\Exception\DuplicatePlayheadException;
use Broadway\Serializer\Serializable;
use Broadway\UuidGenerator\Rfc4122\Version4Generator;
use PHPUnit\Framework\Error\Error;
use PHPUnit\Framework\TestCase;

abstract class EventStoreTest extends TestCase
{
    /**
     * @var EventStore
     */
    protected $eventStore;

    /**
     * @param mixed $id
     * @test
     * @dataProvider idDataProvider
     */
    public function it_creates_a_new_entry_when_id_is_new($id): void
    {
        $domainEventStream = new DomainEventStream([
            $this->createDomainMessage($id, 0),
            $this->createDomainMessage($id, 1),
            $this->createDomainMessage($id, 2),
            $this->createDomainMessage($id, 3),
        ]);

        $this->eventStore->append($id, $domainEventStream);

        $this->assertEquals($domainEventStream, $this->eventStore->load($id));
    }

    /**
     * @param mixed $id
     * @test
     * @dataProvider idDataProvider
     */
    public function it_appends_to_an_already_existing_stream($id): void
    {
        $dateTime = DateTime::fromString('2014-03-12T14:17:19.176169+00:00');
        $domainEventStream = new DomainEventStream([
            $this->createDomainMessage($id, 0, $dateTime),
            $this->createDomainMessage($id, 1, $dateTime),
            $this->createDomainMessage($id, 2, $dateTime),
        ]);
        $this->eventStore->append($id, $domainEventStream);
        $appendedEventStream = new DomainEventStream([
            $this->createDomainMessage($id, 3, $dateTime),
            $this->createDomainMessage($id, 4, $dateTime),
            $this->createDomainMessage($id, 5, $dateTime),
        ]);

        $this->eventStore->append($id, $appendedEventStream);

        $expected = new DomainEventStream([
            $this->createDomainMessage($id, 0, $dateTime),
            $this->createDomainMessage($id, 1, $dateTime),
            $this->createDomainMessage($id, 2, $dateTime),
            $this->createDomainMessage($id, 3, $dateTime),
            $this->createDomainMessage($id, 4, $dateTime),
            $this->createDomainMessage($id, 5, $dateTime),
        ]);
        $this->assertEquals($expected, $this->eventStore->load($id));
    }

    /**
     * @param mixed $id
     * @test
     * @dataProvider idDataProvider
     */
    public function it_throws_an_exception_when_requesting_the_stream_of_a_non_existing_aggregate($id): void
    {
        $this->expectException(EventStreamNotFoundException::class);

        $this->eventStore->load($id);
    }

    /**
     * @param mixed $id
     * @test
     * @dataProvider idDataProvider
     */
    public function it_throws_an_exception_when_appending_a_duplicate_playhead($id): void
    {
        $eventStream = new DomainEventStream([$this->createDomainMessage($id, 0)]);

        $this->expectException(DuplicatePlayheadException::class);

        $this->eventStore->append($id, $eventStream);
        $this->eventStore->append($id, $eventStream);
    }

    /**
     * @test
     */
    public function it_throws_an_exception_when_an_id_cannot_be_converted_to_a_string(): void
    {
        $id = new IdentityThatCannotBeConvertedToAString(
            'Yolntbyaac' //You only live nine times because you are a cat
        );

        if (PHP_VERSION_ID > 70400) {
            $this->expectException(\Throwable::class);
        } else {
            $this->expectException(Error::class);
        }

        $this->expectExceptionMessage(sprintf(
            'Object of class %s could not be converted to string',
            IdentityThatCannotBeConvertedToAString::class
        ));

        $this->eventStore->append($id, new DomainEventStream([]));
    }

    /**
     * @param mixed $id
     * @test
     * @dataProvider idDataProvider
     */
    public function it_loads_events_starting_from_a_given_playhead($id): void
    {
        $dateTime = DateTime::fromString('2014-03-12T14:17:19.176169+00:00');
        $domainEventStream = new DomainEventStream([
            $this->createDomainMessage($id, 0, $dateTime),
            $this->createDomainMessage($id, 1, $dateTime),
            $this->createDomainMessage($id, 2, $dateTime),
            $this->createDomainMessage($id, 3, $dateTime),
        ]);

        $this->eventStore->append($id, $domainEventStream);

        $expected = new DomainEventStream([
            $this->createDomainMessage($id, 2, $dateTime),
            $this->createDomainMessage($id, 3, $dateTime),
        ]);

        $this->assertEquals($expected, $this->eventStore->loadFromPlayhead($id, 2));
    }

    /**
     * @param mixed $id
     * @test
     * @dataProvider idDataProvider
     */
    public function it_returns_empty_event_stream_when_no_events_are_committed_since_given_playhead($id): void
    {
        $this->eventStore->append($id, new DomainEventStream([
            $this->createDomainMessage($id, 0),
        ]));

        $this->assertEquals(
            new DomainEventStream([]),
            $this->eventStore->loadFromPlayhead($id, 1)
        );
    }

    public function idDataProvider(): array
    {
        return [
            'Simple String' => [
                'Yolntbyaac', //You only live nine times because you are a cat
            ],
            'Identitiy' => [
                new StringIdentity(
                    'Yolntbyaac' //You only live nine times because you are a cat
                ),
            ],
            'Integer' => [
                42, // test an int
            ],
            'UUID String' => [
                (new Version4Generator())->generate(), // test UUID
            ],
        ];
    }

    /**
     * @param mixed $id
     */
    protected function createDomainMessage($id, int $playhead, DateTime $recordedOn = null): DomainMessage
    {
        return new DomainMessage($id, $playhead, new MetaData([]), new Event(), $recordedOn ? $recordedOn : DateTime::now());
    }
}

class Event implements Serializable
{
    public static function deserialize(array $data): \Broadway\EventStore\Testing\Event
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

class StringIdentity
{
    /**
     * @var mixed
     */
    private $id;

    /**
     * @param mixed $id
     */
    public function __construct($id)
    {
        $this->id = $id;
    }

    public function __toString(): string
    {
        return (string) $this->id;
    }
}

class IdentityThatCannotBeConvertedToAString
{
    /**
     * @var mixed
     */
    private $id;

    /**
     * @param mixed $id
     */
    public function __construct($id)
    {
        $this->id = $id;
    }
}
