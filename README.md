# MangazScans

A lightweight, security-hardened WordPress theme for manga / comic sites.
Forked from Madara 1.7.3.1 (the most recent ThemeForest build, Nov 2021)
and modernized for the comic site `MangazScans`.

- **Theme dir**: `mangazscans/`
- **Theme version**: 2.0.0 (forked baseline)
- **Forked from**: Madara 1.7.3.1 by WPStylish (ThemeForest)
- **Required plugin**: Madara-Core (ships bundled at `mangazscans/app/plugins/packages/madara-core.zip`, auto-installed via TGM on first activation)
- **Tested PHP**: 8.4 (`php -l` clean across all theme files)

## Install (you said you'd deploy yourself)

1. Build the install zip:
   ```bash
   cd /home/user/modaro
   ./build-zip.sh
   ```
   Produces `dist/mangazscans.zip` (~2.5 MB).
2. WP Admin → **Appearance → Themes → Add New → Upload Theme** → pick `mangazscans.zip`.
3. Activate. The theme's silent installer (`app/install-core.php`) extracts the bundled **Madara - Core** plugin into `wp-content/plugins/` and activates it automatically. No TGM prompts.
   - If WordPress can't write to `wp-content/plugins/` directly (e.g. shared host requires FTP credentials), you'll see an admin notice and can install via the fallback TGM prompt under **Appearance → Install Plugins**.
4. Go to **Appearance → Theme Options** to configure colors, layout, reading style, etc.
5. (Optional) Customizer → **Site Identity** → upload your own logo / Site Icon. The theme ships clean defaults at `images/logo.svg` and `images/favicon.svg`; they auto-load only when you haven't uploaded your own.

### Reading modes (comic-book vs long-strip)

Theme Options → **WP Manga Reading Layout** → *Manga Image Chapter - Reading Style*:

- **Paged** — comic-book style: 1, 3, 6, or 10 images per page, reader navigates with prev/next buttons and keyboard arrows.
- **List** — long-strip / webtoon style: every chapter image stacked on one scroll.

Visitors can override per-session with `?style=paged` or `?style=list` on the chapter URL, and per-manga overrides are available in the manga edit screen (Other Settings → Reading Style).

## What changed vs. stock Madara 1.7.3.1

### Security fixes
- **CVE-class fix: Local File Inclusion in `App\Madara::ajax_load_next_page`.** The original handler `include()`d any `$_POST['template']` whose name contained the substring `plugins`, with no nonce. Now requires a valid `madara_load_more` nonce, validates the template slug (`[a-zA-Z0-9_/-]+`, no `..`, no `.php`), and routes through `get_template_part()` so resolution stays inside the active theme. Companion JS in `js/ajax.js` sends the nonce via `wp_localize_script`.
- **Info-leak fix: `madara_hover_load_post`.** Previously returned content of any post id, including drafts/private. Now restricted to `post_status === 'publish'` and `post_type === 'wp-manga'`; otherwise returns 404.
- **Input handling**: `__format_POST_args()` now uses strict comparisons and `sanitize_text_field()` on string leaves instead of ad-hoc `str_replace` and loose `==`.

### Silent plugin install (new in this phase)
- The theme auto-installs and auto-activates the bundled `madara-core` plugin on theme activation, via `app/install-core.php` → `after_switch_theme`. The TGM prompt is kept only as a fallback for hosts where WP can't write directly to `wp-content/plugins/`.
- `madara-core` itself slimmed: 7.5 MB → 0.9 MB. Duplicate FontAwesome/Ionicons copies (the plugin bundled a second set), legacy font formats, and a bundled set of **HelveticaNeue** `.otf`/`.ttf` files (non-free Monotype font, licensing timebomb, not actually loaded via `@font-face`) were all removed.
- Final install zip: **2.5 MB** (original Madara bundle was ~25 MB).

### Removed (saves ~12 MB and a lot of attack surface)
- AMP support (deprecated by Google; loader, options panel, plugin templates).
- WooCommerce templates, sidebar, integration plugin (no shop on this site).
- Bundled integration plugins: `madara-jetpack`, `madara-welcome`, `madara-starter-content`, `madara-shortcodes`.
- Envato/Mangabooth purchase-code activation flow (`do_validate`, `do_deactivate`, `theme_is_activated`, `get_purchase_code`, …) — pointed at `mangabooth.com` and is irrelevant to a fork.
- 760-line admin-only `release_logs()` changelog method.
- `App\Helpers\Date` class — its global `Date()` function clashed with PHP's built-in `date()` under PHP 8.4 (PHP function names are case-insensitive).
- `ct-icon` font — its loader was already broken in v1.7.3.1 (CSS referenced `madara-budicon.*`, files were named `cactus-budicon.*`).
- Bundled jQuery colorpicker (296 KB) — replaced with native `wpColorPicker`.

### Slimmed assets
- Legacy icon-font formats stripped (eot/svg/ttf/woff). Modern browsers use woff2.
- Duplicate min/unmin JS pairs (`shuffle`, `lightbox`, `aos`, `lazysizes`) — kept the minified copy, dropped the rest.
- `less/` source dir (CSS already compiled into `style.css`).
- `screenshot.jpg` (492 KB) replaced with a 36 KB MangazScans `screenshot.png`.

### Rebrand
- `style.css` header: name, description, version, text domain, license.
- Text domain `madara` → `mangazscans` (sweep across 73 PHP files).
- New brand assets in `images/`: `logo.svg`, `logo-light.svg`, `favicon.svg`, plus PNG fallbacks for each.
- Favicon auto-injected via `wp_head` only when no Customizer Site Icon is set.

## What was deliberately left alone

- The `App\Madara` PHP class name and `App\` namespace. Renaming would have rippled through ~100 references with zero functional benefit (these are internal, not user-visible).
- `madara-core` plugin (the manga post type / chapter reader engine). The theme depends on it; it's still bundled as a TGM-installable zip.
- Bootstrap 4.3.1, Slick, FontAwesome 5.15.3, Ionicons 4.5.10. Used by hundreds of theme classes — replacing them would be a from-scratch redesign, not a fork.
- `.po` / `.mo` translation files. Strings still resolve in English under the new `mangazscans` text domain; you'd want to recompile `.mo` files if you go multi-language.

## Repo layout

```
modaro/
├── mangazscans/           # the theme (this is what gets zipped)
├── build-assets.py        # regenerates favicon/logo/screenshot PNGs from SVG
├── build-zip.sh           # produces dist/mangazscans.zip
├── README.md              # this file
└── themeforest-…-madara-…zip   # original purchase, preserved for reference
```

## Verification

`php -l` (PHP 8.4) is clean across every theme PHP file.
This was a static audit only — no live WordPress was used. Test on staging
before promoting to production, especially the manga reader flow which
depends on the Madara-Core plugin.
