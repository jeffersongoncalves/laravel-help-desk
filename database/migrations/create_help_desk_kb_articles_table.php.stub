<?php

use JeffersonGoncalves\HelpDesk\Database\HelpDeskMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends HelpDeskMigration
{
    public function up(): void
    {
        Schema::create('help_desk_kb_articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->nullable()->constrained('help_desk_departments')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('help_desk_categories')->nullOnDelete();
            $table->string('app_key', 64)->nullable()->index();
            $table->string('title');
            $table->string('slug')->unique();
            $table->longText('body');
            $table->boolean('is_published')->default(false);
            $table->unsignedInteger('views_count')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('help_desk_kb_articles');
    }
};
