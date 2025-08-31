<?php declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Rabbit\ConnectionFactory;
use Illuminate\Console\Command;
use PhpAmqpLib\Wire\AMQPTable;

class MqDeclarePayments extends Command
{
    protected $signature = 'mq:declare:payments';
    protected $description = 'Declare payments RPC topology';

    public function handle(): int
    {
        $ex  = 'payments.x';
        $dlx = 'payments.dlx';
        $q   = 'payments.auth.q';
        $dlq = 'payments.dlq';

        $c = ConnectionFactory::connect();
        $ch = $c->channel();

        $ch->exchange_declare($ex,  'topic', false, true, false);
        $ch->exchange_declare($dlx, 'topic', false, true, false);


        $ch->queue_declare($q, false, true, false, false, false, new AMQPTable([
            'x-queue-type' => 'quorum',
            'x-dead-letter-exchange' => $dlx,
        ]));
        $ch->queue_bind($q, $ex, 'payment.authorize.request');

        $ch->queue_declare($dlq, false, true, false, false, false, new AMQPTable([
            'x-queue-type' => 'quorum',
        ]));
        $ch->queue_bind($dlq, $dlx, 'payment.authorize.request.dlq');

        $ch->close(); $c->close();
        $this->info('payments topology declared.');
        return self::SUCCESS;
    }
}
