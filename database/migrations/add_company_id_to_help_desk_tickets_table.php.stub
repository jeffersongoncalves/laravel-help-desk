<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use JeffersonGoncalves\HelpDesk\Database\HelpDeskMigration;

return new class extends HelpDeskMigration
{
    public function up(): void
    {
        Schema::table('help_desk_tickets', function (Blueprint $table) {
            // Nullable: a single-company installation never sets one, and
            // tickets written before this column existed keep working.
            $table->string('company_id', 64)->nullable()->after('app_key')->index();
        });
    }

    public function down(): void
    {
        Schema::table('help_desk_tickets', function (Blueprint $table) {
            $table->dropIndex(['company_id']);
            $table->dropColumn('company_id');
        });
    }
};
