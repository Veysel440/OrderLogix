<?php declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Telemetry;
use App\Support\Pulse;
use App\Support\EventSchema;
use App\Services\Rabbit\ConnectionFactory;
use App\Services\Rabbit\Publisher;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use PhpAmqpLib\Message\AMQPMessage;

final class MqConsumePaymentAuthorize extends Command
{
    protected $signature = 'mq:consume:payment-authorize';
    protected $description = 'Consumes payment.authorize and emits payment.authorized or payment.failed';

    public function handle(): int
    {
        $q = env('PAYMENTS_AUTH_QUEUE', 'payments.auth.q');
        $prefetch = (int) env('PAYMENTS_PREFETCH', 16);

        $c  = ConnectionFactory::connect('payments');
        $ch = $c->channel();
        $ch->basic_qos(null, $prefetch, null);
        $pub = new Publisher($ch);

        $this->info("payments ← {$q}");

        $cb = function (AMQPMessage $m) use ($pub) {
            Telemetry::span('payments.authorize.consume', function () use ($m, $pub) {
                try {
                    $raw = $m->getBody();
                    $props = $m->get_properties();
                    if (($props['content_encoding'] ?? null) === 'gzip') { $raw = @gzdecode($raw) ?: $raw; }
                    $p = json_decode($raw, true, 512, JSON_INVALID_UTF8_SUBSTITUTE) ?? [];

                    try { EventSchema::validate(['type'=>'payment.authorize','v'=>1,'occurred_at'=>now()->toISOString(),'data'=>$p['data']??[]]); } catch (\Throwable) {}

                    $data = $p['data'] ?? [];
                    $orderId = $data['order_id'] ?? null;
                    $ok = isset($data['amount']) && $data['amount'] > 0;

                    $type = $ok ? 'payment.authorized' : 'payment.failed';
                    $evt  = [
                        'type'=>$type,'v'=>1,'message_id'=>(string) Str::uuid(),
                        'occurred_at'=>now()->toISOString(),
                        'data'=>['order_id'=>$orderId,'reason'=>$ok?null:'invalid_amount'],
                    ];

                    try { EventSchema::validate($evt); } catch (\Throwable) {}

                    $pub->publish('payments.x', $type, $evt);
                    Pulse::send('payments',$type,$ok?'ok':'err',['order_id'=>$orderId]);
                } catch (\Throwable $e) {
                    logger()->warning('payments.authorize error', ['err'=>$e->getMessage()]);
                } finally {
                    $m->ack();
                }
            }, ['messaging.system'=>'rabbitmq','messaging.destination'=>$q]);
        };

        $tag = $ch->basic_consume($q, 'payments', false, false, false, false, $cb);

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
