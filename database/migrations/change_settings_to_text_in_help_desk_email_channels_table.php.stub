<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use JeffersonGoncalves\HelpDesk\Database\HelpDeskMigration;

return new class extends HelpDeskMigration
{
    public function up(): void
    {
        Schema::table('help_desk_email_channels', function (Blueprint $table) {
            $table->text('settings')->change();
        });
    }

    /**
     * Intentionally a no-op: casting back to json isn't safe once
     * encrypted:array ciphertext (never valid JSON) has been written to the
     * column — Postgres rejects the ALTER outright.
     */
    public function down(): void {}
};
