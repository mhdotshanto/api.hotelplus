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
        Schema::create('room_numbers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('category_id');
            $table->unsignedBigInteger('room_name_id');
            $table->unsignedBigInteger('floor_id');
            $table->string('name', 10)->unique();
            $table->boolean('is_active');
            $table->boolean('is_clean')->default(true)->comment('Define House Keeping Require or Not');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('room_numbers');
    }
};
