const config = window.MLGPAdmin || {};
let root = document.getElementById("mlgp-admin-app");

if (root || config.isPostEditor) {
	if (!root && config.isPostEditor) {
		root = document.getElementById("mlgp-editor-picker-root");
		if (!root) {
			root = document.createElement("div");
			root.id = "mlgp-editor-picker-root";
			document.body.appendChild(root);
		}
	}

	const page = (root && root.dataset.page) || config.page || "dashboard";
	const DEFAULT_HEADING_FONT_SIZE = 34;


	function defaultAlbumDisplaySettings(input = {}, globalSettings = config.settings || {}) {
		const source = input && typeof input === "object" ? input : {};
		const globals = globalSettings && typeof globalSettings === "object" ? globalSettings : {};

		return {
			displayType: String(source.display_type || source.displayType || globals.default_album_display_type || "grid"),
			columnsDesktop: Number(source.columns_desktop ?? source.columnsDesktop ?? globals.album_columns_desktop ?? 4),
			columnsTablet: Number(source.columns_tablet ?? source.columnsTablet ?? globals.album_columns_tablet ?? 3),
			columnsMobile: Number(source.columns_mobile ?? source.columnsMobile ?? globals.album_columns_mobile ?? 2),
			cardGap: Number(source.card_gap ?? source.cardGap ?? globals.album_card_gap ?? 18),
			cardPadding: Number(source.card_padding ?? source.cardPadding ?? globals.album_card_padding ?? 0),
			cardMargin: Number(source.card_margin ?? source.cardMargin ?? globals.album_card_margin ?? 0),
			cardBorderWidth: Number(source.card_border_width ?? source.cardBorderWidth ?? globals.album_card_border_width ?? 0),
			cardBorderColor: String(source.card_border_color || source.cardBorderColor || globals.album_card_border_color || "#d7e0ea"),
			cardBorderOpacity: Number(source.card_border_opacity ?? source.cardBorderOpacity ?? globals.album_card_border_opacity ?? 100),
			gapBackgroundColor: String(source.gap_background_color || source.gapBackgroundColor || globals.album_gap_background_color || "#ffffff"),
			gapBackgroundOpacity: Number(source.gap_background_opacity ?? source.gapBackgroundOpacity ?? globals.album_gap_background_opacity ?? 100),
			cardRadius: Number(source.card_radius ?? source.cardRadius ?? globals.album_card_radius ?? 0),
			justifiedRowHeight: Number(source.justified_row_height ?? source.justifiedRowHeight ?? 220),
			coverWidth: Number(source.album_cover_width ?? source.coverWidth ?? globals.album_cover_width ?? 360),
			coverHeight: Number(source.album_cover_height ?? source.coverHeight ?? globals.album_cover_height ?? 280),
			coverFit: String(source.album_cover_fit || source.coverFit || globals.album_cover_fit || "contain"),
			coverLockRatio: Number(source.album_cover_lock_ratio ?? source.coverLockRatio ?? globals.album_cover_lock_ratio ?? 1),
			roundedCorners: Number(source.rounded_corners ?? source.roundedCorners ?? (Number(globals.album_card_radius ?? 0) > 0 ? 1 : 0)),
			paginationEnabled: Number(source.pagination_enabled ?? source.paginationEnabled ?? globals.album_pagination_enabled ?? 1),
			itemsPerPage: Number(source.items_per_page ?? source.itemsPerPage ?? globals.album_items_per_page ?? 18),
			showHeading: Number(source.show_heading ?? source.showHeading ?? globals.album_show_heading ?? 0),
			showDescription: Number(source.show_description ?? source.showDescription ?? globals.album_show_description ?? 0),
			showTitles: Number(source.show_titles ?? source.showTitles ?? globals.album_show_titles ?? 1),
			showCaptions: Number(source.show_captions ?? source.showCaptions ?? globals.album_show_captions ?? 0),
			headingFontSize: Number(source.heading_font_size ?? source.headingFontSize ?? globals.heading_font_size ?? DEFAULT_HEADING_FONT_SIZE),
			headingColor: String(source.heading_color || source.headingColor || globals.heading_color || "#172033"),
			itemTitleFontSize: Number(source.item_title_font_size ?? source.itemTitleFontSize ?? globals.item_title_font_size ?? 18),
			itemTitleColor: String(source.item_title_color || source.itemTitleColor || globals.item_title_color || "#172033"),
		};
	}

	function serializeAlbumSettings(form) {
		return {
			columns_desktop: Number(form.columnsDesktop || 4),
			columns_tablet: Number(form.columnsTablet || 3),
			columns_mobile: Number(form.columnsMobile || 2),
			card_gap: Number(form.cardGap || 18),
			card_padding: Number(form.cardPadding || 0),
			card_margin: Number(form.cardMargin || 0),
			card_border_width: Number(form.cardBorderWidth || 0),
			card_border_color: String(form.cardBorderColor || "#d7e0ea"),
			card_border_opacity: Number(form.cardBorderOpacity ?? 100),
			gap_background_color: String(form.gapBackgroundColor || "#ffffff"),
			gap_background_opacity: Number(form.gapBackgroundOpacity ?? 100),
			card_radius: Number(form.cardRadius || 0),
			justified_row_height: Number(form.justifiedRowHeight || 220),
			album_cover_width: Number(form.coverWidth || 360),
			album_cover_height: Number(form.coverHeight || 280),
			album_cover_fit: String(form.coverFit || "contain"),
			album_cover_lock_ratio: Number(form.coverLockRatio || 0) ? 1 : 0,
			rounded_corners: Number(form.roundedCorners || 0) ? 1 : 0,
			pagination_enabled: Number(form.paginationEnabled || 0) ? 1 : 0,
			items_per_page: Number(form.itemsPerPage || 24),
			show_heading: Number(form.showHeading || 0) ? 1 : 0,
			show_description: Number(form.showDescription || 0) ? 1 : 0,
			show_titles: Number(form.showTitles || 0) ? 1 : 0,
			show_captions: Number(form.showCaptions || 0) ? 1 : 0,
			heading_font_size: Number(form.headingFontSize || DEFAULT_HEADING_FONT_SIZE),
			heading_color: String(form.headingColor || "#172033"),
			item_title_font_size: Number(form.itemTitleFontSize || 18),
			item_title_color: String(form.itemTitleColor || "#172033"),
		};
	}

	function emptyAlbumEditorState() {
		return {
			activeId: 0,
			album: null,
			form: {
				title: "",
				slug: "",
				status: "draft",
				description: "",
				...defaultAlbumDisplaySettings(),
			},
			items: [],
			availableGalleries: [],
			availableAlbums: [],
			albumDirty: false,
			itemsDirty: false,
		};
	}

	const state = {
		page,
		dashboard: null,
		galleries: [],
		albums: [],
		sorting: {
			galleries: (config.sorting && config.sorting.galleries) || "updated_at_desc",
			albums: (config.sorting && config.sorting.albums) || "updated_at_desc",
		},
		tags: {
			items: [],
			stats: {},
			query: "",
		},
		settings: config.settings || {},
		settingsDraft: { ...(config.settings || {}) },
		settingsOps: {
			regenerating: false,
			progressText: '',
			progressPercent: 0,
		},
		validation: config.validation || null,
		license: config.license || null,
		settingsUi: {
			tab: "general",
		},
		albumEditor: emptyAlbumEditorState(),
		albumPicker: {
			open: false,
			type: "gallery",
			context: "album-manager", // 'album-manager' or 'post-editor'
			selectedIds: [],
		},
		selectedGalleryIds: [],
		selectedAlbumIds: [],
	};
	const mediaFrames = {};

	function getSelectedGalleryIds() {
		return Array.isArray(state.selectedGalleryIds) ? state.selectedGalleryIds.map((id) => Number(id || 0)).filter((id) => id > 0) : [];
	}

	function getSelectedAlbumIds() {
		return Array.isArray(state.selectedAlbumIds) ? state.selectedAlbumIds.map((id) => Number(id || 0)).filter((id) => id > 0) : [];
	}

	function isGallerySelected(id) {
		return getSelectedGalleryIds().includes(Number(id || 0));
	}

	function isAlbumSelected(id) {
		return getSelectedAlbumIds().includes(Number(id || 0));
	}

	function toSortableTimestamp(value) {
		if (!value) {
			return 0;
		}

		const normalized = String(value).replace(" ", "T");
		const parsed = Date.parse(normalized);
		return Number.isFinite(parsed) ? parsed : 0;
	}

	function sortNewestFirst(items) {
		return [...(Array.isArray(items) ? items : [])].sort((left, right) => {
			const leftTime = Math.max(
				toSortableTimestamp(left && left.updated_at),
				toSortableTimestamp(left && left.created_at),
				Number(left && left.id || 0)
			);
			const rightTime = Math.max(
				toSortableTimestamp(right && right.updated_at),
				toSortableTimestamp(right && right.created_at),
				Number(right && right.id || 0)
			);

			return rightTime - leftTime;
		});
	}

	function toggleSelection(listKey, id, checked) {
		const numericId = Number(id || 0);

		if (!numericId) {
			return;
		}

		const current = Array.isArray(state[listKey]) ? [...state[listKey]] : [];
		const next = checked
			? Array.from(new Set([...current, numericId]))
			: current.filter((itemId) => Number(itemId) !== numericId);

		state[listKey] = next;
	}

	function setSelectionFromItems(listKey, items, checked) {
		state[listKey] = checked
			? (Array.isArray(items) ? items.map((item) => Number(item.id || 0)).filter((id) => id > 0) : [])
			: [];
	}

	function clearGallerySelection() {
		state.selectedGalleryIds = [];
	}

	function clearAlbumSelection() {
		state.selectedAlbumIds = [];
	}

	function escapeHtml(value) {
		return String(value || "")
			.replaceAll("&", "&amp;")
			.replaceAll("<", "&lt;")
			.replaceAll(">", "&gt;")
			.replaceAll('"', "&quot;")
			.replaceAll("'", "&#039;");
	}

	function renderSortControl(type, selectedValue) {
		const options = [
			["id_desc", "ID ↓"],
			["id_asc", "ID ↑"],
			["created_at_desc", "Criação ↓"],
			["created_at_asc", "Criação ↑"],
			["updated_at_desc", "Modificação ↓"],
			["updated_at_asc", "Modificação ↑"],
		];
		const attr = type === "album" ? "data-mlgp-album-sort" : "data-mlgp-gallery-sort";
		const value = selectedValue || "updated_at_desc";

		return `
			<label class="mlgp-sort-control">
				<span>Ordenar por</span>
				<select ${attr}="1">
					${options.map(([optionValue, label]) => `<option value="${escapeHtml(optionValue)}" ${optionValue === value ? "selected" : ""}>${escapeHtml(label)}</option>`).join("")}
				</select>
			</label>
		`;
	}

	function clampNumber(value, min, max, fallback) {
		const parsed = Number.parseInt(String(value ?? ""), 10);
		const safeValue = Number.isFinite(parsed) ? parsed : fallback;

		return Math.max(min, Math.min(max, safeValue));
	}

	function readControlValue(element) {
		if (!(element instanceof HTMLInputElement || element instanceof HTMLTextAreaElement || element instanceof HTMLSelectElement)) {
			return "";
		}

		if (element instanceof HTMLInputElement && element.type === "checkbox") {
			return element.checked ? "1" : "0";
		}

		return element.value;
	}

	function rememberSettingsFormValues(form) {
		if (!(form instanceof HTMLFormElement) || form.id !== "mlgp-settings-form") {
			return;
		}

		state.settingsDraft = {
			...(state.settingsDraft || {}),
			...collectFormValues(form),
		};
	}

	function syncLockedAlbumCoverValues(previousWidth, previousHeight, changedField, nextValue, lockRatioEnabled) {
		const currentWidth = clampNumber(previousWidth, 120, 1800, 360);
		const currentHeight = clampNumber(previousHeight, 120, 1200, 280);

		if (changedField === "coverWidth" || changedField === "album_cover_width") {
			const nextWidth = clampNumber(nextValue, 120, 1800, currentWidth);

			return {
				coverWidth: nextWidth,
				coverHeight: lockRatioEnabled && currentWidth > 0
					? clampNumber(Math.round((nextWidth * currentHeight) / currentWidth), 120, 1200, currentHeight)
					: currentHeight,
			};
		}

		if (changedField === "coverHeight" || changedField === "album_cover_height") {
			const nextHeight = clampNumber(nextValue, 120, 1200, currentHeight);

			return {
				coverWidth: lockRatioEnabled && currentHeight > 0
					? clampNumber(Math.round((nextHeight * currentWidth) / currentHeight), 120, 1800, currentWidth)
					: currentWidth,
				coverHeight: nextHeight,
			};
		}

		return {
			coverWidth: currentWidth,
			coverHeight: currentHeight,
		};
	}

	function mediaFileLabel(url) {
		const normalized = String(url || "").trim();

		if (!normalized) {
			return "Usando seta padrão do plugin.";
		}

		try {
			const pathname = new URL(normalized, window.location.origin).pathname || "";
			const filename = pathname.split("/").filter(Boolean).pop() || normalized;
			return decodeURIComponent(filename);
		} catch (error) {
			const filename = normalized.split("/").filter(Boolean).pop() || normalized;
			return decodeURIComponent(filename);
		}
	}

	function renderMediaPicker(fieldName, label, url, hint) {
		const cleanUrl = String(url || "").trim();

		return `
			<div class="mlgp-field">
				<label>${escapeHtml(label)}</label>
				<div class="mlgp-media-picker">
					<div class="mlgp-media-picker__preview ${cleanUrl ? "" : "is-empty"}" data-mlgp-media-preview="${escapeHtml(fieldName)}">
						${cleanUrl ? `<img src="${escapeHtml(cleanUrl)}" alt="">` : `<span>Usando seta padrão do plugin.</span>`}
					</div>
					<input type="hidden" name="${escapeHtml(fieldName)}" value="${escapeHtml(cleanUrl)}">
					<div class="mlgp-media-picker__meta">
						<small>${escapeHtml(hint)}</small>
						<div class="mlgp-inline-code" data-mlgp-media-value="${escapeHtml(fieldName)}">${escapeHtml(mediaFileLabel(cleanUrl))}</div>
						<div class="mlgp-actions">
							<button type="button" class="mlgp-button mlgp-button--ghost" data-mlgp-media-pick="${escapeHtml(fieldName)}">Escolher PNG</button>
							<button type="button" class="mlgp-button mlgp-button--ghost" data-mlgp-media-clear="${escapeHtml(fieldName)}">Remover</button>
						</div>
					</div>
				</div>
			</div>
		`;
	}

	function updateMediaPickerValue(fieldName, url) {
		const cleanUrl = String(url || "").trim();
		const input = root.querySelector(`[name="${fieldName}"]`);
		const preview = root.querySelector(`[data-mlgp-media-preview="${fieldName}"]`);
		const label = root.querySelector(`[data-mlgp-media-value="${fieldName}"]`);

		if (input) {
			input.value = cleanUrl;
		}

		if (preview) {
			preview.classList.toggle("is-empty", !cleanUrl);
			preview.innerHTML = cleanUrl
				? `<img src="${escapeHtml(cleanUrl)}" alt="">`
				: "<span>Usando seta padrão do plugin.</span>";
		}

		if (label) {
			label.textContent = mediaFileLabel(cleanUrl);
		}

		state.settingsDraft = {
			...(state.settingsDraft || {}),
			[fieldName]: cleanUrl,
		};
	}

	function openMediaPicker(fieldName) {
		if (!window.wp || !window.wp.media) {
			throw new Error("A biblioteca de midia do WordPress nao esta disponivel nesta tela.");
		}

		if (!mediaFrames[fieldName]) {
			const isPrev = fieldName === "nav_arrow_prev_url";
			const title = isPrev ? "Selecionar PNG da seta anterior" : "Selecionar PNG da seta proxima";

			mediaFrames[fieldName] = window.wp.media({
				title,
				button: {
					text: "Usar esta imagem",
				},
				library: {
					type: "image",
				},
				multiple: false,
			});

			mediaFrames[fieldName].on("select", () => {
				const selection = mediaFrames[fieldName].state().get("selection");
				const attachment = selection && selection.first ? selection.first() : null;

				if (!attachment) {
					return;
				}

				const data = attachment.toJSON ? attachment.toJSON() : {};
				updateMediaPickerValue(fieldName, data.url || "");
			});
		}

		mediaFrames[fieldName].open();
	}

	function request(action, payload) {
		const formData = new FormData();
		formData.append("action", action);
		formData.append("nonce", config.nonce || "");

		Object.entries(payload || {}).forEach(([key, value]) => {
			formData.append(key, value == null ? "" : value);
		});

		return fetch(config.ajaxUrl, {
			method: "POST",
			credentials: "same-origin",
			body: formData,
		})
			.then((response) => response.json())
			.then((response) => {
				if (!response.success) {
					throw new Error(response.data && response.data.message ? response.data.message : config.strings.genericError);
				}

				return response.data;
			});
	}

	function ensureNoticeStack() {
		let stack = document.getElementById("mlgp-notice-stack");

		if (!stack) {
			stack = document.createElement("div");
			stack.id = "mlgp-notice-stack";
			stack.className = "mlgp-notice-stack";
			document.body.appendChild(stack);
		}

		return stack;
	}

	function showNotice(message, type) {
		if (!String(message || "").trim()) {
			return;
		}

		const stack = ensureNoticeStack();
		const notice = document.createElement("div");
		notice.className = `mlgp-notice is-${type || "success"}`;
		notice.setAttribute("role", "status");
		notice.setAttribute("aria-live", type === "error" ? "assertive" : "polite");
		notice.textContent = message;
		stack.appendChild(notice);

		while (stack.children.length > 4) {
			stack.firstElementChild.remove();
		}

		window.setTimeout(() => {
			notice.classList.add("is-leaving");
			window.setTimeout(() => notice.remove(), 220);
		}, 5000);
	}

	function renderCopyableShortcode(value, variant = "inline") {
		const shortcode = String(value || "").trim();

		if (!shortcode) {
			return "";
		}

		const classes = variant === "block"
			? "mlgp-shortcode-box__code is-copyable"
			: "mlgp-inline-code is-copyable";

		return `<code class="${classes}" tabindex="0" title="Clique para copiar" aria-label="Copiar shortcode para a area de transferencia" data-mlgp-copy-shortcode="${escapeHtml(shortcode)}">${escapeHtml(shortcode)}</code>`;
	}

	function renderShortcodeBox(label, value) {
		const shortcodeMarkup = renderCopyableShortcode(value, "block");

		if (!shortcodeMarkup) {
			return "";
		}

		return `
			<div class="mlgp-shortcode-box">
				<span>${escapeHtml(label)}</span>
				${shortcodeMarkup}
			</div>
		`;
	}


	function entityPublicUrl(item, type) {
		if (type !== "gallery") {
			return "";
		}

		if (item && item.public_url) {
			return String(item.public_url);
		}

		const slug = String((item && item.slug) || "").trim();

		if (!slug || !config.siteUrl) {
			return "";
		}

		return `${String(config.siteUrl).replace(/\/$/, "")}/galeria/${encodeURIComponent(slug)}/`;
	}

	function renderListItem(item, type) {
		const badge = type === "gallery" ? "Galeria" : "Album";
		const count = type === "gallery" ? "imagens" : "itens";
		const cover = item.cover && item.cover.thumb_url
			? `<div class="mlgp-entity-row__thumb"><img src="${escapeHtml(item.cover.thumb_url)}" alt="${escapeHtml(item.title)}"></div>`
			: `<div class="mlgp-entity-row__thumb is-empty">${type === "gallery" ? "GAL" : "ALB"}</div>`;
		const shortcode = String(item.shortcode || "").trim();
		const publicUrl = entityPublicUrl(item, type);

		return `
			<div class="mlgp-list__item mlgp-entity-row">
				${cover}
				<div class="mlgp-entity-row__main">
					<h3>${escapeHtml(item.title)}</h3>
					<div class="mlgp-list__meta">
						<span class="mlgp-pill">${badge}</span>
						<span>slug: ${escapeHtml(item.slug)}</span>
						<span>${item.item_count || 0} ${count}</span>
						<span>status: ${escapeHtml(item.status)}</span>
					</div>
					${renderCopyableShortcode(shortcode)}
					${publicUrl ? renderCopyableShortcode(publicUrl) : ""}
				</div>
				<div class="mlgp-list__meta">
					<span>${escapeHtml(item.updated_at || "Sem data")}</span>
				</div>
			</div>
		`;
	}

	function extractAlbumForm(album) {
		const displaySettings = defaultAlbumDisplaySettings(album && album.settings ? album.settings : {});

		return {
			title: album && album.title ? String(album.title) : "",
			slug: album && album.slug ? String(album.slug) : "",
			status: album && album.status ? String(album.status) : "draft",
			description: album && album.description ? String(album.description) : "",
			displayType: String(album && album.display_type ? album.display_type : displaySettings.displayType),
			...displaySettings,
		};
	}

	function syncAlbumEditor(editor) {
		if (!editor || !editor.album) {
			state.albumEditor = emptyAlbumEditorState();
			closeAlbumPicker();
			return;
		}

		state.albumEditor = {
			activeId: Number(editor.album.id || 0),
			album: editor.album,
			form: extractAlbumForm(editor.album),
			items: Array.isArray(editor.items) ? editor.items : [],
			availableGalleries: Array.isArray(editor.available_galleries) ? editor.available_galleries : [],
			availableAlbums: Array.isArray(editor.available_albums) ? editor.available_albums : [],
			albumDirty: false,
			itemsDirty: false,
		};
		closeAlbumPicker();
	}

	function hasAlbumPendingChanges() {
		return !!state.albumEditor.albumDirty || !!state.albumEditor.itemsDirty;
	}

	function markAlbumDirty() {
		state.albumEditor.albumDirty = true;
		updateAlbumDirtyUi();
	}

	function markAlbumItemsDirty() {
		state.albumEditor.itemsDirty = true;
		updateAlbumDirtyUi();
	}

	function updateAlbumDirtyUi() {
		const badge = root.querySelector("[data-mlgp-album-status]");
		const saveButton = root.querySelector("[data-mlgp-save-album-all]");
		const dirty = hasAlbumPendingChanges();

		if (badge) {
			let label = "Sincronizado";

			if (state.albumEditor.albumDirty && state.albumEditor.itemsDirty) {
				label = "Album e estrutura pendentes";
			} else if (state.albumEditor.albumDirty) {
				label = "Dados do album pendentes";
			} else if (state.albumEditor.itemsDirty) {
				label = "Estrutura do album pendente";
			}

			badge.textContent = label;
			badge.className = dirty ? "mlgp-status-pill is-warning" : "mlgp-status-pill is-clean";
		}

		if (saveButton) {
			saveButton.disabled = !dirty;
		}
	}

	function getAlbumItemKey(item) {
		return `${String(item.item_type || "gallery")}:${Number(item.item_id || 0)}`;
	}

	function getAvailableAlbumGalleries() {
		const selectedKeys = new Set(
			(state.albumEditor.items || [])
				.filter((item) => item.item_type === "gallery")
				.map(getAlbumItemKey)
		);

		return sortNewestFirst((state.albumEditor.availableGalleries || []).filter((item) => !selectedKeys.has(`gallery:${Number(item.id || 0)}`)));
	}

	function getAvailableSubalbums() {
		const selectedKeys = new Set(
			(state.albumEditor.items || [])
				.filter((item) => item.item_type === "album")
				.map(getAlbumItemKey)
		);

		return sortNewestFirst((state.albumEditor.availableAlbums || []).filter((item) => !selectedKeys.has(`album:${Number(item.id || 0)}`)));
	}

	function formatCount(value) {
		const numericValue = Number(value || 0);

		if (!Number.isFinite(numericValue)) {
			return "0";
		}

		return new Intl.NumberFormat("pt-BR").format(numericValue);
	}

	function renderDashboardStatusChip(label, value, tone) {
		const numericValue = Math.max(0, Number(value || 0));

		if (!numericValue) {
			return "";
		}

		return `
			<span class="mlgp-status-chip ${tone}">
				<strong>${formatCount(numericValue)}</strong>
				<span>${escapeHtml(label)}</span>
			</span>
		`;
	}

	function renderDashboardQuickCard(item) {
		return `
			<a class="mlgp-quick-card" href="${escapeHtml(item.href || "#")}">
				<span class="mlgp-quick-card__eyebrow">${escapeHtml(item.eyebrow || "Fluxo")}</span>
				<strong>${escapeHtml(item.title || "")}</strong>
				<span>${escapeHtml(item.description || "")}</span>
				<small class="mlgp-quick-card__footer">${escapeHtml(item.footer || "Abrir modulo")}</small>
			</a>
		`;
	}

	function renderDashboardStatCard(item) {
		return `
			<article class="mlgp-stat">
				<span class="mlgp-stat__label">${escapeHtml(item.label || "")}</span>
				<div class="mlgp-stat__value">${escapeHtml(formatCount(item.value || 0))}</div>
				<p class="mlgp-stat__detail">${escapeHtml(item.detail || "")}</p>
			</article>
		`;
	}

	function renderDashboardLegacy() {
		const dashboard = state.dashboard || { stats: {}, recent_galleries: [], recent_albums: [] };
		const stats = dashboard.stats || {};
		const galleries = dashboard.recent_galleries || [];
		const albums = dashboard.recent_albums || [];

		root.innerHTML = `
			<section class="mlgp-action-grid">
				<a class="mlgp-quick-card" href="${escapeHtml((config.pageUrls && config.pageUrls.addImages) || "#")}">
					<strong>Nova galeria</strong>
					<span>Crie a galeria com upload e shortcode no mesmo fluxo.</span>
				</a>
				<a class="mlgp-quick-card" href="${escapeHtml((config.pageUrls && config.pageUrls.albums) || "#")}">
					<strong>Albuns</strong>
					<span>Organize galerias e prepare a navegacao comercial.</span>
				</a>
				<a class="mlgp-quick-card" href="${escapeHtml((config.pageUrls && config.pageUrls.tags) || "#")}">
					<strong>Tags</strong>
					<span>Centralize filtros por assunto e reutilize shortcodes por tag.</span>
				</a>
				<a class="mlgp-quick-card" href="${escapeHtml((config.pageUrls && config.pageUrls.settings) || "#")}">
					<strong>Configurações</strong>
					<span>Controle grid, lightbox, lazy load e labels globais.</span>
				</a>
			</section>

			<section class="mlgp-stats">
				<article class="mlgp-stat">
					<span class="mlgp-stat__label">Galerias</span>
					<div class="mlgp-stat__value">${stats.galleries || 0}</div>
				</article>
				<article class="mlgp-stat">
					<span class="mlgp-stat__label">Albuns</span>
					<div class="mlgp-stat__value">${stats.albums || 0}</div>
				</article>
				<article class="mlgp-stat">
					<span class="mlgp-stat__label">Itens de galeria</span>
					<div class="mlgp-stat__value">${stats.gallery_items || 0}</div>
				</article>
				<article class="mlgp-stat">
					<span class="mlgp-stat__label">Itens de album</span>
					<div class="mlgp-stat__value">${stats.album_items || 0}</div>
				</article>
			</section>

			<section class="mlgp-dashboard-grid">
				<article class="mlgp-card-panel">
					<h2>Ultimas galerias</h2>
					<p class="mlgp-card-panel__intro">Galerias com shortcode pronto, capa dedicada e upload direto no storage proprio do plugin.</p>
					<div class="mlgp-list">
						${galleries.length ? galleries.map((item) => renderListItem(item, "gallery")).join("") : '<div class="mlgp-empty">Nenhuma galeria cadastrada ainda.</div>'}
					</div>
				</article>

				<article class="mlgp-card-panel">
					<h2>Ultimos albuns</h2>
					<p class="mlgp-card-panel__intro">Estrutura preparada para albuns, subalbuns e shortcode dedicado para cada colecao.</p>
					<div class="mlgp-list">
						${albums.length ? albums.map((item) => renderListItem(item, "album")).join("") : '<div class="mlgp-empty">Nenhum album cadastrado ainda.</div>'}
					</div>
				</article>
			</section>

			<section class="mlgp-card-panel">
		<h2>Estado da base v${escapeHtml(config.version || "0.22.58")}</h2>
				<p class="mlgp-card-panel__intro">A base agora inclui criacao de galeria com upload imediato em lotes para acervos grandes, importacao por pasta local, ZIP e pasta do servidor, manager real de albuns, display types iniciais, tags por imagem, paginacao no frontend e motor de imagem com perfis, watermark, rotacao e regeneracao global.</p>
			</section>
		`;
	}

	function renderDashboard() {
		const dashboard = state.dashboard || { stats: {}, recent_galleries: [], recent_albums: [] };
		const stats = dashboard.stats || {};
		const galleries = dashboard.recent_galleries || [];
		const albums = dashboard.recent_albums || [];
		const validation = state.validation && typeof state.validation === "object" ? state.validation : {};
		const validationSummary = validation.summary && typeof validation.summary === "object" ? validation.summary : null;
		const okCount = Number(validationSummary && validationSummary.ok ? validationSummary.ok : 0);
		const warningCount = Number(validationSummary && validationSummary.warning ? validationSummary.warning : 0);
		const errorCount = Number(validationSummary && validationSummary.error ? validationSummary.error : 0);
		const hasValidation = !!validationSummary;
		const totalCollections = Number(stats.galleries || 0) + Number(stats.albums || 0);
		const totalAssets = Number(stats.gallery_items || 0) + Number(stats.album_items || 0);
		const healthTone = !hasValidation
			? "is-warning"
			: (errorCount > 0 ? "is-error" : (warningCount > 0 ? "is-warning" : "is-clean"));
		const healthStateLabel = !hasValidation
			? "Checar"
			: (errorCount > 0 ? "Revisar" : (warningCount > 0 ? "Atencao" : "Estavel"));
		const healthLabel = !hasValidation
			? "Abra a validacao para diagnostico completo da instalacao."
			: (errorCount > 0
				? "Ha pontos criticos para revisar"
				: (warningCount > 0 ? "Base operando com ajustes pendentes" : "Base consistente para seguir evoluindo"));
		const healthDetail = hasValidation
			? "A leitura usa a aba de validacao para acompanhar update, banco, storage e ambiente."
			: "O painel comercial ja esta pronto e a checagem detalhada fica disponivel na area de configuracoes.";
		const nextAction = Number(stats.galleries || 0) > 0
			? {
				title: "Refinar a experiencia comercial",
				description: "Com a base instalada, o passo mais produtivo agora e polir apresentacao, manager e fluxo de entrega.",
				href: (config.pageUrls && config.pageUrls.albums) || "#",
				label: "Abrir albuns e colecoes",
			}
			: {
				title: "Montar a primeira galeria",
				description: "Comece criando a estrutura com upload direto, capa e shortcode pronto para publicar.",
				href: (config.pageUrls && config.pageUrls.addImages) || "#",
				label: "Criar galeria agora",
			};
		const quickCards = [
			{
				eyebrow: "Fluxo rapido",
				title: "Criar galeria",
				description: "Monte a galeria com upload e shortcode no mesmo passo, sem depender do uploader nativo do WordPress.",
				href: (config.pageUrls && config.pageUrls.addImages) || "#",
				footer: "Entrar no fluxo guiado",
			},
			{
				eyebrow: "Colecoes",
				title: "Organizar albuns",
				description: "Estruture capas, ordem e colecoes para apresentar acervos com leitura mais comercial.",
				href: (config.pageUrls && config.pageUrls.albums) || "#",
				footer: "Gerenciar colecoes",
			},
			{
				eyebrow: "Descoberta",
				title: "Centralizar tags",
				description: "Reaproveite filtros por assunto e monte vitrines dinamicas com o mesmo acervo.",
				href: (config.pageUrls && config.pageUrls.tags) || "#",
				footer: "Abrir organizacao por tags",
			},
			{
				eyebrow: "Governanca",
				title: "Validar operacao",
				description: "Revise update, storage, ambiente e persistencia antes de seguir para ajustes maiores.",
				href: (config.pageUrls && config.pageUrls.settings) || "#",
				footer: "Checar configuracoes e validacao",
			},
		];
		const statCards = [
			{
				label: "Galerias",
				value: stats.galleries || 0,
				detail: Number(stats.galleries || 0) > 0
					? "Base pronta para campanhas, paginas e showcases."
					: "Ainda sem galerias. O fluxo guiado ja esta pronto.",
			},
			{
				label: "Albuns",
				value: stats.albums || 0,
				detail: Number(stats.albums || 0) > 0
					? "Colecoes ja podem agrupar galerias e subalbuns."
					: "Monte colecoes quando quiser uma navegacao mais premium.",
			},
			{
				label: "Imagens",
				value: stats.gallery_items || 0,
				detail: Number(stats.gallery_items || 0) > 0
					? "Acervo rastreado no storage proprio do plugin."
					: "Sem imagens indexadas ainda no storage dedicado.",
			},
			{
				label: "Vinculos de album",
				value: stats.album_items || 0,
				detail: Number(stats.album_items || 0) > 0
					? "Relacoes prontas para capa, hierarquia e navegacao."
					: "Os albuns ainda nao possuem itens vinculados.",
			},
		];

		root.innerHTML = `
			<section class="mlgp-card-panel mlgp-dashboard-hero">
				<div class="mlgp-dashboard-hero__lead">
					<div class="mlgp-dashboard-hero__header">
						<span class="mlgp-dashboard-hero__eyebrow">Painel comercial em operacao</span>
						<span class="mlgp-dashboard-hero__version">v${escapeHtml(config.version || "0.22.58")} • ${escapeHtml((state.license && (state.license.state_label || state.license.plan)) || "Free")}</span>
					</div>
					<h2>Painel executivo para galerias, albuns e shortcodes.</h2>
					<p>Leitura rapida da base ativa, proximo passo operacional e os ultimos ativos do plugin sem poluir a tela com informacao secundaria.</p>
					<div class="mlgp-dashboard-hero__stats">
						<span class="mlgp-dashboard-hero__stat"><strong>${escapeHtml(formatCount(totalCollections))}</strong> estruturas ativas</span>
						<span class="mlgp-dashboard-hero__stat"><strong>${escapeHtml(formatCount(totalAssets))}</strong> registros operacionais</span>
						<span class="mlgp-dashboard-hero__stat"><strong>${escapeHtml(formatCount(stats.galleries || 0))}</strong> galerias prontas</span>
					</div>
				</div>
				<div class="mlgp-dashboard-hero__aside">
					<div class="mlgp-dashboard-signal">
						<div class="mlgp-dashboard-signal__top">
							<span class="mlgp-dashboard-signal__label">Saude da base</span>
							<span class="mlgp-status-pill ${healthTone}">${escapeHtml(healthStateLabel)}</span>
						</div>
						<div class="mlgp-dashboard-signal__value">${escapeHtml(healthLabel)}</div>
						<p class="mlgp-dashboard-signal__detail">${escapeHtml(healthDetail)}</p>
						${hasValidation ? `<div class="mlgp-status-cluster">${renderDashboardStatusChip("OK", okCount, "is-ok")}${renderDashboardStatusChip("atencoes", warningCount, "is-warning")}${renderDashboardStatusChip("criticos", errorCount, "is-error")}</div>` : `<a class="mlgp-dashboard-link" href="${escapeHtml((config.pageUrls && config.pageUrls.settings) || "#")}">Abrir validacao e diagnostico</a>`}
					</div>
					<div class="mlgp-dashboard-signal is-soft">
						<span class="mlgp-dashboard-signal__label">Proximo passo</span>
						<div class="mlgp-dashboard-signal__value">${escapeHtml(nextAction.title)}</div>
						<p class="mlgp-dashboard-signal__detail">${escapeHtml(nextAction.description)}</p>
						<a class="mlgp-dashboard-link" href="${escapeHtml(nextAction.href)}">${escapeHtml(nextAction.label)}</a>
					</div>
				</div>
			</section>

			<section class="mlgp-action-grid mlgp-action-grid--dashboard">
				${quickCards.map(renderDashboardQuickCard).join("")}
			</section>

			<section class="mlgp-stats mlgp-stats--dashboard">
				${statCards.map(renderDashboardStatCard).join("")}
			</section>

			<section class="mlgp-dashboard-grid mlgp-dashboard-grid--support">
				<article class="mlgp-card-panel mlgp-story-card">
					<div class="mlgp-section-heading">
						<span class="mlgp-section-heading__eyebrow">Rota executiva</span>
						<h2>Fluxo recomendado agora</h2>
					</div>
					<p class="mlgp-card-panel__intro">Tres decisoes para seguir evoluindo sem perder a base validada.</p>
					<div class="mlgp-story-list">
						<div class="mlgp-story-step">
							<span class="mlgp-story-step__index">1</span>
							<div>
								<strong>Capturar e organizar</strong>
								<p>Crie galerias com upload direto e monte albuns quando o acervo pedir uma leitura mais comercial.</p>
							</div>
						</div>
						<div class="mlgp-story-step">
							<span class="mlgp-story-step__index">2</span>
							<div>
								<strong>Validar a estrutura</strong>
								<p>Use a aba de validacao para checar update, storage, banco e ambiente antes de novos polimentos.</p>
							</div>
						</div>
						<div class="mlgp-story-step">
							<span class="mlgp-story-step__index">3</span>
							<div>
								<strong>Lapidar a frente comercial</strong>
								<p>Com a base protegida, o proximo ganho vem de polish em managers, colecoes e apresentacao do produto.</p>
							</div>
						</div>
					</div>
				</article>
				<article class="mlgp-card-panel mlgp-story-card mlgp-story-card--insight">
					<div class="mlgp-section-heading">
						<span class="mlgp-section-heading__eyebrow">Leitura atual</span>
						<h2>Base pronta para crescer</h2>
					</div>
					<p class="mlgp-card-panel__intro">Pilares comerciais ja ativos na instalacao atual.</p>
					<div class="mlgp-story-badges">
						<span class="mlgp-story-badge">Storage proprio</span>
						<span class="mlgp-story-badge">Shortcodes nativos</span>
						<span class="mlgp-story-badge">Albuns e subalbuns</span>
						<span class="mlgp-story-badge">Tags reutilizaveis</span>
						<span class="mlgp-story-badge">Diagnostico ativo</span>
						<span class="mlgp-story-badge">Linha de update validada</span>
					</div>
					<div class="mlgp-story-callout">
						<strong>Resumo do momento</strong>
						<p>O plugin ja saiu da fase de sobrevivencia. O foco pode migrar para acabamento visual, confianca operacional e experiencia comercial do admin.</p>
					</div>
					<a class="mlgp-dashboard-link" href="${escapeHtml((config.pageUrls && config.pageUrls.settings) || "#")}">Abrir configuracoes e diagnostico</a>
				</article>
			</section>

			<section class="mlgp-dashboard-grid mlgp-dashboard-grid--latest">
				<article class="mlgp-card-panel">
					<div class="mlgp-section-heading">
						<span class="mlgp-section-heading__eyebrow">Fila recente</span>
						<h2>Ultimas galerias</h2>
					</div>
					<p class="mlgp-card-panel__intro">Galerias prontas para shortcode, capa dedicada e uso comercial imediato.</p>
					<div class="mlgp-list mlgp-list--dashboard">
						${galleries.length ? galleries.map((item) => renderListItem(item, "gallery")).join("") : '<div class="mlgp-empty">Nenhuma galeria cadastrada ainda.</div>'}
					</div>
				</article>

				<article class="mlgp-card-panel">
					<div class="mlgp-section-heading">
						<span class="mlgp-section-heading__eyebrow">Colecoes recentes</span>
						<h2>Ultimos albuns</h2>
					</div>
					<p class="mlgp-card-panel__intro">Colecoes com shortcode dedicado e leitura pronta para navegacao do acervo.</p>
					<div class="mlgp-list mlgp-list--dashboard">
						${albums.length ? albums.map((item) => renderListItem(item, "album")).join("") : '<div class="mlgp-empty">Nenhum album cadastrado ainda.</div>'}
					</div>
				</article>
			</section>
		`;
	}

	function renderGalleryRow(item) {
		return `
			<div class="mlgp-list__item mlgp-list__item--selectable">
				<label class="mlgp-check mlgp-check--row">
					<input type="checkbox" data-mlgp-select-gallery="${Number(item.id || 0)}" ${isGallerySelected(item.id) ? "checked" : ""}>
					<span>Selecionar</span>
				</label>
				<div>
					<h3>${escapeHtml(item.title)}</h3>
					<div class="mlgp-list__meta">
						<span class="mlgp-pill">${escapeHtml(item.status)}</span>
						<span>slug: ${escapeHtml(item.slug)}</span>
						<span>${item.item_count || 0} imagens</span>
					</div>
				</div>
				<div class="mlgp-actions">
					<button type="button" class="mlgp-button mlgp-button--ghost" data-edit-gallery="${item.id}">Editar</button>
					<button type="button" class="mlgp-button mlgp-button--danger" data-delete-gallery="${item.id}">Excluir</button>
				</div>
			</div>
		`;
	}

	function renderGalleries() {
		const selectedCount = getSelectedGalleryIds().length;
		const allSelected = state.galleries.length > 0 && selectedCount === state.galleries.length;

		root.innerHTML = `
			<div class="mlgp-grid-two">
				<section class="mlgp-panel">
					<h2>Cadastro de galeria</h2>
					<p class="mlgp-panel__intro">Crie a estrutura base da galeria. O editor de imagens entra na etapa seguinte sem quebrar esta fundacao.</p>
					<form id="mlgp-gallery-form" class="mlgp-form" method="post" action="${escapeHtml(config.formEndpoints?.gallerySave || "")}">
						<input type="hidden" name="action" value="mlgp_save_gallery_form">
						<input type="hidden" name="mlgp_gallery_form_nonce" value="${escapeHtml(config.formNonce || "")}">
						<input type="hidden" name="id" value="">
						<div class="mlgp-field">
							<label for="mlgp-gallery-title">Titulo</label>
							<input id="mlgp-gallery-title" type="text" name="title" required>
						</div>
						<div class="mlgp-field">
							<label for="mlgp-gallery-slug">Slug</label>
							<input id="mlgp-gallery-slug" type="text" name="slug" placeholder="gerado-automaticamente">
						</div>
						<div class="mlgp-field">
							<label for="mlgp-gallery-status">Status</label>
							<select id="mlgp-gallery-status" name="status">
								<option value="draft">Rascunho</option>
								<option value="publish">Publicado</option>
								<option value="private">Privado</option>
							</select>
						</div>
						<div class="mlgp-field">
							<label for="mlgp-gallery-description">Descricao</label>
							<textarea id="mlgp-gallery-description" name="description"></textarea>
						</div>
						<div class="mlgp-actions">
							<button type="submit" class="mlgp-button mlgp-button--accent">Salvar galeria</button>
							<button type="button" class="mlgp-button mlgp-button--ghost" data-reset-gallery="1">Limpar</button>
						</div>
					</form>
				</section>

				<section class="mlgp-panel">
					<div class="mlgp-panel-header-inline">
						<div>
							<h2>Galerias cadastradas</h2>
							<p class="mlgp-panel__intro">Persistencia ativa em tabela propria com slug unico e preparacao para capa, imagens e ordenacao.</p>
						</div>
						<div class="mlgp-actions mlgp-actions--bulk">
							${renderSortControl("gallery", state.sorting.galleries)}
							<label class="mlgp-check mlgp-check--compact">
								<input type="checkbox" data-mlgp-select-all-galleries="1" ${allSelected ? "checked" : ""}>
								<span>Selecionar todas</span>
							</label>
							<button type="button" class="mlgp-button mlgp-button--danger" data-mlgp-delete-selected-galleries="1" ${selectedCount ? "" : "disabled"}>${escapeHtml(config.strings.bulkDeleteSelectedGalleries || "Excluir galerias selecionadas")} (${selectedCount})</button>
							<button type="button" class="mlgp-button mlgp-button--danger" data-mlgp-delete-all-galleries="1" ${state.galleries.length ? "" : "disabled"}>${escapeHtml(config.strings.deleteAllGalleriesAction || "Excluir todas as galerias")}</button>
						</div>
					</div>
					<div class="mlgp-list">
						${state.galleries.length ? state.galleries.map(renderGalleryRow).join("") : '<div class="mlgp-empty">Nenhuma galeria cadastrada ainda.</div>'}
					</div>
				</section>
			</div>
		`;
	}


	function openAlbumPicker(type = "gallery", context = "album-manager") {
		state.albumPicker = {
			open: true,
			type: type === "album" ? "album" : "gallery",
			context: context === "post-editor" ? "post-editor" : "album-manager",
			selectedIds: [],
		};
	}

	function closeAlbumPicker() {
		state.albumPicker = {
			open: false,
			type: "gallery",
			context: "album-manager",
			selectedIds: [],
		};

		if (config.isPostEditor && root && root.id === "mlgp-editor-picker-root") {
			root.innerHTML = "";
		}
	}

	function togglePickerSelection(id, checked) {
		const numericId = Number(id || 0);
		if (!numericId) return;

		if (checked) {
			if (!state.albumPicker.selectedIds.includes(numericId)) {
				state.albumPicker.selectedIds.push(numericId);
			}
		} else {
			state.albumPicker.selectedIds = state.albumPicker.selectedIds.filter(sid => sid !== numericId);
		}
	}

	function renderAlbumPickerItem(item, type) {
		const isSelected = state.albumPicker.selectedIds.includes(Number(item.id || 0));
		const context = state.albumPicker.context || "album-manager";
		const isEditorMode = context === "post-editor";

		const cover = item.cover && item.cover.thumb_url
			? `<div class="mlgp-linked-item__media"><img src="${escapeHtml(item.cover.thumb_url)}" alt="${escapeHtml(item.title || "")}"></div>`
			: `<div class="mlgp-linked-item__media is-empty">${type === "album" ? "ALB" : "GAL"}</div>`;

		return `
			<div class="mlgp-linked-item mlgp-linked-item--picker ${isSelected ? "is-selected" : ""}">
				${cover}
				<div class="mlgp-linked-item__main">
					<div class="mlgp-linked-item__header">
						<strong>${escapeHtml(item.title || "Sem titulo")}</strong>
						<span class="mlgp-pill">${type === "album" ? "Subalbum" : "Galeria"}</span>
					</div>
					<div class="mlgp-list__meta">
						<span>ID ${Number(item.id || 0)}</span>
						<span>slug: ${escapeHtml(item.slug || "")}</span>
						<span>${Number(item.item_count || 0)} itens</span>
					</div>
				</div>
				<div class="mlgp-actions mlgp-actions--picker-side">
					<label class="mlgp-check mlgp-check--row mlgp-check--picker-clean">
						<input type="checkbox" data-mlgp-picker-select="${Number(item.id || 0)}" ${isSelected ? "checked" : ""}>
					</label>
					<button type="button" class="mlgp-button mlgp-button--accent" 
						data-mlgp-add-item-flow="${isEditorMode ? "shortcode" : "linked"}" 
						data-item-type="${type}" 
						data-item-id="${Number(item.id || 0)}">
						${isEditorMode ? "Inserir" : "Adicionar"}
					</button>
				</div>
			</div>
		`;
	}

	function renderAlbumPickerModal() {
		if (!state.albumPicker || !state.albumPicker.open) {
			return "";
		}

		const context = state.albumPicker.context || "album-manager";
		const isEditorMode = context === "post-editor";

		if (!isEditorMode && !state.albumEditor.activeId) {
			return "";
		}

		const pickerType = state.albumPicker.type === "album" ? "album" : "gallery";
		const isGallery = pickerType === "gallery";
		
		let items = [];
		if (isEditorMode) {
			items = isGallery ? state.galleries : state.albums;
		} else {
			items = isGallery ? getAvailableAlbumGalleries() : getAvailableSubalbums();
		}

		const title = isGallery ? (isEditorMode ? "Inserir galeria" : "Adicionar galerias") : (isEditorMode ? "Inserir album" : "Adicionar subalbuns");
		const description = isGallery
			? (isEditorMode ? "Escolha uma galeria para inserir no conteudo do post." : "Selecione galerias ja criadas para vincular ao album atual.")
			: (isEditorMode ? "Escolha um album para inserir no conteudo do post." : "Selecione subalbuns validos para ampliar a navegacao.");
		const selectedCount = state.albumPicker.selectedIds.length;

		const primaryActionLabel = isEditorMode ? `Inserir selecionados (${selectedCount})` : `Adicionar selecionados (${selectedCount})`;

		return `
			<div class="mlgp-modal-backdrop">
				<div class="mlgp-modal mlgp-modal--album-picker" role="dialog" aria-modal="true" aria-label="${escapeHtml(title)}">
					<div class="mlgp-modal__header">
						<div>
							<h2>${escapeHtml(title)}</h2>
							<p>${escapeHtml(description)}</p>
						</div>
						<div class="mlgp-modal__header-actions">
							<button type="button" class="mlgp-button mlgp-button--accent" data-mlgp-batch-add-picker="${pickerType}" data-mlgp-flow="${isEditorMode ? "shortcode" : "linked"}" ${selectedCount ? "" : "disabled"}>${escapeHtml(primaryActionLabel)}</button>
							<button type="button" class="mlgp-modal__close" data-mlgp-close-album-picker="1" aria-label="Fechar">&times;</button>
						</div>
					</div>
					<div class="mlgp-modal__body">
						<div class="mlgp-picker-tabs">
							<button type="button" class="mlgp-shell__tab ${isGallery ? "is-active" : ""}" data-mlgp-open-album-picker="gallery">Galerias</button>
							<button type="button" class="mlgp-shell__tab ${!isGallery ? "is-active" : ""}" data-mlgp-open-album-picker="album">Subalbuns</button>
						</div>
						<div class="mlgp-linked-list mlgp-linked-list--modal">
							${items.length ? items.map((item) => renderAlbumPickerItem(item, pickerType)).join("") : `<div class="mlgp-empty">Nenhum ${isGallery ? "item" : "subalbum"} disponivel para adicionar.</div>`}
						</div>
					</div>
				</div>
			</div>
		`;
	}

	function renderAlbumRow(item) {
		const isActive = Number(item.id || 0) === Number(state.albumEditor.activeId || 0);
		const cover = item.cover && item.cover.thumb_url
			? `<div class="mlgp-album-row__thumb"><img src="${escapeHtml(item.cover.thumb_url)}" alt="${escapeHtml(item.title || "")}"></div>`
			: `<div class="mlgp-album-row__thumb is-empty">ALB</div>`;
		const itemCount = Number(item.item_count || 0);

		return `
			<div class="mlgp-list__item mlgp-album-row ${isActive ? "is-active" : ""}">
				${cover}
				<div class="mlgp-album-row__main">
					<div class="mlgp-album-row__title">
						<h3>${escapeHtml(item.title || "Album sem titulo")}</h3>
					</div>
					<div class="mlgp-album-row__status">
						<span class="mlgp-pill">${escapeHtml(item.status || "draft")}</span>
					</div>
				</div>
				<div class="mlgp-album-row__meta">
					<div class="mlgp-album-row__slug">slug: ${escapeHtml(item.slug || "")}</div>
					<div class="mlgp-album-row__count">${itemCount} ${itemCount === 1 ? "item" : "itens"}</div>
					<div class="mlgp-album-row__shortcode">${item.shortcode ? renderCopyableShortcode(item.shortcode) : "<span>-</span>"}</div>
				</div>
				<div class="mlgp-album-row__actions">
					<label class="mlgp-check mlgp-check--row mlgp-album-row__select">
						<input type="checkbox" data-mlgp-select-album="${Number(item.id || 0)}" ${isAlbumSelected(item.id) ? "checked" : ""}>
						<span>Selecionar</span>
					</label>
					<button type="button" class="mlgp-button mlgp-button--ghost" data-manage-album="${Number(item.id || 0)}">Gerenciar</button>
					<button type="button" class="mlgp-button mlgp-button--danger" data-delete-album="${Number(item.id || 0)}">Excluir</button>
				</div>
			</div>
		`;
	}

	function renderAlbumLinkedItem(item, index) {
		const cover = item.cover && item.cover.thumb_url
			? `<div class="mlgp-linked-item__media"><img src="${escapeHtml(item.cover.thumb_url)}" alt="${escapeHtml(item.title || "")}"></div>`
			: `<div class="mlgp-linked-item__media is-empty">${item.item_type === "album" ? "ALB" : "GAL"}</div>`;

		return `
			<div class="mlgp-linked-item">
				${cover}
				<div class="mlgp-linked-item__main">
					<div class="mlgp-linked-item__header">
						<strong>${escapeHtml(item.title || "Item sem titulo")}</strong>
						<span class="mlgp-pill">${item.item_type === "album" ? "Subalbum" : "Galeria"}</span>
					</div>
					<div class="mlgp-list__meta">
						<span>ID ${Number(item.item_id || 0)}</span>
						<span>slug: ${escapeHtml(item.slug || "")}</span>
						<span>status: ${escapeHtml(item.status || "draft")}</span>
					</div>
					${renderCopyableShortcode(item.shortcode || "")}
				</div>
				<div class="mlgp-actions">
					<button type="button" class="mlgp-icon-button" data-mlgp-move-album-item="${index}" data-direction="up">&uarr;</button>
					<button type="button" class="mlgp-icon-button" data-mlgp-move-album-item="${index}" data-direction="down">&darr;</button>
					<button type="button" class="mlgp-icon-button is-danger" data-mlgp-remove-album-item="${index}">&times;</button>
				</div>
			</div>
		`;
	}


	function renderAlbumManager() {
		if (!state.albumEditor.activeId || !state.albumEditor.album) {
			return `
				<section class="mlgp-panel mlgp-panel--wide">
					<div class="mlgp-empty">
						<p>Crie ou selecione um album para montar a colecao com galerias e subalbuns.</p>
					</div>
				</section>
			`;
		}

		const album = state.albumEditor.album;
		const items = state.albumEditor.items || [];
		const cover = album.cover || null;
		const form = state.albumEditor.form || {};

		return `
			<section class="mlgp-panel mlgp-panel--wide mlgp-panel--album-manager">
				<div class="mlgp-editor">
					<div class="mlgp-editor__toolbar">
						<div>
							<h2>${escapeHtml(album.title || "Album sem titulo")}</h2>
							<div class="mlgp-list__meta">
								<span class="mlgp-pill">ID ${Number(album.id || 0)}</span>
								<span>slug: ${escapeHtml(album.slug || "")}</span>
								<span>${items.length} itens vinculados</span>
								${album.shortcode ? renderCopyableShortcode(album.shortcode) : ""}
							</div>
						</div>
						<div class="mlgp-actions mlgp-actions--album-toolbar">
							<button type="button" class="mlgp-button mlgp-button--ghost" data-reset-album="1">Voltar para albuns</button>
							<button type="button" class="mlgp-button mlgp-button--ghost" data-mlgp-open-album-picker="album">Adicionar subalbuns</button>
							<button type="button" class="mlgp-button mlgp-button--accent" data-mlgp-open-album-picker="gallery">Adicionar galerias</button>
							<span class="mlgp-status-pill ${hasAlbumPendingChanges() ? "is-warning" : "is-clean"}" data-mlgp-album-status>${hasAlbumPendingChanges() ? "Alteracoes pendentes" : "Sincronizado"}</span>
							<button type="button" class="mlgp-button" data-mlgp-save-album-all="1" ${hasAlbumPendingChanges() ? "" : "disabled"}>Salvar alteracoes</button>
						</div>
					</div>
					<div class="mlgp-editor-layout">
						<aside class="mlgp-editor-sidebar">
							<section class="mlgp-editor-panel">
								<h3>Capa atual</h3>
								<div class="mlgp-cover-card mlgp-cover-card--stacked">
									${cover && cover.medium_url ? `<img src="${escapeHtml(cover.medium_url)}" alt="${escapeHtml(cover.alt || cover.title || "")}">` : '<div class="mlgp-cover-card__empty">A capa do album vem do primeiro item valido da colecao.</div>'}
									<div class="mlgp-cover-card__meta">
										<strong>${cover ? escapeHtml(cover.title || "Imagem de capa") : "Sem capa dedicada"}</strong>
										<small>${cover ? escapeHtml(cover.filename || "") : "Organize os itens do album para controlar a capa automaticamente."}</small>
									</div>
								</div>
							</section>
							<section class="mlgp-editor-panel">
								<h3>Dados base do album</h3>
								<div class="mlgp-field">
									<label for="mlgp-editor-album-title">Titulo</label>
									<input id="mlgp-editor-album-title" type="text" value="${escapeHtml(form.title)}" data-mlgp-album-field="title">
								</div>
								<div class="mlgp-field">
									<label for="mlgp-editor-album-slug">Slug</label>
									<input id="mlgp-editor-album-slug" type="text" value="${escapeHtml(form.slug)}" data-mlgp-album-field="slug">
								</div>
								<div class="mlgp-field">
									<label for="mlgp-editor-album-status">Status</label>
									<select id="mlgp-editor-album-status" data-mlgp-album-field="status">
										<option value="draft" ${form.status === "draft" ? "selected" : ""}>Rascunho</option>
										<option value="publish" ${form.status === "publish" ? "selected" : ""}>Publicado</option>
										<option value="private" ${form.status === "private" ? "selected" : ""}>Privado</option>
									</select>
								</div>
								<div class="mlgp-field">
									<label for="mlgp-editor-album-description">Descricao</label>
									<textarea id="mlgp-editor-album-description" data-mlgp-album-field="description">${escapeHtml(form.description)}</textarea>
								</div>
							</section>
							<section class="mlgp-editor-panel">
								<h3>Controles do shortcode</h3>
								<div class="mlgp-field">
									<label for="mlgp-editor-album-display-type">Layout do shortcode</label>
									<select id="mlgp-editor-album-display-type" data-mlgp-album-field="displayType">
										<option value="grid" ${form.displayType === "grid" ? "selected" : ""}>Grid</option>
										<option value="grid_plus" ${form.displayType === "grid_plus" ? "selected" : ""}>Grid Plus</option>
										<option value="masonry" ${form.displayType === "masonry" ? "selected" : ""}>Masonry</option>
										<option value="mosaic" ${form.displayType === "mosaic" ? "selected" : ""}>Mosaico</option>
										<option value="tile" ${form.displayType === "tile" ? "selected" : ""}>Tile</option>
										<option value="justified" ${form.displayType === "justified" ? "selected" : ""}>Justified</option>
									</select>
								</div>
								<div class="mlgp-settings-grid">
									<div class="mlgp-field">
										<label for="mlgp-editor-album-columns-desktop">Colunas desktop</label>
										<input id="mlgp-editor-album-columns-desktop" type="number" min="1" max="8" value="${Number(form.columnsDesktop || 4)}" data-mlgp-album-field="columnsDesktop">
									</div>
									<div class="mlgp-field">
										<label for="mlgp-editor-album-columns-tablet">Colunas tablet</label>
										<input id="mlgp-editor-album-columns-tablet" type="number" min="1" max="6" value="${Number(form.columnsTablet || 3)}" data-mlgp-album-field="columnsTablet">
									</div>
									<div class="mlgp-field">
										<label for="mlgp-editor-album-columns-mobile">Colunas mobile</label>
										<input id="mlgp-editor-album-columns-mobile" type="number" min="1" max="4" value="${Number(form.columnsMobile || 2)}" data-mlgp-album-field="columnsMobile">
									</div>
									<div class="mlgp-field">
										<label for="mlgp-editor-album-gap">Espacamento</label>
										<input id="mlgp-editor-album-gap" type="number" min="0" max="48" value="${Number(form.cardGap || 18)}" data-mlgp-album-field="cardGap">
									</div>
									<div class="mlgp-field">
										<label for="mlgp-editor-album-row-height">Altura base do justified</label>
										<input id="mlgp-editor-album-row-height" type="number" min="120" max="520" value="${Number(form.justifiedRowHeight || 220)}" data-mlgp-album-field="justifiedRowHeight">
									</div>
									<div class="mlgp-field">
										<label for="mlgp-editor-album-items-page">Itens por pagina</label>
										<input id="mlgp-editor-album-items-page" type="number" min="1" max="5000" value="${Number(form.itemsPerPage || 24)}" data-mlgp-album-field="itemsPerPage">
									</div>
									<div class="mlgp-field">
										<label for="mlgp-editor-album-cover-width">Largura da capa de exposicao</label>
										<input id="mlgp-editor-album-cover-width" type="number" min="120" max="1800" value="${Number(form.coverWidth || 360)}" data-mlgp-album-field="coverWidth">
									</div>
									<div class="mlgp-field">
										<label for="mlgp-editor-album-cover-height">Altura da capa de exposicao</label>
										<input id="mlgp-editor-album-cover-height" type="number" min="120" max="1200" value="${Number(form.coverHeight || 280)}" data-mlgp-album-field="coverHeight">
									</div>
									<div class="mlgp-field">
										<label for="mlgp-editor-album-cover-fit">Encaixe da capa do album</label>
										<select id="mlgp-editor-album-cover-fit" data-mlgp-album-field="coverFit">
											<option value="contain" ${String(form.coverFit || "contain") === "contain" ? "selected" : ""}>Mostrar imagem inteira</option>
											<option value="cover" ${String(form.coverFit || "contain") === "cover" ? "selected" : ""}>Preencher e recortar</option>
										</select>
									</div>
									<label class="mlgp-check mlgp-check--inline">
										<input id="mlgp-editor-album-cover-lock" type="checkbox" ${Number(form.coverLockRatio ?? 1) ? "checked" : ""} data-mlgp-album-field="coverLockRatio">
										<span>Travar proporcao da capa</span>
									</label>
									<div class="mlgp-field">
										<label for="mlgp-editor-album-heading-font-size">Tamanho do titulo principal</label>
										<input id="mlgp-editor-album-heading-font-size" type="number" min="20" max="96" value="${Number(form.headingFontSize || DEFAULT_HEADING_FONT_SIZE)}" data-mlgp-album-field="headingFontSize">
									</div>
									<div class="mlgp-field">
										<label for="mlgp-editor-album-heading-color">Cor do titulo principal</label>
										<input id="mlgp-editor-album-heading-color" type="color" value="${escapeHtml(form.headingColor || "#172033")}" data-mlgp-album-field="headingColor">
									</div>
									<div class="mlgp-field">
										<label for="mlgp-editor-album-item-title-size">Tamanho do titulo dos cards</label>
										<input id="mlgp-editor-album-item-title-size" type="number" min="10" max="48" value="${Number(form.itemTitleFontSize || 18)}" data-mlgp-album-field="itemTitleFontSize">
									</div>
									<div class="mlgp-field">
										<label for="mlgp-editor-album-item-title-color">Cor do titulo dos cards</label>
										<input id="mlgp-editor-album-item-title-color" type="color" value="${escapeHtml(form.itemTitleColor || "#172033")}" data-mlgp-album-field="itemTitleColor">
									</div>
								</div>
								<div class="mlgp-check">
									<input id="mlgp-editor-album-show-heading" type="checkbox" ${Number(form.showHeading ?? 0) ? "checked" : ""} data-mlgp-album-field="showHeading">
									<label for="mlgp-editor-album-show-heading">Exibir titulo principal do album</label>
								</div>
								<div class="mlgp-check">
									<input id="mlgp-editor-album-show-description" type="checkbox" ${Number(form.showDescription ?? 0) ? "checked" : ""} data-mlgp-album-field="showDescription">
									<label for="mlgp-editor-album-show-description">Exibir descricao do album</label>
								</div>
								<div class="mlgp-check">
									<input id="mlgp-editor-album-show-titles" type="checkbox" ${Number(form.showTitles ?? 1) ? "checked" : ""} data-mlgp-album-field="showTitles">
									<label for="mlgp-editor-album-show-titles">Exibir titulo nos cards</label>
								</div>
								<div class="mlgp-check">
									<input id="mlgp-editor-album-show-captions" type="checkbox" ${Number(form.showCaptions ?? 0) ? "checked" : ""} data-mlgp-album-field="showCaptions">
									<label for="mlgp-editor-album-show-captions">Exibir descricao nos cards</label>
								</div>
								<div class="mlgp-check">
									<input id="mlgp-editor-album-pagination" type="checkbox" ${Number(form.paginationEnabled ?? 1) ? "checked" : ""} data-mlgp-album-field="paginationEnabled">
									<label for="mlgp-editor-album-pagination">Paginar este album</label>
								</div>
								<div class="mlgp-check">
									<input id="mlgp-editor-album-rounded" type="checkbox" ${Number(form.roundedCorners ?? 0) ? "checked" : ""} data-mlgp-album-field="roundedCorners">
									<label for="mlgp-editor-album-rounded">Usar cantos arredondados</label>
								</div>
							</section>
							<section class="mlgp-editor-panel">
								<h3>Shortcode do album</h3>
								<div class="mlgp-shortcode-stack">
									${renderShortcodeBox("Shortcode principal", album.shortcode || "")}
								</div>
							</section>
						</aside>
						<div class="mlgp-editor-main">
							<section class="mlgp-editor-panel mlgp-editor-panel--wide mlgp-editor-panel--structure">
								<div class="mlgp-panel-header-inline">
									<div>
										<h3>Estrutura do album</h3>
										<p class="mlgp-panel__intro">Organize abaixo a ordem final das galerias e subalbuns. O primeiro item valido da lista continua definindo a capa automaticamente.</p>
									</div>
									<div class="mlgp-list__meta">
										<span>${items.length} itens</span>
									</div>
								</div>
								<div class="mlgp-linked-list mlgp-linked-list--structure">
									${items.length ? items.map((item, index) => renderAlbumLinkedItem(item, index)).join("") : '<div class="mlgp-empty">Nenhum item vinculado ainda. Use o botao Adicionar galerias para montar o album.</div>'}
								</div>
							</section>
						</div>
					</div>
				</div>
			</section>
		`;
	}

	function renderAlbums() {
		const sortedAlbums = state.albums || [];
		const albumSearchQuery = String(state.albumSearchQuery || "").trim().toLowerCase();
		const filteredAlbums = sortedAlbums.filter((item) => {
			if (!albumSearchQuery) {
				return true;
			}

			const haystack = [
				item.title || "",
				item.slug || "",
				item.shortcode || "",
			].join(" ").toLowerCase();

			return haystack.includes(albumSearchQuery);
		});

		const totalLinkedItems = state.albums.reduce((carry, item) => carry + Number(item.item_count || 0), 0);
		const selectedCount = getSelectedAlbumIds().length;
		const allSelected = state.albums.length > 0 && selectedCount === state.albums.length;
		const activeAlbum = state.albumEditor.album || null;

		// Ensure root is valid for Post Editor context
		if (config.isPostEditor && (!root || !document.getElementById(root.id))) {
			root = document.getElementById("mlgp-editor-picker-root") || document.createElement("div");
			root.id = "mlgp-editor-picker-root";
			if (!document.getElementById(root.id)) {
				document.body.appendChild(root);
			}
		}

		if (config.isPostEditor && root && root.id === "mlgp-editor-picker-root") {
			root.innerHTML = renderAlbumPickerModal();
			return;
		}

		const createPanel = activeAlbum
			? ""
			: `
				<section class="mlgp-panel mlgp-panel--album-create">
					<h2>Novo album</h2>
					<p class="mlgp-panel__intro">Cadastre os dados principais do album. Depois use o manager para ajustar layout, titulos, shortcode e estrutura completa da colecao.</p>
					<form id="mlgp-album-form" class="mlgp-form" method="post" action="${escapeHtml(config.formEndpoints?.albumSave || "")}">
						<input type="hidden" name="action" value="mlgp_save_album_form">
						<input type="hidden" name="mlgp_album_form_nonce" value="${escapeHtml(config.albumFormNonce || "")}">
						<input type="hidden" name="id" value="${Number(state.albumEditor.activeId || 0)}">
						<div class="mlgp-field">
							<label for="mlgp-album-title">Titulo</label>
							<input id="mlgp-album-title" type="text" name="title" value="${escapeHtml(state.albumEditor.form.title)}" required>
						</div>
						<div class="mlgp-field">
							<label for="mlgp-album-slug">Slug</label>
							<input id="mlgp-album-slug" type="text" name="slug" value="${escapeHtml(state.albumEditor.form.slug)}" placeholder="gerado-automaticamente">
						</div>
						<div class="mlgp-field">
							<label for="mlgp-album-status">Status</label>
							<select id="mlgp-album-status" name="status">
								<option value="draft" ${state.albumEditor.form.status === "draft" ? "selected" : ""}>Rascunho</option>
								<option value="publish" ${state.albumEditor.form.status === "publish" ? "selected" : ""}>Publicado</option>
								<option value="private" ${state.albumEditor.form.status === "private" ? "selected" : ""}>Privado</option>
							</select>
						</div>
						<div class="mlgp-field">
							<label for="mlgp-album-description">Descricao</label>
							<textarea id="mlgp-album-description" name="description">${escapeHtml(state.albumEditor.form.description)}</textarea>
						</div>
						<div class="mlgp-actions">
							<button type="submit" class="mlgp-button mlgp-button--accent">Criar album</button>
							<button type="button" class="mlgp-button mlgp-button--ghost" data-reset-album="1">Limpar</button>
						</div>
					</form>
				</section>
			`;
		const albumsPanel = `
			<section class="mlgp-panel mlgp-panel--wide mlgp-panel--album-index">
				<div class="mlgp-panel-header-inline">
					<div>
						<h2>Albuns cadastrados</h2>
						<p class="mlgp-panel__intro">Abra um album para controlar exibicao, estrutura e shortcode da colecao.</p>
					</div>
					<div class="mlgp-actions mlgp-actions--bulk">
						${renderSortControl("album", state.sorting.albums)}
						<div class="mlgp-gallery-search">
							<input type="search" value="${escapeHtml(state.albumSearchQuery || "")}" placeholder="Buscar albuns..." data-mlgp-album-search="1">
						</div>
						<label class="mlgp-check mlgp-check--compact">
							<input type="checkbox" data-mlgp-select-all-albums="1" ${allSelected ? "checked" : ""}>
							<span>Selecionar todos</span>
						</label>
						<button type="button" class="mlgp-button mlgp-button--danger" data-mlgp-delete-selected-albums="1" ${selectedCount ? "" : "disabled"}>${escapeHtml(config.strings.bulkDeleteSelectedAlbums || "Excluir albuns selecionados")} (${selectedCount})</button>
						<div class="mlgp-list__meta">
							<span>${filteredAlbums.length} de ${sortedAlbums.length} albuns</span>
							<span>${totalLinkedItems} itens vinculados</span>
						</div>
					</div>
				</div>
				<div class="mlgp-linked-list mlgp-linked-list--albums">
					${filteredAlbums.length ? filteredAlbums.map((item) => renderAlbumRow(item)).join("") : '<div class="mlgp-empty">Nenhum album encontrado para esta busca.</div>'}
				</div>
			</section>
		`;

		if (config.isPostEditor && root && root.id === "mlgp-editor-picker-root") {
			root.innerHTML = renderAlbumPickerModal();
			return;
		}

		if (activeAlbum) {
			root.innerHTML = `${renderAlbumManager()}${renderAlbumPickerModal()}`;
		} else {
			root.innerHTML = `<div class="mlgp-grid-two">${createPanel}${albumsPanel}</div>`;
		}

		updateAlbumDirtyUi();
	}

	function renderSettingsSection(title, intro, content) {
		return `
			<section class="mlgp-settings-section">
				<div class="mlgp-settings-section__header">
					<h3>${escapeHtml(title)}</h3>
					${intro ? `<p class="mlgp-panel__intro">${escapeHtml(intro)}</p>` : ""}
				</div>
				${content}
			</section>
		`;
	}

	function renderSettingsGeneralTab(settings, storageLabel) {
		return renderSettingsSection(
			"Base do produto",
			"Defina preset padrão, estrutura de colunas e comportamento-base do frontend.",
			`
				<div class="mlgp-settings-grid">
					<div class="mlgp-field">
						<label for="mlgp-columns-desktop">Colunas no desktop</label>
						<input id="mlgp-columns-desktop" type="number" min="1" max="8" name="columns_desktop" value="${Number(settings.columns_desktop || 4)}">
					</div>
					<div class="mlgp-field">
						<label for="mlgp-columns-tablet">Colunas no tablet</label>
						<input id="mlgp-columns-tablet" type="number" min="1" max="6" name="columns_tablet" value="${Number(settings.columns_tablet || 3)}">
					</div>
					<div class="mlgp-field">
						<label for="mlgp-columns-mobile">Colunas no mobile</label>
						<input id="mlgp-columns-mobile" type="number" min="1" max="4" name="columns_mobile" value="${Number(settings.columns_mobile || 2)}">
					</div>
					<div class="mlgp-field">
						<label for="mlgp-items-per-page">Itens por página</label>
						<input id="mlgp-items-per-page" type="number" min="1" max="5000" name="items_per_page" value="${Number(settings.items_per_page || 24)}">
					</div>
					<div class="mlgp-field">
						<label for="mlgp-image-quality">Qualidade das imagens</label>
						<input id="mlgp-image-quality" type="number" min="30" max="100" name="image_quality" value="${Number(settings.image_quality || 82)}">
					</div>
					<div class="mlgp-field">
						<label for="mlgp-default-gallery-preset">Preset padrão para novas galerias</label>
						<select id="mlgp-default-gallery-preset" name="default_gallery_preset">
							<option value="masonry-default" ${String(settings.default_gallery_preset || "masonry-default") === "masonry-default" ? "selected" : ""}>Masonry sem margem</option>
							<option value="clean-grid" ${String(settings.default_gallery_preset || "masonry-default") === "clean-grid" ? "selected" : ""}>Clean Grid</option>
							<option value="editorial-tile" ${String(settings.default_gallery_preset || "masonry-default") === "editorial-tile" ? "selected" : ""}>Editorial Tile</option>
							<option value="impact-mosaic" ${String(settings.default_gallery_preset || "masonry-default") === "impact-mosaic" ? "selected" : ""}>Impact Mosaic</option>
							<option value="story-justified" ${String(settings.default_gallery_preset || "masonry-default") === "story-justified" ? "selected" : ""}>Story Justified</option>
							<option value="showcase-filmstrip" ${String(settings.default_gallery_preset || "masonry-default") === "showcase-filmstrip" ? "selected" : ""}>Showcase Filmstrip</option>
						</select>
					</div>
					<div class="mlgp-field">
						<label for="mlgp-default-album-display-type">Layout padrão dos prints de álbum</label>
						<select id="mlgp-default-album-display-type" name="default_album_display_type">
							<option value="grid" ${String(settings.default_album_display_type || "grid") === "grid" ? "selected" : ""}>Grid</option>
							<option value="grid_plus" ${String(settings.default_album_display_type || "grid") === "grid_plus" ? "selected" : ""}>Grid Plus</option>
							<option value="masonry" ${String(settings.default_album_display_type || "grid") === "masonry" ? "selected" : ""}>Masonry</option>
							<option value="mosaic" ${String(settings.default_album_display_type || "grid") === "mosaic" ? "selected" : ""}>Mosaico</option>
							<option value="tile" ${String(settings.default_album_display_type || "grid") === "tile" ? "selected" : ""}>Tile</option>
							<option value="justified" ${String(settings.default_album_display_type || "grid") === "justified" ? "selected" : ""}>Justified</option>
						</select>
					</div>

					<div class="mlgp-field">
						<label for="mlgp-card-gap">Gap entre fotos (px)</label>
						<input id="mlgp-card-gap" type="number" min="0" max="48" name="card_gap" value="${Number(settings.card_gap ?? 0)}">
					</div>
					<div class="mlgp-field">
						<label for="mlgp-card-padding">Padding interno do item (px)</label>
						<input id="mlgp-card-padding" type="number" min="0" max="80" name="card_padding" value="${Number(settings.card_padding ?? 0)}">
					</div>
					<div class="mlgp-field">
						<label for="mlgp-card-margin">Margin externa do item (px)</label>
						<input id="mlgp-card-margin" type="number" min="0" max="40" name="card_margin" value="${Number(settings.card_margin ?? 0)}">
					</div>
					<div class="mlgp-field">
						<label for="mlgp-card-border-width">Espessura da borda (px)</label>
						<input id="mlgp-card-border-width" type="number" min="0" max="20" name="card_border_width" value="${Number(settings.card_border_width ?? 0)}">
					</div>
					<div class="mlgp-field">
						<label for="mlgp-card-border-color">Cor da borda</label>
						<input id="mlgp-card-border-color" type="color" name="card_border_color" value="${escapeHtml(settings.card_border_color || "#d7e0ea")}">
					</div>
					<div class="mlgp-field">
						<label for="mlgp-card-border-opacity">Transparencia da borda (%)</label>
						<input id="mlgp-card-border-opacity" type="number" min="0" max="100" name="card_border_opacity" value="${Number(settings.card_border_opacity ?? 100)}">
					</div>
					<div class="mlgp-field">
						<label for="mlgp-gap-background-color">Cor do espaco entre fotos</label>
						<input id="mlgp-gap-background-color" type="color" name="gap_background_color" value="${escapeHtml(settings.gap_background_color || "#ffffff")}">
					</div>
					<div class="mlgp-field">
						<label for="mlgp-gap-background-opacity">Transparencia do espaco (%)</label>
						<input id="mlgp-gap-background-opacity" type="number" min="0" max="100" name="gap_background_opacity" value="${Number(settings.gap_background_opacity ?? 100)}">
					</div>
				</div>
				<div class="mlgp-settings-callout">
					Esses controles abaixo mandam no bloco externo do shortcode. O default nasce zerado, sem moldura.
				</div>
				<div class="mlgp-settings-grid">
					<div class="mlgp-field">
						<label for="mlgp-wrapper-padding">Padding externo do bloco (px)</label>
						<input id="mlgp-wrapper-padding" type="number" min="0" max="120" name="wrapper_padding" value="${Number(settings.wrapper_padding ?? 0)}">
					</div>
					<div class="mlgp-field">
						<label for="mlgp-wrapper-radius">Radius externo (px)</label>
						<input id="mlgp-wrapper-radius" type="number" min="0" max="80" name="wrapper_radius" value="${Number(settings.wrapper_radius ?? 0)}">
					</div>
					<div class="mlgp-field">
						<label for="mlgp-wrapper-border-width">Borda externa (px)</label>
						<input id="mlgp-wrapper-border-width" type="number" min="0" max="20" name="wrapper_border_width" value="${Number(settings.wrapper_border_width ?? 0)}">
					</div>
					<div class="mlgp-field">
						<label for="mlgp-wrapper-border-color">Cor da borda externa</label>
						<input id="mlgp-wrapper-border-color" type="color" name="wrapper_border_color" value="${escapeHtml(settings.wrapper_border_color || "#ffffff")}">
					</div>
					<div class="mlgp-field">
						<label for="mlgp-wrapper-border-opacity">Transparencia da borda externa (%)</label>
						<input id="mlgp-wrapper-border-opacity" type="number" min="0" max="100" name="wrapper_border_opacity" value="${Number(settings.wrapper_border_opacity ?? 0)}">
					</div>
					<div class="mlgp-field">
						<label for="mlgp-wrapper-background-color">Fundo do bloco</label>
						<input id="mlgp-wrapper-background-color" type="color" name="wrapper_background_color" value="${escapeHtml(settings.wrapper_background_color || "#ffffff")}">
					</div>
					<div class="mlgp-field">
						<label for="mlgp-wrapper-background-opacity">Transparencia do fundo do bloco (%)</label>
						<input id="mlgp-wrapper-background-opacity" type="number" min="0" max="100" name="wrapper_background_opacity" value="${Number(settings.wrapper_background_opacity ?? 0)}">
					</div>
					<div class="mlgp-field">
						<label for="mlgp-wrapper-shadow-opacity">Sombra externa (%)</label>
						<input id="mlgp-wrapper-shadow-opacity" type="number" min="0" max="100" name="wrapper_shadow_opacity" value="${Number(settings.wrapper_shadow_opacity ?? 0)}">
					</div>
					<div class="mlgp-field">
						<label for="mlgp-wrapper-max-width">Largura maxima do container (px, 0 = livre)</label>
						<input id="mlgp-wrapper-max-width" type="number" min="0" max="3840" name="wrapper_max_width" value="${Number(settings.wrapper_max_width ?? 0)}">
					</div>
				</div>
				<div class="mlgp-settings-callout">
					Esses controles viram o default das novas galerias e tambem entram no botao de aplicar configuracao em lote para o acervo existente.
				</div>
				<div class="mlgp-check">
					<input id="mlgp-enable-lightbox" type="checkbox" name="enable_lightbox" ${Number(settings.enable_lightbox || 0) ? "checked" : ""}>
					<label for="mlgp-enable-lightbox">Ativar lightbox nos shortcodes</label>
				</div>
				<div class="mlgp-check">
					<input id="mlgp-enable-lazy-load" type="checkbox" name="enable_lazy_load" ${Number(settings.enable_lazy_load || 0) ? "checked" : ""}>
					<label for="mlgp-enable-lazy-load">Ativar lazy load por padrão</label>
				</div>
				<div class="mlgp-check">
					<input id="mlgp-rounded-corners" type="checkbox" name="rounded_corners" ${Number(settings.rounded_corners ?? 0) ? "checked" : ""}>
					<label for="mlgp-rounded-corners">Usar cantos arredondados por padrão</label>
				</div>
				<div class="mlgp-check">
					<input id="mlgp-pagination-enabled" type="checkbox" name="pagination_enabled" ${Number(settings.pagination_enabled ?? 1) ? "checked" : ""}>
					<label for="mlgp-pagination-enabled">Paginar galerias por padrão no frontend</label>
				</div>
				<div class="mlgp-settings-callout">
					Caminho operacional do plugin: <code class="mlgp-inline-code">${escapeHtml(storageLabel)}</code>
				</div>
			`
		);
	}

	function renderSettingsContentTab(settings) {
		const hideAllTitles = Number(settings.hide_all_titles || 0) === 1;

		return renderSettingsSection(
			"Conteúdo e visibilidade",
			"Defina o que nasce visível no frontend e quais recursos ficam opcionais nas novas galerias.",
			`
				<div class="mlgp-check">
					<input id="mlgp-hide-all-titles" type="checkbox" name="hide_all_titles" ${hideAllTitles ? "checked" : ""}>
					<label for="mlgp-hide-all-titles">Ocultar todos os títulos no frontend</label>
				</div>
				<div class="mlgp-settings-callout">
					Essa chave global suprime títulos principais, títulos das imagens, títulos dos cards de álbum e nomes de galeria nas visualizações por tag.
				</div>
				<div class="mlgp-check">
					<input id="mlgp-enable-frontend-filters" type="checkbox" name="enable_frontend_filters" ${Number(settings.enable_frontend_filters ?? 0) ? "checked" : ""}>
					<label for="mlgp-enable-frontend-filters">Ativar busca e filtro por tag por padrão nas novas galerias</label>
				</div>
				<div class="mlgp-check">
					<input id="mlgp-show-gallery-heading" type="checkbox" name="show_gallery_heading" ${Number(settings.show_gallery_heading ?? 0) ? "checked" : ""}>
					<label for="mlgp-show-gallery-heading">Exibir título principal da galeria por padrão</label>
				</div>
				<div class="mlgp-check">
					<input id="mlgp-show-gallery-description" type="checkbox" name="show_gallery_description" ${Number(settings.show_gallery_description ?? 0) ? "checked" : ""}>
					<label for="mlgp-show-gallery-description">Exibir descrição da galeria por padrão</label>
				</div>
				<div class="mlgp-check">
					<input id="mlgp-show-titles" type="checkbox" name="show_titles" ${Number(settings.show_titles ?? 0) ? "checked" : ""}>
					<label for="mlgp-show-titles">Exibir títulos das imagens por padrão nas novas galerias</label>
				</div>
				<div class="mlgp-check">
					<input id="mlgp-show-captions" type="checkbox" name="show_captions" ${Number(settings.show_captions ?? 0) ? "checked" : ""}>
					<label for="mlgp-show-captions">Exibir legendas das imagens por padrão nas novas galerias</label>
				</div>
				<div class="mlgp-check">
					<input id="mlgp-show-item-tags" type="checkbox" name="show_item_tags" ${Number(settings.show_item_tags ?? 0) ? "checked" : ""}>
					<label for="mlgp-show-item-tags">Exibir tags das imagens por padrão no frontend</label>
				</div>
			`
		);
	}

	function renderSettingsSlideshowTab(settings) {
		return renderSettingsSection(
			"Slideshow e navegação",
			"Controle o comportamento padrão do slideshow e personalize as setas com PNG próprio, se desejar.",
			`
				<div class="mlgp-check">
					<input id="mlgp-slideshow-show-arrows" type="checkbox" name="slideshow_show_arrows" ${Number(settings.slideshow_show_arrows ?? 1) ? "checked" : ""}>
					<label for="mlgp-slideshow-show-arrows">Exibir setas por padrão no slideshow</label>
				</div>
				<div class="mlgp-check">
					<input id="mlgp-slideshow-show-thumbs" type="checkbox" name="slideshow_show_thumbs" ${Number(settings.slideshow_show_thumbs ?? 1) ? "checked" : ""}>
					<label for="mlgp-slideshow-show-thumbs">Exibir trilha de miniaturas por padrão no slideshow</label>
				</div>
				<div class="mlgp-settings-grid">
					${renderMediaPicker("nav_arrow_prev_url", "Seta anterior (PNG)", settings.nav_arrow_prev_url || "", "Se vazio, o plugin usa o ícone padrão para voltar.")}
					${renderMediaPicker("nav_arrow_next_url", "Seta próxima (PNG)", settings.nav_arrow_next_url || "", "Se vazio, o plugin usa o ícone padrão para avançar.")}
				</div>
			`
		);
	}

	function renderSettingsTypographyTab(settings) {
		return renderSettingsSection(
			"Tipografia dos títulos",
			"Controle o título principal e os títulos das imagens sem depender de CSS manual em cada projeto.",
			`
				<div class="mlgp-settings-grid">
					<div class="mlgp-field">
						<label for="mlgp-heading-font-size">Tamanho do título principal (px)</label>
						<input id="mlgp-heading-font-size" type="number" min="20" max="96" name="heading_font_size" value="${Number(settings.heading_font_size || DEFAULT_HEADING_FONT_SIZE)}">
					</div>
					<div class="mlgp-field">
						<label for="mlgp-heading-color">Cor do título principal</label>
						<input id="mlgp-heading-color" type="color" name="heading_color" value="${escapeHtml(settings.heading_color || "#172033")}">
					</div>
					<div class="mlgp-field">
						<label for="mlgp-item-title-font-size">Tamanho do título das imagens (px)</label>
						<input id="mlgp-item-title-font-size" type="number" min="10" max="48" name="item_title_font_size" value="${Number(settings.item_title_font_size || 18)}">
					</div>
					<div class="mlgp-field">
						<label for="mlgp-item-title-color">Cor do título das imagens</label>
						<input id="mlgp-item-title-color" type="color" name="item_title_color" value="${escapeHtml(settings.item_title_color || "#172033")}">
					</div>
				</div>
			`
		);
	}

	function renderSettingsAlbumsTab(settings) {
		return renderSettingsSection(
			"Álbuns",
			"Controle completo do grid de álbuns no frontend. O default nasce daqui.",
			`
				<div class="mlgp-settings-grid">
					<div class="mlgp-field">
						<label for="mlgp-default-album-display-type-tab">Layout padrão do grid do álbum</label>
						<select id="mlgp-default-album-display-type-tab" name="default_album_display_type">
							<option value="grid" ${String(settings.default_album_display_type || "grid") === "grid" ? "selected" : ""}>Grid</option>
							<option value="grid_plus" ${String(settings.default_album_display_type || "grid") === "grid_plus" ? "selected" : ""}>Grid Plus</option>
							<option value="masonry" ${String(settings.default_album_display_type || "grid") === "masonry" ? "selected" : ""}>Masonry</option>
							<option value="mosaic" ${String(settings.default_album_display_type || "grid") === "mosaic" ? "selected" : ""}>Mosaico</option>
							<option value="tile" ${String(settings.default_album_display_type || "grid") === "tile" ? "selected" : ""}>Tile</option>
							<option value="justified" ${String(settings.default_album_display_type || "grid") === "justified" ? "selected" : ""}>Justified</option>
						</select>
					</div>
					<div class="mlgp-field">
						<label for="mlgp-album-columns-desktop">Colunas desktop</label>
						<input id="mlgp-album-columns-desktop" type="number" min="1" max="8" name="album_columns_desktop" value="${Number(settings.album_columns_desktop || 4)}">
					</div>
					<div class="mlgp-field">
						<label for="mlgp-album-columns-tablet">Colunas tablet</label>
						<input id="mlgp-album-columns-tablet" type="number" min="1" max="6" name="album_columns_tablet" value="${Number(settings.album_columns_tablet || 3)}">
					</div>
					<div class="mlgp-field">
						<label for="mlgp-album-columns-mobile">Colunas mobile</label>
						<input id="mlgp-album-columns-mobile" type="number" min="1" max="4" name="album_columns_mobile" value="${Number(settings.album_columns_mobile || 2)}">
					</div>
					<div class="mlgp-field">
						<label for="mlgp-album-card-gap">Espaçamento entre cards (px)</label>
						<input id="mlgp-album-card-gap" type="number" min="0" max="48" name="album_card_gap" value="${Number(settings.album_card_gap ?? 18)}">
					</div>
					<div class="mlgp-field">
						<label for="mlgp-album-card-padding">Padding interno do card (px)</label>
						<input id="mlgp-album-card-padding" type="number" min="0" max="80" name="album_card_padding" value="${Number(settings.album_card_padding ?? 0)}">
					</div>
					<div class="mlgp-field">
						<label for="mlgp-album-card-margin">Margin externa do card (px)</label>
						<input id="mlgp-album-card-margin" type="number" min="0" max="40" name="album_card_margin" value="${Number(settings.album_card_margin ?? 0)}">
					</div>
					<div class="mlgp-field">
						<label for="mlgp-album-card-border-width">Borda do card (px)</label>
						<input id="mlgp-album-card-border-width" type="number" min="0" max="20" name="album_card_border_width" value="${Number(settings.album_card_border_width ?? 0)}">
					</div>
					<div class="mlgp-field">
						<label for="mlgp-album-card-border-color">Cor da borda</label>
						<input id="mlgp-album-card-border-color" type="color" name="album_card_border_color" value="${escapeHtml(settings.album_card_border_color || "#d7e0ea")}">
					</div>
					<div class="mlgp-field">
						<label for="mlgp-album-card-border-opacity">Transparência da borda (%)</label>
						<input id="mlgp-album-card-border-opacity" type="number" min="0" max="100" name="album_card_border_opacity" value="${Number(settings.album_card_border_opacity ?? 100)}">
					</div>
					<div class="mlgp-field">
						<label for="mlgp-album-gap-bg-color">Cor do espaço entre cards</label>
						<input id="mlgp-album-gap-bg-color" type="color" name="album_gap_background_color" value="${escapeHtml(settings.album_gap_background_color || "#ffffff")}">
					</div>
					<div class="mlgp-field">
						<label for="mlgp-album-gap-bg-opacity">Transparência do espaço (%)</label>
						<input id="mlgp-album-gap-bg-opacity" type="number" min="0" max="100" name="album_gap_background_opacity" value="${Number(settings.album_gap_background_opacity ?? 100)}">
					</div>
					<div class="mlgp-field">
						<label for="mlgp-album-card-radius">Radius do card (px)</label>
						<input id="mlgp-album-card-radius" type="number" min="0" max="80" name="album_card_radius" value="${Number(settings.album_card_radius ?? 0)}">
					</div>
					<div class="mlgp-field">
						<label for="mlgp-album-items-per-page">Itens por página</label>
						<input id="mlgp-album-items-per-page" type="number" min="1" max="5000" name="album_items_per_page" value="${Number(settings.album_items_per_page || 18)}">
					</div>
					<div class="mlgp-field">
						<label for="mlgp-album-item-title-font-size">Tamanho do título do card do álbum (px)</label>
						<input id="mlgp-album-item-title-font-size" type="number" min="10" max="48" name="album_item_title_font_size" value="${Number(settings.album_item_title_font_size || settings.item_title_font_size || 18)}">
					</div>
					<div class="mlgp-field">
						<label for="mlgp-album-item-title-color">Cor do título do card do álbum</label>
						<input id="mlgp-album-item-title-color" type="color" name="album_item_title_color" value="${escapeHtml(settings.album_item_title_color || settings.item_title_color || "#172033")}">
					</div>
					<div class="mlgp-field">
						<label for="mlgp-album-cover-width">Largura padrão da capa do álbum (px)</label>
						<input id="mlgp-album-cover-width" type="text" inputmode="numeric" pattern="[0-9]*" name="album_cover_width" value="${Number(settings.album_cover_width || 360)}">
					</div>
					<div class="mlgp-field">
						<label for="mlgp-album-cover-height">Altura padrão da capa do álbum (px)</label>
						<input id="mlgp-album-cover-height" type="text" inputmode="numeric" pattern="[0-9]*" name="album_cover_height" value="${Number(settings.album_cover_height || 280)}">
					</div>
					<div class="mlgp-field">
						<label for="mlgp-album-cover-fit">Encaixe padrão da capa do álbum</label>
						<select id="mlgp-album-cover-fit" name="album_cover_fit">
							<option value="contain" ${String(settings.album_cover_fit || "contain") === "contain" ? "selected" : ""}>Mostrar imagem inteira</option>
							<option value="cover" ${String(settings.album_cover_fit || "contain") === "cover" ? "selected" : ""}>Preencher e recortar</option>
						</select>
					</div>
				</div>
				<div class="mlgp-check">
					<input id="mlgp-album-cover-lock-ratio" type="checkbox" name="album_cover_lock_ratio" ${Number(settings.album_cover_lock_ratio ?? 1) ? "checked" : ""}>
					<label for="mlgp-album-cover-lock-ratio">Travar proporção ao alterar largura e altura</label>
				</div>
				<div class="mlgp-check">
					<input id="mlgp-album-pagination-enabled" type="checkbox" name="album_pagination_enabled" ${Number(settings.album_pagination_enabled ?? 1) ? "checked" : ""}>
					<label for="mlgp-album-pagination-enabled">Paginar álbuns por padrão</label>
				</div>
				<div class="mlgp-check">
					<input id="mlgp-album-show-titles" type="checkbox" name="album_show_titles" ${Number(settings.album_show_titles ?? 1) ? "checked" : ""}>
					<label for="mlgp-album-show-titles">Exibir títulos dos cards de álbum por padrão</label>
				</div>
				<div class="mlgp-check">
					<input id="mlgp-album-show-captions" type="checkbox" name="album_show_captions" ${Number(settings.album_show_captions ?? 0) ? "checked" : ""}>
					<label for="mlgp-album-show-captions">Exibir descrições dos cards de álbum por padrão</label>
				</div>
				<div class="mlgp-check">
					<input id="mlgp-album-show-heading" type="checkbox" name="album_show_heading" ${Number(settings.album_show_heading ?? 0) ? "checked" : ""}>
					<label for="mlgp-album-show-heading">Exibir título principal do álbum por padrão</label>
				</div>
				<div class="mlgp-check">
					<input id="mlgp-album-show-description" type="checkbox" name="album_show_description" ${Number(settings.album_show_description ?? 0) ? "checked" : ""}>
					<label for="mlgp-album-show-description">Exibir descrição principal do álbum por padrão</label>
				</div>
			`
		);
	}

	function renderSettingsImagesTab(settings) {
		return renderSettingsSection(
			"Perfis de imagem",
			"Defina os tamanhos gerados no storage próprio do plugin para thumbs, grids, slideshow e regeneração.",
			`
				<div class="mlgp-settings-grid">
					<div class="mlgp-field">
						<label for="mlgp-thumb-width">Largura da miniatura (px)</label>
						<input id="mlgp-thumb-width" type="number" min="80" max="2400" name="thumb_width" value="${Number(settings.thumb_width || 240)}">
					</div>
					<div class="mlgp-field">
						<label for="mlgp-thumb-height">Altura da miniatura (px)</label>
						<input id="mlgp-thumb-height" type="number" min="80" max="2400" name="thumb_height" value="${Number(settings.thumb_height || 160)}">
					</div>
					<div class="mlgp-field">
						<label for="mlgp-medium-width">Largura média (px)</label>
						<input id="mlgp-medium-width" type="number" min="120" max="3600" name="medium_width" value="${Number(settings.medium_width || 900)}">
					</div>
					<div class="mlgp-field">
						<label for="mlgp-medium-height">Altura média (px)</label>
						<input id="mlgp-medium-height" type="number" min="120" max="3600" name="medium_height" value="${Number(settings.medium_height || 900)}">
					</div>
					<div class="mlgp-field">
						<label for="mlgp-large-width">Largura grande (px)</label>
						<input id="mlgp-large-width" type="number" min="240" max="5200" name="large_width" value="${Number(settings.large_width || 1600)}">
					</div>
					<div class="mlgp-field">
						<label for="mlgp-large-height">Altura grande (px)</label>
						<input id="mlgp-large-height" type="number" min="240" max="5200" name="large_height" value="${Number(settings.large_height || 1600)}">
					</div>
				</div>
				<div class="mlgp-check">
					<input id="mlgp-thumb-crop" type="checkbox" name="thumb_crop" ${Number(settings.thumb_crop || 0) ? "checked" : ""}>
					<label for="mlgp-thumb-crop">Aplicar recorte fixo nas miniaturas</label>
				</div>
			`
		);
	}

	function renderSettingsWatermarkTab(settings) {
		return renderSettingsSection(
			"Marca d'água",
			"A marca d'água textual é aplicada nas variantes geradas a partir das imagens locais do plugin.",
			`
				<div class="mlgp-check">
					<input id="mlgp-watermark-enabled" type="checkbox" name="watermark_enabled" ${Number(settings.watermark_enabled || 0) ? "checked" : ""}>
					<label for="mlgp-watermark-enabled">Ativar marca d'água nas imagens geradas</label>
				</div>
				<div class="mlgp-settings-grid">
					<div class="mlgp-field">
						<label for="mlgp-watermark-text">Texto da marca d'água</label>
						<input id="mlgp-watermark-text" type="text" name="watermark_text" value="${escapeHtml(settings.watermark_text || "")}" placeholder="ML Gallery Pro">
					</div>
					<div class="mlgp-field">
						<label for="mlgp-watermark-opacity">Opacidade (%)</label>
						<input id="mlgp-watermark-opacity" type="number" min="10" max="95" name="watermark_opacity" value="${Number(settings.watermark_opacity || 34)}">
					</div>
					<div class="mlgp-field">
						<label for="mlgp-watermark-position">Posição</label>
						<select id="mlgp-watermark-position" name="watermark_position">
							<option value="top-left" ${String(settings.watermark_position || "bottom-right") === "top-left" ? "selected" : ""}>Topo esquerdo</option>
							<option value="top-right" ${String(settings.watermark_position || "bottom-right") === "top-right" ? "selected" : ""}>Topo direito</option>
							<option value="bottom-left" ${String(settings.watermark_position || "bottom-right") === "bottom-left" ? "selected" : ""}>Base esquerda</option>
							<option value="bottom-right" ${String(settings.watermark_position || "bottom-right") === "bottom-right" ? "selected" : ""}>Base direita</option>
							<option value="center" ${String(settings.watermark_position || "bottom-right") === "center" ? "selected" : ""}>Centro</option>
						</select>
					</div>
				</div>
			`
		);
	}

	function renderSettingsLabelsTab(settings) {
		return renderSettingsSection(
			"Rótulos e mensagens",
			"Ajuste os textos operacionais padrão do plugin sem editar código.",
			`
				<div class="mlgp-settings-grid">
					<div class="mlgp-field">
						<label for="mlgp-label-view-gallery">Rótulo de galeria</label>
						<input id="mlgp-label-view-gallery" type="text" name="label_view_gallery" value="${escapeHtml(settings.label_view_gallery || "")}">
					</div>
					<div class="mlgp-field">
						<label for="mlgp-label-back-to-album">Rótulo de retorno do álbum</label>
						<input id="mlgp-label-back-to-album" type="text" name="label_back_to_album" value="${escapeHtml(settings.label_back_to_album || "")}">
					</div>
					<div class="mlgp-field">
						<label for="mlgp-empty-gallery-message">Mensagem de galeria vazia</label>
						<input id="mlgp-empty-gallery-message" type="text" name="empty_gallery_message" value="${escapeHtml(settings.empty_gallery_message || "")}">
					</div>
					<div class="mlgp-field">
						<label for="mlgp-empty-album-message">Mensagem de álbum vazio</label>
						<input id="mlgp-empty-album-message" type="text" name="empty_album_message" value="${escapeHtml(settings.empty_album_message || "")}">
					</div>
				</div>
			`
		);
	}

	function renderSettingsToolsTab(settings, isRegenerating, storageLabel) {
		return renderSettingsSection(
			"Ferramentas operacionais",
			"Use a regeneração global quando alterar perfis de tamanho, recorte ou marca d'água.",
			`
				<div class="mlgp-settings-callout">
					As variantes locais serão recriadas dentro de <code class="mlgp-inline-code">${escapeHtml(storageLabel)}</code>.
				</div>
				<div class="mlgp-actions">
					<button type="button" class="mlgp-button" data-mlgp-regenerate-all="1" ${isRegenerating ? "disabled" : ""}>${escapeHtml(isRegenerating ? (config.strings.globalRegenerateRunning || "Regenerando toda a biblioteca local...") : (config.strings.globalRegenerateAction || "Regenerar toda a biblioteca local"))}</button>
				</div>
				<div class="mlgp-settings-callout">
					<strong>Zona de limpeza total</strong><br>
					Use estas ações quando precisar zerar o plugin sem mexer manualmente no banco ou no storage.
				</div>
				<div class="mlgp-actions">
					<button type="button" class="mlgp-button mlgp-button--danger" data-mlgp-delete-all-images="1">${escapeHtml(config.strings.deleteAllImagesAction || "Excluir todas as imagens")}</button>
					<button type="button" class="mlgp-button mlgp-button--danger" data-mlgp-delete-all-galleries="1">${escapeHtml(config.strings.deleteAllGalleriesAction || "Excluir todas as galerias")}</button>
					<button type="button" class="mlgp-button mlgp-button--danger" data-mlgp-factory-reset="1">${escapeHtml(config.strings.factoryResetAction || "Resetar plugin para o estado de fabrica")}</button>
				</div>
			`
		);
	}

	function renderValidationStatus(status) {
		const normalized = ["ok", "warning", "error"].includes(String(status || "")) ? String(status) : "warning";
		const labels = {
			ok: "OK",
			warning: "Atencao",
			error: "Critico",
		};
		const classes = {
			ok: "mlgp-status-pill is-clean",
			warning: "mlgp-status-pill is-warning",
			error: "mlgp-status-pill is-error",
		};

		return `<span class="${classes[normalized]}">${labels[normalized]}</span>`;
	}

	function renderValidationItem(item) {
		const label = String(item && item.label ? item.label : "Item");
		const value = String(item && item.value ? item.value : "-");
		const detail = String(item && item.detail ? item.detail : "");
		const status = item && item.status ? item.status : "warning";

		return `
			<article class="mlgp-validation-card">
				<div class="mlgp-validation-card__header">
					<strong>${escapeHtml(label)}</strong>
					${renderValidationStatus(status)}
				</div>
				<div class="mlgp-validation-card__value">${escapeHtml(value)}</div>
				<p class="mlgp-validation-card__detail">${escapeHtml(detail)}</p>
			</article>
		`;
	}

	function renderValidationSummary(summary) {
		const source = summary && typeof summary === "object" ? summary : {};
		const items = [
			{ label: "OK", value: Number(source.ok || 0), detail: "checks consistentes" },
			{ label: "Atencao", value: Number(source.warning || 0), detail: "pontos para revisar" },
			{ label: "Critico", value: Number(source.error || 0), detail: "itens que podem travar fluxo" },
		];

		return `
			<div class="mlgp-stats mlgp-validation-summary">
				${items.map((item) => `
					<article class="mlgp-stat">
						<span class="mlgp-stat__label">${escapeHtml(item.label)}</span>
						<div class="mlgp-stat__value">${Number(item.value || 0)}</div>
						<p class="mlgp-stat__detail">${escapeHtml(item.detail)}</p>
					</article>
				`).join("")}
			</div>
		`;
	}

	function renderLicensePanel(license) {
		const payload = license && typeof license === "object" ? license : {};
		const stateLabel = payload.state_label || payload.plan || (payload.is_full_active ? "Full" : "Free");
		const plan = payload.plan || stateLabel;
		const message = payload.message || (payload.is_full_active ? "Licenca Full ativa." : "Versao Free ativa.");
		const placeholder = config.strings.licensePlaceholder || "MLG-XXXXX-XXXXX-XXXXX";
		const isFree = String(stateLabel || "").toLowerCase() === "free";
		const isTrial = String(stateLabel || "").toLowerCase() === "trial";
		const primaryLabel = !String(payload.license_key || "").trim() && isFree
			? (config.strings.licenseStartTrial || "Iniciar trial gratis")
			: (config.strings.licenseValidate || "Validar serial");

		return `
			<div class="mlgp-license-panel">
				<div class="mlgp-license-panel__header">
					<strong>${escapeHtml(config.strings.licenseTitle || "Licenca / Serial")}</strong>
					<span class="mlgp-pill mlgp-pill--license-${escapeHtml(String(payload.state_tone || "free"))}">${escapeHtml(stateLabel)}</span>
				</div>
				<p class="mlgp-license-panel__status">${escapeHtml(message)}</p>
				<div class="mlgp-license-panel__input">
					<input type="text" data-mlgp-license-input placeholder="${escapeHtml(placeholder)}" value="${escapeHtml(payload.license_key || "")}">
				</div>
				<p class="mlgp-license-panel__hint">${escapeHtml(!String(payload.license_key || "").trim() && isFree ? (config.strings.licenseStartTrialHint || "Sem serial informado, o botao inicia o trial da instalacao.") : (isTrial ? "Trial ativo. Informe um serial para promover a instalacao para Full." : "Informe o serial para validar ou remova a licenca local."))}</p>
				<div class="mlgp-actions">
					<button type="button" class="mlgp-button mlgp-button--accent" data-mlgp-validate-license>${escapeHtml(primaryLabel)}</button>
					<button type="button" class="mlgp-button mlgp-button--ghost" data-mlgp-deactivate-license>${escapeHtml(config.strings.licenseDeactivate || "Remover licenca")}</button>
				</div>
				<div class="mlgp-license-panel__foot">
					<span><strong>Estado:</strong> ${escapeHtml(stateLabel)}</span>
					<span><strong>Plano:</strong> ${escapeHtml(plan)}</span>
				</div>
			</div>
		`;
	}

	function renderSettingsValidationTab(report) {
		const source = report && typeof report === "object" ? report : {};
		const sections = Array.isArray(source.sections) ? source.sections : [];

		if (!sections.length) {
			return renderSettingsSection(
				"Validacao do produto",
				"Confere storage, banco, versao e ambiente para nao avancarmos no escuro.",
				`
					<div class="mlgp-settings-callout">
						Nao foi possivel gerar o diagnostico agora. Tente atualizar esta aba novamente.
					</div>
					<div class="mlgp-actions">
						<button type="button" class="mlgp-button mlgp-button--ghost" data-mlgp-refresh-validation="1">Atualizar diagnostico</button>
					</div>
				`
			);
		}

		return renderSettingsSection(
			"Validacao do produto",
			"Resumo confiavel da instalacao atual para storage, banco, ambiente e linha de update.",
			`
				${renderLicensePanel(state.license)}
				${renderValidationSummary(source.summary || {})}
				<div class="mlgp-validation-sections">
					${sections.map((section) => `
						<div class="mlgp-validation-section">
							<header class="mlgp-validation-section__header">
								<div>
									<strong>${escapeHtml(section.title || "Diagnostico")}</strong>
									${section.intro ? `<p>${escapeHtml(section.intro)}</p>` : ""}
								</div>
								${renderValidationStatus((section.summary || {}).status || "warning")}
							</header>
							<div class="mlgp-validation-grid">
								${(Array.isArray(section.items) ? section.items : []).map(renderValidationItem).join("")}
							</div>
						</div>
					`).join("")}
				</div>
				<div class="mlgp-actions">
					<button type="button" class="mlgp-button mlgp-button--ghost" data-mlgp-refresh-validation="1">Atualizar diagnostico</button>
				</div>
			`
		);
	}

	function renderSettings() {
		const settings = state.settingsDraft || state.settings || {};
		const isRegenerating = !!(state.settingsOps && state.settingsOps.regenerating);
		const progressText = String((state.settingsOps && state.settingsOps.progressText) || "");
		const progressPercent = Number((state.settingsOps && state.settingsOps.progressPercent) || 0);
		const tabs = [
			{ key: "general", label: "Geral" },
			{ key: "content", label: "Conteúdo" },
			{ key: "slideshow", label: "Slideshow" },
			{ key: "typography", label: "Tipografia" },
			{ key: "albums", label: "Álbuns" },
			{ key: "images", label: "Imagens" },
			{ key: "watermark", label: "Marca d'água" },
			{ key: "labels", label: "Rótulos" },
			{ key: "tools", label: "Ferramentas" },
			{ key: "validation", label: "Validacao" },
		];
		const activeTab = tabs.some((tab) => tab.key === state.settingsUi.tab) ? state.settingsUi.tab : "general";
		const storageLabel = config.storageLabel || "wp-content/ml-gallery";
		const tabRenderers = {
			general: () => renderSettingsGeneralTab(settings, storageLabel),
			content: () => renderSettingsContentTab(settings),
			slideshow: () => renderSettingsSlideshowTab(settings),
			typography: () => renderSettingsTypographyTab(settings),
			albums: () => renderSettingsAlbumsTab(settings),
			images: () => renderSettingsImagesTab(settings),
			watermark: () => renderSettingsWatermarkTab(settings),
			labels: () => renderSettingsLabelsTab(settings),
			tools: () => renderSettingsToolsTab(settings, isRegenerating, storageLabel),
			validation: () => renderSettingsValidationTab(state.validation),
		};

		root.innerHTML = `
			<section class="mlgp-panel">
				<div class="mlgp-panel-header-inline">
					<div>
						<h2>Configurações</h2>
						<p class="mlgp-panel__intro">Separe o produto por setores e ajuste só o que importa em cada etapa, sem uma tela interminável.</p>
					</div>
				</div>
				<form id="mlgp-settings-form" class="mlgp-form">
					<div class="mlgp-settings-shell">
						<div class="mlgp-shell__tabs mlgp-settings-shell__tabs">
							${tabs.map((tab) => `
								<button type="button" class="mlgp-shell__tab ${activeTab === tab.key ? "is-active" : ""}" data-mlgp-settings-tab="${tab.key}">
									${escapeHtml(tab.label)}
								</button>
							`).join("")}
						</div>
						<div class="mlgp-settings-shell__body">
							${tabRenderers[activeTab]()}
						</div>
					</div>
					<div class="mlgp-actions mlgp-settings-actions">
						<button type="submit" class="mlgp-button mlgp-button--accent">Salvar configurações</button>
						<button type="button" class="mlgp-button mlgp-button--ghost" data-mlgp-apply-settings-all="1">${activeTab === "albums" ? "Aplicar esta configuração a todos os álbuns" : "Aplicar esta configuração a todos os álbuns"}</button>
					</div>
				</form>
			</section>
		`;
		return;

		root.innerHTML = `
			<section class="mlgp-panel">
				<h2>Configuracoes globais</h2>
				<p class="mlgp-panel__intro">Esses padroes agora controlam o grid, os perfis de imagem, o watermark e a regeneracao operacional de toda a biblioteca local do plugin.</p>
				<form id="mlgp-settings-form" class="mlgp-form">
					<h3>Display base</h3>
					<div class="mlgp-settings-grid">
						<div class="mlgp-field">
							<label for="mlgp-columns-desktop">Colunas desktop</label>
							<input id="mlgp-columns-desktop" type="number" min="1" max="8" name="columns_desktop" value="${Number(settings.columns_desktop || 4)}">
						</div>
						<div class="mlgp-field">
							<label for="mlgp-columns-tablet">Colunas tablet</label>
							<input id="mlgp-columns-tablet" type="number" min="1" max="6" name="columns_tablet" value="${Number(settings.columns_tablet || 3)}">
						</div>
						<div class="mlgp-field">
							<label for="mlgp-columns-mobile">Colunas mobile</label>
							<input id="mlgp-columns-mobile" type="number" min="1" max="4" name="columns_mobile" value="${Number(settings.columns_mobile || 2)}">
						</div>
						<div class="mlgp-field">
							<label for="mlgp-items-per-page">Itens por pagina</label>
							<input id="mlgp-items-per-page" type="number" min="1" max="5000" name="items_per_page" value="${Number(settings.items_per_page || 24)}">
						</div>
						<div class="mlgp-field">
							<label for="mlgp-image-quality">Qualidade da imagem</label>
							<input id="mlgp-image-quality" type="number" min="30" max="100" name="image_quality" value="${Number(settings.image_quality || 82)}">
						</div>
						<div class="mlgp-field">
							<label for="mlgp-default-gallery-preset">Preset padrao para novas galerias</label>
							<select id="mlgp-default-gallery-preset" name="default_gallery_preset">
								<option value="masonry-default" ${String(settings.default_gallery_preset || "masonry-default") === "masonry-default" ? "selected" : ""}>Masonry sem margem</option>
							<option value="clean-grid" ${String(settings.default_gallery_preset || "masonry-default") === "clean-grid" ? "selected" : ""}>Clean Grid</option>
								<option value="editorial-tile" ${String(settings.default_gallery_preset || "masonry-default") === "editorial-tile" ? "selected" : ""}>Editorial Tile</option>
								<option value="impact-mosaic" ${String(settings.default_gallery_preset || "masonry-default") === "impact-mosaic" ? "selected" : ""}>Impact Mosaic</option>
								<option value="story-justified" ${String(settings.default_gallery_preset || "masonry-default") === "story-justified" ? "selected" : ""}>Story Justified</option>
								<option value="showcase-filmstrip" ${String(settings.default_gallery_preset || "masonry-default") === "showcase-filmstrip" ? "selected" : ""}>Showcase Filmstrip</option>
							</select>
						</div>
					</div>

					<div class="mlgp-check">
						<input id="mlgp-enable-lightbox" type="checkbox" name="enable_lightbox" ${Number(settings.enable_lightbox || 0) ? "checked" : ""}>
						<label for="mlgp-enable-lightbox">Ativar lightbox no shortcode</label>
					</div>

					<div class="mlgp-check">
						<input id="mlgp-enable-lazy-load" type="checkbox" name="enable_lazy_load" ${Number(settings.enable_lazy_load || 0) ? "checked" : ""}>
						<label for="mlgp-enable-lazy-load">Ativar lazy load como padrao</label>
					</div>

					<div class="mlgp-check">
						<input id="mlgp-rounded-corners" type="checkbox" name="rounded_corners" ${Number(settings.rounded_corners ?? 0) ? "checked" : ""}>
						<label for="mlgp-rounded-corners">Arredondar cantos das galerias e thumbs no frontend</label>
					</div>

					<div class="mlgp-check">
						<input id="mlgp-pagination-enabled" type="checkbox" name="pagination_enabled" ${Number(settings.pagination_enabled ?? 1) ? "checked" : ""}>
						<label for="mlgp-pagination-enabled">Paginar galerias por padrao no frontend</label>
					</div>

					<div class="mlgp-check">
						<input id="mlgp-enable-frontend-filters" type="checkbox" name="enable_frontend_filters" ${Number(settings.enable_frontend_filters ?? 0) ? "checked" : ""}>
						<label for="mlgp-enable-frontend-filters">Ativar filtros de busca e tag por padrao nas novas galerias</label>
					</div>

					<div class="mlgp-check">
						<input id="mlgp-show-titles" type="checkbox" name="show_titles" ${Number(settings.show_titles ?? 0) ? "checked" : ""}>
						<label for="mlgp-show-titles">Exibir titulos das imagens por padrao nas novas galerias</label>
					</div>

					<div class="mlgp-check">
						<input id="mlgp-show-captions" type="checkbox" name="show_captions" ${Number(settings.show_captions ?? 0) ? "checked" : ""}>
						<label for="mlgp-show-captions">Exibir legendas das imagens por padrao nas novas galerias</label>
					</div>

					<div class="mlgp-check">
						<input id="mlgp-show-item-tags" type="checkbox" name="show_item_tags" ${Number(settings.show_item_tags ?? 0) ? "checked" : ""}>
						<label for="mlgp-show-item-tags">Exibir tags das imagens por padrao no frontend</label>
					</div>

					<h3>Slideshow</h3>
					<p class="mlgp-panel__intro">Defina o comportamento padrao dos controles visuais do slideshow para novas galerias.</p>
					<div class="mlgp-check">
						<input id="mlgp-slideshow-show-arrows" type="checkbox" name="slideshow_show_arrows" ${Number(settings.slideshow_show_arrows ?? 1) ? "checked" : ""}>
						<label for="mlgp-slideshow-show-arrows">Exibir setas por padrao no slideshow</label>
					</div>

					<div class="mlgp-check">
						<input id="mlgp-slideshow-show-thumbs" type="checkbox" name="slideshow_show_thumbs" ${Number(settings.slideshow_show_thumbs ?? 1) ? "checked" : ""}>
						<label for="mlgp-slideshow-show-thumbs">Exibir miniaturas por padrao no slideshow</label>
					</div>

					<h3>Setas personalizadas</h3>
					<p class="mlgp-panel__intro">O plugin usa a seta padrao nativa por default, mas voce pode enviar um PNG proprio para os controles anterior e proximo do slideshow.</p>
					<div class="mlgp-settings-grid">
						${renderMediaPicker("nav_arrow_prev_url", "Seta anterior (PNG)", settings.nav_arrow_prev_url || "", "Se vazio, o plugin usa o icone padrao para voltar.")}
						${renderMediaPicker("nav_arrow_next_url", "Seta proxima (PNG)", settings.nav_arrow_next_url || "", "Se vazio, o plugin usa o icone padrao para avancar.")}
					</div>

					<h3>Tipografia dos titulos</h3>
					<p class="mlgp-panel__intro">Controle o titulo principal da galeria e os titulos das imagens sem depender de CSS manual.</p>
					<div class="mlgp-settings-grid">
						<div class="mlgp-field">
							<label for="mlgp-heading-font-size">Tamanho do titulo principal (px)</label>
						<input id="mlgp-heading-font-size" type="number" min="20" max="96" name="heading_font_size" value="${Number(settings.heading_font_size || DEFAULT_HEADING_FONT_SIZE)}">
						</div>
						<div class="mlgp-field">
							<label for="mlgp-heading-color">Cor do titulo principal</label>
							<input id="mlgp-heading-color" type="color" name="heading_color" value="${escapeHtml(settings.heading_color || "#172033")}">
						</div>
						<div class="mlgp-field">
							<label for="mlgp-item-title-font-size">Tamanho do titulo das imagens (px)</label>
							<input id="mlgp-item-title-font-size" type="number" min="10" max="48" name="item_title_font_size" value="${Number(settings.item_title_font_size || 18)}">
						</div>
						<div class="mlgp-field">
							<label for="mlgp-item-title-color">Cor do titulo das imagens</label>
							<input id="mlgp-item-title-color" type="color" name="item_title_color" value="${escapeHtml(settings.item_title_color || "#172033")}">
						</div>
					</div>
				</form>
			</section>
		`;
	}

	function renderTagRow(item) {
		const galleryTitles = Array.isArray(item.gallery_titles) ? item.gallery_titles : [];
		const galleryPreview = galleryTitles.length
			? galleryTitles.slice(0, 3).join(", ")
			: "Sem galerias associadas";
		const extraGalleryCount = galleryTitles.length > 3 ? ` +${galleryTitles.length - 3}` : "";

		return `
			<article class="mlgp-tag-card">
				<div class="mlgp-tag-card__header">
					<div>
						<h3>${escapeHtml(item.name || item.slug || "Tag")}</h3>
						<div class="mlgp-list__meta">
							<span class="mlgp-pill">#${escapeHtml(item.slug || "")}</span>
							<span>${Number(item.item_count || 0)} imagens</span>
							<span>${Number(item.gallery_count || 0)} galerias</span>
						</div>
					</div>
				</div>
				${renderShortcodeBox("Shortcode global por tag", item.shortcode || "")}
				<div class="mlgp-tag-card__footer">
					<strong>Galerias com esta tag</strong>
					<p>${escapeHtml(galleryPreview)}${escapeHtml(extraGalleryCount)}</p>
				</div>
			</article>
		`;
	}

	function renderTags() {
		const tagState = state.tags || { items: [], stats: {}, query: "" };
		const stats = tagState.stats || {};
		const query = String(tagState.query || "").trim().toLowerCase();
		const items = (tagState.items || []).filter((item) => {
			if (!query) {
				return true;
			}

			const haystack = [
				item.name || "",
				item.slug || "",
				...(Array.isArray(item.gallery_titles) ? item.gallery_titles : []),
			]
				.join(" ")
				.toLowerCase();

			return haystack.includes(query);
		});

		root.innerHTML = `
			<section class="mlgp-stats mlgp-stats--dashboard">
				<article class="mlgp-stat">
					<span class="mlgp-stat__label">Tags</span>
					<div class="mlgp-stat__value">${Number(stats.tags || 0)}</div>
					<p class="mlgp-stat__detail">Termos ativos no catalogo</p>
				</article>
				<article class="mlgp-stat">
					<span class="mlgp-stat__label">Imagens marcadas</span>
					<div class="mlgp-stat__value">${Number(stats.images || 0)}</div>
					<p class="mlgp-stat__detail">Itens com pelo menos uma tag aplicada</p>
				</article>
				<article class="mlgp-stat">
					<span class="mlgp-stat__label">Galerias</span>
					<div class="mlgp-stat__value">${Number(stats.galleries || 0)}</div>
					<p class="mlgp-stat__detail">Galerias com cobertura por tag</p>
				</article>
				<article class="mlgp-stat">
					<span class="mlgp-stat__label">Shortcode</span>
					<div class="mlgp-stat__value">TAG</div>
					<p class="mlgp-stat__detail">Use [ml_gallery type="tag" tag="slug"]</p>
				</article>
			</section>

			<section class="mlgp-panel mlgp-panel--wide mlgp-panel--album-index">
				<div class="mlgp-panel-header-inline">
					<div>
						<h2>Catalogo de tags</h2>
						<p class="mlgp-panel__intro">Cada tag nasce das imagens do manager e pode ser reutilizada em shortcode global para montar galerias tematicas sem duplicar arquivos.</p>
					</div>
					<div class="mlgp-gallery-search">
						<input type="search" value="${escapeHtml(tagState.query || "")}" placeholder="Buscar tags..." data-mlgp-tag-search="1">
					</div>
				</div>
				<div class="mlgp-tag-catalog">
					${items.length ? items.map(renderTagRow).join("") : '<div class="mlgp-empty">Nenhuma tag encontrada para a busca atual.</div>'}
				</div>
			</section>
		`;
	}

	function fillGalleryForm(id) {
		const item = state.galleries.find((gallery) => Number(gallery.id) === Number(id));
		const form = document.getElementById("mlgp-gallery-form");

		if (!item || !form) {
			return;
		}

		form.elements.id.value = item.id || "";
		form.elements.title.value = item.title || "";
		form.elements.slug.value = item.slug || "";
		form.elements.status.value = item.status || "draft";
		form.elements.description.value = item.description || "";
	}

	function fillAlbumForm(id) {
		const item = state.albums.find((album) => Number(album.id) === Number(id));
		const form = document.getElementById("mlgp-album-form");

		if (!item || !form) {
			return;
		}

		form.elements.id.value = item.id || "";
		form.elements.title.value = item.title || "";
		form.elements.slug.value = item.slug || "";
		form.elements.status.value = item.status || "draft";
		form.elements.description.value = item.description || "";
	}

	function resetForm(formId) {
		const form = document.getElementById(formId);

		if (!form) {
			return;
		}

		form.reset();

		if (form.elements.id) {
			form.elements.id.value = "";
		}
	}

	function collectFormValues(form) {
		const payload = {};

		Array.from(form.elements).forEach((element) => {
			if (!element.name) {
				return;
			}

			if (element.type === "checkbox") {
				payload[element.name] = element.checked ? "1" : "0";
				return;
			}

			payload[element.name] = element.value;
		});

		return payload;
	}

	function insertShortcode(id, type = "gallery") {
		const shortcode = type === "album" 
			? `[ml_gallery type="album" id="${id}"]` 
			: `[ml_gallery id="${id}"]`;

		if (window.wp && window.wp.editor && typeof window.wp.editor.insertContent === "function") {
			window.wp.editor.insertContent(shortcode);
		} else if (window.send_to_editor) {
			window.send_to_editor(shortcode);
		} else {
			copyToClipboard(shortcode);
			showNotice("Editor não encontrado. Shortcode copiado para a área de transferência.");
		}
	}

	async function copyToClipboard(value) {
		const text = String(value || "").trim();

		if (!text) {
			return;
		}

		if (navigator.clipboard && typeof navigator.clipboard.writeText === "function") {
			await navigator.clipboard.writeText(text);
			return;
		}

		const textarea = document.createElement("textarea");
		textarea.value = text;
		textarea.setAttribute("readonly", "readonly");
		textarea.style.position = "fixed";
		textarea.style.opacity = "0";
		document.body.appendChild(textarea);
		textarea.select();

		const copied = document.execCommand("copy");
		textarea.remove();

		if (!copied) {
			throw new Error("Nao foi possivel copiar o shortcode.");
		}
	}

	function resetAlbumEditor() {
		state.albumEditor = emptyAlbumEditorState();
		closeAlbumPicker();
	}

	function addAlbumItem(itemType, itemId, shouldRender = true) {
		const list = itemType === "album" ? getAvailableSubalbums() : getAvailableAlbumGalleries();
		const source = list.find((item) => Number(item.id || 0) === Number(itemId || 0));

		if (!source) {
			return;
		}

		const existing = (state.albumEditor.items || []).some(
			(item) => item.item_type === itemType && Number(item.item_id) === Number(source.id)
		);

		if (existing) {
			return;
		}

		state.albumEditor.items = [
			...(state.albumEditor.items || []),
			{
				id: 0,
				item_type: itemType,
				item_id: Number(source.id || 0),
				title: source.title || "",
				slug: source.slug || "",
				description: source.description || "",
				status: source.status || "draft",
				shortcode: source.shortcode || "",
				cover: source.cover || null,
			},
		];
		markAlbumItemsDirty();

		if (shouldRender) {
			renderAlbums();
		}
	}

	function addAlbumItemsBatch(itemType, ids) {
		if (!Array.isArray(ids) || !ids.length) {
			return;
		}

		ids.forEach((id) => {
			addAlbumItem(itemType, id, false);
		});

		renderAlbums();
	}

	function moveAlbumItem(index, direction) {
		const items = [...(state.albumEditor.items || [])];
		const currentIndex = Number(index || 0);
		const targetIndex = direction === "down" ? currentIndex + 1 : currentIndex - 1;

		if (currentIndex < 0 || currentIndex >= items.length || targetIndex < 0 || targetIndex >= items.length) {
			return;
		}

		const current = items[currentIndex];
		items[currentIndex] = items[targetIndex];
		items[targetIndex] = current;
		state.albumEditor.items = items;
		markAlbumItemsDirty();
		renderAlbums();
	}

	function removeAlbumItem(index) {
		state.albumEditor.items = (state.albumEditor.items || []).filter((item, itemIndex) => itemIndex !== Number(index || -1));
		markAlbumItemsDirty();
		renderAlbums();
	}

	async function loadDashboard() {
		const response = await request("mlgp_get_dashboard", {});
		state.dashboard = response || {};
		state.validation = response && response.validation ? response.validation : state.validation;
		state.license = response && response.license ? response.license : state.license;
		renderDashboard();
	}

	async function loadAlbums(shouldRender = true) {
		const response = await request("mlgp_list_albums", { sort_mode: state.sorting.albums || "updated_at_desc" });
		state.albums = response.items || [];
		state.sorting.albums = response.sort_mode || state.sorting.albums || "updated_at_desc";

		if (state.albumEditor.activeId && !state.albums.some((item) => Number(item.id || 0) === Number(state.albumEditor.activeId || 0))) {
			resetAlbumEditor();
		}

		if (shouldRender) {
			renderAlbums();
		}
	}

	async function loadTags() {
		const response = await request("mlgp_list_tags", {});
		state.tags = {
			items: response.items || [],
			stats: response.stats || {},
			query: state.tags && state.tags.query ? state.tags.query : "",
		};
		renderTags();
	}

	async function loadGalleries(shouldRender = true) {
		const response = await request("mlgp_list_galleries", { sort_mode: state.sorting.galleries || "updated_at_desc" });
		state.galleries = response.items || [];
		state.sorting.galleries = response.sort_mode || state.sorting.galleries || "updated_at_desc";

		if (shouldRender && !["galleries", "add-images", "addImages"].includes(state.page)) {
			renderGalleries();
		}

		return response;
	}

	async function loadSettings() {
		const response = await request("mlgp_get_settings", {});
		state.settings = response.settings || {};
		state.settingsDraft = { ...(response.settings || {}) };
		state.validation = response.validation || null;
		state.license = response.license || state.license;
		renderSettings();
	}

	async function regenerateAllLocalItems() {
		state.settingsOps.regenerating = true;
		state.settingsOps.progressText = "Preparando lotes da biblioteca local...";
		state.settingsOps.progressPercent = 0;
		renderSettings();

		let offset = 0;
		const limit = 20;
		let total = 0;
		let processed = 0;
		let failed = 0;
		let lastResponse = null;

		try {
			while (true) {
				const response = await request("mlgp_regenerate_local_items_batch", { offset, limit });
				lastResponse = response;
				total = Number(response.total || total || 0);
				processed += Number(response.processed || 0);
				failed += Number(response.failed || 0);
				offset = Number(response.next_offset || offset);
				state.validation = response.validation || state.validation;

				const doneCount = Math.min(total || offset, offset);
				state.settingsOps.progressPercent = total > 0 ? Math.min(100, Math.round((doneCount / total) * 100)) : 0;
				state.settingsOps.progressText = `Processando lote: ${doneCount} de ${total || doneCount} imagens verificadas. Sucesso: ${processed}. Falhas: ${failed}.`;
				renderSettings();

				if (response.done) {
					break;
				}

				await new Promise((resolve) => window.setTimeout(resolve, 40));
			}

			state.settingsOps.progressPercent = 100;
			state.settingsOps.progressText = `Concluido: ${processed} imagens regeneradas em ${total || processed} analisadas. Falhas: ${failed}.`;
			renderSettings();

			return {
				...(lastResponse || {}),
				message: failed > 0
					? `Reconstrucao em lotes concluida com ressalvas: ${processed} imagens regeneradas e ${failed} falhas.`
					: `Reconstrucao em lotes concluida: ${processed} imagens regeneradas com sucesso.`,
			};
		} finally {
			state.settingsOps.regenerating = false;
			renderSettings();
		}
	}

	async function openAlbumEditor(albumId) {
		const response = await request("mlgp_get_album_editor", { album_id: albumId });
		syncAlbumEditor(response);
		renderAlbums();
	}

	async function saveAlbumData(showNoticeAfterSave = true) {
		const response = await request("mlgp_save_album", {
			id: state.albumEditor.activeId || 0,
			title: state.albumEditor.form.title || "",
			slug: state.albumEditor.form.slug || "",
			description: state.albumEditor.form.description || "",
			status: state.albumEditor.form.status || "draft",
			display_type: state.albumEditor.form.displayType || "grid",
			settings: JSON.stringify(serializeAlbumSettings(state.albumEditor.form)),
		});

		syncAlbumEditor(response.editor || null);
		await loadAlbums(false);
		renderAlbums();

		if (showNoticeAfterSave) {
			showNotice(response.message || "Album salvo com sucesso.");
		}
	}

	async function saveAlbumItems(showNoticeAfterSave = true) {
		if (!state.albumEditor.activeId) {
			return;
		}

		const response = await request("mlgp_save_album_items", {
			album_id: state.albumEditor.activeId,
			items: JSON.stringify(
				(state.albumEditor.items || []).map((item) => ({
					item_type: item.item_type || "gallery",
					item_id: Number(item.item_id || 0),
				}))
			),
		});

		syncAlbumEditor(response.editor || null);
		await loadAlbums(false);
		renderAlbums();

		if (showNoticeAfterSave) {
			showNotice(response.message || "Estrutura do album salva com sucesso.");
		}
	}

	async function saveAlbumAllChanges() {
		if (state.albumEditor.albumDirty || !state.albumEditor.activeId) {
			await saveAlbumData(false);
		}

		if (state.albumEditor.itemsDirty) {
			await saveAlbumItems(false);
		}

				showNotice("Álbum atualizado com sucesso.");
	}

	async function boot() {
		try {
			if (config.isPostEditor) {
				// We don't load everything immediately to avoid overhead on every post load.
				// But we need to make sure state is ready for when the picker opens.
				return;
			}

			if (state.page === "albums") {
				await loadAlbums(false);

				if (Number(config.activeAlbumId || 0)) {
					await openAlbumEditor(config.activeAlbumId);
					return;
				}

				renderAlbums();
				return;
			}

			if (state.page === "galleries") {
				await loadGalleries(false);

				if (Number(config.activeGalleryId || 0)) {
					await openGalleryEditor(config.activeGalleryId);
					return;
				}

				renderGalleries();
				return;
			}

			if (state.page === "add-images" || state.page === "addImages") {
				await loadGalleries(false);
				
				if (state.galleries && state.galleries.length > 0 && Number(config.activeGalleryId || 0)) {
					await openGalleryEditor(config.activeGalleryId);
				} else {
					openGalleryCreator("upload");
				}
				return;
			}

			if (state.page === "settings") {
				await loadSettings();
				return;
			}

			if (state.page === "tags") {
				await loadTags();
				return;
			}

			await loadDashboard();
		} catch (error) {
			showNotice(error.message || config.strings.genericError, "error");
		}
	}

	async function handleRootFieldChange(event) {
		const target = event.target;

		if (!(target instanceof HTMLElement)) {
			return;
		}

		if (target.dataset.mlgpGallerySort) {
			state.sorting.galleries = target.value || "updated_at_desc";
			clearGallerySelection();
			await loadGalleries(false);
			renderGalleries();
			return;
		}

		if (target.dataset.mlgpAlbumSort) {
			state.sorting.albums = target.value || "updated_at_desc";
			clearAlbumSelection();
			await loadAlbums(false);
			renderAlbums();
			return;
		}

		if ((target instanceof HTMLInputElement || target instanceof HTMLTextAreaElement || target instanceof HTMLSelectElement) && target.form && target.form.id === "mlgp-settings-form" && target.name) {
			if (target.name === "album_cover_width" || target.name === "album_cover_height") {
				if (event.type === "input") {
					state.settingsDraft = {
						...(state.settingsDraft || {}),
						[target.name]: String(target.value || ""),
					};
				} else {
					const previousWidth = state.settingsDraft.album_cover_width ?? state.settings.album_cover_width ?? 360;
					const previousHeight = state.settingsDraft.album_cover_height ?? state.settings.album_cover_height ?? 280;
					const lockRatioEnabled = Number(state.settingsDraft.album_cover_lock_ratio ?? state.settings.album_cover_lock_ratio ?? 1) === 1;
					const synced = syncLockedAlbumCoverValues(previousWidth, previousHeight, target.name, target.value, lockRatioEnabled);
					const siblingName = target.name === "album_cover_width" ? "album_cover_height" : "album_cover_width";
					const sibling = root.querySelector(`[name="${siblingName}"]`);

					state.settingsDraft = {
						...(state.settingsDraft || {}),
						album_cover_width: String(synced.coverWidth),
						album_cover_height: String(synced.coverHeight),
					};

					target.value = String(target.name === "album_cover_width" ? synced.coverWidth : synced.coverHeight);

					if (sibling instanceof HTMLInputElement) {
						sibling.value = String(target.name === "album_cover_width" ? synced.coverHeight : synced.coverWidth);
					}
				}
			} else {
				state.settingsDraft = {
					...(state.settingsDraft || {}),
					[target.name]: readControlValue(target),
				};
			}
		}

		const albumField = target.dataset.mlgpAlbumField;

		if (albumField) {
			if (albumField === "coverWidth" || albumField === "coverHeight") {
				const previousWidth = state.albumEditor.form.coverWidth ?? 360;
				const previousHeight = state.albumEditor.form.coverHeight ?? 280;
				const lockRatioEnabled = Number(state.albumEditor.form.coverLockRatio ?? 1) === 1;
				const synced = syncLockedAlbumCoverValues(previousWidth, previousHeight, albumField, target.value, lockRatioEnabled);
				const siblingField = albumField === "coverWidth" ? "coverHeight" : "coverWidth";
				const sibling = root.querySelector(`[data-mlgp-album-field="${siblingField}"]`);

				state.albumEditor.form.coverWidth = synced.coverWidth;
				state.albumEditor.form.coverHeight = synced.coverHeight;
				target.value = String(albumField === "coverWidth" ? synced.coverWidth : synced.coverHeight);

				if (sibling instanceof HTMLInputElement) {
					sibling.value = String(albumField === "coverWidth" ? synced.coverHeight : synced.coverWidth);
				}
			} else {
				state.albumEditor.form[albumField] = target instanceof HTMLInputElement && target.type === "checkbox"
					? (target.checked ? 1 : 0)
					: target.value;
			}
			markAlbumDirty();
			return;
		}

		if (target.dataset.mlgpSelectGallery) {
			toggleSelection("selectedGalleryIds", target.dataset.mlgpSelectGallery, target.checked);
			renderGalleries();
			return;
		}

		if (target.dataset.mlgpSelectAllGalleries) {
			setSelectionFromItems("selectedGalleryIds", state.galleries || [], target.checked);
			renderGalleries();
			return;
		}

		if (target.dataset.mlgpSelectAlbum) {
			toggleSelection("selectedAlbumIds", target.dataset.mlgpSelectAlbum, target.checked);
			renderAlbums();
			return;
		}

		if (target.dataset.mlgpAlbumSearch) {
			state.albumSearchQuery = target.value || "";
			renderAlbums();
			return;
		}

		if (target.dataset.mlgpSelectAllAlbums) {
			setSelectionFromItems("selectedAlbumIds", state.albums || [], target.checked);
			renderAlbums();
			return;
		}

		if (target.dataset.mlgpTagSearch) {
			state.tags.query = target.value || "";
			renderTags();
			return;
		}

		if (target.dataset.mlgpPickerSelect) {
			togglePickerSelection(target.dataset.mlgpPickerSelect, target.checked);
			renderAlbums();
			return;
		}
	}



	root.addEventListener("keydown", async (event) => {
		if (!(event.target instanceof Element) || !["Enter", " "].includes(event.key)) {
			return;
		}

		const target = event.target.closest("[data-mlgp-copy-shortcode]");

		if (!target) {
			return;
		}

		event.preventDefault();

		try {
			await copyToClipboard(target.dataset.mlgpCopyShortcode);
			showNotice("Shortcode copiado com sucesso.");
		} catch (error) {
			showNotice(error.message || "Nao foi possivel copiar o shortcode.", "error");
		}
	});

	root.addEventListener("submit", async (event) => {
		const form = event.target;

		if (!(form instanceof HTMLFormElement)) {
			return;
		}

		if (form.id === "mlgp-gallery-form") {
			event.preventDefault();

			try {
				await request("mlgp_save_gallery", collectFormValues(form));
				showNotice("Galeria salva com sucesso.");
				await loadGalleries();
				resetForm("mlgp-gallery-form");
			} catch (error) {
				showNotice(error.message || config.strings.genericError, "error");
			}
		}

		if (form.id === "mlgp-album-form") {
			event.preventDefault();
			state.albumEditor.form = {
				...defaultAlbumDisplaySettings({}, state.settings || config.settings || {}),
				title: form.elements.title ? form.elements.title.value : "",
				slug: form.elements.slug ? form.elements.slug.value : "",
				status: form.elements.status ? form.elements.status.value : "draft",
				description: form.elements.description ? form.elements.description.value : "",
			};

			try {
				await saveAlbumData(true);
			} catch (error) {
				showNotice(error.message || config.strings.genericError, "error");
			}
		}

		if (form.id === "mlgp-settings-form") {
			event.preventDefault();

			try {
				rememberSettingsFormValues(form);
				const response = await request("mlgp_save_settings", state.settingsDraft || collectFormValues(form));
				state.settings = response.settings || {};
				state.settingsDraft = { ...(response.settings || {}) };
				state.validation = response.validation || state.validation;
				showNotice("Configurações salvas com sucesso.");
				renderSettings();
			} catch (error) {
				showNotice(error.message || config.strings.genericError, "error");
			}
		}
	});

	document.addEventListener("click", async (event) => {
		if (!(event.target instanceof Element)) {
			return;
		}

		const target = event.target.closest("[data-mlgp-trigger-picker],[data-edit-gallery],[data-delete-gallery],[data-reset-gallery],[data-manage-album],[data-delete-album],[data-reset-album],[data-mlgp-open-album-picker],[data-mlgp-close-album-picker],[data-mlgp-add-item-flow],[data-mlgp-batch-add-picker],[data-mlgp-move-album-item],[data-mlgp-remove-album-item],[data-mlgp-save-album-all],[data-mlgp-copy-shortcode],[data-mlgp-regenerate-all],[data-mlgp-media-pick],[data-mlgp-media-clear],[data-mlgp-settings-tab],[data-mlgp-refresh-validation],[data-mlgp-validate-license],[data-mlgp-deactivate-license],[data-mlgp-delete-selected-galleries],[data-mlgp-delete-all-galleries],[data-mlgp-delete-all-images],[data-mlgp-delete-selected-albums],[data-mlgp-factory-reset],[data-mlgp-apply-settings-all]");

		if (!target) {
			return;
		}

		if (target.dataset.mlgpTriggerPicker) {
			const type = target.dataset.mlgpTriggerPicker;
			// For editor picker, we must ensure data is loaded
			if (!state.galleries.length || !state.albums.length) {
				try {
					target.disabled = true;
					await Promise.all([loadGalleries(false), loadAlbums(false)]);
				} catch (e) {
					console.error("Failed to load picker data", e);
				} finally {
					target.disabled = false;
				}
			}
			openAlbumPicker(type, "post-editor");
			renderAlbums();
			return;
		}

		if (target.dataset.mlgpSettingsTab) {
			rememberSettingsFormValues(root.querySelector("#mlgp-settings-form"));
			state.settingsUi.tab = target.dataset.mlgpSettingsTab;
			renderSettings();
			return;
		}

		if (target.dataset.mlgpApplySettingsAll) {
			const form = root.querySelector("#mlgp-settings-form");
			if (!(form instanceof HTMLFormElement)) {
				return;
			}
			const isAlbumTab = String(state.settingsUi.tab || "general") === "albums";
			const action = isAlbumTab ? "mlgp_apply_settings_to_all_albums" : "mlgp_apply_settings_to_all_galleries";
			const entityLabel = isAlbumTab ? "álbuns" : "galerias";
			try {
				rememberSettingsFormValues(form);
				const response = await request(action, state.settingsDraft || collectFormValues(form));
				state.settings = response.settings || {};
				state.settingsDraft = { ...(response.settings || {}) };
				state.validation = response.validation || state.validation;
				showNotice(response.message || `Configuração aplicada a ${Number(response.updated || 0)} ${entityLabel}.`);
				renderSettings();
			} catch (error) {
				showNotice(error.message || config.strings.genericError, "error");
			}
			return;
		}

		if (target.dataset.mlgpRefreshValidation) {
			try {
				const response = await request("mlgp_get_validation_report", {});
				state.validation = response.validation || state.validation;
				state.settingsUi.tab = "validation";
				renderSettings();
			} catch (error) {
				showNotice(error.message || config.strings.genericError, "error");
			}
			return;
		}

		if (target.hasAttribute("data-mlgp-validate-license") || target.hasAttribute("data-mlgp-deactivate-license")) {
			const licenseInput = root.querySelector("[data-mlgp-license-input]");
			const licenseKey = licenseInput ? String(licenseInput.value || "").trim() : "";
			const action = target.hasAttribute("data-mlgp-validate-license") ? "mlgp_validate_license" : "mlgp_deactivate_license";
			const payload = {
				license_key: licenseKey,
				license_nonce: config.licenseNonce || "",
			};

			try {
				target.disabled = true;
				target.textContent = target.hasAttribute("data-mlgp-validate-license") ? (config.strings.licenseProcessing || "Validando...") : (config.strings.licenseRemoving || "Removendo...");
				const response = await request(action, payload);
				state.license = response.license || state.license;
				if (state.dashboard && typeof state.dashboard === "object") {
					state.dashboard.license = state.license;
				}
				showNotice((response.license && response.license.message) || config.strings.licenseSuccess || "Licenca atualizada.");
				renderSettings();
				if (state.page === "dashboard") {
					renderDashboard();
				}
			} catch (error) {
				showNotice(error.message || config.strings.licenseError || "Nao foi possivel validar o serial.", "error");
			} finally {
				target.disabled = false;
				target.textContent = target.hasAttribute("data-mlgp-validate-license")
					? escapeHtml((!String((state.license && state.license.license_key) || "").trim() && String((state.license && (state.license.state_label || state.license.plan)) || "").toLowerCase() === "free") ? (config.strings.licenseStartTrial || "Iniciar trial gratis") : (config.strings.licenseValidate || "Validar serial"))
					: escapeHtml(config.strings.licenseDeactivate || "Remover licenca");
			}

			return;
		}

		if (target.dataset.editGallery) {
			fillGalleryForm(target.dataset.editGallery);
			return;
		}

		if (target.dataset.resetGallery) {
			resetForm("mlgp-gallery-form");
			return;
		}

		if (target.dataset.deleteGallery) {
			if (!window.confirm(config.strings.confirmDeleteGallery)) {
				return;
			}

			try {
				await request("mlgp_delete_gallery", { id: target.dataset.deleteGallery });
				clearGallerySelection();
				showNotice("Galeria excluida com sucesso.");
				await loadGalleries();
			} catch (error) {
				showNotice(error.message || config.strings.genericError, "error");
			}

			return;
		}

		if (target.dataset.mlgpDeleteSelectedGalleries) {
			const ids = getSelectedGalleryIds();

			if (!ids.length) {
				showNotice("Selecione pelo menos uma galeria para excluir.", "error");
				return;
			}

			if (!window.confirm(config.strings.confirmDeleteSelectedGalleries || "Deseja realmente excluir as galerias selecionadas?")) {
				return;
			}

			try {
				await request("mlgp_delete_galleries_bulk", { ids: JSON.stringify(ids) });
				clearGallerySelection();
				showNotice("Galerias selecionadas excluidas com sucesso.");
				await loadGalleries();
			} catch (error) {
				showNotice(error.message || config.strings.genericError, "error");
			}

			return;
		}

		if (target.dataset.mlgpDeleteAllGalleries) {
			if (!window.confirm(config.strings.confirmDeleteAllGalleries || "Deseja excluir todas as galerias?")) {
				return;
			}

			const originalLabel = target.textContent;
			try {
				target.disabled = true;
				const response = await request("mlgp_delete_all_galleries", {});
				clearGallerySelection();
				resetAlbumEditor();
				await Promise.all([loadGalleries(), loadAlbums(), loadTags()]);
				showNotice(response.message || "Todas as galerias foram excluidas com sucesso.");
			} catch (error) {
				showNotice(error.message || config.strings.genericError, "error");
			} finally {
				target.disabled = false;
				target.textContent = originalLabel;
			}

			return;
		}

		if (target.dataset.mlgpDeleteAllImages) {
			if (!window.confirm(config.strings.confirmDeleteAllImages || "Deseja excluir todas as imagens?")) {
				return;
			}

			const originalLabel = target.textContent;
			try {
				target.disabled = true;
				const response = await request("mlgp_delete_all_gallery_images", {});
				clearGallerySelection();
				await Promise.all([loadGalleries(), loadTags()]);
				showNotice(response.message || "Todas as imagens foram excluidas com sucesso.");
				if (state.page === "settings") {
					renderSettings();
				}
			} catch (error) {
				showNotice(error.message || config.strings.genericError, "error");
			} finally {
				target.disabled = false;
				target.textContent = originalLabel;
			}

			return;
		}

		if (target.dataset.manageAlbum) {
			const albumId = Number(target.dataset.manageAlbum || 0);

			if (
				hasAlbumPendingChanges() &&
				albumId !== Number(state.albumEditor.activeId || 0) &&
				!window.confirm("Existem alteracoes pendentes no album atual. Deseja abrir outro album mesmo assim?")
			) {
				return;
			}

			try {
				await openAlbumEditor(albumId);
			} catch (error) {
				showNotice(error.message || config.strings.genericError, "error");
			}

			return;
		}

		if (target.dataset.resetAlbum) {
			if (
				hasAlbumPendingChanges() &&
				!window.confirm("Existem alteracoes pendentes no album atual. Deseja descartar e criar um novo album?")
			) {
				return;
			}

			resetAlbumEditor();
			renderAlbums();
			return;
		}

		if (target.dataset.mlgpOpenAlbumPicker) {
			openAlbumPicker(target.dataset.mlgpOpenAlbumPicker);
			renderAlbums();
			return;
		}

		if (target.dataset.mlgpCloseAlbumPicker) {
			closeAlbumPicker();
			renderAlbums();
			return;
		}

		if (target.dataset.mlgpAddItemFlow) {
			const flow = target.dataset.mlgpAddItemFlow;
			const type = target.dataset.itemType;
			const id = Number(target.dataset.itemId || 0);

			if (flow === "shortcode") {
				insertShortcode(id, type);
				closeAlbumPicker();
			} else {
				addAlbumItem(type, id);
			}
			
			renderAlbums();
			return;
		}

		if (target.dataset.mlgpBatchAddPicker) {
			const flow = target.dataset.mlgpFlow || "linked";
			const type = target.dataset.mlgpBatchAddPicker;
			const ids = state.albumPicker.selectedIds;

			if (flow === "shortcode") {
				ids.forEach(id => insertShortcode(id, type));
				closeAlbumPicker();
			} else {
				addAlbumItemsBatch(type, ids);
			}

			renderAlbums();
			return;
		}

		if (target.dataset.mlgpMoveAlbumItem) {
			moveAlbumItem(Number(target.dataset.mlgpMoveAlbumItem || 0), target.dataset.direction || "up");
			return;
		}

		if (target.dataset.mlgpRemoveAlbumItem) {
			removeAlbumItem(Number(target.dataset.mlgpRemoveAlbumItem || 0));
			return;
		}

		if (target.dataset.mlgpSaveAlbumAll) {
			try {
				await saveAlbumAllChanges();
			} catch (error) {
				showNotice(error.message || config.strings.genericError, "error");
			}

			return;
		}

		if (target.dataset.mlgpCopyShortcode) {
			try {
				await copyToClipboard(target.dataset.mlgpCopyShortcode);
				showNotice("Shortcode copiado com sucesso.");
			} catch (error) {
				showNotice(error.message || "Nao foi possivel copiar o shortcode.", "error");
			}

			return;
		}

		if (target.dataset.mlgpMediaPick) {
			try {
				openMediaPicker(target.dataset.mlgpMediaPick);
			} catch (error) {
				showNotice(error.message || "Nao foi possivel abrir a biblioteca de midia.", "error");
			}

			return;
		}

		if (target.dataset.mlgpMediaClear) {
			updateMediaPickerValue(target.dataset.mlgpMediaClear, "");
			return;
		}

		if (target.dataset.mlgpRegenerateAll) {
			if (!window.confirm(config.strings.confirmGlobalRegenerate || "Deseja regenerar todas as previews locais com os perfis e watermark atuais?")) {
				return;
			}

			try {
				const response = await regenerateAllLocalItems();
				showNotice(response.message || "Regeneracao global concluida com sucesso.");
			} catch (error) {
				showNotice(error.message || config.strings.genericError, "error");
			}

			return;
		}

		if (target.dataset.mlgpFactoryReset) {
			if (!window.confirm(config.strings.confirmFactoryReset || "Deseja resetar o plugin para o estado de fabrica?")) {
				return;
			}

			const originalLabel = target.textContent;
			try {
				target.disabled = true;
				const response = await request("mlgp_factory_reset", {});
				state.settings = response.settings || {};
				state.settingsDraft = { ...(response.settings || {}) };
				state.validation = response.validation || state.validation;
				state.license = response.license || state.license;
				resetAlbumEditor();
				clearGallerySelection();
				clearAlbumSelection();
				await Promise.all([loadGalleries(), loadAlbums(), loadTags()]);
				showNotice(response.message || config.strings.factoryResetSuccess || "Plugin resetado com sucesso.");
				renderSettings();
			} catch (error) {
				showNotice(error.message || config.strings.genericError, "error");
			} finally {
				target.disabled = false;
				target.textContent = originalLabel;
			}

			return;
		}

		if (target.dataset.deleteAlbum) {
			if (!window.confirm(config.strings.confirmDeleteAlbum)) {
				return;
			}

			try {
				const deletedId = Number(target.dataset.deleteAlbum || 0);
				await request("mlgp_delete_album", { id: deletedId });
				state.selectedAlbumIds = getSelectedAlbumIds().filter((id) => id !== deletedId);

				if (deletedId === Number(state.albumEditor.activeId || 0)) {
					resetAlbumEditor();
				}

				showNotice("Album excluido com sucesso.");
				await loadAlbums();
				renderAlbums();
			} catch (error) {
				showNotice(error.message || config.strings.genericError, "error");
			}

			return;
		}

		if (target.dataset.mlgpDeleteSelectedAlbums) {
			const ids = getSelectedAlbumIds();

			if (!ids.length) {
				showNotice("Selecione pelo menos um album para excluir.", "error");
				return;
			}

			if (!window.confirm(config.strings.confirmDeleteSelectedAlbums || "Deseja realmente excluir os albuns selecionados?")) {
				return;
			}

			try {
				await request("mlgp_delete_albums_bulk", { ids: JSON.stringify(ids) });

				if (ids.includes(Number(state.albumEditor.activeId || 0))) {
					resetAlbumEditor();
				}

				clearAlbumSelection();
				showNotice("Albuns selecionados excluidos com sucesso.");
				await loadAlbums();
				renderAlbums();
			} catch (error) {
				showNotice(error.message || config.strings.genericError, "error");
			}

			return;
		}
	});

	if (root && root.id !== "mlgp-editor-picker-root") {
		root.addEventListener("input", handleRootFieldChange);
		root.addEventListener("change", handleRootFieldChange);
	}

	document.addEventListener("keydown", (event) => {
		if (event.key !== "Escape") {
			return;
		}

		if (state.albumPicker && state.albumPicker.open) {
			closeAlbumPicker();
			renderAlbums();
		}
	});

	boot();
}
