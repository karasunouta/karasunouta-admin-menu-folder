<?php
/**
 * KuSubmenuFolder Settings Page Class
 *
 * @package KuSubmenuFolder
 */

namespace karasunouta\KuSubmenuFolder;

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
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'kusf_render_header_action_button', array( $this, 'render_default_header_button' ), 10, 3 );
	}

	/**
	 * 通常版デフォルトの「＋ サブメニューフォルダーを追加 [PRO]」ボタン描画
	 *
	 * @param bool $is_pro Pro版が有効か.
	 * @param int  $folder_count 現在のフォルダー数.
	 * @param int  $max_folders 最大フォルダー数.
	 */
	public function render_default_header_button( bool $is_pro, int $folder_count, int $max_folders ) {
		if ( ! $is_pro ) {
			?>
			<button type="button" id="kusf-add-folder-btn" class="button button-secondary kusf-add-btn is-disabled-pro" disabled>
				<span class="dashicons dashicons-plus-alt2"></span> <?php esc_html_e( 'Add Submenu Folder', 'ku-submenu-folder' ); ?>
				<span class="kusf-pro-badge">PRO</span>
			</button>
			<?php
		}
	}

	/**
	 * メニューページの登録
	 */
	public function register_menu_page() {
		// 「設定」の配下に設定ページを追加
		add_options_page(
			__( 'KU Submenu Folder', 'ku-submenu-folder' ),
			__( 'KU Submenu Folder', 'ku-submenu-folder' ),
			'manage_options',
			'ku-submenu-folder',
			array( $this, 'render_settings_page' )
		);

		// トップレベル「KU Submenu」フォルダメニューを追加 (最下部付近 9999)
		add_menu_page(
			__( 'KU Submenu', 'ku-submenu-folder' ),
			__( 'KU Submenu', 'ku-submenu-folder' ),
			'manage_options',
			'ku-submenu',
			'__return_null',
			'dashicons-category',
			9999
		);
	}

	/**
	 * フォーム送信（保存）の早期ハンドリング（admin_init）
	 * 保護対象スラグのサニタイズおよびPro停止時デデュープ（自動重複除去）
	 */
	public function handle_save_settings() {
		if ( ! isset( $_POST['kusf_save_settings'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to perform this action.', 'ku-submenu-folder' ) );
		}

		check_admin_referer( 'kusf_save_settings_action', 'kusf_save_settings_nonce' );

		$saved_folders_json = isset( $_POST['kusf_folders_json'] ) ? sanitize_text_field( wp_unslash( $_POST['kusf_folders_json'] ) ) : '[]';
		$decoded_folders    = json_decode( $saved_folders_json, true );

		if ( is_array( $decoded_folders ) ) {
			$is_pro          = $this->main->is_pro();
			$max_folders     = $this->main->get_max_folders();
			$max_items       = $this->main->get_max_items();
			$protected_slugs = $this->main->get_protected_slugs();

			// フィルターフック経由でサニタイズ（Pro版有効時はPro版側が全フォルダーをサニタイズ処理）
			$pro_sanitized_folders = apply_filters(
				'kusf_sanitize_folders_for_save',
				null,
				$decoded_folders,
				$max_folders,
				$max_items,
				$protected_slugs
			);

			if ( is_array( $pro_sanitized_folders ) ) {
				if ( empty( $pro_sanitized_folders ) ) {
					$pro_sanitized_folders = array(
						array(
							'id'       => 'folder_default',
							'title'    => 'KU Submenu',
							'icon'     => 'dashicons-category',
							'position' => 99,
							'menues'   => array(),
						),
					);
				}

				$show_admin_bar_link = isset( $_POST['kusf_show_admin_bar_link'] ) ? 1 : 0;
				$setting_link_pos    = isset( $_POST['kusf_setting_link_position'] ) ? sanitize_text_field( wp_unslash( $_POST['kusf_setting_link_position'] ) ) : 'none';
				if ( ! in_array( $setting_link_pos, array( 'none', 'first', 'last' ), true ) ) {
					$setting_link_pos = 'none';
				}

				$options                          = $this->main->get_raw_options();
				$options['sub_menues']            = $pro_sanitized_folders;
				$options['show_admin_bar_link']   = (bool) $show_admin_bar_link;
				$options['setting_link_position'] = $setting_link_pos;
				$this->main->save_options( $options );

			} else {
				// 通常版動作時: 1フォルダー目のみサニタイズ更新 + 休眠中フォルダーからの重複自動除去（デデュープ）
				$raw_options = $this->main->get_raw_options();
				$sub_menues  = $raw_options['sub_menues'] ?? array();

				$first_folder_data = $decoded_folders[0] ?? array();
				$raw_menues        = isset( $first_folder_data['menues'] ) && is_array( $first_folder_data['menues'] ) ? $first_folder_data['menues'] : array();

				$sanitized_first_items = array();
				$first_slugs           = array();
				$item_count            = 0;

				foreach ( $raw_menues as $item ) {
					if ( $item_count >= $max_items ) {
						break;
					}
					$slug = sanitize_text_field( $item['menu_slug'] ?? '' );

					if ( ! empty( $slug ) && ! in_array( $slug, $protected_slugs, true ) ) {
						$sanitized_first_items[] = array(
							'menu_slug' => $slug,
							'title'     => sanitize_text_field( $item['title'] ?? '' ),
							'order'     => (int) ( $item['order'] ?? $item_count ),
							'data'      => array(
								'url'               => sanitize_text_field( $item['data']['url'] ?? $item['url'] ?? '' ),
								'original_position' => isset( $item['data']['original_position'] ) ? (float) $item['data']['original_position'] : (isset( $item['position'] ) ? (float) $item['position'] : 999.0),
								'icon_class'        => sanitize_text_field( $item['data']['icon_class'] ?? $item['icon_class'] ?? '' ),
							),
						);
						$first_slugs[]           = $slug;
						$item_count++;
					}
				}

				// 生データの1フォルダー目を更新 (名前・アイコンはデフォルト保持)
				if ( ! isset( $sub_menues[0] ) || ! is_array( $sub_menues[0] ) ) {
					$sub_menues[0] = array(
						'id'       => 'folder_default',
						'title'    => 'KU Submenu',
						'icon'     => 'dashicons-category',
						'position' => 99,
						'menues'   => $sanitized_first_items,
					);
				} else {
					$sub_menues[0]['menues'] = $sanitized_first_items;
				}

				// 休眠中（2フォルダー目以降）から $first_slugs に含まれる重複項目を自動引き抜き
				if ( count( $sub_menues ) > 1 ) {
					for ( $i = 1; $i < count( $sub_menues ); $i++ ) {
						if ( ! empty( $sub_menues[ $i ]['menues'] ) && is_array( $sub_menues[ $i ]['menues'] ) ) {
							$cleaned_items = array();
							foreach ( $sub_menues[ $i ]['menues'] as $m_item ) {
								if ( ! in_array( $m_item['menu_slug'], $first_slugs, true ) ) {
									$cleaned_items[] = $m_item;
								}
							}
							$sub_menues[ $i ]['menues'] = array_values( $cleaned_items );
						}
					}
				}

				$show_admin_bar_link = isset( $_POST['kusf_show_admin_bar_link'] ) ? 1 : 0;
				$setting_link_pos    = isset( $_POST['kusf_setting_link_position'] ) ? sanitize_text_field( wp_unslash( $_POST['kusf_setting_link_position'] ) ) : 'none';
				if ( ! in_array( $setting_link_pos, array( 'none', 'first', 'last' ), true ) ) {
					$setting_link_pos = 'none';
				}

				$raw_options['sub_menues']            = array_values( $sub_menues );
				$raw_options['show_admin_bar_link']   = (bool) $show_admin_bar_link;
				$raw_options['setting_link_position'] = $setting_link_pos;
				$this->main->save_options( $raw_options );
			}

			// リダイレクトして即座に画面全体を最新状態で再読み込み
			wp_safe_redirect( admin_url( 'options-general.php?page=ku-submenu-folder&kusf_updated=true' ) );
			exit;
		}
	}

	/**
	 * アセット（CSS / JS）の読み込み
	 *
	 * @param string $hook_suffix 現在の管理画面フック名.
	 */
	public function enqueue_assets( $hook_suffix ) {
		$asset_file = KUSF_PLUGIN_DIR . 'build/admin-settings.asset.php';
		if ( file_exists( $asset_file ) ) {
			$asset_info = require $asset_file;
			$deps       = $asset_info['dependencies'] ?? array();
			$version    = $asset_info['version'] ?? KUSF_VERSION;
		} else {
			$deps    = array();
			$version = KUSF_VERSION;
		}

		// JSは全管理画面でEnqueue（親リンク href の書き換え・クリック補正のため）
		wp_enqueue_script(
			'kusf-admin-settings-js',
			KUSF_PLUGIN_URL . 'build/admin-settings.js',
			$deps,
			$version,
			true
		);

		wp_localize_script(
			'kusf-admin-settings-js',
			'kusfParams',
			array(
				'isPro'           => $this->main->is_pro(),
				'maxFolders'      => $this->main->get_max_folders(),
				'maxItems'        => $this->main->get_max_items(),
				'nonce'           => wp_create_nonce( 'kusf_save_settings_nonce' ),
				'settingsUrl'     => admin_url( 'options-general.php?page=ku-submenu-folder' ),
				'protectedSlugs'  => $this->main->get_protected_slugs(),
				'moveUp'          => __( 'Move Up', 'ku-submenu-folder' ),
				'moveDown'        => __( 'Move Down', 'ku-submenu-folder' ),
				'moveLeft'        => __( 'Move Left', 'ku-submenu-folder' ),
				'moveRight'       => __( 'Move Right', 'ku-submenu-folder' ),
				'editFolder'      => __( 'Edit Folder', 'ku-submenu-folder' ),
				'deleteFolder'    => __( 'Delete Folder', 'ku-submenu-folder' ),
				'removeItem'      => __( 'Remove Item', 'ku-submenu-folder' ),
				'confirmDelete'   => __( 'Are you sure you want to delete this folder? Stored menu items will be restored.', 'ku-submenu-folder' ),
				'limitMessage'    => sprintf(
					/* translators: %d: Maximum allowed items */
					__( 'You can store up to %d menu items per folder.', 'ku-submenu-folder' ),
					$this->main->get_max_items()
				),
				'noItemsSelected' => __( 'No menu items selected', 'ku-submenu-folder' ),
				'folderLimitMsg'  => sprintf(
					/* translators: %d: Maximum allowed folders */
					__( 'You can create up to %d submenu folders in Pro version.', 'ku-submenu-folder' ),
					$this->main->get_max_folders()
				),
			)
		);

		// CSSは設定画面のみで読込
		if ( strpos( $hook_suffix, 'ku-submenu-folder' ) !== false ) {
			wp_enqueue_style(
				'kusf-admin-settings-css',
				KUSF_PLUGIN_URL . 'build/admin-settings.css',
				array(),
				$version
			);
		}
	}

	/**
	 * 設定ページの描画処理
	 */
	/**
	 * 設定ページの描画処理
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'ku-submenu-folder' ) );
		}

		$options         = $this->main->get_options();
		$sub_menues      = $options['sub_menues'] ?? array();
		$is_pro          = $this->main->is_pro();
		$max_folders     = $this->main->get_max_folders();
		$protected_slugs = $this->main->get_protected_slugs();

		// 全フォルダーの選択済みメニュー項目を統合収集
		$all_selected_menues = array();
		$selected_slugs      = array();

		foreach ( $sub_menues as $folder ) {
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
		$available_menues = $this->get_clean_root_menues( $all_selected_menues, $sub_menues );

		?>
		<div class="wrap kusf-settings-wrap">
			<div class="kusf-header-area">
				<h1 class="wp-heading-inline"><?php esc_html_e( 'KU Submenu Folder Settings', 'ku-submenu-folder' ); ?></h1>
				<?php do_action( 'kusf_render_header_action_button', $is_pro, count( $sub_menues ), $max_folders ); ?>
			</div>
			<hr class="wp-header-end">

			<?php if ( isset( $_GET['kusf_updated'] ) && 'true' === $_GET['kusf_updated'] ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Settings saved.', 'ku-submenu-folder' ); ?></p>
				</div>
			<?php endif; ?>

			<form method="post" action="" id="kusf-settings-form">
				<?php wp_nonce_field( 'kusf_save_settings_action', 'kusf_save_settings_nonce' ); ?>
				<input type="hidden" name="kusf_folders_json" id="kusf_folders_json" value="<?php echo esc_attr( wp_json_encode( $sub_menues ) ); ?>">

				<div class="kusf-settings-container">
					<!-- 左側: 擬似WPサイドメニュー（保護対象は disabled 化） -->
					<div class="kusf-pseudo-sidebar-wrapper">
						<div class="kusf-pseudo-sidebar">
							<ul class="kusf-pseudo-menu-list">
								<?php foreach ( $available_menues as $item ) : ?>
									<?php
									$slug                  = $item['slug'];
									$title                 = $item['title'];
									$icon_html             = $item['icon_html'];
									$icon_class            = $item['icon_class'];
									$position              = $item['position'];
									$is_checked            = in_array( $slug, $selected_slugs, true );
									$is_disabled           = in_array( $slug, $protected_slugs, true ) || ! empty( $item['is_folder'] ) || str_starts_with( $slug, 'ku-submenu' );
									$is_active_folder_item = $is_disabled && ( 'ku-submenu' === $slug || 'folder_default' === $slug );
									?>
									<li class="kusf-pseudo-menu-item <?php echo $is_checked ? 'is-selected' : ''; ?> <?php echo $is_disabled ? 'is-disabled' : ''; ?> <?php echo $is_active_folder_item ? 'is-selected-folder-active' : ''; ?>"
										data-slug="<?php echo esc_attr( $slug ); ?>"
										data-title="<?php echo esc_attr( wp_strip_all_tags( $title ) ); ?>"
										data-url="<?php echo esc_attr( $item['url'] ); ?>"
										data-position="<?php echo esc_attr( $position ); ?>"
										data-icon-class="<?php echo esc_attr( $icon_class ); ?>"
									>
										<div class="kusf-menu-label">
											<span class="kusf-menu-icon"><?php echo $icon_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
											<span class="kusf-menu-title"><?php echo wp_kses_post( $title ); ?></span>
										</div>
										<div class="kusf-menu-checkbox">
											<input type="checkbox"
												class="kusf-item-toggle"
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
					<div class="kusf-preview-container">
						<div class="kusf-folders-grid" id="kusf-folders-grid">
							<?php foreach ( $sub_menues as $f_idx => $folder ) : ?>
								<?php
								$f_id    = $folder['id'] ?? ( 'folder_' . $f_idx );
								$f_title = $folder['title'] ?? 'KU Submenu';
								$f_icon  = ! empty( $folder['icon'] ) ? $folder['icon'] : 'dashicons-category';
								$f_items = $folder['menues'] ?? array();
								$is_active = ( 0 === $f_idx );
								?>
								<div class="kusf-folder-card <?php echo $is_active ? 'is-active' : ''; ?>" data-folder-id="<?php echo esc_attr( $f_id ); ?>" data-icon="<?php echo esc_attr( $f_icon ); ?>">
									<div class="kusf-folder-header">
										<div class="kusf-folder-header-left">
											<span class="kusf-folder-icon"><?php echo $this->build_icon_html( $f_icon ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
											<span class="kusf-folder-title"><?php echo esc_html( $f_title ); ?></span>
										</div>
										<div class="kusf-folder-header-actions">
											<button type="button" class="kusf-folder-edit-btn <?php echo ( ! $is_pro && $f_idx > 0 ) ? 'is-disabled-pro' : ''; ?>" title="<?php esc_attr_e( 'Edit Folder', 'ku-submenu-folder' ); ?>" <?php echo ( ! $is_pro || $f_idx > 0 ) ? 'disabled' : ''; ?>>
												<span class="dashicons dashicons-edit"></span>
											</button>
											<button type="button" class="kusf-folder-delete-btn <?php echo ( ! $is_pro || 0 === $f_idx ) ? 'is-disabled-pro' : ''; ?>" title="<?php esc_attr_e( 'Delete Folder', 'ku-submenu-folder' ); ?>" <?php echo ( ! $is_pro || 0 === $f_idx ) ? 'disabled' : ''; ?>>
												<span class="dashicons dashicons-trash"></span>
											</button>
										</div>
									</div>
									<ul class="kusf-folder-sublist">
										<?php if ( empty( $f_items ) ) : ?>
											<li class="kusf-empty-notice"><?php esc_html_e( 'No menu items selected', 'ku-submenu-folder' ); ?></li>
										<?php else : ?>
											<?php foreach ( $f_items as $index => $sub_item ) : ?>
												<li class="kusf-subitem-row"
													data-slug="<?php echo esc_attr( $sub_item['menu_slug'] ); ?>"
													data-title="<?php echo esc_attr( $sub_item['title'] ); ?>"
													data-url="<?php echo esc_attr( $sub_item['data']['url'] ?? '' ); ?>"
													data-position="<?php echo esc_attr( $sub_item['data']['original_position'] ?? 999 ); ?>"
													data-icon-class="<?php echo esc_attr( $sub_item['data']['icon_class'] ?? '' ); ?>"
												>
													<div class="kusf-subitem-title">
														<span class="kusf-menu-icon"><?php echo $this->build_icon_html( $sub_item['data']['icon_class'] ?? '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
														<span><?php echo esc_html( $sub_item['title'] ); ?></span>
													</div>
													<div class="kusf-subitem-actions">
														<button type="button" class="button button-small kusf-move-up <?php echo ! $is_pro ? 'is-disabled-pro' : ''; ?>" title="<?php echo esc_attr( ! $is_pro ? __( 'Reordering is available in Pro version', 'ku-submenu-folder' ) : __( 'Move Up', 'ku-submenu-folder' ) ); ?>" <?php echo ( ! $is_pro || 0 === $index ) ? 'disabled' : ''; ?>>
															<span class="dashicons dashicons-arrow-up-alt2"></span>
														</button>
														<button type="button" class="button button-small kusf-move-down <?php echo ! $is_pro ? 'is-disabled-pro' : ''; ?>" title="<?php echo esc_attr( ! $is_pro ? __( 'Reordering is available in Pro version', 'ku-submenu-folder' ) : __( 'Move Down', 'ku-submenu-folder' ) ); ?>" <?php echo ( ! $is_pro || count( $f_items ) - 1 === $index ) ? 'disabled' : ''; ?>>
															<span class="dashicons dashicons-arrow-down-alt2"></span>
														</button>
														<!-- 通常版・Pro版共通: 格納済みメニュー項目の解約ボタン -->
														<button type="button" class="button button-small kusf-item-remove-btn" title="<?php esc_attr_e( 'Remove Item', 'ku-submenu-folder' ); ?>">
															<span class="dashicons dashicons-no-alt"></span>
														</button>
													</div>
												</li>
											<?php endforeach; ?>
										<?php endif; ?>
									</ul>
									<?php if ( ! $is_pro ) : ?>
										<div class="kusf-pro-notice-box">
											<span class="kusf-pro-badge">PRO</span>
											<span class="kusf-pro-notice-text"><?php esc_html_e( 'Reordering menu items and creating multiple folders are available in Pro version.', 'ku-submenu-folder' ); ?></span>
										</div>
									<?php endif; ?>
									<div class="kusf-folder-footer-actions">
										<button type="button" class="button kusf-folder-move-left <?php echo ! $is_pro ? 'is-disabled-pro' : ''; ?>" title="<?php esc_attr_e( 'Move Left', 'ku-submenu-folder' ); ?>" <?php echo ( ! $is_pro || 0 === $f_idx ) ? 'disabled' : ''; ?>>
											<span class="dashicons dashicons-arrow-left-alt2"></span>
										</button>
										<button type="button" class="button kusf-folder-move-right <?php echo ! $is_pro ? 'is-disabled-pro' : ''; ?>" title="<?php esc_attr_e( 'Move Right', 'ku-submenu-folder' ); ?>" <?php echo ( ! $is_pro || count( $sub_menues ) - 1 === $f_idx ) ? 'disabled' : ''; ?>>
											<span class="dashicons dashicons-arrow-right-alt2"></span>
										</button>
									</div>
								</div>
							<?php endforeach; ?>
						</div>

						<div class="kusf-setting-options-card">
							<div class="kusf-setting-option-row">
								<label for="kusf_show_admin_bar_link" class="kusf-setting-option-label">
									<?php esc_html_e( 'Show link to settings page in Toolbar:', 'ku-submenu-folder' ); ?>
								</label>
								<?php $show_admin_bar_link = ! empty( $options['show_admin_bar_link'] ); ?>
								<input type="checkbox" name="kusf_show_admin_bar_link" id="kusf_show_admin_bar_link" value="1" <?php checked( $show_admin_bar_link ); ?>>
							</div>

							<div class="kusf-setting-option-row">
								<label for="kusf_setting_link_position" class="kusf-setting-option-label">
									<?php esc_html_e( 'Add settings menu to submenu folder:', 'ku-submenu-folder' ); ?>
								</label>
								<?php $setting_link_pos = $options['setting_link_position'] ?? 'none'; ?>
								<select name="kusf_setting_link_position" id="kusf_setting_link_position" class="kusf-setting-option-select">
									<option value="none" <?php selected( $setting_link_pos, 'none' ); ?>><?php esc_html_e( 'Do not add', 'ku-submenu-folder' ); ?></option>
									<option value="first" <?php selected( $setting_link_pos, 'first' ); ?>><?php esc_html_e( 'Add to top', 'ku-submenu-folder' ); ?></option>
									<option value="last" <?php selected( $setting_link_pos, 'last' ); ?>><?php esc_html_e( 'Add to bottom', 'ku-submenu-folder' ); ?></option>
								</select>
							</div>
						</div>

						<div class="kusf-form-actions">
							<input type="submit" name="kusf_save_settings" class="button button-primary button-large" value="<?php esc_attr_e( 'Save Changes', 'ku-submenu-folder' ); ?>">
						</div>
					</div>
				</div>
			</form>
		</div>

		<?php do_action( 'kusf_render_admin_modal' ); ?>
		<?php
	}

	/**
	 * グローバル $menu および格離された選択済み項目を「元の位置」と「元のアイコン」で忠実に完全再構成
	 *
	 * @param array $selected_menues 保存されている選択項目.
	 * @param array $sub_menues 保存されている全サブメニューフォルダー構造.
	 * @return array
	 */
	private function get_clean_root_menues( array $selected_menues, array $sub_menues = array() ): array {
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

		// 2. 現在の $menu から未選択項目を取得し、unset による前詰まりオフセットを自動計算・加算補正
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

			$calc_pos = (float) $pos;

			// この未選択項目より前の位置に存在した選択済み(unset済み)項目の件数を連動オフセットとして補正
			$offset = 0;
			foreach ( $selected_slugs_map as $sel_slug => $sel_pos ) {
				if ( $sel_pos <= ( $calc_pos + $offset ) ) {
					$offset++;
				}
			}
			$calc_pos += $offset;

			$items_by_position[] = array(
				'slug'       => $slug,
				'title'      => $clean_title,
				'url'        => $url,
				'position'   => $calc_pos,
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

		// 3. サブメニューフォルダー自体を擬似サイドメニュー項目として順序通りに復元マージ（Pro版有効時は全フォルダー対象）
		$default_folders_to_merge = ! empty( $sub_menues[0] ) ? array( $sub_menues[0] ) : array();
		$folders_to_merge         = apply_filters( 'kusf_get_clean_root_menues_folders', $default_folders_to_merge, $sub_menues );
		$positions_list           = array_column( $items_by_position, 'position' );
		$max_existing             = ! empty( $positions_list ) ? (float) max( $positions_list ) : 999.0;
		$base_pos                 = (float) max( 999000.0, ceil( $max_existing ) + 100.0 );

		foreach ( $folders_to_merge as $idx => $folder ) {
			if ( empty( $folder ) ) {
				continue;
			}
			$f_id    = $folder['id'] ?? ( 'folder_' . $idx );
			$f_slug  = ( 0 === $idx ) ? 'ku-submenu' : ( 'ku-submenu-' . $f_id );
			$f_title = $folder['title'] ?? 'KU Submenu';
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
			return sprintf( '<img src="%s" alt="" class="kusf-custom-icon" style="width:18px;height:18px;vertical-align:middle;" />', esc_attr( $icon_class ) );
		}

		if ( str_contains( $icon_class, 'http://' ) || str_contains( $icon_class, 'https://' ) ) {
			return sprintf( '<img src="%s" alt="" class="kusf-custom-icon" style="width:18px;height:18px;vertical-align:middle;" />', esc_url( $icon_class ) );
		}

		if ( str_contains( $icon_class, 'svg' ) ) {
			return sprintf( '<span class="kusf-svg-icon" style="width:18px;height:18px;display:inline-flex;">%s</span>', wp_kses_post( $icon_class ) );
		}

		return '<span class="dashicons dashicons-admin-generic"></span>';
	}
}
