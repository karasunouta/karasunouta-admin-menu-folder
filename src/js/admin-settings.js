import '../css/admin-settings.css';

/**
 * KU Submenu Folder - Admin Settings & Menu Link Helper (Vanilla JS)
 */

document.addEventListener('DOMContentLoaded', function () {


	/**
	 * URLから kusf_updated=true を静かに消去（F5時の重複メッセージ表示防止）
	 */
	function cleanUrlQuery() {
		if (window.history && window.history.replaceState && window.location.search.includes('kusf_updated=true')) {
			const cleanSearch = window.location.search.replace(/([\?&])kusf_updated=true(&|$)/, '$1').replace(/[\?&]$/, '');
			const cleanUrl = window.location.pathname + cleanSearch + window.location.hash;
			window.history.replaceState({}, document.title, cleanUrl);
		}
	}

	cleanUrlQuery();

	// ----------------------------------------------------------------------
	// 通常版設定画面内UIの操作ロジック (1フォルダー構造対応)
	// ----------------------------------------------------------------------
	const pseudoList = document.querySelector('.kusf-pseudo-menu-list');
	const foldersGrid = document.getElementById('kusf-folders-grid');
	const hiddenInput = document.getElementById('kusf_folders_json');

	if (!pseudoList || !foldersGrid || !hiddenInput) {
		return;
	}

	const isPro = typeof kusfParams !== 'undefined' ? Boolean(kusfParams.isPro) : false;
	const maxItems = typeof kusfParams !== 'undefined' ? parseInt(kusfParams.maxItems, 10) : 5;
	const limitMessage = typeof kusfParams !== 'undefined' ? kusfParams.limitMessage : 'Maximum items limit reached.';
	const protectedSlugs = (typeof kusfParams !== 'undefined' && Array.isArray(kusfParams.protectedSlugs)) ? kusfParams.protectedSlugs : ['ku-submenu', 'ku-submenu-folder'];

	// Pro版が有効な場合は Pro版の JS (pro-admin-settings.js) が上位互換として制御を担当するためここで早期リターン
	if (isPro) {
		return;
	}

	/**
	 * 隠しフィールド (JSON) とUI状態の同期
	 */
	function syncState() {
		const firstCard = foldersGrid.querySelector('.kusf-folder-card');
		if (firstCard) {
			const sublist = firstCard.querySelector('.kusf-folder-sublist');
			updateEmptyNotice(sublist);
		}
		updateButtonStates();
		updateHiddenFieldValue();
		syncPseudoMenuCheckboxes();
	}

	/**
	 * 空フォルダー通知メッセージの自動削除・再挿入制御
	 */
	function updateEmptyNotice(sublist) {
		if (!sublist) return;
		const rows = sublist.querySelectorAll('.kusf-subitem-row');
		const emptyNotice = sublist.querySelector('.kusf-empty-notice');

		if (rows.length > 0) {
			if (emptyNotice) {
				emptyNotice.remove();
			}
		} else {
			if (!emptyNotice) {
				const emptyLi = document.createElement('li');
				emptyLi.className = 'kusf-empty-notice';
				emptyLi.textContent = (typeof kusfParams !== 'undefined' && kusfParams.noItemsSelected) ? kusfParams.noItemsSelected : 'No menu items selected';
				sublist.appendChild(emptyLi);
			}
		}
	}

	/**
	 * 擬似サイドメニューのチェックボックス状態およびアクティブ表示を現在のフォルダーと同期
	 */
	function syncPseudoMenuCheckboxes() {
		const firstCard = foldersGrid.querySelector('.kusf-folder-card');
		if (!firstCard) return;

		const activeFolderId = firstCard.getAttribute('data-folder-id') || 'folder_default';
		const allSubitemRows = firstCard.querySelectorAll('.kusf-subitem-row');
		const storedSlugs = new Set();
		allSubitemRows.forEach(row => storedSlugs.add(row.getAttribute('data-slug')));

		const pseudoItems = pseudoList.querySelectorAll('.kusf-pseudo-menu-item');
		pseudoItems.forEach(item => {
			const slug = item.getAttribute('data-slug');
			const checkbox = item.querySelector('.kusf-item-toggle');

			// アクティブフォルダー項目のハイライト制御 (ku-submenu 等)
			if (slug === 'ku-submenu' || slug === 'folder_default' || slug === activeFolderId) {
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
		const newPos = parseFloat(newRow.getAttribute('data-position')) || 999.0;
		const existingRows = Array.from(sublist.querySelectorAll('.kusf-subitem-row'));
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
		const firstCard = foldersGrid.querySelector('.kusf-folder-card');
		if (!firstCard) return;

		const folderId = firstCard.getAttribute('data-folder-id') || 'folder_default';
		const titleEl = firstCard.querySelector('.kusf-folder-title');
		const folderTitle = titleEl ? titleEl.textContent.trim() : 'KU Submenu';
		const folderIcon = firstCard.getAttribute('data-icon') || 'dashicons-category';

		const subitemRows = firstCard.querySelectorAll('.kusf-subitem-row');
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

		hiddenInput.value = JSON.stringify([{
			id: folderId,
			title: folderTitle,
			icon: folderIcon,
			position: 99,
			menues: menuesData
		}]);
	}

	/**
	 * カード内の上下移動ボタン有効/無効を更新
	 */
	function updateButtonStates() {
		if (!isPro) return;
		const firstCard = foldersGrid.querySelector('.kusf-folder-card');
		if (!firstCard) return;

		const subitemRows = firstCard.querySelectorAll('.kusf-subitem-row');
		subitemRows.forEach(function (row, iIndex) {
			const btnUp = row.querySelector('.kusf-move-up');
			const btnDown = row.querySelector('.kusf-move-down');
			if (btnUp) btnUp.disabled = (iIndex === 0);
			if (btnDown) btnDown.disabled = (iIndex === subitemRows.length - 1);
		});
	}

	/**
	 * アイコンHTML生成 helper
	 */
	function buildIconHtml(iconClass) {
		if (!iconClass) {
			return '<span class="dashicons dashicons-category"></span>';
		}
		if (iconClass.includes('dashicons-')) {
			return `<span class="dashicons ${escapeHtml(iconClass)}"></span>`;
		}
		if (iconClass.includes('data:image') || iconClass.includes('http')) {
			return `<img src="${escapeHtml(iconClass)}" alt="" class="kusf-custom-icon" style="width:18px;height:18px;vertical-align:middle;" />`;
		}
		if (iconClass.includes('svg')) {
			return `<span class="kusf-svg-icon" style="width:18px;height:18px;display:inline-flex;">${iconClass}</span>`;
		}
		return '<span class="dashicons dashicons-category"></span>';
	}

	/**
	 * エスケープ helper
	 */
	function escapeHtml(str) {
		return String(str || '')
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#039;');
	}

	/**
	 * サブアイテム行DOM要素の生成
	 */
	function createSubitemRow(slug, title, url, position, iconClass) {
		const moveUpText = isPro ? (kusfParams.moveUp || 'Move Up') : 'Reordering is available in Pro version';
		const moveDownText = isPro ? (kusfParams.moveDown || 'Move Down') : 'Reordering is available in Pro version';
		const removeText = kusfParams.removeItem || 'Remove Item';
		const disabledAttr = !isPro ? 'disabled' : '';
		const proClass = !isPro ? 'is-disabled-pro' : '';

		const li = document.createElement('li');
		li.className = 'kusf-subitem-row';
		li.setAttribute('data-slug', slug);
		li.setAttribute('data-title', title);
		li.setAttribute('data-url', url);
		li.setAttribute('data-position', position || '999');
		li.setAttribute('data-icon-class', iconClass || '');

		li.innerHTML = `
			<div class="kusf-subitem-title">
				<span class="kusf-menu-icon">${buildIconHtml(iconClass)}</span>
				<span>${escapeHtml(title)}</span>
			</div>
			<div class="kusf-subitem-actions">
				<button type="button" class="button button-small kusf-move-up ${proClass}" title="${escapeHtml(moveUpText)}" ${disabledAttr}>
					<span class="dashicons dashicons-arrow-up-alt2"></span>
				</button>
				<button type="button" class="button button-small kusf-move-down ${proClass}" title="${escapeHtml(moveDownText)}" ${disabledAttr}>
					<span class="dashicons dashicons-arrow-down-alt2"></span>
				</button>
				<button type="button" class="button button-small kusf-item-remove-btn" title="${escapeHtml(removeText)}">
					<span class="dashicons dashicons-no-alt"></span>
				</button>
			</div>
		`;

		return li;
	}

	// ----------------------------------------------------------------------
	// イベント処理: 単一フォルダーカード内操作 (解約・上下並び替え)
	// ----------------------------------------------------------------------
	foldersGrid.addEventListener('click', function (e) {
		const card = e.target.closest('.kusf-folder-card');
		if (!card) return;

		// 1. サブアイテムの解約（「✕」ボタン）
		const itemRemoveBtn = e.target.closest('.kusf-item-remove-btn');
		if (itemRemoveBtn) {
			const subitemRow = itemRemoveBtn.closest('.kusf-subitem-row');
			if (subitemRow) {
				subitemRow.remove();
				syncState();
			}
			return;
		}

		// 2. サブアイテムの上下並び替え (Pro版のみ)
		if (!isPro) return;

		const btnUp = e.target.closest('.kusf-move-up');
		const btnDown = e.target.closest('.kusf-move-down');
		if (btnUp && !btnUp.disabled) {
			const currentRow = btnUp.closest('.kusf-subitem-row');
			const prevRow = currentRow.previousElementSibling;
			if (prevRow) {
				currentRow.parentNode.insertBefore(currentRow, prevRow);
				syncState();
			}
			return;
		}
		if (btnDown && !btnDown.disabled) {
			const currentRow = btnDown.closest('.kusf-subitem-row');
			const nextRow = currentRow.nextElementSibling;
			if (nextRow) {
				currentRow.parentNode.insertBefore(nextRow, currentRow);
				syncState();
			}
			return;
		}
	});

	// ----------------------------------------------------------------------
	// イベント処理: 左側擬似メニューのチェックボックス切替
	// ----------------------------------------------------------------------
	pseudoList.addEventListener('change', function (e) {
		const toggle = e.target;
		if (!toggle.classList.contains('kusf-item-toggle')) return;

		const itemRow = toggle.closest('.kusf-pseudo-menu-item');
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

		const firstCard = foldersGrid.querySelector('.kusf-folder-card');
		if (!firstCard) {
			toggle.checked = false;
			return;
		}

		const sublist = firstCard.querySelector('.kusf-folder-sublist');

		if (toggle.checked) {
			const currentCount = sublist.querySelectorAll('.kusf-subitem-row').length;
			if (currentCount >= maxItems) {
				alert(limitMessage);
				toggle.checked = false;
				return;
			}

			const newRow = createSubitemRow(slug, title, url, position, iconClass);
			if (!isPro) {
				insertRowSortedByPosition(sublist, newRow);
			} else {
				sublist.appendChild(newRow);
			}
			itemRow.classList.add('is-selected');
		} else {
			const existingRows = firstCard.querySelectorAll(`.kusf-subitem-row[data-slug="${slug}"]`);
			existingRows.forEach(r => r.remove());
			itemRow.classList.remove('is-selected');
		}

		syncState();
	});

	// ----------------------------------------------------------------------
	// イベント処理: 左側擬似メニューの行全体クリックでチェックボックス切替
	// ----------------------------------------------------------------------
	pseudoList.addEventListener('click', function (e) {
		const itemRow = e.target.closest('.kusf-pseudo-menu-item');
		if (!itemRow || itemRow.classList.contains('is-disabled')) return;

		// チェックボックス自体をクリックした場合は change イベントが自然発火するので二重発火を防止
		if (e.target.tagName && e.target.tagName.toLowerCase() === 'input') return;

		const checkbox = itemRow.querySelector('.kusf-item-toggle');
		if (checkbox && !checkbox.disabled) {
			checkbox.checked = !checkbox.checked;
			checkbox.dispatchEvent(new Event('change', { bubbles: true }));
		}
	});

	// 初期状態同期
	syncState();
});
