<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('orders', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('status', 24)->index();
            $t->decimal('total',12,2)->default(0);
            $t->string('currency',3)->default('TRY');
            $t->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('orders'); }
};
