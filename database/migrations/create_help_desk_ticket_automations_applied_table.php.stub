<?php

use JeffersonGoncalves\HelpDesk\Database\HelpDeskMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends HelpDeskMigration
{
    public function up(): void
    {
        Schema::create('help_desk_ticket_automations_applied', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('help_desk_tickets')->cascadeOnDelete();
            $table->foreignId('automation_rule_id')->constrained('help_desk_automation_rules')->cascadeOnDelete();
            $table->timestamp('applied_at');

            $table->unique(['ticket_id', 'automation_rule_id'], 'help_desk_automations_applied_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('help_desk_ticket_automations_applied');
    }
};
