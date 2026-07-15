# Installation

This guide covers installing the PHP CMS release artifact on a hosting environment.

## Requirements

- PHP 8.0 or newer.
- PHP web server support through Apache, Nginx, or equivalent.
- PHP extensions commonly available on shared hosting: `json`, `mbstring`, `dom`, `libxml`, `fileinfo`, `session`, `openssl`.
- Outbound HTTPS plus either PHP cURL or `allow_url_fopen` is required only for license/update checks and downloading the optional GeoIP dataset.
- Write access for the PHP process to runtime directories.

Node.js and Composer are not required for the CMS runtime.

## Runtime Layout

The release ZIP contains `index.php`, `.htaccess`, `clipon/`, and documentation. Runtime directories are site-owned state and therefore are not stored in Git or included as pre-populated directories in the ZIP. After installation, the CMS expects this structure:

```text
/
├── index.php
├── .htaccess
├── clipon/
├── content/
├── config/
├── data/
├── logs/
├── modules/
├── templates/
└── assets/uploads/
```

## Install From Release ZIP

Download the published release ZIP, then upload and extract it into the target site root. The repository already uses the deployed release layout, so no local build step is required.

Before opening setup, choose one of these permission workflows:

1. Recommended: create the runtime directories yourself and make only those directories writable by the PHP process:

```text
content/
config/
data/
logs/
modules/
templates/
assets/uploads/
```

2. Alternatively, temporarily allow the PHP process to create directories in the site root. On the setup environment-check step, Clipon attempts to create the directories above with mode `0755`. After they have been created, remove write access to the site root while keeping the runtime directories writable.

If neither workflow is configured, setup will report the runtime-directory check as failed. Do not look for empty runtime directories in the ZIP.

Keep `clipon/` read-only during normal runtime. It should only be writable during a controlled update process.

## First Admin Setup

Open:

```text
/clipon/setup.php
```

The setup flow verifies or creates runtime directories according to the permission workflow above, then prepares site settings, language settings, and the first admin account. First-admin registration is available only before an admin account exists.

## Attach To An Existing Site

1. Add the CMS release files to the existing site root.
2. Mark editable HTML elements with `class="clipon"`.
3. Prefer stable `id` or `data-key` attributes for editable elements.
4. Use setup/Markup Editor to prepare page content, blog list templates, and blog post templates.
5. Verify public rendering before giving editors access.

Example editable field:

```html
<h1 id="hero_title" class="clipon">Page title</h1>
```

## Web Server Notes

The public Integration API is a physical PHP endpoint and needs no extra rewrite rule:

```text
POST   /clipon/api/integrations.php?provider=PROVIDER_ID
DELETE /clipon/api/integrations.php?provider=PROVIDER_ID&id=EXTERNAL_ID
```

Allow PHP execution for this endpoint while continuing to deny direct requests to `modules/`, `config/`, `content/`, `data/`, and `logs/`. Use TLS and ensure a reverse proxy preserves the request method, `Content-Type`, `Content-Length`, `Authorization`, and client address.

Apache releases include a root `.htaccess` that denies direct access to private runtime directories before routing real files.

The shipped Apache rules are relative to the directory containing `.htaccess` and therefore support both a domain root and a subdirectory deployment. Ensure `AllowOverride` permits rewrite and authorization directives. For a subdirectory such as `/cms`, keep `.htaccess` in that directory and open `/cms/clipon/setup.php`; do not add `RewriteBase /`.

For Nginx, adapt the following complete baseline server block. Set `root`, `server_name`, and the PHP-FPM socket for your host:

```nginx
server {
    listen 80;
    server_name example.com;
    root /var/www/clipon;
    index index.php index.html;

    location ~ ^/(?:config|content|data|logs|modules|templates|backup|backups|temp|tmp)(?:/|$) {
        deny all;
    }

    location ~* (?:\.jsonl?|\.log|\.bak|\.backup|\.old|\.orig|\.save|\.sql|\.sqlite(?:-shm|-wal)?|\.tmp|\.temp|~)$ {
        deny all;
    }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        try_files $uri =404;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_pass unix:/run/php/php-fpm.sock;
    }
}
```

Prefer placing runtime directories outside the document root when the hosting layout permits it. Otherwise, verify that requests such as `/logs/auth.log`, `/config/settings.php`, and `/data/geoip/geoip.csv` return `403` or `404` before going live.

## After Installation

- Log in to `/clipon/admin/login.php`.
- Set site URL and languages in Settings.
- Configure analytics/cookie consent if needed.
- Verify pages, blog, media uploads, redirects, and sitemap.
- Run a backup after the first successful setup.
