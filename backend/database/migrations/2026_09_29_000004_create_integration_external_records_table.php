<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_external_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('integration_id')->constrained()->cascadeOnDelete();
            $table->string('record_type', 80);
            $table->string('local_id', 100);
            $table->string('external_id', 255);
            $table->timestamps();
            $table->unique(['integration_id', 'record_type', 'local_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_external_records');
    }
};
