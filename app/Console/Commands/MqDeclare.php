<?php declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Rabbit\ConnectionFactory;
use Illuminate\Console\Command;
use PhpAmqpLib\Wire\AMQPTable;

final class MqDeclare extends Command
{
    protected $signature = 'mq:declare';
    protected $description = 'Declare orders + inventory topology (default vhost for orders).';

    public function handle(): int
    {
        $ordersEx  = env('RABBITMQ_EXCHANGE', 'orders.x');
        $ordersDlx = env('RABBITMQ_DLX', 'orders.dlx');
        $ordersQ   = env('RABBITMQ_QUEUE', 'orders.q');

        $c = ConnectionFactory::connect('orders');
        $ch = $c->channel();

        $ch->exchange_declare($ordersEx, 'topic', false, true, false);
        $ch->exchange_declare($ordersDlx,'topic', false, true, false);

        $ch->queue_declare($ordersQ, false, true, false, false, false, new AMQPTable([
            'x-queue-type' => 'quorum',
            'x-dead-letter-exchange' => $ordersDlx,
        ]));
        $ch->queue_bind($ordersQ, $ordersEx, 'order.placed');

        $ch->close(); $c->close();

        $this->call('mq:declare:inventory');

        $this->info('Topology declared.');
        return self::SUCCESS;
    }
}
