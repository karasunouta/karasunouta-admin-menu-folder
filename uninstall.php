<?php
/**
 * Uninstall KU Submenu Folder
 *
 * @package KuSubmenuFolder
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// プラグイン設定オプションの削除
delete_option( 'ku_submenu_folder_options' );
delete_site_option( 'ku_submenu_folder_options' );
