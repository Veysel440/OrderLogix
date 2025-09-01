<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('processed_messages', function (Blueprint $t) {
            if (!Schema::hasColumn('processed_messages', 'consumer')) {
                $t->string('consumer', 64)->default('default')->after('message_id');
            }
            $t->unique(['message_id','consumer'],'pm_uidx_msg_cons');
            $t->index(['consumer','processed_at'],'pm_idx_cons_time');
            $t->index('processed_at','pm_idx_time');
        });

        Schema::table('idempotency_keys', function (Blueprint $t) {
            if (!Schema::hasColumn('idempotency_keys','expires_at')) {
                $t->timestamp('expires_at')->nullable()->after('created_at');
            }
            $t->unique('key','ik_uidx_key');
            $t->index('created_at','ik_idx_created');
            $t->index('expires_at','ik_idx_expires');
        });
    }
    public function down(): void {
        Schema::table('processed_messages', function (Blueprint $t) {
            $t->dropUnique('pm_uidx_msg_cons');
            $t->dropIndex('pm_idx_cons_time');
            $t->dropIndex('pm_idx_time');
        });
        Schema::table('idempotency_keys', function (Blueprint $t) {
            $t->dropUnique('ik_uidx_key');
            $t->dropIndex('ik_idx_created');
            $t->dropIndex('ik_idx_expires');
            $t->dropColumn('expires_at');
        });
    }
};
