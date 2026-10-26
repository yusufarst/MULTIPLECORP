<?php

namespace App\Modules\IAM\Authentication;

/**
 * SECURITY AU-03 (1): the locally bundled list of common passwords, checked first and with no network call. The
 * list is the project's own compilation — widely published common choices and their usual decorations, English
 * and Indonesian, no third-party file and so no licence beyond the repository's (DEP-07) —; a password is
 * compared in lower case after NFC normalization, its trailing digits and symbols also stripped, so "Password1!"
 * meets "password".
 */
final class CommonPasswords
{
    private const WORDS = [
        '123456', '1234567', '12345678', '123456789', '1234567890', '111111', '000000', '121212', '654321', '666666',
        '696969', '112233', '123123', '123321', '7777777', '88888888', 'password', 'passw0rd', 'p@ssw0rd', 'p@ssword',
        'pass', 'passwort', 'qwerty', 'qwertyuiop', 'qwerty123', 'asdfgh', 'asdfghjkl', 'zxcvbn', 'zxcvbnm', '1q2w3e',
        '1q2w3e4r', '1qaz2wsx', 'qazwsx', 'abc123', 'abcd1234', 'abcdef', 'admin', 'administrator', 'root', 'toor',
        'letmein', 'welcome', 'welcome1', 'login', 'master', 'secret', 'sunshine', 'princess', 'dragon', 'monkey',
        'football', 'baseball', 'soccer', 'superman', 'batman', 'iloveyou', 'trustno1', 'starwars', 'whatever', 'shadow',
        'michael', 'jennifer', 'charlie', 'freedom', 'hello', 'hello123', 'changeme', 'default', 'guest', 'test',
        'test123', 'testing', 'user', 'access', 'computer', 'internet', 'samsung', 'google', 'facebook', 'instagram',
        'indonesia', 'jakarta', 'bandung', 'surabaya', 'yogyakarta', 'garuda', 'merdeka', 'bismillah', 'alhamdulillah',
        'insyaallah', 'sayang', 'sayangku', 'cinta', 'cintaku', 'rahasia', 'katasandi', 'sandi', 'kucing', 'anjing',
        'bunga', 'mawar', 'melati', 'ganteng', 'cantik', 'manis', 'semangat', 'sukses', 'rezeki', 'barokah',
        'perusahaan', 'kantor', 'gudang', 'keuangan', 'proyek', 'admin123', 'qwe123', 'asd123', 'zaq12wsx', 'aaaaaa',
    ];

    /** @var array<array-key, true>|null */
    private static ?array $index = null;

    public static function contains(string $password): bool
    {
        self::$index ??= array_fill_keys(self::WORDS, true);
        $lower = mb_strtolower($password);
        $stem = preg_replace('/[\d\W_]+$/u', '', $lower) ?? $lower;

        return isset(self::$index[$lower]) || ($stem !== '' && isset(self::$index[$stem]));
    }
}
