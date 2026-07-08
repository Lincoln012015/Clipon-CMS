# 📝 Core Blog Feature in Clipon CMS

The Core Blog subsystem implements a full-featured blogging system with support for categories, a tree-like structure, inline editing, and indexed route-map lookup. It is part of Clipon Core and is not an installable runtime module.

---

## 🛠 Setup and Structure

### 1. File structure
On an installed site:

*   `content/blog/` — PHP post files with a JSON payload and a `<?php die(); ?>` guard.
*   `config/blog_directories.php` — category hierarchy configuration.
*   `data/blog_tags.php` — localized tag reference.
*   `data/blog_index.php` — index for fast list rendering.
*   `templates/blog_post.php` — the main template for a single post.
*   `clipon/admin/blog.php` — the blog management panel.

### 2. Templating (blog_post.php)
For a post's data to be pulled in automatically and be editable, use the following `id` values on tags with the `clipon` class:
*   `title` — the post title.
*   `content` — the main post text.
*   `thumbnail` — the post thumbnail, set in the admin panel or via inline image editing.

Author, date, tags, excerpt, and SEO are edited in the post settings in the admin panel. In the template, they can be output as plain markup without `class="clipon"`.

**Example of a minimal layout:**
```html
<h1 id="title" class="clipon">Title</h1>
<time id="date" datetime="{{date}}">{{date}}</time>
<img id="thumbnail" class="clipon" src="{{thumbnail}}" alt="{{title}}">
<div id="content" class="clipon">Post content</div>
<span id="tags">tag1, tag2</span>
```

---

## 🚀 Usage

### 1. Managing in the Admin Panel
Go to the **Blog** section in the navigation panel:
*   **Creating categories**: Click "Add Category" to create a folder. Folders support nesting (drag-and-drop).
*   **Creating posts**: Click "Create Post". The system will automatically generate a URL (slug) based on the title.
*   **Settings (Accordion)**: Click on a post in the list to open the settings panel. Here you can change the author, date, tags, SEO parameters, template, and the **Post Thumbnail** via the centralized media picker.
*   **Tags**: A separate button in the blog admin panel opens the tag reference. Tags have a stable `id` and localized names. Posts store only tag `id` values; the names for the current language are substituted at render time.

### 2. Inline editing
1.  Open a post on the site.
2.  Add `?edit` to the URL (or click the "Pencil" icon in the admin panel).
3.  Click on the title, main content, or image to edit it.
4.  To edit the thumbnail, click on the element with `id="thumbnail"`.
5.  Changes are saved automatically (1 second after you stop typing).
6.  Click **"← Back to admin"** to save the version and return to the management panel.

### 3. Displaying a list of posts (Shortcodes)

#### Option A: Flexible `[blog_loop]` loop (Recommended)
Lets you insert a list of posts directly into your existing HTML design. It repeats the markup inside it for each post.

**Example:**
```html
<div class="articles">
    [blog_loop per_page=5]
    <article>
        <div class="thumbnail">{{thumbnail_img}}</div>
        <h2><a href="{{url}}">{{title}}</a></h2>
        <p>{{excerpt}}</p>
        <span class="date">{{date}}</span>
    </article>
    [/blog_loop]
</div>
```

**Variables available inside the loop:**
* `{{title}}` — the title.
* `{{url}}` — the link to the post.
* `{{date}}`, `{{author}}` — the date and author.
* `{{excerpt}}` — a short description for the blog card.
* `{{thumbnail}}` — the path to the thumbnail (URL).
* `{{thumbnail_img}}` — a ready-made `<img>` tag with the thumbnail. If no thumbnail is set, the value is empty.
* `{{image}}` — a separate fallback variable: automatically inserts an `<img>` tag with the first image from the post content if no thumbnail is set.
* `{{tags}}` — a list of clickable links to tags. The link text is taken from the current language's label, and `?tag=` receives the stable tag `id`.
* `{{slug}}` — the post's URL identifier.

#### Markup Editor during setup
During setup, the main tool is the **Markup Editor** for a specific output HTML/PHP file. It has three separate workflow scenarios:

