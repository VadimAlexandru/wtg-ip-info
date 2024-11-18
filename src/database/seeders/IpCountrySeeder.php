<?php

namespace IpCountryDetector\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use IpCountryDetector\Models\IpCountry;
use IpCountryDetector\Services\CsvFilePathService;
use Symfony\Component\Console\Output\ConsoleOutput;
use Throwable;

class IpCountrySeeder extends Seeder
{
    protected CsvFilePathService $csvFilePathService;

    /**
     * Run the database seeds.
     *
     * @return void
     * @throws Throwable
     */

    public function __construct(CsvFilePathService $csvFilePathService)
    {
        $this->csvFilePathService = $csvFilePathService;
    }
    public function run(): void
    {
        IpCountry::truncate();
        $this->logMessage('info', "Table 'ip_country' has been cleared.");

        Artisan::call('migrate');
        $this->logMessage('info', "Database migrations have been run.");

        $csvFilePath = $this->csvFilePathService->getCsvFilePath();
        $this->logMessage('info', "CSV file path: $csvFilePath");
        sleep(5);

        if (!$handle = fopen($csvFilePath, 'r')) {
            $this->logMessage('error', "Unable to open CSV file: $csvFilePath");
            return;
        }

        try {
            DB::transaction(function () use ($handle) {
                $dataRows = [];
                $rowCount = 1;
                $batchSize = 1000;
                $batch = [];

                while (($data = fgetcsv($handle, 1000, ",")) !== false) {
                    $dataRows[] = $data;
                }

                usort($dataRows, function ($a, $b) {
                    return strcmp($a[2], $b[2]);
                });

                $totalRows = count($dataRows);

                foreach ($dataRows as $data) {
                    [$firstIp, $lastIp, $country, $region, $subregion, $city, , $latitude, $longitude, $timezone] = $data;

                    $batch[] = [
                        'first_ip' => $this->convertIpToNumeric($firstIp),
                        'last_ip' => $this->convertIpToNumeric($lastIp),
                        'country' => $country,
                        'region' => $region,
                        'subregion' => $subregion,
                        'latitude' => $latitude,
                        'longitude' => $longitude,
                        'city' => $city,
                        'timezone' => $timezone,
                    ];

                    if (count($batch) >= $batchSize) {
                        IpCountry::insertOrIgnore($batch);
                        $batch = [];
                    }

                    $percentage = number_format(($rowCount / $totalRows) * 100, 1);

                    $this->logMessage('info', sprintf(
                        "[%6.1f%% | %6d / 100%% | %6d] - Country: [%2s] - IP Range: [%15s - %-15s] - Region: [%s] - Subregion: [%s] - City: [%s] Map: [%s : %s] - Timezone: [%s]",
                        $percentage,
                        $rowCount,
                        $totalRows,
                        $country,
                        str_pad($firstIp, 15, " ", STR_PAD_RIGHT),
                        str_pad($lastIp, 15, " ", STR_PAD_RIGHT),
                        $region,
                        $subregion,
                        $city,
                        $latitude,
                        $longitude,
                        $timezone
                    ));

                    $rowCount++;
                }

                if (!empty($batch)) {
                    IpCountry::insertOrIgnore($batch);
                }

                fclose($handle);
                $this->logMessage('info', "CSV processing completed and file closed.");
            });
        } catch (Throwable $e) {
            $this->logMessage('error', "Failed to process CSV file: {$e->getMessage()}");
        }

    }

    function convertIpToNumeric($ip): float|int|string
    {
        if (is_numeric($ip)) {
            return $ip;
        }

        $numericIp = ip2long($ip);

        if ($numericIp === false) {
            throw new InvalidArgumentException("Wrong format: $ip");
        }

        return $numericIp;
    }


    private function logMessage(string $level, string $message): void
    {
        Log::{$level}($message);

        $output = new ConsoleOutput();
        $output->writeln("<info>{$message}</info>");
    }
}
