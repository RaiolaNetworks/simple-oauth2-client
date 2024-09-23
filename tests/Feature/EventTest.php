<?php

declare(strict_types=1);

use Raiolanetworks\OAuth\Events\EventsOAuthTokenUpdated;
use Raiolanetworks\OAuth\Models\OAuth;
use Raiolanetworks\OAuth\Tests\Models\TestUser;

it('dispatches the EventsOAuthTokenUpdated event with correct attributes', function () {
    $mockUser  = TestUser::factory()->create();
    $oauthData = OAuth::factory(state: [
        'user_id' => $mockUser->id,
    ])->create();

    $groups = ['admin', 'editor'];

    $event = new EventsOAuthTokenUpdated($mockUser, $oauthData, $groups);

    expect($event->user)->toBe($mockUser);
    expect($event->groups)->toBe($groups);
});
