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
        Schema::create('sale_rooms', function (Blueprint $table) {
            $table->id();
            $table->string('code',7);
            $table->unsignedBigInteger('guest_id');
            $table->string('guest_name_title',5);
            $table->string('guest_name_first',50)->nullable();
            $table->string('guest_name_last',50);
            $table->string('guest_mobile',11);
            $table->string('guest_mobile_emergency',50)->nullable();
            $table->string('guest_email',50)->nullable();
            $table->string('guest_company',50)->nullable();
            $table->string('guest_address')->nullable();
            $table->unsignedBigInteger('ref_id');
            $table->unsignedBigInteger('user_id');
            $table->string('master_room',10);
            $table->string('rooms');
            $table->integer('guest_qty');
            $table->integer('room_qty');
            $table->integer('xb_qty')->default(0);
            $table->date('date');
            $table->decimal('price',10,2);
            $table->decimal('vat_per',5,2)->default(0.00);
            $table->decimal('vat_amount',10,2)->default(0.00);
            $table->decimal('servicecharge_per',5,2)->default(0.00);
            $table->decimal('servicecharge_amount',10,2)->default(0.00);
            $table->decimal('price_gross',10,2);
            $table->enum('discount_type',['invoice', 'category'])->nullable();
            $table->decimal('discount_per',5,2)->default(0.00);
            $table->decimal('discount_amount',10,2)->default(0.00);
            $table->decimal('price_sale',10,2);
            $table->decimal('price_net',10,2);
            $table->string('remarks')->nullable();
            $table->date('date_checkin');
            $table->time('eta')->default('12:00:00');
            $table->date('date_checkout');
            $table->time('etd')->default('11:00:00');
            $table->timestamp('date_to_confirm')->nullable();
            $table->enum('status',['tentative','guaranteed','checkin','checkout','cancelled','postponed','inactive']);
            $table->boolean('is_room_allocated')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_rooms');
    }
};
