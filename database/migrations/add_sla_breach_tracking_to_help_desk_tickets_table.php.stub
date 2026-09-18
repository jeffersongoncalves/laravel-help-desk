<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use JeffersonGoncalves\HelpDesk\Database\HelpDeskMigration;

return new class extends HelpDeskMigration
{
    public function up(): void
    {
        Schema::table('help_desk_tickets', function (Blueprint $table) {
            // Set once a breach has been dispatched for this ticket, so the
            // scheduled check never re-dispatches for the same one.
            $table->timestamp('sla_first_response_breached_at')->nullable()->after('sla_paused_at');
            $table->timestamp('sla_resolution_breached_at')->nullable()->after('sla_first_response_breached_at');
        });
    }

    public function down(): void
    {
        Schema::table('help_desk_tickets', function (Blueprint $table) {
            $table->dropColumn(['sla_first_response_breached_at', 'sla_resolution_breached_at']);
        });
    }
};
