<?php

namespace IpCountryDetector\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use IpCountryDetector\Services\CsvFilePathService;
use Throwable;

class InstallIpCountryDetectorCommand extends Command
{
    protected $signature = 'ip-country-detector:install';

    protected $description = 'Install the IP Country Detector package, update IP database, and seed the data.';
    protected CsvFilePathService $csvFilePathService;

    private const CSV_URL = 'https://cdn.jsdelivr.net/npm/@ip-location-db/asn-country/asn-country-ipv4.csv';

    public function __construct(CsvFilePathService $csvFilePathService)
    {
        parent::__construct();
        $this->csvFilePathService = $csvFilePathService;
    }

    public function handle(): int
    {
        $this->info('Starting installation of IP Country Detector package...');

        sleep(2);


        try {
            $storageFilePath = $this->csvFilePathService->getCsvFilePath();

            if (!file_exists($storageFilePath)) {
                $this->info('CSV file not found. Downloading...');

                sleep(2);

                $response = Http::get(self::CSV_URL);

                if ($response->ok()) {
                    $this->csvFilePathService->putCsvFile($response);
                    $this->info('CSV file downloaded successfully.');
                } else {
                    $this->error('Failed to download CSV file.');
                    return 1;
                }
            } else {
                $this->info('CSV file already exists. Skipping download.');
            }

            $this->info('Preparing file for migration...');

            sleep(2);

            $this->call('migrate');

            $this->info('Preparing file for data import...');
            sleep(2);
            Artisan::call('db:seed', [
                '--class' => 'database\seeders\IpCountrySeeder',
            ]);

            $this->info('Database seeded successfully.');

        } catch (Throwable $e) {
            $this->error('An error occurred during installation: ' . $e->getMessage());
            Log::error('InstallIpCountryDetectorCommand error: ' . $e->getMessage());
            return 1;
        }

        $this->info('IP Country Detector package installed successfully.');
        return 0;
    }
}
