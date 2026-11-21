<?php

namespace App\Support\Backup;

use RuntimeException;

/**
 * The off-server copy's encryption (BKR-09; DIR-083 OE-09; architecture challenge 13b): each file encrypted with a
 * fresh key under libsodium's XChaCha20-Poly1305 secret stream, that key sealed to the operator's X25519 public key.
 * The VPS holds only the public key, so nothing on it — or with the copy — decrypts the copy; the secret key stays in
 * the operator's secret store off the VPS. A truncated, reordered or altered file fails to open.
 *
 * Layout: "MCBK1" · the sealed stream key (80 bytes) · the stream header (24 bytes) · chunks, each a 4-byte big-endian
 * length and its ciphertext, the last tagged final.
 */
final class SealedStream
{
    private const MAGIC = 'MCBK1';

    private const CHUNK = 1048576;

    /** @return array{public: string, secret: string} a new key pair, each base64-encoded */
    public static function keyPair(): array
    {
        $pair = sodium_crypto_box_keypair();

        return ['public' => base64_encode(sodium_crypto_box_publickey($pair)), 'secret' => base64_encode(sodium_crypto_box_secretkey($pair))];
    }

    /** Encrypts a file to the public key; answers the encrypted file's SHA-256. */
    public static function encrypt(string $source, string $target, string $publicKey): string
    {
        $public = self::key($publicKey, 'public');
        $key = sodium_crypto_secretstream_xchacha20poly1305_keygen();
        $pushed = sodium_crypto_secretstream_xchacha20poly1305_init_push($key);
        $state = Scalars::text($pushed[0]);
        $header = Scalars::text($pushed[1]);
        $in = fopen($source, 'rb');
        $out = fopen($target, 'xb');

        if ($in === false || $out === false) {
            throw new RuntimeException('The file to copy or its encrypted copy cannot be opened.');
        }

        $hash = hash_init('sha256');

        try {
            self::put($out, $hash, self::MAGIC.sodium_crypto_box_seal($key, $public).$header);
            $chunk = (string) fread($in, self::CHUNK);

            while (true) {
                $next = feof($in) ? '' : (string) fread($in, self::CHUNK);
                $final = $next === '' && feof($in);
                $cipher = sodium_crypto_secretstream_xchacha20poly1305_push($state, $chunk, '',
                    $final ? SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL : SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE);
                self::put($out, $hash, pack('N', strlen($cipher)).$cipher);

                if ($final) {
                    break;
                }

                $chunk = $next;
            }
        } finally {
            fclose($in);
            fclose($out);
            sodium_memzero($key);
        }

        chmod($target, 0600);

        return hash_final($hash);
    }

    /** Decrypts a file with the secret key, refusing one that is truncated, altered or sealed to another key. */
    public static function decrypt(string $source, string $target, string $secretKey): void
    {
        $secret = self::key($secretKey, 'secret');
        $pair = sodium_crypto_box_keypair_from_secretkey_and_publickey($secret, sodium_crypto_box_publickey_from_secretkey($secret));
        $in = fopen($source, 'rb');

        if ($in === false) {
            throw new RuntimeException('The encrypted copy cannot be read.');
        }

        $out = null;

        try {
            $head = (string) fread($in, strlen(self::MAGIC) + SODIUM_CRYPTO_BOX_SEALBYTES + SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES + SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES);

            if (! str_starts_with($head, self::MAGIC)) {
                throw new RuntimeException('The file is not an encrypted copy.');
            }

            $key = sodium_crypto_box_seal_open(substr($head, strlen(self::MAGIC), SODIUM_CRYPTO_BOX_SEALBYTES + SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES), $pair);

            if ($key === false) {
                throw new RuntimeException('The copy was sealed to another key.');
            }

            $state = sodium_crypto_secretstream_xchacha20poly1305_init_pull(substr($head, -SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES), $key);
            $out = fopen($target, 'xb');

            if ($out === false) {
                throw new RuntimeException('The decrypted file cannot be written.');
            }

            while (true) {
                $length = fread($in, 4);

                if ($length === false || strlen($length) < 4) {
                    throw new RuntimeException('The encrypted copy is truncated.');
                }

                $size = unpack('N', $length);
                $cipher = (string) fread($in, is_array($size) && is_int($size[1]) ? max(1, $size[1]) : 1);
                $opened = sodium_crypto_secretstream_xchacha20poly1305_pull($state, $cipher);

                if ($opened === false) {
                    throw new RuntimeException('The encrypted copy is altered.');
                }

                [$plain, $tag] = $opened;
                fwrite($out, Scalars::text($plain));

                if ($tag === SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL) {
                    if (fread($in, 1) !== '') {
                        throw new RuntimeException('The encrypted copy has bytes after its end.');
                    }

                    break;
                }
            }
        } finally {
            fclose($in);

            if (is_resource($out)) {
                fclose($out);
            }
        }

        chmod($target, 0600);
    }

    /** @param  resource  $out */
    private static function put($out, \HashContext $hash, string $bytes): void
    {
        if (fwrite($out, $bytes) !== strlen($bytes)) {
            throw new RuntimeException('The encrypted copy cannot be written in full.');
        }

        hash_update($hash, $bytes);
    }

    private static function key(string $encoded, string $half): string
    {
        $key = base64_decode($encoded, true);

        if ($key === false || strlen($key) !== SODIUM_CRYPTO_BOX_PUBLICKEYBYTES) {
            throw new RuntimeException("The copy's {$half} key is not a base64-encoded X25519 key.");
        }

        return $key;
    }
}
