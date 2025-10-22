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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('external_id')->unique(); // referensi kamu (UUID)
            $table->string('charge_id')->nullable(); // ewc_xxx (Xendit)
            $table->string('channel_code');          // ID_OVO, ID_DANA, ID_SHOPEEPAY, ID_LINKAJA
            $table->unsignedBigInteger('amount');
            $table->string('currency')->default('IDR');
            $table->string('status')->default('PENDING'); // PENDING, SUCCEEDED, FAILED, VOIDED, REFUNDED
            $table->json('items')->nullable();
            $table->json('metadata')->nullable();
            $table->json('raw')->nullable();         // payload Xendit utk audit
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
