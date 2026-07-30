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

		$options    = $this->main->get_options();
		$sub_menues = $options['sub_menues'] ?? array();

		if ( empty( $sub_menues ) || ! is_array( $sub_menues ) ) {
			return;
		}

		// 現在アクティブな画面・親ファイルを特定
		$current_slug = $plugin_page ?? ( $_GET['page'] ?? $pagenow ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		// 全フォルダーの格納対象メニューと全ターゲットスラグを収集
		$all_target_slugs = array();
		foreach ( $sub_menues as $folder ) {
			if ( ! empty( $folder['menues'] ) && is_array( $folder['menues'] ) ) {
				foreach ( $folder['menues'] as $item ) {
					if ( ! empty( $item['menu_slug'] ) ) {
						$all_target_slugs[] = $item['menu_slug'];
					}
				}
			}
		}

		$all_target_slugs = array_unique( $all_target_slugs );

		// 元の $menu から対象項目の元データを取得
		$items_to_move = array();
		foreach ( $menu as $index => $item ) {
			if ( empty( $item[2] ) ) {
				continue;
			}

			$menu_slug = $item[2];

			if ( in_array( $menu_slug, $all_target_slugs, true ) ) {
				// 自プラグイン自体のメニューや設定メニューの移動は防ぐ
				if ( 'ku-submenu' === $menu_slug || 'ku-submenu-folder' === $menu_slug || 'options-general.php' === $menu_slug || str_starts_with( $menu_slug, 'ku-submenu-' ) ) {
					continue;
				}

				$is_active = $this->is_menu_active( $menu_slug, $current_slug, $parent_file );

				$items_to_move[ $menu_slug ] = array(
					'menu_index' => $index,
					'menu_data'  => $item,
					'is_active'  => $is_active,
				);
			}
		}

		// 各フォルダーごとに処理
		$valid_folders = array();
		foreach ( $sub_menues as $folder_idx => $folder ) {
			$folder_id    = $folder['id'] ?? ( 'folder_' . $folder_idx );
			$parent_slug  = ( 0 === $folder_idx || 'folder_default' === $folder_id ) ? 'ku-submenu' : 'ku-submenu-' . $folder_id;
			$folder_title = $folder['title'] ?? 'KU Submenu';
			$folder_icon  = ! empty( $folder['icon'] ) ? $folder['icon'] : 'dashicons-category';
			$folder_items = $folder['menues'] ?? array();

			// 第2フォルダー以降で親メニューがまだ未登録の場合、動的に追加登録
			if ( 0 !== $folder_idx && 'folder_default' !== $folder_id ) {
				// メニュー登録
				add_menu_page(
					$folder_title,
					$folder_title,
					'manage_options',
					$parent_slug,
					'__return_null',
					$folder_icon,
					9999 + $folder_idx
				);
			} else {
				// デフォルトフォルダーのタイトル・アイコン反映
				foreach ( $menu as $k => $m_item ) {
					if ( isset( $m_item[2] ) && 'ku-submenu' === $m_item[2] ) {
						$menu[ $k ][0] = $folder_title;
						$menu[ $k ][6] = $folder_icon;
						break;
					}
				}
			}

			// サブメニュー構造の初期化
			if ( isset( $submenu[ $parent_slug ] ) ) {
				unset( $submenu[ $parent_slug ] );
			}
			$submenu[ $parent_slug ] = array();

			$valid_item_count = 0;

			if ( ! empty( $folder_items ) ) {
				foreach ( $folder_items as $config_item ) {
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

					$submenu[ $parent_slug ][] = array(
						$title,
						$capability,
						$url,
						$title,
					);
					$valid_item_count++;

					// 非アクティブの場合のみ元のルートメニューから削除（非表示化）
					if ( ! $is_active && isset( $menu[ $item_info['menu_index'] ] ) ) {
						unset( $menu[ $item_info['menu_index'] ] );
					}
				}
			}

			// 有効なオリジナル格納アイテムが 0件 の場合、空フォルダーとして消去処理
			if ( 0 === $valid_item_count ) {
				// サブメニュー構造を破棄
				unset( $submenu[ $parent_slug ] );

				// ルートメニュー ($menu) からフォルダーのノードを破棄
				foreach ( $menu as $k => $m_item ) {
					if ( isset( $m_item[2] ) && $m_item[2] === $parent_slug ) {
						unset( $menu[ $k ] );
						break;
					}
				}
				continue;
			}

			// 設定項目の自動追加ロジック (none / first / last) - 有効アイテムが1件以上ある場合のみ追加
			$setting_link_pos = $options['setting_link_position'] ?? 'none';
			if ( 'first' === $setting_link_pos ) {
				array_unshift(
					$submenu[ $parent_slug ],
					array(
						__( 'Submenu Settings', 'ku-submenu-folder' ),
						'manage_options',
						'options-general.php?page=ku-submenu-folder',
						__( 'Submenu Settings', 'ku-submenu-folder' ),
					)
				);
			} elseif ( 'last' === $setting_link_pos ) {
				$submenu[ $parent_slug ][] = array(
					__( 'Submenu Settings', 'ku-submenu-folder' ),
					'manage_options',
					'options-general.php?page=ku-submenu-folder',
					__( 'Submenu Settings', 'ku-submenu-folder' ),
				);
			}

			$valid_folders[] = $folder;
		}

		// 全フォルダーをユーザーの設定順に従い、隙間のない連続キーで $menu の最末尾へ一括再配置
		$this->reposition_all_folders_to_bottom( $valid_folders );
	}

	/**
	 * 全フォルダー項目をユーザーの設定順（0, 1, 2...）に従い、隙間のない連続キー（+0, +1, +2...）で最末尾へ配置
	 *
	 * @param array $sub_menues サブメニューフォルダー設定配列.
	 */
	private function reposition_all_folders_to_bottom( array $sub_menues ) {
		global $menu;
		if ( empty( $menu ) || ! is_array( $menu ) ) {
			return;
		}

		// 全フォルダーの親スラグ順序を生成
		$folder_slugs = array();
		foreach ( $sub_menues as $f_idx => $folder ) {
			$f_id          = $folder['id'] ?? ( 'folder_' . $f_idx );
			$parent_slug   = ( 0 === $f_idx || 'folder_default' === $f_id ) ? 'ku-submenu' : 'ku-submenu-' . $f_id;
			$folder_slugs[] = $parent_slug;
		}

		// 1. $menu から全フォルダー項目を一旦抽出・保持し、元の位置からは削除
		$extracted_folders = array();
		foreach ( $menu as $key => $item ) {
			if ( isset( $item[2] ) && in_array( $item[2], $folder_slugs, true ) ) {
				$extracted_folders[ $item[2] ] = $item;
				unset( $menu[ $key ] );
			}
		}

		if ( empty( $extracted_folders ) ) {
			return;
		}

		// 2. 既存の数値キーの最大値を取得し、基準位置を算出（最小 99900）
		$numeric_keys = array_filter( array_keys( $menu ), 'is_numeric' );
		$max_existing = ! empty( $numeric_keys ) ? (int) max( $numeric_keys ) : 999;
		$base_pos     = max( 99900, $max_existing + 10 );

		// 3. ユーザーの並び順通りに、隙間のない連続キー（+0, +1, +2...）で末尾に隙間なく並べて配置
		foreach ( $folder_slugs as $idx => $slug ) {
			if ( isset( $extracted_folders[ $slug ] ) ) {
				$new_key          = (string) ( $base_pos + $idx );
				$menu[ $new_key ] = $extracted_folders[ $slug ];
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
