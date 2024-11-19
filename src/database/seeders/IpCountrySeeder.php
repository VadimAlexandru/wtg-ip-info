<?php

namespace IpCountryDetector\Database\Seeders;

use Exception;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use IpCountryDetector\Models\IpCountry;
use IpCountryDetector\Services\CsvFilePathService;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
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
        $csvFilePath = $this->csvFilePathService->getCsvFilePath();
        $this->logMessage('info', "CSV file path: $csvFilePath");

        if (!file_exists($csvFilePath)) {
            $this->logMessage('error', "CSV file not found: $csvFilePath");
            return;
        }
        $updateMode = Artisan::output()->contains('--update');
        if ($updateMode) {
            $this->logMessage('info', "Update mode enabled: existing data will be updated or new data added.");
        } else {
            IpCountry::truncate();
            $this->logMessage('info', "Table 'ip_country' has been cleared.");
        }

        Artisan::call('migrate');
        $this->logMessage('info', "Database migrations have been run.");

        try {
            $dataRows = [];
            $rowCount = 0;

            if (($handle = fopen($csvFilePath, 'r')) === false) {
                throw new Exception("Unable to open CSV file: $csvFilePath");
            }

            fgetcsv($handle, 1000, ",");

            $this->logMessage('info', "Reading and processing CSV file...");
            while (($data = fgetcsv($handle, 1000, ",")) !== false) {
                $dataRows[] = $data;
            }

            fclose($handle);

            usort($dataRows, fn($a, $b) => strcmp($a[2], $b[2]));

            $totalRows = count($dataRows);

            foreach ($dataRows as $data) {
                [$firstIp, $lastIp, $country, $region, $subregion, $city, , $latitude, $longitude, $timezone] = $data;

                $record = [
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

                if ($updateMode) {
                    IpCountry::updateOrCreate(
                        ['first_ip' => $record['first_ip'], 'last_ip' => $record['last_ip']],
                        $record
                    );
                } else {
                    IpCountry::insertOrIgnore($record);
                }

                $rowCount++;
                $percentage = ($rowCount / $totalRows) * 100;

                $this->logMessage('info', sprintf(
                    "[%6.2f%% | %6d / %6d] - Country: [%2s] - IP Range: [%15s - %15s] - Region: [%s] - Subregion: [%s] - City: [%s]",
                    $percentage,
                    $rowCount,
                    $totalRows,
                    $country,
                    str_pad($firstIp, 15, " ", STR_PAD_RIGHT),
                    str_pad($lastIp, 15, " ", STR_PAD_RIGHT),
                    $region,
                    $subregion,
                    $city
                ));
            }

            $this->logMessage('info', "CSV file processed successfully. Total rows: $totalRows");
        } catch (Throwable $e) {
            $this->logMessage('error', "Failed to process CSV file: {$e->getMessage()}");
        }
    }


    /**
     * @throws Exception
     */

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
