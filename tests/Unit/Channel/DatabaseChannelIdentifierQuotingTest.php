<?php

declare(strict_types=1);

use Marko\Database\Config\DatabaseTimezoneConfig;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Notification\Channel\DatabaseChannel;
use Marko\Notification\Contracts\NotifiableInterface;
use Marko\Notification\Contracts\NotificationInterface;
use Marko\Testing\Fake\FakeClock;

/*
 * DatabaseChannel quotes the notifications table through ConnectionInterface::quoteIdentifier() (#338). The
 * connection quotes with backticks, so SQL that names the table bare or picks its own delimiter shows up.
 */

describe('DatabaseChannel identifier quoting', function (): void {
    beforeEach(function (): void {
        $this->statements = [];
        $connection = $this->createStub(ConnectionInterface::class);
        $connection->method('quoteIdentifier')->willReturnCallback(fn (string $name): string => "`$name`");
        $connection->method('execute')->willReturnCallback(function (string $sql): int {
            $this->statements[] = $sql;

            return 1;
        });
        $this->notifiable = $this->createStub(NotifiableInterface::class);
        $this->notifiable->method('getNotifiableType')->willReturn('App\\Entity\\User');
        $this->notifiable->method('getNotifiableId')->willReturn(42);
        $this->notification = $this->createStub(NotificationInterface::class);
        $this->notification->method('toDatabase')->willReturn(['message' => 'Hello']);
        $this->channel = new DatabaseChannel($connection, new FakeClock(), DatabaseTimezoneConfig::fromName('UTC'));
    });

    it('quotes the notifications table when sending to one notifiable', function (): void {
        $this->channel->send($this->notifiable, $this->notification);

        expect($this->statements)->toHaveCount(1)
            ->and($this->statements[0])->toStartWith('INSERT INTO `notifications` (id, type,');
    })->issue(338);

    it('quotes the notifications table when sending to many notifiables', function (): void {
        $this->channel->sendMany([$this->notifiable, $this->notifiable], $this->notification);

        expect($this->statements)->toHaveCount(1)
            ->and($this->statements[0])->toStartWith('INSERT INTO `notifications` (id, type,');
    })->issue(338);
});
