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
        Schema::create('permits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->unique()->constrained()->cascadeOnDelete()->index();
            $table->string('permit_number')->unique()->index();
            $table->string('verification_token', 64)->unique()->index();
            $table->string('business_name');
            $table->string('owner_name');
            $table->string('business_address');
            $table->string('business_nature');
            $table->date('issued_at');
            $table->date('valid_until');
            $table->string('status')->default('ACTIVE')->index();
            $table->string('pdf_path')->nullable();
            $table->foreignId('generated_by')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('permits');
    }
};
