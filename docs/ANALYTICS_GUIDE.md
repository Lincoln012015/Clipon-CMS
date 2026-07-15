# Analytics: Core, Funnels, and Attribution

Clipon CMS analytics consists of core data collection and a PRO tier for advanced reports. Core is always responsible for local collection of page views, events, UTM/referrer data, languages, countries, devices, conversions, and the basic Dashboard. The `pro_analytics` module unlocks real advanced reports, funnels, and attribution instead of a locked/demo UI.

## Availability: Core, Locked Preview, and PRO

| Feature | Without Pro | With Pro (`pro_analytics`) |
|---------|---------|-------------------------|
| View and event collection | Yes (always in core) | Yes |
| Dashboard (hits, uniques, top pages) | Yes | Yes |
| Advanced analytics (reports, charts) | Demo data in UI | Real data |
| Funnels (metrics) | Demo data / locked UI | Real data |
| Attribution (metrics) | Demo data / locked UI | Real data |
| Funnel CRUD (step configuration) | No | Yes |
| Page-based conversions | Yes | Yes |
| Custom conversion types | Yes | Yes |
| Direct JavaScript conversion tracking | No | Yes |
| Filtering of bots, probe requests, and technical traffic | Yes | Yes |

The funnel and attribution toggles in **Settings > Analytics** (`enable_funnels`, `enable_attribution`) are enabled by default as a locked PRO preview. Core daily analytics files are still kept locally, but funnel/attribution logic and real advanced reports are activated only when the `pro_analytics` module is available.

The locked preview is informational only: it does not provide Funnel CRUD or real funnel/attribution results.

---

## Core Analytics: Privacy Modes and Cookie Consent

Two modes are available in **Settings > Analytics**:

- **Privacy/basic without cookies** — the default mode. The CMS does not set a visitor cookie for analytics and does not create a PHP session cookie for anonymous public requests. It counts views, pages, UTM, external referrers, devices, languages, countries, and daily unique visitors via a short-lived daily hash. Raw IP addresses and User-Agent strings are not stored.
- **Full analytics after consent** — the extended mode. Before consent, it works like privacy/basic. After clicking Accept, the CMS sets a first-party visitor cookie and enables sessions, more accurate exit pages, bounce rate, and conversion deduplication. Funnels and attribution additionally depend on the availability of `pro_analytics` and the corresponding toggles.

If the cookie banner is disabled, the CMS always uses privacy/basic mode, even if full analytics is selected in the settings.

### Cookie banner

The banner has a controlled Accept/Reject structure and does not support an HTML override in v1, so as not to break the consent flow. In the admin panel you can change:

- the title, text, and button labels;
- the privacy/cookie policy URL;
- the position, theme, colors, and border radius;
- custom CSS, but only within the `.clipon-cookie-banner` namespace.

The documentation is informational in nature. The site owner is responsible for compliance with local legislation and policy texts.

### Geolocation

The visitor's country is determined by a local GeoIP resolver. **Settings > Analytics** contains a GeoIP panel showing status, the number of ranges, the time of the last/next update, and a manual update button. Data is stored locally in `data/geoip/` or compatible legacy locations. Updating the dataset downloads IP-to-country ranges from `ipdeny.com` over HTTPS and therefore requires outbound network access plus PHP cURL or enabled URL streams. Existing local data remains usable when the source is unavailable.

---

## Bot filtering

Clipon automatically filters out obvious technical traffic before it reaches the analytics reports. This helps avoid mixing real page views with crawlers, security probes, monitoring tools, headless browsers, and requests to service files.

The filter operates in core analytics and does not require the Pro module.

### What is filtered automatically

- requests without a `User-Agent`;
- known bot/crawler/spider user agents, for example Googlebot, Bingbot, Ahrefs, Semrush, Yandex, Baidu, DuckDuckBot;
- headless/automation tools, for example Playwright, Puppeteer, Selenium, Lighthouse;
- HTTP clients and integration tools, for example curl, wget, python-requests, httpx, Postman;
- non-HTML requests, for example with `Accept: image/*`;
- non-GET requests;
- service and probe paths, for example `/robots.txt`, `/sitemap.xml`, `/.env`, `/.git`, `/wp-admin`, `/xmlrpc.php`, `/phpmyadmin`;
- browser user agents without `Accept-Language`, which often indicates an automated browser.

The filter does not store raw IP addresses or user agents.

### Configuring the allowlist

The allowlist is used when a legitimate tool or internal monitoring service is mistakenly filtered out as a bot.

1. Go to **Settings > Analytics**.
2. Open the **Bot Filtering** section.
3. In the **Allowlist patterns** field, add one fragment per line.

Examples:

```text
MyCompanyMonitor
/health-check
InternalPreviewBot
```

The allowlist is matched against the user agent and the request path. If there is a match, a GET HTML request will be counted even when it looks like a bot/probe request.

The allowlist does not turn any request into a page view: POST requests and non-HTML requests are still not counted in pageview analytics.

### Configuring the denylist

The denylist is used when you need to manually exclude traffic that isn't covered by the standard rules.

1. Go to **Settings > Analytics**.
2. Open the **Bot Filtering** section.
3. In the **Denylist patterns** field, add one fragment per line.

