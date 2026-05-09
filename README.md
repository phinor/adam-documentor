# ADAM Documentor

Generate a static site from a published Google Doc.

## How it works

A single PHP script (`src/generate.php`) drives the build:

1. Fetches the HTML of a published Google Doc.
2. Parses it with PHP's DOM extension and walks the heading structure (`h1` / `h2` / `h3`).
3. Splits the document into one HTML page per `h1`, with `h2` and `h3` rendered as anchored sections within each page.
4. Side-loads every image referenced in the document into `html/images/` so the published site has no runtime dependency on Google's CDN.
5. Wraps each page in `template/template.html`, builds a per-page navigation menu, and writes the result to `html/`.
6. Generates `sitemap.txt` and `robots.txt`, sweeps any stale HTML or images left over from previous runs, and logs a summary to `html/generate.log`.

## Requirements

- PHP 7.2.5 or higher (PHP 8.x supported)
- The `dom` PHP extension (usually bundled)
- [Composer](https://getcomposer.org/)
- A Google Doc that has been published to the web

## Installation

```bash
git clone https://github.com/phinor/adam-documentor.git
cd adam-documentation
composer install
cp .env.example .env
```

Then edit `.env` (see [Configuration](#configuration) below).

## Configuration

All configuration lives in the `.env` file at the repository root. Two values are required:

### `DOCUMENTATION_URL`

The "Publish to web" URL of the source Google Doc. To obtain it:

1. Open the Google Doc in a browser.
2. Go to **File → Share → Publish to web**.
3. On the **Link** tab, click **Publish** and copy the URL it produces.

The URL looks like `https://docs.google.com/document/d/e/.../pub`. The script downloads this URL on every run, so the doc must remain published.

### `SITEMAP_URL`

The public base URL where the generated site will be served. It is used when writing `sitemap.txt` and `robots.txt`. **Include the trailing slash**, e.g. `https://help.adam.co.za/`.

## Usage

From the repository root:

```bash
php src/generate.php
```

Output is written to `html/`. Each top-level heading in the source document becomes one HTML file (the first `h1` becomes `index.html`). The script also:

- Writes `html/full.html` containing the entire document as a single page.
- Extracts Google's inline CSS into `html/default.css`.
- Copies `template/custom.css`, `template/display.js`, `template/logo.png`, and `template/favicon.ico` into `html/`.
- Removes any HTML pages or images in `html/` that were not produced by this run.
- Records image and HTML counts (new, reused, deleted) in `html/generate.log`.

To deploy, serve the contents of `html/` from any static web host.

## Project layout

```
adam-documentation/
├── src/
│   └── generate.php       # The generator script
├── template/
│   ├── template.html      # Page wrapper (header, nav, footer)
│   ├── custom.css         # Site-specific styles
│   ├── display.js         # Mobile nav toggle
│   ├── logo.png
│   └── favicon.ico
├── html/                  # Generated output (committed)
├── .env.example           # Sample configuration
├── composer.json
└── README.md
```

## Customising the template

`template/template.html` is the page wrapper used for every generated page. The generator substitutes the following placeholders:

| Placeholder      | Replaced with                                                |
| ---------------- | ------------------------------------------------------------ |
| `#title`         | The page's `h1` text                                         |
| `#menu`          | The navigation tree for the current page                     |
| `#body`          | The page's HTML content                                      |
| `#cssfile`       | `default.css?<hash>` (extracted from the Google Doc)         |
| `#templatecss`   | `custom.css?<hash>`                                          |
| `#hash(file)`    | The MD5 hash of `template/<file>`, used for cache-busting    |

Edit `template/template.html` and `template/custom.css` to change the look of the site, then re-run the generator.

## Contributing

Contributions are welcome. For anything beyond a small fix, please open an issue first to discuss the change.

1. Fork the repository.
2. Create a feature branch: `git checkout -b my-change`.
3. Make your changes, matching the existing code style.
4. Commit with a descriptive message and push to your fork.
5. Open a pull request against `main`.

Bug reports and feature requests should be filed at <https://github.com/phinor/adam-documentation/issues>.

## License

This project is released under the MIT License. See [LICENSE](LICENSE) for the full text.
