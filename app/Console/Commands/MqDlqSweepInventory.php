<?php declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Pulse;
use App\Services\Rabbit\ConnectionFactory;
use App\Services\Rabbit\Publisher;
use Illuminate\Console\Command;
use PhpAmqpLib\Message\AMQPMessage;

final class MqDlqSweepInventory extends Command
{
    protected $signature = 'mq:dlq:sweep-inventory {--limit=1000} {--dry=0}';
    protected $description = 'Moves messages from inventory.reserve.dlq → inventory.retry (classic)';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $dry   = (bool) $this->option('dry');

        $c  = ConnectionFactory::connect('inventory');
        $ch = $c->channel();
        $pub= new Publisher($ch);

        $dlq = 'inventory.reserve.dlq';
        $moved = 0;

        $this->info("sweeping DLQ: {$dlq} limit={$limit} dry={$dry}");

        for ($i=0; $i<$limit; $i++) {
            $msg = $ch->basic_get($dlq, false);
            if (!$msg instanceof AMQPMessage) break;

            if (!$dry) {
                $props = $msg->get_properties();
                unset($props['expiration']);
                $retry = new AMQPMessage($msg->getBody(), $props);
                $ch->basic_publish($retry, '', 'inventory.retry');
            }

            $msg->ack();
            $moved++;
        }

        Pulse::send('inventory','inventory.reserve.dlq','err', ['moved'=>$moved]);
        $this->info("moved={$moved}");

        $ch->close(); $c->close();
        return self::SUCCESS;
    }
}
