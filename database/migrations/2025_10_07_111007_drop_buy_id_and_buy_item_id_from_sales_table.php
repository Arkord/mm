<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropForeign(['buy_id']);
            $table->dropForeign(['buy_item_id']);
            $table->dropColumn('buy_id');
            $table->dropColumn('buy_item_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->foreignId('buy_id')->constrained('buys')->onDelete('cascade');
            $table->foreignId('buy_item_id')->constrained('buy_items')->onDelete('cascade');
        });
    }
};
?>