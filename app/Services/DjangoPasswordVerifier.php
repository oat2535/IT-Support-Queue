<?php

namespace App\Services;

class DjangoPasswordVerifier
{
    /**
     * Verify a plaintext password against a Django pbkdf2_sha256 password hash.
     */
    public function verify(string $plainPassword, string $djangoHash): bool
    {
        $parts = explode('$', $djangoHash, 4);
        if (count($parts) !== 4) {
            return false;
        }

        [$algorithm, $iterationsRaw, $salt, $expectedHash] = $parts;
        if ($algorithm !== 'pbkdf2_sha256' || ! ctype_digit($iterationsRaw) || (int) $iterationsRaw <= 0 || $salt === '' || $expectedHash === '') {
            return false;
        }

        $derived = hash_pbkdf2('sha256', $plainPassword, $salt, (int) $iterationsRaw, 0, true);
        $encoded = base64_encode($derived);

        return hash_equals($expectedHash, $encoded);
    }
}
