<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('watchlist_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('symbol', 24);
            $table->string('company_name', 180);
            $table->string('exchange', 32);
            $table->string('instrument_type', 16)->default('stock');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'symbol'], 'watchlist_user_symbol_unique');
            $table->index(['user_id', 'sort_order'], 'watchlist_user_sort_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('watchlist_items');
    }
};
