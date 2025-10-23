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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('total_purchased_price');
            $table->enum('status', ['waiting', 'on progress', 'completed'])->default('waiting');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bakery_id')->constrained('bakeries')->cascadeOnDelete();
            $table->timestamps();
            $table->index(['user_id', 'bakery_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
