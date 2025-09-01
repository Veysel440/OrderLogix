<?php declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

class MqDeclareAll extends Command
{
    protected $signature = 'mq:declare:all';
    protected $description = 'Declare all vhosts';

    public function handle(): int
    {
        $this->call('mq:declare:orders');
        $this->call('mq:declare:inventory');
        $this->call('mq:declare:payments');
        return self::SUCCESS;
    }
}
