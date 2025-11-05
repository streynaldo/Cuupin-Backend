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
        Schema::create('bakery_wallets', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('total_wallet')->default(0);
            $table->unsignedInteger('total_earned')->default(0);
            $table->unsignedInteger('total_withdrawn')->default(0);
            $table->string('no_rekening')->nullable()->unique();
            $table->enum('nama_bank', ['ID_BCA', 'ID_MANDIRI', 'ID_BRI', 'ID_BNI'])->nullable();
            $table->string('nama_pemilik')->nullable();
            $table->foreignId('bakery_id')->constrained('bakeries')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bakery_wallets');
    }
};