* **Page Content** — markup for static page content. Here you have auto-rules for tags (`h1/h2/h3/p/img/a`), zone exclusions (`header/footer/nav/aside`), and manual adding or removal of `clipon` markers.
* **Blog List** — preparing a dynamic list of posts via `[blog_loop]` and, if needed, `[blog_pagination]`.
* **Blog Post Template** — preparing a single post template with editable fields `title`, `content`, `thumbnail`, and read-only metadata `date`, `author`, `tags`.

Old links remain compatible with the new structure: `mode=auto` and `mode=manual` open the **Page Content** scenario, `mode=blog-list` opens **Blog List**, and `mode=blog-post` opens **Blog Post Template**.

In the public release, the main Markup Editor endpoint is `clipon/markup_picker.php`. The old `visual_picker.php` and `blog_visual_picker.php` are not part of the base artifact.

#### List markup wizard
In the **Blog List** scenario, the Markup Editor converts the selected news card into a `[blog_loop]`.

You must select:
* the list container;
* one post card inside the container;
* the title field (`{{title}}`).

Optionally, you can select the image, excerpt, date, author, tags, link (`{{url}}`), and the pagination block. If no link is selected, the card is rendered without a link to a separate post, which is convenient for cases, testimonials, or other lists without a detail page. The **Per page** field sets `per_page` in the shortcode; valid values range from `1` to `50`, with a default of `6`.

The **Blog List** panel also includes:
* **Blog lists** — a switch for the specific list currently being marked up. The **Add list**, **Rename**, and **Remove** buttons add a new list, rename the active list, or remove the active list and its temporary markers.
* **Page param** — a custom pagination URL parameter for this list, for example `news_page`. This lets you set up a page with several independent blog lists. The field's hint uses the CMS's common tooltip pattern via the `data-tooltip` attribute; in the Markup Editor it renders as a floating tooltip over the page.
* **Filter tags** — selecting tags from the blog's tag reference. The selected tag `id` values are added to the shortcode as `tags="news,cases"` and filter posts specifically for this list.

The markup tools in **Blog List** are shown as a checklist: for each field you can see whether it has already been selected, and which DOM element is bound to the active list.

If the source template already has static pagination, select the **Pagination** tool and click the wrapper of that block (`nav`, `ul`, `div`). The wizard will preserve the wrapper's HTML, classes, and attributes, but will convert the page links to dynamic ones.

For the **List container**, **Post card**, and **Pagination** tools, clicking on the page opens an ancestor picker. There you can select the clicked element or one of its parent containers; hovering highlights the corresponding level on the page. `Esc` closes the picker without making a selection.

The Markup Editor and the fallback migration protect `[blog_loop]...[/blog_loop]` and `[blog_pagination]...[/blog_pagination]` from automatic tagging, so the `h2/p/img/a/span` auto-rules should not turn dynamic cards or pagination into static `clipon` fields.

The wizard's left panel can be collapsed with a button in the top bar to free up page space while selecting elements.

#### Post template wizard
The **Blog Post Template** scenario prepares the template for a single post:
* `title`, `content`, and `thumbnail` become editable fields with `class="clipon"`;
* `date`, `author`, and `tags` receive only the corresponding `id` and are filled in as read-only metadata from the post settings.

The **Blog Post Template** tools are also shown as a checklist: after selecting a field, the wizard shows which DOM element is bound to `title`, `content`, `thumbnail`, `date`, `author`, or `tags`.

For `thumbnail`, you need to select the `<img>` itself or a wrapper that contains an `<img>`. If there is no image, the wizard will not assign `id="thumbnail"`.

#### Conditionals in the template
You can use the `{{if variable}}...{{endif}}` construct to hide blocks when data is missing.
**Example:**
```html
[blog_loop]
    <div class="post">
        {{if thumbnail}} {{thumbnail_img}} {{endif}}
        <h3>{{title}}</h3>
    </div>
[/blog_loop]
```

#### Dynamic shortcode parameters
Since shortcodes are processed after the PHP in the template runs, you can use PHP variables to dynamically substitute parameters. This is useful when one template is used for several pages with different data.

**Example: showing a specialist's cases on their page**
```php
[blog_loop tags="specialist_<?= $slug ?>" per_page=10]
<div class="case-item">
    <h3>{{title}}</h3>
    <p>{{excerpt}}</p>
    <a href="{{url}}">Read more</a>
</div>
[/blog_loop]
```

