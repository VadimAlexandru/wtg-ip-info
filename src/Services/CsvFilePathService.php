<?php

namespace IpCountryDetector\Services;

use Illuminate\Support\Facades\Storage;
class CsvFilePathService
{
    private const TEMP_CSV_FILE = 'geolite2-city-ipv4-num.csv';
    private const TEMP_GZ_FILE = 'geolite2-city-ipv4-num.csv.gz';

    /**
     * Get the full path to the CSV file.
     *
     * @return string
     */
    public function getCsvFilePath(): string
    {
        return Storage::path(self::TEMP_CSV_FILE);
    }

    public function getCsvFileName(): string
    {
        return self::TEMP_CSV_FILE;
    }

    public function putCsvFile($response): string
    {
        return Storage::put($this->getCsvFileName(), $response->body());
    }

    public function putAndExtractCsvFile($response): bool
    {
        Storage::put(self::TEMP_GZ_FILE, $response->body());

        $gzContent = Storage::get(self::TEMP_GZ_FILE);
        $csvContent = gzdecode($gzContent);

        if ($csvContent !== false) {
            Storage::put(self::TEMP_CSV_FILE, $csvContent);
            Storage::delete(self::TEMP_GZ_FILE);
            return true;
        }

        return false;
    }
}
