<?php

use JeffersonGoncalves\HelpDesk\Database\HelpDeskMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends HelpDeskMigration
{
    public function up(): void
    {
        Schema::create('help_desk_sla_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->nullable()->constrained('help_desk_departments')->nullOnDelete();
            $table->string('priority', 16)->nullable();
            $table->unsignedInteger('first_response_minutes');
            $table->unsignedInteger('resolution_minutes');
            $table->json('business_hours')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('help_desk_sla_policies');
    }
};
