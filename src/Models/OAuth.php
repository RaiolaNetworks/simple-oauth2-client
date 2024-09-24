<?php

declare(strict_types=1);

namespace Raiolanetworks\OAuth\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Raiolanetworks\OAuth\Database\Factories\OAuthFactory;

class OAuth extends Model
{
    /** @use HasFactory<OAuthFactory> */
    use HasFactory;

    protected $table = 'oauth';

    /**
     * @return Factory<OAuth>
     */
    protected static function newFactory(): Factory
    {
        return OAuthFactory::new();
    }

    protected $fillable = [
        'user_id',
        'oauth_id',
        'oauth_token',
        'oauth_refresh_token',
        'oauth_token_expires_at',
    ];

    // @phpstan-ignore-next-line
    public function user(): BelongsTo
    {
        /** @var Model $model */
        $model = config('oauth.user_model_name');

        return $this->belongsTo((new $model)::class);
    }
}
