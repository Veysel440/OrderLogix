<?php


use Testcontainers\Containers\RabbitMQContainer;
use Testcontainers\Wait\Wait;

$rmq = null;

beforeAll(function () use (&$rmq) {
    $rmq = RabbitMQContainer::make('rabbitmq:3.13-management')
        ->wait(Wait::forHttp('/api/health/checks/alarms')->withPort(15672)->withStartupTimeout(60))
        ->start();

    $host = $rmq->getHost();
    $amqpPort = $rmq->getAmqpPort();
    $mgmtPort = $rmq->getHttpPort();

    putenv("RABBITMQ_HOST={$host}");
    putenv("RABBITMQ_PORT={$amqpPort}");
    putenv("RABBITMQ_USER=guest");
    putenv("RABBITMQ_PASSWORD=guest");
    putenv("RABBITMQ_VHOST=/");

    putenv("RABBITMQ_MGMT_URL=http://{$host}:{$mgmtPort}");
    putenv("RABBITMQ_MGMT_USER=guest");
    putenv("RABBITMQ_MGMT_PASSWORD=guest");

    /** @var \Illuminate\Foundation\Application $app */
    $app = require __DIR__.'/../bootstrap/app.php';
    $kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
    $kernel->call('migrate', ['--force' => true]);
});

afterAll(function () use (&$rmq) {
    if ($rmq) {
        $rmq->stop();
    }
});
