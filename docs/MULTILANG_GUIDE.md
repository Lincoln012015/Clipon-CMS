# Guide to Using Multilingual Support in Clipon CMS

Clipon CMS base includes a runtime fallback for the primary language and public-safe integration points for multilingual support. Full management of multiple languages, translations, localized slugs, and route maps for language versions is activated via the licensed Multilang/PRO module.

This document distinguishes between:

- **Base core fallback** — the site runs with a single primary language, and the multilingual panels in the admin panel may show a locked/promo state.
- **Multilang module** — adds full language management, translations, localized URLs, alternate links, and primary language migrations.

---

## 1. Language Settings

In base core, the site's primary language is available. Managing multiple languages in Settings is locked if the Multilang module is not installed and does not have a valid license.

After the Multilang module is activated:

Before you start working with translations, you need to configure the list of languages:

1. Go to **Settings** in the admin panel.
2. Find the **Languages** section.
3. Add the languages you need (for example, `uk` for Ukrainian, `en` for English).
4. **Important:** the first language in the list is considered the **primary** one. It is displayed at the root address (for example, `/about`), while the others are displayed with a prefix (for example, `/en/about`).

### Changing the primary language in an existing project

This scenario belongs to the Multilang module.

- When you change the order of languages in the settings (the top language becomes primary), the system may perform a migration of primary content between locales.
- During this operation, the CMS updates canonical slugs (including page/post URLs), rebuilds the route map, and rebuilds the sitemap.
- A backup of the content files is created before the migration, and a report is generated in `logs/migrations/` on the installed site after it completes.
- If an error occurs during the operation, the CMS attempts to automatically roll back the data from the backup.

---

## 2. Editing Pages

Full editing of page translations is available after the Multilang module is activated.

To translate a page, open it in the admin panel:

1. On the right you'll find the **Page Settings** block.
2. Expand the **Languages / Translations** accordion.
3. Select the language you want to translate into.
4. **What can be translated:**
   - **Title:** the page name for a specific language.
   - **Slug (URL):** a unique address. For example, the Ukrainian version may have the URL `kontakty`, while the English one has `contacts`.
   - **SEO Meta:** Title and Description for search engines.
   - **Content blocks:** all the text on the page marked with `class="clipon"`. Simply switch the language in the accordion and edit the text right on the page — the system will automatically save it for the selected language.

---

## 3. Optional Module/Template API Examples (for developers)

Base core has a fallback-level PHP class, `SiteLanguageStub`, which returns URLs without a language prefix. The Multilang module can connect full helpers for the language switcher and localized URLs.

Do not treat `lang_links()`, `url()`, or `resource_url()` as base core global functions unless they are connected by your template or module.

The examples below are conditional integration examples, not APIs guaranteed by the base release. Confirm that the active module or site template defines each helper before using it.

### Language switcher
After the Multilang module is activated, the language switcher should be built from the language-link data provided by the module. If the `lang_links()` helper is connected in your template, an example might look like this:

```php
<ul class="lang-switcher">
    <?php foreach(lang_links() as $l): ?>
        <li class="<?= $l['active'] ? 'is-active' : '' ?>">
            <a href="<?= $l['url'] ?>" hreflang="<?= $l['code'] ?>">
                <?= strtoupper($l['code']) ?>
            </a>
        </li>
    <?php endforeach; ?>
</ul>
```
*In Multilang mode, the helper should take into account the current language, localized slugs, and the settings in the admin panel.*

### Links in menus and text
In Multilang mode, build menus through the localized URL helper provided by the module or your template. If the `url()` helper is connected, an example:

```php
<a href="<?= url('/about') ?>">About us</a>
```
- If the language is `uk` (primary): returns `/about`
- If the language is `en`: automatically returns `/en/about`

### Redirects after form submission
If a form redirects the user to another page after submission, for example to a thank-you page, do not specify a fixed address such as `/thank-you`.

The site's language is determined from the URL: the primary language opens without a prefix (`/thank-you`), while other languages open with a language prefix or a localized slug (`/en/thank-you`, `/en/thanks`). Therefore, a fixed redirect to `/thank-you` will open the primary language version even when the user filled out the form in a different language.

When the optional integration provides `url()`, use it for localized redirects:

```php
$redirectUrl = url('/thank-you');
```

If the optional integration provides `resource_url()`, it can resolve separate localized slugs, for example:

```php
$redirectUrl = resource_url('thank-you', 'page');
```

The same rule applies to `action`, `data-redirect`, or JavaScript redirects in forms: the address should be built through the multilingual helper rather than being hardcoded.

---

## 4. Media Library and SEO

Localized alt text is available in Multilang mode.

Images also need localization for better ranking in Google:

1. Go to the **Media** section.
2. In the top right corner, select a language (for example, `EN`).
3. Fill in the **Alt text** field under the images in English.
4. The system will automatically insert this text into the `<img>` tag on the corresponding language version of the site.

---

## 5. Blog

Blog post translations are available in Multilang mode.

Blog posts work similarly to pages:
- You can create a translation for each post.
- If a post has a translation, it will appear in the list for the corresponding language version.
- If there is no translation, the post will be hidden in that language (or will only be displayed in the primary language).

---

## 6. Analytics

Language-based analytics is available when the site has active language versions. In base core, analytics may show basic or locked/promo data depending on the installed modules.

You can track the popularity of each language version:
- Go to the **Analytics** section.
- Find the **Languages** card.
- It displays the number of views for each localization, letting you assess engagement from foreign audiences.

---

## Technical Details (SEO Under the Hood)
The system automatically adds to the `<head>` of each page:
- An `<html lang="...">` tag with the correct language code.
- `<link rel="alternate" hreflang="...">` tags for all language versions, which helps Google index the site correctly.
- An `x-default` tag pointing to the primary version.

In the base fallback for a single primary language, this SEO data may be limited to the primary language, without alternate links for additional locales.
