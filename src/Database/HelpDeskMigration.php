<?php

namespace JeffersonGoncalves\HelpDesk\Database;

use Illuminate\Database\Migrations\Migration;

/**
 * Base class for the package migrations.
 *
 * Laravel swaps the default connection while a migration runs, so declaring it
 * here is enough for the plain `Schema::` calls inside each migration to land
 * on the help desk connection.
 */
abstract class HelpDeskMigration extends Migration
{
    public function getConnection(): ?string
    {
        return config('help-desk.connection');
    }
}
