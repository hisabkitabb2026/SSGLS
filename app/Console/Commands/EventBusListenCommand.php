<?php

namespace App\Console\Commands;

use App\Services\EventBus\Contracts\EventBusContract;
use Illuminate\Console\Command;

/**
 * Event Bus Listen Command
 *
 * Start listening to events from the message broker
 */
class EventBusListenCommand extends Command
{
    protected $signature = 'event-bus:listen {queue? : The queue to listen to} {--timeout=0 : Timeout in seconds (0 = indefinite)}';

    protected $description = 'Start listening to events from the message broker';

    public function handle(EventBusContract $eventBus): int
    {
        $queue = $this->argument('queue');
        $timeout = (int) $this->option('timeout');

        $this->info('Starting event bus listener...');
        if ($queue) {
            $this->info("Queue: {$queue}");
        }
        if ($timeout > 0) {
            $this->info("Timeout: {$timeout} seconds");
        }

        try {
            $eventBus->listen($queue, $timeout);
        } catch (\Exception $e) {
            $this->error("Error: {$e->getMessage()}");

            return 1;
        }

        return 0;
    }
}
