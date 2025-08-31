<?php declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Reservation;
use App\Services\Rabbit\ConnectionFactory;
use App\Services\Rabbit\Publisher;
use App\Services\Rabbit\RetryHelper as RH;
use App\Support\Telemetry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpAmqpLib\Message\AMQPMessage;

class MqConsumeInventory extends Command
{
    protected $signature = 'mq:consume:inventory';
    protected $description = 'Consumes inventory.reserve, reserves stock with retries, emits inventory.reserved';

    public function handle(): int
    {
        $q   = 'inventory.reserve.q';
        $max = (int) env('INVENTORY_MAX_RETRY', 3);

        $c  = ConnectionFactory::connect();
        $ch = $c->channel();
        $ch->basic_qos(null, 16, null);
        $pub = new Publisher($ch);

        $this->info("inventory listening on {$q}");

        $handleFail = function (AMQPMessage $m, \Throwable $e) use ($ch, $pub, $max) {
            $n = RH::retryCount($m);
            if ($n < $max) {
                $props = array_merge($m->get_properties(), RH::withRetryHeaders($m, $n + 1));
                $props['expiration'] = (string) RH::computeDelayMs($n); // ms
                $retryMsg = new \PhpAmqpLib\Message\AMQPMessage($m->getBody(), $props);
                $ch->basic_publish($retryMsg, '', 'inventory.retry');
                $this->line("retry[{$n}] delay={$props['expiration']}ms");
            } else {
                $pub->publish('inventory.dlx', 'inventory.reserve.dlq', [
                    'type'        => 'inventory.reserve.failed',
                    'message_id'  => (string) Str::uuid(),
                    'occurred_at' => now()->toISOString(),
                    'error'       => substr($e->getMessage(), 0, 200),
                    'payload'     => json_decode($m->getBody(), true),
                ]);
                $this->line('→ sent to DLQ');
            }
            $m->ack();
        };

        $cb = function (AMQPMessage $m) use ($pub, $handleFail) {
            Telemetry::span('inventory.reserve.consume', function () use ($pub, $handleFail, $m) {
                try {
                    $payload = json_decode($m->getBody(), true, 512, JSON_INVALID_UTF8_SUBSTITUTE);
                    $data = $payload['data'] ?? null;
                    if (!$data || empty($data['items'])) {
                        $this->line('skip: bad payload');
                        $m->ack();
                        return;
                    }

                    $mid = $payload['message_id'] ?? null;
                    if ($mid) {
                        $ins = DB::table('processed_messages')->insertOrIgnore([
                            'message_id'   => $mid,
                            'consumer'     => 'inventory',
                            'processed_at' => now(),
                        ]);
                        if ($ins === 0) { $this->line("dup: {$mid}"); $m->ack(); return; }
                    }

                    DB::transaction(function () use ($data) {
                        foreach ($data['items'] as $i) {
                            $sku = $i['sku']; $qty = (int) $i['qty'];
                            $p = Product::where('sku', $sku)->lockForUpdate()->firstOrFail();
                            if ($p->stock_qty - $p->reserved_qty < $qty) {
                                throw new \RuntimeException("insufficient stock for {$sku}");
                            }
                            $p->reserved_qty += $qty;
                            $p->save();

                            Reservation::updateOrCreate(
                                ['order_id' => $data['order_id'] ?? null, 'product_id' => $p->id],
                                ['qty' => $qty, 'status' => 'RESERVED']
                            );
                        }
                    });

                    $this->line("reserved: order_id=" . ($data['order_id'] ?? 'null') . " items=" . count($data['items']));

                    $pub->publish('inventory.x', 'inventory.reserved', [
                        'message_id'  => (string) Str::uuid(),
                        'type'        => 'inventory.reserved',
                        'occurred_at' => now()->toISOString(),
                        'data'        => $data,
                    ], [
                        'x-causation-id' => $mid,
                    ]);

                    $this->line('→ published inventory.reserved');
                    $m->ack();
                } catch (\Throwable $e) {
                    logger()->warning('inventory.reserve failed', ['err' => $e->getMessage()]);
                    $handleFail($m, $e);
                }
            }, [
                'messaging.system'      => 'rabbitmq',
                'messaging.destination' => 'inventory.reserve.q',
            ]);
        };

        $ch->basic_consume($q, '', false, false, false, false, $cb);
        while ($ch->is_consuming()) { $ch->wait(); }

        $ch->close(); $c->close();
        return self::SUCCESS;
    }
}
