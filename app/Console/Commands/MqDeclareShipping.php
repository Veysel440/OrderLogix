<?php declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Rabbit\ConnectionFactory;
use Illuminate\Console\Command;
use PhpAmqpLib\Wire\AMQPTable;

class MqDeclareShipping extends Command
{
    protected $signature = 'mq:declare:shipping';
    protected $description = 'Declare shipping exchanges/queues in /shipping vhost';

    public function handle(): int
    {
        $c = ConnectionFactory::connect('shipping'); // RMQ_SHIPPING_* env desteklemek istersen ekleyebilirsin
        $ch = $c->channel();

        $ch->exchange_declare('shipping.x','topic',false,true,false);
        $ch->exchange_declare('shipping.dlx','topic',false,true,false);

        $ch->queue_declare('shipping.schedule.q', false, true, false, false, false, new AMQPTable([
            'x-queue-type'=>'quorum','x-dead-letter-exchange'=>'shipping.dlx',
        ]));
        $ch->queue_bind('shipping.schedule.q','shipping.x','shipping.schedule');

        $ch->queue_declare('shipping.retry', false, true, false, false, false, new AMQPTable([
            'x-queue-type'=>'classic',
            'x-dead-letter-exchange'=>'shipping.x',
            'x-dead-letter-routing-key'=>'shipping.schedule',
        ]));

        $ch->queue_declare('shipping.schedule.dlq', false, true, false, false, false, new AMQPTable([
            'x-queue-type'=>'quorum',
        ]));
        $ch->queue_bind('shipping.schedule.dlq','shipping.dlx','shipping.schedule.dlq');

        $ch->queue_declare('shipping.failed.q', false, true, false, false, false, new AMQPTable([
            'x-queue-type'=>'quorum',
        ]));
        $ch->queue_bind('shipping.failed.q','shipping.x','shipping.failed');

        $this->info('shipping vhost declared');
        $ch->close(); $c->close();
        return self::SUCCESS;
    }
}