In this example:
- `$slug` is the identifier of the current page (for example, `ivanov`, `petrov`)
- The blog tags must match: `specialist_ivanov`, `specialist_petrov`
- Each specialist's page automatically shows only their own cases

**Using custom page fields:**
```php
[blog_loop tags="specialist_<?= $specialist_id ?? $slug ?>" per_page=10]
...
[/blog_loop]
```

This lets you use a single template for all specialist pages, but with different content for each one.

#### Option B: Simple `[blog]` list
Outputs a standard vertical list of posts.
* `[blog per_page=5]` — the latest 5 posts.
* `[blog tags="news,travel"]` — filtering by tags. The value must be the stable tag `id`.
* `[blog template="partials/blog_list.php"]` — using a separate PHP file for rendering.

In PHP templates, `$post['tags']` contains the IDs, `$post['display_tags']` contains the labels for the current language, and `$post['localized_tags']` is an array of `id/label` pairs.

> Tag filtering works the same way for both `[blog]` and `[blog_loop]`.
>
> - Via the shortcode attribute: `tags="tag1,tag2"`.
> - Via the URL parameter `tags` or `tag`, if the `tags` attribute is not set in the shortcode.
>
>   Examples:
>   - `/blog.php?tags=travel` — show posts tagged `travel`.
>   - `/uk/blog?tag=travel` — show posts by the stable tag `id`.
>   - `/en/blog?tag=travel` — the same tag in the English version.
>   - `/blog.php?tags=travel,news` — either the `travel` or `news` tag.

#### Pagination
Both shortcodes automatically support pagination if the number of posts exceeds the `per_page` value.

*   **The `p` parameter**: the current page number is passed in the URL as `?p=1`. When navigating between pages, the system automatically preserves other query parameters (for example, selected tags).
*   **Number of posts**: `per_page` is capped on the server within the range `1..50`, even if a larger or smaller value is specified in the shortcode.
*   **Multiple lists on one page**: so that two blog blocks don't use the same `p` parameter, set a custom page parameter: `[blog per_page=6 page_param="blog_page"]` or `[blog_loop per_page=6 page_param="articles_page"]...[/blog_loop]`.
*   **Styling**: the pagination block has the `.blog-pagination` class, the current page has `.active`, gaps have `.ellipsis`, and the navigation buttons have the `.prev` and `.next` classes.
*   **Custom HTML pagination**: for static pagination selected in the wizard, a separate `[blog_pagination]` shortcode is generated. It uses the classes and wrapper from the template, moves `.active` to the current page, and updates `href` values taking `page_param` into account.
*   **Custom templates**: when using `template="path/to/file.php"`, pagination is not rendered automatically. The template receives `$posts` (only the posts for the current page), `$allPosts`, `$postsToShow`, `$total`, `$page`, `$perPage`, `$totalPages`, and `$pageParam`, which you can use to output your own navigation.

**Example of custom pagination:**
```html
[blog_loop per_page=6 page_param="blog_page" pagination="none"]
    <article>{{title}}</article>
[/blog_loop]

[blog_pagination per_page=6 page_param="blog_page"]
    <nav class="pagination">
        <a class="prev" href="#">Prev</a>
        <a href="#">1</a>
        <a class="active" href="#">2</a>
        <a href="#">3</a>
        <a class="next" href="#">Next</a>
    </nav>
[/blog_pagination]
```

If the CMS cannot recognize the page template inside `[blog_pagination]`, it will output the standard `.blog-pagination`, so that navigation doesn't disappear.

---

## 💡 Important Notes
*   **Performance**: The Blog subsystem uses automatic indexing (`data/blog_index.php`). With a large number of posts, the system doesn't scan all files every time, ensuring fast list loading.
*   **Tags**: The new format stores an array of stable tag IDs in the post: `'tags' => ['care', 'travel']`.
*   **Tag reference**: The global list of tags is stored in `data/blog_tags.php` in an `id + labels` format, for example `['id' => 'care', 'labels' => ['uk' => 'Догляд', 'en' => 'Care']]`.
*   **Tag cleanup**: The Blog subsystem automatically cleans up stray `<p>` tags when editing titles and metadata.
*   **Routing**: When a `slug` is renamed or a post is moved, the system automatically updates `route_map.php`, preserving the integrity of links across the entire site.
*   **Localization**: Post content and tag labels are taken from the site's current language. If a tag translation is missing, the primary language is used.
