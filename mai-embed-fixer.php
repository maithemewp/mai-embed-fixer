<?php

/**
 * Plugin Name:     Mai Embed Fixer
 * Plugin URI:      https://bizbudding.com/
 * Description:     Attempts to fix twitter/x and instagram embeds that aren't working in WordPress.
 * Version:         0.2.1
 *
 * Author:          BizBudding
 * Author URI:      https://bizbudding.com
 */

namespace Mai\EmbedFixer;

use WP_HTML_Tag_Processor;
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

// Load vendor files.
require_once __DIR__ . '/vendor/autoload.php';

add_filter( 'render_block_core/embed', __NAMESPACE__ . '\convert_embeds', 20, 2 );
/**
 * Convert embeds to their social media equivalents.
 *
 * @version 0.1.0
 *
 * @param string $block_content The content of the block.
 * @param array  $block         The block data.
 *
 * @return string The content of the block.
 */
function convert_embeds( $block_content, $block ) {
	$provider = $block['attrs']['provider'] ?? '';
	$url      = $block['attrs']['url'] ?? '';

	if ( ! $provider && $url ) {
		$replace = '';
		$host    = parse_url( $url, PHP_URL_HOST );
		$host    = str_replace( 'www.', '', $host );

		// Check for known hosts.
		switch ( $host ) {
			case 'instagram.com':
				$replace .= get_embed( $url, 'instagram' );
				break;
			case 'twitter.com':
				$replace .= get_embed( $url, 'twitter' );
				break;
			default:
				return $block_content;
		}

		// If we have a replacement, use it.
		if ( $replace ) {
			$block_content = $replace;
		}
	}

	return $block_content;
}

add_filter( 'do_shortcode_tag', __NAMESPACE__ . '\convert_embed_shortcode', 10, 2);
/**
 * Convert embed shortcode to the proper social media embed format.
 *
 * @since 0.2.0
 *
 * @param string $output The output from the shortcode.
 * @param string $tag    The name of the shortcode.
 *
 * @return string The modified output.
 */
function convert_embed_shortcode( $output, $tag ) {
	// Bail if not an embed shortcode.
	if ( 'embed' !== $tag ) {
		return $output;
	}

	// Set up tag processor.
	$tags = new WP_HTML_Tag_Processor( $output );

	// Loop through tags.
	while ( $tags->next_tag( [ 'tag_name' => 'a' ] ) ) {
		$url = $tags->get_attribute( 'href' );
		break;
	}

	// Bail if no url.
	if ( ! $url ) {
		return $output;
	}

	$replace = '';

	if ( str_contains( $output, 'instagram.com' ) ) {
		$replace .= get_embed( $url, 'instagram' );
	}

	if ( str_contains( $output, 'twitter.com' ) ) {
		$replace .= get_embed( $url, 'twitter' );
	}

	return $replace ?? $output;
}

add_filter( 'the_content', __NAMESPACE__ . '\add_scripts', 30, 1 );
/**
 * Add scripts to posts with social media embeds.
 *
 * @version 0.1.0
 *
 * @param string $content The content of the post.
 *
 * @return string The content of the post.
 */
function add_scripts( $content ) {
	// Bail if not on the main query or in the loop.
	if ( ! is_main_query() || ! in_the_loop() ) {
		return $content;
	}

	// Bail if not on a post.
	if ( ! is_singular() ) {
		return $content;
	}

	// Should run.
	$has_twitter   = false;
	$has_instagram = false;

	// Set up tag processor.
	$tags = new WP_HTML_Tag_Processor( $content );

	// Loop through tags.
	while ( $tags->next_tag( [ 'tag_name' => 'blockquote', 'class_name' => 'twitter-tweet' ] ) ) {
		$has_twitter = true;
		break;
	}

	// Set up tag processor.
	$tags = new WP_HTML_Tag_Processor( $content );

	// Loop through tags.
	while ( $tags->next_tag( [ 'tag_name' => 'blockquote', 'class_name' => 'instagram-media' ] ) ) {
		$has_instagram = true;
		break;
	}

	// Bail if this post doesn't have a twitter or instagram embed.
	if ( ! $has_twitter && ! $has_instagram ) {
		return $content;
	}

	// If we have a twitter embed.
	if ( $has_twitter ) {
		// Check if the content already has Twitter script tags.
		if ( str_contains( $content, 'https://platform.twitter.com/widgets.js' ) ) {
			// Remove all Twitter script tags except the first one.
			$content = preg_replace( '/<script[^>]*src="[^"]*platform\.twitter\.com\/widgets\.js[^"]*"[^>]*><\/script>/', '', $content, -1 );
		}

		// Try to find the first Twitter figure and add script before it.
		$twitter_script = '<script async class="mai-twitter-script" src="https://platform.twitter.com/widgets.js" charset="utf-8"></script>';
		$content_og     = $content;
		$content        = preg_replace( '/(<figure[^>]*class="[^"]*wp-embed-twitter[^"]*"[^>]*>)/', $twitter_script . '$1', $content, 1 );

		// Fallback: If regex replacement failed (content unchanged), add script at the end.
		if ( $content === $content_og ) {
			$content .= $twitter_script;
		}
	}

	// If we have an instagram embed.
	if ( $has_instagram ) {
		// Check if the content already has Instagram script tags.
		if ( str_contains( $content, '//www.instagram.com/embed.js' ) ) {
			// Remove all Instagram script tags except the first one.
			$content = preg_replace( '/<script[^>]*src="[^"]*www\.instagram\.com\/embed\.js[^"]*"[^>]*><\/script>/', '', $content, -1 );
		}

		// Try to find the first Instagram figure and add script before it.
		$instagram_script = '<script async class="mai-instagram-script" src="//www.instagram.com/embed.js" charset="utf-8"></script>';
		$content_og       = $content;
		$content          = preg_replace( '/(<figure[^>]*class="[^"]*wp-embed-instagram[^"]*"[^>]*>)/', $instagram_script . '$1', $content, 1 );

		// Fallback: If regex replacement failed (content unchanged), add script at the end.
		if ( $content === $content_og ) {
			$content .= $instagram_script;
		}
	}

	return $content;
}

/**
 * Get the embed for a given url and type.
 *
 * @since 0.2.0
 *
 * @param string $url The url of the embed.
 * @param string $type The type of embed.
 *
 * @return string The embed.
 */
function get_embed( $url, $type ) {
	$embed = '';

	switch ( $type ) {
		case 'instagram':
			$embed .= '<figure class="wp-embed-instagram" style="width:100%;max-width:540px;">';
			$embed .= sprintf( '<blockquote class="instagram-media" style="width:100%%;" data-instgrm-captioned data-instgrm-permalink="%s" data-instgrm-version="14"></blockquote>', esc_url( $url ) );
			$embed .= '</figure>';
			break;
		case 'twitter':
			$embed .= '<figure class="wp-embed-twitter" style="width:100%;max-width:540px;">';
			$embed .= sprintf( '<blockquote class="twitter-tweet" style="width:100%%;" data-lang="en"><a href="%s"></a></blockquote>', esc_url( $url ) );
			$embed .= '</figure>';
			break;
	}

	return $embed;
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
function updater() {
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