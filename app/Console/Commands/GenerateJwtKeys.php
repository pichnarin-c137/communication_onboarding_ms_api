<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class GenerateJwtKeys extends Command
{
    protected $signature = 'jwt:generate-keys {--force : Force regeneration of keys}';

    protected $description = 'Generate RSA key pair for JWT token signing';

    public function handle(): int
    {
        $keysPath = storage_path('keys');
        $privateKeyPath = storage_path('keys/jwt_private.pem');
        $publicKeyPath = storage_path('keys/jwt_public.pem');

        // Create keys directory if it doesn't exist
        if (! File::isDirectory($keysPath)) {
            File::makeDirectory($keysPath, 0755, true);
            $this->info('Created keys directory');
        }

        // Check if keys already exist
        if (File::exists($privateKeyPath) && ! $this->option('force')) {
            $this->error('Keys already exist. Use --force to regenerate');

            return self::FAILURE;
        }

        // Generate RSA key pair (4096 bits for security)
        $config = [
            'private_key_bits' => 4096,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];

        $this->info('Generating RSA key pair (4096 bits)...');

        $res = openssl_pkey_new($config);

        if ($res === false) {
            $this->error('Failed to generate key pair');

            return self::FAILURE;
        }

        // Export private key
        openssl_pkey_export($res, $privateKey);
        File::put($privateKeyPath, $privateKey);
        File::chmod($privateKeyPath, 0600); // Restrict permissions

        // Export public key
        $publicKey = openssl_pkey_get_details($res);
        File::put($publicKeyPath, $publicKey['key']);
        File::chmod($publicKeyPath, 0644);

        $this->info('✓ Private key: '.$privateKeyPath);
        $this->info('✓ Public key: '.$publicKeyPath);
        $this->warn('Keep private key secure and never commit to version control!');

        return self::SUCCESS;
    }
}
