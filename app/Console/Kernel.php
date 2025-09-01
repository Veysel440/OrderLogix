<?php declare(strict_types=1);

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('outbox:publish')->everyMinute()->withoutOverlapping();
        $schedule->command('health:smoke')->everyFiveMinutes();
        $schedule->command('mq:dlq:inventory requeue --limit=1000')->dailyAt('03:00');
    }
}
