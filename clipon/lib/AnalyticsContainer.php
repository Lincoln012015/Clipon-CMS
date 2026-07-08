<?php

require_once __DIR__ . '/AnalyticsStorage.php';
require_once __DIR__ . '/AnalyticsReport.php';
require_once __DIR__ . '/AnalyticsEvent.php';
require_once __DIR__ . '/AnalyticsTracker.php';
require_once __DIR__ . '/AnalyticsView.php';

class AnalyticsContainer {
    private AnalyticsStorage $storage;
    private AnalyticsReport $report;
    private AnalyticsEvent $events;
    private AnalyticsTracker $tracker;
    private AnalyticsView $view;

    public function __construct(string $dataDir) {
        $this->storage = new AnalyticsStorage($dataDir);
        $this->report = new AnalyticsReport($this->storage);
        $this->events = new AnalyticsEvent($this->storage);
        $this->tracker = new AnalyticsTracker($this->storage, $dataDir);
        $this->view = new AnalyticsView($this->report);
    }

    public function storage(): AnalyticsStorage {
        return $this->storage;
    }

    public function report(): AnalyticsReport {
        return $this->report;
    }

    public function events(): AnalyticsEvent {
        return $this->events;
    }

    public function tracker(): AnalyticsTracker {
        return $this->tracker;
    }

    public function view(): AnalyticsView {
        return $this->view;
    }
}