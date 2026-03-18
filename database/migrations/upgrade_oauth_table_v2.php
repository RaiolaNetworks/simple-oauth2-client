<?php

declare(strict_types=1);

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
        Schema::table('oauth', function (Blueprint $table) {
            $table->longText('oauth_refresh_token')->nullable()->change();
            $table->unsignedBigInteger('oauth_token_expires_at')->nullable()->change();
            $table->index('oauth_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('oauth', function (Blueprint $table) {
            $table->string('oauth_refresh_token')->nullable()->change();
            $table->integer('oauth_token_expires_at')->nullable()->change();
            $table->dropIndex(['oauth_id']);
        });
    }
};
