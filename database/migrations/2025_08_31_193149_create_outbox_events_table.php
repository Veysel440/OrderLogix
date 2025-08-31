<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('outbox_events', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('type',64)->index();
            $t->json('payload');
            $t->timestamp('occurred_at')->useCurrent();
            $t->timestamp('published_at')->nullable();
            $t->string('aggregate_type',64)->nullable();
            $t->string('aggregate_id',64)->nullable()->index();
        });
    }
    public function down(): void { Schema::dropIfExists('outbox_events'); }
};
