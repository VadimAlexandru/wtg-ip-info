<?php

namespace IpCountryDetector\Services;

use Exception;
use Illuminate\Support\Facades\Log;
use IpCountryDetector\Enums\CountryStatus;

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
            if ($cachedCountry) {
                return CountryStatus::SUCCESS->value;
            }

            $ipLong = $this->validateAndConvertIp($ipAddress);

            $country = $this->findCountryByIp($ipLong);
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

    private function fetchCountryAndCache(string $ipAddress): string
    {
        $country = $this->fetchCountryFromApi($ipAddress);
        if ($country !== 'Country not found') {
            $this->ipCacheService->setCountryToCache($ipAddress, $country);
            return CountryStatus::SUCCESS->value;
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

    private function findCountryByIp(int $ipLong): string
    {
        $result = \DB::table('ip_country')
            ->where('first_ip', '<=', $ipLong)
            ->where('last_ip', '>=', $ipLong)
            ->select('country')
            ->first();

        if ($result) {
            return CountryStatus::SUCCESS->value;
        }

        return CountryStatus::IP_NOT_IN_RANGE->value;
    }

    private function fetchCountryFromApi(string $ipAddress): string
    {
        return $this->ipApiService->getCountry($ipAddress);
    }
}
