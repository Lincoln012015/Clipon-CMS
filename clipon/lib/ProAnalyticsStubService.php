<?php

require_once __DIR__ . '/AnalyticsStorage.php';
require_once __DIR__ . '/AnalyticsReport.php';
require_once __DIR__ . '/AnalyticsView.php';
require_once __DIR__ . '/ProAnalyticsServiceInterface.php';
require_once __DIR__ . '/ProAnalyticsMessages.php';

class ProAnalyticsStubService implements ProAnalyticsServiceInterface {
    private AnalyticsView $view;

    public function __construct(?string $dataDir = null) {
        $dir = $dataDir ?? (defined('C_DATA_PATH') ? C_DATA_PATH . '/analytics' : '');
        $storage = new AnalyticsStorage($dir);
        $this->view = new AnalyticsView(new AnalyticsReport($storage));
    }

    public function isLicensed(): bool {
        return $this->moduleState() === 'licensed';
    }

    public function isMissingFiles(): bool {
        return $this->moduleState() === 'missing_files';
    }

    public function isAvailable(): bool {
        return $this->isLicensed() && !$this->isMissingFiles();
    }

    public function getMode(): string {
        if ($this->isMissingFiles()) {
            return 'install_required';
        }

        if ($this->isLicensed()) {
            return 'licensed_unavailable';
        }

        return 'mock';
    }

    public function getStatusMessage(): string {
        return ProAnalyticsMessages::statusMessageForFlags(
            $this->isMissingFiles(),
            $this->isLicensed(),
            $this->isAvailable()
        );
    }

    public function getAnalyticsViewData(string $from, string $to): array {
        if ($this->isMissingFiles()) {
            return ProAnalyticsMessages::missingModulePayload();
        }

        $data = $this->view->getMockData($from, $to);
        $data['mode'] = $this->getMode();

        $message = $this->getStatusMessage();
        if ($message !== '') {
            $data['stub_message'] = $message;
        }

        return $data;
    }

    public function getFunnelsViewData(string $from, string $to, string $funnelId = '', array $funnelsList = []): array {
        if ($this->isMissingFiles()) {
            return ProAnalyticsMessages::missingModulePayload();
        }

        return $this->getAnalyticsViewData($from, $to);
    }

    public function getAttributionViewData(string $from, string $to): array {
        if ($this->isMissingFiles()) {
            return ProAnalyticsMessages::missingModulePayload();
        }

        return $this->getAnalyticsViewData($from, $to);
    }

    private function moduleState(): string {
        if (!class_exists('ModuleManager')) {
            return 'unlicensed';
        }

        return ModuleManager::getProModuleState('pro_analytics');
    }
}
