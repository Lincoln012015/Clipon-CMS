<?php

require_once __DIR__ . '/AnalyticsGeoIpUpdater.php';

class AnalyticsGeoResolver {
    public function resolveCountry(Request $request, string $ip): string {
        foreach ($this->countryHeaderNames() as $header) {
            $country = $this->normalizeCountryCode($request->server($header));
            if ($country !== null) {
                return $country;
            }
        }

        foreach ($this->geoIpCsvSources() as $csv) {
            $country = $this->lookupCountryFromCsv($csv, $ip);
            if ($country !== null) {
                return $country;
            }
        }

        $this->maybeScheduleGeoIpUpdate();
        return 'unknown';
    }

    private function lookupCountryFromCsv(string $file, string $ip): ?string {
        $ipBinary = $this->ipToBinary($ip);
        if ($ipBinary === null || !$this->isPublicIp($ip)) {
            return null;
        }

        $handle = fopen($file, 'r');
        if (!$handle) {
            return null;
        }

        try {
            while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
                if (count($row) < 2) {
                    continue;
                }

                $range = $this->csvRange($row);
                $country = $this->csvCountry($row);
                if ($range === null || $country === null) {
                    continue;
                }

                if (strlen($ipBinary) === strlen($range[0]) && strcmp($ipBinary, $range[0]) >= 0 && strcmp($ipBinary, $range[1]) <= 0) {
                    return $country;
                }
            }
        } finally {
            fclose($handle);
        }

        return null;
    }

    private function countryHeaderNames(): array {
        return [
            'HTTP_CF_IPCOUNTRY',
            'HTTP_CLOUDFRONT_VIEWER_COUNTRY',
            'HTTP_X_VERCEL_IP_COUNTRY',
            'HTTP_X_COUNTRY_CODE',
            'HTTP_FASTLY_CLIENT_COUNTRY_CODE',
            'GEOIP_COUNTRY_CODE',
        ];
    }

    private function geoIpCsvSources(): array {
        $sources = [];
        $env = getenv('CLIPON_ANALYTICS_GEOIP_CSV') ?: '';
        if ($env !== '') {
            foreach (explode(PATH_SEPARATOR, $env) as $path) {
                $sources[] = trim($path);
            }
        }

        if (defined('C_DATA_PATH')) {
            $sources[] = C_DATA_PATH . '/geoip.csv';
            $sources[] = C_DATA_PATH . '/geoip/geoip.csv';
            $sources[] = C_DATA_PATH . '/geoip/GeoIPCountryWhois.csv';
        }

        if (defined('C_CONFIG_PATH')) {
            $sources[] = C_CONFIG_PATH . '/geoip.csv';
        }

        $readable = [];
        foreach ($sources as $source) {
            if ($source !== '' && is_readable($source) && !isset($readable[$source])) {
                $readable[$source] = $source;
            }
        }

        return array_values($readable);
    }

    private function maybeScheduleGeoIpUpdate(): void {
        static $scheduled = false;
        if ($scheduled || PHP_SAPI === 'cli') {
            return;
        }

        $scheduled = true;
        $updater = new AnalyticsGeoIpUpdater();
        $updater->scheduleAutoUpdate();
    }

    private function csvRange(array $row): ?array {
        $first = trim((string)($row[0] ?? ''));
        if (strpos($first, '/') !== false) {
            return $this->cidrRange($first);
        }

        $start = $this->ipBoundary($row[0] ?? null);
        $end = $this->ipBoundary($row[1] ?? null);
        if ($start === null || $end === null) {
            return null;
        }

        return [$start, $end];
    }

    private function csvCountry(array $row): ?string {
        $candidates = [];
        if (count($row) >= 3) {
            $candidates[] = $row[2];
        }
        $candidates[] = $row[count($row) - 1] ?? '';

        foreach ($candidates as $candidate) {
            $country = $this->normalizeCountryCode($candidate);
            if ($country !== null) {
                return $country;
            }
        }

        return null;
    }

    private function cidrRange(string $cidr): ?array {
        [$ip, $prefix] = array_pad(explode('/', $cidr, 2), 2, null);
        if (!is_string($prefix) || !ctype_digit($prefix)) {
            return null;
        }

        $bits = (int)$prefix;
        $binary = $this->ipToBinary((string)$ip);
        if ($binary === null) {
            return null;
        }

        $maxBits = strlen($binary) * 8;
        if ($bits < 0 || $bits > $maxBits) {
            return null;
        }

        return $this->binaryCidrRange($binary, $bits);
    }

    private function ipBoundary($value): ?string {
        $value = trim((string)$value);
        if (filter_var($value, FILTER_VALIDATE_IP)) {
            return $this->ipToBinary($value);
        }

        if (is_numeric($value)) {
            $int = (int)$value;
            if ($int < 0 || $int > 0xFFFFFFFF) {
                return null;
            }
            return pack('N', $int);
        }

        return null;
    }

    private function ipToBinary(string $ip): ?string {
        $binary = @inet_pton($ip);
        return $binary === false ? null : $binary;
    }

    private function binaryCidrRange(string $binary, int $bits): array {
        $start = '';
        $end = '';
        $remaining = $bits;
        $length = strlen($binary);

        for ($i = 0; $i < $length; $i++) {
            $byte = ord($binary[$i]);
            if ($remaining >= 8) {
                $startByte = $byte;
                $endByte = $byte;
                $remaining -= 8;
            } elseif ($remaining > 0) {
                $mask = (0xFF << (8 - $remaining)) & 0xFF;
                $startByte = $byte & $mask;
                $endByte = $startByte | (~$mask & 0xFF);
                $remaining = 0;
            } else {
                $startByte = 0;
                $endByte = 0xFF;
            }

            $start .= chr($startByte);
            $end .= chr($endByte);
        }

        return [$start, $end];
    }

    private function normalizeCountryCode($value): ?string {
        if (!is_string($value)) {
            return null;
        }

        $country = strtoupper(trim($value));
        if ($country === 'XX' || $country === 'T1') {
            return null;
        }

        return preg_match('/^[A-Z]{2}$/', $country) ? $country : null;
    }

    private function isPublicIp(string $ip): bool {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }
}
