<?php declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Rabbit\ConnectionFactory;
use Illuminate\Console\Command;
use PhpAmqpLib\Wire\AMQPTable;

class MqDeclare extends Command
{
    protected $signature = 'mq:declare';
    protected $description = 'Declare exchanges, queues, bindings (orders + inventory)';

    public function handle(): int
    {
        $ordersEx  = env('RABBITMQ_EXCHANGE', 'orders.x');
        $ordersDlx = env('RABBITMQ_DLX', 'orders.dlx');
        $ordersQ   = env('RABBITMQ_QUEUE', 'orders.q');

        $invEx  = 'inventory.x';
        $invDlx = 'inventory.dlx';
        $invQ   = 'inventory.reserve.q';
        $invDlq = 'inventory.dlq';

        $retry5  = 'inventory.retry.5s';
        $retry30 = 'inventory.retry.30s';
        $retry5m = 'inventory.retry.5m';

        $parkEx = 'inventory.parking.x';
        $parkQ  = 'inventory.parking.q';

        $c = ConnectionFactory::connect();
        $ch = $c->channel();

        $ch->exchange_declare($ordersEx, 'topic', false, true, false);
        $ch->exchange_declare($ordersDlx,'topic', false, true, false);
        $ch->exchange_declare($invEx,   'topic', false, true, false);
        $ch->exchange_declare($invDlx,  'topic', false, true, false);
        $ch->exchange_declare($parkEx,  'fanout',false, true, false);

        $ch->queue_declare($ordersQ, false, true, false, false, false, new AMQPTable([
            'x-queue-type' => 'quorum',
            'x-dead-letter-exchange' => $ordersDlx,
        ]));
        $ch->queue_bind($ordersQ, $ordersEx, 'order.placed');

        $ch->queue_declare($invQ, false, true, false, false, false, new AMQPTable([
            'x-queue-type' => 'quorum',
            'x-dead-letter-exchange' => $invDlx,
        ]));
        $ch->queue_bind($invQ, $invEx, 'inventory.reserve');

        $ch->queue_declare($invDlq, false, true, false, false, false, new AMQPTable([
            'x-queue-type' => 'quorum',
        ]));
        $ch->queue_bind($invDlq, $invDlx, 'inventory.reserve.dlq');

        foreach ([[$retry5, 5000], [$retry30, 30000], [$retry5m, 300000]] as [$q, $ttl]) {
            $ch->queue_declare($q, false, true, false, false, false, new AMQPTable([
                'x-queue-type' => 'classic',
                'x-message-ttl' => $ttl,
                'x-dead-letter-exchange' => $invEx,
                'x-dead-letter-routing-key' => 'inventory.reserve',
            ]));
        }

        $ch->queue_declare($parkQ, false, true, false, false, false, new AMQPTable([
            'x-queue-type' => 'quorum',
        ]));
        $ch->queue_bind($parkQ, $parkEx, '');

        $ch->close(); $c->close();
        $this->info('Topology declared/updated.');
        return self::SUCCESS;
    }
}
