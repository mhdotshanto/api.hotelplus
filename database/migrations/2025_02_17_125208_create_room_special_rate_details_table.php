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
        Schema::create('room_special_rate_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('special_rate_id');
            $table->unsignedBigInteger('category_id');
            $table->decimal('rate', 10,2);
            $table->decimal('rate_xb', 10,2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('room_special_rate_details');
    }
};
