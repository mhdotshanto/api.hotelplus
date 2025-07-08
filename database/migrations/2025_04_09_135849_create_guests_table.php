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
        Schema::create('guests', function (Blueprint $table) {
            $table->id();
            $table->string('name_address',20)->nullable();
            $table->string('last_name',30);
            $table->string('first_name',30)->nullable();
            $table->string('email')->nullable();
            $table->string('mobile',11)->unique();
            $table->string('secondary_mobile',50);
            $table->string('emergency_contact',50);
            $table->string('gender',10)->nullable();
            $table->date('dob')->nullable();
            $table->date('dom')->nullable();
            $table->unsignedBigInteger('pres_country_id');
            $table->string('pres_city',50);
            $table->string('pres_address');
            $table->unsignedBigInteger('perm_country_id');
            $table->string('perm_city',50);
            $table->string('perm_address');
            $table->string('source',100)->nullable();
            $table->string('profession',100)->nullable();
            $table->string('market',100)->nullable();
            $table->string('company',100)->nullable();
            $table->string('nid',50)->nullable();
            $table->string('passport',50)->nullable();
            $table->string('passport_date',100)->nullable();
            $table->date('passport_exp_date')->nullable();
            $table->unsignedBigInteger('nationality_id')->nullable();
            $table->string('preference_id',30)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('guests');
    }
};
