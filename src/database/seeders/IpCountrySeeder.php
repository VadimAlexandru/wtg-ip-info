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
            $this->logMessage('info', "Converting CSV to SQL dump...");

            $sqlDumpPath = storage_path('app/ip_country_dump.sql');
            $this->convertCsvToSql($csvFilePath, $sqlDumpPath);

            $this->logMessage('info', "SQL dump created at: $sqlDumpPath");
            $this->logMessage('info', "Starting SQL dump import...");

            $this->importSqlDump($sqlDumpPath);

            $this->logMessage('info', "SQL dump imported successfully.");
        } catch (Throwable $e) {
            $this->logMessage('error', "Failed to process CSV file: {$e->getMessage()}");
        }
    }

    /**
     * @throws Exception
     */
    private function convertCsvToSql(string $csvFilePath, string $sqlDumpPath): void
    {
        $handle = null;
        $sqlDump = null;

        try {
            $handle = fopen($csvFilePath, 'r');
            if (!$handle) {
                throw new Exception("Unable to open CSV file: $csvFilePath");
            }

            $sqlDump = fopen($sqlDumpPath, 'w');
            if (!$sqlDump) {
                throw new Exception("Unable to create SQL dump file: $sqlDumpPath");
            }

            fgetcsv($handle, 1000, ",");

            fwrite($sqlDump, "INSERT INTO `ip_country` (`first_ip`, `last_ip`, `country`, `region`, `subregion`, `city`, `latitude`, `longitude`, `timezone`) VALUES\n");

            $isFirstRow = true;
            while (($data = fgetcsv($handle, 1000, ",")) !== false) {
                [$firstIp, $lastIp, $country, $region, $subregion, $city, , $latitude, $longitude, $timezone] = $data;

                $values = sprintf(
                    "(%s, %s, '%s', '%s', '%s', '%s', %s, %s, '%s')",
                    $this->convertIpToNumeric($firstIp),
                    $this->convertIpToNumeric($lastIp),
                    addslashes($country),
                    addslashes($region),
                    addslashes($subregion),
                    addslashes($city),
                    $latitude,
                    $longitude,
                    addslashes($timezone)
                );

                fwrite($sqlDump, "\n-- Inserting row: $firstIp - $lastIp\n");
                fwrite($sqlDump, ($isFirstRow ? "" : ",\n") . $values);
                $isFirstRow = false;
            }

            fwrite($sqlDump, ";\n");
        } finally {
            if ($handle) {
                fclose($handle);
            }
            if ($sqlDump) {
                fclose($sqlDump);
            }
        }
    }

    private function importSqlDump(string $sqlDumpPath): void
    {
        $database = config('database.connections.mysql.database');
        $host = config('database.connections.mysql.host');
        $username = config('database.connections.mysql.username');
        $password = config('database.connections.mysql.password');

        DB::statement("SET GLOBAL max_allowed_packet = 512 * 1024 * 1024");

        $this->logMessage('info', "Set max_allowed_packet to 512 MB temporally");

        $command = [
            'mysql',
            '--host=' . $host,
            '--user=' . $username,
            '--password=' . $password,
            '--verbose',
            $database,
        ];

        $process = new Process($command);
        $process->setInput(file_get_contents($sqlDumpPath));
        $process->setTimeout(3600);

        $process->run(function ($type, $data) {
            if ($type === Process::OUT) {
                if (stripos($data, 'Query OK') !== false || stripos($data, 'INSERT') !== false) {
                    $this->logMessage('info', "Imported: " . trim($data));
                }
            } else {
                $this->logMessage('error', trim($data));
            }
        });

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
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
