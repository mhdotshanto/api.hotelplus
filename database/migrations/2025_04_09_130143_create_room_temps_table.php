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
        Schema::create('room_temps', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sale_id')->default(null);
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('guest_id')->default(0);
            $table->enum('type', ['sales', 'block'])->default('sales');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('room_temps');
    }
};
