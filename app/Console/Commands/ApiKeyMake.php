<?php declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ApiKey;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ApiKeyMake extends Command
{
    protected $signature = 'apikey:make {name} {--ability=*} {--user-id=}';
    protected $description = 'Create API key with abilities';

    public function handle(): int
    {
        $plain = Str::uuid()->toString().'.'.Str::random(32);
        $hash = hash('sha256', $plain);

        $row = ApiKey::create([
            'name' => $this->argument('name'),
            'key_hash' => $hash,
            'abilities' => $this->option('ability') ?: [],
            'user_id' => $this->option('user-id') ?: null,
        ]);
        $this->line('API Key (yalnızca bir kez gösterilir):');
        $this->info($plain);
        $this->line('Hash: '.$row->key_hash);
        return self::SUCCESS;
    }
}
