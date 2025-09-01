<?php declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Rabbit\ConnectionFactory;
use Illuminate\Console\Command;
use PhpAmqpLib\Wire\AMQPTable;

class MqDeclareOrders extends Command
{
    protected $signature = 'mq:declare:orders';
    protected $description = 'Declare exchanges/queues in /orders vhost';

    public function handle(): int
    {
        $c = ConnectionFactory::connect('orders');
        $ch = $c->channel();

        $ch->exchange_declare('orders.x','topic',false,true,false);
        $ch->exchange_declare('orders.dlx','topic',false,true,false);

        $ch->queue_declare('orders.q', false, true, false, false, false, new AMQPTable([
            'x-queue-type'=>'quorum',
            'x-dead-letter-exchange'=>'orders.dlx',
        ]));
        $ch->queue_bind('orders.q','orders.x','order.placed');

        $ch->queue_declare('orders.retry', false, true, false, false, false, new AMQPTable([
            'x-queue-type'=>'classic',
            'x-dead-letter-exchange'=>'orders.x',
            'x-dead-letter-routing-key'=>'order.placed',
        ]));

        $ch->queue_declare('orders.dlq', false, true, false, false, false, new AMQPTable([
            'x-queue-type'=>'quorum',
        ]));
        $ch->queue_bind('orders.dlq','orders.dlx','order.placed.dlq');

        $this->info('orders vhost declared');
        $ch->close(); $c->close();
        return self::SUCCESS;
    }
}
