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
        Schema::create('businesses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete()->index();
            $table->string('name')->index();
            $table->string('address');
            $table->string('barangay')->index();
            $table->string('nature');
            $table->string('ownership_type');
            $table->string('dti_sec_type')->nullable();
            $table->string('dti_sec_number')->nullable();
            $table->unsignedInteger('number_of_employees')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('businesses');
    }
};
