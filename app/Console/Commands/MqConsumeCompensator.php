<?php declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Rabbit\ConnectionFactory;
use App\Services\Rabbit\Publisher;
use App\Support\EventSchema;
use App\Support\Telemetry;
use App\Support\Trace;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpAmqpLib\Message\AMQPMessage;

class MqConsumeCompensator extends Command
{
    protected $signature = 'mq:consume:compensator {--once}';
    protected $description = 'Listens shipping.failed and triggers payment.refund + inventory.release';

    public function handle(): int
    {
        $prefetch=(int)env('COMP_PREFETCH',16);
        $runOnce=(bool)$this->option('once');

        $cShip=ConnectionFactory::connect('shipping');
        $cPay =ConnectionFactory::connect('payments');
        $cInv =ConnectionFactory::connect('inventory');

        $chShip=$cShip->channel(); $chShip->basic_qos(null,$prefetch,null);
        $chPay =$cPay ->channel();
        $chInv =$cInv ->channel();

        $pubPay=new Publisher($chPay);
        $pubInv=new Publisher($chInv);

        $q='shipping.failed.q';
        $this->info("compensator listening on {$q}");

        $cb=function(AMQPMessage $m) use($pubPay,$pubInv){
            $props=$m->get_properties();
            $tpIn =Trace::fromAmqpHeaders($props);
            Telemetry::span('compensator.consume', function() use($m,$props,$pubPay,$pubInv,$tpIn){
                $raw=$m->getBody();
                if(($props['content_encoding']??null)==='gzip'){ $raw=@gzdecode($raw)?:$raw; }
                $payload=json_decode($raw,true,512,JSON_INVALID_UTF8_SUBSTITUTE);
                EventSchema::validate($payload); // shipping.failed

                $mid=$props['message_id']??$payload['message_id']??null;
                $data=$payload['data']??[];
                $orderId=$data['order_id']??null;

                if($mid){
                    $ins=DB::table('processed_messages')->insertOrIgnore([
                        'message_id'=>$mid,'consumer'=>'compensator','processed_at'=>now(),
                    ]);
                    if($ins===0){ $m->ack(); return; }
                }

                $hdr=['x-causation-id'=>$mid,'x-correlation-id'=>$orderId];
                if($tpIn){ $hdr['traceparent']=$tpIn; }

                $pubInv->publish('inventory.x','inventory.release',[
                    'type'=>'inventory.release','v'=>1,'message_id'=>(string)Str::uuid(),
                    'occurred_at'=>now()->toISOString(),
                    'data'=>['order_id'=>$orderId,'items'=>$data['items']??[]],
                ],$hdr);

                $pubPay->publish('payments.x','payment.refund',[
                    'type'=>'payment.refund','v'=>1,'message_id'=>(string)Str::uuid(),
                    'occurred_at'=>now()->toISOString(),
                    'data'=>['order_id'=>$orderId,'reason'=>'shipping_failed'],
                ],$hdr);

                $m->ack();
            },['messaging.system'=>'rabbitmq','messaging.destination'=>'shipping.failed.q','traceparent'=>$tpIn]);
        };

        $chShip->basic_consume($q,'',false,false,false,false,$cb);
        do { try{$chShip->wait(null,false,5);}catch(\PhpAmqpLib\Exception\AMQPTimeoutException){} }
        while(!$runOnce && $chShip->is_consuming());

        $chShip->close(); $cShip->close();
        $chPay->close();  $cPay->close();
        $chInv->close();  $cInv->close();
        return self::SUCCESS;
    }
}
