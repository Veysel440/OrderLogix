<?php declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Rabbit\ConnectionFactory;
use Illuminate\Console\Command;

final class MqStats extends Command
{
    protected $signature = 'mq:stats {queues*} {--ctx=orders}';
    protected $description = 'Passive declare to show message/consumer counts (per vhost via --ctx)';

    public function handle(): int
    {
        $ctx = (string) $this->option('ctx');
        $c   = ConnectionFactory::connect($ctx);
        $ch  = $c->channel();

        foreach ($this->argument('queues') as $q) {
            try {
                [$name, $messages, $consumers] = $ch->queue_declare($q, true, false, false, false);
                $this->line(sprintf('[%s] %-28s messages=%-6d consumers=%-3d', $ctx, $name, $messages, $consumers));
            } catch (\Throwable $e) {
                $this->warn(sprintf('[%s] %s: %s', $ctx, $q, $e->getMessage()));
            }
        }

        $ch->close(); $c->close();
        return self::SUCCESS;
    }
}
