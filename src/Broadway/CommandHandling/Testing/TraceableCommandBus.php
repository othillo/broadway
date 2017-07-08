<?php

/*
 * This file is part of the broadway/broadway package.
 *
 * (c) Qandidate.com <opensource@qandidate.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Broadway\CommandHandling\Testing;

use Broadway\CommandHandling\CommandBus;
use Broadway\CommandHandling\CommandHandler;

/**
 * Command bus that is able to record all dispatched commands.
 */
class TraceableCommandBus implements CommandBus
{
    private $commandHandlers = [];
    private $commands        = [];
    private $record          = false;

    /**
     * {@inheritDoc}
     */
    public function subscribe(CommandHandler $handler)
    {
        $this->commandHandlers[] = $handler;
    }

    /**
     * {@inheritDoc}
     */
    public function dispatch($command)
    {
        if (! $this->record) {
            return;
        }

        $this->commands[] = $command;
    }

    /**
     * @return array
     */
    public function getRecordedCommands(): array
    {
        return $this->commands;
    }

    public function record()
    {
        return $this->record = true;
    }
}
