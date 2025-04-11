<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exports', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('target', 16);
            $table->string('status', 12)->index();
            $table->unsignedInteger('document_count')->default(0);
            $table->string('file_path')->nullable();
            $table->string('external_url')->nullable();
            $table->text('error')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('document_export', function (Blueprint $table) {
            $table->foreignUlid('export_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('document_id')->constrained()->cascadeOnDelete();
            $table->string('external_id')->nullable();

            $table->primary(['export_id', 'document_id']);
        });

        Schema::create('integrations', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 20)->unique();
            $table->text('credentials')->nullable();
            $table->string('account_name')->nullable();
            $table->json('settings')->nullable();
            $table->foreignId('connected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 40);
            $table->string('subject_type', 40);
            $table->string('subject_id', 40);
            $table->json('changes')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_events');
        Schema::dropIfExists('integrations');
        Schema::dropIfExists('document_export');
        Schema::dropIfExists('exports');
    }
};
