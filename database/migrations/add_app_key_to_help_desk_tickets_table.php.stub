<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use JeffersonGoncalves\HelpDesk\Database\HelpDeskMigration;

return new class extends HelpDeskMigration
{
    public function up(): void
    {
        Schema::table('help_desk_tickets', function (Blueprint $table) {
            // Nullable: a single-application installation never sets one, and
            // tickets written before this column existed keep working.
            $table->string('app_key', 64)->nullable()->after('source')->index();
        });
    }

    public function down(): void
    {
        Schema::table('help_desk_tickets', function (Blueprint $table) {
            $table->dropIndex(['app_key']);
            $table->dropColumn('app_key');
        });
    }
};
