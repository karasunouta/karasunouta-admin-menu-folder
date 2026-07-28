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
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * メニューページ・サブメニューページの登録
	 */
	public function register_menu_page() {
		// トップレベル「WP Sub Menu」の登録
		add_menu_page(
			'WP Sub Menu',
			'WP Sub Menu',
			'manage_options',
			'wp-sub-menu',
			array( $this, 'render_top_menu_page' ),
			'dashicons-category',
			99
		);

		// サブメニュー「Edit」の登録
		add_submenu_page(
			'wp-sub-menu',
			__( 'WP Sub Menu 設定', 'ku-submenu-folder' ),
			'Edit',
			'manage_options',
			'wp-sub-menu-edit',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * トップレベルメニューをクリックした際のデフォルト表示ページ
	 */
	public function render_top_menu_page() {
		$this->render_settings_page();
	}

	/**
	 * アセット（CSS / JS）の読み込み
	 *
	 * @param string $hook_suffix 現在の管理画面フック名.
	 */
	public function enqueue_assets( $hook_suffix ) {
		if ( strpos( $hook_suffix, 'wp-sub-menu' ) === false ) {
			return;
		}

		wp_enqueue_style(
			'kusf-admin-settings-css',
			KUSF_PLUGIN_URL . 'assets/css/admin-settings.css',
			array(),
			KUSF_VERSION
		);

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
				'maxItems'     => $this->main->get_max_items(),
				'nonce'        => wp_create_nonce( 'kusf_save_settings_nonce' ),
				'limitMessage' => sprintf(
					/* translators: %d: Maximum allowed items */
					__( 'WP Sub Menu に格納できるメニューは最大 %d 件までです。', 'ku-submenu-folder' ),
					$this->main->get_max_items()
				),
			)
		);
	}

	/**
	 * 設定ページの描画処理
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'このページにアクセスする権限がありません。', 'ku-submenu-folder' ) );
		}

		$options       = $this->main->get_options();
		$notice_message = '';

		// フォーム送信（保存）の処理
		if ( isset( $_POST['kusf_save_settings'] ) && check_admin_referer( 'kusf_save_settings_action', 'kusf_save_settings_nonce' ) ) {
			$saved_menues_json = isset( $_POST['kusf_selected_menues'] ) ? sanitize_text_field( wp_unslash( $_POST['kusf_selected_menues'] ) ) : '[]';
			$decoded_menues    = json_decode( $saved_menues_json, true );

			if ( is_array( $decoded_menues ) ) {
				$max_items       = $this->main->get_max_items();
				$sanitized_items = array();

				// 最大件数チェックとサニタイズ
				$count = 0;
				foreach ( $decoded_menues as $item ) {
					if ( $count >= $max_items ) {
						break;
					}
					if ( ! empty( $item['menu_slug'] ) ) {
						$sanitized_items[] = array(
							'menu_slug' => sanitize_text_field( $item['menu_slug'] ),
							'title'     => sanitize_text_field( $item['title'] ?? '' ),
							'order'     => (int) ( $item['order'] ?? $count ),
							'data'      => array(
								'url' => sanitize_text_field( $item['url'] ?? '' ),
							),
						);
						$count++;
					}
				}

				// デフォルトフォルダ（folder_default）の構造を維持して更新
				$options['sub_menues'][0]['menues'] = $sanitized_items;
				$this->main->save_options( $options );

				$notice_message = __( '設定を保存しました。', 'ku-submenu-folder' );
			}
		}

		// 設定済みメニューの取得（folder_default）
		$default_folder  = $options['sub_menues'][0] ?? array();
		$selected_menues = $default_folder['menues'] ?? array();
		$selected_slugs  = array_column( $selected_menues, 'menu_slug' );

		// 現在登録されている全てのサイドバーメニューを取得
		$raw_menu = $GLOBALS['menu'] ?? array();
		$available_menues = $this->get_clean_root_menues( $raw_menu );

		?>
		<div class="wrap kusf-settings-wrap">
			<h1 class="wp-heading-inline"><?php echo esc_html( $default_folder['title'] ?? 'WP Sub Menu' ); ?></h1>
			<hr class="wp-header-end">

			<?php if ( ! empty( $notice_message ) ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php echo esc_html( $notice_message ); ?></p>
				</div>
			<?php endif; ?>

			<form method="post" action="" id="kusf-settings-form">
				<?php wp_nonce_field( 'kusf_save_settings_action', 'kusf_save_settings_nonce' ); ?>
				<input type="hidden" name="kusf_selected_menues" id="kusf_selected_menues" value="<?php echo esc_attr( wp_json_encode( $selected_menues ) ); ?>">

				<div class="kusf-settings-container">
					<!-- 左側: 擬似WPサイドメニュー -->
					<div class="kusf-pseudo-sidebar-wrapper">
						<div class="kusf-pseudo-sidebar">
							<ul class="kusf-pseudo-menu-list">
								<?php foreach ( $available_menues as $item ) : ?>
									<?php
									$slug        = $item['slug'];
									$title       = $item['title'];
									$icon_html   = $item['icon_html'];
									$is_checked  = in_array( $slug, $selected_slugs, true );
									$is_disabled = ( 'wp-sub-menu' === $slug );
									?>
									<li class="kusf-pseudo-menu-item <?php echo $is_checked ? 'is-selected' : ''; ?>" data-slug="<?php echo esc_attr( $slug ); ?>" data-title="<?php echo esc_attr( wp_strip_all_tags( $title ) ); ?>" data-url="<?php echo esc_attr( $item['url'] ); ?>">
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

					<!-- 右側: WP Sub Menu プレビュー・構造カスタマイザー -->
					<div class="kusf-preview-container">
						<div class="kusf-folder-card">
							<div class="kusf-folder-header">
								<span class="dashicons dashicons-category"></span>
								<span class="kusf-folder-title"><?php echo esc_html( $default_folder['title'] ?? 'WP Sub Menu' ); ?></span>
							</div>
							<ul class="kusf-folder-sublist" id="kusf-folder-sublist">
								<?php foreach ( $selected_menues as $index => $sub_item ) : ?>
									<li class="kusf-subitem-row" data-slug="<?php echo esc_attr( $sub_item['menu_slug'] ); ?>" data-title="<?php echo esc_attr( $sub_item['title'] ); ?>" data-url="<?php echo esc_attr( $sub_item['data']['url'] ?? '' ); ?>">
										<div class="kusf-subitem-title">
											<span class="dashicons dashicons-admin-generic"></span>
											<span><?php echo esc_html( $sub_item['title'] ); ?></span>
										</div>
										<div class="kusf-subitem-actions">
											<button type="button" class="button button-small kusf-move-up" title="上へ" <?php echo 0 === $index ? 'disabled' : ''; ?>>
												<span class="dashicons dashicons-arrow-up-alt2"></span>
											</button>
											<button type="button" class="button button-small kusf-move-down" title="下へ" <?php echo ( count( $selected_menues ) - 1 === $index ) ? 'disabled' : ''; ?>>
												<span class="dashicons dashicons-arrow-down-alt2"></span>
											</button>
										</div>
									</li>
								<?php endforeach; ?>
							</ul>
						</div>

						<div class="kusf-form-actions">
							<input type="submit" name="kusf_save_settings" class="button button-primary button-large" value="<?php esc_attr_e( '変更を保存', 'ku-submenu-folder' ); ?>">
						</div>
					</div>
				</div>
			</form>
		</div>
		<?php
	}

	/**
	 * グローバル $menu から、表示用にクリーニングされたルートメニュー配列を生成
	 *
	 * @param array $raw_menu グローバル $menu.
	 * @return array
	 */
	private function get_clean_root_menues( array $raw_menu ): array {
		$clean = array();

		foreach ( $raw_menu as $item ) {
			// ディバイダーや空項目をスキップ
			if ( empty( $item[0] ) || ( isset( $item[4] ) && strpos( $item[4], 'wp-menu-separator' ) !== false ) ) {
				continue;
			}

			$title     = wp_strip_all_tags( $item[0] );
			$slug      = $item[2];
			$icon_class = $item[6] ?? 'dashicons-admin-generic';

			// 通知バッジ等のタグを除去したプレーンタイトルと、HTMLタイトルの準備
			$clean_title = preg_replace( '/\s*<span.*?>.*?<\/span>/i', '', $item[0] );

			// アイコンHTMLの組み立て
			$icon_html = '<span class="dashicons dashicons-admin-generic"></span>';
			if ( strpos( $icon_class, 'dashicons-' ) === 0 ) {
				$icon_html = sprintf( '<span class="dashicons %s"></span>', esc_attr( $icon_class ) );
			} elseif ( strpos( $icon_class, 'data:image' ) === 0 || strpos( $icon_class, 'http' ) === 0 ) {
				$icon_html = sprintf( '<img src="%s" alt="" class="kusf-custom-icon" />', esc_url( $icon_class ) );
			}

			$url = $slug;
			if ( ! str_contains( $slug, '.php' ) && ! str_contains( $slug, 'http' ) ) {
				$url = 'admin.php?page=' . $slug;
			}

			$clean[] = array(
				'slug'      => $slug,
				'title'     => trim( $clean_title ),
				'raw_title' => $item[0],
				'url'       => $url,
				'icon_html' => $icon_html,
			);
		}

		return $clean;
	}
}
