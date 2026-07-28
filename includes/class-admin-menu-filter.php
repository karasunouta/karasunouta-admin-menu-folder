<?php
/**
 * KuSubmenuFolder Admin Menu Filter Class
 *
 * @package KuSubmenuFolder
 */

namespace karasunouta\KuSubmenuFolder;

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
		// 他の全メニューが登録された最終段階でメニューを再構築
		add_action( 'admin_menu', array( $this, 'filter_admin_menu' ), 99999 );
	}

	/**
	 * WPサイドメニューの動的フィルタリング処理
	 */
	public function filter_admin_menu() {
		global $menu, $submenu, $pagenow, $plugin_page, $parent_file;

		if ( empty( $menu ) || ! is_array( $menu ) ) {
			return;
		}

		$options         = $this->main->get_options();
		$default_folder  = $options['sub_menues'][0] ?? array();
		$selected_menues = $default_folder['menues'] ?? array();

		// WPがデフォルトで「ku-submenu」配下に自動生成する同名サブメニューを削除
		if ( isset( $submenu['ku-submenu'] ) ) {
			unset( $submenu['ku-submenu'] );
		}
		$submenu['ku-submenu'] = array();

		if ( empty( $selected_menues ) ) {
			return;
		}

		$target_slugs = array_column( $selected_menues, 'menu_slug' );
		if ( empty( $target_slugs ) ) {
			return;
		}

		// 現在アクティブな画面・親ファイルを特定
		$current_slug = $plugin_page ?? ( $_GET['page'] ?? $pagenow ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		// 格納対象メニューのインデックスとメニュー元データを検索・抽出
		$items_to_move = array();

		foreach ( $menu as $index => $item ) {
			if ( empty( $item[2] ) ) {
				continue;
			}

			$menu_slug = $item[2];

			if ( in_array( $menu_slug, $target_slugs, true ) ) {
				// 自プラグイン自体のメニューや設定メニューの移動は防ぐ
				if ( 'ku-submenu' === $menu_slug || 'ku-submenu-folder' === $menu_slug || 'options-general.php' === $menu_slug ) {
					continue;
				}

				// アクティブ状態のチェック
				$is_active = $this->is_menu_active( $menu_slug, $current_slug, $parent_file );

				$items_to_move[ $menu_slug ] = array(
					'menu_index' => $index,
					'menu_data'  => $item,
					'is_active'  => $is_active,
				);
			}
		}

		// 設定画面の並び順に従って 「KU Submenu」 配下のサブメニューとして登録
		foreach ( $selected_menues as $config_item ) {
			$slug = $config_item['menu_slug'];

			if ( ! isset( $items_to_move[ $slug ] ) ) {
				continue;
			}

			$item_info = $items_to_move[ $slug ];
			$menu_data = $item_info['menu_data'];
			$is_active = $item_info['is_active'];

			$title      = $menu_data[0];
			$capability = $menu_data[1];
			$url        = $menu_data[2];

			// KU Submenu > サブメニュー項目として並行追加
			$submenu['ku-submenu'][] = array(
				$title,
				$capability,
				$url,
				$title,
			);

			// 非アクティブの場合のみ、元のルートメニューから削除（非表示化）
			// アクティブの場合は削除しないため、元のサイドメニューにも並行してそのまま表示される
			if ( ! $is_active ) {
				unset( $menu[ $item_info['menu_index'] ] );
			}
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
