import '../css/admin-settings.css';

/**
 * Karasunouta Admin Menu Folder - Admin Settings (Vanilla JS)
 */

/**
 * HTML エスケープ helper
 *
 * @param {string} str エスケープ対象文字列
 * @returns {string}
 */
export function escapeHtml(str) {
	return String(str || '')
		.replace(/&/g, '&amp;')
		.replace(/</g, '&lt;')
		.replace(/>/g, '&gt;')
		.replace(/"/g, '&quot;')
		.replace(/'/g, '&#039;');
}

/**
 * アイコン HTML 生成 helper
 *
 * @param {string} iconClass アイコン指定文字列
 * @returns {string}
 */
export function buildIconHtml(iconClass) {
	const icon = String(iconClass || '').trim();

	if (icon.includes('dashicons-')) {
		return `<span class="dashicons ${escapeHtml(icon)}"></span>`;
	}
	if (/^data:image\/(?:png|jpe?g|gif|webp|svg\+xml);base64,[A-Za-z0-9+/=]+$/.test(icon)) {
		return `<img src="${escapeHtml(icon)}" alt="" class="kamf-custom-icon" width="18" height="18" />`;
	}
	if (icon.startsWith('http://') || icon.startsWith('https://') || icon.startsWith('/')) {
		return `<img src="${escapeHtml(icon)}" alt="" class="kamf-custom-icon" width="18" height="18" />`;
	}
	return '<span class="dashicons dashicons-admin-generic"></span>';
}

/**
 * 非表示の送信用フィールドを生成して追加
 *
 * @param {HTMLElement} container 追加先コンテナ
 * @param {string}      name      フィールド名
 * @param {string}      value     値
 * @returns {HTMLInputElement}
 */
export function appendHiddenField(container, name, value) {
	const input = document.createElement('input');
	input.type = 'hidden';
	input.name = name;
	input.value = value === null || value === undefined ? '' : String(value);
	container.appendChild(input);
	return input;
}

/**
 * フォルダーカード内のメニュー項目から送信用フィールドを構築
 *
 * @param {HTMLElement} container 追加先コンテナ
 * @param {HTMLElement} card      フォルダーカード要素
 * @param {string}      baseName  フィールド名の基準（例: kamf_folder[menues]）
 * @param {string[]}    skipSlugs 除外するスラグ
 */
export function appendItemFields(container, card, baseName, skipSlugs = []) {
	let index = 0;

	card.querySelectorAll('.kamf-subitem-row').forEach(function (row) {
		const slug = row.getAttribute('data-slug');
		if (!slug || skipSlugs.includes(slug)) {
			row.remove();
			return;
		}

		const prefix = `${baseName}[${index}]`;
		appendHiddenField(container, `${prefix}[menu_slug]`, slug);
		appendHiddenField(container, `${prefix}[title]`, row.getAttribute('data-title') || '');
		appendHiddenField(container, `${prefix}[url]`, row.getAttribute('data-url') || '');
		appendHiddenField(container, `${prefix}[position]`, row.getAttribute('data-position') || '999');
		appendHiddenField(container, `${prefix}[icon_class]`, row.getAttribute('data-icon-class') || '');
		index++;
	});
}

/**
 * フォルダーカードから送信用フィールド（識別子とメニュー項目）を構築
 *
 * @param {HTMLElement} container 追加先コンテナ
 * @param {HTMLElement} card      フォルダーカード要素
 * @param {string}      baseName  フィールド名の基準（例: kamf_folder）
 * @param {string[]}    skipSlugs 除外するスラグ
 */
export function appendFolderFields(container, card, baseName, skipSlugs = []) {
	appendHiddenField(container, `${baseName}[id]`, card.getAttribute('data-folder-id') || 'folder-default');
	appendItemFields(container, card, `${baseName}[menues]`, skipSlugs);
}

// 拡張機能向けに共通ユーティリティを公開
window.kamfUtils = {
	escapeHtml,
	buildIconHtml,
	appendHiddenField,
	appendItemFields,
	appendFolderFields
};

document.addEventListener('DOMContentLoaded', function () {

	/**
	 * URLから settings-updated=true を静かに消去（F5時の重複メッセージ表示防止）
	 */
	function cleanUrlQuery() {
		if (!window.history || !window.history.replaceState) {
			return;
		}

		const search = window.location.search;
		if (!search.includes('settings-updated=')) {
			return;
		}

		const cleanSearch = search
			.replace(/([?&])settings-updated=[^&]*(&|$)/, '$1')
			.replace(/[?&]$/, '');
		window.history.replaceState({}, document.title, window.location.pathname + cleanSearch + window.location.hash);
	}

	cleanUrlQuery();

	const resetBtn = document.getElementById('kamf-reset-settings-btn');
	const resetForm = document.getElementById('kamf-reset-form');
	if (resetBtn && resetForm) {
		resetBtn.addEventListener('click', function () {
			const confirmMsg = (typeof kamfParams !== 'undefined' && kamfParams.confirmReset)
				? kamfParams.confirmReset
				: 'Are you sure you want to reset Karasunouta Admin Menu Folder settings to default?';
			if (confirm(confirmMsg)) {
				resetForm.submit();
			}
		});
	}

	// ----------------------------------------------------------------------
	// 設定画面内UIの操作ロジック
	// ----------------------------------------------------------------------
	const pseudoList = document.querySelector('.kamf-pseudo-menu-list');
	const foldersGrid = document.getElementById('kamf-folders-grid');
	const fieldsContainer = document.getElementById('kamf-folder-fields');

	if (!pseudoList || !foldersGrid || !fieldsContainer) {
		return;
	}

	const params = typeof kamfParams !== 'undefined' ? kamfParams : {};
	const maxItems = params.maxItems ? parseInt(params.maxItems, 10) : 99;
	const limitMessage = params.limitMessage || 'Maximum items limit reached.';
	const protectedSlugs = Array.isArray(params.protectedSlugs) ? params.protectedSlugs : [];

	/**
	 * プライマリーフォルダーのカード要素を取得
	 */
	function getPrimaryCard() {
		return foldersGrid.querySelector('.kamf-folder-card[data-primary="1"]')
			|| foldersGrid.querySelector('.kamf-folder-card');
	}

	/**
	 * 送信用フィールドとUI状態の同期
	 */
	function syncState() {
		const card = getPrimaryCard();
		if (card) {
			updateEmptyNotice(card.querySelector('.kamf-folder-sublist'));
		}

		writeHiddenFields();
		syncPseudoMenuCheckboxes();

		// 拡張フック（状態同期の完了を通知）
		if (window.wp && window.wp.hooks) {
			window.wp.hooks.doAction('kamf.afterSyncState', foldersGrid, fieldsContainer);
		}
	}

	/**
	 * 送信用の非表示フィールドを再構築
	 */
	function writeHiddenFields() {
		// 拡張機能が独自にフィールドを構築する場合は既定処理をスキップ
		if (window.wp && window.wp.hooks) {
			const handled = window.wp.hooks.applyFilters('kamf.writeHiddenFields', false, fieldsContainer, foldersGrid);
			if (handled) {
				return;
			}
		}

		fieldsContainer.textContent = '';

		const card = getPrimaryCard();
		if (card) {
			appendFolderFields(fieldsContainer, card, 'kamf_folder', protectedSlugs);
		}
	}

	/**
	 * 空フォルダー通知メッセージの自動削除・再挿入制御
	 */
	function updateEmptyNotice(sublist) {
		if (!sublist) {
			return;
		}

		const rows = sublist.querySelectorAll('.kamf-subitem-row');
		const emptyNotice = sublist.querySelector('.kamf-empty-notice');

		if (rows.length > 0) {
			if (emptyNotice) {
				emptyNotice.remove();
			}
			return;
		}

		if (!emptyNotice) {
			const emptyLi = document.createElement('li');
			emptyLi.className = 'kamf-empty-notice';
			emptyLi.textContent = params.noItemsSelected || 'No menu items selected';
			sublist.appendChild(emptyLi);
		}
	}

	/**
	 * 擬似サイドメニューのチェックボックス状態およびアクティブ表示を同期
	 */
	function syncPseudoMenuCheckboxes() {
		const card = getPrimaryCard();
		if (!card) {
			return;
		}

		const storedSlugs = new Set();
		card.querySelectorAll('.kamf-subitem-row').forEach(row => storedSlugs.add(row.getAttribute('data-slug')));

		pseudoList.querySelectorAll('.kamf-pseudo-menu-item').forEach(function (item) {
			const checkbox = item.querySelector('.kamf-item-toggle');
			if (!checkbox || checkbox.disabled) {
				return;
			}

			const isStored = storedSlugs.has(item.getAttribute('data-slug'));
			checkbox.checked = isStored;
			item.classList.toggle('is-selected', isStored);
		});
	}

	/**
	 * 元のWPポジション順 (data-position 昇順) に従って行要素を挿入
	 */
	function insertRowSortedByPosition(sublist, newRow) {
		const newPos = parseFloat(newRow.getAttribute('data-position')) || 999.0;
		const existingRows = Array.from(sublist.querySelectorAll('.kamf-subitem-row'));

		for (let i = 0; i < existingRows.length; i++) {
			const rowPos = parseFloat(existingRows[i].getAttribute('data-position')) || 999.0;
			if (newPos < rowPos) {
				sublist.insertBefore(newRow, existingRows[i]);
				return;
			}
		}

		sublist.appendChild(newRow);
	}

	/**
	 * サブアイテム行DOM要素の生成
	 */
	function createSubitemRow(slug, title, url, position, iconClass) {
		const li = document.createElement('li');
		li.className = 'kamf-subitem-row';
		li.setAttribute('data-slug', slug);
		li.setAttribute('data-title', title || '');
		li.setAttribute('data-url', url || '');
		li.setAttribute('data-position', position || '999');
		li.setAttribute('data-icon-class', iconClass || '');

		li.innerHTML = `
			<div class="kamf-subitem-title">
				<span class="kamf-menu-icon">${buildIconHtml(iconClass)}</span>
				<span>${escapeHtml(title)}</span>
			</div>
			<div class="kamf-subitem-actions">
				<button type="button" class="button button-small kamf-item-remove-btn" title="${escapeHtml(params.removeItem || 'Remove Item')}">
					<span class="dashicons dashicons-no-alt"></span>
				</button>
			</div>
		`;

		// 拡張フック（アクションボタンの追加等）
		if (window.wp && window.wp.hooks) {
			window.wp.hooks.doAction('kamf.afterCreateSubitemRow', li, {
				slug,
				title,
				url,
				position,
				iconClass
			});
		}

		return li;
	}

	// ----------------------------------------------------------------------
	// イベント処理: フォルダーカード内のアイテム削除
	// ----------------------------------------------------------------------
	foldersGrid.addEventListener('click', function (e) {
		const itemRemoveBtn = e.target.closest('.kamf-item-remove-btn');
		if (!itemRemoveBtn) {
			return;
		}

		const subitemRow = itemRemoveBtn.closest('.kamf-subitem-row');
		if (subitemRow) {
			subitemRow.remove();
			syncState();
		}
	});

	// ----------------------------------------------------------------------
	// イベント処理: 左側擬似メニューの行全体クリックでチェックボックス切替
	// ----------------------------------------------------------------------
	pseudoList.addEventListener('click', function (e) {
		const itemRow = e.target.closest('.kamf-pseudo-menu-item');
		if (!itemRow || itemRow.classList.contains('is-disabled')) {
			return;
		}

		// チェックボックス自体のクリックは change が自然発火するため二重発火を防止
		if (e.target.tagName && e.target.tagName.toLowerCase() === 'input') {
			return;
		}

		const checkbox = itemRow.querySelector('.kamf-item-toggle');
		if (checkbox && !checkbox.disabled) {
			checkbox.checked = !checkbox.checked;
			checkbox.dispatchEvent(new Event('change', { bubbles: true }));
		}
	});

	// ----------------------------------------------------------------------
	// イベント処理: 左側擬似メニューのチェックボックス切替
	// ----------------------------------------------------------------------
	pseudoList.addEventListener('change', function (e) {
		const toggle = e.target;
		if (!toggle.classList || !toggle.classList.contains('kamf-item-toggle')) {
			return;
		}

		const itemRow = toggle.closest('.kamf-pseudo-menu-item');
		if (!itemRow) {
			return;
		}

		const slug = itemRow.getAttribute('data-slug');
		if (!slug || protectedSlugs.includes(slug)) {
			toggle.checked = false;
			return;
		}

		const targetCard = foldersGrid.querySelector('.kamf-folder-card.is-active') || getPrimaryCard();
		if (!targetCard) {
			toggle.checked = false;
			return;
		}

		const sublist = targetCard.querySelector('.kamf-folder-sublist');

		if (toggle.checked) {
			if (sublist.querySelectorAll('.kamf-subitem-row').length >= maxItems) {
				alert(limitMessage);
				toggle.checked = false;
				return;
			}

			const newRow = createSubitemRow(
				slug,
				itemRow.getAttribute('data-title'),
				itemRow.getAttribute('data-url'),
				itemRow.getAttribute('data-position'),
				itemRow.getAttribute('data-icon-class')
			);

			// 拡張フック（挿入位置を差し替え可能にする）。未処理なら元のメニュー位置順に挿入
			const handled = (window.wp && window.wp.hooks)
				? window.wp.hooks.applyFilters('kamf.insertSubitemRow', false, sublist, newRow)
				: false;

			if (!handled) {
				insertRowSortedByPosition(sublist, newRow);
			}

			itemRow.classList.add('is-selected');
		} else {
			foldersGrid.querySelectorAll('.kamf-subitem-row').forEach(function (row) {
				if (row.getAttribute('data-slug') === slug) {
					row.remove();
				}
			});
			itemRow.classList.remove('is-selected');
		}

		syncState();
	});

	// 拡張機能から状態の再同期を要求できるように公開
	window.kamfAdminSettings = { syncState, getPrimaryCard };

	// 初期状態同期
	syncState();
});
