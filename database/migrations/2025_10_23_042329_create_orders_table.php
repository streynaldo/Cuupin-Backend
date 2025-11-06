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
            $table->unsignedInteger('total_refunded_price');
            $table->enum('status', ['WAITING', 'ONPROGRESS', 'PAID', 'CONFIRMED', 'COMPLETED', 'CANCELLED', 'WITHDRAWN'])->default('WAITING');
            $table->string('reference_id');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bakery_id')->constrained('bakeries')->cascadeOnDelete();
            $table->timestamp('expired_at')->nullable();
            $table->text('payment_session_url')->nullable()->default(null);
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
