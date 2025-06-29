<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained()
                ->onDelete('cascade');
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('tasks')
                ->onDelete('cascade');
            $table->enum('status', ['todo', 'done'])
                ->default('todo');
            $table->tinyInteger('priority')
                ->unsigned()
                ->default(3);
            $table->string('title');
            $table->text('description')->nullable();
            $table->timestamps();
            $table->timestamp('completed_at')->nullable();
            $table->softDeletes();

            $table->index(['user_id', 'status'], 'idx_user_status');
            $table->index(['user_id', 'priority'], 'idx_user_priority');
            $table->index(['user_id', 'created_at'], 'idx_user_created');
            $table->index(['user_id', 'completed_at'], 'idx_user_completed');
            $table->index('parent_id', 'idx_parent');

            $table->fullText(['title', 'description'], 'idx_fulltext_search');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
