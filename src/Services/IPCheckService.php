<?php

namespace IpCountryDetector\Services;

use Exception;
use Illuminate\Support\Facades\Log;
use IpCountryDetector\Enums\CountryStatus;
use IpCountryDetector\Models\IpCountry;

class IPCheckService
{
    private IPCacheService $ipCacheService;
    private IpApiService $ipApiService;

    public function __construct(IPCacheService $ipCacheService, IpApiService $ipApiService)
    {
        $this->ipCacheService = $ipCacheService;
        $this->ipApiService = $ipApiService;
    }

    public function ipToCountry(string $ipAddress = null, string $timeZone = null): string
    {
        try {
            $cachedCountry = $this->getCachedCountryOrFetch($ipAddress);
            if (is_string($cachedCountry) && $cachedCountry) {
                return $cachedCountry;
            }

            $country = $this->findCountryByIp($ipAddress);
            if ($country != CountryStatus::IP_NOT_IN_RANGE->value) {
                $this->ipCacheService->setCountryToCache($ipAddress, $country);
                return $country;
            }

            return $this->fetchCountryAndCache($ipAddress);
        } catch (\Exception $e) {
            Log::error('Error determining country by IP: ' . $e->getMessage());

            return $this->timeZoneToCountry($timeZone) ?? CountryStatus::UNKNOWN->value;
        }
    }

    private function validateAndConvertIp(string $ipAddress): bool|int|string
    {
        if (!filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) &&
            !filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return false;
        }

        return filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
            ? ip2long($ipAddress)
            : false;
    }

    private function getCachedCountryOrFetch(string $ipAddress): ?string
    {
        $cachedCountry = $this->ipCacheService->getCountryFromCache($ipAddress);
        return $cachedCountry ?: null;
    }

    private function fetchCountryAndCache(string $ipAddress): string
    {
        $country = $this->fetchCountryFromApi($ipAddress);
        if ($country !== 'Country not found') {
            $this->ipCacheService->setCountryToCache($ipAddress, $country);
            return $country;
        }

        return CountryStatus::NOT_FOUND->value;
    }

    public function timeZoneToCountry(string $timeZone): string
    {
        try {
            $timezone = new \DateTimeZone($timeZone);
            $region = $timezone->getLocation();

            return strtoupper($region['country_code']);
        } catch (\Exception $e) {
            return CountryStatus::NOT_FOUND->value;
        }
    }

    private function findCountryByIp($ipAddress): int|string
    {
        $ipLong = $this->validateAndConvertIp($ipAddress);

        if (!$ipLong) {
            return $ipLong;
        }

        $result = IpCountry::where('first_ip', '<=', $ipLong)
            ->where('last_ip', '>=', $ipLong)
            ->select('country')
            ->first();

        return $result ? $result->country : CountryStatus::IP_NOT_IN_RANGE->value;
    }

    private function fetchCountryFromApi(string $ipAddress): string
    {
        return $this->ipApiService->getCountry($ipAddress);
    }
}
