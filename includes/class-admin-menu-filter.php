<?php
/**
 * AdminMenuFolder Admin Menu Filter Class
 *
 * @package KarasunoutaAdminMenuFolder
 */

namespace karasunouta\AdminMenuFolder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin Menu Filter Class
 */
class Menu_Filter {

	/**
	 * Main instance
	 *
	 * @var Main
	 */
	private $main;

	/**
	 * コンストラクタ
	 *
	 * @param Main $main Main instance.
	 */
	public function __construct( Main $main ) {
		$this->main = $main;
		// 他の全メニューが登録された最終段階（最大優先度）でメニューを再構築
		add_action( 'admin_menu', array( $this, 'filter_admin_menu' ), PHP_INT_MAX );
	}

	/**
	 * WPサイドメニューの動的フィルタリング処理
	 */
	public function filter_admin_menu() {
		global $menu;

		if ( empty( $menu ) || ! is_array( $menu ) ) {
			return;
		}

		$folder = $this->main->get_folder();
		$slugs  = wp_list_pluck( $folder['menues'], 'menu_slug' );

		$this->apply_folder( Main::FOLDER_MENU_SLUG, $folder, $this->collect_items_to_move( $slugs ) );

		// フォルダーの親ノードをユーザーの設定順に従い、隙間のない連続キーで $menu の最末尾へ配置
		$this->reposition_folders_to_bottom( $this->main->get_folder_menu_slugs() );

		/**
		 * 管理メニューの再構築が完了した直後に発火
		 */
		do_action( 'kamf_after_filter_admin_menu' );
	}

	/**
	 * 指定スラグのルートメニュー項目を、元データとアクティブ状態つきで収集
	 *
	 * @param array $target_slugs 収集対象のルートメニュースラグ配列.
	 * @return array スラグをキーとした収集結果.
	 */
	public function collect_items_to_move( array $target_slugs ): array {
		global $menu, $pagenow, $plugin_page, $parent_file;

		if ( empty( $menu ) || ! is_array( $menu ) || empty( $target_slugs ) ) {
			return array();
		}

		// 現在表示中の画面を特定（$plugin_page は wp-admin/admin.php が $_GET['page'] から設定する）
		$current_slug   = ! empty( $plugin_page ) ? (string) $plugin_page : (string) $pagenow;
		$protected      = $this->main->get_protected_slugs();
		$items_to_move  = array();

		foreach ( $menu as $index => $menu_item ) {
			if ( empty( $menu_item[2] ) ) {
				continue;
			}

			$menu_slug = $menu_item[2];

			if ( ! in_array( $menu_slug, $target_slugs, true ) ) {
				continue;
			}

			// 自身の設定ページやフォルダー自体の移動は防ぐ
			if ( in_array( $menu_slug, $protected, true ) ) {
				continue;
			}

			$items_to_move[ $menu_slug ] = array(
				'menu_index' => $index,
				'menu_data'  => $menu_item,
				'is_active'  => $this->is_menu_active( $menu_slug, $current_slug, $parent_file ),
			);
		}

		return $items_to_move;
	}

	/**
	 * 1フォルダー分のサブメニュー構造を構築
	 *
	 * 格納できる有効な項目が1件も無い場合は、フォルダーの親ノードごと破棄する。
	 *
	 * @param string $parent_slug   フォルダーの親メニュースラグ.
	 * @param array  $folder        フォルダー設定配列.
	 * @param array  $items_to_move collect_items_to_move() の収集結果.
	 * @return bool 有効な項目が1件以上あり、フォルダーが表示される場合に true.
	 */
	public function apply_folder( string $parent_slug, array $folder, array $items_to_move ): bool {
		global $menu, $submenu;

		if ( '' === $parent_slug ) {
			return false;
		}

		// サブメニュー構造の初期化
		unset( $submenu[ $parent_slug ] );
		$submenu[ $parent_slug ] = array();

		$folder_items     = isset( $folder['menues'] ) && is_array( $folder['menues'] ) ? $folder['menues'] : array();
		$valid_item_count = 0;

		foreach ( $folder_items as $config_item ) {
			$slug = $config_item['menu_slug'] ?? '';

			if ( '' === $slug || ! isset( $items_to_move[ $slug ] ) ) {
				continue;
			}

			$item_info = $items_to_move[ $slug ];
			$menu_data = $item_info['menu_data'];

			$title      = $menu_data[0];
			$capability = $menu_data[1];
			$url        = $menu_data[2];

			$submenu[ $parent_slug ][] = array( $title, $capability, $url, $title );
			$valid_item_count++;

			// 現在閲覧中でない場合のみ元のルートメニューから削除（非表示化）
			if ( empty( $item_info['is_active'] ) && isset( $menu[ $item_info['menu_index'] ] ) ) {
				unset( $menu[ $item_info['menu_index'] ] );
			}
		}

		if ( 0 === $valid_item_count ) {
			unset( $submenu[ $parent_slug ] );
			$this->remove_menu_node( $parent_slug );
			return false;
		}

		$this->add_settings_link( $parent_slug );

		return true;
	}

