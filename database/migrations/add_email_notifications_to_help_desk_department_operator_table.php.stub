<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use JeffersonGoncalves\HelpDesk\Database\HelpDeskMigration;

return new class extends HelpDeskMigration
{
    public function up(): void
    {
        Schema::table('help_desk_department_operator', function (Blueprint $table) {
            // Per department, so an operator can follow one queue by email and
            // not another. Defaults to on: being in a department is the opt-in.
            $table->boolean('email_notifications')->default(true)->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('help_desk_department_operator', function (Blueprint $table) {
            $table->dropColumn('email_notifications');
        });
    }
};
