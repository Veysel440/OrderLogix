<?php

it('serves /api/metrics', function () {
    $res = $this->get('/api/metrics');
    $res->assertOk();
    expect($res->getContent())->toContain('orderlogix_orders_total');
});

it('serves /healthz', function () {
    $res = $this->get('/healthz');
    expect(in_array($res->getStatusCode(), [200,503], true))->toBeTrue();
    expect($res->json())->toHaveKeys(['status','db','amqp','mgmt','time']);
});
