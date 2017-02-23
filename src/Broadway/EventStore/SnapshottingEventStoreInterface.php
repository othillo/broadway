<?php

/*
 * This file is part of the broadway/broadway package.
 *
 * (c) Qandidate.com <opensource@qandidate.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Broadway\EventStore;

use Broadway\Domain\DomainEventStreamInterface;

interface SnapshottingEventStoreInterface extends EventStoreInterface
{
    /**
     * @param mixed $id
     * @param int   $playhead
     *
     * @return DomainEventStreamInterface
     */
    public function loadFromPlayhead($id, $playhead);
}
