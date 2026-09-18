<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use JeffersonGoncalves\HelpDesk\Database\HelpDeskMigration;

return new class extends HelpDeskMigration
{
    public function up(): void
    {
        Schema::table('help_desk_tickets', function (Blueprint $table) {
            $table->timestamp('sla_paused_at')->nullable()->after('sla_resolution_due_at');
            $table->unsignedInteger('total_sla_paused_minutes')->default(0)->after('sla_paused_at');
        });
    }

    public function down(): void
    {
        Schema::table('help_desk_tickets', function (Blueprint $table) {
            $table->dropColumn(['sla_paused_at', 'total_sla_paused_minutes']);
        });
    }
};
