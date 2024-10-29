<?php

namespace IpCountryDetector\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
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

        try {
            if ($ipAddress) {
                $country = $this->ipCheckService->ipToCountry($ipAddress, $timeZone);
                $countryStatus = CountryStatus::SUCCESS;
            } else {
                $country = null;
                $countryStatus = CountryStatus::IP_NOT_IN_RANGE;
            }
        } catch (\Exception $e) {
            Log::warning("IP to Country failed, switching to timezone: {$e->getMessage()}");
            $country = null;
            $countryStatus = CountryStatus::NOT_FOUND;
        }

        if (!$country) {
            try {
                $country = $this->ipCheckService->timeZoneToCountry($timeZone);
            } catch (\Exception $e) {
                Log::warning("TimeZone to Country failed: {$e->getMessage()}");
                $country = 'Unknown';
                $countryStatus = CountryStatus::NOT_FOUND;
            }
        }

        if (empty($ipAddress) || $ipAddress === '127.0.0.1') {
            Log::info('Local IP or missing IP detected, using timezone and country fallback.');
            return [
                'timezone' => $timeZone,
                'country' => $country,
                'status' => $countryStatus->value
            ];
        }

        return [
            'ip' => $ipAddress,
            'timezone' => $timeZone,
            'country' => $country,
            'status' => $countryStatus->value
        ];
    }


    /**
     * @throws Exception
     */
    public function checkIPFromEntry($ipAddress): ?string
    {
        if ($ipAddress == '127.0.0.1' || $ipAddress == '::1') {
            return null;
        }
        $country = $this->ipCheckService->ipToCountrySimple($ipAddress);

        if (empty($country)) {
            return null;
        }

        return $country;
    }
}
