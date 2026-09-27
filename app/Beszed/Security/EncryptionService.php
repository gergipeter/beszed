<?php

namespace App\Beszed\Security;

use ParagonIE\Sodium\Crypto;
use SodiumException;

/**
 * Encryption Service
 * End-to-end encryption with Sodium
 */
class EncryptionService
{
    /**
     * Generate key pair for a user
     */
    public static function generateKeyPair(): array
    {
        $keypair = Crypto::box_keypair();

        return [
            'public_key' => Crypto::box_publickey_from_secretkey(Crypto::box_secretkey($keypair)),
            'secret_key' => Crypto::box_secretkey($keypair),
        ];
    }

    /**
     * Encrypt data for recipient
     */
    public static function encrypt(
        string $plaintext,
        string $recipientPublicKey,
        string $senderSecretKey
    ): string {
        $nonce = random_bytes(\ParagonIE\Sodium\Core\Util::NONCEBYTES);

        $ciphertext = Crypto::box(
            $plaintext,
            $nonce,
            Crypto::box_beforenm($recipientPublicKey, $senderSecretKey)
        );

        // Return nonce + ciphertext
        return bin2hex($nonce . $ciphertext);
    }

    /**
     * Decrypt data from sender
     */
    public static function decrypt(
        string $encryptedData,
        string $senderPublicKey,
        string $recipientSecretKey
    ): string {
        $data = hex2bin($encryptedData);
        $nonce = substr($data, 0, \ParagonIE\Sodium\Core\Util::NONCEBYTES);
        $ciphertext = substr($data, \ParagonIE\Sodium\Core\Util::NONCEBYTES);

        return Crypto::box_open(
            $ciphertext,
            $nonce,
            Crypto::box_beforenm($senderPublicKey, $recipientSecretKey)
        );
    }

    /**
     * Encrypt file
     */
    public static function encryptFile(
        string $filePath,
        string $recipientPublicKey,
        string $senderSecretKey
    ): string {
        $plaintext = file_get_contents($filePath);
        $encrypted = self::encrypt($plaintext, $recipientPublicKey, $senderSecretKey);
        $encryptedPath = $filePath . '.encrypted';

        file_put_contents($encryptedPath, $encrypted);

        return $encryptedPath;
    }

    /**
     * Decrypt file
     */
    public static function decryptFile(
        string $encryptedFilePath,
        string $senderPublicKey,
        string $recipientSecretKey
    ): string {
        $encrypted = file_get_contents($encryptedFilePath);
        $plaintext = self::decrypt($encrypted, $senderPublicKey, $recipientSecretKey);
        $decryptedPath = str_replace('.encrypted', '', $encryptedFilePath);

        file_put_contents($decryptedPath, $plaintext);

        return $decryptedPath;
    }

    /**
     * Create signature
     */
    public static function sign(string $message, string $secretKey): string
    {
        $signature = \ParagonIE\Sodium\Crypto::sign($message, $secretKey);
        return bin2hex($signature);
    }

    /**
     * Verify signature
     */
    public static function verify(
        string $message,
        string $signature,
        string $publicKey
    ): bool {
        try {
            \ParagonIE\Sodium\Crypto::sign_open(
                hex2bin($signature),
                $publicKey
            );
            return true;
        } catch (SodiumException) {
            return false;
        }
    }
}
