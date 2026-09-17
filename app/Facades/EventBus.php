<?php

namespace App\Facades;

use App\Services\EventBus\Contracts\EventBusContract;
use Illuminate\Support\Facades\Facade;

/**
 * EventBus Facade
 *
 * Provides convenient static access to the EventBus
 *
 * @method static string publish(\App\Services\EventBus\Events\Event $event, array $metadata = [])
 * @method static void subscribe(string $pattern, callable $handler, ?string $queue = null)
 * @method static void unsubscribe(string $pattern, ?string $queue = null)
 * @method static void listen(?string $queue = null, int $timeout = 0)
 * @method static array getMetadata(string $eventId)
 * @method static void replay(\DateTime $from, ?\DateTime $to = null, ?string $pattern = null)
 * @method static int cleanup(int $daysOld = 30)
 *
 * @see EventBusContract
 */
class EventBus extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'event-bus';
    }
}
