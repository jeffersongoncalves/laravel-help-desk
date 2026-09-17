<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use JeffersonGoncalves\HelpDesk\Database\HelpDeskMigration;

return new class extends HelpDeskMigration
{
    public function up(): void
    {
        Schema::table('help_desk_tickets', function (Blueprint $table) {
            // Deliberately not reusing `due_at`: it's already public API surface
            // with undocumented meaning to whatever currently sets it. Separate,
            // clearly-named columns avoid silently changing what an existing
            // nullable column means for current installs.
            $table->foreignId('sla_policy_id')->nullable()->after('priority')->constrained('help_desk_sla_policies')->nullOnDelete();
            $table->timestamp('first_response_at')->nullable()->after('last_replied_at');
            $table->timestamp('sla_first_response_due_at')->nullable()->after('first_response_at');
            $table->timestamp('sla_resolution_due_at')->nullable()->after('sla_first_response_due_at');
        });
    }

    public function down(): void
    {
        Schema::table('help_desk_tickets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sla_policy_id');
            $table->dropColumn(['first_response_at', 'sla_first_response_due_at', 'sla_resolution_due_at']);
        });
    }
};
