<?php

namespace App\Services\Crm;

use App\Models\CrmContact;
use App\Models\CrmDeal;
use App\Models\User;

/**
 * Pure formatter that maps CRM Eloquent models to the frontend API contract shape.
 * Stateless and dependency-free — not a domain service, so it does not violate the
 * service-isolation rule when reused by multiple CRM services.
 */
final class CrmPresenter
{
    public static function contact(CrmContact $contact): array
    {
        return [
            'id' => $contact->id,
            'company_name' => $contact->company_name,
            'company_name_kh' => $contact->company_name_kh,
            'contact_name' => $contact->contact_name,
            'phone' => $contact->phone,
            'email' => $contact->email,
            'address' => $contact->address,
            'business_type' => $contact->relationLoaded('businessType') && $contact->businessType
                ? ['id' => $contact->businessType->id, 'name' => $contact->businessType->name_en]
                : null,
            'source' => $contact->source,
            'status' => $contact->status,
            'active_deals_count' => (int) ($contact->active_deals_count ?? 0),
            'notes' => $contact->notes ?? '',
            'created_by' => self::user($contact->relationLoaded('creator') ? $contact->creator : null),
            'created_at' => $contact->created_at?->toIso8601String(),
        ];
    }

    public static function deal(CrmDeal $deal): array
    {
        return [
            'id' => $deal->id,
            'contact_id' => $deal->crm_contact_id,
            'title' => $deal->title,
            'stage' => $deal->stage,
            'value' => $deal->value !== null ? (float) $deal->value : null,
            'expected_close_date' => $deal->expected_close_date?->toDateString(),
            'notes' => $deal->notes ?? '',
            'assigned_to' => self::user($deal->relationLoaded('assignee') ? $deal->assignee : null),
            'won_at' => $deal->won_at?->toIso8601String(),
            'demo_completed_at' => $deal->demo_completed_at?->toIso8601String(),
            'lost_reason' => $deal->lost_reason,
            'client_id' => $deal->client_id,
            'created_by' => self::user($deal->relationLoaded('creator') ? $deal->creator : null),
            'created_at' => $deal->created_at?->toIso8601String(),
        ];
    }

    /**
     * Shape a related user (assignee / creator) to `{id, name}` or null.
     * Returns null when the relation was not eager-loaded or is absent.
     */
    private static function user(?User $user): ?array
    {
        if (! $user) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => trim("{$user->first_name} {$user->last_name}"),
        ];
    }
}
