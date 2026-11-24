<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_listings', function (Blueprint $table): void {
            $table->id();
            $table->string('source', 40);
            $table->string('source_listing_key', 64);
            $table->string('title');
            $table->string('company');
            $table->text('job_url');
            $table->text('employer_apply_url')->nullable();
            $table->timestamp('posted_at');
            $table->string('employment_type', 30);
            $table->string('pay')->nullable();
            $table->longText('description');
            $table->longText('qualifications');
            $table->boolean('is_open')->default(true);
            $table->boolean('is_remote')->default(false);
            $table->boolean('accepts_philippines')->default(false);
            $table->timestamps();

            $table->unique(['source', 'source_listing_key']);
            $table->index(['posted_at', 'is_open', 'is_remote', 'accepts_philippines']);
        });

        Schema::create('job_source_checks', function (Blueprint $table): void {
            $table->string('source', 40)->primary();
            $table->string('status', 30);
            $table->text('message')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('last_successful_at')->nullable();
            $table->unsignedInteger('records_found')->default(0);
            $table->timestamps();
        });

        Schema::create('job_refresh_runs', function (Blueprint $table): void {
            $table->id();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->boolean('successful')->default(false);
            $table->timestamps();

            $table->index(['successful', 'finished_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_refresh_runs');
        Schema::dropIfExists('job_source_checks');
        Schema::dropIfExists('job_listings');
    }
};
