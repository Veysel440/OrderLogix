<?php declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Rabbit\ConnectionFactory;
use App\Services\Rabbit\Publisher;
use App\Support\EventSchema;
use App\Support\Telemetry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpAmqpLib\Message\AMQPMessage;

class MqConsumeOrchestrator extends Command
{
    protected $signature = 'mq:consume:orchestrator {--once}';
    protected $description = 'Consumes order.placed and publishes inventory.reserve';

    public function handle(): int
    {
        $ordersQ   = env('RABBITMQ_QUEUE', 'orders.q');
        $prefetch  = (int) env('ORCHESTRATOR_PREFETCH', 32);
        $runOnce   = (bool) $this->option('once');
        $stop      = false;

        if (function_exists('pcntl_signal')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGTERM, fn() => $GLOBALS['__mq_stop'] = true);
            pcntl_signal(SIGINT,  fn() => $GLOBALS['__mq_stop'] = true);
        }

        $c  = ConnectionFactory::connect('orders');
        $ch = $c->channel();
        $ch->basic_qos(null, $prefetch, null);
        $pub = new Publisher($ch);

        $this->info("orchestrator listening on {$ordersQ} (prefetch={$prefetch})");

        $cb = function (AMQPMessage $msg) use ($pub) {
            Telemetry::span('orchestrator.consume', function () use ($pub, $msg) {
                $props = $msg->get_properties();
                $raw   = $msg->getBody();
                $enc   = $props['content_encoding'] ?? null;
                if ($enc === 'gzip') { $raw = @gzdecode($raw) ?: $raw; }

                $payload = json_decode($raw, true, 512, JSON_INVALID_UTF8_SUBSTITUTE) ?? [];
                EventSchema::validate($payload); // order.placed@v

                $messageId = $props['message_id'] ?? $payload['message_id'] ?? null;
                $data      = $payload['data'] ?? [];

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
                    'type'        => 'inventory.reserve',
                    'v'           => 1,
                    'message_id'  => (string) Str::uuid(),
                    'occurred_at' => now()->toISOString(),
                    'data'        => [
                        'order_id' => $data['order_id'] ?? null,
                        'items'    => $data['items'],
                    ],
                ];

                $pub->publish('inventory.x', 'inventory.reserve', $inv, [
                    'x-causation-id'   => $messageId,
                    'x-correlation-id' => $data['order_id'] ?? $messageId,
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

        do {
            try { $ch->wait(null, false, 5); } catch (\PhpAmqpLib\Exception\AMQPTimeoutException) {}
            $stop = $stop || !empty($GLOBALS['__mq_stop']);
        } while (!$runOnce && !$stop && $ch->is_consuming());

        $ch->close(); $c->close();
        return self::SUCCESS;
    }
}
