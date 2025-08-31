<?php declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Rabbit\ConnectionFactory;
use Illuminate\Console\Command;

class MqStats extends Command
{
    protected $signature = 'mq:stats {queues*}';
    protected $description = 'Show message/consumer counts (passive declare)';

    public function handle(): int
    {
        $c = ConnectionFactory::connect();
        $ch = $c->channel();

        foreach ($this->argument('queues') as $q) {
            [$name, $messages, $consumers] = $ch->queue_declare($q, true, false, false, false);
            $this->line(sprintf('%-28s messages=%-6d consumers=%-3d', $name, $messages, $consumers));
        }

        $ch->close(); $c->close();
        return self::SUCCESS;
    }
}
