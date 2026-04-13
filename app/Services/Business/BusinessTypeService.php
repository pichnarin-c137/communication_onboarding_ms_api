<?php

namespace App\Services\Business;

use App\Exceptions\Business\BusinessTypeInUseException;
use App\Exceptions\Business\BusinessTypeNotFoundException;
use App\Models\BusinessType;
use Illuminate\Support\Facades\Cache;

class BusinessTypeService
{
    public function list(int $perPage, int $page): array
    {
        $version  = (int) Cache::get('business_type:list_version', 1);
        $cacheKey = "business_type:list:v{$version}:page_{$page}_per_{$perPage}";
        $ttl      = config('coms.business.business_type_list_ttl', 600);

        return Cache::remember($cacheKey, $ttl, function () use ($perPage, $page): array {
            $paginator = BusinessType::orderBy('name_en')
                ->paginate($perPage, ['*'], 'page', $page);

            return [
                'data' => $paginator->items(),
                'meta' => [
                    'total'        => $paginator->total(),
                    'per_page'     => $paginator->perPage(),
                    'current_page' => $paginator->currentPage(),
                    'last_page'    => $paginator->lastPage(),
                    'from'         => $paginator->firstItem(),
                    'to'           => $paginator->lastItem(),
                ],
            ];
        });
    }

    public function get(string $id): BusinessType
    {
        $cacheKey = "business_type:{$id}";
        $ttl      = config('coms.business.business_type_show_ttl', 1800);

        $businessType = Cache::remember($cacheKey, $ttl, fn() => BusinessType::find($id));

        if (! $businessType) {
            throw new BusinessTypeNotFoundException(
                "Business type with ID '{$id}' not found.",
                context: ['business_type_id' => $id]
            );
        }

        return $businessType;
    }

    public function create(array $data): BusinessType
    {
        $businessType = BusinessType::create($data);

        $this->invalidateListCache();

        return $businessType;
    }

    public function update(BusinessType $businessType, array $data): BusinessType
    {
        $businessType->update($data);

        $this->invalidateListCache();
        Cache::forget("business_type:{$businessType->id}");

        return $businessType->fresh();
    }

    public function delete(BusinessType $businessType): void
    {
        if ($businessType->companies()->exists()) {
            throw new BusinessTypeInUseException(
                'This business type is in use and cannot be deleted.',
                context: ['business_type_id' => $businessType->id]
            );
        }

        $businessType->delete();

        $this->invalidateListCache();
        Cache::forget("business_type:{$businessType->id}");
    }

    private function invalidateListCache(): void
    {
        // Bump list cache version so all paginated key variants become stale at once.
        if (! Cache::has('business_type:list_version')) {
            Cache::forever('business_type:list_version', 2);

            return;
        }

        Cache::increment('business_type:list_version');
    }
}
