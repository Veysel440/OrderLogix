<?php declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

final class MqDeclareAll extends Command
{
    protected $signature   = 'mq:declare:all';
    protected $description = 'Declare all vhosts (orders, inventory, payments, shipping if available)';

    public function handle(): int
    {
        $this->call('mq:declare:orders');
        $this->call('mq:declare:inventory');
        $this->call('mq:declare:payments');

        try { $this->call('mq:declare:shipping'); } catch (\Throwable) {}

        return self::SUCCESS;
    }
}
