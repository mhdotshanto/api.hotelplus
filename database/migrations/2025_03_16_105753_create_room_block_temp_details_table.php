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
        Schema::create('room_block_temp_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('temp_id');
            $table->unsignedBigInteger('room_id');
            $table->date('date');
            $table->decimal('rate', 10,2);
            $table->decimal('gross_rate', 10,2);
            $table->decimal('sale_rate', 10,2);
            $table->boolean('is_xb');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('room_block_temp_details');
    }
};
