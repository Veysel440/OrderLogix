<?php declare(strict_types=1);

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('outbox:publish')
            ->everyMinute()
            ->withoutOverlapping();

        $schedule->command('health:smoke')
            ->everyFiveMinutes()
            ->onOneServer();

        $schedule->command('mq:dlq:sweep-inventory --limit=1000')
            ->dailyAt('03:00')
            ->onOneServer();

        $schedule->command('db:purge-stales --pm-days=7 --ik-days=2 --batch=20000')
            ->dailyAt('03:10')
            ->withoutOverlapping()
            ->onOneServer();
    }
}
