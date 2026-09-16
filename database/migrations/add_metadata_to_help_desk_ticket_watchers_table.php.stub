<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use JeffersonGoncalves\HelpDesk\Database\HelpDeskMigration;

return new class extends HelpDeskMigration
{
    public function up(): void
    {
        Schema::table('help_desk_ticket_watchers', function (Blueprint $table) {
            // Holds the watcher identity snapshot. The other tables that carry
            // one already had a metadata column; this is the only one that did not.
            $table->json('metadata')->nullable()->after('watcher_id');
        });
    }

    public function down(): void
    {
        Schema::table('help_desk_ticket_watchers', function (Blueprint $table) {
            $table->dropColumn('metadata');
        });
    }
};
