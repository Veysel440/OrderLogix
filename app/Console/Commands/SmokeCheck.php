<?php declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SmokeCheck extends Command
{
    protected $signature = 'health:smoke {--url=} {--fail-on-degraded=1} {--timeout=3}';
    protected $description = 'Smoke test for /healthz and DB reachability';

    public function handle(): int
    {
        $ok = true;

        try { \DB::select('select 1'); $this->line('db: ok'); }
        catch (\Throwable $e) { $this->error('db: err'); $ok = false; }

        $url = $this->option('url') ?: rtrim(config('app.url'), '/').'/healthz';
        try {
            $r = Http::timeout((int)$this->option('timeout'))->get($url);
            $status = $r->ok() ? ($r->json('status') ?? 'unknown') : 'http_error';
            $this->line("healthz: {$status}");
            if ((int)$this->option('fail-on-degraded') === 1 && $status !== 'ok') { $ok = false; }
        } catch (\Throwable $e) {
            $this->error('healthz: unreachable');
            $ok = false;
        }

        $this->line($ok ? 'SMOKE_OK' : 'SMOKE_NOK');
        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
