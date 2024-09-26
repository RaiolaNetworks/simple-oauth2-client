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
        Schema::create('oauth', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained($this->getTableName())->cascadeOnDelete();
            $table->string('oauth_id')->nullable();
            $table->longText('oauth_token')->nullable();
            $table->string('oauth_refresh_token')->nullable();
            $table->integer('oauth_token_expires_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('oauth');
    }

    protected function getTableName(): string
    {
        /** @var Model $model */
        $model = config('oauth.user_model_name');

        return (new $model())->getTable();
    }
};
