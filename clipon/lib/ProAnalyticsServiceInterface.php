<?php

interface ProAnalyticsServiceInterface {
    public function isLicensed(): bool;

    public function isMissingFiles(): bool;

    public function isAvailable(): bool;

    public function getMode(): string;

    public function getStatusMessage(): string;

    public function getAnalyticsViewData(string $from, string $to): array;

    /**
     * @param array<int,array<string,mixed>> $funnelsList
     */
    public function getFunnelsViewData(string $from, string $to, string $funnelId = '', array $funnelsList = []): array;

    public function getAttributionViewData(string $from, string $to): array;
}
