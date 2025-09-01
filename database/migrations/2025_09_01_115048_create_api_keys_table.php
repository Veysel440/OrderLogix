<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('api_keys', function (Blueprint $t) {
            $t->id();
            $t->string('name',120);
            $t->string('key_hash', 64)->unique();
            $t->json('abilities')->nullable();
            $t->unsignedBigInteger('user_id')->nullable()->index();
            $t->timestamp('last_used_at')->nullable();
            $t->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('api_keys'); }
};
