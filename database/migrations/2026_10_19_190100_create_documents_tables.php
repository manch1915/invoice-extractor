<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('status', 20)->index();
            $table->string('source', 12)->default('upload');
            $table->json('source_meta')->nullable();
            $table->string('original_filename');
            $table->string('mime_type', 100);
            $table->unsignedInteger('size');
            $table->string('storage_path');
            $table->char('sha256', 64)->index();

            $table->string('doc_type', 12)->nullable();
            $table->string('language', 5)->nullable();
            $table->string('engine', 12)->nullable();
            $table->string('text_source', 12)->nullable();
            $table->decimal('ocr_quality', 4, 3)->nullable();
            $table->unsignedSmallInteger('page_count')->default(0);
            $table->decimal('confidence', 4, 3)->nullable();
            $table->unsignedInteger('processing_ms')->nullable();
            $table->json('warnings')->nullable();
            $table->text('error')->nullable();

            $table->foreignId('vendor_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('category_source', 10)->nullable();
            $table->string('suggested_category', 40)->nullable();

            $table->string('vendor_name')->nullable();
            $table->string('vendor_tax_id', 32)->nullable();
            $table->string('buyer_name')->nullable();
            $table->string('buyer_tax_id', 32)->nullable();
            $table->string('document_number', 64)->nullable();
            $table->date('issue_date')->nullable()->index();
            $table->date('due_date')->nullable();
            $table->char('currency', 3)->nullable();
            $table->bigInteger('subtotal')->nullable()->comment('minor units');
            $table->decimal('tax_rate', 5, 2)->nullable();
            $table->bigInteger('tax_amount')->nullable()->comment('minor units');
            $table->bigInteger('total')->nullable()->comment('minor units');

            $table->ulid('duplicate_of_id')->nullable();
            $table->boolean('auto_approved')->default(false);
            $table->unsignedInteger('review_seconds')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('exported_at')->nullable();
            $table->timestamps();

            $table->index(['vendor_id', 'document_number']);
        });

        // Postgres needs the primary key in place before a column can point back at it
        Schema::table('documents', function (Blueprint $table) {
            $table->foreign('duplicate_of_id')->references('id')->on('documents')->nullOnDelete();
        });

        Schema::create('document_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('document_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('number');
            $table->string('image_path');
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->json('words');

            $table->unique(['document_id', 'number']);
        });

        Schema::create('document_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('document_id')->constrained()->cascadeOnDelete();
            $table->string('key', 24);
            $table->string('value')->nullable();
            $table->string('original_value')->nullable();
            $table->string('raw')->nullable();
            $table->decimal('confidence', 4, 3)->default(0);
            $table->unsignedSmallInteger('page')->nullable();
            $table->json('bbox')->nullable();
            $table->string('status', 12);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['document_id', 'key']);
        });

        Schema::create('line_items', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('document_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('description', 500);
            $table->decimal('quantity', 14, 3)->nullable();
            $table->bigInteger('unit_price')->nullable()->comment('minor units');
            $table->bigInteger('amount')->nullable()->comment('minor units');
            $table->decimal('confidence', 4, 3)->default(0);
            $table->unsignedSmallInteger('page')->nullable();
            $table->json('bbox')->nullable();
        });

        Schema::create('document_issues', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('document_id')->constrained()->cascadeOnDelete();
            $table->string('code', 32);
            $table->string('severity', 10);
            $table->string('field', 24)->nullable();
            $table->json('context')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['document_id', 'resolved_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_issues');
        Schema::dropIfExists('line_items');
        Schema::dropIfExists('document_fields');
        Schema::dropIfExists('document_pages');
        Schema::dropIfExists('documents');
    }
};
