<?php

namespace App\Observers;

use App\Models\OnboardingRequest;
use App\Models\OnboardingStatusHistory;
use Illuminate\Support\Str;

class OnboardingRequestStatusObserver
{
    public function created(OnboardingRequest $request): void
    {
        // Seed the initial row so the first stage's duration is computable.
        OnboardingStatusHistory::create([
            'id' => (string) Str::uuid(),
            'onboarding_id' => $request->id,
            'from_status' => null,
            'to_status' => $request->status ?? 'pending',
            'changed_at' => $request->created_at ?? now(),
            'changed_by_user_id' => $this->actorId(),
            'reason' => null,
        ]);
    }

    public function updated(OnboardingRequest $request): void
    {
        if (! $request->wasChanged('status')) {
            return;
        }

        OnboardingStatusHistory::create([
            'id' => (string) Str::uuid(),
            'onboarding_id' => $request->id,
            'from_status' => $request->getOriginal('status'),
            'to_status' => $request->status,
            'changed_at' => now(),
            'changed_by_user_id' => $this->actorId(),
            'reason' => $request->hold_reason ?? $request->revision_note ?? null,
        ]);
    }

    private function actorId(): ?string
    {
        if (! app()->bound('request')) {
            return null;
        }

        $id = request()->get('auth_user_id');

        return is_string($id) && $id !== '' ? $id : null;
    }
}
