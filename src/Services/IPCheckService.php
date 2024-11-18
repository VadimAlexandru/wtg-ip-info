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

    public function ipToCountry(string $ipAddress = null, string $timeZone = 'UTC'): string|array|object
    {
        try {
            $cachedCountry = $this->getCachedCountryOrFetch($ipAddress);
            if (is_string($cachedCountry) && $cachedCountry) {
                return json_decode($cachedCountry, true);
            }

            $ipLong = $this->validateAndConvertIp($ipAddress);
            $country = $this->findCountryByIp($ipLong);
            if (!empty($country) && $country !== CountryStatus::IP_NOT_IN_RANGE->value) {
                $this->ipCacheService->setCountryToCache($ipAddress, $country);
                return $country;
            }

            return $this->fetchCountryAndCache($ipAddress);
        } catch (\Exception $e) {
            Log::error('Error determining country by IP: ' . $e->getMessage());

            return $this->timeZoneToCountry($timeZone) ?? CountryStatus::UNKNOWN;
        }
    }

    private function validateAndConvertIp(string $ipAddress): bool|int|string
    {
        if (!filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) &&
            !filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            throw new \InvalidArgumentException('Invalid IP address');
        }

        return filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
            ? ip2long($ipAddress)
            : $ipAddress;
    }

    private function getCachedCountryOrFetch(string $ipAddress): ?string
    {
        $cachedCountry = $this->ipCacheService->getCountryFromCache($ipAddress);
        return $cachedCountry ?: null;
    }

    private function fetchCountryAndCache(string $ipAddress): array|string
    {
        $country = $this->fetchCountryFromApi($ipAddress);
        if ($country !== 'Country not found') {
            $this->ipCacheService->setCountryToCache($ipAddress, $country);
            return $country;
        }

        return CountryStatus::NOT_FOUND->value;
    }

    public function timeZoneToCountry(string $timeZone): array|string
    {
        try {
            $timezone = new \DateTimeZone($timeZone);
            $region = $timezone->getLocation();

            return strtoupper($region['country_code']);
        } catch (\Exception $e) {
            return CountryStatus::NOT_FOUND->value;
        }
    }

    private function findCountryByIp(int $ipLong): array|int|IpCountry
    {
        $result = IpCountry::select('country', 'region', 'subregion', 'city', 'timezone', 'latitude', 'longitude')
            ->where('first_ip', '<=', $ipLong)
            ->where('last_ip', '>=', $ipLong)
            ->first();
        return $result ? $result->toArray() : CountryStatus::IP_NOT_IN_RANGE->value;
    }

    private function fetchCountryFromApi(string $ipAddress): string|array
    {
        return $this->ipApiService->getCountry($ipAddress);
    }
}
