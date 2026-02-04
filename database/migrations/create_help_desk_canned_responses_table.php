<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('help_desk_canned_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->nullable()->constrained('help_desk_departments')->nullOnDelete();
            $table->string('title')->index();
            $table->longText('body');
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('help_desk_canned_responses');
    }
};
