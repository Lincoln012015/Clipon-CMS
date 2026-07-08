# Bundled Browser Dependencies

Clipon CMS core is PHP-based and does not require Node.js or public CDN access for normal runtime use.

The base release vendors the browser libraries needed by admin/editor screens under `clipon/assets/vendor/`:

| Library | Version | Local path | License | Used by |
| --- | --- | --- | --- | --- |
| SortableJS | 1.15.0 | `clipon/assets/vendor/sortablejs/Sortable.min.js` | MIT | Pages, Blog, Settings drag-and-drop ordering. |
| Chart.js | 3.9.1 | `clipon/assets/vendor/chartjs/chart.min.js` | MIT | Analytics dashboard charts. |
| marked | 12.0.1 | `clipon/assets/vendor/marked/marked.min.js` | MIT | Inline editor markdown parsing. |
| Turndown | 7.1.3 | `clipon/assets/vendor/turndown/turndown.js` | MIT | Inline editor HTML-to-Markdown conversion. |
| turndown-plugin-gfm | 1.0.2 | `clipon/assets/vendor/turndown-plugin-gfm/turndown-plugin-gfm.js` | MIT | GitHub-Flavored Markdown table/list support for Turndown. |

Each vendored package keeps its license notice beside the bundled file.

## Inline Editor Bundle

`clipon/assets/js/dist/tiptap-bundle.iife.js` is included as a prebuilt editor bundle. It embeds the following runtime packages; normal CMS installations load the local bundle and do not fetch these packages from a CDN.

| Package | Version | License |
| --- | --- | --- |
| `@tiptap/core` | 2.27.2 | MIT |
| `@tiptap/starter-kit` | 2.27.2 | MIT |
| `@tiptap/extension-image` | 2.27.2 | MIT |
| `@tiptap/extension-link` | 2.27.2 | MIT |
| `@tiptap/extension-text-style` | 2.27.2 | MIT |
| `@tiptap/extension-color` | 2.27.2 | MIT |
| `@tiptap/extension-highlight` | 2.27.2 | MIT |
| `@tiptap/extension-text-align` | 2.27.2 | MIT |
| `@tiptap/extension-underline` | 2.27.2 | MIT |
| `@tiptap/extension-bubble-menu` | 2.1.13 | MIT |
| `@tiptap/extension-floating-menu` | 2.1.13 | MIT |
| `@tiptap/extension-table` | 2.27.2 | MIT |
| `@tiptap/extension-table-row` | 2.27.2 | MIT |
| `@tiptap/extension-table-cell` | 2.27.2 | MIT |
| `@tiptap/extension-table-header` | 2.27.2 | MIT |
| `@tiptap/pm` (ProseMirror integration) | 2.27.2 | MIT |
| `@floating-ui/dom` | 1.7.5 | MIT |
| `@floating-ui/core` | 1.7.4 | MIT |
| `@floating-ui/utils` | 0.2.10 | MIT |
| `marked` | 12.0.1 | MIT |

The prebuilt editor bundle and applicable upstream copyright/license notices are included in the release. Consolidated notices are shipped beside the bundle in `clipon/assets/js/dist/THIRD_PARTY_LICENSES.md`. Redistributors of a modified bundle must update the dependency inventory and notices.
