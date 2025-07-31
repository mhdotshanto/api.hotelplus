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
        Schema::create('acc_cash_books', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('voucher_id');
            $table->unsignedBigInteger('sale_room_id');
            $table->unsignedBigInteger('head_id');
            $table->unsignedBigInteger('bank_id')->default(0);
            $table->string('head', 255)->default('acc_head')->comment('acc_head/bank/party/suspension');
            $table->date('date');
            $table->string('payment_method', 50);
            $table->string('reference_number', 100)->nullable();
            $table->decimal('debit', 15, 2)->default(0.00);
            $table->decimal('credit', 15, 2)->default(0.00);
            $table->boolean('is_sale')->default(false);
            $table->boolean('is_cash')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('acc_cash_books');
    }
};
