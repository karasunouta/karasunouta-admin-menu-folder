<?php
/**
 * Plugin Name:       KU Submenu Folder
 * Plugin URI:        https://example.com/ku-submenu-folder
 * Description:       WPサイドメニューの各種メニューをフォルダ構造（Sub Menu）内に整理・収納するプラグイン。
 * Version:           1.0.2
 * Requires at least: 6.4
 * Requires PHP:      8.0
 * Author:            karasunouta
 * Text Domain:       ku-submenu-folder
 * Domain Path:       /languages
 *
 * @package KuSubmenuFolder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Direct access denied.
}

// プラグイン定数
define( 'KUSF_VERSION', '1.0.2' );
define( 'KUSF_PLUGIN_FILE', __FILE__ );
define( 'KUSF_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'KUSF_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// 必要なクラスファイルの読み込み
require_once KUSF_PLUGIN_DIR . 'includes/class-ku-submenu-folder.php';
require_once KUSF_PLUGIN_DIR . 'includes/class-admin-settings.php';
require_once KUSF_PLUGIN_DIR . 'includes/class-admin-menu-filter.php';

/**
 * プラグインのメインインスタンスを起動
 */
function kusf_init() {
	\karasunouta\KuSubmenuFolder\Main::get_instance();
}
add_action( 'plugins_loaded', 'kusf_init' );

/**
 * プラグイン一覧画面の「設定」リンクを追加
 *
 * @param array $links 既存のリンク配列.
 * @return array
 */
function kusf_plugin_action_links( $links ) {
	$settings_link = sprintf(
		'<a href="%s">%s</a>',
		esc_url( admin_url( 'options-general.php?page=ku-submenu-folder' ) ),
		esc_html__( '設定', 'ku-submenu-folder' )
	);
	array_unshift( $links, $settings_link );
	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'kusf_plugin_action_links' );
