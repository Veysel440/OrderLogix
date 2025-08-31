<?php declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Rabbit\ConnectionFactory;
use Illuminate\Console\Command;
use PhpAmqpLib\Message\AMQPMessage;

class MqDlqSweepInventory extends Command
{
    protected $signature = 'mq:dlq:inventory {action=requeue : requeue|park} {--limit=}';
    protected $description = 'Sweep inventory DLQ: requeue to inventory.reserve or move to parking lot';

    public function handle(): int
    {
        $action = $this->argument('action');
        $limit  = (int) ($this->option('limit') ?? env('DLQ_SWEEP_LIMIT', 1000));
        $dlq    = 'inventory.dlq';

        $c = ConnectionFactory::connect();
        $ch = $c->channel();

        $count = 0;
        for ($i = 0; $i < $limit; $i++) {
            /** @var AMQPMessage|null $m */
            $m = $ch->basic_get($dlq, false);
            if (!$m) break;

            if ($action === 'requeue') {
                $ch->basic_publish(new AMQPMessage($m->getBody(), $m->get_properties()), 'inventory.x', 'inventory.reserve');
            } else {
                $ch->basic_publish(new AMQPMessage($m->getBody(), $m->get_properties()), 'inventory.parking.x', '');
            }
            $m->ack();
            $count++;
        }

        $ch->close(); $c->close();
        $this->info("{$action} {$count} message(s).");
        return self::SUCCESS;
    }
}
