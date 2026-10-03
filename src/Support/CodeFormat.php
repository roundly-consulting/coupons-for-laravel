<?php

declare(strict_types=1);

namespace RoundlyConsulting\Coupons\Support;

use Random\Randomizer;
use RoundlyConsulting\Coupons\Exceptions\InvalidCouponConfiguration;
use RoundlyConsulting\PackageToolkit\Support\Config;

/**
 * The auto-generated code format from `coupons.code.*`, validated on every read so a
 * misconfigured key fails loudly instead of silently producing codes nobody asked for.
 *
 * Symbols are drawn with PHP's own Randomizer, whose default engine is the CSPRNG. It is
 * deliberately not crypto-for-laravel's token helper: pulling that in would widen the
 * `require` graph of every package built on coupons for one uniform pick.
 */
final class CodeFormat
{
    public const int MIN_LENGTH = 4;

    public const int MAX_LENGTH = 64;

    public const int MIN_SYMBOLS = 2;

    public const int DEFAULT_LENGTH = 6;

    public const string DEFAULT_CHARSET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

    /**
     * @throws InvalidCouponConfiguration when the length is not an integer in MIN_LENGTH..MAX_LENGTH.
     */
    public static function length(): int
    {
        return Config::using(InvalidCouponConfiguration::class)
            ->integer('coupons.code.length', self::DEFAULT_LENGTH, min: self::MIN_LENGTH, max: self::MAX_LENGTH);
    }

    /**
     * The alphabet split into symbols (multibyte-safe), upper-cased like every code. An absent
     * key falls back to the shipped alphabet; anything present must be a usable one.
     *
     * @return list<string>
     *
     * @throws InvalidCouponConfiguration when the alphabet is empty, not a string, holds
     *                                    whitespace/control characters, repeats a symbol
     *                                    (case-insensitively),
     *                                    or has fewer than MIN_SYMBOLS symbols.
     */
    public static function symbols(): array
    {
        $charset = config('coupons.code.charset') ?? self::DEFAULT_CHARSET;

        if (! is_string($charset) || $charset === '') {
            throw InvalidCouponConfiguration::invalidCharset('must be a non-empty string');
        }

        // `preg_match` returns false on invalid UTF-8, which is just as unusable.
        if (preg_match('/[\s\p{C}]/u', $charset) !== 0) {
            throw InvalidCouponConfiguration::invalidCharset('must not contain whitespace or control characters');
        }

        // Codes are case-insensitive and stored upper-cased, so the alphabet is too: `a` and
        // `A` are one symbol, and an alphabet holding both would silently shrink the key space.
        $symbols = array_map(
            static fn (string $symbol): string => mb_strtoupper($symbol, 'UTF-8'),
            mb_str_split($charset),
        );

        if (count(array_unique($symbols)) !== count($symbols)) {
            throw InvalidCouponConfiguration::invalidCharset('must not contain duplicate symbols (case-insensitively)');
        }

        if (count($symbols) < self::MIN_SYMBOLS) {
            throw InvalidCouponConfiguration::invalidCharset('must contain at least '.self::MIN_SYMBOLS.' symbols');
        }

        return $symbols;
    }

    /**
     * The canonical form of a code: surrounding whitespace (Unicode spaces included) trimmed,
     * upper-cased. Codes are case-insensitive, so every write and every lookup goes through
     * this — matching and uniqueness never depend on the database collation.
     */
    public static function normalize(string $code): string
    {
        // `preg_replace` returns null on invalid UTF-8; a plain trim is the best that input gets.
        $trimmed = preg_replace('/^[\s\p{Z}\x{200B}\x{FEFF}]+|[\s\p{Z}\x{200B}\x{FEFF}]+$/u', '', $code) ?? trim($code);

        return mb_strtoupper($trimmed, 'UTF-8');
    }

    /**
     * One candidate code: `length()` symbols drawn uniformly from `symbols()`.
     */
    public static function generate(Randomizer $randomizer): string
    {
        $symbols = self::symbols();
        $length = self::length();
        $max = count($symbols) - 1;
        $code = '';

        for ($i = 0; $i < $length; $i++) {
            $code .= $symbols[$randomizer->getInt(0, $max)];
        }

        return $code;
    }

    /**
     * The `about` line. The alphabet is reported by size only: printing it would hand a
     * brute-forcer the exact key space generated codes are drawn from.
     */
    public static function describe(): string
    {
        try {
            return self::length().' chars from a '.count(self::symbols()).'-symbol alphabet';
        } catch (InvalidCouponConfiguration $e) {
            return 'INVALID: '.$e->getMessage();
        }
    }
}
