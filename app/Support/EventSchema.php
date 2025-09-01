<?php declare(strict_types=1);

namespace App\Support;

use JsonSchema\Validator;

final class EventSchema
{
    /** @var array<string, array<int, array<string,mixed>>> */
    private static array $reg = [
        'order.placed' => [
            1 => [
                '$schema'=>'https://json-schema.org/draft/2020-12/schema',
                'type'=>'object',
                'required'=>['type','v','occurred_at','data'],
                'properties'=>[
                    'type'=>['const'=>'order.placed'],
                    'v'=>['type'=>'integer','minimum'=>1],
                    'occurred_at'=>['type'=>'string'],
                    'message_id'=>['type'=>['string','null']],
                    'data'=>[
                        'type'=>'object',
                        'required'=>['items'],
                        'properties'=>[
                            'order_id'=>['type'=>['integer','string','null']],
                            'items'=>[
                                'type'=>'array','minItems'=>1,
                                'items'=>[
                                    'type'=>'object','required'=>['sku','qty'],
                                    'properties'=>[
                                        'sku'=>['type'=>'string','minLength'=>1],
                                        'qty'=>['type'=>'integer','minimum'=>1],
                                    ],
                                    'additionalProperties'=>false
                                ]
                            ],
                        ],
                        'additionalProperties'=>true
                    ],
                ],
                'additionalProperties'=>true
            ],
        ],
        'inventory.reserve' => [
            1 => [
                '$schema'=>'https://json-schema.org/draft/2020-12/schema',
                'type'=>'object',
                'required'=>['type','v','occurred_at','data'],
                'properties'=>[
                    'type'=>['const'=>'inventory.reserve'],
                    'v'=>['type'=>'integer','minimum'=>1],
                    'occurred_at'=>['type'=>'string'],
                    'message_id'=>['type'=>'string'],
                    'data'=>[
                        'type'=>'object',
                        'required'=>['items'],
                        'properties'=>[
                            'order_id'=>['type'=>['integer','string','null']],
                            'items'=>[
                                'type'=>'array','minItems'=>1,
                                'items'=>[
                                    'type'=>'object','required'=>['sku','qty'],
                                    'properties'=>[
                                        'sku'=>['type'=>'string','minLength'=>1],
                                        'qty'=>['type'=>'integer','minimum'=>1],
                                    ],
                                    'additionalProperties'=>false
                                ]
                            ],
                        ],
                        'additionalProperties'=>true
                    ],
                ],
                'additionalProperties'=>true
            ],
        ],
        'inventory.reserved' => [
            1 => [
                '$schema'=>'https://json-schema.org/draft/2020-12/schema',
                'type'=>'object',
                'required'=>['type','v','occurred_at','data'],
                'properties'=>[
                    'type'=>['const'=>'inventory.reserved'],
                    'v'=>['type'=>'integer','minimum'=>1],
                    'occurred_at'=>['type'=>'string'],
                    'message_id'=>['type'=>'string'],
                    'data'=>['$ref'=>'#/$defs/payload'],
                ],
                '$defs'=>[
                    'payload'=>[
                        'type'=>'object',
                        'required'=>['items'],
                        'properties'=>[
                            'order_id'=>['type'=>['integer','string','null']],
                            'items'=>[
                                'type'=>'array','minItems'=>1,
                                'items'=>[
                                    'type'=>'object','required'=>['sku','qty'],
                                    'properties'=>[
                                        'sku'=>['type'=>'string','minLength'=>1],
                                        'qty'=>['type'=>'integer','minimum'=>1],
                                    ],
                                    'additionalProperties'=>false
                                ]
                            ],
                        ],
                        'additionalProperties'=>true
                    ]
                ],
                'additionalProperties'=>true
            ],
        ],
        'payment.failed' => [
            1 => [
                '$schema'=>'https://json-schema.org/draft/2020-12/schema',
                'type'=>'object',
                'required'=>['type','v','occurred_at','data'],
                'properties'=>[
                    'type'=>['const'=>'payment.failed'],
                    'v'=>['type'=>'integer','minimum'=>1],
                    'occurred_at'=>['type'=>'string'],
                    'message_id'=>['type'=>'string'],
                    'data'=>[
                        'type'=>'object',
                        'required'=>['order_id','reason'],
                        'properties'=>[
                            'order_id'=>['type'=>['integer','string']],
                            'reason'=>['type'=>'string','minLength'=>1],
                        ],
                        'additionalProperties'=>true
                    ],
                ],
                'additionalProperties'=>true
            ],
        ],
    ];

    public static function validate(array $event): void
    {
        $type = $event['type'] ?? null;
        $v    = (int)($event['v'] ?? 0);
        if (!$type || $v < 1 || !isset(self::$reg[$type][$v])) {
            throw new \InvalidArgumentException("unknown schema: {$type}@{$v}");
        }
        $schema = self::$reg[$type][$v];
        $obj = json_decode(json_encode($event), false, 512, JSON_THROW_ON_ERROR);
        $validator = new Validator();
        $validator->validate($obj, $schema);
        if (!$validator->isValid()) {
            $errs = array_map(fn($e)=>"{$e['property']}: {$e['message']}", $validator->getErrors());
            throw new \InvalidArgumentException('schema validation failed: '.implode('; ',$errs));
        }
    }
}
