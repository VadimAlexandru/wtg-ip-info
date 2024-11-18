<?php

namespace IpCountryDetector\Services;

use IpCountryDetector\Services\Drivers\RedisCacheService;
class IPCacheService
{
    private RedisCacheService $cacheService;

    public function __construct(RedisCacheService $cacheService)
    {
        $this->cacheService = $cacheService;
    }

    public function getCountryFromCache(string $ipAddress): ?string
    {
        return $this->cacheService->get($this->getCacheKey($ipAddress));
    }

    public function setCountryToCache(string $ipAddress, string $country): void
    {
        $this->cacheService->set($this->getCacheKey($ipAddress), $country);
    }

    private function getCacheKey(string $ipAddress): string
    {
        return config('ipcountry.redis.prefix') . '::' . $ipAddress;
    }
}
