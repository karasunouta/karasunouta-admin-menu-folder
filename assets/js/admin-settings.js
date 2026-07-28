/**
 * KU Submenu Folder - Admin Settings & Menu Link Helper (Vanilla JS)
 */

document.addEventListener('DOMContentLoaded', function () {
	// ----------------------------------------------------------------------
	// 全管理画面共通: 親メニュー「KU Submenu」のリンク先を設定ページに強制補正
	// ----------------------------------------------------------------------
	function fixParentMenuLink() {
		if (typeof kusfParams === 'undefined' || !kusfParams.settingsUrl) {
			return;
		}

		// WPサイドメニューの KU Submenu トップレベルアンカータグを検索
		const parentAnchors = document.querySelectorAll('#toplevel_page_ku-submenu > a, .toplevel_page_ku-submenu > a');
		parentAnchors.forEach(function (anchor) {
			anchor.setAttribute('href', kusfParams.settingsUrl);
			anchor.addEventListener('click', function (e) {
				e.preventDefault();
				window.location.href = kusfParams.settingsUrl;
			});
		});
	}

	// 最先頭で必ず実行
	fixParentMenuLink();

	// ----------------------------------------------------------------------
	// 設定画面内UIのリアルタイム操作ロジック
	// ----------------------------------------------------------------------
	const pseudoList = document.querySelector('.kusf-pseudo-menu-list');
	const folderSublist = document.getElementById('kusf-folder-sublist');
	const hiddenInput = document.getElementById('kusf_selected_menues');

	if (!pseudoList || !folderSublist || !hiddenInput) {
		return;
	}

	const maxItems = typeof kusfParams !== 'undefined' ? parseInt(kusfParams.maxItems, 10) : 5;
	const limitMessage = typeof kusfParams !== 'undefined' ? kusfParams.limitMessage : '最大件数に達しました。';
	const protectedSlugs = (typeof kusfParams !== 'undefined' && Array.isArray(kusfParams.protectedSlugs)) ? kusfParams.protectedSlugs : ['ku-submenu', 'ku-submenu-folder'];

	/**
	 * 隠しフィールド (JSON) とボタンの有効/無効状態を更新
	 */
	function syncState() {
		updateButtonStates();
		updateHiddenFieldValue();
	}

	/**
	 * 右側リストの順番に従って隠しフィールド値をJSON化（保護対象は除外してバリデーション）
	 */
	function updateHiddenFieldValue() {
		const items = folderSublist.querySelectorAll('.kusf-subitem-row');
		const result = [];

		items.forEach(function (row, index) {
			const slug = row.getAttribute('data-slug');
			if (protectedSlugs.includes(slug)) {
				row.remove();
				return;
			}

			const pos = parseFloat(row.getAttribute('data-position')) || 999.0;
			const iconClass = row.getAttribute('data-icon-class') || '';

			result.push({
				menu_slug: slug,
				title: row.getAttribute('data-title'),
				order: index,
				data: {
					url: row.getAttribute('data-url') || '',
					original_position: pos,
					icon_class: iconClass
				}
			});
		});

		hiddenInput.value = JSON.stringify(result);
	}

	/**
	 * 上下ボタンの enabled / disabled 状態を更新
	 */
	function updateButtonStates() {
		const rows = folderSublist.querySelectorAll('.kusf-subitem-row');
		rows.forEach(function (row, index) {
			const btnUp = row.querySelector('.kusf-move-up');
			const btnDown = row.querySelector('.kusf-move-down');

			if (btnUp) {
				btnUp.disabled = (index === 0);
			}
			if (btnDown) {
				btnDown.disabled = (index === rows.length - 1);
			}
		});
	}

	/**
	 * 右側リストに新しい項目DOM要素を生成して返却
	 */
	function createSubitemRow(slug, title, url, position, iconClass) {
		const moveUpText = (typeof kusfParams !== 'undefined' && kusfParams.moveUp) ? kusfParams.moveUp : 'Move Up';
		const moveDownText = (typeof kusfParams !== 'undefined' && kusfParams.moveDown) ? kusfParams.moveDown : 'Move Down';

		const li = document.createElement('li');
		li.className = 'kusf-subitem-row';
		li.setAttribute('data-slug', slug);
		li.setAttribute('data-title', title);
		li.setAttribute('data-url', url);
		li.setAttribute('data-position', position || '999');
		li.setAttribute('data-icon-class', iconClass || '');

		li.innerHTML = `
			<div class="kusf-subitem-title">
				<span class="dashicons dashicons-admin-generic"></span>
				<span>${escapeHtml(title)}</span>
			</div>
			<div class="kusf-subitem-actions">
				<button type="button" class="button button-small kusf-move-up" title="${escapeHtml(moveUpText)}">
					<span class="dashicons dashicons-arrow-up-alt2"></span>
				</button>
				<button type="button" class="button button-small kusf-move-down" title="${escapeHtml(moveDownText)}">
					<span class="dashicons dashicons-arrow-down-alt2"></span>
				</button>
			</div>
		`;

		return li;
	}

	/**
	 * エスケープヘルパー
	 */
	function escapeHtml(str) {
		return String(str)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#039;');
	}

	// ----------------------------------------------------------------------
	// イベント処理: 左側擬似メニューのチェックボックス連動
	// ----------------------------------------------------------------------
	pseudoList.addEventListener('change', function (event) {
		const toggle = event.target;
		if (!toggle.classList.contains('kusf-item-toggle')) {
			return;
		}

		const itemRow = toggle.closest('.kusf-pseudo-menu-item');
		if (!itemRow) {
			return;
		}

		const slug = itemRow.getAttribute('data-slug');
		const title = itemRow.getAttribute('data-title');
		const url = itemRow.getAttribute('data-url');
		const position = itemRow.getAttribute('data-position');
		const iconClass = itemRow.getAttribute('data-icon-class');

		// 保護対象スラグ（自己フォルダや他フォルダ）の場合は強制ブロック
		if (protectedSlugs.includes(slug)) {
			toggle.checked = false;
			return;
		}

		if (toggle.checked) {
			// 現在の右側リスト件数チェック
			const currentCount = folderSublist.querySelectorAll('.kusf-subitem-row').length;
			if (currentCount >= maxItems) {
				alert(limitMessage);
				toggle.checked = false;
				return;
			}

			// 右側リストに追加
			const newRow = createSubitemRow(slug, title, url, position, iconClass);
			folderSublist.appendChild(newRow);
			itemRow.classList.add('is-selected');
		} else {
			// 右側リストから削除
			const existingRow = folderSublist.querySelector(`.kusf-subitem-row[data-slug="${slug}"]`);
			if (existingRow) {
				existingRow.remove();
			}
			itemRow.classList.remove('is-selected');
		}

		syncState();
	});

	// ----------------------------------------------------------------------
	// イベント処理: 右側リストの上下ボタン操作 (イベント委任)
	// ----------------------------------------------------------------------
	folderSublist.addEventListener('click', function (event) {
		const btnUp = event.target.closest('.kusf-move-up');
		const btnDown = event.target.closest('.kusf-move-down');

		if (btnUp && !btnUp.disabled) {
			const currentRow = btnUp.closest('.kusf-subitem-row');
			const prevRow = currentRow.previousElementSibling;
			if (prevRow) {
				folderSublist.insertBefore(currentRow, prevRow);
				syncState();
			}
		} else if (btnDown && !btnDown.disabled) {
			const currentRow = btnDown.closest('.kusf-subitem-row');
			const nextRow = currentRow.nextElementSibling;
			if (nextRow) {
				folderSublist.insertBefore(nextRow, currentRow);
				syncState();
			}
		}
	});

	// 初期状態の同期
	syncState();
});
