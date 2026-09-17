<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('pail {--timeout=0} {--filter=} {--message=} {--level=}')]
#[Description('Tail application logs')]
class PailCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $logPath = storage_path('logs/laravel.log');
        if (! file_exists($logPath)) {
            touch($logPath);
        }

        $this->info('Tailing logs from ' . $logPath . ' ...');

        $file = fopen($logPath, 'r');
        if (! $file) {
            $this->error('Unable to open log file.');
            return 1;
        }

        fseek($file, 0, SEEK_END);

        while (true) {
            $line = fgets($file);
            if ($line !== false) {
                $this->output->write($line);
            } else {
                usleep(500000);
                clearstatcache(false, $logPath);
            }
        }

        fclose($file);

        return 0;
    }
}
