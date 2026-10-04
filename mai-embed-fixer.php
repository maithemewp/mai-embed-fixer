<?php

/**
 * Plugin Name:       Mai Embed Fixer
 * Plugin URI:        https://bizbudding.com/
 * Description:       Attempts to fix twitter/x and instagram embeds that aren't working in WordPress.
 * Version:           0.3.0
 * Requires at least: 6.2
 * Requires PHP:      8.1
 *
 * Author:            BizBudding
 * Author URI:        https://bizbudding.com
 */

declare(strict_types=1);

namespace Mai\EmbedFixer;

use WP_HTML_Tag_Processor;
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

// Load vendor files.
require_once __DIR__ . '/vendor/autoload.php';

/**
 * The social networks this plugin knows how to embed.
 *
 * Everything that differs between networks lives here, so adding a network
 * means adding a case and its match arms. None of the matches has a default,
 * so a missing arm throws UnhandledMatchError instead of quietly doing nothing.
 *
 * @since 0.3.0
 */
enum Network: string {
	case Instagram = 'instagram';
	case Twitter   = 'twitter';

	/**
	 * Get the network for a url, from its host.
	 *
	 * x.com is the same network as twitter.com since the rebrand, and links
	 * pasted today almost always use x.com, so both map to Twitter.
	 *
	 * @since 0.3.0
	 *
	 * @param string $url The url to check.
	 *
	 * @return self|null The network, or null if the host isn't one we handle.
	 */
	public static function from_url( string $url ): ?self {
		$host = strtolower( (string) parse_url( $url, PHP_URL_HOST ) );
		$host = (string) preg_replace( '/^(www|mobile)\./', '', $host );

		return match ( $host ) {
			'instagram.com'        => self::Instagram,
			'twitter.com', 'x.com' => self::Twitter,
			default                => null,
		};
	}

	/**
	 * Get the url the network's script can embed, or null if the url isn't a single post.
	 *
	 * Profile, hashtag and explore links share the host but have nothing to
	 * embed, so converting them would leave an empty frame. Those return null
	 * and the original content is kept.
	 *
	 * Instagram's script builds its iframe from the url plus `embed/captioned/`.
	 * Its share button gives links with the account name in the path, like
	 * `instagram.com/{user}/p/{id}/`, and the embed page for that shape 404s,
	 * redirects to the homepage, and is refused in an iframe. The same is true
	 * of `/reels/{id}/`. So Instagram urls are rewritten to
	 * `https://www.instagram.com/{p|reel|tv}/{id}/`, dropping everything after the id.
	 *
	 * @since 0.3.0
	 *
	 * @param string $url The url.
	 *
	 * @return string|null
	 */
	public function permalink( string $url ): ?string {
		$path = (string) parse_url( $url, PHP_URL_PATH );

		return match ( $this ) {
			self::Instagram => preg_match( '#^/(?:[^/]+/)?(p|reels?|tv)/([A-Za-z0-9_-]+)#', $path, $matches )
				? sprintf( 'https://www.instagram.com/%s/%s/', 'reels' === $matches[1] ? 'reel' : $matches[1], $matches[2] )
				: null,
			self::Twitter   => preg_match( '#^/(?:[^/]+|i/web)/status(?:es)?/\d+#', $path ) ? $url : null,
		};
	}

	/**
	 * Get the blockquote class the network's script looks for.
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function blockquote_class(): string {
		return match ( $this ) {
			self::Instagram => 'instagram-media',
			self::Twitter   => 'twitter-tweet',
		};
	}

	/**
	 * Get the script tag that turns the blockquotes into embeds.
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function script(): string {
		return match ( $this ) {
			self::Instagram => '<script async class="mai-instagram-script" src="//www.instagram.com/embed.js" charset="utf-8"></script>',
			self::Twitter   => '<script async class="mai-twitter-script" src="https://platform.twitter.com/widgets.js" charset="utf-8"></script>',
		};
	}

	/**
	 * Get a regex fragment that matches the script's src, for finding copies already in the content.
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function script_pattern(): string {
		return match ( $this ) {
			self::Instagram => 'www\.instagram\.com\/embed\.js',
			self::Twitter   => 'platform\.twitter\.com\/widgets\.js',
		};
	}

	/**
	 * Get the embed markup for a permalink.
	 *
	 * The url is also the link text inside the blockquote, so readers still
	 * get a link to the post when an ad blocker or consent tool stops the
	 * network's script.
	 *
	 * @since 0.3.0
	 *
	 * @param string $permalink A url from permalink().
	 *
	 * @return string
	 */
	public function embed( string $permalink ): string {
		$attributes = match ( $this ) {
			self::Instagram => sprintf( ' data-instgrm-captioned data-instgrm-permalink="%s" data-instgrm-version="14"', esc_url( $permalink ) ),
			self::Twitter   => ' data-lang="en"',
		};

		return sprintf(
			'<figure class="wp-embed-%1$s" style="width:100%%;max-width:540px;"><blockquote class="%2$s" style="width:100%%;"%3$s><a href="%4$s">%5$s</a></blockquote></figure>',
			$this->value,
			$this->blockquote_class(),
			$attributes,
			esc_url( $permalink ),
			esc_html( $permalink )
		);
	}
}

/**
 * Get the embed markup for a url, or null if the url isn't a post we can embed.
 *
 * @since 0.2.0
 * @since 0.3.0 Takes only a url, and returns null for urls we don't handle.
 *
 * @param string $url The url.
 *
 * @return string|null
 */
