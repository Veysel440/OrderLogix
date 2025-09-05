<?php declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Telemetry;
use App\Support\Pulse;
use App\Support\EventSchema;
use App\Support\Trace;
use App\Services\Rabbit\ConnectionFactory;
use App\Services\Rabbit\Publisher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpAmqpLib\Message\AMQPMessage;

final class MqConsumeOrchestrator extends Command
{
    protected $signature = 'mq:consume:orchestrator';
    protected $description = 'Consumes order.placed and publishes inventory.reserve';

    public function handle(): int
    {
        $q = env('ORDERS_QUEUE', 'orders.q');
        $prefetch = (int) env('ORCHESTRATOR_PREFETCH', 32);

        $c  = ConnectionFactory::connect('orders');
        $ch = $c->channel();
        $ch->basic_qos(null, $prefetch, null);
        $pub = new Publisher($ch);

        $this->info("orchestrator ← {$q}");

        $cb = function (AMQPMessage $m) use ($pub) {
            $props = $m->get_properties();
            $tpIn  = \App\Support\Trace::fromAmqpHeaders($props);

            Telemetry::span('orchestrator.consume', function () use ($m, $props, $pub, $tpIn) {
                try {
                    $raw = $m->getBody();
                    if (($props['content_encoding'] ?? null) === 'gzip') { $raw = @gzdecode($raw) ?: $raw; }
                    $payload = json_decode($raw, true, 512, JSON_INVALID_UTF8_SUBSTITUTE) ?? [];
                    EventSchema::validate($payload);

                    $mid  = $props['message_id'] ?? $payload['message_id'] ?? null;
                    $data = $payload['data'] ?? $payload;
                    if (!$mid || empty($data['items'])) { $m->ack(); return; }

                    $ins = DB::table('processed_messages')->insertOrIgnore([
                        'message_id' => $mid, 'consumer' => 'orchestrator', 'processed_at' => now(),
                    ]);
                    if ($ins === 0) { $m->ack(); return; }

                    Pulse::send('orders','order.placed','ok',[
                        'order_id'=>$data['order_id']??null, 'items'=>count($data['items'])
                    ]);

                    $evt = [
                        'type'=>'inventory.reserve','v'=>1,
                        'message_id'=>(string) Str::uuid(),'occurred_at'=>now()->toISOString(),
                        'data'=>['order_id'=>$data['order_id']??null,'items'=>$data['items']],
                    ];
                    $hdr = ['x-causation-id'=>$mid,'x-correlation-id'=>$data['order_id']??null];
                    if ($tpIn) $hdr['traceparent'] = $tpIn;

                    $pub->publish('inventory.x','inventory.reserve',$evt,$hdr);
                } catch (\Throwable $e) {
                    logger()->warning('orchestrator error', ['err'=>$e->getMessage()]);
                } finally {
                    $m->ack();
                }
            }, ['messaging.system'=>'rabbitmq','messaging.destination'=>$q,'traceparent'=>$tpIn]);
        };

        $tag = $ch->basic_consume($q, 'orchestrator', false, false, false, false, $cb);

        $running = true;
        if (\function_exists('pcntl_signal')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGTERM, function() use (&$running, $ch, $tag) { $running = false; try { $ch->basic_cancel($tag); } catch (\Throwable) {} });
            pcntl_signal(SIGINT,  function() use (&$running, $ch, $tag) { $running = false; try { $ch->basic_cancel($tag); } catch (\Throwable) {} });
        }

        while ($running && $ch->is_consuming()) { $ch->wait(null, true, 1); }

        $ch->close(); $c->close();
        return self::SUCCESS;
    }
}
