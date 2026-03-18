<?php

declare(strict_types=1);

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Raiolanetworks\OAuth\Tests\Models\TestUser;

it('encrypts existing plaintext tokens', function () {
    $user = TestUser::factory()->create();

    DB::table('oauth')->insert([
        'user_id'                => $user->id,
        'oauth_id'               => 'test-oauth-id',
        'oauth_token'            => 'plaintext_token',
        'oauth_refresh_token'    => 'plaintext_refresh',
        'oauth_token_expires_at' => time() + 3600,
        'created_at'             => now(),
        'updated_at'             => now(),
    ]);

    $this->artisan('oauth:encrypt-tokens')
        ->assertExitCode(Command::SUCCESS);

    $record = DB::table('oauth')->where('oauth_id', 'test-oauth-id')->first();

    expect($record->oauth_token)->not->toBe('plaintext_token');
    expect(Crypt::decryptString($record->oauth_token))->toBe('plaintext_token');
    expect(Crypt::decryptString($record->oauth_refresh_token))->toBe('plaintext_refresh');
});

it('skips already encrypted tokens', function () {
    $user = TestUser::factory()->create();

    $encryptedToken   = Crypt::encryptString('my_token');
    $encryptedRefresh = Crypt::encryptString('my_refresh');

    DB::table('oauth')->insert([
        'user_id'                => $user->id,
        'oauth_id'               => 'already-encrypted',
        'oauth_token'            => $encryptedToken,
        'oauth_refresh_token'    => $encryptedRefresh,
        'oauth_token_expires_at' => time() + 3600,
        'created_at'             => now(),
        'updated_at'             => now(),
    ]);

    $this->artisan('oauth:encrypt-tokens')
        ->assertExitCode(Command::SUCCESS);

    $record = DB::table('oauth')->where('oauth_id', 'already-encrypted')->first();

    // Should remain the same (not double-encrypted)
    expect($record->oauth_token)->toBe($encryptedToken);
    expect($record->oauth_refresh_token)->toBe($encryptedRefresh);
});

it('encrypts only the plaintext field when the other is already encrypted', function () {
    $user = TestUser::factory()->create();

    $encryptedToken = Crypt::encryptString('already_encrypted_token');

    DB::table('oauth')->insert([
        'user_id'                => $user->id,
        'oauth_id'               => 'partial-encrypted',
        'oauth_token'            => $encryptedToken,
        'oauth_refresh_token'    => 'plaintext_refresh',
        'oauth_token_expires_at' => time() + 3600,
        'created_at'             => now(),
        'updated_at'             => now(),
    ]);

    $this->artisan('oauth:encrypt-tokens')
        ->assertExitCode(Command::SUCCESS);

    $record = DB::table('oauth')->where('oauth_id', 'partial-encrypted')->first();

    // oauth_token should remain unchanged (already encrypted)
    expect($record->oauth_token)->toBe($encryptedToken);
    // oauth_refresh_token should now be encrypted
    expect($record->oauth_refresh_token)->not->toBe('plaintext_refresh');
    expect(Crypt::decryptString($record->oauth_refresh_token))->toBe('plaintext_refresh');
});

it('handles empty table gracefully', function () {
    $this->artisan('oauth:encrypt-tokens')
        ->expectsOutput('No OAuth records found.')
        ->assertExitCode(Command::SUCCESS);
});
