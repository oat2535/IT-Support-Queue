<?php

namespace App\Models\Concerns;

use Carbon\Carbon;
use DateTimeInterface;

trait FormatsNaiveTimestamps
{
    /**
     * Normalize a timestamp value to Asia/Bangkok naive format without microseconds.
     */
    public static function normalizeNaiveTimestamp(mixed $value, bool $zeroSeconds = false): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $carbon = $value instanceof DateTimeInterface
            ? Carbon::instance($value)->setTimezone('Asia/Bangkok')
            : Carbon::parse((string) $value, 'Asia/Bangkok')->setTimezone('Asia/Bangkok');

        return $carbon->format($zeroSeconds ? 'Y-m-d H:i:00' : 'Y-m-d H:i:s');
    }

    /**
     * Strip microseconds from specified model attributes before saving.
     *
     * @param  array<int, string>  $attributes
     * @param  array<int, string>  $zeroSecondsAttributes
     */
    protected function normalizeModelTimestamps(array $attributes, array $zeroSecondsAttributes = []): void
    {
        foreach ($attributes as $attribute) {
            $raw = $this->getAttributes()[$attribute] ?? null;
            if ($raw !== null && $raw !== '') {
                $this->attributes[$attribute] = self::normalizeNaiveTimestamp(
                    $raw,
                    in_array($attribute, $zeroSecondsAttributes, true),
                );
            }
        }
    }
}
