<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name_en');
            $table->string('name_ru');
            $table->string('color', 9)->default('#6b7280');
            $table->string('account_code', 20)->nullable()->comment('Xero account code');
            $table->string('account_1c', 20)->nullable()->comment('1C cost account, e.g. 26 or 44.01');
            $table->json('keywords')->nullable();
            $table->timestamps();
        });

        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('normalized_name')->index();
            $table->string('tax_id', 32)->nullable()->index();
            $table->string('country', 2)->nullable();
            $table->foreignId('default_category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('category_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('pattern')->nullable()->comment('keyword matched against vendor and line descriptions');
            $table->string('source', 16)->default('manual');
            $table->unsignedInteger('hits')->default(0);
            $table->timestamp('last_matched_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('vendor_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_rules');
        Schema::dropIfExists('vendors');
        Schema::dropIfExists('categories');
    }
};
