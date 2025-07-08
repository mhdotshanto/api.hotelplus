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
        Schema::create('sale_room_categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sale_id');
            $table->unsignedBigInteger('category_id');
            $table->date('date');
            $table->integer('qty');
            $table->decimal('rate',10,2);
            $table->boolean('is_xb')->default(false);
            $table->decimal('discount_per',5,2)->default(0.00);
            $table->decimal('discount_amount',10,2)->default(0.00);
            $table->decimal('rate_sale',10,2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_room_categories');
    }
};
