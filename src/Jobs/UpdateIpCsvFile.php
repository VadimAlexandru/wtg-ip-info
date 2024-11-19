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

    private const CSV_URL = 'https://cdn.jsdelivr.net/npm/@ip-location-db/asn-country/asn-country-ipv4.csv';
    private const STORAGE_PATH = 'asn-country-ipv4.csv';

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
