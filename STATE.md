# State
Updated: 2026-10-04 by Claude

## Now
0.3.0 released on 2026-10-04 (tag `0.3.0`, `main` and `develop` at `90496d9`). It fixes Instagram embeds with account-name links (`instagram.com/{user}/p/{id}/`), adds x.com, converts only single-post links, and keeps a visible link in each embed.

## Next
- Confirm verilymag.com has updated to 0.3.0, then tell Mary Rose (Help Scout #785724) the draft can be published.
- Decide whether to raise `Requires PHP` from 8.1 to 8.2. Web PHP per site is set in nginx and wasn't checked.

## Blocked / waiting on
Nothing.

## Verify
- Local copy: `~/Herd/verilymag`. Post 48172 has 7 Instagram embeds, 5 with account-name links.
- Render it with `wp eval` and check every `data-instgrm-permalink` is `/p/{id}/`.
- Load the rendered HTML with embed.js in headless Chrome. Every iframe should be taller than 2px.

## Gotchas
- The [embed] shortcode's url is in its content or `src` attribute. Never read it from the output, where the first link can be a hashtag.
- Core's embed block stores `providerNameSlug`, never `provider`. Match on the url host instead.
- `wp ... --skip-themes` on Mai sites fatals in mai-performance-images (`mai_get_breakpoints()`). That's the flag, not a bug.
- Don't add SRI hashes to the Instagram or Twitter script tags. Both scripts change without notice and SRI would block them.
