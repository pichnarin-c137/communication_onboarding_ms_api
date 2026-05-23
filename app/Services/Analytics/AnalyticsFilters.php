<?php

namespace App\Services\Analytics;

/**
 * Shared filter helpers — applied AFTER scope filters on every analytics query
 * that respects the common query params (location_type, system_id, ...).
 *
 * NOTE on business_type_id: the current schema has no direct link from a
 * `client` to a `business_type`. The `companies` table carries `business_type_id`
 * but isn't joined to clients/appointments today. Until that link is added,
 * the business_type_id filter is intentionally a no-op. Documented in the
 * /docs/ApiResponse deviations section.
 */
final class AnalyticsFilters
{
    public static function applyAppointment($q, array $filters): void
    {
        if (! empty($filters['location_type'])) {
            $q->where('appointments.location_type', $filters['location_type']);
        }
        // system_id and business_type_id intentionally no-op: see class docblock.
    }

    public static function applyOnboarding($q, array $filters): void
    {
        // onboarding_requests has neither location_type nor system_id; nothing to apply.
        // business_type_id intentionally no-op (see class docblock).
    }
}
