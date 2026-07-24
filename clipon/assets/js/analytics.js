(function () {
    "use strict";
    const node = document.getElementById("clipon-analytics-config");
    if (!node) return;
    let config;
    try { config = JSON.parse(node.textContent || "{}"); } catch (_) { return; }
    let sequence = 0, sentPageView = false, visibleSeconds = 0, lastTick = 0, lastHeartbeat = 0, scrollMax = 0;
    const envelope = (type, data) => ({
        version: 1, type: type, pageview_id: config.pageviewId, path: config.path,
        token: config.token, sequence: ++sequence, data: data || {}
    });
    function send(type, data, urgent) {
        const body = JSON.stringify(envelope(type, data));
        if (urgent && navigator.sendBeacon) {
            if (navigator.sendBeacon(config.endpoint, new Blob([body], {type: "application/json"}))) return;
        }
        fetch(config.endpoint, {method: "POST", credentials: "same-origin", keepalive: !!urgent,
            headers: {"Content-Type": "application/json"}, body: body}).catch(function () {});
    }
    function pageView() {
        if (sentPageView || document.visibilityState !== "visible") return;
        sentPageView = true; lastTick = performance.now(); send("page_view", {}, false);
    }
    function tick() {
        const now = performance.now();
        if (document.visibilityState === "visible" && lastTick) visibleSeconds += Math.min(1, Math.max(0, (now - lastTick) / 1000));
        lastTick = document.visibilityState === "visible" ? now : 0;
        const whole = Math.floor(visibleSeconds);
        if (sentPageView && ((whole >= 5 && lastHeartbeat < 5) || whole - lastHeartbeat >= 25)) {
            lastHeartbeat = whole; send("engagement", {visible_seconds: whole}, false);
        }
    }
    function flush() {
        tick();
        if (sentPageView && Math.floor(visibleSeconds) > lastHeartbeat) {
            lastHeartbeat = Math.floor(visibleSeconds); send("engagement", {visible_seconds: lastHeartbeat}, true);
        }
    }
    function refreshForBfcache(event) {
        if (!event.persisted) return;
        fetch(config.tokenEndpoint + "?path=" + encodeURIComponent(location.pathname), {credentials: "same-origin", headers: {"Accept": "text/html"}})
            .then(r => r.ok ? r.json() : Promise.reject()).then(next => {
                config.pageviewId = next.pageview_id; config.token = next.token; config.path = next.path;
                sequence = 0; sentPageView = false; visibleSeconds = 0; lastHeartbeat = 0; scrollMax = 0; pageView();
            }).catch(function () {});
    }
    window.cliponAnalytics = window.cliponAnalytics || {};
    window.cliponAnalytics.trackConversion = function (key) {
        if (sentPageView && typeof key === "string" && /^[a-z0-9_-]{1,48}$/.test(key)) send("conversion", {key: key}, false);
    };
    document.addEventListener("visibilitychange", function () {
        if (document.visibilityState === "visible") pageView(); else flush();
    });
    window.addEventListener("pagehide", flush);
    window.addEventListener("pageshow", refreshForBfcache);
    window.addEventListener("scroll", function () {
        if (!sentPageView) return;
        const root = document.documentElement, max = root.scrollHeight - root.clientHeight;
        if (max <= 0) return;
        const percent = Math.round((root.scrollTop / max) * 100);
        [25, 50, 75, 100].forEach(function (threshold) {
            if (percent >= threshold && threshold > scrollMax) { scrollMax = threshold; send("scroll", {max_percent: scrollMax}, false); }
        });
    }, {passive: true});
    setInterval(tick, 1000);
    if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", pageView); else pageView();
})();
