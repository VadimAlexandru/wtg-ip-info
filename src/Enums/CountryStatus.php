<?php

namespace IpCountryDetector\Enums;

enum CountryStatus: int
{
    case UNKNOWN = 0;
    case NOT_FOUND = 404;
    case IP_NOT_IN_RANGE = 204;
    case SUCCESS = 200;
}