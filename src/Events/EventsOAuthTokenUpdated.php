<?php

declare(strict_types=1);

namespace Raiolanetworks\OAuth\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Raiolanetworks\OAuth\Models\OAuth;

class EventsOAuthTokenUpdated
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param array<string, mixed> $groups
     */
    public function __construct(public Model|Authenticatable $user, public OAuth $oauthData, public array $groups)
    {
        $this->user      = $user->refresh();
        $this->oauthData = $oauthData->refresh();
        $this->groups    = $groups;
    }
}
