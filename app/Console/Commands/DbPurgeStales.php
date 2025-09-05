<?php declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class DbPurgeStales extends Command
{
    protected $signature   = 'db:purge-stales {--pm-days=7} {--ik-days=2} {--batch=10000} {--dry-run=0}';
    protected $description = 'Purge old rows from processed_messages and idempotency_keys (batched, safe)';

    public function handle(): int
    {
        $pmDays = (int) $this->option('pm-days');
        $ikDays = (int) $this->option('ik-days');
        $batch  = max(100, (int) $this->option('batch'));
        $dry    = (bool) $this->option('dry-run');

        $pmCut = now()->subDays($pmDays);
        $ikCut = now()->subDays($ikDays);

        $pmTotal = 0;
        while (true) {
            $ids = DB::table('processed_messages')
                ->where('processed_at', '<', $pmCut)
                ->orderBy('processed_at')
                ->limit($batch)
                ->pluck('message_id');

            $n = $ids->count();
            if ($n === 0) break;

            $this->line("processed_messages → deleting {$n}...");
            if (!$dry) {
                DB::table('processed_messages')->whereIn('message_id', $ids)->delete();
            }
            $pmTotal += $n;
            if ($n < $batch) break;
        }
        $this->info("processed_messages purged: {$pmTotal}");

        $ikTotal = 0;
        while (true) {
            $ids = DB::table('idempotency_keys')
                ->where(function ($q) use ($ikCut) {
                    $q->whereNotNull('expires_at')->where('expires_at', '<', now())
                        ->orWhere(function ($q2) use ($ikCut) {
                            $q2->whereNull('expires_at')->where('created_at', '<', $ikCut);
                        });
                })
                ->orderBy('id')
                ->limit($batch)
                ->pluck('id');

            $n = $ids->count();
            if ($n === 0) break;

            $this->line("idempotency_keys → deleting {$n}...");
            if (!$dry) {
                DB::table('idempotency_keys')->whereIn('id', $ids)->delete();
            }
            $ikTotal += $n;
            if ($n < $batch) break;
        }
        $this->info("idempotency_keys purged: {$ikTotal}");

        return self::SUCCESS;
    }
}
