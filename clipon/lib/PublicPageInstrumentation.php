<?php

require_once __DIR__ . '/Settings.php';
require_once __DIR__ . '/PoweredBy.php';
require_once __DIR__ . '/CookieConsentPolicy.php';
require_once __DIR__ . '/CookieConsentRenderer.php';

class PublicPageInstrumentation {
    public static function inject(string $html): string {
        $html = self::injectAnalyticsTask($html);
        $html = (new CookieConsentRenderer(Settings::load(), new CookieConsentPolicy(Settings::load(), new Request())))->inject($html);
        return PoweredBy::injectIntoHtml($html);
    }

    private static function injectAnalyticsTask(string $html): string {
        $policy = new CookieConsentPolicy(Settings::load(), new Request());
        $mode = $policy->analyticsModeForRequest();
        $endpointUrl = self::analyticsEndpointUrl();
        if ($mode === CookieConsentPolicy::MODE_BASIC) {
            $pageviewId = CookieConsentPolicy::newPageviewId();
            $signature = CookieConsentPolicy::signPageviewId($pageviewId);
            $analyticsJs = '
        <script>
        (function() {
            const pageviewId = ' . json_encode($pageviewId, JSON_UNESCAPED_UNICODE) . ';
            const signature = ' . json_encode($signature, JSON_UNESCAPED_UNICODE) . ';
            let triggered = {"25%": false, "50%": false, "75%": false, "100%": false};
            function send(payload, keepalive) {
                payload.pageview_id = pageviewId;
                payload.signature = signature;
                fetch(' . json_encode($endpointUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ', {
                    method: "POST",
                    keepalive: !!keepalive,
                    body: JSON.stringify(payload),
                    headers: { "Content-Type": "application/json" }
                }).catch(function(){});
            }
            window.addEventListener("scroll", function() {
                let h = document.documentElement, b = document.body, st = "scrollTop", sh = "scrollHeight";
                let denom = ((h[sh]||b[sh]) - h.clientHeight);
                if (denom <= 0) return;
                let percent = (h[st]||b[st]) / denom * 100;
                for (let threshold in triggered) {
                    if (percent >= parseInt(threshold) && !triggered[threshold]) {
                        triggered[threshold] = true;
                        send({ category: "scroll", action: threshold, label: window.location.pathname }, false);
                    }
                }
            });
            const sendPulse = () => send({ category: "system", action: "timer_pulse", label: window.location.pathname }, true);
            setInterval(() => { if (document.visibilityState === "visible") sendPulse(); }, 30000);
            window.addEventListener("visibilitychange", function() { if (document.visibilityState === "hidden") sendPulse(); });
        })();
        </script>';

            return self::appendBeforeBodyClose($html, $analyticsJs);
        }

        self::ensureSessionStarted();
        $session = new Session();
        $analyticsToken = $session->get('analytics_event_token', '');
        if (!is_string($analyticsToken) || $analyticsToken === '') {
            try {
                $analyticsToken = bin2hex(random_bytes(16));
            } catch (Exception $e) {
                $analyticsToken = md5(uniqid('analytics', true));
            }
            $session->set('analytics_event_token', $analyticsToken);
        }

        $analyticsJs = '
        <script>
        (function() {
            const analyticsToken = ' . json_encode($analyticsToken, JSON_UNESCAPED_UNICODE) . ';
            let triggered = {"25%": false, "50%": false, "75%": false, "100%": false};
            window.addEventListener("scroll", function() {
                let h = document.documentElement, b = document.body, st = "scrollTop", sh = "scrollHeight";
                let denom = ((h[sh]||b[sh]) - h.clientHeight);
                if (denom <= 0) return;
                let percent = (h[st]||b[st]) / denom * 100;
                for (let threshold in triggered) {
                    if (percent >= parseInt(threshold) && !triggered[threshold]) {
                        triggered[threshold] = true;
                        fetch(' . json_encode($endpointUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ', {
                            method: "POST",
                            body: JSON.stringify({ category: "scroll", action: threshold, label: window.location.pathname }),
                            headers: { "Content-Type": "application/json", "X-Analytics-Token": analyticsToken }
                        });
                    }
                }
            });

            const sendPulse = () => {
                fetch(' . json_encode($endpointUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ', {
                    method: "POST",
                    keepalive: true,
                    body: JSON.stringify({ category: "system", action: "timer_pulse" }),
                    headers: { "Content-Type": "application/json", "X-Analytics-Token": analyticsToken }
                });
            };

            setInterval(() => { if (document.visibilityState === "visible") sendPulse(); }, 30000);
            window.addEventListener("visibilitychange", function() { if (document.visibilityState === "hidden") sendPulse(); });
        })();
        </script>';

        return self::appendBeforeBodyClose($html, $analyticsJs);
    }

    private static function appendBeforeBodyClose(string $html, string $snippet): string {
        if (strpos($html, '</body>') !== false) {
            return str_replace('</body>', $snippet . '</body>', $html);
        }
        return $html . $snippet;
    }

    private static function ensureSessionStarted(): void {
        if (session_status() === PHP_SESSION_ACTIVE || !class_exists('SessionManager')) {
            return;
        }

        SessionManager::start();
        SessionManager::enforceActivity();
    }

    private static function analyticsEndpointUrl(): string {
        $base = defined('C_BASE_URL') ? (string)C_BASE_URL : (defined('CMS_BASE_PATH') ? (string)CMS_BASE_PATH : '');
        $base = rtrim($base, '/');
        if ($base === '/' || $base === '\\') {
            $base = '';
        }

        return $base . '/clipon/admin/api/track_event.php';
    }
}
