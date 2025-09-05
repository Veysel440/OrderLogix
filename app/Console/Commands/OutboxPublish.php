<?php declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\OutboxEvent;
use App\Services\Rabbit\ConnectionFactory;
use App\Services\Rabbit\Publisher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class OutboxPublish extends Command
{
    protected $signature = 'outbox:publish {--limit=200} {--ex=} {--ctx=orders}';
    protected $description = 'Publish pending outbox events to RabbitMQ (publisher confirms, unroutable fail)';

    public function handle(): int
    {
        $ex   = (string) ($this->option('ex') ?: env('RABBITMQ_EXCHANGE','orders.x'));
        $ctx  = (string) $this->option('ctx');
        $lim  = max(1, (int) $this->option('limit'));

        $conn = ConnectionFactory::connect($ctx);
        $ch   = $conn->channel();
        $pub  = new Publisher($ch);

        $count = 0;

        $events = OutboxEvent::query()
            ->whereNull('published_at')
            ->orderBy('occurred_at')
            ->limit($lim)
            ->get();

        foreach ($events as $e) {
            try {
                $payload = $e->payload;
                $payload['message_id']  = $e->id;
                $payload['type']        = $e->type;
                $payload['occurred_at'] = $e->occurred_at?->toISOString();

                $pub->publish($ex, $e->type, $payload, [
                    'x-idempotency-key' => $e->id,
                    'x-aggregate-id'    => $e->aggregate_id,
                ]);

                DB::table('outbox_events')
                    ->where('id', $e->id)
                    ->update(['published_at' => now()]);
                $count++;
            } catch (\Throwable $err) {
                $this->error("publish failed id={$e->id}: ".$err->getMessage());
            }
        }

        $ch->close(); $conn->close();
        $this->info("Published {$count} event(s) to {$ex} on ctx={$ctx}.");
        return self::SUCCESS;
    }
}
