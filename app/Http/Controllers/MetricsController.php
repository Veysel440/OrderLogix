<?php declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

final class MetricsController
{
    public function __invoke()
    {
        $outboxPending = (int) DB::table('outbox_events')->whereNull('published_at')->count();
        $processed1m   = (int) DB::table('processed_messages')->where('processed_at','>',now()->subMinute())->count();
        $ordersTotal   = (int) DB::table('orders')->count();
        $resReserved   = (int) DB::table('reservations')->where('status','RESERVED')->sum('qty');
        $payAuth       = (int) DB::table('payments')->where('status','AUTHORIZED')->count();

        $lines = [];
        $lines[] = "# HELP orderlogix_outbox_pending Outbox'ta bekleyen event sayısı";
        $lines[] = "# TYPE orderlogix_outbox_pending gauge";
        $lines[] = "orderlogix_outbox_pending {$outboxPending}";
        $lines[] = "# HELP orderlogix_processed_last_min Son 1 dakikada işlenen mesaj sayısı";
        $lines[] = "# TYPE orderlogix_processed_last_min counter";
        $lines[] = "orderlogix_processed_last_min {$processed1m}";
        $lines[] = "# HELP orderlogix_orders_total Sipariş toplamı";
        $lines[] = "# TYPE orderlogix_orders_total counter";
        $lines[] = "orderlogix_orders_total {$ordersTotal}";
        $lines[] = "# HELP orderlogix_reservations_qty_reserved Rezerve miktar";
        $lines[] = "# TYPE orderlogix_reservations_qty_reserved gauge";
        $lines[] = "orderlogix_reservations_qty_reserved {$resReserved}";
        $lines[] = "# HELP orderlogix_payments_authorized Yetkilendirilen ödeme sayısı";
        $lines[] = "# TYPE orderlogix_payments_authorized counter";
        $lines[] = "orderlogix_payments_authorized {$payAuth}";

        return response(implode("\n", $lines)."\n", 200)
            ->header('Content-Type','text/plain; version=0.0.4; charset=utf-8');
    }
}
