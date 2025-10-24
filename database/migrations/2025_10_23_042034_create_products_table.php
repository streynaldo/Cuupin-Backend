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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('product_name');
            $table->text('description')->nullable();
            $table->unsignedInteger('price');
            $table->unsignedTinyInteger('best_before')->nullable();
            $table->string('image_url')->nullable();
            $table->unsignedInteger('discount_price')->nullable();
            $table->foreignId('bakery_id')->constrained('bakeries')->cascadeOnDelete();
            $table->foreignId('discount_id')->nullable()->constrained('discount_events')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
