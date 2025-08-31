<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('processed_messages', function (Blueprint $t) {
            $t->id();
            $t->uuid('message_id');
            $t->string('consumer',64);
            $t->timestamp('processed_at')->useCurrent();
            $t->unique(['message_id','consumer']);
            $t->index('message_id');
        });
    }
    public function down(): void { Schema::dropIfExists('processed_messages'); }
};
