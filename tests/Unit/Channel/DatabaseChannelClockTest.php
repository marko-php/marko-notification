<?php

declare(strict_types=1);

use Marko\Database\Connection\ConnectionInterface;
use Marko\Notification\Channel\DatabaseChannel;
use Marko\Notification\Contracts\NotifiableInterface;
use Marko\Notification\Contracts\NotificationInterface;
use Marko\Testing\Fake\FakeClock;

test('it stamps created_at from the injected clock when sending one notification', function (): void {
    $capturedBindings = null;

    $connection = $this->createMock(ConnectionInterface::class);
    $connection->method('execute')
        ->willReturnCallback(function (string $sql, array $bindings) use (&$capturedBindings) {
            $capturedBindings = $bindings;

            return 1;
        });

    $notifiable = $this->createMock(NotifiableInterface::class);
    $notifiable->method('getNotifiableType')->willReturn('App\\Entity\\User');
    $notifiable->method('getNotifiableId')->willReturn(42);

    $notification = $this->createMock(NotificationInterface::class);
    $notification->method('toDatabase')->willReturn([]);

    $channel = new DatabaseChannel($connection, new FakeClock('2026-03-14 15:09:26'));
    $channel->send($notifiable, $notification);

    expect($capturedBindings[6])->toBe('2026-03-14 15:09:26');
});

test('it stamps created_at from the injected clock for every row of a batch send', function (): void {
    $captured = [];

    $connection = $this->createMock(ConnectionInterface::class);
    $connection->method('execute')
        ->willReturnCallback(function (string $sql, array $bindings) use (&$captured) {
            $captured[] = $bindings;

            return 2;
        });

    $first = $this->createMock(NotifiableInterface::class);
    $first->method('getNotifiableType')->willReturn('App\\Entity\\User');
    $first->method('getNotifiableId')->willReturn(1);

    $second = $this->createMock(NotifiableInterface::class);
    $second->method('getNotifiableType')->willReturn('App\\Entity\\User');
    $second->method('getNotifiableId')->willReturn(2);

    $notification = $this->createMock(NotificationInterface::class);
    $notification->method('toDatabase')->willReturn([]);

    $channel = new DatabaseChannel($connection, new FakeClock('2026-03-14 15:09:26'));
    $channel->sendMany([$first, $second], $notification);

    expect($captured)->toHaveCount(1)
        ->and($captured[0][6])->toBe('2026-03-14 15:09:26')
        ->and($captured[0][13])->toBe('2026-03-14 15:09:26');
});
