<?php

namespace App\Services\Business;

use App\Exceptions\Business\CompanyNotFoundException;
use App\Models\Company;
use Illuminate\Support\Facades\Cache;

class CompanyService
{
    public function list(array $filters, int $perPage, int $page): array
    {
        $filterHash = md5(serialize($filters));
        $cacheKey = "company:list:$filterHash:page_{$page}_per_$perPage";
        $ttl = config('coms.business.company_list_ttl', 300);

        return Cache::remember($cacheKey, $ttl, function () use ($filters, $perPage, $page): array {
            $query = Company::with(['businessType', 'logo'])
                ->orderBy('name_en');

            if (! empty($filters['business_type_id'])) {
                $query->where('business_type_id', $filters['business_type_id']);
            }

            if (! empty($filters['search'])) {
                $search = '%'.$filters['search'].'%';
                $query->where(function ($q) use ($search): void {
                    $q->where('name_en', 'ilike', $search)
                        ->orWhere('name_km', 'ilike', $search);
                });
            }

            $paginator = $query->paginate($perPage, ['*'], 'page', $page);

            return [
                'data' => $paginator->items(),
                'meta' => [
                    'total' => $paginator->total(),
                    'per_page' => $paginator->perPage(),
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'from' => $paginator->firstItem(),
                    'to' => $paginator->lastItem(),
                ],
            ];
        });
    }

    /**
     * @throws CompanyNotFoundException
     */
    public function get(string $id): Company
    {
        $cacheKey = "company:$id";
        $ttl = config('coms.business.company_show_ttl', 600);

        $company = Cache::remember(
            $cacheKey,
            $ttl,
            fn () => Company::with(['businessType', 'logo', 'patentDocument'])->find($id)
        );

        if (! $company) {
            throw new CompanyNotFoundException(
                "Company with ID '$id' not found.",
                context: ['company_id' => $id]
            );
        }

        return $company;
    }

    public function create(array $data, string $userId): Company
    {
        $company = Company::create(array_merge($data, ['created_by_user_id' => $userId]));

        $this->invalidateListCache();

        return $company->load(['businessType', 'logo', 'patentDocument']);
    }

    public function update(Company $company, array $data): Company
    {
        $company->update($data);

        $this->invalidateListCache();
        Cache::forget("company:$company->id");

        return $company->fresh(['businessType', 'logo', 'patentDocument']);
    }

    public function delete(Company $company): void
    {
        $company->delete();

        $this->invalidateListCache();
        Cache::forget("company:$company->id");
    }

    private function invalidateListCache(): void
    {
        for ($p = 1; $p <= 5; $p++) {
            foreach ([15, 25, 50, 100] as $pp) {
                // We can't flush all filter combos, so we clear the no-filter variants
                $noFilterKey = 'company:list:'.md5(serialize([])).":page_{$p}_per_$pp";
                Cache::forget($noFilterKey);
            }
        }
    }
}
