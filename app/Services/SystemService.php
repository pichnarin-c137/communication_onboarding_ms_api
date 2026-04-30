<?php

namespace App\Services;

use App\Models\System;

class SystemService
{
    public function listSystems(array $filters = []): array
    {
        $query = System::where('is_active', true)->orderBy('name');

        if (! empty($filters['search'])) {
            $query->where('name', 'like', "%{$filters['search']}%")
                ->orWhere('code', 'like', "%{$filters['search']}%");
        }

        return $query->get(['id', 'code', 'name'])
            ->map(function ($system) {
                return [
                    'value' => $system->id,
                    'label' => ucfirst($system->name)." ($system->code)",
                ];
            })
            ->toArray();
    }
}