	/**
	 * フォルダー内に設定ページへのリンクを追加
	 *
	 * @param string $parent_slug フォルダーの親メニュースラグ.
	 */
	private function add_settings_link( string $parent_slug ) {
		global $submenu;

		$options  = $this->main->get_options();
		$position = $options['setting_link_position'];

		if ( 'none' === $position ) {
			return;
		}

		$link = array(
			__( 'Menu Folder Settings', 'karasunouta-admin-menu-folder' ),
			'manage_options',
			'options-general.php?page=' . Main::SETTINGS_PAGE_SLUG,
			__( 'Menu Folder Settings', 'karasunouta-admin-menu-folder' ),
		);

		if ( 'first' === $position ) {
			array_unshift( $submenu[ $parent_slug ], $link );
			return;
		}

		$submenu[ $parent_slug ][] = $link;
	}

	/**
	 * ルートメニューから指定スラグのノードを削除
	 *
	 * @param string $slug 削除対象のルートメニュースラグ.
	 */
	private function remove_menu_node( string $slug ) {
		global $menu;

		foreach ( $menu as $key => $menu_item ) {
			if ( isset( $menu_item[2] ) && $menu_item[2] === $slug ) {
				unset( $menu[ $key ] );
				break;
			}
		}
	}

	/**
	 * フォルダーの親ノードを指定順（0, 1, 2...）に従い、隙間のない連続キーで最末尾へ配置
	 *
	 * @param array $ordered_parent_slugs フォルダーの親メニュースラグ配列（表示順）.
	 */
	public function reposition_folders_to_bottom( array $ordered_parent_slugs ) {
		global $menu;

		if ( empty( $menu ) || ! is_array( $menu ) || empty( $ordered_parent_slugs ) ) {
			return;
		}

		// 1. $menu から全フォルダーノードを抽出・保持し、元の位置からは全ノードを確実に削除
		$extracted = array();
		foreach ( $menu as $key => $menu_item ) {
			if ( isset( $menu_item[2] ) && in_array( $menu_item[2], $ordered_parent_slugs, true ) ) {
				// 後勝ちで最新の指定ノードを保持し、重複キーはすべて消去
				$extracted[ $menu_item[2] ] = $menu_item;
				unset( $menu[ $key ] );
			}
		}

		if ( empty( $extracted ) ) {
			return;
		}

		// 2. 既存キーの最大値を算出（数値型・文字列数値型の両方に対応）
		$max_existing = 0.0;
		foreach ( array_keys( $menu ) as $key ) {
			if ( is_numeric( $key ) && (float) $key > $max_existing ) {
				$max_existing = (float) $key;
			}
		}

		// 確実に全メニューの最末尾より後ろの位置を算出（最小 999000）
		$base_position = (int) max( 999000, ceil( $max_existing ) + 100 );

		// 3. 指定順のまま、隙間のない連続キー（$base_position + 0, + 1, + 2...）で最末尾へ一括配置
		$offset = 0;
		foreach ( $ordered_parent_slugs as $slug ) {
			if ( ! isset( $extracted[ $slug ] ) ) {
				continue;
			}
			$menu[ (string) ( $base_position + $offset ) ] = $extracted[ $slug ];
			$offset++;
		}
	}

	/**
	 * 対象メニュー（またはその子メニュー）が現在閲覧中（アクティブ）か判定
	 *
	 * @param string      $target_slug 対象ルートメニューのスラグ.
	 * @param string      $current_slug 現在表示中のページスラグ/ファイル.
	 * @param string|null $parent_file WPが判定した親ファイル.
	 * @return bool
	 */
	private function is_menu_active( string $target_slug, string $current_slug, ?string $parent_file ): bool {
		global $submenu;

		// 直接ターゲットスラグと現在ページが一致
		if ( $target_slug === $current_slug ) {
			return true;
		}

		// $parent_file とターゲットが一致
		if ( ! empty( $parent_file ) && $parent_file === $target_slug ) {
			return true;
		}

		// 子メニュー項目を探索し、現在ページが含まれているかチェック
		if ( isset( $submenu[ $target_slug ] ) && is_array( $submenu[ $target_slug ] ) ) {
			foreach ( $submenu[ $target_slug ] as $sub_item ) {
				if ( ! empty( $sub_item[2] ) && $sub_item[2] === $current_slug ) {
					return true;
				}
			}
		}

		return false;
	}
}
