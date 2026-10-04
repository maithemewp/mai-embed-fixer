# Changelog

All notable changes to **Mai Embed Fixer** are recorded here. Reverse-chronological.

## 0.3.0 (2026-10-04)

### Fixed
- **Instagram embeds with the account name in the link now show.** Instagram's share button gives links like `instagram.com/{user}/p/{id}/`, and its embed script can't load that shape, so readers saw an empty frame. The plugin rewrites them to `instagram.com/p/{id}/`. `/reels/{id}/` becomes `/reel/{id}/`, and anything after the id, like `?img_index=3`, is dropped. Found on Verily.
- **Other `[embed]` shortcodes are no longer blanked.** 0.2.x returned an empty string for every `[embed]` that wasn't Instagram or Twitter, so YouTube and other embeds in classic content disappeared.
- **The `[embed]` shortcode embeds the right tweet.** It took its url from the first link in the output, which is a hashtag or mention when Twitter's oEmbed works. It now reads the url from the shortcode, as core does.

### Added
- **x.com and mobile links.** `x.com`, `mobile.twitter.com` and `mobile.instagram.com` links are converted too.
- **A visible link inside each embed.** Readers still get a link to the post when an ad blocker or consent tool stops Instagram's or Twitter's script.
- **`Requires at least: 6.2`** in the plugin header. The plugin uses `WP_HTML_Tag_Processor`, which arrived in 6.2.

### Changed
- **Only links to a single post, reel or tweet are converted.** Profile, hashtag and explore links are left as WordPress rendered them, instead of becoming empty embeds.
- **A failed regex returns the post untouched.** A very long post can hit PHP's regex limits. The post now renders without the script swap instead of breaking.
- **Modernized code.** `strict_types`, a `Network` enum holding everything that differs between networks, `match`, and typed signatures. Still PHP 8.1.

## 0.2.2 (2025-09-17)

### Fixed
- **Undefined `$url` warning.**

## 0.2.1 (2025-09-09)

### Fixed
- **Composer conflicts with other plugins.**

## 0.2.0 (2025-09-09)

### Added
- **Support for the `[embed]` shortcode,** for classic content.

## 0.1.0 (2025-08-15)

### Added
- **First release.** Converts Instagram and Twitter embed blocks to each network's own markup and adds its script once per post.
