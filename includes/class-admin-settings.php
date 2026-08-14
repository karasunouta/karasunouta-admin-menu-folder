<?php
/**
 * AdminMenuFolder Settings Page Class
 *
 * @package AdminMenuFolder
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
			__( 'Admin Menu Folder', 'admin-menu-folder' ),
			__( 'Admin Menu Folder', 'admin-menu-folder' ),
			'manage_options',
			'admin-menu-folder',
			array( $this, 'render_settings_page' )
		);

		// トップレベル「Menu Folder」フォルダメニューを追加 (最下部付近 9999)
		add_menu_page(
			__( 'Menu Folder', 'admin-menu-folder' ),
			__( 'Menu Folder', 'admin-menu-folder' ),
			'manage_options',
			'amf-folder',
			'__return_null',
			'dashicons-category',
			9999
		);
	}

	/**
	 * フォーム送信（保存）の早期ハンドリング（admin_init）
	 * 保護対象スラグのサニタイズ
	 */
	public function handle_save_settings() {
		if ( ! isset( $_POST['amf_save_settings'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to perform this action.', 'admin-menu-folder' ) );
		}

		check_admin_referer( 'amf_save_settings_action', 'amf_save_settings_nonce' );

		$saved_folders_json = isset( $_POST['amf_folders_json'] ) ? sanitize_text_field( wp_unslash( $_POST['amf_folders_json'] ) ) : '[]';
		$decoded_folders    = json_decode( $saved_folders_json, true );

		if ( is_array( $decoded_folders ) ) {
			$max_folders     = $this->main->get_max_folders();
			$max_items       = $this->main->get_max_items();
			$protected_slugs = $this->main->get_protected_slugs();

			// フィルターフック経由でサニタイズ（Pro版有効時はPro版側が全フォルダーをサニタイズ処理）
			$sanitized_folders = apply_filters(
				'amf_sanitize_folders_for_save',
				null,
				$decoded_folders,
				$max_folders,
				$max_items,
				$protected_slugs
			);

			if ( ! is_array( $sanitized_folders ) ) {
				$sanitized_folders = array();
				foreach ( array_slice( $decoded_folders, 0, $max_folders ) as $f_idx => $folder ) {
					$raw_items       = isset( $folder['menues'] ) && is_array( $folder['menues'] ) ? $folder['menues'] : array();
					$sanitized_items = array();
					$item_count      = 0;

					foreach ( $raw_items as $item ) {
						if ( $item_count >= $max_items ) {
							break;
						}
						$slug = sanitize_text_field( $item['menu_slug'] ?? '' );

						if ( ! empty( $slug ) && ! in_array( $slug, $protected_slugs, true ) ) {
							$sanitized_items[] = array(
								'menu_slug' => $slug,
								'title'     => sanitize_text_field( $item['title'] ?? '' ),
								'order'     => (int) ( $item['order'] ?? $item_count ),
								'data'      => array(
									'url'               => sanitize_text_field( $item['data']['url'] ?? $item['url'] ?? '' ),
									'original_position' => isset( $item['data']['original_position'] ) ? (float) $item['data']['original_position'] : (isset( $item['position'] ) ? (float) $item['position'] : 999.0),
									'icon_class'        => sanitize_text_field( $item['data']['icon_class'] ?? $item['icon_class'] ?? '' ),
								),
							);
							$item_count++;
						}
					}

					$sanitized_folders[] = array(
						'id'       => sanitize_text_field( $folder['id'] ?? ( 'folder_' . $f_idx ) ),
						'title'    => 'Menu Folder',
						'icon'     => sanitize_text_field( $folder['icon'] ?? 'dashicons-category' ),
						'position' => (int) ( $folder['position'] ?? 99 ),
						'menues'   => $sanitized_items,
					);
				}
			}

			if ( empty( $sanitized_folders ) ) {
				$sanitized_folders = array(
					array(
						'id'       => 'folder_default',
						'title'    => 'Menu Folder',
						'icon'     => 'dashicons-category',
						'position' => 99,
						'menues'   => array(),
					),
				);
			}

			$show_admin_bar_link = isset( $_POST['amf_show_admin_bar_link'] ) ? 1 : 0;
			$setting_link_pos    = isset( $_POST['amf_setting_link_position'] ) ? sanitize_text_field( wp_unslash( $_POST['amf_setting_link_position'] ) ) : 'none';
			if ( ! in_array( $setting_link_pos, array( 'none', 'first', 'last' ), true ) ) {
				$setting_link_pos = 'none';
			}

			$options                          = $this->main->get_raw_options();
			$options['menu_folders']          = $sanitized_folders;
			$options['show_admin_bar_link']   = (bool) $show_admin_bar_link;
			$options['setting_link_position'] = $setting_link_pos;
			$this->main->save_options( $options );

			// リダイレクトして即座に画面全体を最新状態で再読み込み
			wp_safe_redirect( admin_url( 'options-general.php?page=admin-menu-folder&amf_updated=true' ) );
			exit;
		}
	}

	/**
	 * 設定のリセット（初期化）処理
	 */
	public function handle_reset_settings() {
		if ( isset( $_POST['action'] ) && 'amf_reset_settings' === $_POST['action'] ) {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'admin-menu-folder' ) );
			}

			check_admin_referer( 'amf_reset_settings_nonce', 'amf_reset_nonce' );

			$this->main->reset_options();

			wp_safe_redirect( admin_url( 'options-general.php?page=admin-menu-folder&amf_reset=true' ) );
			exit;
		}
	}

	/**
	 * アセット（CSS / JS）の読み込み
	 *
	 * @param string $hook_suffix 現在の管理画面フック名.
	 */
	public function enqueue_assets( $hook_suffix ) {
		$asset_file = AMF_PLUGIN_DIR . 'build/admin-settings.asset.php';
		if ( file_exists( $asset_file ) ) {
			$asset_info = require $asset_file;
			$deps       = $asset_info['dependencies'] ?? array();
			$version    = $asset_info['version'] ?? AMF_VERSION;
		} else {
			$deps    = array();
			$version = AMF_VERSION;
		}

		// JSは全管理画面でEnqueue（親リンク href の書き換え・クリック補正のため）
		wp_enqueue_script(
			'amf-admin-settings-js',
			AMF_PLUGIN_URL . 'build/admin-settings.js',
			$deps,
			$version,
			true
		);

		wp_localize_script(
			'amf-admin-settings-js',
			'amfParams',
			array(
				'isPro'           => $this->main->is_pro(),
				'maxFolders'      => $this->main->get_max_folders(),
				'maxItems'        => $this->main->get_max_items(),
				'nonce'           => wp_create_nonce( 'amf_save_settings_nonce' ),
				'settingsUrl'     => admin_url( 'options-general.php?page=admin-menu-folder' ),
				'protectedSlugs'  => $this->main->get_protected_slugs(),
				'moveUp'          => __( 'Move Up', 'admin-menu-folder' ),
				'moveDown'        => __( 'Move Down', 'admin-menu-folder' ),
				'moveLeft'        => __( 'Move Left', 'admin-menu-folder' ),
				'moveRight'       => __( 'Move Right', 'admin-menu-folder' ),
				'editFolder'      => __( 'Edit Folder', 'admin-menu-folder' ),
				'deleteFolder'    => __( 'Delete Folder', 'admin-menu-folder' ),
				'removeItem'      => __( 'Remove Item', 'admin-menu-folder' ),
				'confirmDelete'   => __( 'Are you sure you want to delete this folder? Stored menu items will be restored.', 'admin-menu-folder' ),
				'confirmReset'    => __( 'Are you sure you want to reset Admin Menu Folder settings to default?', 'admin-menu-folder' ),
				'limitMessage'    => sprintf(
					/* translators: %d: Maximum allowed items */
					__( 'You can store up to %d menu items per folder.', 'admin-menu-folder' ),
					$this->main->get_max_items()
				),
				'noItemsSelected' => __( 'No menu items selected', 'admin-menu-folder' ),
			)
		);

		// CSSは設定画面のみで読込
		if ( strpos( $hook_suffix, 'admin-menu-folder' ) !== false ) {
			wp_enqueue_style(
				'amf-admin-settings-css',
				AMF_PLUGIN_URL . 'build/admin-settings.css',
				array(),
				$version
			);
		}
	}

	/**
	 * 設定ページの描画処理
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'admin-menu-folder' ) );
		}

		$options         = $this->main->get_options();
		$menu_folders    = $options['menu_folders'] ?? array();
		$max_folders     = $this->main->get_max_folders();
		$protected_slugs = $this->main->get_protected_slugs();

		// 全フォルダーの選択済みメニュー項目を統合収集
		$all_selected_menues = array();
		$selected_slugs      = array();

		foreach ( $menu_folders as $folder ) {
			if ( ! empty( $folder['menues'] ) && is_array( $folder['menues'] ) ) {
				foreach ( $folder['menues'] as $item ) {
					$all_selected_menues[] = $item;
					if ( ! empty( $item['menu_slug'] ) ) {
						$selected_slugs[] = $item['menu_slug'];
					}
				}
			}
		}

		// 全ルートメニュー項目を復元・取得
		$available_menues = $this->get_clean_root_menues( $all_selected_menues, $menu_folders );

		?>
		<div class="wrap amf-settings-wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Admin Menu Folder Settings', 'admin-menu-folder' ); ?></h1>
			<?php
			$reset_button_html = sprintf(
				'<form id="amf-reset-form" method="post" action="%s" style="display:inline-block;margin-left:15px;">' .
				'<input type="hidden" name="action" value="amf_reset_settings" />' .
				'%s' .
				'<button type="button" id="amf-reset-settings-btn" class="page-title-action">%s</button>' .
				'</form>',
				esc_url( admin_url( 'options-general.php?page=admin-menu-folder' ) ),
				wp_nonce_field( 'amf_reset_settings_nonce', 'amf_reset_nonce', true, false ),
				esc_html__( 'Reset to Default', 'admin-menu-folder' )
			);

			$header_actions_html = apply_filters( 'amf_render_header_actions', $reset_button_html, count( $menu_folders ), $max_folders );
			echo $header_actions_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			?>
			<hr class="wp-header-end">

			<?php if ( isset( $_GET['amf_updated'] ) && 'true' === $_GET['amf_updated'] ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Settings saved.', 'admin-menu-folder' ); ?></p>
				</div>
			<?php endif; ?>

			<?php if ( isset( $_GET['amf_reset'] ) && 'true' === $_GET['amf_reset'] ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Settings reset to default.', 'admin-menu-folder' ); ?></p>
				</div>
			<?php endif; ?>

			<form method="post" action="" id="amf-settings-form">
				<?php wp_nonce_field( 'amf_save_settings_action', 'amf_save_settings_nonce' ); ?>
				<input type="hidden" name="amf_folders_json" id="amf_folders_json" value="<?php echo esc_attr( wp_json_encode( $menu_folders ) ); ?>">

				<div class="amf-settings-container">
					<!-- 左側: 擬似WPサイドメニュー（保護対象は disabled 化） -->
					<div class="amf-pseudo-sidebar-wrapper">
						<div class="amf-pseudo-sidebar">
							<ul class="amf-pseudo-menu-list">
								<?php foreach ( $available_menues as $item ) : ?>
									<?php
									$slug                  = $item['slug'];
									$title                 = $item['title'];
									$icon_html             = $item['icon_html'];
									$icon_class            = $item['icon_class'];
									$position              = $item['position'];
									$is_checked            = in_array( $slug, $selected_slugs, true );
									$is_disabled           = in_array( $slug, $protected_slugs, true ) || ! empty( $item['is_folder'] ) || str_starts_with( $slug, 'amf-folder' );
									$is_active_folder_item = $is_disabled && ( 'amf-folder' === $slug || 'folder_default' === $slug );
									?>
									<li class="amf-pseudo-menu-item <?php echo $is_checked ? 'is-selected' : ''; ?> <?php echo $is_disabled ? 'is-disabled' : ''; ?> <?php echo $is_active_folder_item ? 'is-selected-folder-active' : ''; ?>"
										data-slug="<?php echo esc_attr( $slug ); ?>"
										data-title="<?php echo esc_attr( wp_strip_all_tags( $title ) ); ?>"
										data-url="<?php echo esc_attr( $item['url'] ); ?>"
										data-position="<?php echo esc_attr( $position ); ?>"
										data-icon-class="<?php echo esc_attr( $icon_class ); ?>"
									>
										<div class="amf-menu-label">
											<span class="amf-menu-icon"><?php echo $icon_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
											<span class="amf-menu-title"><?php echo wp_kses_post( $title ); ?></span>
										</div>
										<div class="amf-menu-checkbox">
											<input type="checkbox"
												class="amf-item-toggle"
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

					<!-- 右側: サブメニューフォルダー プレビュー・マルチグリッドエリア -->
					<div class="amf-preview-container">
						<div class="amf-folders-grid" id="amf-folders-grid">
							<?php foreach ( $menu_folders as $f_idx => $folder ) : ?>
								<?php
								$f_id      = $folder['id'] ?? ( 'folder_' . $f_idx );
								$f_title   = $folder['title'] ?? 'Menu Folder';
								$f_icon    = ! empty( $folder['icon'] ) ? $folder['icon'] : 'dashicons-category';
								$f_items   = $folder['menues'] ?? array();
								$is_active = ( 0 === $f_idx );
								?>
								<div class="amf-folder-card <?php echo $is_active ? 'is-active' : ''; ?>" data-folder-id="<?php echo esc_attr( $f_id ); ?>" data-icon="<?php echo esc_attr( $f_icon ); ?>">
									<div class="amf-folder-header">
										<div class="amf-folder-header-left">
											<span class="amf-folder-icon"><?php echo $this->build_icon_html( $f_icon ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
											<span class="amf-folder-title"><?php echo esc_html( $f_title ); ?></span>
										</div>
										<div class="amf-folder-header-actions">
											<?php do_action( 'amf_render_folder_header_actions', $folder, $f_idx, count( $menu_folders ) ); ?>
										</div>
									</div>
									<ul class="amf-folder-sublist">
										<?php if ( empty( $f_items ) ) : ?>
											<li class="amf-empty-notice"><?php esc_html_e( 'No menu items selected', 'admin-menu-folder' ); ?></li>
										<?php else : ?>
											<?php foreach ( $f_items as $index => $sub_item ) : ?>
												<li class="amf-subitem-row"
													data-slug="<?php echo esc_attr( $sub_item['menu_slug'] ); ?>"
													data-title="<?php echo esc_attr( $sub_item['title'] ); ?>"
													data-url="<?php echo esc_attr( $sub_item['data']['url'] ?? '' ); ?>"
													data-position="<?php echo esc_attr( $sub_item['data']['original_position'] ?? 999 ); ?>"
													data-icon-class="<?php echo esc_attr( $sub_item['data']['icon_class'] ?? '' ); ?>"
												>
													<div class="amf-subitem-title">
														<span class="amf-menu-icon"><?php echo $this->build_icon_html( $sub_item['data']['icon_class'] ?? '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
														<span><?php echo esc_html( $sub_item['title'] ); ?></span>
													</div>
													<?php
													$remove_button_html   = sprintf(
														'<button type="button" class="button button-small amf-item-remove-btn" title="%s"><span class="dashicons dashicons-no-alt"></span></button>',
														esc_attr__( 'Remove Item', 'admin-menu-folder' )
													);
													$subitem_buttons_html = apply_filters( 'amf_render_subitem_buttons', $remove_button_html, $sub_item, $index, count( $f_items ) );
													?>
													<div class="amf-subitem-actions">
														<?php echo $subitem_buttons_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
													</div>
												</li>
											<?php endforeach; ?>
										<?php endif; ?>
									</ul>
									<?php do_action( 'amf_render_folder_footer_actions', $f_idx, count( $menu_folders ) ); ?>
								</div>
							<?php endforeach; ?>
						</div>

						<div class="amf-setting-options-card">
							<div class="amf-setting-option-row">
								<label for="amf_show_admin_bar_link" class="amf-setting-option-label">
									<?php esc_html_e( 'Show link to settings page in Toolbar:', 'admin-menu-folder' ); ?>
								</label>
								<?php $show_admin_bar_link = ! empty( $options['show_admin_bar_link'] ); ?>
								<input type="checkbox" name="amf_show_admin_bar_link" id="amf_show_admin_bar_link" value="1" <?php checked( $show_admin_bar_link ); ?>>
							</div>

							<div class="amf-setting-option-row">
								<label for="amf_setting_link_position" class="amf-setting-option-label">
									<?php esc_html_e( 'Add settings menu to Menu Folder:', 'admin-menu-folder' ); ?>
								</label>
								<?php $setting_link_pos = $options['setting_link_position'] ?? 'none'; ?>
								<select name="amf_setting_link_position" id="amf_setting_link_position" class="amf-setting-option-select">
									<option value="none" <?php selected( $setting_link_pos, 'none' ); ?>><?php esc_html_e( 'Do not add', 'admin-menu-folder' ); ?></option>
									<option value="first" <?php selected( $setting_link_pos, 'first' ); ?>><?php esc_html_e( 'Add to top', 'admin-menu-folder' ); ?></option>
									<option value="last" <?php selected( $setting_link_pos, 'last' ); ?>><?php esc_html_e( 'Add to bottom', 'admin-menu-folder' ); ?></option>
								</select>
							</div>
						</div>

						<div class="amf-form-actions">
							<input type="submit" name="amf_save_settings" class="button button-primary button-large" value="<?php esc_attr_e( 'Save Changes', 'admin-menu-folder' ); ?>">
						</div>
					</div>
				</div>
			</form>
		</div>

		<?php do_action( 'amf_render_admin_modal' ); ?>
		<?php
	}

	/**
	 * グローバル $menu および格離された選択済み項目を「元の位置」と「元のアイコン」で忠実に完全再構成
	 *
	 * @param array $selected_menues 保存されている選択項目.
	 * @param array $menu_folders 保存されている全サブメニューフォルダー構造.
	 * @return array
	 */
	private function get_clean_root_menues( array $selected_menues, array $menu_folders = array() ): array {
		$items_by_position = array();
		$raw_menu           = $GLOBALS['menu'] ?? array();
		$existing_slugs     = array();

		// 1. 現在除外されている選択済みスラグと position のマップを作成
		$selected_slugs_map = array();
		foreach ( $selected_menues as $sel ) {
			if ( ! empty( $sel['menu_slug'] ) ) {
				$pos = isset( $sel['data']['original_position'] ) ? (float) $sel['data']['original_position'] : 999.0;
				$selected_slugs_map[ $sel['menu_slug'] ] = $pos;
			}
		}

		// 2. 現在の $menu から未選択項目を取得 (WPの標準位置 $pos をそのまま絶対基準位置として利用)
		foreach ( $raw_menu as $pos => $item ) {
			if ( empty( $item[0] ) || ( isset( $item[4] ) && strpos( $item[4], 'wp-menu-separator' ) !== false ) ) {
				continue;
			}

			$slug        = $item[2];
			$clean_title = trim( preg_replace( '/\s*<span.*?>.*?<\/span>/i', '', $item[0] ) );
			$icon_class  = $item[6] ?? 'dashicons-admin-generic';

			$url = $slug;
			if ( ! str_contains( $slug, '.php' ) && ! str_contains( $slug, 'http' ) ) {
				$url = 'admin.php?page=' . $slug;
			}

			$items_by_position[] = array(
				'slug'       => $slug,
				'title'      => $clean_title,
				'url'        => $url,
				'position'   => (float) $pos,
				'icon_class' => $icon_class,
				'icon_html'  => $this->build_icon_html( $icon_class ),
			);

			$existing_slugs[] = $slug;
		}

		// 3. 現在フィルタリングにより $menu から除外されている選択済みメニュー項目を「元の位置」「元のアイコン」で復元マージ
		foreach ( $selected_menues as $sel ) {
			$sel_slug = $sel['menu_slug'] ?? '';
			if ( ! empty( $sel_slug ) && ! in_array( $sel_slug, $existing_slugs, true ) ) {
				$pos        = isset( $sel['data']['original_position'] ) ? (float) $sel['data']['original_position'] : 999.0;
				$icon_class = $sel['data']['icon_class'] ?? 'dashicons-admin-generic';

				$url = $sel['data']['url'] ?? $sel_slug;
				if ( ! str_contains( $url, '.php' ) && ! str_contains( $url, 'http' ) ) {
					$url = 'admin.php?page=' . $url;
				}

				$items_by_position[] = array(
					'slug'       => $sel_slug,
					'title'      => $sel['title'] ?? $sel_slug,
					'url'        => $url,
					'position'   => $pos,
					'icon_class' => $icon_class,
					'icon_html'  => $this->build_icon_html( $icon_class ),
				);

				$existing_slugs[] = $sel_slug;
			}
		}

		// 3. サブメニューフォルダー自体を擬似サイドメニュー項目として順序通りに復元マージ
		$default_folders_to_merge = ! empty( $menu_folders[0] ) ? array( $menu_folders[0] ) : array();
		$folders_to_merge         = apply_filters( 'amf_get_clean_root_menues_folders', $default_folders_to_merge, $menu_folders );
		$positions_list           = array_column( $items_by_position, 'position' );
		$max_existing             = ! empty( $positions_list ) ? (float) max( $positions_list ) : 999.0;
		$base_pos                 = (float) max( 999000.0, ceil( $max_existing ) + 100.0 );

		foreach ( $folders_to_merge as $idx => $folder ) {
			if ( empty( $folder ) ) {
				continue;
			}
			$f_id    = $folder['id'] ?? ( 'folder_' . $idx );
			$f_slug  = ( 0 === $idx ) ? 'amf-folder' : ( 'amf-folder-' . $f_id );
			$f_title = $folder['title'] ?? 'Menu Folder';
			$f_icon  = ! empty( $folder['icon'] ) ? $folder['icon'] : 'dashicons-category';
			$f_pos   = $base_pos + (float) $idx;

			// 既存のリストから途中に挟まっているフォルダー項目を除去
			foreach ( $items_by_position as $k => $v ) {
				if ( isset( $v['slug'] ) && ( $v['slug'] === $f_slug || $v['slug'] === $f_id ) ) {
					unset( $items_by_position[ $k ] );
				}
			}

			$items_by_position[] = array(
				'slug'       => $f_slug,
				'title'      => $f_title,
				'url'        => 'admin.php?page=' . $f_slug,
				'position'   => $f_pos,
				'icon_class' => $f_icon,
				'icon_html'  => $this->build_icon_html( $f_icon ),
				'is_folder'  => true,
			);

			$existing_slugs[] = $f_slug;
		}

		// 4. 元の位置 (position) の数値による厳密な昇順ソート (配列キーの型キャスト揺れ防止)
		usort(
			$items_by_position,
			function ( $a, $b ) {
				$pos_a = (float) ( $a['position'] ?? 999.0 );
				$pos_b = (float) ( $b['position'] ?? 999.0 );
				return $pos_a <=> $pos_b;
			}
		);

		return $items_by_position;
	}

	/**
	 * メニュー項目に応じた正しいアイコンHTMLを構築
	 *
	 * @param string $icon_class アイコン指定文字列.
	 * @return string
	 */
	private function build_icon_html( string $icon_class ): string {
		if ( str_contains( $icon_class, 'dashicons-' ) ) {
			return sprintf( '<span class="dashicons %s"></span>', esc_attr( $icon_class ) );
		}

		if ( str_starts_with( $icon_class, 'data:image/' ) ) {
			return sprintf( '<img src="%s" alt="" class="amf-custom-icon" style="width:18px;height:18px;vertical-align:middle;" />', esc_attr( $icon_class ) );
		}

		if ( str_contains( $icon_class, 'http://' ) || str_contains( $icon_class, 'https://' ) ) {
			return sprintf( '<img src="%s" alt="" class="amf-custom-icon" style="width:18px;height:18px;vertical-align:middle;" />', esc_url( $icon_class ) );
		}

		if ( str_contains( $icon_class, 'svg' ) ) {
			return sprintf( '<span class="amf-svg-icon" style="width:18px;height:18px;display:inline-flex;">%s</span>', wp_kses_post( $icon_class ) );
		}

		return '<span class="dashicons dashicons-admin-generic"></span>';
	}
}

