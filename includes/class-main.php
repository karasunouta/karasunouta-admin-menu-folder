<?php
/**
 * AdminMenuFolder Main Class
 *
 * @package AdminMenuFolder
 */

namespace karasunouta\AdminMenuFolder;

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
	public const OPTION_KEY = 'admin_menu_folder_options';

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
		add_action( 'admin_bar_menu', array( $this, 'add_admin_bar_link' ), 90 );
	}

	/**
	 * 各コンポーネントを初期化
	 */
	private function init_components() {
		$this->settings_page = new Settings_Page( $this );
		$this->menu_filter   = new Menu_Filter( $this );
	}

	/**
	 * 作成可能な最大サブメニューフォルダー数を取得（フィルターフック対応）
	 *
	 * @return int
	 */
	public function get_max_folders(): int {
		/**
		 * 作成可能な最大サブメニューフォルダー数をフィルタリング
		 *
		 * @param int $max_folders 通常版デフォルトは1
		 */
		return (int) apply_filters( 'admin_menu_folder_max_folders', 1 );
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
		 * @param int $max_items デフォルトは99件
		 */
		$default_max = 99;
		return (int) apply_filters( 'admin_menu_folder_max_items', $default_max );
	}

	/**
	 * 保護対象となるすべてのSub Menuフォルダスラグ一覧を取得
	 * （自己ネストや相互フォルダ格納を防ぐため）
	 *
	 * @return array
	 */
	public function get_protected_slugs(): array {
		$options   = $this->get_options();
		$protected = array( 'admin-menu-folder' );

		if ( ! empty( $options['menu_folders'] ) && is_array( $options['menu_folders'] ) ) {
			foreach ( $options['menu_folders'] as $folder ) {
				$id   = $folder['id'] ?? '';
				$slug = $folder['slug'] ?? $id;

				if ( ! empty( $slug ) ) {
					$protected[] = $slug;
					$protected[] = 'admin-menu-folder-' . $slug;
					$protected[] = 'admin-menu-folder-' . $id;
				}
			}
		}

		return array_unique( array_filter( $protected ) );
	}

	/**
	 * プラグイン設定を取得
	 *
	 * @return array
	 */
	public function get_options(): array {
		$defaults = array(
			'version'             => ADMIN_MENU_FOLDER_VERSION,
			'show_admin_bar_link' => false,
			'menu_folders'        => array(
				array(
					// ※初期IDを動的タイムスタンプにすると、DB未保存の初期状態でリクエストごとにID・URLが変化してしまうため、
					// デフォルト値として固定識別子 'folder_default' を定義（プログラム上は他のPro版追加IDと完全に対等に扱われます）。
					'id'       => 'folder_default',
					'title'    => 'Menu Folder',
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

		// 許容最大フォルダー数 (get_max_folders()) を超えるフォルダーは切り詰め
		$max_folders = $this->get_max_folders();
		if ( ! empty( $options['menu_folders'] ) && is_array( $options['menu_folders'] ) ) {
			$options['menu_folders'] = array_slice( $options['menu_folders'], 0, $max_folders );
		}

		// デフォルトでは第1フォルダーのタイトルを標準名 'Menu Folder' にセット
		if ( ! empty( $options['menu_folders'][0] ) ) {
			$options['menu_folders'][0]['title'] = 'Menu Folder';
		}

		// フォルダー内のメニュー項目を元のWPメニュー位置 (original_position) の昇順でソート
		if ( ! empty( $options['menu_folders'] ) && is_array( $options['menu_folders'] ) ) {
			foreach ( $options['menu_folders'] as &$folder ) {
				if ( ! empty( $folder['menues'] ) && is_array( $folder['menues'] ) ) {
					usort(
						$folder['menues'],
						function ( $a, $b ) {
							$pos_a = (float) ( $a['data']['original_position'] ?? ( $a['position'] ?? 999.0 ) );
							$pos_b = (float) ( $b['data']['original_position'] ?? ( $b['position'] ?? 999.0 ) );
							return $pos_a <=> $pos_b;
						}
					);
					$folder['menues'] = array_values( $folder['menues'] );
				}
			}
			unset( $folder );
		}

		/**
		 * 設定データをフィルタリング
		 *
		 * @param array $options 設定配列.
		 * @param array $saved DBから直接読み込んだ生の保存設定.
		 */
		return apply_filters( 'admin_menu_folder_get_options', $options, $saved );
	}

	/**
	 * 生のDB保存オプションを取得
	 *
	 * @return array
	 */
	public function get_raw_options(): array {
		$saved = get_option( self::OPTION_KEY, array() );
		return is_array( $saved ) ? $saved : array();
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

	/**
	 * プラグイン設定を初期状態に削除・復元
	 *
	 * @return bool
	 */
	public function reset_options(): bool {
		return delete_option( self::OPTION_KEY );
	}

	/**
	 * 管理バーに設定ページへのリンクを追加
	 *
	 * @param \WP_Admin_Bar $wp_admin_bar Admin bar object.
	 */
	public function add_admin_bar_link( $wp_admin_bar ) {
		$options = $this->get_options();
		if ( empty( $options['show_admin_bar_link'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$wp_admin_bar->add_node(
			array(
				'id'    => 'admin-menu-folder',
				'title' => sprintf(
					'<span class="ab-icon dashicons dashicons-category" style="top:2px;"></span><span class="ab-label">%s</span>',
					esc_html__( 'Admin Menu Folder', 'admin-menu-folder' )
				),
				'href'  => admin_url( 'options-general.php?page=admin-menu-folder' ),
			)
		);
	}
}
