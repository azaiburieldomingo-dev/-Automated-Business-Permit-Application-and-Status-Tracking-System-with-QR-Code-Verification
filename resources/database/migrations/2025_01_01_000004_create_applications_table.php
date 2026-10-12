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
        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->index();
            $table->foreignId('business_id')->nullable()->constrained()->index();
            $table->string('tracking_number')->unique()->nullable()->index();
            $table->string('status')->default('DRAFT')->index();
            $table->string('applicant_name');
            $table->string('contact_number', 32);
            $table->string('email');
            $table->string('business_name')->index();
            $table->string('business_address');
            $table->string('business_nature');
            $table->string('ownership_type');
            $table->string('dti_sec_type')->nullable();
            $table->string('dti_sec_number')->nullable();
            $table->string('barangay')->index();
            $table->unsignedInteger('number_of_employees')->default(0);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('applications');
    }
};
