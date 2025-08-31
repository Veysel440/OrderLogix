<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('idempotency_keys', function (Blueprint $t) {
            $t->id();
            $t->string('key', 80);
            $t->string('scope', 120);            // "POST /api/orders"
            $t->unsignedBigInteger('user_id')->nullable()->index();
            $t->string('request_hash', 64);
            $t->smallInteger('response_code')->nullable();
            $t->mediumText('response_body')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->unique(['key','scope','user_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('idempotency_keys'); }
};
