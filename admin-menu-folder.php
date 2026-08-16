<?php
/**
 * Plugin Name:       Admin Menu Folder
 * Description:       Organizes and stores various WP side menu items into folder structures (submenus).
 * Version:           1.3.0
 * Requires at least: 6.4
 * Requires PHP:      8.0
 * Author:            karasunouta
 * Author URI:        https://karasunouta.com
 * Text Domain:       admin-menu-folder
 *
 * @package AdminMenuFolder
 *
 * Copyright (c) 2026 karasunouta
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Direct access denied.
}

// プラグイン定数
define( 'ADMIN_MENU_FOLDER_VERSION', '1.3.0' );
define( 'ADMIN_MENU_FOLDER_PLUGIN_FILE', __FILE__ );
define( 'ADMIN_MENU_FOLDER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ADMIN_MENU_FOLDER_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// 必要なクラスファイルの読み込み
require_once ADMIN_MENU_FOLDER_PLUGIN_DIR . 'includes/class-main.php';
require_once ADMIN_MENU_FOLDER_PLUGIN_DIR . 'includes/class-admin-settings.php';
require_once ADMIN_MENU_FOLDER_PLUGIN_DIR . 'includes/class-admin-menu-filter.php';

/**
 * プラグインのメインインスタンスを起動および翻訳ファイルの読み込み
 */
function admin_menu_folder_init() {
	\karasunouta\AdminMenuFolder\Main::get_instance();
}
add_action( 'plugins_loaded', 'admin_menu_folder_init' );

/**
 * プラグイン一覧画面の「設定」リンクを追加
 *
 * @param array $links 既存のリンク配列.
 * @return array
 */
function admin_menu_folder_plugin_action_links( $links ) {
	$action_links = array(
		'settings' => sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'options-general.php?page=admin-menu-folder' ) ),
			esc_html__( 'Settings', 'admin-menu-folder' )
		),
	);
	return array_merge( $action_links, $links );
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'admin_menu_folder_plugin_action_links' );

