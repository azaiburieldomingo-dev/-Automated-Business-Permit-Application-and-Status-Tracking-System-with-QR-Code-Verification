<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fields for manual (send-money) payments: what the applicant reports
     * after paying, and why the BPLO rejected it if the payment didn't match.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('payer_name')->nullable();
            $table->string('payer_number', 20)->nullable();
            $table->string('proof_path')->nullable();
            $table->string('proof_original_name')->nullable();
            $table->string('rejection_reason', 500)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['payer_name', 'payer_number', 'proof_path', 'proof_original_name', 'rejection_reason']);
        });
    }
};
