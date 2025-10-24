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
        Schema::create('discount_events', function (Blueprint $table) {
            $table->id();
            $table->string('discount_name');
            $table->unsignedInteger('discount');
            $table->string('discount_photo')->nullable();
            $table->dateTime('discount_start_time');
            $table->dateTime('discount_end_time');
            $table->timestamps();
            // Indexes to quickly find active discount windows
            $table->index(['discount_start_time', 'discount_end_time']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('discount_events');
    }
};
