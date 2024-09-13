<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
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
        Schema::table($this->getTableName(), function (Blueprint $table) {
            $table->string('oauth_id')->nullable();
            $table->longText('oauth_token')->nullable();
            $table->string('oauth_refresh_token')->nullable();
            $table->integer('oauth_token_expires_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropColumns($this->getTableName(), [
            'oauth_id',
            'oauth_token',
            'oauth_refresh_token',
            'oauth_token_expires_at',
        ]);
    }

    protected function getTableName(): string
    {
        /** @var Model $model */
        $model = config('oauth.user_model_name');

        return (new $model())->getTable();
    }
};
