<?php

namespace App\Console\Commands;

use App\Models\ProcessedMessage;
use App\Services\Rabbit\ConnectionFactory;
use App\Services\Rabbit\Publisher;
use Illuminate\Console\Command;
use PhpAmqpLib\Message\AMQPMessage;

class MqConsumeOrchestrator extends Command
{
    protected $signature = 'mq:consume:orchestrator';
    protected $description = 'Consumes order.placed and publishes inventory.reserve';

    public function handle(): int
    {
        $ordersQ = env('RABBITMQ_QUEUE','orders.q');

        $conn = ConnectionFactory::connect();
        $ch = $conn->channel();
        $ch->basic_qos(null, 32, null);
        $pub = new Publisher($ch);

        $this->info("orchestrator listening on {$ordersQ}");

        $io = $this; // konsola yazmak için

        $cb = function(AMQPMessage $msg) use ($pub,$io) {
            $payload = json_decode($msg->getBody(), true, 512, JSON_INVALID_UTF8_SUBSTITUTE) ?? [];
            $messageId = $msg->get_properties()['message_id'] ?? $payload['message_id'] ?? null;

            // Outbox'tan gelen eski yapı: order_id, items üst seviyede olabilir
            $data = $payload['data'] ?? $payload;

            if (!$messageId || empty($data['items'])) { $io->line('skip: missing data'); $msg->ack(); return; }

            try {
                ProcessedMessage::create([
                    'message_id'=>$messageId,'consumer'=>'orchestrator','processed_at'=>now(),
                ]);
            } catch (\Throwable) { $io->line("dup: $messageId"); $msg->ack(); return; }

            $io->line("order.placed consumed: order_id=".($data['order_id'] ?? 'null')." items=".count($data['items']));

            $inv = [
                'message_id'=>(string)\Illuminate\Support\Str::uuid(),
                'type'=>'inventory.reserve',
                'occurred_at'=>now()->toISOString(),
                'data'=>[
                    'order_id'=>$data['order_id'] ?? null,
                    'items'=>$data['items'],
                ],
            ];
            $pub->publish('inventory.x','inventory.reserve',$inv,['x-causation-id'=>$messageId]);

            $io->line('→ published inventory.reserve');
            $msg->ack();
        };

        $ch->basic_consume($ordersQ,'',false,false,false,false,$cb);
        while ($ch->is_consuming()) { $ch->wait(); }
        $ch->close(); $conn->close();
        return self::SUCCESS;
    }
}
