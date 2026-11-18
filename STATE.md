# State
Updated: 2026-11-18 by Claude

## Now
0.3.0 released on 2026-10-04 (tag `0.3.0`, `main` and `develop` at `90496d9`). It fixes Instagram embeds with account-name links (`instagram.com/{user}/p/{id}/`), adds x.com, converts only single-post links, and keeps a visible link in each embed.

On 2026-11-18 0.3.0 was pushed and activated on the 41 sites that run Mai Performance Media 1.0.0 but had no Embed Fixer, so it now runs on every one of them. Before that, pasted Instagram and X embeds with no script showed only their link on those sites: 1,888 Instagram posts on one site, 632 on another. Mai Performance Media delays the script the fixer adds until each embed nears the screen. Three dev or staging copies still run 0.2.1 or 0.2.2.

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
