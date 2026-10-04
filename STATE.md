# State
Updated: 2026-10-04 by Claude

## Now
0.3.0 committed on `develop`, not pushed or tagged. Fixes Instagram embeds that use account-name links (`instagram.com/{user}/p/{id}/`), which Instagram's embed.js can't load. Also adds x.com support and modernizes the code (strict types, enum, match). A second commit fixes what the review found: regex failures return the post untouched, the shortcode reads its own url, only single-post links convert, and each embed keeps a visible link.

## Next
- Push and tag 0.3.0 once Mike approves.
- Update verilymag.com, then tell Mary Rose (Help Scout #785724) the draft can be published.
- Decide whether to raise `Requires PHP` from 8.1 to 8.2. Web PHP per site is set in nginx and wasn't checked.

## Blocked / waiting on
Mike's go-ahead to push and tag.

## Verify
- Local copy: `~/Herd/verilymag`. Post 48172 has 7 Instagram embeds, 5 with account-name links.
- Render it with `wp eval` and check every `data-instgrm-permalink` is `/p/{id}/`.
- Load the rendered HTML with embed.js in headless Chrome. Every iframe should be taller than 2px.

## Gotchas
- The [embed] shortcode's url is in its content or `src` attribute. Never read it from the output, where the first link can be a hashtag.
- Core's embed block stores `providerNameSlug`, never `provider`. Match on the url host instead.
- `wp ... --skip-themes` on Mai sites fatals in mai-performance-images (`mai_get_breakpoints()`). That's the flag, not a bug.
- Don't add SRI hashes to the Instagram or Twitter script tags. Both scripts change without notice and SRI would block them.
