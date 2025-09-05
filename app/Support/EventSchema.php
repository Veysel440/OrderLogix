<?php declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;
use JsonException;
use JsonSchema\Validator;

final class EventSchema
{
    /** @var array<string, array<int, array<string,mixed>>> */
    private static array $cache = [];

    /** @return array<string, array<int, array<string,mixed>>> */
    private static function registry(): array
    {
        if (self::$cache) return self::$cache;

        $item = [
            'type'=>'object','required'=>['sku','qty'],
            'properties'=>['sku'=>['type'=>'string','minLength'=>1],'qty'=>['type'=>'integer','minimum'=>1]],
            'additionalProperties'=>false,
        ];
        $items = ['type'=>'array','minItems'=>1,'items'=>$item];

        $base = static fn(string $type, array $dataProps) => [
            '$schema'=>'https://json-schema.org/draft/2020-12/schema',
            'type'=>'object',
            'required'=>['type','v','occurred_at','data'],
            'properties'=>[
                'type'=>['const'=>$type],
                'v'=>['type'=>'integer','minimum'=>1],
                'occurred_at'=>['type'=>'string'],
                'message_id'=>['type'=>['string','null']],
                'data'=>$dataProps,
            ],
            'additionalProperties'=>true,
        ];

        self::$cache = [
            'order.placed' => [
                1 => $base('order.placed', [
                    'type'=>'object','required'=>['items'],
                    'properties'=>['order_id'=>['type'=>['integer','string','null']], 'items'=>$items],
                    'additionalProperties'=>true,
                ]),
            ],
            'order.status_changed' => [
                1 => $base('order.status_changed', [
                    'type'=>'object','required'=>['order_id','status'],
                    'properties'=>[
                        'order_id'=>['type'=>['integer','string']],
                        'status'  =>['type'=>'string','minLength'=>1],
                    ],
                    'additionalProperties'=>true,
                ]),
            ],

            'inventory.reserve' => [
                1 => $base('inventory.reserve', [
                    'type'=>'object','required'=>['items'],
                    'properties'=>['order_id'=>['type'=>['integer','string','null']], 'items'=>$items],
                    'additionalProperties'=>true,
                ]),
            ],
            'inventory.reserved' => [
                1 => $base('inventory.reserved', ['$ref'=>'#/$defs/payload']) + [
                        '$defs'=>[
                            'payload'=>[
                                'type'=>'object','required'=>['items'],
                                'properties'=>['order_id'=>['type'=>['integer','string','null']], 'items'=>$items],
                                'additionalProperties'=>true,
                            ],
                        ],
                    ],
            ],
            'inventory.release' => [
                1 => $base('inventory.release', [
                    'type'=>'object','required'=>['order_id','items'],
                    'properties'=>['order_id'=>['type'=>['integer','string','null']], 'items'=>$items],
                    'additionalProperties'=>true,
                ]),
            ],


            'payment.authorize' => [
                1 => $base('payment.authorize', [
                    'type'=>'object','required'=>['order_id','amount'],
                    'properties'=>[
                        'order_id'=>['type'=>['integer','string']],
                        'amount'  =>['type'=>'number','minimum'=>0],
                    ],
                    'additionalProperties'=>true,
                ]),
            ],
            'payment.authorized' => [
                1 => $base('payment.authorized', [
                    'type'=>'object','required'=>['order_id'],
                    'properties'=>['order_id'=>['type'=>['integer','string']]],
                    'additionalProperties'=>true,
                ]),
            ],
            'payment.failed' => [
                1 => $base('payment.failed', [
                    'type'=>'object','required'=>['order_id','reason'],
                    'properties'=>['order_id'=>['type'=>['integer','string']], 'reason'=>['type'=>'string','minLength'=>1]],
                    'additionalProperties'=>true,
                ]),
            ],
            'payment.refund' => [
                1 => $base('payment.refund', [
                    'type'=>'object','required'=>['order_id','reason'],
                    'properties'=>['order_id'=>['type'=>['integer','string']], 'reason'=>['type'=>'string','minLength'=>1]],
                    'additionalProperties'=>true,
                ]),
            ],

            'shipping.schedule' => [
                1 => $base('shipping.schedule', [
                    'type'=>'object','required'=>['order_id','address'],
                    'properties'=>['order_id'=>['type'=>['integer','string']], 'address'=>['type'=>'string','minLength'=>3]],
                    'additionalProperties'=>true,
                ]),
            ],
            'shipping.scheduled' => [
                1 => $base('shipping.scheduled', [
                    'type'=>'object','required'=>['order_id','carrier','eta'],
                    'properties'=>['order_id'=>['type'=>['integer','string']], 'carrier'=>['type'=>'string','minLength'=>2], 'eta'=>['type'=>'string']],
                    'additionalProperties'=>true,
                ]),
            ],
            'shipping.failed' => [
                1 => $base('shipping.failed', [
                    'type'=>'object','required'=>['order_id','reason'],
                    'properties'=>['order_id'=>['type'=>['integer','string']], 'reason'=>['type'=>'string','minLength'=>1]],
                    'additionalProperties'=>true,
                ]),
            ],
        ];

        return self::$cache;
    }

    /** @return array{ok:true}|array{ok:false,errors:string[]} */
    public static function try(array $event): array
    {
        $type = $event['type'] ?? null;
        $v    = (int)($event['v'] ?? 0);
        $reg  = self::registry();
        if (!$type || $v < 1 || !isset($reg[$type][$v])) {
            return ['ok'=>false,'errors'=>["unknown schema: {$type}@{$v}"]];
        }

        $schema = $reg[$type][$v];
        try {
            $obj = json_decode(json_encode($event, JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            return ['ok'=>false,'errors'=>['invalid json: '.$e->getMessage()]];
        }

        $vdr = new Validator(); $vdr->validate($obj, $schema);
        if ($vdr->isValid()) return ['ok'=>true];

        $errs = array_map(
            static fn(array $e) => sprintf('%s: %s', $e['property'] ?? '(root)', $e['message'] ?? 'invalid'),
            $vdr->getErrors()
        );
        return ['ok'=>false,'errors'=>$errs];
    }

    public static function validate(array $event): void
    {
        $r = self::try($event);
        if ($r['ok'] === true) return;
        throw new InvalidArgumentException('schema validation failed: '.implode('; ', $r['errors']));
    }
}