function get_embed( string $url ): ?string {
	$network   = Network::from_url( $url );
	$permalink = $network?->permalink( $url );

	return $permalink ? $network->embed( $permalink ) : null;
}

add_filter( 'render_block_core/embed', __NAMESPACE__ . '\convert_embeds', 20, 2 );
/**
 * Convert embed blocks to their social media equivalents.
 *
 * Instagram's oEmbed endpoint is gone and Twitter's is unreliable, so the
 * saved block often holds nothing but the bare url. We swap the whole block
 * for the network's own blockquote markup, which its script turns into the
 * real embed in the browser.
 *
 * The block's url attribute is the source of truth, so the incoming content
 * is replaced outright and never needs to be a string.
 *
 * @since 0.1.0
 * @since 0.3.0 Handles x.com and mobile. hosts, and skips urls that aren't a single post.
 *
 * @param mixed $block_content The content of the block.
 * @param array $block         The block data.
 *
 * @return mixed The content of the block.
 */
function convert_embeds( mixed $block_content, array $block ): mixed {
	return get_embed( (string) ( $block['attrs']['url'] ?? '' ) ) ?? $block_content;
}

add_filter( 'do_shortcode_tag', __NAMESPACE__ . '\convert_embed_shortcode', 10, 4 );
/**
 * Convert the [embed] shortcode to the proper social media embed format.
 *
 * Classic content goes through the shortcode instead of the block, so it
 * needs the same swap. The url is the shortcode's content, or its `src`
 * attribute, the same places core reads it from. We don't read it from the
 * output, because when oEmbed works the first link there can be a hashtag
 * or mention instead of the post.
 *
 * @since 0.2.0
 * @since 0.3.0 Reads the url from the shortcode, and returns the original output for urls we don't handle.
 *
 * @param mixed        $output The output from the shortcode. Any shortcode's callback lands here, and some return null or false.
 * @param string       $tag    The name of the shortcode.
 * @param array|string $attr   The shortcode attributes, or an empty string when there are none.
 * @param array        $m      The regex match for the shortcode. Index 5 is its content.
 *
 * @return mixed The modified output.
 */
function convert_embed_shortcode( mixed $output, string $tag, array|string $attr = '', array $m = [] ): mixed {
	if ( 'embed' !== $tag ) {
		return $output;
	}

	$url = trim( (string) ( $m[5] ?? '' ) ) ?: (string) ( is_array( $attr ) ? $attr['src'] ?? '' : '' );

	return get_embed( $url ) ?? $output;
}

add_filter( 'the_content', __NAMESPACE__ . '\add_scripts', 30, 1 );
/**
 * Add each network's script to singular posts that have one of its embeds.
 *
 * Embeds pasted from the networks often bring their own script tag, so a
 * post can end up loading the same script several times. We remove every
 * copy and add one back, before the first of our embeds, or at the end if
 * there is none of ours (e.g. only a custom HTML embed).
 *
 * If a regex fails (a huge post can hit PCRE's limits), the post is
 * returned untouched rather than blanked.
 *
 * @since 0.1.0
 * @since 0.3.0 Returns the original content if a regex fails.
 *
 * @param mixed $content The content of the post. Another filter may have broken it, so it isn't trusted to be a string.
 *
 * @return mixed The content of the post.
 */
function add_scripts( mixed $content ): mixed {
	if ( ! is_string( $content ) || ! is_main_query() || ! in_the_loop() || ! is_singular() ) {
		return $content;
	}

	$original = $content;

	foreach ( Network::cases() as $network ) {
		$tags = new WP_HTML_Tag_Processor( $content );

		if ( ! $tags->next_tag( [ 'tag_name' => 'blockquote', 'class_name' => $network->blockquote_class() ] ) ) {
			continue;
		}

		// Remove every existing copy of the script. We add one back below.
		$content = preg_replace( '/<script[^>]*src="[^"]*' . $network->script_pattern() . '[^"]*"[^>]*><\/script>/', '', $content );

		if ( null === $content ) {
			return $original;
		}

		// Add the script before our first embed figure, or at the end if there isn't one.
		$figure  = '/(<figure[^>]*class="[^"]*wp-embed-' . $network->value . '[^"]*"[^>]*>)/';
		$updated = preg_replace( $figure, $network->script() . '$1', $content, 1 );

		if ( null === $updated ) {
			return $original;
		}

		$content = $updated === $content ? $content . $network->script() : $updated;
	}

	return $content;
}

add_action( 'plugins_loaded', __NAMESPACE__ . '\updater' );
/**
 * Setup the updater.
 *
 * composer require yahnis-elsts/plugin-update-checker
 *
 * @since 0.1.0
 *
 * @uses https://github.com/YahnisElsts/plugin-update-checker/
 *
 * @return void
 */
function updater(): void {
	// Setup the updater.
	$updater = PucFactory::buildUpdateChecker( 'https://github.com/maithemewp/mai-embed-fixer/', __FILE__, 'mai-embed-fixer' );

	// Maybe set github api token.
	if ( defined( 'MAI_GITHUB_API_TOKEN' ) ) {
		$updater->setAuthentication( MAI_GITHUB_API_TOKEN );
	}

	// Add icons for Dashboard > Updates screen.
	if ( function_exists( 'mai_get_updater_icons' ) && $icons = mai_get_updater_icons() ) {
		$updater->addResultFilter(
			function ( $info ) use ( $icons ) {
				$info->icons = $icons;
				return $info;
			}
		);
	}
}
