<?php

namespace IpCountryDetector\Services\Interfaces;

interface IpCountryServiceInterface
{
    public function getCountry(string $ipAddress): string;
}