Examples:

```text
BadScraper
SyntheticCheck
/load-test
```

The denylist is matched against the user agent and the request path. If there is a match, the request will not be counted in analytics.

### Debug counter

The **Count filtered requests by reason** option adds aggregated filtering statistics to the daily analytics file:

- the total number of filtered requests;
- counts by reason, for example `bot_ua`, `probe_path`, `non_html`, `denylist`, `browser_headers`.

This mode is useful while configuring the allowlist/denylist or diagnosing sudden traffic discrepancies. For privacy, only daily reason counters are stored, with no IP addresses, URL details, or user agents.

To view this data:

1. Go to **Settings > Analytics**.
2. Open the **Bot Filtering** section.
3. Click **View filter log**.

The popup will show the total number of filtered requests, a breakdown by reason, and a table by day. This is a technical log, so it is not given a separate item in the admin sidebar.

Recommendation: keep the debug counter disabled at all times and turn it on temporarily only when you need to check how the filter is performing.

### Practical tips

- Do not add generic words such as `bot`, `monitor`, `chrome`, or `/` to the allowlist, as this can let technical traffic back into the reports.
- For the allowlist, it's better to use a unique user agent for the internal service, for example `CompanyHealthCheck/1.0`.
- For the denylist, it's better to use specific user-agent fragments or service paths that are clearly not real pages for users.
- If your statistics dropped sharply after enabling the filter, temporarily turn on the debug counter and check which reason is filtering out the most requests.

---

## PRO Analytics: Funnels

This section applies only after the `pro_analytics` module is installed and active. Without it, the Funnels screen remains a locked/demo preview and the creation controls described below are unavailable.

A funnel is a sequence of steps (URLs) that a user must go through to reach a goal.

### How to create a funnel:
1. Go to the **Analytics > Funnels** section.
2. Click the **"+ Add funnel"** button.
3. In the modal window:
   - **Name**: any convenient name (for example: "Product purchase").
   - **Steps**: a list of URLs, one per line. For example:
     ```
     /cart
     /checkout
     /thank-you
     ```
   - **Strict order**: if this checkbox is enabled, the system will count a transition only if the user visited `/cart` BEFORE `/checkout`. If the checkbox is not enabled, any paths containing these pages are counted.

### Data analysis:
- **Conversion**: shows the percentage of users who moved from the previous step to the next one.
- **Exit paths**: if a user didn't follow the funnel, you'll see exactly where they went instead of the next step.
- **Average path length**: helps you understand how convoluted the path to the goal is.

---

## PRO Analytics: Attribution

This section applies only after the `pro_analytics` module is installed and active. Core collects UTM/referrer and configured conversion data, but the attribution report and its models are PRO features.

Attribution helps you understand **where** the users who completed a target action (conversion) came from.

### How it works:
The system tracks the `utm_source`, `utm_medium`, and `utm_campaign` parameters, as well as the `Referer` (the site the visit came from).

### Attribution models:
1. **First Touch**: all "credit" for the conversion goes to the very first source through which the user first reached the site. Useful for evaluating reach and engagement.
2. **Last Touch**: all credit goes to the last source before the conversion. Useful for evaluating direct impact on sales.

### Page-based conversion setup:
For data to appear in the attribution report, you need to:
1. Go to **Settings**.
2. In the **Conversions** section, make sure the required conversion types are enabled. You can also create a custom type with a stable key and display label.
3. For pages and posts that should count as conversions, enable the corresponding conversion type in the page/post settings. The CMS stores the conversion URL map in `config/conversions.php`.

### Direct conversion tracking (PRO)

The `pro_analytics` module can count a JavaScript action as a conversion without redirecting the visitor to a dedicated conversion page. Typical examples are a successful AJAX form submission, a completed checkout widget, or a phone-number click.

1. Go to **Settings > Analytics**.
2. Create or enable the required conversion type and note its stable key.
3. Save the Analytics settings.
4. Call the injected public helper with that type key after the action succeeds:

```html
<script>
document.querySelector('#lead-form').addEventListener('submit-success', function () {
    window.cliponAnalytics?.trackConversion('lead');
});
</script>
```

The optional second argument records the page/path associated with the conversion. If omitted, the current pathname is used:

```js
window.cliponAnalytics?.trackConversion('purchase', '/booking/complete');
```

Type keys may contain lowercase Latin letters, digits, underscores, and hyphens, and are limited to 48 characters. The CMS supports up to 40 custom conversion types.

The helper sends `category: "conversion"` to the standard public analytics endpoint with the page's analytics token. The server accepts the conversion only when the PRO Analytics service is available and the supplied conversion type exists and is enabled; arbitrary keys are rejected. Privacy/basic collection deduplicates by page-view identifier, while full analytics applies a five-minute session deduplication window for the same type and path.

Direct conversions contribute to total conversions, conversions by page and type, recent conversions, and PRO reports. Page-based conversions through `config/conversions.php` continue to work independently.

---

## PRO Analytics: Filtering Funnel and Attribution Reports
In both tabs, you can select a time range for analysis. Data is updated in real time based on visit logs.

This period filter applies to the PRO funnel and attribution reports described above.
