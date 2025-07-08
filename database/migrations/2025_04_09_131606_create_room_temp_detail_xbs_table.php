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
        Schema::create('room_temp_detail_xbs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('room_temp_id');
            $table->unsignedBigInteger('room_number_id');
            $table->unsignedBigInteger('room_category_id');
            $table->unsignedBigInteger('room_name_id');
            $table->date('date');
            $table->enum('stock', ['hold', 'release'])->default('hold');
            $table->integer('qty')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('room_temp_detail_xbs');
    }
};
