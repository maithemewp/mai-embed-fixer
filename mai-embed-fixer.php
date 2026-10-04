<?php

/**
 * Plugin Name:     Mai Embed Fixer
 * Plugin URI:      https://bizbudding.com/
 * Description:     Attempts to fix twitter/x and instagram embeds that aren't working in WordPress.
 * Version:         0.3.0
 * Requires PHP:    8.1
 *
 * Author:          BizBudding
 * Author URI:      https://bizbudding.com
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
		$host = preg_replace( '/^(www|mobile)\./', '', $host );

		return match ( $host ) {
			'instagram.com'        => self::Instagram,
			'twitter.com', 'x.com' => self::Twitter,
			default                => null,
		};
	}
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
 * @since 0.1.0
 * @since 0.3.0 Handles x.com urls.
 *
 * @param mixed $block_content The content of the block. Another filter may have broken it, so it isn't trusted to be a string.
 * @param array $block         The block data.
 *
 * @return mixed The content of the block.
 */
function convert_embeds( mixed $block_content, array $block ): mixed {
	$url     = (string) ( $block['attrs']['url'] ?? '' );
	$network = $url ? Network::from_url( $url ) : null;

	if ( ! $network ) {
		return $block_content;
	}

	return get_embed( $url, $network );
}

add_filter( 'do_shortcode_tag', __NAMESPACE__ . '\convert_embed_shortcode', 10, 2 );
/**
 * Convert the [embed] shortcode to the proper social media embed format.
 *
 * Classic content goes through the shortcode instead of the block, so it
 * needs the same swap. The url comes from the first link in the output.
 *
 * @since 0.2.0
 * @since 0.3.0 Returns the original output when the url isn't one we handle.
 *
 * @param mixed  $output The output from the shortcode. Any shortcode's callback lands here, and some return null or false.
 * @param string $tag    The name of the shortcode.
 *
 * @return mixed The modified output.
 */
function convert_embed_shortcode( mixed $output, string $tag ): mixed {
	if ( 'embed' !== $tag || ! is_string( $output ) ) {
		return $output;
	}

	$tags = new WP_HTML_Tag_Processor( $output );
	$url  = $tags->next_tag( [ 'tag_name' => 'a' ] ) ? (string) $tags->get_attribute( 'href' ) : '';

	$network = $url ? Network::from_url( $url ) : null;

	if ( ! $network ) {
		return $output;
	}

	return get_embed( $url, $network );
}

add_filter( 'the_content', __NAMESPACE__ . '\add_scripts', 30, 1 );
/**
 * Add each network's script to singular posts that have one of its embeds.
 *
 * Embeds pasted from the networks often bring their own script tag, so a
 * post can end up loading the same script several times. We strip those and
 * add one copy, before the first of our embeds, or at the end if there is
 * none of ours (e.g. only a custom HTML embed).
 *
 * @since 0.1.0
 *
 * @param mixed $content The content of the post. Another filter may have broken it, so it isn't trusted to be a string.
 *
 * @return mixed The content of the post.
 */
function add_scripts( mixed $content ): mixed {
	if ( ! is_string( $content ) || ! is_main_query() || ! in_the_loop() || ! is_singular() ) {
		return $content;
	}

	foreach ( Network::cases() as $network ) {
		if ( ! has_blockquote( $content, get_blockquote_class( $network ) ) ) {
			continue;
		}

		[ $src_pattern, $script ] = match ( $network ) {
			Network::Twitter   => [
				'platform\.twitter\.com\/widgets\.js',
				'<script async class="mai-twitter-script" src="https://platform.twitter.com/widgets.js" charset="utf-8"></script>',
			],
			Network::Instagram => [
				'www\.instagram\.com\/embed\.js',
				'<script async class="mai-instagram-script" src="//www.instagram.com/embed.js" charset="utf-8"></script>',
			],
		};

		// Remove every existing copy of the script. We add one back below.
		$content = preg_replace( '/<script[^>]*src="[^"]*' . $src_pattern . '[^"]*"[^>]*><\/script>/', '', $content );

		// Add the script before our first embed figure, or at the end if there isn't one.
		$figure  = '/(<figure[^>]*class="[^"]*wp-embed-' . $network->value . '[^"]*"[^>]*>)/';
		$updated = preg_replace( $figure, $script . '$1', $content, 1 );
		$content = $updated === $content ? $content . $script : $updated;
	}

	return $content;
}

/**
 * Check whether content has a blockquote with a given class.
 *
 * @since 0.3.0
 *
 * @param string $content    The content to check.
 * @param string $class_name The blockquote class to look for.
 *
 * @return bool
 */
function has_blockquote( string $content, string $class_name ): bool {
	$tags = new WP_HTML_Tag_Processor( $content );

	return $tags->next_tag( [ 'tag_name' => 'blockquote', 'class_name' => $class_name ] );
}

/**
 * Get the blockquote class each network's script looks for.
 *
 * @since 0.3.0
 *
 * @param Network $network The network.
 *
 * @return string
 */
function get_blockquote_class( Network $network ): string {
	return match ( $network ) {
		Network::Twitter   => 'twitter-tweet',
		Network::Instagram => 'instagram-media',
	};
}

/**
 * Get the embed markup for a url.
 *
 * @since 0.2.0
 * @since 0.3.0 Takes a Network instead of a string, and normalizes Instagram urls.
 *
 * @param string  $url     The url of the embed.
 * @param Network $network The network the url belongs to.
 *
 * @return string The embed.
 */
function get_embed( string $url, Network $network ): string {
	return match ( $network ) {
		Network::Instagram => sprintf(
			'<figure class="wp-embed-instagram" style="width:100%%;max-width:540px;"><blockquote class="instagram-media" style="width:100%%;" data-instgrm-captioned data-instgrm-permalink="%s" data-instgrm-version="14"></blockquote></figure>',
			esc_url( normalize_instagram_url( $url ) )
		),
		Network::Twitter   => sprintf(
			'<figure class="wp-embed-twitter" style="width:100%%;max-width:540px;"><blockquote class="twitter-tweet" style="width:100%%;" data-lang="en"><a href="%s"></a></blockquote></figure>',
			esc_url( $url )
		),
	};
}

/**
 * Normalize an Instagram url to the shape Instagram's embed.js can load.
 *
 * embed.js builds its iframe from the permalink plus `embed/captioned/`.
 * Instagram now shares links with the account name in the path, like
 * `instagram.com/{user}/p/{id}/`, and the embed page for that shape 404s,
 * redirects to the homepage, and is refused in an iframe. The same is true
 * of `/reels/{id}/`. Editors paste these links as-is, so we rewrite them to
 * `instagram.com/{p|reel|tv}/{id}/` and drop the query string.
 *
 * @since 0.3.0
 *
 * @param string $url The Instagram url.
 *
 * @return string The normalized url, or the original if it isn't a post, reel, or tv url.
 */
function normalize_instagram_url( string $url ): string {
	$path = (string) parse_url( $url, PHP_URL_PATH );

	if ( ! preg_match( '#^/(?:[^/]+/)?(p|reels?|tv)/([A-Za-z0-9_-]+)#', $path, $matches ) ) {
		return $url;
	}

	$type = 'reels' === $matches[1] ? 'reel' : $matches[1];

	return sprintf( 'https://www.instagram.com/%s/%s/', $type, $matches[2] );
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
