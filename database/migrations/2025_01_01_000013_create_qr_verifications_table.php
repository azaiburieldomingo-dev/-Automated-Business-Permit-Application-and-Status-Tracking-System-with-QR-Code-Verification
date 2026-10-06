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
        Schema::create('qr_verifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('permit_id')->nullable()->index();
            $table->foreign('permit_id')->references('id')->on('permits')->nullOnDelete();
            $table->string('token')->index();
            $table->string('result');
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('qr_verifications');
    }
};
