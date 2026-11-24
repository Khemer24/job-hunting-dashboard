<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_listings', function (Blueprint $table): void {
            $table->text('job_url')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('job_listings')
            ->whereNull('job_url')
            ->update(['job_url' => '']);

        Schema::table('job_listings', function (Blueprint $table): void {
            $table->text('job_url')->nullable(false)->change();
        });
    }
};
