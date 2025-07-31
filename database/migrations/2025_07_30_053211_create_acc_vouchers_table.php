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
        Schema::create('acc_vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_ref', 10)->nullable();
            $table->unsignedBigInteger('acc_head_id');
            $table->unsignedBigInteger('acc_bank_id');
            $table->unsignedBigInteger('sale_room_id');
            $table->text('note')->nullable();
            $table->string('reference_number', 100)->nullable();
            $table->string('payment_method', 50);
            $table->enum('status', ['pending', 'paid'])->default('pending');
            $table->decimal('debit', 15, 2)->default(0);
            $table->decimal('credit', 15, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('acc_vouchers');
    }
};
