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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete()->index();
            $table->string('tracking_number')->index();
            $table->decimal('amount', 12, 2);
            $table->json('breakdown_json');
            $table->string('method');
            $table->string('reference_number')->nullable()->index();
            $table->string('status')->default('PENDING')->index();
            $table->string('verification_status')->default('UNVERIFIED');
            $table->timestamp('payment_date')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
