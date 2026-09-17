<?php

namespace App\Console\Commands;

use App\Services\EventBus\DeadLetterQueue\DLQHandler;
use Illuminate\Console\Command;

/**
 * DLQ Handler Command
 *
 * Start listening to and processing dead letter queue messages
 */
class DLQHandlerCommand extends Command
{
    protected $signature = 'event-bus:dlq {--timeout=0 : Timeout in seconds (0 = indefinite)}';

    protected $description = 'Start processing dead letter queue messages';

    public function handle(DLQHandler $dlqHandler): int
    {
        $timeout = (int) $this->option('timeout');

        $this->info('Starting dead letter queue handler...');
        if ($timeout > 0) {
            $this->info("Timeout: {$timeout} seconds");
        }

        try {
            $dlqHandler->listen($timeout);
        } catch (\Exception $e) {
            $this->error("Error: {$e->getMessage()}");

            return 1;
        }

        return 0;
    }
}
