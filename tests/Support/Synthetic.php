<?php

namespace Tests\Support;

use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Invented values from a fixed seed (TEST_STRATEGY TS-04 and §4): one seed gives the same values on
 * every machine and in every run. Nothing is copied from a real document — e-mail addresses use the
 * reserved example domains, and telephone, tax and bank-account numbers carry a leading block that no
 * real number uses, so none of them can reach or name a real person or company.
 */
final class Synthetic
{
    public const SEED = 20261020;

    /** The reserved example domains (RFC 2606). */
    public const EMAIL_DOMAINS = ['example.com', 'example.net', 'example.org'];

    private const GIVEN_NAMES = ['Ayu', 'Bayu', 'Citra', 'Dimas', 'Eka', 'Fajar', 'Gita', 'Hendra', 'Indah', 'Joko', 'Kartika', 'Lukman'];

    private const FAMILY_NAMES = ['Contoh', 'Percobaan', 'Sampel', 'Uji', 'Fiksi', 'Rekaan'];

    private const WORDS = ['Cahaya', 'Lestari', 'Harapan', 'Sentosa', 'Makmur', 'Abadi', 'Bersama', 'Utama', 'Jaya', 'Gemilang', 'Sejati', 'Mulia'];

    private Randomizer $random;

    public function __construct(int $seed = self::SEED)
    {
        $this->random = new Randomizer(new Mt19937($seed));
    }

    public function companyName(): string
    {
        return 'PT Contoh '.$this->pick(self::WORDS).' '.$this->pick(self::WORDS);
    }

    public function personName(): string
    {
        return $this->pick(self::GIVEN_NAMES).' '.$this->pick(self::FAMILY_NAMES);
    }

    public function email(string $localPart): string
    {
        return $localPart.'@'.$this->pick(self::EMAIL_DOMAINS);
    }

    /** An Indonesian-format number in the unassigned area block 000. */
    public function phone(): string
    {
        return '+62 000 '.$this->digits(4).' '.$this->digits(4);
    }

    /** Sixteen digits opening with 00, which is no province code, so it is no NIK or NPWP. */
    public function taxNumber(): string
    {
        return '00'.$this->digits(14);
    }

    public function bankAccount(): string
    {
        return '0000'.$this->digits(6);
    }

    public function address(): string
    {
        return 'Jl. Contoh '.$this->pick(self::WORDS).' No. '.$this->random->getInt(1, 199).', Kota Contoh '.$this->pick(self::WORDS);
    }

    /** Whole rupiah as the exact decimal string money is carried in, never a float (DEVELOPMENT.md). */
    public function amount(int $min, int $max): string
    {
        return $this->random->getInt($min, $max).'.00';
    }

    /**
     * @template T
     *
     * @param  non-empty-list<T>  $items
     * @return T
     */
    public function pick(array $items): mixed
    {
        return $items[$this->random->getInt(0, count($items) - 1)];
    }

    private function digits(int $length): string
    {
        return $this->random->getBytesFromString('0123456789', $length);
    }
}
