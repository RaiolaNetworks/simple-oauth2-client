<?php

declare(strict_types=1);

namespace Raiolanetworks\OAuth\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

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
    public function __construct(public Model $user, public array $groups)
    {
        $this->user   = $user->refresh();
        $this->groups = $groups;
    }
}
