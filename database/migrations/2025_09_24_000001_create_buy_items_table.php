<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('buy_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('buy_id')->constrained('buys')->cascadeOnDelete();
            $table->string('material');
            $table->decimal('kgs', 10, 2);
            $table->decimal('precio_kg', 10, 2);
            $table->decimal('total', 12, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('buy_items');
    }
};