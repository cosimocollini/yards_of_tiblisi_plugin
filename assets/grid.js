(function () {
	'use strict';

	const config = window.yardsArchiveConfig || {};
	const GRID_SIZE   = config.gridSize || 8;
	const REFRESH_MS  = config.refreshMs || 4000;
	const REST_URL    = config.restUrl;

	let cells = []; // { id, locked, data }
	let intervalId = null;

	function initCells() {
		cells = Array.from({ length: GRID_SIZE }, () => ({ id: null, locked: false, data: null }));
	}

	function getCurrentIds() {
		return cells.filter((c) => c.id !== null).map((c) => c.id);
	}

	function getLockedIds() {
		return cells.filter((c) => c.locked && c.id !== null).map((c) => c.id);
	}

	async function fetchRandomImages(excludeIds, count) {
		const url = new URL(REST_URL);
		url.searchParams.set('count', count);
		excludeIds.forEach((id) => url.searchParams.append('exclude[]', id));

		const response = await fetch(url.toString(), {
			headers: { 'X-WP-Nonce': config.nonce },
		});

		if (!response.ok) {
			return [];
		}

		return response.json();
	}

	async function refreshUnlockedCells() {
		const unlockedIndexes = cells
			.map((c, i) => (!c.locked ? i : null))
			.filter((i) => i !== null);

		if (unlockedIndexes.length === 0) {
			stopRefresh(); // tutte locked, niente da aggiornare
			return;
		}

		// Escludi sia le celle bloccate sia quelle libere correnti, per non ripetere immagini visibili.
		const excludeIds = getCurrentIds();
		const fresh = await fetchRandomImages(excludeIds, unlockedIndexes.length);

		fresh.forEach((item, idx) => {
			const cellIndex = unlockedIndexes[idx];
			if (cellIndex === undefined) return;
			cells[cellIndex].id = item.id;
			cells[cellIndex].data = item;
		});

		render();
	}

	function toggleLock(index) {
		const cell = cells[index];
		if (!cell || cell.id === null) return;

		cell.locked = !cell.locked;
		render();

		// Se sblocco una cella e il ciclo era fermo (tutte erano locked), lo faccio ripartire.
		if (!cell.locked && intervalId === null) {
			startRefresh();
		}
	}

	function render() {
		const container = document.getElementById('yards-archive-grid');
		if (!container) return;

		container.innerHTML = cells
			.map((cell, index) => {
				if (!cell.data) {
					return `<div class="yards-archive-cell yards-archive-cell--empty"></div>`;
				}
				const lockedClass = cell.locked ? 'yards-archive-cell--locked' : '';
				return `
					<div class="yards-archive-cell ${lockedClass}" data-index="${index}" role="button" tabindex="0" aria-pressed="${cell.locked}">
						<img src="${cell.data.image}" alt="${escapeHtml(cell.data.title)}" loading="lazy" />
						${cell.locked ? '<span class="yards-archive-lock-icon" aria-hidden="true">&#128274;</span>' : ''}
					</div>
				`;
			})
			.join('');

		container.querySelectorAll('.yards-archive-cell').forEach((el) => {
			el.addEventListener('click', () => toggleLock(parseInt(el.dataset.index, 10)));
			el.addEventListener('keydown', (e) => {
				if (e.key === 'Enter' || e.key === ' ') {
					e.preventDefault();
					toggleLock(parseInt(el.dataset.index, 10));
				}
			});
		});
	}

	function escapeHtml(str) {
		const div = document.createElement('div');
		div.textContent = str || '';
		return div.innerHTML;
	}

	function startRefresh() {
		if (intervalId !== null) return;
		intervalId = setInterval(refreshUnlockedCells, REFRESH_MS);
	}

	function stopRefresh() {
		if (intervalId !== null) {
			clearInterval(intervalId);
			intervalId = null;
		}
	}

	async function init() {
		const container = document.getElementById('yards-archive-grid');
		if (!container || !REST_URL) return;

		initCells();
		await refreshUnlockedCells(); // popolamento iniziale di tutte le celle
		startRefresh();
	}

	document.addEventListener('DOMContentLoaded', init);
})();
