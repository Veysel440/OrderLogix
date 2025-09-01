<?php declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DbPurgeStales extends Command
{
    protected $signature = 'db:purge-stales {--pm-days=7} {--ik-days=2} {--batch=10000} {--dry-run=0}';
    protected $description = 'Purge old rows from processed_messages and idempotency_keys';

    public function handle(): int
    {
        $pmDays = (int)$this->option('pm-days');
        $ikDays = (int)$this->option('ik-days');
        $batch  = (int)$this->option('batch');
        $dry    = (bool)$this->option('dry-run');

        $pmCut = now()->subDays($pmDays);
        $ikCut = now()->subDays($ikDays);

        $cntPm = DB::table('processed_messages')->where('processed_at','<',$pmCut)->limit($batch)->count();
        $this->info("processed_messages purge candidates: {$cntPm}");
        if (!$dry && $cntPm>0) {
            DB::table('processed_messages')->where('processed_at','<',$pmCut)->limit($batch)->delete();
        }

        $cntIk = DB::table('idempotency_keys')
            ->where(function($q) use($ikCut){
                $q->whereNotNull('expires_at')->where('expires_at','<',now())
                    ->orWhere(function($q2) use($ikCut){ $q2->whereNull('expires_at')->where('created_at','<',$ikCut); });
            })
            ->limit($batch)->count();
        $this->info("idempotency_keys purge candidates: {$cntIk}");
        if (!$dry && $cntIk>0) {
            DB::table('idempotency_keys')
                ->where(function($q) use($ikCut){
                    $q->whereNotNull('expires_at')->where('expires_at','<',now())
                        ->orWhere(function($q2) use($ikCut){ $q2->whereNull('expires_at')->where('created_at','<',$ikCut); });
                })
                ->limit($batch)->delete();
        }

        return self::SUCCESS;
    }
}
