<?php declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Rabbit\ConnectionFactory;
use App\Services\Rabbit\Publisher;
use App\Support\Telemetry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpAmqpLib\Message\AMQPMessage;

class MqConsumeOrchestrator extends Command
{
    protected $signature = 'mq:consume:orchestrator';
    protected $description = 'Consumes order.placed and publishes inventory.reserve';

    public function handle(): int
    {
        $ordersQ = env('RABBITMQ_QUEUE', 'orders.q');

        $c  = ConnectionFactory::connect();
        $ch = $c->channel();
        $ch->basic_qos(null, 32, null);
        $pub = new Publisher($ch);

        $this->info("orchestrator listening on {$ordersQ}");

        $cb = function (AMQPMessage $msg) use ($pub) {
            Telemetry::span('orchestrator.consume', function () use ($pub, $msg) {
                $payload   = json_decode($msg->getBody(), true, 512, JSON_INVALID_UTF8_SUBSTITUTE) ?? [];
                $messageId = $msg->get_properties()['message_id'] ?? $payload['message_id'] ?? null;
                $data      = $payload['data'] ?? $payload;

                if (!$messageId || empty($data['items'])) {
                    $this->line('skip: missing data');
                    $msg->ack();
                    return;
                }

                $ins = DB::table('processed_messages')->insertOrIgnore([
                    'message_id'   => $messageId,
                    'consumer'     => 'orchestrator',
                    'processed_at' => now(),
                ]);
                if ($ins === 0) {
                    $this->line("dup: {$messageId}");
                    $msg->ack();
                    return;
                }

                $this->line("order.placed consumed: order_id=" . ($data['order_id'] ?? 'null') . " items=" . count($data['items']));

                $inv = [
                    'message_id'  => (string) Str::uuid(),
                    'type'        => 'inventory.reserve',
                    'occurred_at' => now()->toISOString(),
                    'data'        => [
                        'order_id' => $data['order_id'] ?? null,
                        'items'    => $data['items'],
                    ],
                ];
                $pub->publish('inventory.x', 'inventory.reserve', $inv, [
                    'x-causation-id' => $messageId,
                ]);

                $this->line('→ published inventory.reserve');
                $msg->ack();
            }, [
                'messaging.system'      => 'rabbitmq',
                'messaging.operation'   => 'process',
                'messaging.destination' => 'orders.q',
            ]);
        };

        $ch->basic_consume($ordersQ, '', false, false, false, false, $cb);
        while ($ch->is_consuming()) { $ch->wait(); }

        $ch->close(); $c->close();
        return self::SUCCESS;
    }
}
