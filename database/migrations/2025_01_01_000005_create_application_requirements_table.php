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
        Schema::create('application_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete()->index();
            $table->foreignId('requirement_type_id')->constrained()->index();
            $table->string('original_filename')->nullable();
            $table->string('stored_path')->nullable();
            $table->string('mime')->nullable();
            $table->unsignedInteger('size_kb')->nullable();
            $table->string('checklist_status')->default('MISSING');
            $table->string('correction_note')->nullable();
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamps();

            $table->unique(['application_id', 'requirement_type_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('application_requirements');
    }
};
