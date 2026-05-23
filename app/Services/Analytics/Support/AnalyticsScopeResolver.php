<?php

namespace App\Services\Analytics\Support;

use App\Exceptions\Analytics\ForbiddenScopeException;
use App\Models\SaleTrainerAssignment;
use Illuminate\Http\Request;

class AnalyticsScopeResolver
{
    public function resolve(Request $request): AnalyticsScope
    {
        $role = (string) $request->get('auth_role');
        $userId = (string) $request->get('auth_user_id');

        $reqTrainerId = $request->query('trainer_id');
        $reqSaleId = $request->query('sale_id');

        return match ($role) {
            'admin'   => $this->resolveAdmin($userId, $reqTrainerId, $reqSaleId),
            'sale'    => $this->resolveSale($userId, $reqTrainerId),
            'trainer' => $this->resolveTrainer($userId, $reqTrainerId),
            default   => new AnalyticsScope(role: $role, userId: $userId),
        };
    }

    private function resolveAdmin(string $userId, ?string $trainerId, ?string $saleId): AnalyticsScope
    {
        return new AnalyticsScope(
            role: 'admin',
            userId: $userId,
            trainerIds: [],
            overrideTrainerId: $trainerId ?: null,
            overrideSaleId: $saleId ?: null,
        );
    }

    private function resolveSale(string $userId, ?string $trainerId): AnalyticsScope
    {
        $roster = SaleTrainerAssignment::query()
            ->where('sale_user_id', $userId)
            ->pluck('trainer_user_id')
            ->all();

        if ($trainerId !== null && $trainerId !== '' && ! in_array($trainerId, $roster, true)) {
            throw new ForbiddenScopeException(
                'You cannot view analytics for a trainer outside your roster.',
                0,
                null,
                ['requested_trainer_id' => $trainerId],
            );
        }

        return new AnalyticsScope(
            role: 'sale',
            userId: $userId,
            trainerIds: $roster,
            overrideTrainerId: ($trainerId !== null && $trainerId !== '') ? $trainerId : null,
        );
    }

    private function resolveTrainer(string $userId, ?string $trainerId): AnalyticsScope
    {
        if ($trainerId !== null && $trainerId !== '' && $trainerId !== $userId) {
            throw new ForbiddenScopeException(
                'Trainers may only request analytics for themselves.',
                0,
                null,
                ['requested_trainer_id' => $trainerId],
            );
        }

        return new AnalyticsScope(
            role: 'trainer',
            userId: $userId,
            trainerIds: [$userId],
        );
    }
}
