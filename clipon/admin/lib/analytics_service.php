<?php

/**
 * @return ProAnalyticsServiceInterface
 */
function clipon_pro_analytics_service() {
    if (!function_exists('registry') || !registry()->has('pro_analytics.service')) {
        return new ProAnalyticsStubService();
    }

    return registry()->get('pro_analytics.service');
}
