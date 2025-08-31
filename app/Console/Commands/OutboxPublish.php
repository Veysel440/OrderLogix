<?php

namespace App\Console\Commands;


use App\Models\OutboxEvent;
use App\Services\Rabbit\ConnectionFactory;
use App\Services\Rabbit\Publisher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class OutboxPublish extends Command
{
    protected $signature = 'outbox:publish {--limit=200}';
    protected $description = 'Publish pending outbox events to RabbitMQ';

    public function handle(): int
    {
        $ex = env('RABBITMQ_EXCHANGE','orders.x');

        $conn = ConnectionFactory::connect();
        $ch = $conn->channel();
        $ch->confirm_select(); // publisher confirms
        $pub = new Publisher($ch);

        $limit = (int) $this->option('limit');
        $count = 0;

        $events = OutboxEvent::query()
            ->whereNull('published_at')
            ->orderBy('occurred_at')
            ->limit($limit)
            ->get();

        foreach ($events as $e) {
            $payload = $e->payload;
            $payload['message_id'] = $e->id;
            $payload['type'] = $e->type;
            $payload['occurred_at'] = $e->occurred_at?->toISOString();

            $pub->publish($ex, $e->type, $payload, [
                'x-idempotency-key' => $e->id,
                'x-aggregate-id' => $e->aggregate_id,
            ]);

            $ch->wait_for_pending_acks_returns(3.0);

            DB::table('outbox_events')
                ->where('id', $e->id)
                ->update(['published_at' => now()]);
            $count++;
        }

        $ch->close(); $conn->close();
        $this->info("Published $count event(s).");
        return self::SUCCESS;
    }
}
