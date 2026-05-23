<?php

namespace App\Services\Analytics\Support;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * Period descriptor: current window, comparison window, bucket granularity, tz.
 *
 * `from` / `to` are interpreted as inclusive *date* boundaries in the business
 * timezone (Asia/Phnom_Penh by default). Internally we work with start-of-day
 * and end-of-day timestamps in that tz, then convert to UTC when binding to
 * Postgres so that timestamptz columns compare correctly.
 */
final class AnalyticsPeriod
{
    public function __construct(
        public readonly CarbonImmutable $from,
        public readonly CarbonImmutable $to,
        public readonly ?CarbonImmutable $compareFrom,
        public readonly ?CarbonImmutable $compareTo,
        public readonly string $groupBy,
        public readonly string $timezone,
        public readonly string $compareMode,
    ) {}

    public static function fromRequest(Request $request, string $defaultGroupBy = 'week'): self
    {
        $tz = config('coms.analytics.business_timezone', 'Asia/Phnom_Penh');
        $from = CarbonImmutable::parse($request->query('from'), $tz)->startOfDay();
        $to = CarbonImmutable::parse($request->query('to'), $tz)->endOfDay();

        $compareMode = (string) ($request->query('compare') ?? 'prev');
        $groupBy = (string) ($request->query('group_by') ?? $defaultGroupBy);

        [$cFrom, $cTo] = self::computeCompareWindow($from, $to, $compareMode);

        return new self($from, $to, $cFrom, $cTo, $groupBy, $tz, $compareMode);
    }

    private static function computeCompareWindow(
        CarbonImmutable $from,
        CarbonImmutable $to,
        string $mode,
    ): array {
        if ($mode === 'none') {
            return [null, null];
        }

        if ($mode === 'yoy') {
            return [$from->subYear(), $to->subYear()];
        }

        // prev: same-length window immediately before `from`
        $lengthSeconds = (int) $from->diffInSeconds($to, true);
        $cTo = $from->subSecond();
        $cFrom = $cTo->subSeconds($lengthSeconds);

        return [$cFrom, $cTo];
    }

    /**
     * Postgres expression that truncates a timestamp/date column to the current
     * group_by, in the business timezone, returned as a DATE.
     *
     * Example: bucketExpression('appointments.scheduled_date')
     *          → date_trunc('week', (appointments.scheduled_date)::timestamp AT TIME ZONE 'Asia/Phnom_Penh')::date
     *
     * Use this in raw SELECT/GROUP BY.
     */
    public function bucketExpression(string $column): string
    {
        $tz = $this->timezone;
        // Cast to timestamp so AT TIME ZONE works for both date and timestamp columns.
        return sprintf(
            "date_trunc('%s', ((%s)::timestamp AT TIME ZONE '%s'))::date",
            $this->groupBy,
            $column,
            $tz,
        );
    }

    /**
     * Same as bucketExpression() but assumes the column is already timestamptz
     * (so we just shift to local tz, truncate, cast to date).
     */
    public function bucketExpressionTz(string $column): string
    {
        return sprintf(
            "date_trunc('%s', (%s AT TIME ZONE '%s'))::date",
            $this->groupBy,
            $column,
            $this->timezone,
        );
    }

    /**
     * Generate the list of bucket dates between `from` and `to` for the current
     * group_by. Used to pad zero-buckets in time-series responses.
     *
     * @return list<string>  ISO Y-m-d strings, ascending
     */
    public function bucketDates(): array
    {
        $start = match ($this->groupBy) {
            'day'   => $this->from->startOfDay(),
            'week'  => $this->from->startOfWeek(\Carbon\CarbonInterface::MONDAY),
            'month' => $this->from->startOfMonth(),
        };

        $end = match ($this->groupBy) {
            'day'   => $this->to->startOfDay(),
            'week'  => $this->to->startOfWeek(\Carbon\CarbonInterface::MONDAY),
            'month' => $this->to->startOfMonth(),
        };

        $step = match ($this->groupBy) {
            'day'   => '1 day',
            'week'  => '1 week',
            'month' => '1 month',
        };

        $out = [];
        for ($d = $start; $d->lte($end); $d = $d->add($step)) {
            $out[] = $d->toDateString();
        }

        return $out;
    }
}
