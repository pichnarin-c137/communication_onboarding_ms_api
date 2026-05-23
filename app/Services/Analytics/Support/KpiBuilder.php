<?php

namespace App\Services\Analytics\Support;

/**
 * Build the standardised KPI object used across the analytics API:
 *   { value, previous, delta_pct, good_direction }
 *
 * Conventions:
 *   - delta_pct rounded to 1 decimal
 *   - delta_pct = null when previous is 0 or null (avoids /0 and meaningless ±Inf)
 *   - `good_direction` = "up" or "down"; controls the FE pill colour
 */
final class KpiBuilder
{
    public static function build(
        int|float|null $value,
        int|float|null $previous,
        string $goodDirection = 'up',
    ): array {
        $value = $value === null ? null : (is_float($value) ? round($value, 4) : $value);
        $previous = $previous === null ? null : (is_float($previous) ? round($previous, 4) : $previous);

        $deltaPct = null;
        if ($previous !== null && $previous != 0 && $value !== null) {
            $deltaPct = round((($value - $previous) / $previous) * 100, 1);
        }

        return [
            'value'          => $value,
            'previous'       => $previous,
            'delta_pct'      => $deltaPct,
            'good_direction' => $goodDirection,
        ];
    }
}
