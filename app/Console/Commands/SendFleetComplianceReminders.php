<?php

namespace App\Console\Commands;

use App\Services\Document\FleetComplianceReminderService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:send-fleet-compliance-reminders')]
#[Description('Send company owners a consolidated fleet compliance reminder')]
class SendFleetComplianceReminders extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(FleetComplianceReminderService $service): int
    {
        $count = $service->sendDueReminders();
        $this->info("Sent {$count} fleet compliance reminder email(s).");

        return self::SUCCESS;
    }
}
