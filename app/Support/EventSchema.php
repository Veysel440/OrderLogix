<?php declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;
use JsonException;
use JsonSchema\Validator;

final class EventSchema
{
    /**
     * Build the immutable registry.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    private static function registry(): array
    {
        $itemSchema = [
            'type'       => 'object',
            'required'   => ['sku', 'qty'],
            'properties' => [
                'sku' => ['type' => 'string', 'minLength' => 1],
                'qty' => ['type' => 'integer', 'minimum' => 1],
            ],
            'additionalProperties' => false,
        ];

        $itemsArray = [
            'type'     => 'array',
            'minItems' => 1,
            'items'    => $itemSchema,
        ];

        return [
            'shipping.schedule' => [
                1 => [
                    '$schema'   => 'https://json-schema.org/draft/2020-12/schema',
                    'type'      => 'object',
                    'required'  => ['type', 'v', 'occurred_at', 'data'],
                    'properties'=> [
                        'type'        => ['const' => 'shipping.schedule'],
                        'v'           => ['type' => 'integer', 'minimum' => 1],
                        'occurred_at' => ['type' => 'string'],
                        'message_id'  => ['type' => 'string'],
                        'data'        => [
                            'type'       => 'object',
                            'required'   => ['order_id', 'address'],
                            'properties' => [
                                'order_id' => ['type' => ['integer', 'string']],
                                'address'  => ['type' => 'string', 'minLength' => 3],
                            ],
                            'additionalProperties' => true,
                        ],
                    ],
                    'additionalProperties' => true,
                ],
            ],

            'shipping.scheduled' => [
                1 => [
                    '$schema'   => 'https://json-schema.org/draft/2020-12/schema',
                    'type'      => 'object',
                    'required'  => ['type', 'v', 'occurred_at', 'data'],
                    'properties'=> [
                        'type'        => ['const' => 'shipping.scheduled'],
                        'v'           => ['type' => 'integer', 'minimum' => 1],
                        'occurred_at' => ['type' => 'string'],
                        'message_id'  => ['type' => 'string'],
                        'data'        => [
                            'type'       => 'object',
                            'required'   => ['order_id', 'carrier', 'eta'],
                            'properties' => [
                                'order_id' => ['type' => ['integer', 'string']],
                                'carrier'  => ['type' => 'string', 'minLength' => 2],
                                'eta'      => ['type' => 'string'],
                            ],
                            'additionalProperties' => true,
                        ],
                    ],
                    'additionalProperties' => true,
                ],
            ],

            'shipping.failed' => [
                1 => [
                    '$schema'   => 'https://json-schema.org/draft/2020-12/schema',
                    'type'      => 'object',
                    'required'  => ['type', 'v', 'occurred_at', 'data'],
                    'properties'=> [
                        'type'        => ['const' => 'shipping.failed'],
                        'v'           => ['type' => 'integer', 'minimum' => 1],
                        'occurred_at' => ['type' => 'string'],
                        'message_id'  => ['type' => 'string'],
                        'data'        => [
                            'type'       => 'object',
                            'required'   => ['order_id', 'reason'],
                            'properties' => [
                                'order_id' => ['type' => ['integer', 'string']],
                                'reason'   => ['type' => 'string', 'minLength' => 1],
                            ],
                            'additionalProperties' => true,
                        ],
                    ],
                    'additionalProperties' => true,
                ],
            ],

            'inventory.release' => [
                1 => [
                    '$schema'   => 'https://json-schema.org/draft/2020-12/schema',
                    'type'      => 'object',
                    'required'  => ['type', 'v', 'occurred_at', 'data'],
                    'properties'=> [
                        'type'        => ['const' => 'inventory.release'],
                        'v'           => ['type' => 'integer', 'minimum' => 1],
                        'occurred_at' => ['type' => 'string'],
                        'message_id'  => ['type' => 'string'],
                        'data'        => [
                            'type'       => 'object',
                            'required'   => ['order_id', 'items'],
                            'properties' => [
                                'order_id' => ['type' => ['integer', 'string', 'null']],
                                'items'    => $itemsArray,
                            ],
                            'additionalProperties' => true,
                        ],
                    ],
                    'additionalProperties' => true,
                ],
            ],

            'payment.refund' => [
                1 => [
                    '$schema'   => 'https://json-schema.org/draft/2020-12/schema',
                    'type'      => 'object',
                    'required'  => ['type', 'v', 'occurred_at', 'data'],
                    'properties'=> [
                        'type'        => ['const' => 'payment.refund'],
                        'v'           => ['type' => 'integer', 'minimum' => 1],
                        'occurred_at' => ['type' => 'string'],
                        'message_id'  => ['type' => 'string'],
                        'data'        => [
                            'type'       => 'object',
                            'required'   => ['order_id', 'reason'],
                            'properties' => [
                                'order_id' => ['type' => ['integer', 'string']],
                                'reason'   => ['type' => 'string', 'minLength' => 1],
                            ],
                            'additionalProperties' => true,
                        ],
                    ],
                    'additionalProperties' => true,
                ],
            ],

            'order.placed' => [
                1 => [
                    '$schema'   => 'https://json-schema.org/draft/2020-12/schema',
                    'type'      => 'object',
                    'required'  => ['type', 'v', 'occurred_at', 'data'],
                    'properties'=> [
                        'type'        => ['const' => 'order.placed'],
                        'v'           => ['type' => 'integer', 'minimum' => 1],
                        'occurred_at' => ['type' => 'string'],
                        'message_id'  => ['type' => ['string', 'null']],
                        'data'        => [
                            'type'       => 'object',
                            'required'   => ['items'],
                            'properties' => [
                                'order_id' => ['type' => ['integer', 'string', 'null']],
                                'items'    => $itemsArray,
                            ],
                            'additionalProperties' => true,
                        ],
                    ],
                    'additionalProperties' => true,
                ],
            ],

            'inventory.reserve' => [
                1 => [
                    '$schema'   => 'https://json-schema.org/draft/2020-12/schema',
                    'type'      => 'object',
                    'required'  => ['type', 'v', 'occurred_at', 'data'],
                    'properties'=> [
                        'type'        => ['const' => 'inventory.reserve'],
                        'v'           => ['type' => 'integer', 'minimum' => 1],
                        'occurred_at' => ['type' => 'string'],
                        'message_id'  => ['type' => 'string'],
                        'data'        => [
                            'type'       => 'object',
                            'required'   => ['items'],
                            'properties' => [
                                'order_id' => ['type' => ['integer', 'string', 'null']],
                                'items'    => $itemsArray,
                            ],
                            'additionalProperties' => true,
                        ],
                    ],
                    'additionalProperties' => true,
                ],
            ],

            'inventory.reserved' => [
                1 => [
                    '$schema'   => 'https://json-schema.org/draft/2020-12/schema',
                    'type'      => 'object',
                    'required'  => ['type', 'v', 'occurred_at', 'data'],
                    'properties'=> [
                        'type'        => ['const' => 'inventory.reserved'],
                        'v'           => ['type' => 'integer', 'minimum' => 1],
                        'occurred_at' => ['type' => 'string'],
                        'message_id'  => ['type' => 'string'],
                        'data'        => ['$ref' => '#/$defs/payload'],
                    ],
                    '$defs' => [
                        'payload' => [
                            'type'       => 'object',
                            'required'   => ['items'],
                            'properties' => [
                                'order_id' => ['type' => ['integer', 'string', 'null']],
                                'items'    => $itemsArray,
                            ],
                            'additionalProperties' => true,
                        ],
                    ],
                    'additionalProperties' => true,
                ],
            ],

            'payment.failed' => [
                1 => [
                    '$schema'   => 'https://json-schema.org/draft/2020-12/schema',
                    'type'      => 'object',
                    'required'  => ['type', 'v', 'occurred_at', 'data'],
                    'properties'=> [
                        'type'        => ['const' => 'payment.failed'],
                        'v'           => ['type' => 'integer', 'minimum' => 1],
                        'occurred_at' => ['type' => 'string'],
                        'message_id'  => ['type' => 'string'],
                        'data'        => [
                            'type'       => 'object',
                            'required'   => ['order_id', 'reason'],
                            'properties' => [
                                'order_id' => ['type' => ['integer', 'string']],
                                'reason'   => ['type' => 'string', 'minLength' => 1],
                            ],
                            'additionalProperties' => true,
                        ],
                    ],
                    'additionalProperties' => true,
                ],
            ],
        ];
    }

    public static function validate(array $event): void
    {
        $type = $event['type'] ?? null;
        $v    = (int)($event['v'] ?? 0);

        $reg = self::registry();
        if (!$type || $v < 1 || !isset($reg[$type][$v])) {
            throw new InvalidArgumentException("unknown schema: {$type}@{$v}");
        }

        $schema = $reg[$type][$v];

        try {
            $obj = json_decode(json_encode($event, JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new InvalidArgumentException('invalid event payload: '.$e->getMessage(), previous: $e);
        }

        $validator = new Validator();
        $validator->validate($obj, $schema);

        if (!$validator->isValid()) {
            $errs = array_map(
                static fn(array $e) => sprintf('%s: %s', $e['property'] ?? '(root)', $e['message'] ?? 'invalid'),
                $validator->getErrors()
            );
            throw new InvalidArgumentException('schema validation failed: '.implode('; ', $errs));
        }
    }
}
