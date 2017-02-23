<?php

/*
 * This file is part of the broadway/broadway package.
 *
 * (c) Qandidate.com <opensource@qandidate.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Broadway\Snapshot;

use Broadway\EventSourcing\EventSourcedAggregateRoot;

class Snapshot
{
    private $playhead;
    private $aggregateRoot;

    /**
     * @param int`                      $playhead
     * @param EventSourcedAggregateRoot $aggregateRoot
     */
    public function __construct($playhead, EventSourcedAggregateRoot $aggregateRoot)
    {
        $this->playhead      = $playhead;
        $this->aggregateRoot = $aggregateRoot;
    }

    /**
     * @return int
     */
    public function getPlayhead()
    {
        return $this->playhead;
    }

    /**
     * @return EventSourcedAggregateRoot
     */
    public function getAggregateRoot()
    {
        return $this->aggregateRoot;
    }
}
