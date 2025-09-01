<?php declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Rabbit\ConnectionFactory;
use Illuminate\Console\Command;
use PhpAmqpLib\Wire\AMQPTable;

class MqDeclareInventory extends Command
{
    protected $signature = 'mq:declare:inventory';
    protected $description = 'Declare exchanges/queues in /inventory vhost';

    public function handle(): int
    {
        $c = ConnectionFactory::connect('inventory');
        $ch = $c->channel();

        $ch->exchange_declare('inventory.x','topic',false,true,false);
        $ch->exchange_declare('inventory.dlx','topic',false,true,false);

        $ch->queue_declare('inventory.reserve.q', false, true, false, false, false, new AMQPTable([
            'x-queue-type'=>'quorum',
            'x-dead-letter-exchange'=>'inventory.dlx',
        ]));
        $ch->queue_bind('inventory.reserve.q','inventory.x','inventory.reserve');

        $ch->queue_declare('inventory.retry', false, true, false, false, false, new AMQPTable([
            'x-queue-type'=>'classic',
            'x-dead-letter-exchange'=>'inventory.x',
            'x-dead-letter-routing-key'=>'inventory.reserve',
        ]));

        $ch->queue_declare('inventory.reserve.dlq', false, true, false, false, false, new AMQPTable([
            'x-queue-type'=>'quorum',
        ]));
        $ch->queue_bind('inventory.reserve.dlq','inventory.dlx','inventory.reserve.dlq');

        $this->info('inventory vhost declared');
        $ch->close(); $c->close();
        return self::SUCCESS;
    }
}
