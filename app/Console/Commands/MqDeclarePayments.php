<?php declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Rabbit\ConnectionFactory;
use Illuminate\Console\Command;
use PhpAmqpLib\Wire\AMQPTable;

final class MqDeclarePayments extends Command
{
    protected $signature = 'mq:declare:payments';
    protected $description = 'Declare exchanges/queues in /payments vhost';

    public function handle(): int
    {
        $c  = ConnectionFactory::connect('payments');
        $ch = $c->channel();

        $ch->exchange_declare('payments.parking.x', 'fanout', false, true, false);

        $ch->exchange_declare('payments.dlx','topic',false,true,false);
        $ch->exchange_declare(
            'payments.x','topic',false,true,false,false,false,
            new AMQPTable(['alternate-exchange' => 'payments.parking.x'])
        );

        $ch->queue_declare('payments.auth.q', false, true, false, false, false, new AMQPTable([
            'x-queue-type'=>'quorum',
            'x-dead-letter-exchange'=>'payments.dlx',
        ]));
        $ch->queue_bind('payments.auth.q','payments.x','payment.authorize');

        $ch->queue_declare('payments.failed.q', false, true, false, false, false, new AMQPTable([
            'x-queue-type'=>'quorum',
        ]));
        $ch->queue_bind('payments.failed.q','payments.x','payment.failed');

        $ch->queue_declare('payments.retry', false, true, false, false, false, new AMQPTable([
            'x-queue-type'=>'classic',
            'x-dead-letter-exchange'=>'payments.x',
            'x-dead-letter-routing-key'=>'payment.authorize',
        ]));

        $ch->queue_declare('payments.auth.dlq', false, true, false, false, false, new AMQPTable([
            'x-queue-type'=>'quorum',
        ]));
        $ch->queue_bind('payments.auth.dlq','payments.dlx','payment.authorize.dlq');

        $ch->queue_declare('payments.parking.q', false, true, false, false, false, new AMQPTable([
            'x-queue-type'=>'quorum',
        ]));
        $ch->queue_bind('payments.parking.q','payments.parking.x','');

        $this->info('payments vhost declared');
        $ch->close(); $c->close();
        return self::SUCCESS;
    }
}
