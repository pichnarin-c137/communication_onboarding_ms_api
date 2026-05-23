<?php

namespace App\Services\Analytics\Support;

final class AnalyticsCacheKey
{
    public static function build(string $endpoint, AnalyticsScope $scope, array $queryParams): string
    {
        $params = $queryParams;
        ksort($params);
        $serialised = http_build_query($params);

        $hash = sha1(implode('|', [
            $scope->role,
            $scope->userId,
            $scope->overrideTrainerId ?? '',
            $scope->overrideSaleId ?? '',
            $serialised,
        ]));

        return "analytics:{$endpoint}:{$hash}";
    }
}
