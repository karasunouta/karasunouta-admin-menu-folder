import '../css/admin-settings.css';

/**
 * Karasunouta Admin Menu Folder - Admin Settings & Menu Link Helper (Vanilla JS)
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
	if (!iconClass) {
		return '<span class="dashicons dashicons-category"></span>';
	}
	if (iconClass.includes('dashicons-')) {
		return `<span class="dashicons ${escapeHtml(iconClass)}"></span>`;
	}
	if (iconClass.includes('data:image') || iconClass.includes('http')) {
		return `<img src="${escapeHtml(iconClass)}" alt="" class="kamf-custom-icon" style="width:18px;height:18px;vertical-align:middle;" />`;
	}
	if (iconClass.includes('svg')) {
		return `<span class="kamf-svg-icon" style="width:18px;height:18px;display:inline-flex;">${iconClass}</span>`;
	}
	return '<span class="dashicons dashicons-category"></span>';
}

// 拡張アドオン向けに共通ユーティリティを公開
window.kamfUtils = {
	escapeHtml,
	buildIconHtml
};

document.addEventListener('DOMContentLoaded', function () {

	/**
	 * URLから kamf_updated=true および kamf_reset=true を静かに消去（F5時の重複メッセージ表示防止）
	 */
	function cleanUrlQuery() {
		if (window.history && window.history.replaceState) {
			const search = window.location.search;
			if (search.includes('kamf_updated=true') || search.includes('kamf_reset=true')) {
				const cleanSearch = search.replace(/([\?&])kamf_updated=true(&|$)/, '$1').replace(/([\?&])kamf_reset=true(&|$)/, '$1').replace(/[\?&]$/, '');
				const cleanUrl = window.location.pathname + cleanSearch + window.location.hash;
				window.history.replaceState({}, document.title, cleanUrl);
			}
		}
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
	// 設定画面内UIの操作ロジック (1フォルダー構造対応)
	// ----------------------------------------------------------------------
	const pseudoList = document.querySelector('.kamf-pseudo-menu-list');
	const foldersGrid = document.getElementById('kamf-folders-grid');
	const hiddenInput = document.getElementById('kamf_folders_json');

	if (!pseudoList || !foldersGrid || !hiddenInput) {
		return;
	}

	// ----------------------------------------------------------------------
	// 共通UIイベント: 左側擬似メニューの行全体クリックでチェックボックス切替
	// ----------------------------------------------------------------------
	pseudoList.addEventListener('click', function (e) {
		const itemRow = e.target.closest('.kamf-pseudo-menu-item');
		if (!itemRow || itemRow.classList.contains('is-disabled')) return;

		// チェックボックス自体をクリックした場合は change イベントが自然発火するので二重発火を防止
		if (e.target.tagName && e.target.tagName.toLowerCase() === 'input') return;

		const checkbox = itemRow.querySelector('.kamf-item-toggle');
		if (checkbox && !checkbox.disabled) {
			checkbox.checked = !checkbox.checked;
			checkbox.dispatchEvent(new Event('change', { bubbles: true }));
		}
	});

	const maxItems = typeof kamfParams !== 'undefined' ? parseInt(kamfParams.maxItems, 10) : 99;
	const limitMessage = typeof kamfParams !== 'undefined' ? kamfParams.limitMessage : 'Maximum items limit reached.';
	const protectedSlugs = (typeof kamfParams !== 'undefined' && Array.isArray(kamfParams.protectedSlugs)) ? kamfParams.protectedSlugs : ['kamf-folder-default', 'karasunouta-admin-menu-folder'];

	/**
	 * 隠しフィールド (JSON) とUI状態の同期
	 */
	function syncState() {
		const firstCard = foldersGrid.querySelector('.kamf-folder-card');
		if (firstCard) {
			const sublist = firstCard.querySelector('.kamf-folder-sublist');
			updateEmptyNotice(sublist);
		}
		updateHiddenFieldValue();
		syncPseudoMenuCheckboxes();

		// 拡張フック（状態同期の完了を通知）
		if (window.wp && window.wp.hooks) {
			window.wp.hooks.doAction('kamf.afterSyncState', foldersGrid, hiddenInput);
		}
	}

	/**
	 * 空フォルダー通知メッセージの自動削除・再挿入制御
	 */
	function updateEmptyNotice(sublist) {
		if (!sublist) return;
		const rows = sublist.querySelectorAll('.kamf-subitem-row');
		const emptyNotice = sublist.querySelector('.kamf-empty-notice');

		if (rows.length > 0) {
			if (emptyNotice) {
				emptyNotice.remove();
			}
		} else {
			if (!emptyNotice) {
				const emptyLi = document.createElement('li');
				emptyLi.className = 'kamf-empty-notice';
				emptyLi.textContent = (typeof kamfParams !== 'undefined' && kamfParams.noItemsSelected) ? kamfParams.noItemsSelected : 'No menu items selected';
				sublist.appendChild(emptyLi);
			}
		}
	}

	/**
	 * 擬似サイドメニューのチェックボックス状態およびアクティブ表示を現在のフォルダーと同期
	 */
	function syncPseudoMenuCheckboxes() {
		const firstCard = foldersGrid.querySelector('.kamf-folder-card');
		if (!firstCard) return;

		const activeFolderId = firstCard.getAttribute('data-folder-id') || 'folder-default';
		const allSubitemRows = firstCard.querySelectorAll('.kamf-subitem-row');
		const storedSlugs = new Set();
		allSubitemRows.forEach(row => storedSlugs.add(row.getAttribute('data-slug')));

		const pseudoItems = pseudoList.querySelectorAll('.kamf-pseudo-menu-item');
		pseudoItems.forEach(item => {
			const slug = item.getAttribute('data-slug');
			const checkbox = item.querySelector('.kamf-item-toggle');

			// アクティブフォルダー項目のハイライト制御 (kamf-folder-default 等)
			if (slug === 'kamf-folder-default' || slug === 'folder-default' || slug === activeFolderId || slug === ('kamf-' + activeFolderId)) {
				item.classList.add('is-selected-folder-active');
			} else {
				item.classList.remove('is-selected-folder-active');
			}

			if (checkbox && !checkbox.disabled) {
				const isStored = storedSlugs.has(slug);
				checkbox.checked = isStored;
				if (isStored) {
					item.classList.add('is-selected');
				} else {
					item.classList.remove('is-selected');
				}
			}
		});
	}

	/**
	 * 元のWPポジション順 (data-position 昇順) に従って行要素を挿入
	 */
	function insertRowSortedByPosition(sublist, newRow) {
		// フックでカスタム挿入処理が行われた場合はスキップ
		if (window.wp && window.wp.hooks) {
			const handled = window.wp.hooks.applyFilters('kamf.customInsertRow', false, sublist, newRow);
			if (handled) return;
		}

		const newPos = parseFloat(newRow.getAttribute('data-position')) || 999.0;
		const existingRows = Array.from(sublist.querySelectorAll('.kamf-subitem-row'));
		let inserted = false;

		for (let i = 0; i < existingRows.length; i++) {
			const rowPos = parseFloat(existingRows[i].getAttribute('data-position')) || 999.0;
			if (newPos < rowPos) {
				sublist.insertBefore(newRow, existingRows[i]);
				inserted = true;
				break;
			}
		}

		if (!inserted) {
			sublist.appendChild(newRow);
		}
	}

	/**
	 * 1フォルダー目からJSON構造をビルドして隠しフィールドに格納
	 */
	function updateHiddenFieldValue() {
		const firstCard = foldersGrid.querySelector('.kamf-folder-card');
		if (!firstCard) return;

		const folderId = firstCard.getAttribute('data-folder-id') || 'folder-default';
		const titleEl = firstCard.querySelector('.kamf-folder-title');
		const folderTitle = titleEl ? titleEl.textContent.trim() : 'Menu Folder';
		const folderIcon = firstCard.getAttribute('data-icon') || 'dashicons-category';

		const subitemRows = firstCard.querySelectorAll('.kamf-subitem-row');
		const menuesData = [];

		subitemRows.forEach(function (row, iIndex) {
			const slug = row.getAttribute('data-slug');
			if (protectedSlugs.includes(slug)) {
				row.remove();
				return;
			}

			const pos = parseFloat(row.getAttribute('data-position')) || 999.0;
			const iconClass = row.getAttribute('data-icon-class') || '';

			menuesData.push({
				menu_slug: slug,
				title: row.getAttribute('data-title'),
				order: iIndex,
				data: {
					url: row.getAttribute('data-url') || '',
					original_position: pos,
					icon_class: iconClass
				}
			});
		});

		let jsonPayload = [{
			id: folderId,
			title: folderTitle,
			icon: folderIcon,
			position: 99,
			menues: menuesData
		}];

		// 拡張フック（複数フォルダー対応等のアドオンがJSONデータをフィルタリング可能）
		if (window.wp && window.wp.hooks) {
			jsonPayload = window.wp.hooks.applyFilters('kamf.foldersJsonPayload', jsonPayload, foldersGrid);
		}

		hiddenInput.value = JSON.stringify(jsonPayload);
	}

	/**
	 * サブアイテム行DOM要素の生成
	 */
	function createSubitemRow(slug, title, url, position, iconClass) {
		const removeText = (typeof kamfParams !== 'undefined' && kamfParams.removeItem) ? kamfParams.removeItem : 'Remove Item';

		const li = document.createElement('li');
		li.className = 'kamf-subitem-row';
		li.setAttribute('data-slug', slug);
		li.setAttribute('data-title', title);
		li.setAttribute('data-url', url);
		li.setAttribute('data-position', position || '999');
		li.setAttribute('data-icon-class', iconClass || '');

		let actionsHtml = `
			<button type="button" class="button button-small kamf-item-remove-btn" title="${escapeHtml(removeText)}">
				<span class="dashicons dashicons-no-alt"></span>
			</button>
		`;

		// 拡張フック（アドオンが並び替えボタン等を差し込めるようにする）
		if (window.wp && window.wp.hooks) {
			actionsHtml = window.wp.hooks.applyFilters('kamf.subitemActionButtons', actionsHtml, {
				slug,
				title,
				url,
				position,
				iconClass
			});
		}

		li.innerHTML = `
			<div class="kamf-subitem-title">
				<span class="kamf-menu-icon">${buildIconHtml(iconClass)}</span>
				<span>${escapeHtml(title)}</span>
			</div>
			<div class="kamf-subitem-actions">
				${actionsHtml}
			</div>
		`;

		return li;
	}

	// ----------------------------------------------------------------------
	// イベント処理: 単一フォルダーカード内操作 (アイテム削除)
	// ----------------------------------------------------------------------
	foldersGrid.addEventListener('click', function (e) {
		const card = e.target.closest('.kamf-folder-card');
		if (!card) return;

		// サブアイテムの削除（「✕」ボタン）
		const itemRemoveBtn = e.target.closest('.kamf-item-remove-btn');
		if (itemRemoveBtn) {
			const subitemRow = itemRemoveBtn.closest('.kamf-subitem-row');
			if (subitemRow) {
				subitemRow.remove();
				syncState();
			}
		}
	});

	// ----------------------------------------------------------------------
	// イベント処理: 左側擬似メニューのチェックボックス切替
	// ----------------------------------------------------------------------
	pseudoList.addEventListener('change', function (e) {
		const toggle = e.target;
		if (!toggle.classList.contains('kamf-item-toggle')) return;

		const itemRow = toggle.closest('.kamf-pseudo-menu-item');
		if (!itemRow) return;

		const slug = itemRow.getAttribute('data-slug');
		const title = itemRow.getAttribute('data-title');
		const url = itemRow.getAttribute('data-url');
		const position = itemRow.getAttribute('data-position');
		const iconClass = itemRow.getAttribute('data-icon-class');

		if (protectedSlugs.includes(slug)) {
			toggle.checked = false;
			return;
		}

		const targetCard = foldersGrid.querySelector('.kamf-folder-card.is-active') || foldersGrid.querySelector('.kamf-folder-card');
		if (!targetCard) {
			toggle.checked = false;
			return;
		}

		const sublist = targetCard.querySelector('.kamf-folder-sublist');

		if (toggle.checked) {
			const currentCount = sublist.querySelectorAll('.kamf-subitem-row').length;
			if (currentCount >= maxItems) {
				alert(limitMessage);
				toggle.checked = false;
				return;
			}

			const newRow = createSubitemRow(slug, title, url, position, iconClass);

			// フックでカスタム挿入処理（Pro版等は末尾追加）を適用。未登録ならデフォルトで位置順ソート挿入
			const handled = (window.wp && window.wp.hooks)
				? window.wp.hooks.applyFilters('kamf.insertSubitemRow', false, sublist, newRow)
				: false;

			if (!handled) {
				insertRowSortedByPosition(sublist, newRow);
			}

			itemRow.classList.add('is-selected');
		} else {
			const existingRows = foldersGrid.querySelectorAll(`.kamf-subitem-row[data-slug="${slug}"]`);
			existingRows.forEach(r => r.remove());
			itemRow.classList.remove('is-selected');
		}

		syncState();
	});

	// 初期状態同期
	syncState();
});
