<?php

declare(strict_types=1);

namespace Raiolanetworks\OAuth\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class OAuthEncryptTokensCommand extends Command
{
    public $signature = 'oauth:encrypt-tokens';

    public $description = 'Encrypt existing plaintext OAuth tokens (v1 to v2 migration)';

    public function handle(): int
    {
        $records = DB::table('oauth')
            ->whereNotNull('oauth_token')
            ->orWhereNotNull('oauth_refresh_token')
            ->get();

        if ($records->isEmpty()) {
            $this->info('No OAuth records found.');

            return self::SUCCESS;
        }

        $encrypted = 0;
        $skipped   = 0;

        foreach ($records as $record) {
            $update = [];

            if ($record->oauth_token !== null && ! $this->isAlreadyEncrypted($record->oauth_token)) {
                $update['oauth_token'] = Crypt::encryptString($record->oauth_token);
            }

            if ($record->oauth_refresh_token !== null && ! $this->isAlreadyEncrypted($record->oauth_refresh_token)) {
                $update['oauth_refresh_token'] = Crypt::encryptString($record->oauth_refresh_token);
            }

            if ($update !== []) {
                DB::table('oauth')->where('id', $record->id)->update($update);
                $encrypted++;
            } else {
                $skipped++;
            }
        }

        $this->info("Done. Encrypted: {$encrypted}, Skipped (already encrypted): {$skipped}.");

        return self::SUCCESS;
    }

    protected function isAlreadyEncrypted(string $value): bool
    {
        try {
            Crypt::decryptString($value);

            return true;
        } catch (Exception) {
            return false;
        }
    }
}
