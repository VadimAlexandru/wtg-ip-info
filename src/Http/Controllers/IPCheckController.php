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
        $country = null;

        try {
            if (empty($ipAddress) || $ipAddress === '127.0.0.1' || $ipAddress === '::1') {
                Log::info('Local IP or missing IP detected, using timezone and country fallback.');
                $country = $this->ipCheckService->timeZoneToCountry($timeZone);
                $countryStatus = CountryStatus::IP_NOT_IN_RANGE;
            } else {
                $country = $this->ipCheckService->ipToCountry($ipAddress, $timeZone);
                $countryStatus = CountryStatus::SUCCESS;
            }
        } catch (\Exception $e) {
            Log::warning("IP to Country failed, switching to timezone: {$e->getMessage()}");
            $country = $this->ipCheckService->timeZoneToCountry($timeZone);
            $countryStatus = CountryStatus::NOT_FOUND;
        }

        return [
            'ip' => $ipAddress,
            'timezone' => $timeZone,
            'country' => $country ?? 'Unknown',
            'status' => $countryStatus->value
        ];
    }

}
