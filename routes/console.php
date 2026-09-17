<?php

use App\Models\CompanySetting;
use App\Models\RecurringInvoice;
use App\Services\Document\RecurringInvoiceService;
use App\Support\Setup\InstallUtils;
use Illuminate\Support\Facades\Schedule;

// Only run in demo environment
if (config('app.env') === 'demo') {
    Schedule::command('reset:app --force')
        ->daily()
        ->runInBackground()
        ->withoutOverlapping();
}

if (InstallUtils::isDbCreated()) {
    Schedule::command('check:invoices:status')
        ->daily();

    Schedule::command('check:estimates:status')
        ->daily();

    Schedule::command('app:send-fleet-compliance-reminders')
        ->dailyAt('08:00')
        ->withoutOverlapping();

    // The recurring-invoice query below runs at console-bootstrap time (i.e.
    // every Artisan::call()). During installation the database can be in a
    // partially-migrated state where the `users` table exists (so isDbCreated()
    // is true) but `recurring_invoices` does not yet. An uncaught exception here
    // crashes the entire request — including the installation wizard's own
    // Artisan::call('config:clear') — producing a 500 that the frontend reports
    // as a "connection refused / network error". Wrap it so installation and
    // partial-DB states degrade gracefully instead of bringing everything down.
    try {
        $recurringInvoices = RecurringInvoice::where('status', 'ACTIVE')->get();
        foreach ($recurringInvoices as $recurringInvoice) {
            $timeZone = CompanySetting::getSetting('time_zone', $recurringInvoice->company_id);

            Schedule::call(function () use ($recurringInvoice) {
                app(RecurringInvoiceService::class)->generateInvoice($recurringInvoice);
            })->cron($recurringInvoice->frequency)->timezone($timeZone);
        }
    } catch (Exception $e) {
        // Database may be partially migrated (e.g. during installation or
        // upgrades). Silently skip schedule registration until the schema is
        // complete; the scheduler will pick everything up on the next run.
    }
}
