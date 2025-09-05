<?php declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

final class SmokeCheck extends Command
{
    protected $signature = 'health:smoke {--url=} {--api-key=} {--fail-on-degraded=1} {--timeout=3}';
    protected $description = 'Smoke test for DB and /healthz';

    public function handle(): int
    {
        $ok = true;

        try { \DB::select('select 1'); $this->line('db: ok'); }
        catch (\Throwable) { $this->error('db: err'); $ok = false; }

        $url = (string) ($this->option('url') ?: rtrim((string) config('app.url'), '/').'/healthz');
        try {
            $req = Http::timeout((int)$this->option('timeout'));
            if ($k = (string) $this->option('api-key')) $req = $req->withHeaders(['X-Api-Key' => $k]);
            $r = $req->get($url);

            $status = $r->ok() ? ($r->json('status') ?? 'unknown') : 'http_error';
            $this->line("healthz: {$status}");

            if ((int)$this->option('fail-on-degraded') === 1 && $status !== 'ok') $ok = false;
        } catch (\Throwable) {
            $this->error('healthz: unreachable');
            $ok = false;
        }

        $this->line($ok ? 'SMOKE_OK' : 'SMOKE_NOK');
        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
