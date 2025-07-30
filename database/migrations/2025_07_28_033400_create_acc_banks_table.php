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
        Schema::create('acc_banks', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('short_name');
            $table->string('slug')->unique();

            $table->enum('banking_type', ['regular', 'gateway', 'bkash', 'nagad', 'rocket'])->default('regular');

            $table->string('branch_name');
            $table->string('account_number');
            $table->string('account_name');

            $table->string('contact_person')->nullable();
            $table->string('designation')->nullable();
            $table->string('mobile')->nullable();
            $table->string('email')->nullable();

            $table->decimal('gateway_fee', 10, 2)->default(0.00);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('acc_banks');
    }
};
