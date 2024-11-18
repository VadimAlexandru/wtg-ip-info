<?php

namespace seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
                    [$firstIp, $lastIp, $country] = $data;

                    $batch[] = [
                        'first_ip' => ip2long($firstIp),
                        'last_ip' => ip2long($lastIp),
                        'country' => $country,
                    ];

                    if (count($batch) >= $batchSize) {
                        IpCountry::insertOrIgnore($batch);
                        $batch = [];
                    }

                    $percentage = number_format(($rowCount / $totalRows) * 100, 1);

                    $this->logMessage('info', sprintf(
                        "[%6.1f%% | %6d / 100%% | %6d] - [%2s] - [%15s - %-15s]",
                        $percentage,
                        $rowCount,
                        $totalRows,
                        $country,
                        str_pad($firstIp, 15, " ", STR_PAD_RIGHT),
                        str_pad($lastIp, 15, " ", STR_PAD_RIGHT)
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

    private function logMessage(string $level, string $message): void
    {
        Log::{$level}($message);

        $output = new ConsoleOutput();
        $output->writeln("<info>{$message}</info>");
    }
}
