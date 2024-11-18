<?php

namespace IpCountryDetector\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class UpdateIpCsvFile implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const CSV_URL = 'https://github.com/sapics/ip-location-db/raw/refs/heads/main/geolite2-city/geolite2-city-ipv4-num.csv.gz';
    private const STORAGE_PATH = 'geolite2-city-ipv4-num.csv';

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        $response = Http::get(self::CSV_URL);

        if ($response->ok()) {
            Storage::put(self::STORAGE_PATH, $response->body());
        }
    }
}
