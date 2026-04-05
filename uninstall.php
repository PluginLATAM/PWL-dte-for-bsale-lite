<?php
// uninstall.php
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// If the companion edition is still installed, shared data must be preserved.
// WP_UNINSTALL_PLUGIN tells us which edition is being removed.
$pwl_dte_plugins_dir = dirname( untrailingslashit( plugin_dir_path( __FILE__ ) ) );
$pwl_dte_companion_rel = ( WP_UNINSTALL_PLUGIN === 'pwl-dte-for-bsale-pro/pwl-dte-for-bsale-pro.php' )
	? 'pwl-dte-for-bsale/pwl-dte-for-bsale.php'
	: 'pwl-dte-for-bsale-pro/pwl-dte-for-bsale-pro.php';
$pwl_dte_companion = $pwl_dte_plugins_dir . '/' . $pwl_dte_companion_rel;

if ( file_exists( $pwl_dte_companion ) ) {
    // Other edition is present — skip cleanup to avoid destroying shared settings and data.
    return;
}

// Delete all plugin options
$pwl_dte_options = [
    'pwl_dte_api_token',
    'pwl_dte_office_id',
    'pwl_dte_price_list_id',
    'pwl_dte_sandbox_mode',
    'pwl_dte_default_doc_type',
    'pwl_dte_auto_declare_sii',
    'pwl_dte_auto_send_email',
    'pwl_dte_auto_dispatch',
    'pwl_dte_enable_stock_sync',
    'pwl_dte_stock_sync_interval',
    'pwl_dte_enable_webhooks',
    'pwl_dte_webhook_secret',
    'pwl_dte_db_version',
    'pwl_dte_cpn_id',
    'pwl_dte_stock_office_id',
    'pwl_dte_office_map',
];

foreach ( $pwl_dte_options as $pwl_dte_option ) {
    delete_option( $pwl_dte_option );
}

// Drop custom tables
global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}pwl_dte_documents" );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}pwl_dte_webhook_events" );
