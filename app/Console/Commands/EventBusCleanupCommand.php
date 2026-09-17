<?php

namespace App\Console\Commands;

use App\Services\EventBus\Contracts\EventBusContract;
use App\Services\EventBus\DeadLetterQueue\DLQHandler;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Event Bus Cleanup Command
 *
 * Clean up old events, idempotent records, and dead letters
 */
class EventBusCleanupCommand extends Command
{
    protected $signature = 'event-bus:cleanup {--days=30 : Number of days to retain}';

    protected $description = 'Clean up old event logs and dead letter messages';

    public function handle(EventBusContract $eventBus, DLQHandler $dlqHandler): int
    {
        $days = (int) $this->option('days');

        $this->info("Cleaning up event records older than {$days} days...");

        try {
            // Clean event logs
            $deletedLogs = $eventBus->cleanup($days);
            $this->info("Deleted {$deletedLogs} old event logs");

            // Clean dead letters
            $deletedDLQ = $dlqHandler->cleanup($days);
            $this->info("Deleted {$deletedDLQ} old dead letter messages");

            // Clean idempotent events
            $deletedIdempotent = DB::table('idempotent_events')
                ->where('expires_at', '<', now())
                ->delete();
            $this->info("Deleted {$deletedIdempotent} expired idempotent records");

            // Clean old saga records
            $deletedSagas = DB::table('sagas')
                ->where('completed_at', '<', now()->subDays($days))
                ->where('state', 'completed')
                ->delete();
            $this->info("Deleted {$deletedSagas} completed saga records");

            // Clean event metrics older than 90 days
            $deletedMetrics = DB::table('event_metrics')
                ->where('date', '<', now()->subDays(90)->toDateString())
                ->delete();
            $this->info("Deleted {$deletedMetrics} old event metrics");

            $this->info('Cleanup completed successfully');

            return 0;
        } catch (\Exception $e) {
            $this->error("Error: {$e->getMessage()}");

            return 1;
        }
    }
}
