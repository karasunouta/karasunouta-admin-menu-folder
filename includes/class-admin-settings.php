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
	 * 保護対象スラグのサニタイズ（サーバーサイドバリデーション）
	 */
	public function handle_save_settings() {
		if ( ! isset( $_POST['kusf_save_settings'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to perform this action.', 'ku-submenu-folder' ) );
		}

		check_admin_referer( 'kusf_save_settings_action', 'kusf_save_settings_nonce' );

		$saved_menues_json = isset( $_POST['kusf_selected_menues'] ) ? sanitize_text_field( wp_unslash( $_POST['kusf_selected_menues'] ) ) : '[]';
		$decoded_menues    = json_decode( $saved_menues_json, true );

		if ( is_array( $decoded_menues ) ) {
			$options         = $this->main->get_options();
			$max_items       = $this->main->get_max_items();
			$protected_slugs = $this->main->get_protected_slugs();
			$sanitized_items = array();

			$count = 0;
			foreach ( $decoded_menues as $item ) {
				if ( $count >= $max_items ) {
					break;
				}
				$slug = sanitize_text_field( $item['menu_slug'] ?? '' );

				// 保護対象スラグ（自己フォルダ・他フォルダ・設定画面等）が含まれている場合は即排除
				if ( ! empty( $slug ) && ! in_array( $slug, $protected_slugs, true ) ) {
					$sanitized_items[] = array(
						'menu_slug' => $slug,
						'title'     => sanitize_text_field( $item['title'] ?? '' ),
						'order'     => (int) ( $item['order'] ?? $count ),
						'data'      => array(
							'url'               => sanitize_text_field( $item['data']['url'] ?? $item['url'] ?? '' ),
							'original_position' => isset( $item['data']['original_position'] ) ? (float) $item['data']['original_position'] : (isset( $item['position'] ) ? (float) $item['position'] : 999.0),
							'icon_class'        => sanitize_text_field( $item['data']['icon_class'] ?? $item['icon_class'] ?? '' ),
						),
					);
					$count++;
				}
			}

			$options['sub_menues'][0]['menues'] = $sanitized_items;
			$this->main->save_options( $options );

			// リダイレクトして即座に画面全体を最新状態で再読み込み
			wp_safe_redirect( admin_url( 'options-general.php?page=ku-submenu-folder&updated=true' ) );
			exit;
		}
	}

	/**
	 * アセット（CSS / JS）の読み込み
	 *
	 * @param string $hook_suffix 現在の管理画面フック名.
	 */
	public function enqueue_assets( $hook_suffix ) {
		// JSは全管理画面でEnqueue（親リンク href の書き換え・クリック補正のため）
		wp_enqueue_script(
			'kusf-admin-settings-js',
			KUSF_PLUGIN_URL . 'assets/js/admin-settings.js',
			array(),
			KUSF_VERSION,
			true
		);

		wp_localize_script(
			'kusf-admin-settings-js',
			'kusfParams',
			array(
				'maxItems'       => $this->main->get_max_items(),
				'nonce'          => wp_create_nonce( 'kusf_save_settings_nonce' ),
				'settingsUrl'    => admin_url( 'options-general.php?page=ku-submenu-folder' ),
				'protectedSlugs' => $this->main->get_protected_slugs(),
				'moveUp'         => __( 'Move Up', 'ku-submenu-folder' ),
				'moveDown'       => __( 'Move Down', 'ku-submenu-folder' ),
				'limitMessage'   => sprintf(
					/* translators: %d: Maximum allowed items */
					__( 'You can store up to %d menu items in KU Submenu.', 'ku-submenu-folder' ),
					$this->main->get_max_items()
				),
			)
		);

		// CSSは設定画面のみで読込
		if ( strpos( $hook_suffix, 'ku-submenu-folder' ) !== false ) {
			wp_enqueue_style(
				'kusf-admin-settings-css',
				KUSF_PLUGIN_URL . 'assets/css/admin-settings.css',
				array(),
				KUSF_VERSION
			);
		}
	}

	/**
	 * 設定ページの描画処理
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'ku-submenu-folder' ) );
		}

		$options         = $this->main->get_options();
		$default_folder  = $options['sub_menues'][0] ?? array();
		$selected_menues = $default_folder['menues'] ?? array();
		$selected_slugs  = array_column( $selected_menues, 'menu_slug' );
		$protected_slugs = $this->main->get_protected_slugs();

		// 全ルートメニュー項目を復元・取得
		$available_menues = $this->get_clean_root_menues( $selected_menues );

		?>
		<div class="wrap kusf-settings-wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'KU Submenu Folder Settings', 'ku-submenu-folder' ); ?></h1>
			<hr class="wp-header-end">

			<?php if ( isset( $_GET['updated'] ) && 'true' === $_GET['updated'] ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Settings saved.', 'ku-submenu-folder' ); ?></p>
				</div>
			<?php endif; ?>

			<form method="post" action="" id="kusf-settings-form">
				<?php wp_nonce_field( 'kusf_save_settings_action', 'kusf_save_settings_nonce' ); ?>
				<input type="hidden" name="kusf_selected_menues" id="kusf_selected_menues" value="<?php echo esc_attr( wp_json_encode( $selected_menues ) ); ?>">

				<div class="kusf-settings-container">
					<!-- 左側: 擬似WPサイドメニュー（保護対象は disabled 化） -->
					<div class="kusf-pseudo-sidebar-wrapper">
						<div class="kusf-pseudo-sidebar">
							<ul class="kusf-pseudo-menu-list">
								<?php foreach ( $available_menues as $item ) : ?>
									<?php
									$slug        = $item['slug'];
									$title       = $item['title'];
									$icon_html   = $item['icon_html'];
									$icon_class  = $item['icon_class'];
									$position    = $item['position'];
									$is_checked  = in_array( $slug, $selected_slugs, true );
									$is_disabled = in_array( $slug, $protected_slugs, true );
									?>
									<li class="kusf-pseudo-menu-item <?php echo $is_checked ? 'is-selected' : ''; ?> <?php echo $is_disabled ? 'is-disabled' : ''; ?>"
										data-slug="<?php echo esc_attr( $slug ); ?>"
										data-title="<?php echo esc_attr( wp_strip_all_tags( $title ) ); ?>"
										data-url="<?php echo esc_attr( $item['url'] ); ?>"
										data-position="<?php echo esc_attr( $position ); ?>"
										data-icon-class="<?php echo esc_attr( $icon_class ); ?>"
									>
										<div class="kusf-menu-label">
											<span class="kusf-menu-icon"><?php echo $icon_html; ?></span>
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

					<!-- 右側: KU Submenu プレビュー・構造カスタマイザー -->
					<div class="kusf-preview-container">
						<div class="kusf-folder-card">
							<div class="kusf-folder-header">
								<span class="dashicons dashicons-category"></span>
								<span class="kusf-folder-title"><?php echo esc_html( $default_folder['title'] ?? 'KU Submenu' ); ?></span>
							</div>
							<ul class="kusf-folder-sublist" id="kusf-folder-sublist">
								<?php foreach ( $selected_menues as $index => $sub_item ) : ?>
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
											<button type="button" class="button button-small kusf-move-up" title="<?php esc_attr_e( 'Move Up', 'ku-submenu-folder' ); ?>" <?php echo 0 === $index ? 'disabled' : ''; ?>>
												<span class="dashicons dashicons-arrow-up-alt2"></span>
											</button>
											<button type="button" class="button button-small kusf-move-down" title="<?php esc_attr_e( 'Move Down', 'ku-submenu-folder' ); ?>" <?php echo ( count( $selected_menues ) - 1 === $index ) ? 'disabled' : ''; ?>>
												<span class="dashicons dashicons-arrow-down-alt2"></span>
											</button>
										</div>
									</li>
								<?php endforeach; ?>
							</ul>
						</div>

						<div class="kusf-form-actions">
							<input type="submit" name="kusf_save_settings" class="button button-primary button-large" value="<?php esc_attr_e( 'Save Changes', 'ku-submenu-folder' ); ?>">
						</div>
					</div>
				</div>
			</form>
		</div>
		<?php
	}

	/**
	 * グローバル $menu および格離された選択済み項目を「元の位置」と「元のアイコン」で忠実に完全再構成
	 *
	 * @param array $selected_menues 保存されている選択項目.
	 * @return array
	 */
	private function get_clean_root_menues( array $selected_menues ): array {
		$items_by_position = array();
		$raw_menu           = $GLOBALS['menu'] ?? array();
		$existing_slugs     = array();

		// 1. 現在の $menu から項目を取得
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

			$items_by_position[ (string) $pos ] = array(
				'slug'       => $slug,
				'title'      => $clean_title,
				'url'        => $url,
				'position'   => (float) $pos,
				'icon_class' => $icon_class,
				'icon_html'  => $this->build_icon_html( $icon_class ),
			);

			$existing_slugs[] = $slug;
		}

		// 2. 現在フィルタリングにより $menu から除外されている選択済みメニュー項目を「元の位置」「元のアイコン」で復元マージ
		foreach ( $selected_menues as $sel ) {
			$sel_slug = $sel['menu_slug'] ?? '';
			if ( ! empty( $sel_slug ) && ! in_array( $sel_slug, $existing_slugs, true ) ) {
				$pos        = isset( $sel['data']['original_position'] ) ? (float) $sel['data']['original_position'] : 999.0;
				$icon_class = $sel['data']['icon_class'] ?? 'dashicons-admin-generic';

				while ( isset( $items_by_position[ (string) $pos ] ) ) {
					$pos += 0.001;
				}

				$url = $sel['data']['url'] ?? $sel_slug;
				if ( ! str_contains( $url, '.php' ) && ! str_contains( $url, 'http' ) ) {
					$url = 'admin.php?page=' . $url;
				}

				$items_by_position[ (string) $pos ] = array(
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

		// 3. 元の位置 (position) の昇順でソート
		uksort(
			$items_by_position,
			function ( $a, $b ) {
				return (float) $a <=> (float) $b;
			}
		);

		return array_values( $items_by_position );
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

		if ( str_contains( $icon_class, 'data:image' ) || str_contains( $icon_class, 'http' ) ) {
			return sprintf( '<img src="%s" alt="" class="kusf-custom-icon" style="width:18px;height:18px;vertical-align:middle;" />', esc_url( $icon_class ) );
		}

		if ( str_contains( $icon_class, 'svg' ) ) {
			return sprintf( '<span class="kusf-svg-icon" style="width:18px;height:18px;display:inline-flex;">%s</span>', wp_kses_post( $icon_class ) );
		}

		return '<span class="dashicons dashicons-admin-generic"></span>';
	}
}
