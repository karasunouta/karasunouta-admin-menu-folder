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
	 * Pro版が有効か判定（フィルターフック対応）
	 *
	 * @return bool
	 */
	public function is_pro(): bool {
		/**
		 * Pro版の有効状態をフィルタリング
		 *
		 * @param bool $is_pro デフォルトは false
		 */
		return (bool) apply_filters( 'ku_submenu_folder_is_pro', false );
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
		return (int) apply_filters( 'ku_submenu_folder_max_folders', 1 );
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
		return (int) apply_filters( 'ku_submenu_folder_max_items', $default_max );
	}

	/**
	 * 保護対象となるすべてのSub Menuフォルダスラグ一覧を取得
	 * （自己ネストや相互フォルダ格納を防ぐため）
	 *
	 * @return array
	 */
	public function get_protected_slugs(): array {
		$options   = $this->get_options();
		$protected = array( 'ku-submenu-folder', 'ku-submenu' );

		if ( ! empty( $options['sub_menues'] ) && is_array( $options['sub_menues'] ) ) {
			foreach ( $options['sub_menues'] as $folder ) {
				$id   = $folder['id'] ?? '';
				$slug = $folder['slug'] ?? $id;

				if ( ! empty( $slug ) ) {
					$protected[] = $slug;
					$protected[] = 'ku-submenu-' . $slug;
					if ( 'folder_default' !== $id ) {
						$protected[] = 'ku-submenu-' . $id;
					}
				}
			}
		}

		return array_unique( array_filter( $protected ) );
	}

	/**
	 * プラグイン設定を取得（データ構造の標準化およびPro停止時の自動フォールバックを保証）
	 *
	 * @return array
	 */
	public function get_options(): array {
		$defaults = array(
			'version'             => KUSF_VERSION,
			'show_admin_bar_link' => false,
			'sub_menues'          => array(
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

		// sub_menues が空の場合の保護
		if ( empty( $options['sub_menues'] ) || ! is_array( $options['sub_menues'] ) ) {
			$options['sub_menues'] = $defaults['sub_menues'];
		}

		// Pro版が無効な場合の自動フォールバックルール（タイトルのKU Submenu復元・アイコン復元・1フォルダー・最大件数マスク・元位置順への自動仮ソート）
		if ( ! $this->is_pro() ) {
			$first_folder = $options['sub_menues'][0] ?? $defaults['sub_menues'][0];

			// フォルダー名とアイコンを通常版デフォルトに復元
			$first_folder['title'] = 'KU Submenu';
			$first_folder['icon']  = 'dashicons-category';

			if ( ! empty( $first_folder['menues'] ) && is_array( $first_folder['menues'] ) ) {
				$items = array_slice( $first_folder['menues'], 0, $this->get_max_items() );

				// 通常版動作時のみ、DBデータは変更せず動的に original_position (WP元位置) の昇順で仮ソートして表示
				usort(
					$items,
					function ( $a, $b ) {
						$pos_a = (float) ( $a['data']['original_position'] ?? ( $a['position'] ?? 999.0 ) );
						$pos_b = (float) ( $b['data']['original_position'] ?? ( $b['position'] ?? 999.0 ) );
						return $pos_a <=> $pos_b;
					}
				);

				$first_folder['menues'] = array_values( $items );
			} else {
				$first_folder['menues'] = array();
			}

			$options['sub_menues'] = array( $first_folder );
		}

		return $options;
	}

	/**
	 * 生のDB保存オプションを取得（Pro無効時のデデュープ・マージ処理用）
	 *
	 * @return array
	 */
	public function get_raw_options(): array {
		$defaults = array(
			'version'             => KUSF_VERSION,
			'show_admin_bar_link' => false,
			'sub_menues'          => array(
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

		return wp_parse_args( $saved, $defaults );
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
				'id'    => 'ku-submenu-folder',
				'title' => sprintf(
					'<span class="ab-icon dashicons dashicons-category" style="top:2px;"></span><span class="ab-label">%s</span>',
					esc_html__( 'KU Submenu Folder', 'ku-submenu-folder' )
				),
				'href'  => admin_url( 'options-general.php?page=ku-submenu-folder' ),
			)
		);
	}
}
