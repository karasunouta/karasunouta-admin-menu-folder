<?php
/**
 * AdminMenuFolder Settings Page Class
 *
 * @package KarasunoutaAdminMenuFolder
 */

namespace karasunouta\AdminMenuFolder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings Page Class
 */
class Settings_Page {

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
		add_action( 'admin_menu', array( $this, 'register_menu_page' ), 9 );
		add_action( 'admin_init', array( $this, 'handle_save_settings' ) );
		add_action( 'admin_init', array( $this, 'handle_reset_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * メニューページの登録
	 */
	public function register_menu_page() {
		// 「設定」の配下に設定ページを追加
		add_options_page(
			__( 'Admin Menu Folder', 'karasunouta-admin-menu-folder' ),
			__( 'Admin Menu Folder', 'karasunouta-admin-menu-folder' ),
			'manage_options',
			Main::SETTINGS_PAGE_SLUG,
			array( $this, 'render_settings_page' )
		);

		// フォルダーの親メニューノードを追加（格納項目が無い場合は Menu_Filter が破棄する）
		$folder = $this->main->get_folder();

		add_menu_page(
			$this->main->get_folder_title( $folder ),
			$this->main->get_folder_title( $folder ),
			'manage_options',
			Main::FOLDER_MENU_SLUG,
			'__return_null',
			$this->main->get_folder_icon( $folder ),
			9999
		);
	}

	/**
	 * フォーム送信（保存）の早期ハンドリング（admin_init）
	 */
	public function handle_save_settings() {
		if ( ! isset( $_POST['kamf_save_settings'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to perform this action.', 'karasunouta-admin-menu-folder' ) );
		}

		check_admin_referer( 'kamf_save_settings_action', 'kamf_save_settings_nonce' );

		$options = $this->main->get_raw_options();

		// フォルダーのフィールドが送信されていない場合は既存の内容を維持する
		if ( isset( $_POST['kamf_folder'] ) && is_array( $_POST['kamf_folder'] ) ) {
			$raw_folder        = map_deep( wp_unslash( $_POST['kamf_folder'] ), 'sanitize_text_field' );
			$options['folder'] = $this->main->sanitize_folder( $raw_folder, $this->main->get_protected_slugs() );
		}

		$raw_link_position = isset( $_POST['kamf_setting_link_position'] )
			? sanitize_text_field( wp_unslash( $_POST['kamf_setting_link_position'] ) )
			: 'none';

		$options['version']               = KAMF_VERSION;
		$options['show_admin_bar_link']   = isset( $_POST['kamf_show_admin_bar_link'] );
		$options['setting_link_position'] = $this->main->normalize_setting_link_position( $raw_link_position );

		$this->main->save_options( $options );

		add_settings_error(
			'kamf_messages',
			'kamf_settings_saved',
			__( 'Settings saved.', 'karasunouta-admin-menu-folder' ),
			'success'
		);

		/**
		 * 設定の保存完了直後（リダイレクト前）に発火
		 *
		 * @param array $options 保存された設定配列.
		 */
		do_action( 'kamf_after_save_settings', $options );

		$this->redirect_with_notices();
	}

	/**
	 * 設定のリセット（初期化）処理
	 */
	public function handle_reset_settings() {
		if ( ! isset( $_POST['kamf_reset_settings'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to perform this action.', 'karasunouta-admin-menu-folder' ) );
		}

		check_admin_referer( 'kamf_reset_settings_action', 'kamf_reset_settings_nonce' );

		$this->main->reset_options();

		add_settings_error(
			'kamf_messages',
			'kamf_settings_reset',
			__( 'Settings reset to default.', 'karasunouta-admin-menu-folder' ),
			'success'
		);

		/**
		 * 設定のリセット完了直後（リダイレクト前）に発火
		 */
		do_action( 'kamf_after_reset_settings' );

		$this->redirect_with_notices();
	}

	/**
	 * 管理通知を引き継いだ状態で設定ページへリダイレクト
	 */
	private function redirect_with_notices() {
		// WordPress標準の仕組みでリダイレクト後も通知を表示する
		set_transient( 'settings_errors', get_settings_errors(), 30 );

		wp_safe_redirect( $this->main->get_settings_url( array( 'settings-updated' => 'true' ) ) );
		exit;
	}

	/**
	 * アセット（CSS / JS）の読み込み
	 *
	 * @param string $hook_suffix 現在の管理画面フック名.
	 */
	public function enqueue_assets( $hook_suffix ) {
		// アセットは自身の設定画面でのみ読み込む
		if ( ! is_string( $hook_suffix ) || strpos( $hook_suffix, Main::SETTINGS_PAGE_SLUG ) === false ) {
			return;
		}

		$asset_file = KAMF_PLUGIN_DIR . 'build/admin-settings.asset.php';
		if ( file_exists( $asset_file ) ) {
			$asset_info = require $asset_file;
			$deps       = $asset_info['dependencies'] ?? array();
			$version    = $asset_info['version'] ?? KAMF_VERSION;
		} else {
			$deps    = array();
			$version = KAMF_VERSION;
		}

		if ( ! in_array( 'wp-hooks', $deps, true ) ) {
			$deps[] = 'wp-hooks';
		}

		wp_enqueue_style(
			'kamf-admin-settings-css',
			KAMF_PLUGIN_URL . 'build/admin-settings.css',
			array(),
			$version
		);

		wp_enqueue_script(
			'kamf-admin-settings-js',
			KAMF_PLUGIN_URL . 'build/admin-settings.js',
			$deps,
			$version,
			true
		);

		wp_localize_script(
			'kamf-admin-settings-js',
			'kamfParams',
			array(
				'maxItems'        => Main::MAX_ITEMS_PER_FOLDER,
				'settingsUrl'     => $this->main->get_settings_url(),
				'protectedSlugs'  => $this->main->get_protected_slugs(),
				'primarySlug'     => Main::FOLDER_MENU_SLUG,
				'removeItem'      => __( 'Remove Item', 'karasunouta-admin-menu-folder' ),
				'confirmReset'    => __( 'Are you sure you want to reset Karasunouta Admin Menu Folder settings to default?', 'karasunouta-admin-menu-folder' ),
				'limitMessage'    => sprintf(
					/* translators: %d: Maximum allowed items */
					__( 'You can store up to %d menu items per folder.', 'karasunouta-admin-menu-folder' ),
					Main::MAX_ITEMS_PER_FOLDER
				),
				'noItemsSelected' => __( 'No menu items selected', 'karasunouta-admin-menu-folder' ),
			)
		);
	}

	/**
	 * 設定ページの描画処理
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'karasunouta-admin-menu-folder' ) );
		}

		$options      = $this->main->get_options();
		$folder       = $options['folder'];
		$folder_nodes = $this->main->get_folder_nodes();

		$card = array(
			'id'      => $folder['id'],
			'slug'    => Main::FOLDER_MENU_SLUG,
			'title'   => $this->main->get_folder_title( $folder ),
			'icon'    => $this->main->get_folder_icon( $folder ),
			'primary' => true,
			'active'  => true,
			'menues'  => $folder['menues'],
		);

		// フォルダーへ移動済みの項目は $menu から除外されているため、全フォルダー分を復元対象とする
		$stored_items     = $this->main->get_all_stored_items();
		$available_menues = $this->get_root_menu_items( $stored_items, $folder_nodes );
		$selected_slugs   = wp_list_pluck( $stored_items, 'menu_slug' );
		$protected_slugs  = $this->main->get_protected_slugs();

		?>
		<div class="wrap kamf-settings-wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Admin Menu Folder Settings', 'karasunouta-admin-menu-folder' ); ?></h1>

			<form id="kamf-reset-form" method="post" action="<?php echo esc_url( $this->main->get_settings_url() ); ?>" class="kamf-reset-form">
				<?php wp_nonce_field( 'kamf_reset_settings_action', 'kamf_reset_settings_nonce' ); ?>
				<input type="hidden" name="kamf_reset_settings" value="1" />
				<button type="button" id="kamf-reset-settings-btn" class="page-title-action">
					<?php esc_html_e( 'Reset to Default', 'karasunouta-admin-menu-folder' ); ?>
				</button>
			</form>

			<?php
			/**
			 * ページタイトル行のアクション領域の末尾に出力
			 */
			do_action( 'kamf_render_page_title_actions' );
			?>

			<hr class="wp-header-end">

			<?php settings_errors( 'kamf_messages' ); ?>

			<form method="post" action="<?php echo esc_url( $this->main->get_settings_url() ); ?>" id="kamf-settings-form">
				<?php wp_nonce_field( 'kamf_save_settings_action', 'kamf_save_settings_nonce' ); ?>

				<div id="kamf-folder-fields" class="kamf-folder-fields">
					<?php $this->render_folder_fields( $folder ); ?>
				</div>

				<div class="kamf-settings-container">
					<!-- 左側: 擬似WPサイドメニュー（保護対象は disabled 化） -->
					<div class="kamf-pseudo-sidebar-wrapper">
						<div class="kamf-pseudo-sidebar">
							<ul class="kamf-pseudo-menu-list">
								<?php foreach ( $available_menues as $item ) : ?>
									<?php
									$slug        = $item['slug'];
									$is_checked  = in_array( $slug, $selected_slugs, true );
									$is_folder   = ! empty( $item['is_folder'] );
									$is_disabled = $is_folder || in_array( $slug, $protected_slugs, true );

									$item_classes = array( 'kamf-pseudo-menu-item' );
									if ( $is_checked ) {
										$item_classes[] = 'is-selected';
									}
									if ( $is_disabled ) {
										$item_classes[] = 'is-disabled';
									}
									if ( $is_folder && ! empty( $item['is_primary'] ) ) {
										$item_classes[] = 'is-selected-folder-active';
									}
									?>
									<li class="<?php echo esc_attr( implode( ' ', $item_classes ) ); ?>"
										data-folder-node="<?php echo esc_attr( $is_folder ? '1' : '0' ); ?>"
										data-slug="<?php echo esc_attr( $slug ); ?>"
										data-title="<?php echo esc_attr( $item['title'] ); ?>"
										data-url="<?php echo esc_attr( $item['url'] ); ?>"
										data-position="<?php echo esc_attr( (string) $item['position'] ); ?>"
										data-icon-class="<?php echo esc_attr( $item['icon_class'] ); ?>"
									>
										<div class="kamf-menu-label">
											<span class="kamf-menu-icon"><?php $this->render_icon( $item['icon_class'] ); ?></span>
											<span class="kamf-menu-title"><?php echo esc_html( $item['title'] ); ?></span>
										</div>
										<div class="kamf-menu-checkbox">
											<input type="checkbox"
												class="kamf-item-toggle"
												value="<?php echo esc_attr( $slug ); ?>"
												<?php checked( $is_checked ); ?>
												<?php disabled( $is_disabled ); ?>
											>
										</div>
									</li>
								<?php endforeach; ?>
							</ul>
						</div>
					</div>

					<!-- 右側: フォルダーのプレビュー・カスタマイズエリア -->
					<div class="kamf-preview-container">
						<div class="kamf-folders-grid" id="kamf-folders-grid">
							<?php
							$this->render_folder_card( $card );

							/**
							 * フォルダーカードの描画後に出力
							 *
							 * @param array $card プライマリーフォルダーのカード情報.
							 */
							do_action( 'kamf_after_folder_cards', $card );
							?>
						</div>

						<div class="kamf-setting-options-card">
							<div class="kamf-setting-option-row">
								<label for="kamf_show_admin_bar_link" class="kamf-setting-option-label">
									<?php esc_html_e( 'Show link to settings page in Toolbar:', 'karasunouta-admin-menu-folder' ); ?>
								</label>
								<input type="checkbox" name="kamf_show_admin_bar_link" id="kamf_show_admin_bar_link" value="1" <?php checked( ! empty( $options['show_admin_bar_link'] ) ); ?>>
							</div>

							<div class="kamf-setting-option-row">
								<label for="kamf_setting_link_position" class="kamf-setting-option-label">
									<?php esc_html_e( 'Add settings menu to Menu Folder:', 'karasunouta-admin-menu-folder' ); ?>
								</label>
								<?php $setting_link_pos = $options['setting_link_position']; ?>
								<select name="kamf_setting_link_position" id="kamf_setting_link_position" class="kamf-setting-option-select">
									<option value="none" <?php selected( $setting_link_pos, 'none' ); ?>><?php esc_html_e( 'Do not add', 'karasunouta-admin-menu-folder' ); ?></option>
									<option value="first" <?php selected( $setting_link_pos, 'first' ); ?>><?php esc_html_e( 'Add to top', 'karasunouta-admin-menu-folder' ); ?></option>
									<option value="last" <?php selected( $setting_link_pos, 'last' ); ?>><?php esc_html_e( 'Add to bottom', 'karasunouta-admin-menu-folder' ); ?></option>
								</select>
							</div>
						</div>

						<div class="kamf-form-actions">
							<input type="submit" name="kamf_save_settings" class="button button-primary button-large" value="<?php esc_attr_e( 'Save Changes', 'karasunouta-admin-menu-folder' ); ?>">
						</div>
					</div>
				</div>
			</form>
		</div>

		<?php
		/**
		 * 設定ページ本体の描画完了後に出力（モーダルダイアログ等の配置用）
		 */
		do_action( 'kamf_after_settings_page' );
	}

	/**
	 * フォルダーカードを描画
	 *
	 * @param array $card id / slug / title / icon / primary / active / menues を持つ配列.
	 */
	public function render_folder_card( array $card ) {
		$card = wp_parse_args(
			$card,
			array(
				'id'      => Main::FOLDER_ID,
				'slug'    => Main::FOLDER_MENU_SLUG,
				'title'   => '',
				'icon'    => 'dashicons-category',
				'primary' => false,
				'active'  => false,
				'menues'  => array(),
			)
		);

		$items = is_array( $card['menues'] ) ? $card['menues'] : array();

		$card_classes = array( 'kamf-folder-card' );
		if ( ! empty( $card['active'] ) ) {
			$card_classes[] = 'is-active';
		}
		if ( ! empty( $card['primary'] ) ) {
			$card_classes[] = 'is-primary';
		}
		?>
		<div class="<?php echo esc_attr( implode( ' ', $card_classes ) ); ?>"
			data-folder-id="<?php echo esc_attr( $card['id'] ); ?>"
			data-folder-slug="<?php echo esc_attr( $card['slug'] ); ?>"
			data-icon="<?php echo esc_attr( $card['icon'] ); ?>"
			data-primary="<?php echo esc_attr( ! empty( $card['primary'] ) ? '1' : '0' ); ?>"
		>
			<div class="kamf-folder-header">
				<div class="kamf-folder-header-left">
					<span class="kamf-folder-icon"><?php $this->render_icon( $card['icon'] ); ?></span>
					<span class="kamf-folder-title"><?php echo esc_html( $card['title'] ); ?></span>
				</div>
				<div class="kamf-folder-header-actions">
					<?php
					/**
					 * フォルダーカードのヘッダーアクション領域に出力
					 *
					 * @param array $card カード情報.
					 */
					do_action( 'kamf_render_folder_header_actions', $card );
					?>
				</div>
			</div>
			<ul class="kamf-folder-sublist">
				<?php if ( empty( $items ) ) : ?>
					<li class="kamf-empty-notice"><?php esc_html_e( 'No menu items selected', 'karasunouta-admin-menu-folder' ); ?></li>
				<?php else : ?>
					<?php foreach ( $items as $index => $sub_item ) : ?>
						<li class="kamf-subitem-row"
							data-slug="<?php echo esc_attr( $sub_item['menu_slug'] ?? '' ); ?>"
							data-title="<?php echo esc_attr( $sub_item['title'] ?? '' ); ?>"
							data-url="<?php echo esc_attr( $sub_item['data']['url'] ?? '' ); ?>"
							data-position="<?php echo esc_attr( (string) ( $sub_item['data']['original_position'] ?? 999 ) ); ?>"
							data-icon-class="<?php echo esc_attr( $sub_item['data']['icon_class'] ?? '' ); ?>"
						>
							<div class="kamf-subitem-title">
								<span class="kamf-menu-icon"><?php $this->render_icon( $sub_item['data']['icon_class'] ?? '' ); ?></span>
								<span><?php echo esc_html( $sub_item['title'] ?? '' ); ?></span>
							</div>
							<div class="kamf-subitem-actions">
								<?php
								/**
								 * メニュー項目のアクション領域の先頭に出力
								 *
								 * @param array $sub_item メニュー項目情報.
								 * @param int   $index    フォルダー内の並び順.
								 * @param array $card     カード情報.
								 */
								do_action( 'kamf_render_subitem_actions', $sub_item, $index, $card );
								?>
								<button type="button" class="button button-small kamf-item-remove-btn" title="<?php esc_attr_e( 'Remove Item', 'karasunouta-admin-menu-folder' ); ?>">
									<span class="dashicons dashicons-no-alt"></span>
								</button>
							</div>
						</li>
					<?php endforeach; ?>
				<?php endif; ?>
			</ul>
			<?php
			/**
			 * フォルダーカードのフッターアクション領域に出力
			 *
			 * @param array $card カード情報.
			 */
			do_action( 'kamf_render_folder_footer_actions', $card );
			?>
		</div>
		<?php
	}

	/**
	 * フォルダーの保存用フォームフィールドを描画
	 *
	 * @param array  $folder     フォルダー設定配列.
	 * @param string $field_name フォームフィールドの基準名.
	 */
	public function render_folder_fields( array $folder, string $field_name = 'kamf_folder' ) {
		printf(
			'<input type="hidden" name="%1$s[id]" value="%2$s" />',
			esc_attr( $field_name ),
			esc_attr( $folder['id'] ?? Main::FOLDER_ID )
		);

		$items = isset( $folder['menues'] ) && is_array( $folder['menues'] ) ? $folder['menues'] : array();

		foreach ( array_values( $items ) as $index => $item ) {
			$values = array(
				'menu_slug'  => $item['menu_slug'] ?? '',
				'title'      => $item['title'] ?? '',
				'url'        => $item['data']['url'] ?? '',
				'position'   => (string) ( $item['data']['original_position'] ?? 999 ),
				'icon_class' => $item['data']['icon_class'] ?? '',
			);

			foreach ( $values as $key => $value ) {
				printf(
					'<input type="hidden" name="%1$s[menues][%2$d][%3$s]" value="%4$s" />',
					esc_attr( $field_name ),
					(int) $index,
					esc_attr( $key ),
					esc_attr( (string) $value )
				);
			}
		}
	}

	/**
	 * メニュー項目のアイコンをエスケープ済みで出力
	 *
	 * @param string $icon_class アイコンクラスまたは画像URI.
	 */
	public function render_icon( string $icon_class ) {
		$icon_class = trim( $icon_class );

		if ( str_contains( $icon_class, 'dashicons-' ) ) {
			printf( '<span class="dashicons %s"></span>', esc_attr( $icon_class ) );
			return;
		}

		if ( preg_match( '#^data:image/(?:png|jpe?g|gif|webp|svg\+xml);base64,[A-Za-z0-9+/=]+$#', $icon_class ) ) {
			printf( '<img src="%s" alt="" class="kamf-custom-icon" width="18" height="18" />', esc_attr( $icon_class ) );
			return;
		}

		if ( str_starts_with( $icon_class, 'http://' ) || str_starts_with( $icon_class, 'https://' ) || str_starts_with( $icon_class, '/' ) ) {
			printf( '<img src="%s" alt="" class="kamf-custom-icon" width="18" height="18" />', esc_url( $icon_class ) );
			return;
		}

		echo '<span class="dashicons dashicons-admin-generic"></span>';
	}

	/**
	 * 管理メニューのキーが 0 から始まる連番へ前詰めされているかを判定
	 *
	 * WordPress は他プラグインがメニュー順を差し替えた場合に $menu のキーを連番へ
	 * 振り直す。その環境では元のメニュー位置がキーから読み取れなくなるため、
	 * フォルダーへ移動した項目の数だけ位置を補正する必要がある。
	 *
	 * @param array $raw_menu グローバル $menu 配列.
	 * @return bool
	 */
	private function has_compacted_menu_keys( array $raw_menu ): bool {
		if ( count( $raw_menu ) < 2 ) {
			return false;
		}

		$keys = array_keys( $raw_menu );
		foreach ( $keys as $index => $key ) {
			if ( ! is_numeric( $key ) || (int) $key !== $index ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * 擬似サイドメニュー用に、ルートメニュー項目を「元の位置」「元のアイコン」で再構成して取得
	 *
	 * @param array $selected_items フォルダーに格納されているメニュー項目.
	 * @param array $folder_nodes   フォルダーの親メニューノード一覧（表示順）.
	 * @return array
	 */
	public function get_root_menu_items( array $selected_items, array $folder_nodes = array() ): array {
		$items          = array();
		$raw_menu       = $GLOBALS['menu'] ?? array();
		$existing_slugs = array();
		$folder_slugs   = wp_list_pluck( $folder_nodes, 'slug' );

		// 1. フォルダーへ移動済み（$menu から除外されている）項目のスラグと元の位置のマップを作成
		$selected_positions = array();
		foreach ( $selected_items as $selected ) {
			if ( ! empty( $selected['menu_slug'] ) ) {
				$selected_positions[ $selected['menu_slug'] ] = (float) ( $selected['data']['original_position'] ?? 999.0 );
			}
		}

		$is_compacted_menu = $this->has_compacted_menu_keys( $raw_menu );

		// 2. 現在の $menu から未格納の項目を取得（キーが前詰めされている環境のみオフセットを補正）
		foreach ( $raw_menu as $position => $menu_item ) {
			if ( empty( $menu_item[0] ) || empty( $menu_item[2] ) ) {
				continue;
			}

			if ( isset( $menu_item[4] ) && str_contains( $menu_item[4], 'wp-menu-separator' ) ) {
				continue;
			}

			$slug = $menu_item[2];

			// フォルダーの親ノードと自身の設定ページはステップ4で末尾へ配置する
			if ( in_array( $slug, $folder_slugs, true ) || Main::SETTINGS_PAGE_SLUG === $slug ) {
				continue;
			}

			$title      = trim( (string) preg_replace( '/\s*<span.*?>.*?<\/span>/i', '', $menu_item[0] ) );
			$icon_class = $menu_item[6] ?? 'dashicons-admin-generic';

			$url = $slug;
			if ( ! str_contains( $slug, '.php' ) && ! str_contains( $slug, 'http' ) ) {
				$url = 'admin.php?page=' . $slug;
			}

			$calculated_position = (float) $position;

			// キーが前詰めされている環境の場合のみオフセット補正を適用
			if ( $is_compacted_menu ) {
				$offset = 0;
				foreach ( $selected_positions as $selected_position ) {
					if ( $selected_position <= ( $calculated_position + $offset ) ) {
						$offset++;
					}
				}
				$calculated_position += $offset;
			}

			$items[] = array(
				'slug'       => $slug,
				'title'      => wp_strip_all_tags( $title ),
				'url'        => $url,
				'position'   => $calculated_position,
				'icon_class' => (string) $icon_class,
			);

			$existing_slugs[] = $slug;
		}

		// 3. フォルダーへ移動済みで $menu から除外されている項目を「元の位置」「元のアイコン」で復元
		foreach ( $selected_items as $selected ) {
			$slug = $selected['menu_slug'] ?? '';

			if ( '' === $slug || in_array( $slug, $existing_slugs, true ) ) {
				continue;
			}

			$url = $selected['data']['url'] ?? $slug;
			if ( ! str_contains( $url, '.php' ) && ! str_contains( $url, 'http' ) ) {
				$url = 'admin.php?page=' . $url;
			}

			$items[] = array(
				'slug'       => $slug,
				'title'      => wp_strip_all_tags( (string) ( $selected['title'] ?? $slug ) ),
				'url'        => $url,
				'position'   => (float) ( $selected['data']['original_position'] ?? 999.0 ),
				'icon_class' => (string) ( $selected['data']['icon_class'] ?? 'dashicons-admin-generic' ),
			);

			$existing_slugs[] = $slug;
		}

		// 4. 元の位置 (position) の数値による厳密な昇順ソート（配列キーの型キャスト揺れ防止）
		usort(
			$items,
			function ( $a, $b ) {
				return ( (float) $a['position'] ) <=> ( (float) $b['position'] );
			}
		);

		// 5. フォルダーの親ノードを指定された表示順で末尾へ配置
		$positions = wp_list_pluck( $items, 'position' );
		$base      = max( 999000.0, ! empty( $positions ) ? ceil( (float) max( $positions ) ) + 100.0 : 999000.0 );

		foreach ( $folder_nodes as $index => $node ) {
			$items[] = array(
				'slug'       => $node['slug'],
				'title'      => wp_strip_all_tags( (string) $node['title'] ),
				'url'        => 'admin.php?page=' . $node['slug'],
				'position'   => $base + (float) $index,
				'icon_class' => (string) $node['icon'],
				'is_folder'  => true,
				'is_primary' => ! empty( $node['primary'] ),
			);
		}

		return $items;
	}
}
