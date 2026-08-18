<?php
/**
 * AdminMenuFolder Main Class
 *
 * @package KarasunoutaAdminMenuFolder
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
	public const OPTION_KEY = 'kamf_options';

	/**
	 * 管理メニューフォルダーの識別子
	 */
	public const FOLDER_ID = 'folder-default';

	/**
	 * 管理メニューフォルダーの親メニュースラグ
	 */
	public const FOLDER_MENU_SLUG = 'kamf-folder-default';

	/**
	 * 設定ページのスラグ
	 */
	public const SETTINGS_PAGE_SLUG = 'karasunouta-admin-menu-folder';

	/**
	 * 1フォルダーに格納できるメニュー項目数の上限
	 */
	public const MAX_ITEMS_PER_FOLDER = 99;

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
		$this->maybe_migrate_options();
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
	 * 設定ページのURLを取得
	 *
	 * @param array $args 追加のクエリ引数.
	 * @return string
	 */
	public function get_settings_url( array $args = array() ): string {
		$query = array_merge( array( 'page' => self::SETTINGS_PAGE_SLUG ), $args );
		return add_query_arg( $query, admin_url( 'options-general.php' ) );
	}

	/**
	 * プラグイン設定を取得
	 *
	 * @return array
	 */
	public function get_options(): array {
		$defaults = array(
			'version'               => KAMF_VERSION,
			'folder'                => $this->get_default_folder(),
			'show_admin_bar_link'   => false,
			'setting_link_position' => 'none',
		);

		$saved = $this->get_raw_options();

		$options = wp_parse_args( $saved, $defaults );

		$options['folder']                = $this->normalize_folder( is_array( $options['folder'] ) ? $options['folder'] : array() );
		$options['show_admin_bar_link']   = ! empty( $options['show_admin_bar_link'] );
		$options['setting_link_position'] = $this->normalize_setting_link_position( $options['setting_link_position'] );

		return $options;
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
	 * 管理メニューフォルダーの設定を取得
	 *
	 * @return array
	 */
	public function get_folder(): array {
		$options = $this->get_options();
		return $options['folder'];
	}

	/**
	 * 管理メニューフォルダーの初期設定を取得
	 *
	 * @return array
	 */
	public function get_default_folder(): array {
		return array(
			'id'     => self::FOLDER_ID,
			'menues' => array(),
		);
	}

	/**
	 * 管理メニューフォルダーの表示名を取得
	 *
	 * @param array $folder フォルダー設定配列.
	 * @return string
	 */
	public function get_folder_title( array $folder ): string {
		$default = __( 'Menu Folder', 'karasunouta-admin-menu-folder' );

		/**
		 * 管理メニューフォルダーの表示名をフィルタリング
		 *
		 * @param string $title  表示名.
		 * @param array  $folder フォルダー設定配列.
		 */
		$title = apply_filters( 'kamf_folder_title', $default, $folder );
		$title = trim( wp_strip_all_tags( (string) $title ) );

		return '' !== $title ? $title : $default;
	}

	/**
	 * 管理メニューフォルダーのアイコン指定を取得
	 *
	 * @param array $folder フォルダー設定配列.
	 * @return string
	 */
	public function get_folder_icon( array $folder ): string {
		$default = 'dashicons-category';

		/**
		 * 管理メニューフォルダーのアイコン指定をフィルタリング
		 *
		 * @param string $icon   アイコンクラスまたは画像URI.
		 * @param array  $folder フォルダー設定配列.
		 */
		$icon = apply_filters( 'kamf_folder_icon', $default, $folder );
		$icon = is_string( $icon ) ? trim( $icon ) : '';

		return '' !== $icon ? $icon : $default;
	}

	/**
	 * 管理メニュー上のフォルダーノード一覧を表示順に取得
	 *
	 * @return array[] id / slug / title / icon / primary / menues を持つ配列のリスト.
	 */
	public function get_folder_nodes(): array {
		$folder = $this->get_folder();

		$nodes = array(
			array(
				'id'      => $folder['id'],
				'slug'    => self::FOLDER_MENU_SLUG,
				'title'   => $this->get_folder_title( $folder ),
				'icon'    => $this->get_folder_icon( $folder ),
				'primary' => true,
				'menues'  => $folder['menues'],
			),
		);

		/**
		 * 管理メニュー上のフォルダーノード一覧を表示順にフィルタリング
		 *
		 * @param array[] $nodes id / slug / title / icon / primary / menues を持つ配列のリスト.
		 */
		$nodes = apply_filters( 'kamf_folder_nodes', $nodes );

		$normalized = array();
		foreach ( (array) $nodes as $node ) {
			if ( ! is_array( $node ) || empty( $node['slug'] ) ) {
				continue;
			}
			$normalized[] = array(
				'id'      => (string) ( $node['id'] ?? '' ),
				'slug'    => (string) $node['slug'],
				'title'   => (string) ( $node['title'] ?? '' ),
				'icon'    => ! empty( $node['icon'] ) ? (string) $node['icon'] : 'dashicons-category',
				'primary' => ! empty( $node['primary'] ),
				'menues'  => isset( $node['menues'] ) && is_array( $node['menues'] ) ? array_values( $node['menues'] ) : array(),
			);
		}

		return $normalized;
	}

	/**
	 * すべてのフォルダーに格納されているメニュー項目を取得
	 *
	 * @return array
	 */
	public function get_all_stored_items(): array {
		$items = array();

		foreach ( $this->get_folder_nodes() as $node ) {
			foreach ( $node['menues'] as $item ) {
				if ( ! empty( $item['menu_slug'] ) ) {
					$items[ $item['menu_slug'] ] = $item;
				}
			}
		}

		return array_values( $items );
	}

	/**
	 * 管理メニュー上のフォルダー親メニュースラグ一覧を表示順に取得
	 *
	 * @return string[]
	 */
	public function get_folder_menu_slugs(): array {
		$slugs = array();
		foreach ( $this->get_folder_nodes() as $node ) {
			$slugs[] = $node['slug'];
		}
		return array_values( array_unique( $slugs ) );
	}

	/**
	 * フォルダーへの格納を禁止する管理メニュースラグ一覧を取得
	 * （フォルダー自身の入れ子や設定ページの巻き込みを防ぐため）
	 *
	 * @return string[]
	 */
	public function get_protected_slugs(): array {
		$protected = array(
			self::SETTINGS_PAGE_SLUG,
			self::FOLDER_ID,
			self::FOLDER_MENU_SLUG,
			'options-general.php',
		);

		foreach ( $this->get_folder_menu_slugs() as $slug ) {
			$protected[] = $slug;
		}

		/**
		 * フォルダーへの格納を禁止する管理メニュースラグをフィルタリング
		 *
		 * @param string[] $protected 保護対象スラグ配列.
		 */
		$protected = apply_filters( 'kamf_protected_slugs', $protected );

		$protected = array_map( 'strval', (array) $protected );

		return array_values( array_unique( array_filter( $protected ) ) );
	}

	/**
	 * フォルダー設定配列を正規化
	 *
	 * @param array $folder フォルダー設定配列.
	 * @return array
	 */
	public function normalize_folder( array $folder ): array {
		$id    = isset( $folder['id'] ) ? sanitize_key( (string) $folder['id'] ) : '';
		$items = isset( $folder['menues'] ) && is_array( $folder['menues'] ) ? $folder['menues'] : array();

		// 通常版の既定動作として、格納項目を元のメニュー位置順（昇順）に整列
		$sorted_items = $this->sort_menu_items_by_original_position( $items );

		/**
		 * 管理メニューフォルダーに格納されたメニュー項目一覧をフィルタリング
		 *
		 * @param array $items  ソート済みのメニュー項目配列.
		 * @param array $folder フォルダー設定配列.
		 */
		$filtered_items = apply_filters( 'kamf_folder_items', $sorted_items, $folder );

		return array(
			'id'     => '' !== $id ? $id : self::FOLDER_ID,
			'menues' => is_array( $filtered_items ) ? array_values( $filtered_items ) : $sorted_items,
		);
	}

	/**
	 * メニュー項目一覧を元のWordPressメニュー位置 (original_position) の昇順に整列
	 *
	 * @param array $items メニュー項目配列のリスト.
	 * @return array
	 */
	public function sort_menu_items_by_original_position( array $items ): array {
		usort(
			$items,
			function ( $a, $b ) {
				$pos_a = (float) ( $a['data']['original_position'] ?? ( $a['position'] ?? 999.0 ) );
				$pos_b = (float) ( $b['data']['original_position'] ?? ( $b['position'] ?? 999.0 ) );
				return $pos_a <=> $pos_b;
			}
		);
		return array_values( $items );
	}

	/**
	 * 受信したフォルダーデータをサニタイズ
	 *
	 * @param array $raw             受信したフォルダーデータ.
	 * @param array $protected_slugs 保護対象スラグ配列.
	 * @return array
	 */
	public function sanitize_folder( array $raw, array $protected_slugs ): array {
		$id         = isset( $raw['id'] ) ? sanitize_key( (string) $raw['id'] ) : '';
		$raw_items  = isset( $raw['menues'] ) && is_array( $raw['menues'] ) ? $raw['menues'] : array();

		return array(
			'id'     => '' !== $id ? $id : self::FOLDER_ID,
			'menues' => $this->sanitize_menu_items( $raw_items, $protected_slugs ),
		);
	}

	/**
	 * 受信したメニュー項目データをサニタイズ
	 *
	 * @param array $raw_items       受信したメニュー項目データの配列.
	 * @param array $protected_slugs 保護対象スラグ配列.
	 * @return array
	 */
	public function sanitize_menu_items( array $raw_items, array $protected_slugs ): array {
		$items = array();
		$seen  = array();

		foreach ( $raw_items as $raw ) {
			if ( count( $items ) >= self::MAX_ITEMS_PER_FOLDER ) {
				break;
			}

			if ( ! is_array( $raw ) ) {
				continue;
			}

			$slug = sanitize_text_field( (string) ( $raw['menu_slug'] ?? $raw['slug'] ?? '' ) );

			if ( '' === $slug || in_array( $slug, $protected_slugs, true ) || isset( $seen[ $slug ] ) ) {
				continue;
			}

			$seen[ $slug ] = true;

			$raw_data = isset( $raw['data'] ) && is_array( $raw['data'] ) ? $raw['data'] : array();

			$items[] = array(
				'menu_slug' => $slug,
				'title'     => sanitize_text_field( (string) ( $raw['title'] ?? '' ) ),
				'order'     => count( $items ),
				'data'      => array(
					'url'               => sanitize_text_field( (string) ( $raw_data['url'] ?? $raw['url'] ?? '' ) ),
					'original_position' => (float) ( $raw_data['original_position'] ?? $raw['position'] ?? 999.0 ),
					'icon_class'        => sanitize_text_field( (string) ( $raw_data['icon_class'] ?? $raw['icon_class'] ?? '' ) ),
				),
			);
		}

		return $items;
	}

	/**
	 * 設定リンクの挿入位置指定を正規化
	 *
	 * @param mixed $position 挿入位置指定.
	 * @return string
	 */
	public function normalize_setting_link_position( $position ): string {
		$position = is_string( $position ) ? $position : '';
		return in_array( $position, array( 'none', 'first', 'last' ), true ) ? $position : 'none';
	}

	/**
	 * 旧バージョンの設定構造を現行構造へ移行
	 */
	private function maybe_migrate_options() {
		$saved = get_option( self::OPTION_KEY, null );

		if ( ! is_array( $saved ) || ! array_key_exists( 'menu_folders', $saved ) ) {
			return;
		}

		$legacy = is_array( $saved['menu_folders'] ) ? $saved['menu_folders'] : array();
		$first  = isset( $legacy[0] ) && is_array( $legacy[0] ) ? $legacy[0] : array();
		$items  = isset( $first['menues'] ) && is_array( $first['menues'] ) ? $first['menues'] : array();

		// 旧構造では読み出し時に整列していたため、移行時に一度だけ元のメニュー位置順へ並べ替える
		usort(
			$items,
			function ( $a, $b ) {
				$pos_a = (float) ( $a['data']['original_position'] ?? $a['position'] ?? 999.0 );
				$pos_b = (float) ( $b['data']['original_position'] ?? $b['position'] ?? 999.0 );
				return $pos_a <=> $pos_b;
			}
		);

		unset( $saved['menu_folders'] );

		$saved['version'] = KAMF_VERSION;
		$saved['folder']  = array(
			'id'     => self::FOLDER_ID,
			'menues' => array_values( $items ),
		);

		update_option( self::OPTION_KEY, $saved );
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
				'id'    => 'kamf-admin-bar-link',
				'title' => sprintf(
					'<span class="ab-icon dashicons dashicons-category" style="top:2px;"></span><span class="ab-label">%s</span>',
					esc_html__( 'Admin Menu Folder', 'karasunouta-admin-menu-folder' )
				),
				'href'  => $this->get_settings_url(),
			)
		);
	}
}
