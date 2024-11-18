<?php

namespace IpCountryDetector\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use IpCountryDetector\Enums\CountryStatus;
use IpCountryDetector\Services\IPCheckService;

class IPCheckController extends Controller
{
    private IPCheckService $ipCheckService;

    public function __construct(IPCheckService $ipCheckService)
    {
        $this->ipCheckService = $ipCheckService;
    }

    /**
     * @throws Exception
     */

    public function checkIP(Request $request): array
    {
        $ipAddress = $request->input('ip')
            ?? $request->header('CF-Connecting-IP')
            ?? $request->ip();

        $timeZone = $request->input('timezone', 'UTC');
        $countryStatus = CountryStatus::UNKNOWN;
        $country = null;

        try {
            if (empty($ipAddress) || $ipAddress === '127.0.0.1' || $ipAddress === '::1') {
                Log::info('Local IP or missing IP detected, using timezone and country fallback.');
                $country = $this->ipCheckService->timeZoneToCountry($timeZone);
                $countryStatus = $country ? CountryStatus::IP_NOT_IN_RANGE : CountryStatus::NOT_FOUND;
            } else {
                $country = $this->ipCheckService->ipToCountry($ipAddress, $timeZone);
                $countryStatus = CountryStatus::SUCCESS;
            }
        } catch (\Exception $e) {
            Log::warning("Error detected: {$e->getMessage()}");
            $country = 'Unknown';
            $countryStatus = CountryStatus::NOT_FOUND;
        }

        return [
            'ip' => $ipAddress,
            'country' => $country['country'] ?? 'Unknown',
            'region' => $country['region'] ?? 'Unknown',
            'subregion' => $country['subregion'] ?? 'Unknown',
            'city' => $country['city'] ?? 'Unknown',
            'timezone' => $timeZone,
            'latitude' => $country['latitude'] ?? 'Unknown',
            'longitude' => $country['longitude'] ?? 'Unknown',
            'status' => $countryStatus->value
        ];

    }

}