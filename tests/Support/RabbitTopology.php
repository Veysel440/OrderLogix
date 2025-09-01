<?php


use App\Services\Rabbit\ConnectionFactory;
use PhpAmqpLib\Wire\AMQPTable;

function declareTestTopology(): void {
    $c = ConnectionFactory::connect('orders');
    $ch = $c->channel();

    $ch->exchange_declare('orders.x','topic',false,true,false);
    $ch->exchange_declare('orders.dlx','topic',false,true,false);
    $ch->queue_declare('orders.q',false,true,false,false,false,new AMQPTable([
        'x-queue-type'=>'quorum',
        'x-dead-letter-exchange'=>'orders.dlx',
    ]));
    $ch->queue_bind('orders.q','orders.x','order.placed');

    $ch->exchange_declare('inventory.x','topic',false,true,false);
    $ch->exchange_declare('inventory.dlx','topic',false,true,false);
    $ch->queue_declare('inventory.reserve.q',false,true,false,false,false,new AMQPTable([
        'x-queue-type'=>'quorum',
        'x-dead-letter-exchange'=>'inventory.dlx',
    ]));
    $ch->queue_bind('inventory.reserve.q','inventory.x','inventory.reserve');

    $ch->queue_declare('inventory.retry',false,true,false,false,false,new AMQPTable([
        'x-queue-type'=>'classic',
        'x-dead-letter-exchange'=>'inventory.x',
        'x-dead-letter-routing-key'=>'inventory.reserve',
    ]));

    $ch->close(); $c->close();
}
