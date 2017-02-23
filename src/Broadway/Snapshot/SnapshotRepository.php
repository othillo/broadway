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

interface SnapshotRepository
{
    /**
     * @param mixed $id
     *
     * @return Snapshot
     *
     * @throws SnapshotNotFoundException
     */
    public function load($id);

    /**
     * @param Snapshot $snapshot
     */
    public function save(Snapshot $snapshot);
}
