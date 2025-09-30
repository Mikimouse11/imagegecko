<?php
/**
 * Uninstall script for ImageGecko AI Photos
 *
 * This file is executed when the plugin is deleted via the WordPress admin.
 * It removes all plugin data from the database.
 *
 * @package ImageGecko
 */

// Exit if accessed directly or not from WordPress uninstall process.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Clean up plugin options.
 */
function imagegecko_uninstall_options() {
	// Remove plugin settings.
	delete_option( 'imagegecko_settings' );
	delete_option( 'imagegecko_api_key' );

	// For multisite installations, remove options from all sites.
	if ( is_multisite() ) {
		$sites = get_sites( array( 'number' => 0 ) );

		foreach ( $sites as $site ) {
			switch_to_blog( $site->blog_id );
			delete_option( 'imagegecko_settings' );
			delete_option( 'imagegecko_api_key' );
			restore_current_blog();
		}
	}
}

/**
 * Clean up product metadata.
 */
function imagegecko_uninstall_metadata() {
	// Remove all ImageGecko metadata from products.
	$meta_keys = array(
		'_imagegecko_status',
		'_imagegecko_status_message',
		'_imagegecko_generated_attachment',
		'_imagegecko_generated_at',
	);

	foreach ( $meta_keys as $meta_key ) {
		delete_metadata( 'post', 0, $meta_key, '', true );
	}

	// Remove metadata from attachments.
	$attachment_meta_keys = array(
		'_imagegecko_generated',
		'_imagegecko_product_id',
		'_imagegecko_generated_date',
		'_imagegecko_source_product',
	);

	foreach ( $attachment_meta_keys as $meta_key ) {
		delete_metadata( 'post', 0, $meta_key, '', true );
	}
}

/**
 * Clean up user metadata (notices).
 */
function imagegecko_uninstall_user_meta() {
	delete_metadata( 'user', 0, 'imagegecko_admin_notice', '', true );
}

/**
 * Clean up scheduled events.
 */
function imagegecko_uninstall_scheduled_events() {
	// Remove any pending cron events.
	wp_clear_scheduled_hook( 'imagegecko_generate_product_image' );

	// If Action Scheduler is present, clean up any pending actions.
	if ( function_exists( 'as_unschedule_all_actions' ) ) {
		as_unschedule_all_actions( 'imagegecko/generate_product_image', array(), 'imagegecko' );
	}
}

// Execute uninstall procedures.
imagegecko_uninstall_options();
imagegecko_uninstall_metadata();
imagegecko_uninstall_user_meta();
imagegecko_uninstall_scheduled_events();

// Optional: Delete AI-generated images from Media Library
// Uncomment the following if you want to delete generated images on uninstall.
/*
function imagegecko_uninstall_generated_images() {
	$args = array(
		'post_type'      => 'attachment',
		'posts_per_page' => -1,
		'meta_query'     => array(
			array(
				'key'   => '_imagegecko_generated',
				'value' => '1',
			),
		),
	);

	$attachments = get_posts( $args );

	foreach ( $attachments as $attachment ) {
		wp_delete_attachment( $attachment->ID, true );
	}
}
imagegecko_uninstall_generated_images();
*/
