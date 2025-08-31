<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('products', function (Blueprint $t) {
            $t->id();
            $t->string('sku')->unique();
            $t->string('name');
            $t->decimal('price',12,2);
            $t->unsignedInteger('stock_qty')->default(0);
            $t->unsignedInteger('reserved_qty')->default(0);
            $t->timestamps();
            $t->index(['name']);
        });
    }
    public function down(): void { Schema::dropIfExists('products'); }
};
