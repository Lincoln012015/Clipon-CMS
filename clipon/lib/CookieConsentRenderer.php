<?php

require_once __DIR__ . '/CookieConsentPolicy.php';
require_once __DIR__ . '/RequestSecurity.php';

class CookieConsentRenderer {
    private array $settings;
    private CookieConsentPolicy $policy;

    public function __construct(?array $settings = null, ?CookieConsentPolicy $policy = null) {
        $this->settings = $settings ?? Settings::load();
        $this->policy = $policy ?? new CookieConsentPolicy($this->settings, new Request());
    }

    public function inject(string $html): string {
        if (!$this->policy->shouldRenderBanner()) {
            return $html;
        }

        $banner = $this->render();
        if (strpos($html, '</body>') !== false) {
            return str_replace('</body>', $banner . '</body>', $html);
        }
        return $html . $banner;
    }

    public function render(): string {
        $title = $this->setting('cookie_banner_title', $this->translate('cookie_banner_default_title', 'Cookies'));
        $text = $this->setting('cookie_banner_text', $this->translate('cookie_banner_default_text', 'We use analytics cookies to improve this site.'));
        $accept = $this->setting('cookie_accept_text', $this->translate('cookie_banner_accept', 'Accept'));
        $reject = $this->setting('cookie_reject_text', $this->translate('cookie_banner_reject', 'Reject'));
        $policyUrl = $this->setting('cookie_policy_url', '');
        $position = in_array(($this->settings['cookie_banner_position'] ?? ''), ['bottom_bar', 'bottom_right'], true)
            ? $this->settings['cookie_banner_position']
            : 'bottom_bar';
        $theme = in_array(($this->settings['cookie_banner_theme'] ?? ''), ['light', 'dark', 'auto'], true)
            ? $this->settings['cookie_banner_theme']
            : 'auto';
        $colors = is_array($this->settings['cookie_banner_colors'] ?? null) ? $this->settings['cookie_banner_colors'] : [];
        $radius = $this->sanitizeSize((string)($this->settings['cookie_banner_radius'] ?? '10px'), '10px');
        $customCss = $this->sanitizeCustomCss((string)($this->settings['cookie_banner_custom_css'] ?? ''));

        $styleVars = [
            '--clipon-cookie-bg' => $this->sanitizeColor((string)($colors['background'] ?? '#ffffff'), '#ffffff'),
            '--clipon-cookie-text' => $this->sanitizeColor((string)($colors['text'] ?? '#111827'), '#111827'),
            '--clipon-cookie-muted' => $this->sanitizeColor((string)($colors['muted'] ?? '#4b5563'), '#4b5563'),
            '--clipon-cookie-accent' => $this->sanitizeColor((string)($colors['accent'] ?? '#2563eb'), '#2563eb'),
            '--clipon-cookie-border' => $this->sanitizeColor((string)($colors['border'] ?? '#e5e7eb'), '#e5e7eb'),
            '--clipon-cookie-radius' => $radius,
        ];
        $vars = '';
        foreach ($styleVars as $name => $value) {
            $vars .= $name . ':' . $value . ';';
        }

        $policyLink = '';
        if ($policyUrl !== '') {
            $policyLink = ' <a class="clipon-cookie-banner__link" href="' . htmlspecialchars($policyUrl, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($this->translate('cookie_banner_policy_link', 'Policy'), ENT_QUOTES, 'UTF-8') . '</a>';
        }

        return '<style>' . $this->baseCss() . $customCss . '</style>'
            . '<div class="clipon-cookie-banner clipon-cookie-banner--' . htmlspecialchars($position, ENT_QUOTES, 'UTF-8') . ' clipon-cookie-banner--' . htmlspecialchars($theme, ENT_QUOTES, 'UTF-8') . '" style="' . htmlspecialchars($vars, ENT_QUOTES, 'UTF-8') . '" role="dialog" aria-live="polite" aria-label="' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '">'
            . '<div class="clipon-cookie-banner__content"><strong class="clipon-cookie-banner__title">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</strong><p class="clipon-cookie-banner__text">' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . $policyLink . '</p></div>'
            . '<div class="clipon-cookie-banner__actions"><button type="button" class="clipon-cookie-banner__button clipon-cookie-banner__reject" data-cookie-consent="reject">' . htmlspecialchars($reject, ENT_QUOTES, 'UTF-8') . '</button><button type="button" class="clipon-cookie-banner__button clipon-cookie-banner__accept" data-cookie-consent="accept">' . htmlspecialchars($accept, ENT_QUOTES, 'UTF-8') . '</button></div>'
            . '</div>'
            . '<script>' . $this->managerJs() . '</script>';
    }

    private function setting(string $key, string $fallback): string {
        $value = trim((string)($this->settings[$key] ?? ''));
        return $value !== '' ? $value : $fallback;
    }

    private function translate(string $key, string $fallback): string {
        if (!function_exists('__')) {
            return $fallback;
        }

        $translated = (string)__($key);
        return $translated !== '' && $translated !== $key ? $translated : $fallback;
    }

    private function sanitizeColor(string $value, string $fallback): string {
        $value = trim($value);
        if (preg_match('/^#[0-9a-fA-F]{3,8}$/', $value) || preg_match('/^rgba?\([0-9.,% ]+\)$/', $value)) {
            return $value;
        }
        return $fallback;
    }

    private function sanitizeSize(string $value, string $fallback): string {
        $value = trim($value);
        return preg_match('/^[0-9]{1,3}(px|rem|em)$/', $value) ? $value : $fallback;
    }

    private function sanitizeCustomCss(string $css): string {
        $css = trim($css);
        if ($css === '') return '';
        if (stripos($css, '<') !== false || stripos($css, 'javascript:') !== false || stripos($css, '@import') !== false) {
            return '';
        }
        if (strpos($css, '.clipon-cookie-banner') === false) {
            return '';
        }
        return "\n" . $css . "\n";
    }

    private function baseCss(): string {
        return '.clipon-cookie-banner{position:fixed;z-index:2147483000;box-sizing:border-box;background:var(--clipon-cookie-bg);color:var(--clipon-cookie-text);border:1px solid var(--clipon-cookie-border);border-radius:var(--clipon-cookie-radius);box-shadow:0 18px 60px rgba(15,23,42,.18);font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;display:flex;gap:16px;align-items:center;padding:16px;max-width:min(680px,calc(100vw - 32px));}.clipon-cookie-banner--bottom_bar{left:16px;right:16px;bottom:16px;max-width:none;justify-content:space-between}.clipon-cookie-banner--bottom_right{right:16px;bottom:16px;flex-direction:column;align-items:stretch;width:min(420px,calc(100vw - 32px))}.clipon-cookie-banner__title{display:block;margin:0 0 4px;font-size:15px}.clipon-cookie-banner__text{margin:0;color:var(--clipon-cookie-muted);font-size:14px;line-height:1.45}.clipon-cookie-banner__link{color:var(--clipon-cookie-accent);text-decoration:underline}.clipon-cookie-banner__actions{display:flex;gap:8px;flex-shrink:0}.clipon-cookie-banner__button{border:1px solid var(--clipon-cookie-border);border-radius:calc(var(--clipon-cookie-radius) * .75);padding:9px 14px;cursor:pointer;font-weight:700;background:transparent;color:var(--clipon-cookie-text)}.clipon-cookie-banner__accept{background:var(--clipon-cookie-accent);border-color:var(--clipon-cookie-accent);color:#fff}@media(max-width:640px){.clipon-cookie-banner,.clipon-cookie-banner--bottom_bar{left:12px;right:12px;bottom:12px;flex-direction:column;align-items:stretch}.clipon-cookie-banner__actions{justify-content:stretch}.clipon-cookie-banner__button{flex:1}}';
    }

    private function managerJs(): string {
        $request = new Request();
        $secureAttribute = RequestSecurity::isSecure($request) ? '; Secure' : '';

        return '(function(){var secureAttr=' . json_encode($secureAttribute) . ';function setConsent(v){var maxAge=180*24*60*60;document.cookie=' . json_encode(CookieConsentPolicy::CONSENT_COOKIE) . '+ "=" + v + "; Max-Age="+maxAge+"; Path=/; SameSite=Lax"+secureAttr;var el=document.querySelector(".clipon-cookie-banner");if(el)el.remove();if(v==="accepted"){window.location.reload();}}document.addEventListener("click",function(e){var btn=e.target.closest("[data-cookie-consent]");if(!btn)return;var v=btn.getAttribute("data-cookie-consent")==="accept"?"accepted":"rejected";setConsent(v);});window.CliponCookieConsent={reset:function(){document.cookie=' . json_encode(CookieConsentPolicy::CONSENT_COOKIE . '=; Max-Age=0; Path=/; SameSite=Lax') . '+secureAttr;window.location.reload();}};})();';
    }
}
