<?php

namespace IpCountryDetector\Services;

use Illuminate\Support\Facades\Storage;
class CsvFilePathService
{
    private const TEMP_CSV_FILE = 'asn-country-ipv4.csv';

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
}
