<?php declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ProcessedMessage;
use App\Services\Rabbit\ConnectionFactory;
use Illuminate\Console\Command;
use PhpAmqpLib\Message\AMQPMessage;

class MqConsumeOrders extends Command
{
    protected $signature = 'mq:consume:orders';
    protected $description = 'Consume order.placed from orders.q (demo)';

    public function handle(): int
    {
        $q = env('RABBITMQ_QUEUE','orders.q');

        $conn = ConnectionFactory::connect();
        $ch = $conn->channel();
        $ch->basic_qos(null, 32, null);

        $callback = function (AMQPMessage $msg) {
            $payload = json_decode($msg->getBody(), true) ?? [];
            $messageId = $msg->get_properties()['message_id'] ?? ($payload['message_id'] ?? null);
            if (!$messageId) { $msg->ack(); return; }

            try {
                ProcessedMessage::create([
                    'message_id' => $messageId,
                    'consumer' => 'orders-consumer',
                    'processed_at' => now(),
                ]);
            } catch (\Throwable $e) {
                $msg->ack();
                return;
            }

            logger()->info('order.placed consumed', ['message_id' => $messageId, 'payload' => $payload]);

            $msg->ack();
        };

        $ch->basic_consume($q, '', false, false, false, false, $callback);

        while ($ch->is_consuming()) { $ch->wait(); }

        $ch->close(); $conn->close();
        return self::SUCCESS;
    }
}
