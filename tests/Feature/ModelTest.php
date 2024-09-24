<?php

declare(strict_types=1);

use Raiolanetworks\OAuth\Models\OAuth;
use Raiolanetworks\OAuth\Tests\Models\TestUser;

it('check user relationships in the OAuth model', function () {
    $user = TestUser::factory()->create();
    $oauthModel = OAuth::factory(state: [
        'user_id' => $user->id
    ])->create();

    expect($oauthModel->user)->toBeInstanceOf($user::class);
    expect($oauthModel->user->id)->toBe($user->id);
});
