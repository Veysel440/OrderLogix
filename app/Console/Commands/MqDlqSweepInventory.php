<?php

namespace App\Console\Commands;

use App\Services\Rabbit\ConnectionFactory;
use PhpAmqpLib\Message\AMQPMessage;

class MqDlqSweepInventory extends \Illuminate\Console\Command
{
    protected $signature = 'mq:dlq:inventory {action=requeue : requeue|park} {--limit=500}';
    protected $description = 'Sweep inventory DLQ: requeue to inventory.reserve or move to parking';

    public function handle(): int
    {
        $action = $this->argument('action');
        $limit = (int) $this->option('limit');
        $dlq = 'inventory.dlq';

        $conn = ConnectionFactory::connect();
        $ch = $conn->channel();

        $count = 0;
        for ($i=0;$i<$limit;$i++) {
            /** @var AMQPMessage|null $m */
            $m = $ch->basic_get($dlq, false);
            if (!$m) break;

            $body = $m->getBody();
            if ($action === 'requeue') {
                $ch->basic_publish(new AMQPMessage($body, $m->get_properties()), 'inventory.x', 'inventory.reserve');
            } else {
                $ch->basic_publish(new AMQPMessage($body, $m->get_properties()), 'inventory.parking.x', '');
            }
            $m->ack();
            $count++;
        }

        $ch->close(); $conn->close();
        $this->info("$action $count message(s).");
        return self::SUCCESS;
    }
}
