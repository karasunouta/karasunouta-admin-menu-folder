<?php
/**
 * KuSubmenuFolder Main Class
 *
 * @package KuSubmenuFolder
 */

namespace karasunouta\KuSubmenuFolder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main Singleton Class
 */
class Main {

	/**
	 * オプションキー名
	 */
	public const OPTION_KEY = 'ku_submenu_folder_options';

	/**
	 * シングルトンインスタンス
	 *
	 * @var Main|null
	 */
	private static $instance = null;

	/**
	 * 設定ページインスタンス
	 *
	 * @var Settings_Page|null
	 */
	public $settings_page = null;

	/**
	 * メニューフィルターインスタンス
	 *
	 * @var Menu_Filter|null
	 */
	public $menu_filter = null;

	/**
	 * インスタンスを取得
	 *
	 * @return Main
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * コンストラクタ
	 */
	private function __construct() {
		$this->init_components();
	}

	/**
	 * 各コンポーネントを初期化
	 */
	private function init_components() {
		$this->settings_page = new Settings_Page( $this );
		$this->menu_filter   = new Menu_Filter( $this );
	}

	/**
	 * 格納可能な最大メニュー数を取得（フィルターフック対応）
	 *
	 * @return int
	 */
	public function get_max_items(): int {
		/**
		 * WP Sub Menuに格納可能な最大メニュー数をフィルタリング
		 *
		 * @param int $max_items 通常版デフォルトは5件
		 */
		return (int) apply_filters( 'ku_submenu_folder_max_items', 5 );
	}

	/**
	 * プラグイン設定を取得（データ構造の標準化を保証）
	 *
	 * @return array
	 */
	public function get_options(): array {
		$defaults = array(
			'version'    => KUSF_VERSION,
			'sub_menues' => array(
				array(
					'id'       => 'folder_default',
					'title'    => 'KU Submenu',
					'icon'     => 'dashicons-category',
					'position' => 99,
					'menues'   => array(),
				),
			),
		);

		$saved = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		$options = wp_parse_args( $saved, $defaults );

		// sub_menues が空の場合の保護および旧タイトルの自動更新
		if ( empty( $options['sub_menues'] ) || ! is_array( $options['sub_menues'] ) ) {
			$options['sub_menues'] = $defaults['sub_menues'];
		} else {
			if ( isset( $options['sub_menues'][0]['title'] ) && ( 'WP Sub Menu' === $options['sub_menues'][0]['title'] || 'KU Submenu Folder' === $options['sub_menues'][0]['title'] ) ) {
				$options['sub_menues'][0]['title'] = 'KU Submenu';
			}
		}

		return $options;
	}

	/**
	 * プラグイン設定を保存
	 *
	 * @param array $options 保存する設定配列.
	 * @return bool
	 */
	public function save_options( array $options ): bool {
		return update_option( self::OPTION_KEY, $options );
	}
}
