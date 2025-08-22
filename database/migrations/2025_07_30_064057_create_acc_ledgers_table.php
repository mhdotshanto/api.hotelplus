<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    //  $table->string('head', 255)->comment('acc_head/bank/party/suspension');
    public function up(): void
    {
        Schema::create('acc_ledgers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('voucher_id')->default(0);
            $table->unsignedBigInteger('sale_room_id');
            $table->unsignedBigInteger('acc_head_id')->default(0);
            $table->unsignedBigInteger('bank_id')->default(0);
            $table->date('date');
            $table->enum('type', ['general', 'bank', 'sale', 'cashbook'])->default('general');
            $table->enum('acc_type', ['acc_head', 'bank', 'sale'])->default('general');
            $table->string('note', 255)->nullable();
            $table->boolean('is_cash')->default(false);
            $table->string('reference_number', 100)->nullable();
            $table->decimal('debit', 10, 2)->default(0.00);
            $table->decimal('credit', 10, 2)->default(0.00);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('acc_ledgers');
    }
};
