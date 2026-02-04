<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('help_desk_tickets', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('reference_number', 32)->unique();
            $table->foreignId('department_id')->constrained('help_desk_departments')->restrictOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('help_desk_categories')->nullOnDelete();
            $table->string('user_type');
            $table->unsignedBigInteger('user_id');
            $table->string('assigned_to_type')->nullable();
            $table->unsignedBigInteger('assigned_to_id')->nullable();
            $table->string('title');
            $table->longText('description');
            $table->string('status', 32)->default('open')->index();
            $table->string('priority', 16)->default('medium')->index();
            $table->string('source', 32)->default('web');
            $table->string('email_message_id')->nullable()->index();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('last_replied_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_type', 'user_id']);
            $table->index(['assigned_to_type', 'assigned_to_id']);
            $table->index(['status', 'priority']);
            $table->index(['department_id', 'status']);
            $table->index('created_at');

            if (DB::getDriverName() !== 'sqlite') {
                $table->fullText('title');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('help_desk_tickets');
    }
};
