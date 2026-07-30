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
	const limitMessage = typeof kusfParams !== 'undefined' ? kusfParams.limitMessage : '最大件数に達しました。';
	const protectedSlugs = (typeof kusfParams !== 'undefined' && Array.isArray(kusfParams.protectedSlugs)) ? kusfParams.protectedSlugs : ['ku-submenu', 'ku-submenu-folder'];

	// Pro版が有効な場合は Pro版の JS (pro-admin-settings.js) が上位互換として制御を担当するためここで早期リターン
	if (isPro) {
		return;
	}

	/**
	 * 隠しフィールド (JSON) とUI状態の同期
	 */
	function syncState() {
		updateButtonStates();
		updateHiddenFieldValue();
		syncPseudoMenuCheckboxes();
	}

	/**
	 * 擬似サイドメニューのチェックボックス状態を現在のフォルダー内格納項目と同期
	 */
	function syncPseudoMenuCheckboxes() {
		const firstCard = foldersGrid.querySelector('.kusf-folder-card');
		if (!firstCard) return;

		const allSubitemRows = firstCard.querySelectorAll('.kusf-subitem-row');
		const storedSlugs = new Set();
		allSubitemRows.forEach(row => storedSlugs.add(row.getAttribute('data-slug')));

		const pseudoItems = pseudoList.querySelectorAll('.kusf-pseudo-menu-item');
		pseudoItems.forEach(item => {
			const slug = item.getAttribute('data-slug');
			const checkbox = item.querySelector('.kusf-item-toggle');
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
		const moveUpText = kusfParams.moveUp || 'Move Up';
		const moveDownText = kusfParams.moveDown || 'Move Down';
		const removeText = kusfParams.removeItem || 'Remove Item';

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
				<button type="button" class="button button-small kusf-move-up" title="${escapeHtml(moveUpText)}">
					<span class="dashicons dashicons-arrow-up-alt2"></span>
				</button>
				<button type="button" class="button button-small kusf-move-down" title="${escapeHtml(moveDownText)}">
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

		// 2. サブアイテムの上下並び替え
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
			sublist.appendChild(newRow);
			itemRow.classList.add('is-selected');
		} else {
			const existingRows = firstCard.querySelectorAll(`.kusf-subitem-row[data-slug="${slug}"]`);
			existingRows.forEach(r => r.remove());
			itemRow.classList.remove('is-selected');
		}

		syncState();
	});

	// 初期状態同期
	syncState();
});


