<?php

/*
 * Plugin Name: Dedicated Transients
 * Plugin URI: https://github.com/pcfreak30/dedicated-transients
 * Description: WordPress plugin to re-route transient storage to dedicated tables
 * Version: 0.1.0
 * Author: Derrick Hammer
 * Author URI: https://www.derrickhammer.com
 * License:  GPL3
 * */

require_once __DIR__ . '/constants.php';

register_activation_hook( __FILE__, 'dedicated_transients_activate' );
register_deactivation_hook( __FILE__, 'dedicated_transients_deactivate' );
register_uninstall_hook( __FILE__, 'dedicated_transients_uninstall' );

// Deactivate object cache is active
dedicated_transients_check_object_cache();


/**
 * Activate only if object cache is not active
 */
function dedicated_transients_activate() {
	if ( dedicated_transients_check_object_cache() ) {
		return;
	}
	$source     = __DIR__ . DIRECTORY_SEPARATOR . DEDICATED_TRANSIENTS_WPMU_DROPIN;
	$target     = WPMU_PLUGIN_DIR . DIRECTORY_SEPARATOR . DEDICATED_TRANSIENTS_WPMU_DROPIN;
	$filesystem = dedicated_transients_wp_filesystem();
	if ( ! $filesystem->is_dir( WPMU_PLUGIN_DIR ) ) {
		$filesystem->mkdir( WPMU_PLUGIN_DIR );
	}
	if ( ! ( $filesystem->is_file( $target ) && md5_file( $source ) === md5_file( $target ) ) ) {
		$filesystem->copy( $source, $target, true );
	}
	dedicated_transients_install_tables();
}

/**
 *
 */
function dedicated_transients_deactivate() {
	$source     = __DIR__ . DIRECTORY_SEPARATOR . DEDICATED_TRANSIENTS_WPMU_DROPIN;
	$target     = WPMU_PLUGIN_DIR . DIRECTORY_SEPARATOR . DEDICATED_TRANSIENTS_WPMU_DROPIN;
	$filesystem = dedicated_transients_wp_filesystem();
	if ( $filesystem->is_file( $target ) && md5_file( $source ) === md5_file( $target ) ) {
		$filesystem->delete( $target );
	}
}

/**
 *
 */
function dedicated_transients_uninstall() {
	global $wpdb;
	$wpdb->query( "DROP TABLE {$wpdb->base_prefix}" . DEDICATED_TRANSIENTS_TABLE . " IF EXISTS" );
	if ( is_multisite() ) {
		$wpdb->query( "DROP TABLE {$wpdb->base_prefix}" . DEDICATED_TRANSIENTS_WPMU_TABLE . " IF EXISTS" );
	}
}

/**
 * @return bool
 */
function dedicated_transients_check_object_cache() {
	if ( wp_using_ext_object_cache() ) {
		add_action( 'admin_notices', 'dedicated_transients_object_cache_error' );
		deactivate_plugins( __FILE__, false, true );

		return true;
	}

	return false;
}


/**
 *
 */
function dedicated_transients_object_cache_error() {
	$info = get_plugin_data( $this->plugin_file );
	_e( sprintf( '
	<div class="error notice">
		<p>Opps! %s can not be activate when Object Cache is in use! Object Cache is also considered superior than this plugin.</p>
	</div>', $info['Name'] ) );
}

/**
 * @return \WP_Filesystem_Base
 */
function dedicated_transients_wp_filesystem() {
	/** @var \WP_Filesystem_Base $wp_filesystem */
	global $wp_filesystem;
	if ( is_null( $wp_filesystem ) ) {
		require_once ABSPATH . '/wp-admin/includes/file.php';
		WP_Filesystem();
	}

	return $wp_filesystem;
}

/**
 *
 */
function dedicated_transients_install_tables() {
	/*
	 * Copied schema from ./wp-admin/includes/schema.php
	 */
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$charset_collate = $wpdb->get_charset_collate();
	dbDelta( "CREATE TABLE {$wpdb->base_prefix}" . DEDICATED_TRANSIENTS_TABLE . " (
  option_id bigint(20) unsigned NOT NULL auto_increment,
  option_name varchar(191) NOT NULL default '',
  option_value longtext NOT NULL,
  autoload varchar(20) NOT NULL default 'yes',
  PRIMARY KEY  (option_id),
  UNIQUE KEY option_name (option_name)
) $charset_collate;" );
	if ( is_multisite() ) {
		$max_index_length = 191;
		dbDelta( "CREATE TABLE {$wpdb->base_prefix}" . DEDICATED_TRANSIENTS_WPMU_TABLE . " (
  meta_id bigint(20) NOT NULL auto_increment,
  site_id bigint(20) NOT NULL default '0',
  meta_key varchar(255) default NULL,
  meta_value longtext,
  PRIMARY KEY  (meta_id),
  KEY meta_key (meta_key($max_index_length)),
  KEY site_id (site_id)
) $charset_collate;" );
	}
}