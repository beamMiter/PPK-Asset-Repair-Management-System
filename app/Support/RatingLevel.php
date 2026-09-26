<?php

namespace App\Support;

/**
 * What a satisfaction score is called, in one place. The team board and a person's own page each kept a copy and the copies
 * disagreed: the same 4.2 was "ดี" on the board and "ดีมาก" on the person's page, and a technician nobody had rated yet was
 * "ควรปรับปรุง" (an average of nothing counted as 0.00).
 *
 * The colours are not here on purpose: Tailwind only reads `resources/`, so a class name that lives in a PHP file would never
 * be generated. `<x-rating.level>` maps the key returned here to its colours.
 */
final class RatingLevel
{
    public const GREAT = 'great';    // 4.5 and up
    public const GOOD = 'good';      // 4.0 – 4.49
    public const FAIR = 'fair';      // 3.0 – 3.99
    public const POOR = 'poor';      // under 3.0
    public const NONE = 'none';      // nobody has rated yet

    /** Fewer ratings than this say little about a person: one 5-star review is not "consistently excellent". */
    public const ENOUGH_REVIEWS = 5;

    /** @return array{key: string, label: string} */
    public static function of(?float $average, int $reviews): array
    {
        $key = match (true) {
            $reviews <= 0 || $average === null => self::NONE,
            $average >= 4.5 => self::GREAT,
            $average >= 4.0 => self::GOOD,
            $average >= 3.0 => self::FAIR,
            default => self::POOR,
        };

        return ['key' => $key, 'label' => self::label($key)];
    }

    public static function label(string $key): string
    {
        return match ($key) {
            self::GREAT => 'ดีมาก',
            self::GOOD => 'ดี',
            self::FAIR => 'ปานกลาง',
            self::POOR => 'ควรปรับปรุง',
            default => 'ยังไม่มีการประเมิน',
        };
    }

    /** What one rater's 1–5 stars are called. */
    public static function scoreLabel(int $score): string
    {
        return match (true) {
            $score >= 5 => 'พอใจมาก',
            $score === 4 => 'พอใจ',
            $score === 3 => 'ปานกลาง',
            $score === 2 => 'ไม่ค่อยพอใจ',
            default => 'ไม่พอใจ',
        };
    }

    public static function isThin(int $reviews): bool
    {
        return $reviews < self::ENOUGH_REVIEWS;
    }
}
