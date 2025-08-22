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
        Schema::create('acc_parties', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->string('slug', 50)->nullable();
            $table->string('contact_person', 255)->nullable();
            $table->string('designation', 255)->nullable();
            $table->string('mobile', 255)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('address', 255)->nullable();
            $table->string('type', 30)->nullable();
            $table->unsignedBigInteger('relation_id')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('acc_parties');
    }
};
