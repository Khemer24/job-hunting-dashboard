<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_listings', function (Blueprint $table): void {
            $table->string('entry_method', 20)->default('feed')->after('source_listing_key');
            $table->index(['entry_method', 'source']);
        });
    }

    public function down(): void
    {
        Schema::table('job_listings', function (Blueprint $table): void {
            $table->dropIndex(['entry_method', 'source']);
            $table->dropColumn('entry_method');
        });
    }
};
