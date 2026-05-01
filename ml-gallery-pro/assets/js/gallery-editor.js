(function () {
	const config = window.MLGPAdmin || {};
	const root = document.getElementById("mlgp-admin-app");
	const currentPage = root ? (root.dataset.page || config.page || "galleries") : "galleries";
	const isAddImagesPage = currentPage === "add-images";
	const DEFAULT_HEADING_FONT_SIZE = 34;

	if (!root || !["galleries", "add-images"].includes(currentPage)) {
		return;
	}

	function getServerImportRoots() {
		return Array.isArray(config.serverImportRoots) ? config.serverImportRoots : [];
	}

	function getDefaultServerRoot() {
		const roots = getServerImportRoots();

		return roots.length ? String(roots[0].value || "") : "";
	}

	function emptyEditorState() {
		return {
			activeId: 0,
			gallery: null,
			form: {
				title: "",
				slug: "",
				status: "draft",
				description: "",
				displayType: "masonry",
				columnsDesktop: 4,
				columnsTablet: 3,
				columnsMobile: 2,
				cardGap: 0,
				roundedCorners: 0,
				enableFrontendFilters: 0,
				paginationEnabled: 1,
				itemsPerPage: 24,
				showHeading: 0,
				showDescription: 0,
				showTitles: 0,
				showCaptions: 0,
				showItemTags: 0,
				headingFontSize: DEFAULT_HEADING_FONT_SIZE,
				headingColor: "#172033",
				itemTitleFontSize: 18,
				itemTitleColor: "#172033",
				justifiedRowHeight: 220,
				slideshowAutoplay: 1,
				slideshowShowArrows: 1,
				slideshowShowThumbs: 1,
				slideshowInterval: 4000,
			},
			items: [],
			coverItemId: 0,
			selectedItemIds: [],
			galleryDirty: false,
			itemsDirty: false,
		};
	}

	function emptyUploadState() {
		return {
			uploading: false,
			progress: 0,
			totalFiles: 0,
			message: "",
			serverRoot: getDefaultServerRoot(),
			serverPath: "",
		};
	}

	function emptyCreateModalState() {
		return {
			open: false,
			title: "",
			slug: "",
			description: "",
			status: "publish",
			source: "files",
			files: [],
			zipFile: null,
			submitting: false,
			progress: 0,
			message: "",
			serverRoot: getDefaultServerRoot(),
			serverPath: "",
		};
	}

	const state = {
		searchTerm: "",
		sortMode: (config.sorting && config.sorting.galleries) || "id_desc",
		galleries: [],
		editor: emptyEditorState(),
		itemFilters: {
			search: "",
			visibility: "all",
			tag: "",
		},
		dragItemId: 0,
		bulkTab: "titles",
		bulkTitle: "",
		bulkAlt: "",
		bulkCaption: "",
		bulkTags: "",
		upload: emptyUploadState(),
		createModal: emptyCreateModalState(),
	};

	function escapeHtml(value) {
		return String(value || "")
			.replaceAll("&", "&amp;")
			.replaceAll("<", "&lt;")
			.replaceAll(">", "&gt;")
			.replaceAll('"', "&quot;")
			.replaceAll("'", "&#039;");
	}

	function renderSortControl(selectedValue) {
		const options = [
			["id_desc", "Ordenar: ID ↓"],
			["id_asc", "Ordenar: ID ↑"],
			["created_at_desc", "Ordenar: Criação ↓"],
			["created_at_asc", "Ordenar: Criação ↑"],
			["updated_at_desc", "Ordenar: Modificação ↓"],
			["updated_at_asc", "Ordenar: Modificação ↑"],
		];
		const value = selectedValue || "id_desc";

		return `
			<label class="mlgp-sort-control">
				<span class="screen-reader-text">Ordenar por</span>
				<select data-mlgp-gallery-sort="1">
					${options.map(([optionValue, label]) => `<option value="${optionValue}" ${optionValue === value ? "selected" : ""}>${label}</option>`).join("")}
				</select>
			</label>
		`;
	}

	function isFieldControl(target) {
		return target instanceof HTMLInputElement || target instanceof HTMLTextAreaElement || target instanceof HTMLSelectElement;
	}

	function integerValue(value, fallback) {
		const parsed = Number.parseInt(String(value ?? ""), 10);

		return Number.isFinite(parsed) ? parsed : fallback;
	}

	function toggleValue(value, fallback) {
		if (value === null || typeof value === "undefined" || value === "") {
			return fallback;
		}

		if (typeof value === "boolean") {
			return value ? 1 : 0;
		}

		return ["1", "true", "yes", "on"].includes(String(value).toLowerCase()) ? 1 : 0;
	}

	function defaultGalleryDisplaySettings() {
		const settings = config.settings || {};
		const defaults = {
			columnsDesktop: integerValue(settings.columns_desktop, 4),
			columnsTablet: integerValue(settings.columns_tablet, 3),
			columnsMobile: integerValue(settings.columns_mobile, 2),
			cardGap: integerValue(settings.card_gap, 0),
			roundedCorners: toggleValue(settings.rounded_corners, 0),
			enableFrontendFilters: toggleValue(settings.enable_frontend_filters, 0),
			paginationEnabled: toggleValue(settings.pagination_enabled, 1),
			itemsPerPage: integerValue(settings.items_per_page, 24),
			showHeading: toggleValue(settings.show_gallery_heading, 0),
			showDescription: toggleValue(settings.show_gallery_description, 0),
			showTitles: toggleValue(settings.show_titles, 0),
			showCaptions: toggleValue(settings.show_captions, 0),
			showItemTags: toggleValue(settings.show_item_tags, 0),
			headingFontSize: integerValue(settings.heading_font_size, DEFAULT_HEADING_FONT_SIZE),
			headingColor: String(settings.heading_color || "#172033"),
			itemTitleFontSize: integerValue(settings.item_title_font_size, 18),
			itemTitleColor: String(settings.item_title_color || "#172033"),
			justifiedRowHeight: 220,
			slideshowAutoplay: 1,
			slideshowShowArrows: toggleValue(settings.slideshow_show_arrows, 1),
			slideshowShowThumbs: toggleValue(settings.slideshow_show_thumbs, 1),
			slideshowInterval: 4000,
		};
		const presetId = String(settings.default_gallery_preset || "masonry-default");
		const preset = getGalleryPresets().find((entry) => entry.id === presetId);

		return preset ? { ...defaults, ...preset.settings } : defaults;
	}

	function displayTypeLabel(value) {
		switch (String(value || "grid")) {
			case "tile":
				return "Tile";
			case "mosaic":
				return "Mosaic";
			case "masonry":
				return "Masonry";
			case "justified":
				return "Justified";
			case "slideshow":
				return "Slideshow";
			case "filmstrip":
				return "Filmstrip";
			case "imagebrowser":
				return "Image Browser";
			default:
				return "Thumbnails Grid";
		}
	}

	function getGalleryPresets() {
		const settings = config.settings || {};

		return [
			{
				id: "masonry-default",
				label: "Masonry sem margem",
				description: "Masonry com canto reto, sem margem entre fotos e sem títulos ou descrições por padrão.",
				displayType: "masonry",
				settings: {
					displayType: "masonry",
					columnsDesktop: 4,
					columnsTablet: 3,
					columnsMobile: 2,
					cardGap: 0,
					roundedCorners: 0,
					enableFrontendFilters: 0,
					paginationEnabled: 1,
					itemsPerPage: 24,
					showHeading: 0,
					showDescription: 0,
					showTitles: 0,
					showCaptions: 0,
					showItemTags: 0,
				},
			},
			{
				id: "clean-grid",
				label: "Clean Grid",
				description: "Grade limpa para galerias grandes, com foco nas imagens.",
				displayType: "grid",
				settings: {
					displayType: "grid",
					columnsDesktop: 4,
					columnsTablet: 3,
					columnsMobile: 2,
					cardGap: integerValue(settings.card_gap, 0),
					roundedCorners: 1,
					enableFrontendFilters: 0,
					paginationEnabled: 1,
					itemsPerPage: 24,
					showTitles: 0,
					showCaptions: 0,
					showItemTags: 0,
				},
			},
			{
				id: "editorial-tile",
				label: "Editorial Tile",
				description: "Composicao mais premium para eventos, portfolio e destaque.",
				displayType: "tile",
				settings: {
					displayType: "tile",
					columnsDesktop: 4,
					columnsTablet: 2,
					columnsMobile: 1,
					cardGap: 16,
					roundedCorners: 1,
					enableFrontendFilters: 0,
					paginationEnabled: 1,
					itemsPerPage: 18,
					showTitles: 0,
					showCaptions: 0,
					showItemTags: 0,
				},
			},
			{
				id: "impact-mosaic",
				label: "Impact Mosaic",
				description: "Mosaico de alto impacto visual para home, landing e campanhas.",
				displayType: "mosaic",
				settings: {
					displayType: "mosaic",
					cardGap: 14,
					roundedCorners: 1,
					enableFrontendFilters: 0,
					paginationEnabled: 1,
					itemsPerPage: 20,
					showTitles: 0,
					showCaptions: 0,
					showItemTags: 0,
				},
			},
			{
				id: "story-justified",
				label: "Story Justified",
				description: "Linha editorial para acervo fotografico e cobertura de evento.",
				displayType: "justified",
				settings: {
					displayType: "justified",
					cardGap: 12,
					roundedCorners: 0,
					enableFrontendFilters: 0,
					paginationEnabled: 1,
					itemsPerPage: 30,
					showTitles: 0,
					showCaptions: 0,
					showItemTags: 0,
					justifiedRowHeight: 260,
				},
			},
			{
				id: "showcase-filmstrip",
				label: "Showcase Filmstrip",
				description: "Apresentacao guiada com destaque grande e trilha horizontal.",
				displayType: "filmstrip",
				settings: {
					displayType: "filmstrip",
					roundedCorners: 1,
					enableFrontendFilters: 0,
					paginationEnabled: 0,
					showTitles: 0,
					showCaptions: 0,
					showItemTags: 0,
					slideshowAutoplay: 0,
					slideshowShowArrows: 1,
					slideshowShowThumbs: 1,
					slideshowInterval: 4500,
				},
			},
		];
	}

	function applyGalleryPreset(presetId) {
		const preset = getGalleryPresets().find((entry) => entry.id === String(presetId || ""));

		if (!preset) {
			return;
		}

		state.editor.form = {
			...state.editor.form,
			...preset.settings,
		};

		markGalleryDirty();
		renderPage();
		showNotice(`Preset aplicado: ${preset.label}`);
	}

	function readFieldControlValue(target) {
		if (!isFieldControl(target)) {
			return "";
		}

		if (target instanceof HTMLInputElement && target.type === "checkbox") {
			return target.checked ? 1 : 0;
		}

		if (target instanceof HTMLInputElement && target.type === "number") {
			return target.value === "" ? "" : integerValue(target.value, 0);
		}

		return target.value;
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
			.then(async (response) => {
				const text = await response.text();
				let parsed = null;

				try {
					parsed = JSON.parse(text);
				} catch (parseError) {
					const error = new Error(config.strings.genericError || "Nao foi possivel concluir a operacao.");
					error.responseText = text;
					error.responseStatus = response.status;
					throw error;
				}

				return parsed;
			})
			.then((response) => {
				if (!response.success) {
					const error = new Error(response.data && response.data.message ? response.data.message : config.strings.genericError);
					error.responseData = response.data || null;
					error.lastEditor = response.data && response.data.editor ? response.data.editor : null;
					throw error;
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

	function extractGalleryForm(gallery) {
		const defaults = defaultGalleryDisplaySettings();
		const gallerySettings = gallery && gallery.settings ? gallery.settings : {};

		return {
			title: gallery && gallery.title ? String(gallery.title) : "",
			slug: gallery && gallery.slug ? String(gallery.slug) : "",
			status: gallery && gallery.status ? String(gallery.status) : "draft",
			description: gallery && gallery.description ? String(gallery.description) : "",
			publishedAt: gallery && gallery.published_at ? String(gallery.published_at) : "",
			createdAt: gallery && gallery.created_at ? String(gallery.created_at) : "",
			updatedAt: gallery && gallery.updated_at ? String(gallery.updated_at) : "",
			displayType: gallery && gallery.display_type ? String(gallery.display_type) : (defaults.displayType || "grid"),
			columnsDesktop: integerValue(gallerySettings.columns_desktop, defaults.columnsDesktop),
			columnsTablet: integerValue(gallerySettings.columns_tablet, defaults.columnsTablet),
			columnsMobile: integerValue(gallerySettings.columns_mobile, defaults.columnsMobile),
			cardGap: integerValue(gallerySettings.card_gap, defaults.cardGap),
			roundedCorners: toggleValue(gallerySettings.rounded_corners, defaults.roundedCorners),
			enableFrontendFilters: toggleValue(gallerySettings.enable_frontend_filters, defaults.enableFrontendFilters),
			paginationEnabled: toggleValue(gallerySettings.pagination_enabled, defaults.paginationEnabled),
			itemsPerPage: integerValue(gallerySettings.items_per_page, defaults.itemsPerPage),
			showHeading: toggleValue(gallerySettings.show_heading, defaults.showHeading),
			showDescription: toggleValue(gallerySettings.show_description, defaults.showDescription),
			showTitles: toggleValue(gallerySettings.show_titles, defaults.showTitles),
			showCaptions: toggleValue(gallerySettings.show_captions, defaults.showCaptions),
			showItemTags: toggleValue(gallerySettings.show_item_tags, defaults.showItemTags),
			headingFontSize: integerValue(gallerySettings.heading_font_size, defaults.headingFontSize),
			headingColor: String(gallerySettings.heading_color || defaults.headingColor),
			itemTitleFontSize: integerValue(gallerySettings.item_title_font_size, defaults.itemTitleFontSize),
			itemTitleColor: String(gallerySettings.item_title_color || defaults.itemTitleColor),
			justifiedRowHeight: integerValue(gallerySettings.justified_row_height, defaults.justifiedRowHeight),
			slideshowAutoplay: toggleValue(gallerySettings.slideshow_autoplay, defaults.slideshowAutoplay),
			slideshowShowArrows: toggleValue(gallerySettings.slideshow_show_arrows, defaults.slideshowShowArrows),
			slideshowShowThumbs: toggleValue(gallerySettings.slideshow_show_thumbs, defaults.slideshowShowThumbs),
			slideshowInterval: integerValue(gallerySettings.slideshow_interval, defaults.slideshowInterval),
		};
	}

	function hasPendingChanges() {
		return !!state.editor.galleryDirty || !!state.editor.itemsDirty;
	}

	function getSelectedItemIds() {
		return Array.from(
			new Set(
				(state.editor.selectedItemIds || [])
					.map((itemId) => Number(itemId || 0))
					.filter((itemId) => itemId > 0)
			)
		);
	}

	function setSelectedItemIds(itemIds) {
		const validIds = (state.editor.items || []).map((item) => Number(item.id || 0));

		state.editor.selectedItemIds = Array.from(
			new Set(
				(itemIds || [])
					.map((itemId) => Number(itemId || 0))
				.filter((itemId) => itemId > 0 && validIds.includes(itemId))
			)
		);
	}

	function normalizeItemTags(item) {
		const rawTags = Array.isArray(item.tag_list) && item.tag_list.length
			? item.tag_list
			: String(item.item_tags || "").split(/[\r\n,;|]+/);
		const seen = new Set();

		return rawTags
			.map((tag) => String(tag || "").trim())
			.filter((tag) => {
				const key = tag.toLowerCase();

				if (!tag || seen.has(key)) {
					return false;
				}

				seen.add(key);
				return true;
			});
	}

	function getAvailableItemTags() {
		return Array.from(
			new Set(
				(state.editor.items || []).flatMap((item) =>
					normalizeItemTags(item).map((tag) => String(tag).toLowerCase())
				)
			)
		)
			.sort()
			.map((tag) => ({
				value: tag,
				label: tag
					.split("-")
					.join(" ")
					.replace(/\b\w/g, (letter) => letter.toUpperCase()),
			}));
	}

	function itemMatchesFilters(item) {
		const search = String(state.itemFilters.search || "").trim().toLowerCase();
		const visibility = String(state.itemFilters.visibility || "all");
		const tag = String(state.itemFilters.tag || "").trim().toLowerCase();
		const itemTags = normalizeItemTags(item).map((currentTag) => currentTag.toLowerCase());

		if (visibility === "visible" && !Number(item.is_visible || 0)) {
			return false;
		}

		if (visibility === "hidden" && Number(item.is_visible || 0)) {
			return false;
		}

		if (tag && !itemTags.includes(tag)) {
			return false;
		}

		if (!search) {
			return true;
		}

		const attachment = item.attachment || {};
		const haystack = [
			item.item_title || "",
			item.item_caption || "",
			item.item_alt || "",
			item.item_link || "",
			item.item_tags || "",
			attachment.title || "",
			attachment.filename || "",
		]
			.join(" ")
			.toLowerCase();

		return haystack.includes(search);
	}

	function getFilteredEditorItems() {
		return (state.editor.items || []).filter(itemMatchesFilters);
	}

	function clearItemFilters() {
		state.itemFilters = {
			search: "",
			visibility: "all",
			tag: "",
		};
	}

	function getServerRootMeta(value) {
		const currentValue = String(value || "");

		return getServerImportRoots().find((rootItem) => String(rootItem.value || "") === currentValue) || getServerImportRoots()[0] || null;
	}

	function normalizeServerPath(value) {
		return String(value || "").trim().replaceAll("\\", "/").replace(/^\/+/, "").replace(/\/+$/, "");
	}

	function hasServerImport() {
		return getServerImportRoots().length > 0;
	}

	function renderServerImportFields(context) {
		const isCreateContext = context === "create";
		const rootValue = isCreateContext ? state.createModal.serverRoot : state.upload.serverRoot;
		const pathValue = isCreateContext ? state.createModal.serverPath : state.upload.serverPath;
		const rootMeta = getServerRootMeta(rootValue);
		const rootOptions = getServerImportRoots()
			.map((rootItem) => `<option value="${escapeHtml(rootItem.value || "")}" ${String(rootItem.value || "") === String(rootValue || "") ? "selected" : ""}>${escapeHtml(rootItem.label || rootItem.value || "")}</option>`)
			.join("");

		if (!hasServerImport()) {
			return "";
		}

		return `
			<div class="mlgp-server-import">
				<div class="mlgp-server-import__header">
					<strong>${escapeHtml(config.strings.serverImportAction || "Pasta do servidor")}</strong>
					<p>${escapeHtml(config.strings.serverImportHint || "Importar imagens de uma pasta ja existente no servidor")}</p>
				</div>
				<div class="mlgp-server-import__grid">
					<div class="mlgp-field">
						<label for="${isCreateContext ? "mlgp-create-server-root" : "mlgp-upload-server-root"}">${escapeHtml(config.strings.serverImportRootLabel || "Raiz autorizada")}</label>
						<select id="${isCreateContext ? "mlgp-create-server-root" : "mlgp-upload-server-root"}" ${isCreateContext ? 'data-mlgp-create-server-root="1"' : 'data-mlgp-upload-server-root="1"'}>
							${rootOptions}
						</select>
					</div>
					<div class="mlgp-field">
						<label for="${isCreateContext ? "mlgp-create-server-path" : "mlgp-upload-server-path"}">${escapeHtml(config.strings.serverImportPathLabel || "Pasta relativa")}</label>
						<input id="${isCreateContext ? "mlgp-create-server-path" : "mlgp-upload-server-path"}" type="text" value="${escapeHtml(pathValue || "")}" placeholder="${escapeHtml((rootMeta && rootMeta.example) || config.strings.serverImportPathPlaceholder || "clientes/evento-a")}" ${isCreateContext ? 'data-mlgp-create-server-path="1"' : 'data-mlgp-upload-server-path="1"'}>
					</div>
					<div class="mlgp-actions">
						${isCreateContext ? "" : `<button type="button" class="mlgp-button mlgp-button--accent" data-mlgp-import-server="1" ${state.upload.uploading ? "disabled" : ""}>${escapeHtml(config.strings.serverImportButton || "Importar pasta do servidor")}</button>`}
					</div>
				</div>
				<div class="mlgp-list__meta">
					<span>${escapeHtml(config.strings.serverImportPathHint || "Informe a pasta relativa dentro da raiz selecionada.")}</span>
					${rootMeta && rootMeta.path ? `<span>${escapeHtml(rootMeta.path)}</span>` : ""}
				</div>
			</div>
		`;
	}

	function hasCreateSelection() {
		return !!(
			state.createModal.zipFile ||
			state.createModal.files.length ||
			(state.createModal.source === "server" && normalizeServerPath(state.createModal.serverPath || ""))
		);
	}

	function getCreateSelectionCount() {
		if (state.createModal.source === "server" && normalizeServerPath(state.createModal.serverPath || "")) {
			return 1;
		}

		if (state.createModal.zipFile) {
			return 1;
		}

		return state.createModal.files.length;
	}

	function isImageFile(file) {
		if (!file) {
			return false;
		}

		return String(file.type || "").startsWith("image/") || /\.(avif|bmp|gif|heic|jpeg|jpg|png|svg|tif|tiff|webp)$/i.test(String(file.name || ""));
	}

	function syncEditor(editor, options) {
		const settings = options || {};

		if (!editor || !editor.gallery) {
			state.editor = emptyEditorState();
			state.upload = emptyUploadState();
			state.bulkTab = "titles";
			state.bulkTitle = "";
			state.bulkAlt = "";
			state.bulkCaption = "";
			state.bulkTags = "";
			return;
		}

		const incomingItems = Array.isArray(editor.items) ? editor.items : [];
		const incomingIds = incomingItems.map((item) => Number(item.id || 0));
		const preserveGalleryDraft =
			!!settings.preserveGalleryDraft &&
			state.editor.galleryDirty &&
			Number(state.editor.activeId) === Number(editor.gallery.id || 0);
		const preserveSelection =
			!!settings.preserveSelection &&
			Number(state.editor.activeId) === Number(editor.gallery.id || 0);

		state.editor = {
			activeId: Number(editor.gallery.id || 0),
			gallery: editor.gallery,
			form: preserveGalleryDraft ? { ...state.editor.form } : extractGalleryForm(editor.gallery),
			items: incomingItems,
			coverItemId: Number(editor.gallery.cover_item_id || 0),
			selectedItemIds: preserveSelection ? getSelectedItemIds().filter((itemId) => incomingIds.includes(itemId)) : [],
			galleryDirty: preserveGalleryDraft,
			itemsDirty: false,
		};
		const nextUpload = emptyUploadState();
		nextUpload.serverRoot = state.upload.serverRoot || nextUpload.serverRoot;
		nextUpload.serverPath = state.upload.serverPath || "";
		state.upload = nextUpload;
		state.bulkTab = "titles";
		state.bulkTitle = "";
		state.bulkAlt = "";
		state.bulkCaption = "";
		state.bulkTags = "";
	}

	function updateDirtyUi() {
		const badge = root.querySelector("[data-mlgp-editor-status]");
		const saveButton = root.querySelector("[data-mlgp-save-all]");
		const dirty = hasPendingChanges();

		if (badge) {
			let label = "Sincronizado";

			if (state.editor.galleryDirty && state.editor.itemsDirty) {
				label = "Galeria e imagens pendentes";
			} else if (state.editor.galleryDirty) {
				label = "Dados da galeria pendentes";
			} else if (state.editor.itemsDirty) {
				label = "Imagens pendentes";
			}

			badge.textContent = label;
			badge.className = dirty ? "mlgp-status-pill is-warning" : "mlgp-status-pill is-clean";
		}

		if (saveButton) {
			saveButton.disabled = !dirty;
		}
	}

	function markGalleryDirty() {
		state.editor.galleryDirty = true;
		updateDirtyUi();
	}

	function markItemsDirty() {
		state.editor.itemsDirty = true;
		updateDirtyUi();
	}

	function setUploadState(nextState) {
		state.upload = {
			...state.upload,
			...(nextState || {}),
		};
		updateUploadUi();
	}

	function updateUploadUi() {
		const progressRoot = root.querySelector("[data-mlgp-upload-progress]");
		const fill = root.querySelector("[data-mlgp-upload-progress-fill]");
		const label = root.querySelector("[data-mlgp-upload-status]");
		const buttons = root.querySelectorAll("[data-mlgp-open-upload]");

		if (progressRoot) {
			const shouldShow = state.upload.uploading || state.upload.progress > 0;
			progressRoot.hidden = !shouldShow;
		}

		if (fill) {
			fill.style.width = `${Math.max(0, Math.min(100, Number(state.upload.progress || 0)))}%`;
		}

		if (label) {
			label.textContent = state.upload.message || "";
		}

		buttons.forEach((button) => {
			button.disabled = !!state.upload.uploading;
		});
	}

	function setCreateModalState(nextState) {
		state.createModal = {
			...state.createModal,
			...(nextState || {}),
		};
		updateCreateModalUi();
	}

	function resetCreateModal() {
		state.createModal = emptyCreateModalState();
	}

	function updateCreateModalUi() {
		const progressRoot = root.querySelector("[data-mlgp-create-progress]");
		const fill = root.querySelector("[data-mlgp-create-progress-fill]");
		const label = root.querySelector("[data-mlgp-create-status]");
		const submitButton = root.querySelector("[data-mlgp-submit-create-gallery]");
		const closeButtons = root.querySelectorAll("[data-mlgp-close-create-modal]");
		const sourceButtons = root.querySelectorAll("[data-mlgp-open-create-source]");

		if (progressRoot) {
			const shouldShow = state.createModal.submitting || state.createModal.progress > 0;
			progressRoot.hidden = !shouldShow;
		}

		if (fill) {
			fill.style.width = `${Math.max(0, Math.min(100, Number(state.createModal.progress || 0)))}%`;
		}

		if (label) {
			label.textContent = state.createModal.message || "";
		}

		if (submitButton) {
			submitButton.disabled = !!state.createModal.submitting;
			submitButton.textContent = getCreateSubmitLabel();
		}

		closeButtons.forEach((button) => {
			if (button instanceof HTMLButtonElement) {
				button.disabled = !!state.createModal.submitting;
			}
		});

		sourceButtons.forEach((button) => {
			if (button instanceof HTMLButtonElement) {
				button.disabled = !!state.createModal.submitting;
			}
		});
	}


	function galleryPublicUrl(item) {
		if (item && item.public_url) {
			return String(item.public_url);
		}

		const slug = String((item && item.slug) || "").trim();

		if (!slug || !config.siteUrl) {
			return "";
		}

		return `${String(config.siteUrl).replace(/\/$/, "")}/galeria/${encodeURIComponent(slug)}/`;
	}

	function galleryShortcode(item) {
		if (item && item.shortcode) {
			return String(item.shortcode);
		}

		return item && item.id ? `[ml_gallery id="${Number(item.id)}"]` : "";
	}

	function upsertGallerySummaryFromEditor(editor) {
		if (!editor || !editor.gallery) {
			return;
		}

		const galleryId = Number(editor.gallery.id || 0);

		if (!galleryId) {
			return;
		}

		const summary = {
			...editor.gallery,
			id: galleryId,
			item_count: Array.isArray(editor.items) ? editor.items.length : Number(editor.gallery.item_count || 0),
			cover: editor.gallery.cover || null,
			shortcode: galleryShortcode(editor.gallery),
		};
		const existingIndex = state.galleries.findIndex((item) => Number(item.id || 0) === galleryId);

		if (existingIndex >= 0) {
			state.galleries.splice(existingIndex, 1, {
				...state.galleries[existingIndex],
				...summary,
			});
			return;
		}

		state.galleries.unshift(summary);
	}

	function serializeGallerySettings() {
		return {
			columns_desktop: Math.max(1, Math.min(8, integerValue(state.editor.form.columnsDesktop, 4))),
			columns_tablet: Math.max(1, Math.min(6, integerValue(state.editor.form.columnsTablet, 3))),
			columns_mobile: Math.max(1, Math.min(4, integerValue(state.editor.form.columnsMobile, 2))),
			card_gap: Math.max(0, Math.min(48, integerValue(state.editor.form.cardGap, 18))),
			rounded_corners: toggleValue(state.editor.form.roundedCorners, 1),
			enable_frontend_filters: toggleValue(state.editor.form.enableFrontendFilters, 0),
			pagination_enabled: toggleValue(state.editor.form.paginationEnabled, 1),
			items_per_page: Math.max(1, Math.min(5000, integerValue(state.editor.form.itemsPerPage, 24))),
			show_heading: toggleValue(state.editor.form.showHeading, 1),
			show_description: toggleValue(state.editor.form.showDescription, 1),
			show_titles: toggleValue(state.editor.form.showTitles, 0),
			show_captions: toggleValue(state.editor.form.showCaptions, 0),
			show_item_tags: toggleValue(state.editor.form.showItemTags, 0),
			heading_font_size: Math.max(20, Math.min(96, integerValue(state.editor.form.headingFontSize, DEFAULT_HEADING_FONT_SIZE))),
			heading_color: String(state.editor.form.headingColor || "#172033"),
			item_title_font_size: Math.max(10, Math.min(48, integerValue(state.editor.form.itemTitleFontSize, 18))),
			item_title_color: String(state.editor.form.itemTitleColor || "#172033"),
			justified_row_height: Math.max(120, Math.min(520, integerValue(state.editor.form.justifiedRowHeight, 220))),
			slideshow_autoplay: toggleValue(state.editor.form.slideshowAutoplay, 1),
			slideshow_show_arrows: toggleValue(state.editor.form.slideshowShowArrows, 1),
			slideshow_show_thumbs: toggleValue(state.editor.form.slideshowShowThumbs, 1),
			slideshow_interval: Math.max(1500, Math.min(20000, integerValue(state.editor.form.slideshowInterval, 4000))),
		};
	}

	function getCreateSubmitLabel() {
		const hasZip = !!state.createModal.zipFile;
		const hasFiles = state.createModal.files.length > 0;
		const hasServer = state.createModal.source === "server" && !!normalizeServerPath(state.createModal.serverPath || "");

		if (state.createModal.submitting) {
			if (hasServer) {
				return String(config.strings.creatingGalleryServer || "Criando galeria e importando pasta do servidor...");
			}

			if (hasZip) {
				return String(config.strings.creatingGalleryZip || "Criando galeria e importando ZIP...");
			}

			return hasFiles
				? String(config.strings.creatingGalleryUpload || "Criando galeria e enviando imagens...")
				: String(config.strings.creatingGallery || "Criando galeria...");
		}

		if (hasServer) {
			return String(config.strings.createGalleryAndImportServerAction || "Criar galeria e importar pasta do servidor");
		}

		if (hasZip) {
			return String(config.strings.createGalleryAndImportZipAction || "Criar galeria e importar ZIP");
		}

		return hasFiles
			? String(config.strings.createGalleryAndUploadAction || "Criar galeria e enviar imagens")
			: String(config.strings.createEmptyGalleryAction || "Criar galeria vazia");
	}

	function galleryMatchesSearch(item) {
		if (!state.searchTerm) {
			return true;
		}

		const needle = state.searchTerm.trim().toLowerCase();
		const haystack = [
			item.title || "",
			item.slug || "",
			galleryShortcode(item),
			String(item.id || ""),
		]
			.join(" ")
			.toLowerCase();

		return haystack.includes(needle);
	}

	function getFilteredGalleries() {
		return state.galleries.filter(galleryMatchesSearch);
	}

	function getTotals() {
		return state.galleries.reduce(
			(accumulator, item) => {
				accumulator.galleries += 1;
				accumulator.images += Number(item.item_count || 0);

				if (String(item.status || "") === "publish") {
					accumulator.published += 1;
				}

				return accumulator;
			},
			{
				galleries: 0,
				images: 0,
				published: 0,
			}
		);
	}

	function renderMetricCard(label, value, detail, variant = "number") {
		const isStorage = variant === "storage";

		return `
			<article class="mlgp-stat ${isStorage ? "mlgp-stat--storage" : ""}">
				<span class="mlgp-stat__label">${escapeHtml(label)}</span>
				${isStorage
					? `<div class="mlgp-stat__value mlgp-stat__value--storage"><span class="mlgp-soft-code mlgp-soft-code--path">${escapeHtml(value)}</span></div>`
					: `<div class="mlgp-stat__value">${escapeHtml(value)}</div>`}
				${detail ? `<p class="mlgp-stat__detail">${escapeHtml(detail)}</p>` : ""}
			</article>
		`;
	}

	function renderGalleryRow(item) {
		const isActive = Number(item.id) === Number(state.editor.activeId);
		const cover = item.cover && item.cover.thumb_url
			? `<div class="mlgp-gallery-row__thumb"><img src="${escapeHtml(item.cover.thumb_url)}" alt="${escapeHtml(item.title || "")}"></div>`
			: `<div class="mlgp-gallery-row__thumb is-empty">${Number(item.item_count || 0) ? "IMG" : "SEM"}</div>`;
		const itemCount = Number(item.item_count || 0);
		const shortcode = galleryShortcode(item);
		const publicUrl = galleryPublicUrl(item);
		const status = String(item.status || "draft").toLowerCase();
		const statusLabel = status === "publish" || status === "published"
			? "Publicado"
			: (status === "private" ? "Privado" : "Rascunho");

		return `
			<div class="mlgp-list__item mlgp-gallery-row ${isActive ? "is-active" : ""}">
				${cover}
				<div class="mlgp-gallery-row__main">
					<h3>${escapeHtml(item.title || "Galeria sem titulo")}</h3>
					<span class="mlgp-pill">${escapeHtml(statusLabel)}</span>
				</div>
				<div class="mlgp-gallery-row__meta">
					<div class="mlgp-gallery-row__slug">slug: ${escapeHtml(item.slug || "")}</div>
					<div class="mlgp-gallery-row__count">${itemCount} ${itemCount === 1 ? "imagem" : "imagens"}</div>
					<div class="mlgp-gallery-row__shortcode">${renderCopyableShortcode(shortcode)}</div>
					${publicUrl ? `<div class="mlgp-gallery-row__url">${renderCopyableShortcode(publicUrl)}</div>` : ""}
				</div>
				<div class="mlgp-gallery-row__actions">
					<button type="button" class="mlgp-button mlgp-button--accent" data-mlgp-open-editor="${Number(item.id || 0)}">Editar</button>
					<button type="button" class="mlgp-button mlgp-button--danger" data-mlgp-delete-gallery="${Number(item.id || 0)}">Excluir</button>
				</div>
			</div>
		`;
	}

	function renderItemCard(item, index) {
		const attachment = item.attachment || {};
		const selected = getSelectedItemIds().includes(Number(item.id || 0));

		return `
			<article class="mlgp-media-card ${item.is_cover ? "is-cover" : ""} ${selected ? "is-selected" : ""}" data-item-id="${item.id}" data-mlgp-drop-item="${item.id}">
				<div class="mlgp-media-card__media">
					${attachment.thumb_url ? `<img src="${escapeHtml(attachment.thumb_url)}" alt="${escapeHtml(item.item_alt || attachment.alt || attachment.title || "")}">` : '<div class="mlgp-media-card__placeholder">Sem preview</div>'}
					<button type="button" class="mlgp-cover-chip ${item.is_cover ? "is-active" : ""}" data-mlgp-set-cover="${item.id}">${item.is_cover ? "Capa ativa" : "Definir capa"}</button>
				</div>
				<div class="mlgp-media-card__content">
					<div class="mlgp-media-card__topbar">
						<div class="mlgp-media-card__header">
							<strong>${escapeHtml(attachment.title || item.item_title || `Imagem ${index + 1}`)}</strong>
							<small>${escapeHtml(attachment.filename || "")}</small>
						</div>
						<div class="mlgp-media-card__topmeta">
							<label class="mlgp-check mlgp-check--compact">
								<input type="checkbox" ${selected ? "checked" : ""} data-mlgp-select-item="${item.id}">
								<span>Selecionar</span>
							</label>
							<span class="mlgp-media-card__order">#${index + 1}</span>
						</div>
					</div>
					<div class="mlgp-media-card__fields">
						<div class="mlgp-field mlgp-media-card__field--title">
							<label>Titulo</label>
							<input type="text" value="${escapeHtml(item.item_title || "")}" data-mlgp-item-field="item_title" data-item-id="${item.id}">
						</div>
						<div class="mlgp-field">
							<label>Alt</label>
							<input type="text" value="${escapeHtml(item.item_alt || "")}" data-mlgp-item-field="item_alt" data-item-id="${item.id}">
						</div>
						<div class="mlgp-field">
							<label>Link</label>
							<input type="text" value="${escapeHtml(item.item_link || "")}" data-mlgp-item-field="item_link" data-item-id="${item.id}" placeholder="https://">
						</div>
						<div class="mlgp-field mlgp-media-card__field--caption">
							<label>Legenda</label>
							<textarea data-mlgp-item-field="item_caption" data-item-id="${item.id}">${escapeHtml(item.item_caption || "")}</textarea>
						</div>
						<div class="mlgp-field mlgp-media-card__field--tags">
							<label>Tags</label>
							<input type="text" value="${escapeHtml(item.item_tags || "")}" data-mlgp-item-field="item_tags" data-item-id="${item.id}" placeholder="evento, retrato, destaque">
						</div>
						<label class="mlgp-check mlgp-media-card__visibility">
							<input type="checkbox" ${Number(item.is_visible || 0) ? "checked" : ""} data-mlgp-item-field="is_visible" data-item-id="${item.id}">
							<span>Visivel no frontend</span>
						</label>
					</div>
				</div>
				<div class="mlgp-media-card__aside">
					<div class="mlgp-actions mlgp-actions--vertical mlgp-actions--compact">
						<span class="mlgp-drag-handle" draggable="true" data-mlgp-drag-handle="${item.id}" title="Arraste para reordenar">&#9776;</span>
						<button type="button" class="mlgp-icon-button" data-mlgp-move-item="${item.id}" data-direction="up">&uarr;</button>
						<button type="button" class="mlgp-icon-button" data-mlgp-move-item="${item.id}" data-direction="down">&darr;</button>
						<button type="button" class="mlgp-icon-button is-danger" data-mlgp-remove-item="${item.id}">&times;</button>
					</div>
				</div>
			</article>
		`;
	}

	function renderItemCardVisual(item, index) {
		const attachment = item.attachment || {};
		const selected = getSelectedItemIds().includes(Number(item.id || 0));
		const isCover = !!item.is_cover;
		const w = Number(attachment.width || 0);
		const h = Number(attachment.height || 0);
		const orientation = w && h ? (w >= h ? "H" : "V") : "";
		const thumbUrl = attachment.thumb_url || attachment.file_url || "";

		return `
			<article class="mlgp-thumb-card ${isCover ? "is-cover" : ""} ${selected ? "is-selected" : ""}" data-item-id="${item.id}" data-mlgp-drop-item="${item.id}">
				<div class="mlgp-thumb-card__img">
					${thumbUrl ? `<img src="${escapeHtml(thumbUrl)}" alt="${escapeHtml(item.item_alt || attachment.alt || "")}" loading="lazy">` : '<div class="mlgp-thumb-card__placeholder">Sem preview</div>'}
					${isCover ? '<span class="mlgp-thumb-card__badge">Capa</span>' : ""}
					${orientation ? `<span class="mlgp-thumb-card__orient">${orientation}</span>` : ""}
					<div class="mlgp-thumb-card__overlay">
						<button type="button" class="mlgp-thumb-card__action" data-mlgp-set-cover="${item.id}" title="Definir como capa">&#9733;</button>
						<button type="button" class="mlgp-thumb-card__action" data-mlgp-remove-item="${item.id}" title="Excluir">&times;</button>
						<label class="mlgp-thumb-card__select">
							<input type="checkbox" ${selected ? "checked" : ""} data-mlgp-select-item="${item.id}">
						</label>
					</div>
				</div>
				<span class="mlgp-thumb-card__name" draggable="true" data-mlgp-drag-handle="${item.id}">${escapeHtml(item.item_title || attachment.title || `#${index + 1}`)}</span>
			</article>
		`;
	}

	function getBackUrl() {
		return String((config.pageUrls && config.pageUrls.galleries) || "#");
	}

	function getViewMode() {
		return state.editor.viewMode || "visual";
	}

	function renderCreateFilesList() {
		if (state.createModal.zipFile) {
			return `
				<div class="mlgp-file-chip-list">
					<span class="mlgp-file-chip is-more">${escapeHtml(state.createModal.zipFile.name || "arquivo.zip")}</span>
				</div>
			`;
		}

		if (!state.createModal.files.length) {
			return `<div class="mlgp-create-modal__files-empty">Nenhum arquivo selecionado ainda.</div>`;
		}

		const files = state.createModal.files
			.slice(0, 6)
			.map((file) => `<span class="mlgp-file-chip">${escapeHtml(file.name)}</span>`)
			.join("");
		const remaining = state.createModal.files.length - 6;

		return `
			<div class="mlgp-file-chip-list">
				${files}
				${remaining > 0 ? `<span class="mlgp-file-chip is-more">+${remaining}</span>` : ""}
			</div>
		`;
	}

	function renderCreateModal() {
		const isZipSource = state.createModal.source === "zip";
		const isFolderSource = state.createModal.source === "folder";
		const isServerSource = state.createModal.source === "server";
		const sourceLabel = isZipSource
			? String(config.strings.importZipAction || "Importar ZIP")
			: (isFolderSource
				? String(config.strings.importFolderAction || "Importar pasta")
				: (isServerSource ? String(config.strings.serverImportAction || "Pasta do servidor") : String(config.strings.computerUploadAction || "Computador")));
		const dropTitle = isZipSource
			? String(config.strings.dropZipHint || "Arraste um arquivo ZIP ou clique para selecionar")
			: (isFolderSource ? String(config.strings.dropFolderHint || "Selecione uma pasta local com imagens") : String(config.strings.dropFilesHint || "Arraste imagens ou clique para selecionar"));
		const sourceDescription = isZipSource
			? String(config.strings.importZipHint || "Crie a galeria a partir de um arquivo ZIP")
			: (isFolderSource ? String(config.strings.importFolderHint || "Suba uma pasta inteira de imagens") : String(config.strings.computerUploadHint || "Selecionar imagens avulsas"));

		return `
			<div class="mlgp-modal-backdrop" data-mlgp-close-create-modal="1">
				<div class="mlgp-modal mlgp-create-modal" role="dialog" aria-modal="true" aria-labelledby="mlgp-create-modal-title">
					<div class="mlgp-modal__header">
						<div>
							<h2 id="mlgp-create-modal-title">${escapeHtml(config.strings.newGalleryTitle || "Adicionar nova galeria")}</h2>
							<p>${escapeHtml(config.strings.newGalleryDescription || "")}</p>
						</div>
						<button type="button" class="mlgp-modal__close" data-mlgp-close-create-modal="1" aria-label="Fechar">&times;</button>
					</div>
					<div class="mlgp-modal__body">
						<div class="mlgp-field">
							<label for="mlgp-create-gallery-title">Titulo da galeria</label>
							<input id="mlgp-create-gallery-title" type="text" value="${escapeHtml(state.createModal.title)}" data-mlgp-create-field="title" placeholder="Ex.: Ensaio Samba Oke">
						</div>
						<div class="mlgp-modal__grid">
							<div class="mlgp-field">
								<label for="mlgp-create-gallery-slug">Slug</label>
								<input id="mlgp-create-gallery-slug" type="text" value="${escapeHtml(state.createModal.slug)}" data-mlgp-create-field="slug" placeholder="gerado-automaticamente">
							</div>
							<div class="mlgp-field">
								<label for="mlgp-create-gallery-status">Status</label>
								<select id="mlgp-create-gallery-status" data-mlgp-create-field="status">
									<option value="draft" ${state.createModal.status === "draft" ? "selected" : ""}>Rascunho</option>
									<option value="publish" ${state.createModal.status === "publish" ? "selected" : ""}>Publicado</option>
									<option value="private" ${state.createModal.status === "private" ? "selected" : ""}>Privado</option>
								</select>
							</div>
						</div>
						<div class="mlgp-field">
							<label for="mlgp-create-gallery-description">Descricao</label>
							<textarea id="mlgp-create-gallery-description" data-mlgp-create-field="description" placeholder="Opcional">${escapeHtml(state.createModal.description)}</textarea>
						</div>
						<div class="mlgp-source-grid">
							<button type="button" class="mlgp-source-card ${state.createModal.source === "files" ? "is-active" : ""}" data-mlgp-open-create-source="files">
								<strong>${escapeHtml(config.strings.computerUploadAction || "Computador")}</strong>
								<span>${escapeHtml(config.strings.computerUploadHint || "Selecionar imagens avulsas")}</span>
							</button>
							<button type="button" class="mlgp-source-card ${state.createModal.source === "folder" ? "is-active" : ""}" data-mlgp-open-create-source="folder">
								<strong>${escapeHtml(config.strings.importFolderAction || "Importar pasta")}</strong>
								<span>${escapeHtml(config.strings.importFolderHint || "Ler imagens de uma pasta local")}</span>
							</button>
							<button type="button" class="mlgp-source-card ${state.createModal.source === "zip" ? "is-active" : ""}" data-mlgp-open-create-source="zip">
								<strong>${escapeHtml(config.strings.importZipAction || "Importar ZIP")}</strong>
								<span>${escapeHtml(config.strings.importZipHint || "Criar a galeria a partir de um ZIP")}</span>
							</button>
							${hasServerImport() ? `
								<button type="button" class="mlgp-source-card ${state.createModal.source === "server" ? "is-active" : ""}" data-mlgp-open-create-source="server">
									<strong>${escapeHtml(config.strings.serverImportAction || "Pasta do servidor")}</strong>
									<span>${escapeHtml(config.strings.serverImportHint || "Importar imagens de uma pasta ja existente no servidor")}</span>
								</button>
							` : `
								<button type="button" class="mlgp-source-card is-disabled" disabled>
									<strong>Links externos</strong>
									<span>${escapeHtml(config.strings.comingSoon || "Em breve")}</span>
								</button>
							`}
						</div>
						${isServerSource
							? renderServerImportFields("create")
							: `
								<div class="mlgp-next-dropzone" data-mlgp-create-dropzone="1" data-mlgp-create-dropzone-type="${escapeHtml(state.createModal.source)}">
									<input id="mlgp-create-gallery-upload-input" type="file" multiple accept="image/*" hidden>
									<input id="mlgp-create-gallery-folder-input" type="file" multiple accept="image/*" webkitdirectory directory hidden>
									<input id="mlgp-create-gallery-zip-input" type="file" accept=".zip,application/zip" hidden>
									<div class="mlgp-next-dropzone__icon" aria-hidden="true">&#8682;</div>
									<strong>${escapeHtml(dropTitle)}</strong>
									<p>${escapeHtml(sourceDescription)}</p>
									<p class="mlgp-next-dropzone__path">Diretorio: ${escapeHtml(config.storageLabel || "wp-content/ml-gallery")}</p>
									${renderCreateFilesList()}
								</div>
							`}
						<div class="mlgp-upload-progress" data-mlgp-create-progress ${state.createModal.submitting || state.createModal.progress > 0 ? "" : "hidden"}>
							<div class="mlgp-upload-progress__bar">
								<span data-mlgp-create-progress-fill style="width:${Math.max(0, Math.min(100, Number(state.createModal.progress || 0)))}%"></span>
							</div>
							<div class="mlgp-upload-progress__text" data-mlgp-create-status>${escapeHtml(state.createModal.message || "")}</div>
						</div>
					</div>
					<div class="mlgp-modal__footer">
						<div class="mlgp-modal__footer-meta">${isServerSource
							? `${escapeHtml(sourceLabel)}: ${escapeHtml(normalizeServerPath(state.createModal.serverPath) || "Informe a pasta relativa")}`
							: (hasCreateSelection() ? `${escapeHtml(sourceLabel)}: ${getCreateSelectionCount()} ${escapeHtml(config.strings.imagesSelected || "arquivo(s) selecionado(s)")}` : "Voce pode criar a galeria mesmo sem imagens.")}</div>
						<div class="mlgp-actions">
							<button type="button" class="mlgp-button mlgp-button--ghost" data-mlgp-close-create-modal="1">Cancelar</button>
							<button type="button" class="mlgp-button mlgp-button--accent" data-mlgp-submit-create-gallery="1">${escapeHtml(getCreateSubmitLabel())}</button>
						</div>
					</div>
				</div>
			</div>
		`;
	}

	function renderAddImagesPage() {
		const isZipSource = state.createModal.source === "zip";
		const isFolderSource = state.createModal.source === "folder";
		const isServerSource = state.createModal.source === "server";
		const sourceLabel = isZipSource
			? String(config.strings.importZipAction || "Importar ZIP")
			: (isFolderSource
				? String(config.strings.importFolderAction || "Importar pasta")
				: (isServerSource ? String(config.strings.serverImportAction || "Pasta do servidor") : String(config.strings.computerUploadAction || "Computador")));
		const dropTitle = isZipSource
			? String(config.strings.dropZipHint || "Arraste um arquivo ZIP ou clique para selecionar")
			: (isFolderSource ? String(config.strings.dropFolderHint || "Selecione uma pasta local com imagens") : String(config.strings.dropFilesHint || "Arraste imagens ou clique para selecionar"));
		const sourceDescription = isZipSource
			? String(config.strings.importZipHint || "Crie a galeria a partir de um arquivo ZIP")
			: (isFolderSource ? String(config.strings.importFolderHint || "Suba uma pasta inteira de imagens") : String(config.strings.computerUploadHint || "Selecionar imagens avulsas"));
		const galleryUrl = state.editor.activeId
			? `${String((config.pageUrls && config.pageUrls.galleries) || "#")}&gallery_id=${Number(state.editor.activeId || 0)}`
			: String((config.pageUrls && config.pageUrls.galleries) || "#");
		const activeShortcode = state.editor.gallery ? galleryShortcode(state.editor.gallery) : "";
		const statusLabel = state.createModal.status === "publish"
			? "Publicado"
			: (state.createModal.status === "private" ? "Privado" : "Rascunho");
		const sourceSummary = isServerSource
			? (normalizeServerPath(state.createModal.serverPath) || "Informe a pasta relativa")
			: (hasCreateSelection() ? `${getCreateSelectionCount()} ${String(config.strings.imagesSelected || "arquivo(s) selecionado(s)")}` : "Aguardando arquivos para envio");

		return `
			<section class="mlgp-panel mlgp-panel--wide mlgp-add-images-page">
				<div class="mlgp-add-images-page__hero">
					<div class="mlgp-add-images-page__hero-copy">
						<div class="mlgp-gallery-toolbar__meta">
							<span class="mlgp-pill">Fluxo direto</span>
							<span>${escapeHtml(config.strings.addImagesPageDescription || "")}</span>
						</div>
						<div class="mlgp-add-images-page__hero-stats">
							<span class="mlgp-add-images-chip"><strong>Fonte</strong><em>${escapeHtml(sourceLabel)}</em></span>
							<span class="mlgp-add-images-chip"><strong>Status</strong><em>${escapeHtml(statusLabel)}</em></span>
							<span class="mlgp-add-images-chip"><strong>Storage</strong><em>${escapeHtml(config.storageLabel || "wp-content/ml-gallery")}</em></span>
						</div>
					</div>
					<div class="mlgp-actions">
						<a class="mlgp-button mlgp-button--ghost" href="${escapeHtml(getBackUrl())}">${escapeHtml(config.strings.backToGalleriesAction || "Voltar para galerias")}</a>
						${state.editor.activeId ? `<a class="mlgp-button mlgp-button--accent" href="${escapeHtml(galleryUrl)}">${escapeHtml(config.strings.openGalleryManagerAction || "Abrir manager da galeria")}</a>` : ""}
					</div>
				</div>
				<div class="mlgp-add-images-layout">
					<section class="mlgp-editor-panel mlgp-add-images-page__main">
						<div class="mlgp-add-images-page__section-head">
							<h3>${escapeHtml(config.strings.newGalleryTitle || "Adicionar nova galeria")}</h3>
							<p class="mlgp-panel__intro">${escapeHtml(config.strings.newGalleryDescription || "")}</p>
						</div>
						<div class="mlgp-add-images-page__form">
							<div class="mlgp-field">
								<label for="mlgp-inline-gallery-title">Titulo da galeria</label>
								<input id="mlgp-inline-gallery-title" type="text" value="${escapeHtml(state.createModal.title)}" data-mlgp-create-field="title" placeholder="Ex.: Evento Samba Oke">
							</div>
							<div class="mlgp-modal__grid">
								<div class="mlgp-field">
									<label for="mlgp-inline-gallery-slug">Slug</label>
									<input id="mlgp-inline-gallery-slug" type="text" value="${escapeHtml(state.createModal.slug)}" data-mlgp-create-field="slug" placeholder="gerado-automaticamente">
								</div>
								<div class="mlgp-field">
									<label for="mlgp-inline-gallery-status">Status</label>
									<select id="mlgp-inline-gallery-status" data-mlgp-create-field="status">
										<option value="draft" ${state.createModal.status === "draft" ? "selected" : ""}>Rascunho</option>
										<option value="publish" ${state.createModal.status === "publish" ? "selected" : ""}>Publicado</option>
										<option value="private" ${state.createModal.status === "private" ? "selected" : ""}>Privado</option>
									</select>
								</div>
							</div>
							<div class="mlgp-field">
								<label for="mlgp-inline-gallery-description">Descricao</label>
								<textarea id="mlgp-inline-gallery-description" data-mlgp-create-field="description" placeholder="Opcional">${escapeHtml(state.createModal.description)}</textarea>
							</div>
						</div>
						<div class="mlgp-add-images-page__source-stage">
							<div class="mlgp-add-images-page__section-label">Escolha a origem dos arquivos</div>
							<div class="mlgp-source-grid ${hasServerImport() ? "" : "mlgp-source-grid--three"}">
								<button type="button" class="mlgp-source-card ${state.createModal.source === "files" ? "is-active" : ""}" data-mlgp-open-create-source="files">
									<strong>${escapeHtml(config.strings.computerUploadAction || "Computador")}</strong>
									<span>${escapeHtml(config.strings.computerUploadHint || "Selecionar imagens avulsas")}</span>
								</button>
								<button type="button" class="mlgp-source-card ${state.createModal.source === "folder" ? "is-active" : ""}" data-mlgp-open-create-source="folder">
									<strong>${escapeHtml(config.strings.importFolderAction || "Importar pasta")}</strong>
									<span>${escapeHtml(config.strings.importFolderHint || "Ler imagens de uma pasta local")}</span>
								</button>
								<button type="button" class="mlgp-source-card ${state.createModal.source === "zip" ? "is-active" : ""}" data-mlgp-open-create-source="zip">
									<strong>${escapeHtml(config.strings.importZipAction || "Importar ZIP")}</strong>
									<span>${escapeHtml(config.strings.importZipHint || "Criar a galeria a partir de um ZIP")}</span>
								</button>
								${hasServerImport() ? `
									<button type="button" class="mlgp-source-card ${state.createModal.source === "server" ? "is-active" : ""}" data-mlgp-open-create-source="server">
										<strong>${escapeHtml(config.strings.serverImportAction || "Pasta do servidor")}</strong>
										<span>${escapeHtml(config.strings.serverImportHint || "Importar imagens de uma pasta ja existente no servidor")}</span>
									</button>
								` : ""}
							</div>
							${isServerSource
								? renderServerImportFields("create")
								: `
									<div class="mlgp-next-dropzone" data-mlgp-create-dropzone="1" data-mlgp-create-dropzone-type="${escapeHtml(state.createModal.source)}">
										<input id="mlgp-create-gallery-upload-input" type="file" multiple accept="image/*" hidden>
										<input id="mlgp-create-gallery-folder-input" type="file" multiple accept="image/*" webkitdirectory directory hidden>
										<input id="mlgp-create-gallery-zip-input" type="file" accept=".zip,application/zip" hidden>
										<div class="mlgp-next-dropzone__icon" aria-hidden="true">&#8682;</div>
										<strong>${escapeHtml(dropTitle)}</strong>
										<p>${escapeHtml(sourceDescription)}</p>
										<p class="mlgp-next-dropzone__path">Diretorio: ${escapeHtml(config.storageLabel || "wp-content/ml-gallery")}</p>
										${renderCreateFilesList()}
									</div>
								`}
						</div>
						<div class="mlgp-upload-progress" data-mlgp-create-progress ${state.createModal.submitting || state.createModal.progress > 0 ? "" : "hidden"}>
							<div class="mlgp-upload-progress__bar">
								<span data-mlgp-create-progress-fill style="width:${Math.max(0, Math.min(100, Number(state.createModal.progress || 0)))}%"></span>
							</div>
							<div class="mlgp-upload-progress__text" data-mlgp-create-status>${escapeHtml(state.createModal.message || "")}</div>
						</div>
						<div class="mlgp-add-images-page__footer">
							<div class="mlgp-add-images-page__summary">
								<strong>${escapeHtml(sourceLabel)}</strong>
								<span>${escapeHtml(sourceSummary)}</span>
							</div>
							<div class="mlgp-actions">
								<button type="button" class="mlgp-button mlgp-button--accent" data-mlgp-submit-create-gallery="1">${escapeHtml(getCreateSubmitLabel())}</button>
							</div>
						</div>
					</section>
					<aside class="mlgp-editor-panel mlgp-add-images-page__aside">
						<div class="mlgp-add-images-sidecard mlgp-add-images-sidecard--accent">
							<h3>Fluxo comercial</h3>
							<p>Monte a galeria no mesmo passo, com origem controlada, status definido e entrega pronta para shortcode.</p>
							<div class="mlgp-add-images-steps">
								<div class="mlgp-add-images-step"><strong>1.</strong><span>Defina o titulo, o slug e o status da nova galeria.</span></div>
								<div class="mlgp-add-images-step"><strong>2.</strong><span>Escolha a origem dos arquivos e envie computador, pasta local, ZIP ou pasta do servidor.</span></div>
								<div class="mlgp-add-images-step"><strong>3.</strong><span>O plugin cria a galeria, salva no storage proprio e entrega o shortcode pronto para uso.</span></div>
							</div>
						</div>
						<div class="mlgp-add-images-sidecard">
							<span class="mlgp-add-images-sidecard__label">Painel de leitura</span>
							<div class="mlgp-add-images-sidecard__facts">
								<div><strong>Origem ativa</strong><span>${escapeHtml(sourceLabel)}</span></div>
								<div><strong>Status</strong><span>${escapeHtml(statusLabel)}</span></div>
								<div><strong>Storage</strong><span>${escapeHtml(config.storageLabel || "wp-content/ml-gallery")}</span></div>
							</div>
						</div>
						${activeShortcode ? `
							${renderShortcodeBox("Ultima galeria criada", activeShortcode)}
						` : `
							<div class="mlgp-add-images-sidecard">
								<span class="mlgp-add-images-sidecard__label">Shortcode pronto</span>
								<p>Depois da criacao, o shortcode nativo da galeria aparece aqui para copiar e publicar.</p>
							</div>
						`}
					</aside>
				</div>
			</section>
		`;
	}

	function renderBulkTabButton(key, label) {
		return `
			<button type="button" class="mlgp-shell__tab ${state.bulkTab === key ? "is-active" : ""}" data-mlgp-bulk-tab="${key}">
				${escapeHtml(label)}
			</button>
		`;
	}

	function renderActiveBulkPanel(selectedCount) {
		switch (state.bulkTab) {
			case "alt":
				return `
					<section class="mlgp-bulk-panel mlgp-bulk-panel--single">
						<strong class="mlgp-bulk-panel__title">ALT</strong>
						<div class="mlgp-field mlgp-bulk-tools__field">
							<label for="mlgp-bulk-alt">ALT em lote</label>
							<input id="mlgp-bulk-alt" type="text" value="${escapeHtml(state.bulkAlt || "")}" placeholder="${escapeHtml(config.strings.bulkAltPlaceholder || "Novo ALT para as imagens selecionadas")}" data-mlgp-bulk-alt="1">
						</div>
						<div class="mlgp-actions mlgp-actions--bulk">
							<button type="button" class="mlgp-button mlgp-button--ghost" data-mlgp-bulk-action="replace_alts" ${selectedCount ? "" : "disabled"}>${escapeHtml(config.strings.bulkReplaceAlts || "Substituir ALT")}</button>
							<button type="button" class="mlgp-button mlgp-button--ghost" data-mlgp-bulk-action="clear_alts" ${selectedCount ? "" : "disabled"}>${escapeHtml(config.strings.bulkClearAlts || "Limpar ALT")}</button>
						</div>
					</section>
				`;
			case "captions":
				return `
					<section class="mlgp-bulk-panel mlgp-bulk-panel--single">
						<strong class="mlgp-bulk-panel__title">Legendas</strong>
						<div class="mlgp-field mlgp-bulk-tools__field">
							<label for="mlgp-bulk-caption">Legenda em lote</label>
							<textarea id="mlgp-bulk-caption" rows="3" placeholder="${escapeHtml(config.strings.bulkCaptionPlaceholder || "Nova legenda para as imagens selecionadas")}" data-mlgp-bulk-caption="1">${escapeHtml(state.bulkCaption || "")}</textarea>
						</div>
						<div class="mlgp-actions mlgp-actions--bulk">
							<button type="button" class="mlgp-button mlgp-button--ghost" data-mlgp-bulk-action="replace_captions" ${selectedCount ? "" : "disabled"}>${escapeHtml(config.strings.bulkReplaceCaptions || "Substituir legendas")}</button>
							<button type="button" class="mlgp-button mlgp-button--ghost" data-mlgp-bulk-action="clear_captions" ${selectedCount ? "" : "disabled"}>${escapeHtml(config.strings.bulkClearCaptions || "Limpar legendas")}</button>
						</div>
					</section>
				`;
			case "tags":
				return `
					<section class="mlgp-bulk-panel mlgp-bulk-panel--single">
						<strong class="mlgp-bulk-panel__title">Tags</strong>
						<div class="mlgp-field mlgp-bulk-tools__field">
							<label for="mlgp-bulk-tags">Tags em lote</label>
							<input id="mlgp-bulk-tags" type="text" value="${escapeHtml(state.bulkTags || "")}" placeholder="${escapeHtml(config.strings.bulkTagsPlaceholder || "evento, capa, destaque")}" data-mlgp-bulk-tags="1">
						</div>
						<div class="mlgp-actions mlgp-actions--bulk">
							<button type="button" class="mlgp-button mlgp-button--ghost" data-mlgp-bulk-action="append_tags" ${selectedCount ? "" : "disabled"}>${escapeHtml(config.strings.bulkAppendTags || "Adicionar tags")}</button>
							<button type="button" class="mlgp-button mlgp-button--ghost" data-mlgp-bulk-action="replace_tags" ${selectedCount ? "" : "disabled"}>${escapeHtml(config.strings.bulkReplaceTags || "Substituir tags")}</button>
							<button type="button" class="mlgp-button mlgp-button--ghost" data-mlgp-bulk-action="clear_tags" ${selectedCount ? "" : "disabled"}>${escapeHtml(config.strings.bulkClearTags || "Limpar tags")}</button>
						</div>
					</section>
				`;
			case "tools":
				return `
					<section class="mlgp-bulk-panel mlgp-bulk-panel--single">
						<strong class="mlgp-bulk-panel__title">Ferramentas</strong>
						<div class="mlgp-actions mlgp-actions--bulk">
							<button type="button" class="mlgp-button mlgp-button--ghost" data-mlgp-bulk-action="rotate_left" ${selectedCount ? "" : "disabled"}>${escapeHtml(config.strings.bulkRotateLeft || "Rotacionar 90° para a esquerda")}</button>
							<button type="button" class="mlgp-button mlgp-button--ghost" data-mlgp-bulk-action="rotate_right" ${selectedCount ? "" : "disabled"}>${escapeHtml(config.strings.bulkRotateRight || "Rotacionar 90° para a direita")}</button>
							<button type="button" class="mlgp-button" data-mlgp-bulk-action="regenerate" ${selectedCount ? "" : "disabled"}>${escapeHtml(config.strings.bulkRegenerate || "Regenerar previews")}</button>
						</div>
					</section>
				`;
			case "titles":
			default:
				return `
					<section class="mlgp-bulk-panel mlgp-bulk-panel--single">
						<strong class="mlgp-bulk-panel__title">Títulos</strong>
						<div class="mlgp-field mlgp-bulk-tools__field">
							<label for="mlgp-bulk-title">Título em lote</label>
							<input id="mlgp-bulk-title" type="text" value="${escapeHtml(state.bulkTitle || "")}" placeholder="${escapeHtml(config.strings.bulkTitlePlaceholder || "Novo título para as imagens selecionadas")}" data-mlgp-bulk-title="1">
						</div>
						<div class="mlgp-actions mlgp-actions--bulk">
							<button type="button" class="mlgp-button mlgp-button--ghost" data-mlgp-bulk-action="replace_titles" ${selectedCount ? "" : "disabled"}>${escapeHtml(config.strings.bulkReplaceTitles || "Substituir títulos")}</button>
							<button type="button" class="mlgp-button mlgp-button--ghost" data-mlgp-bulk-action="clear_titles" ${selectedCount ? "" : "disabled"}>${escapeHtml(config.strings.bulkClearTitles || "Limpar títulos")}</button>
						</div>
					</section>
				`;
		}
	}

	function renderEditor() {
		if (!state.editor.activeId || !state.editor.gallery) {
			return `
				<section class="mlgp-panel mlgp-panel--wide">
					<div class="mlgp-empty">
						<p>Crie ou selecione uma galeria para abrir o manager de imagens.</p>
						<div class="mlgp-actions mlgp-actions--center">
							${isAddImagesPage
								? `<button type="button" class="mlgp-button mlgp-button--accent" data-mlgp-open-create-source="${escapeHtml(state.createModal.source || "files")}">${escapeHtml(config.strings.addImagesPrimaryAction || "Criar galeria com imagens")}</button>`
								: `<a class="mlgp-button mlgp-button--accent" href="${escapeHtml(String((config.pageUrls && config.pageUrls.addImages) || "#"))}">${escapeHtml(config.strings.addImagesPrimaryAction || "Criar galeria com imagens")}</a>`}
						</div>
					</div>
				</section>
			`;
		}

		const cover = state.editor.gallery.cover || null;
		const items = state.editor.items || [];
		const filteredItems = items;
		const selectedCount = getSelectedItemIds().length;
		const uploadStatus = state.upload.message || `${state.upload.totalFiles || 0} arquivo(s) pronto(s) para upload`;
		const shortcode = galleryShortcode(state.editor.gallery);
		const legacyShortcode = state.editor.gallery.legacy_shortcode || "";
		const publicUrl = galleryPublicUrl(state.editor.gallery);

		return `
			<section class="mlgp-panel mlgp-panel--wide">
				<div class="mlgp-editor" data-mlgp-editor-shell="1">
					<div class="mlgp-editor__toolbar">
						<div>
							<h2>${escapeHtml(state.editor.gallery.title || "Galeria sem titulo")}</h2>
							<div class="mlgp-list__meta">
								<span class="mlgp-pill">ID ${Number(state.editor.gallery.id || 0)}</span>
								<span>slug: ${escapeHtml(state.editor.gallery.slug || "")}</span>
								<span>${items.length} imagens</span>
								<span>${escapeHtml(displayTypeLabel(state.editor.form.displayType || "grid"))}</span>
								${renderCopyableShortcode(shortcode)}
								${publicUrl ? renderCopyableShortcode(publicUrl) : ""}
							</div>
						</div>
						<div class="mlgp-actions">
							<span class="mlgp-status-pill ${hasPendingChanges() ? "is-warning" : "is-clean"}" data-mlgp-editor-status>${hasPendingChanges() ? "Alteracoes pendentes" : "Sincronizado"}</span>
							${!isAddImagesPage ? `<button type="button" class="mlgp-button mlgp-button--ghost" data-mlgp-close-editor="1">${escapeHtml(config.strings.backToGalleriesAction || "Voltar para galerias")}</button>` : ""}
							<button type="button" class="mlgp-button mlgp-button--accent" data-mlgp-open-upload="files">Adicionar imagens</button>
							<button type="button" class="mlgp-button" data-mlgp-save-all="1" ${hasPendingChanges() ? "" : "disabled"}>Salvar alteracoes</button>
						</div>
					</div>
					<div class="mlgp-editor-layout">
						<aside class="mlgp-editor-sidebar">
							<section class="mlgp-editor-panel">
								<h3>Capa da galeria</h3>
								<div class="mlgp-cover-card">
									${cover && (cover.large_url || cover.medium_url) ? `<img src="${escapeHtml(cover.large_url || cover.medium_url)}" alt="${escapeHtml(cover.alt || cover.title || "")}">` : '<div class="mlgp-cover-card__empty">Nenhuma capa definida</div>'}
									<div class="mlgp-cover-card__meta">
										<strong>${cover ? escapeHtml(cover.title || "Imagem selecionada") : "Sem imagem"}</strong>
										<small>${cover ? escapeHtml(cover.filename || "") : "Selecione uma imagem da galeria para definir a capa"}</small>
									</div>
								</div>
							</section>
							<section class="mlgp-editor-panel">
								<h3>Dados da galeria</h3>
								<div class="mlgp-field">
									<label for="mlgp-editor-gallery-title">Titulo</label>
									<input id="mlgp-editor-gallery-title" type="text" value="${escapeHtml(state.editor.form.title)}" data-mlgp-gallery-field="title">
								</div>
								<div class="mlgp-field">
									<label for="mlgp-editor-gallery-slug">Slug</label>
									<input id="mlgp-editor-gallery-slug" type="text" value="${escapeHtml(state.editor.form.slug)}" data-mlgp-gallery-field="slug">
								</div>
								<div class="mlgp-field">
									<label for="mlgp-editor-gallery-status">Status</label>
									<select id="mlgp-editor-gallery-status" data-mlgp-gallery-field="status">
										<option value="draft" ${state.editor.form.status === "draft" ? "selected" : ""}>Rascunho</option>
										<option value="publish" ${state.editor.form.status === "publish" ? "selected" : ""}>Publicado</option>
										<option value="private" ${state.editor.form.status === "private" ? "selected" : ""}>Privado</option>
									</select>
								</div>
								<div class="mlgp-field">
									<label for="mlgp-editor-gallery-description">Descricao</label>
									<textarea id="mlgp-editor-gallery-description" data-mlgp-gallery-field="description">${escapeHtml(state.editor.form.description)}</textarea>
								</div>
								<div class="mlgp-field">
									<label for="mlgp-editor-gallery-published-at">Data de publicacao</label>
									<input id="mlgp-editor-gallery-published-at" type="datetime-local" value="${escapeHtml((state.editor.form.publishedAt || "").replace(" ", "T").substring(0, 16))}" data-mlgp-gallery-field="publishedAt">
									<small class="mlgp-field__hint">Data do evento ou publicacao. Editavel para eventos retroativos.</small>
								</div>
								<div class="mlgp-field mlgp-field--inline-dates">
									<div>
										<label for="mlgp-editor-gallery-created-at">Criada em</label>
										<input id="mlgp-editor-gallery-created-at" type="datetime-local" value="${escapeHtml((state.editor.form.createdAt || "").replace(" ", "T").substring(0, 16))}" data-mlgp-gallery-field="createdAt">
									</div>
									<div>
										<label>Modificada em</label>
										<span class="mlgp-field__readonly">${escapeHtml(state.editor.form.updatedAt || "—")}</span>
									</div>
								</div>
							</section>
							<section class="mlgp-editor-panel">
								<h3>Exibicao da galeria</h3>
								<p class="mlgp-panel__intro">Defina o modo visual principal do shortcode e os ajustes base de grid, texto e navegacao visual.</p>
								<div class="mlgp-preset-grid">
									${getGalleryPresets().map((preset) => `
										<button type="button" class="mlgp-preset-card ${state.editor.form.displayType === preset.displayType ? "is-active" : ""}" data-mlgp-apply-preset="${escapeHtml(preset.id)}">
											<strong>${escapeHtml(preset.label)}</strong>
											<span>${escapeHtml(preset.description)}</span>
										</button>
									`).join("")}
								</div>
								<div class="mlgp-field">
									<label for="mlgp-editor-display-type">Display type</label>
									<select id="mlgp-editor-display-type" data-mlgp-gallery-field="displayType">
										<option value="grid" ${state.editor.form.displayType === "grid" ? "selected" : ""}>Thumbnails Grid</option>
										<option value="tile" ${state.editor.form.displayType === "tile" ? "selected" : ""}>Tile</option>
										<option value="mosaic" ${state.editor.form.displayType === "mosaic" ? "selected" : ""}>Mosaic</option>
										<option value="masonry" ${state.editor.form.displayType === "masonry" ? "selected" : ""}>Masonry</option>
										<option value="justified" ${state.editor.form.displayType === "justified" ? "selected" : ""}>Justified</option>
										<option value="slideshow" ${state.editor.form.displayType === "slideshow" ? "selected" : ""}>Slideshow</option>
										<option value="filmstrip" ${state.editor.form.displayType === "filmstrip" ? "selected" : ""}>Filmstrip</option>
										<option value="imagebrowser" ${state.editor.form.displayType === "imagebrowser" ? "selected" : ""}>Image Browser</option>
									</select>
								</div>
								<div class="mlgp-editor-fields">
									<div class="mlgp-field">
										<label for="mlgp-editor-columns-desktop">Colunas desktop</label>
										<input id="mlgp-editor-columns-desktop" type="number" min="1" max="8" value="${Number(state.editor.form.columnsDesktop || 4)}" data-mlgp-gallery-field="columnsDesktop">
									</div>
									<div class="mlgp-field">
										<label for="mlgp-editor-columns-tablet">Colunas tablet</label>
										<input id="mlgp-editor-columns-tablet" type="number" min="1" max="6" value="${Number(state.editor.form.columnsTablet || 3)}" data-mlgp-gallery-field="columnsTablet">
									</div>
									<div class="mlgp-field">
										<label for="mlgp-editor-columns-mobile">Colunas mobile</label>
										<input id="mlgp-editor-columns-mobile" type="number" min="1" max="4" value="${Number(state.editor.form.columnsMobile || 2)}" data-mlgp-gallery-field="columnsMobile">
									</div>
									<div class="mlgp-field">
										<label for="mlgp-editor-card-gap">Espacamento</label>
										<input id="mlgp-editor-card-gap" type="number" min="0" max="48" value="${Number(state.editor.form.cardGap || 18)}" data-mlgp-gallery-field="cardGap">
									</div>
								</div>
								<label class="mlgp-check">
									<input type="checkbox" ${Number(state.editor.form.roundedCorners || 0) ? "checked" : ""} data-mlgp-gallery-field="roundedCorners">
									<span>Usar cantos arredondados nesta galeria</span>
								</label>
								<label class="mlgp-check">
									<input type="checkbox" ${Number(state.editor.form.enableFrontendFilters || 0) ? "checked" : ""} data-mlgp-gallery-field="enableFrontendFilters">
									<span>Exibir busca e filtro por tag no frontend desta galeria</span>
								</label>
								<div class="mlgp-editor-fields">
									<label class="mlgp-check">
										<input type="checkbox" ${Number(state.editor.form.paginationEnabled || 0) ? "checked" : ""} data-mlgp-gallery-field="paginationEnabled">
										<span>Paginar esta galeria no frontend</span>
									</label>
									<div class="mlgp-field">
										<label for="mlgp-editor-items-per-page">Imagens por pagina</label>
										<input id="mlgp-editor-items-per-page" type="number" min="1" max="5000" value="${Number(state.editor.form.itemsPerPage || 24)}" data-mlgp-gallery-field="itemsPerPage">
									</div>
								</div>
								<label class="mlgp-check">
									<input type="checkbox" ${Number(state.editor.form.showHeading || 0) ? "checked" : ""} data-mlgp-gallery-field="showHeading">
									<span>Exibir título principal da galeria</span>
								</label>
								<label class="mlgp-check">
									<input type="checkbox" ${Number(state.editor.form.showDescription || 0) ? "checked" : ""} data-mlgp-gallery-field="showDescription">
									<span>Exibir descrição da galeria</span>
								</label>
								<label class="mlgp-check">
									<input type="checkbox" ${Number(state.editor.form.showTitles || 0) ? "checked" : ""} data-mlgp-gallery-field="showTitles">
									<span>Exibir titulos das imagens no frontend</span>
								</label>
								<label class="mlgp-check">
									<input type="checkbox" ${Number(state.editor.form.showCaptions || 0) ? "checked" : ""} data-mlgp-gallery-field="showCaptions">
									<span>Exibir legendas das imagens no frontend</span>
								</label>
								<label class="mlgp-check">
									<input type="checkbox" ${Number(state.editor.form.showItemTags || 0) ? "checked" : ""} data-mlgp-gallery-field="showItemTags">
									<span>Exibir tags das imagens no frontend</span>
								</label>
								<div class="mlgp-editor-fields">
									<div class="mlgp-field">
										<label for="mlgp-editor-heading-font-size">Tamanho do titulo principal (px)</label>
										<input id="mlgp-editor-heading-font-size" type="number" min="20" max="96" value="${Number(state.editor.form.headingFontSize || DEFAULT_HEADING_FONT_SIZE)}" data-mlgp-gallery-field="headingFontSize">
									</div>
									<div class="mlgp-field">
										<label for="mlgp-editor-heading-color">Cor do titulo principal</label>
										<input id="mlgp-editor-heading-color" type="color" value="${escapeHtml(state.editor.form.headingColor || "#172033")}" data-mlgp-gallery-field="headingColor">
									</div>
								</div>
								<div class="mlgp-editor-fields">
									<div class="mlgp-field">
										<label for="mlgp-editor-item-title-font-size">Tamanho do titulo das imagens (px)</label>
										<input id="mlgp-editor-item-title-font-size" type="number" min="10" max="48" value="${Number(state.editor.form.itemTitleFontSize || 18)}" data-mlgp-gallery-field="itemTitleFontSize">
									</div>
									<div class="mlgp-field">
										<label for="mlgp-editor-item-title-color">Cor do titulo das imagens</label>
										<input id="mlgp-editor-item-title-color" type="color" value="${escapeHtml(state.editor.form.itemTitleColor || "#172033")}" data-mlgp-gallery-field="itemTitleColor">
									</div>
								</div>
								<div class="mlgp-field">
									<label for="mlgp-editor-justified-row-height">Altura da linha no Justified (px)</label>
									<input id="mlgp-editor-justified-row-height" type="number" min="120" max="520" step="10" value="${Number(state.editor.form.justifiedRowHeight || 220)}" data-mlgp-gallery-field="justifiedRowHeight">
								</div>
								<div class="mlgp-editor-fields">
									<label class="mlgp-check">
										<input type="checkbox" ${Number(state.editor.form.slideshowAutoplay || 0) ? "checked" : ""} data-mlgp-gallery-field="slideshowAutoplay">
										<span>Autoplay no slideshow</span>
									</label>
									<label class="mlgp-check">
										<input type="checkbox" ${Number(state.editor.form.slideshowShowArrows || 0) ? "checked" : ""} data-mlgp-gallery-field="slideshowShowArrows">
										<span>Exibir setas no slideshow</span>
									</label>
									<label class="mlgp-check">
										<input type="checkbox" ${Number(state.editor.form.slideshowShowThumbs || 0) ? "checked" : ""} data-mlgp-gallery-field="slideshowShowThumbs">
										<span>Exibir miniaturas no slideshow</span>
									</label>
									<div class="mlgp-field">
										<label for="mlgp-editor-slideshow-interval">Intervalo do slideshow (ms)</label>
										<input id="mlgp-editor-slideshow-interval" type="number" min="1500" max="20000" step="500" value="${Number(state.editor.form.slideshowInterval || 4000)}" data-mlgp-gallery-field="slideshowInterval">
									</div>
								</div>
							</section>
							<section class="mlgp-editor-panel">
								<h3>Shortcodes</h3>
								<div class="mlgp-shortcode-stack">
									${renderShortcodeBox("Padrao", shortcode)}
									${renderShortcodeBox("URL canonica", publicUrl)}
									${renderShortcodeBox("Compatibilidade", legacyShortcode)}
								</div>
								<p class="mlgp-panel__intro">Storage: ${escapeHtml(config.storageLabel || "wp-content/ml-gallery")}</p>
							</section>
						</aside>
						<div class="mlgp-editor-main">
							<section class="mlgp-editor-panel">
								<div class="mlgp-panel-header-inline">
									<div>
										<h3>Adicionar imagens</h3>
										<p class="mlgp-panel__intro">${escapeHtml(config.strings.uploadStorageLabel || "")}</p>
									</div>
								</div>
								<div class="mlgp-upload-box" data-mlgp-upload-dropzone="1">
									<input id="mlgp-gallery-upload-input" type="file" multiple accept="image/*" hidden>
									<input id="mlgp-gallery-folder-input" type="file" multiple accept="image/*" webkitdirectory directory hidden>
									<input id="mlgp-gallery-zip-input" type="file" accept=".zip,application/zip" hidden>
									<div class="mlgp-upload-source-row ${hasServerImport() ? "mlgp-upload-source-row--four" : ""}">
										<button type="button" class="mlgp-source-card mlgp-source-card--compact" data-mlgp-open-upload="files" ${state.upload.uploading ? "disabled" : ""}>
											<strong>${escapeHtml(config.strings.computerUploadAction || "Computador")}</strong>
											<span>${escapeHtml(config.strings.computerUploadHint || "Selecionar arquivos avulsos")}</span>
										</button>
										<button type="button" class="mlgp-source-card mlgp-source-card--compact" data-mlgp-open-upload="folder" ${state.upload.uploading ? "disabled" : ""}>
											<strong>${escapeHtml(config.strings.importFolderAction || "Importar pasta")}</strong>
											<span>${escapeHtml(config.strings.importFolderHint || "Subir uma pasta inteira de imagens")}</span>
										</button>
										<button type="button" class="mlgp-source-card mlgp-source-card--compact" data-mlgp-open-upload="zip" ${state.upload.uploading ? "disabled" : ""}>
											<strong>${escapeHtml(config.strings.importZipAction || "Importar ZIP")}</strong>
											<span>${escapeHtml(config.strings.importZipHint || "Extrair imagens de um ZIP")}</span>
										</button>
										${hasServerImport() ? `
											<div class="mlgp-source-card mlgp-source-card--compact mlgp-source-card--server">
												<strong>${escapeHtml(config.strings.serverImportAction || "Pasta do servidor")}</strong>
												<span>${escapeHtml(config.strings.serverImportHint || "Importar imagens de uma pasta ja existente no servidor")}</span>
											</div>
										` : ""}
									</div>
									<div class="mlgp-scan-storage-row">
										<strong>Escanear pasta de armazenamento</strong>
										<span>Sincronize imagens enviadas via FTP. Registros existentes sao atualizados sem duplicar.</span>
										<div class="mlgp-scan-storage-filter">
											<input type="text" data-mlgp-scan-filter="1" placeholder="Filtrar pastas..." autocomplete="off">
										</div>
										<div class="mlgp-scan-storage-controls">
											<select data-mlgp-scan-folder-select="1" size="8">
												<option value="">Carregando pastas...</option>
											</select>
											<button type="button" class="mlgp-button mlgp-button--accent" data-mlgp-scan-folder="1" ${state.upload.uploading ? "disabled" : ""}>Escanear</button>
										</div>
									</div>
									<strong>Diretorio: ${escapeHtml(config.storageLabel || "wp-content/ml-gallery")}</strong>
									<p>Arraste imagens para esta area ou use os atalhos acima para subir arquivos, pasta local ou um ZIP diretamente para a galeria.</p>
									${renderServerImportFields("upload")}
									<div class="mlgp-upload-progress" data-mlgp-upload-progress ${state.upload.uploading || state.upload.progress > 0 ? "" : "hidden"}>
										<div class="mlgp-upload-progress__bar">
											<span data-mlgp-upload-progress-fill style="width:${Math.max(0, Math.min(100, Number(state.upload.progress || 0)))}%"></span>
										</div>
										<div class="mlgp-upload-progress__text" data-mlgp-upload-status>${escapeHtml(uploadStatus)}</div>
									</div>
								</div>
							</section>
							<section class="mlgp-editor-panel mlgp-editor-panel--wide">
								<div class="mlgp-panel-header-inline">
									<div>
										<h3>Manager de imagens</h3>
										<p class="mlgp-panel__intro">${getViewMode() === "visual" ? "Visualize e selecione a capa. Ative o modo avancado para editar campos." : "Edite titulo, legenda, alt, link, visibilidade, capa, tags e organize a ordem."}</p>
									</div>
									<div class="mlgp-list__meta">
										<button type="button" class="mlgp-button ${getViewMode() === "advanced" ? "mlgp-button--accent" : "mlgp-button--ghost"} mlgp-button--sm" data-mlgp-toggle-view="1">${getViewMode() === "visual" ? "Avancado" : "Visual"}</button>
										<span>${items.length} imagens</span>
										<span>${selectedCount} selecionadas</span>
									</div>
								</div>
									<div class="mlgp-bulk-toolbar">
									<div class="mlgp-bulk-toolbar__meta">
										<strong>Ações em lote</strong>
										<span>Selecione as imagens e aplique a ação desejada. A ordenação continua disponível em cada card.</span>
									</div>
									<div class="mlgp-bulk-toolbar__row">
										<div class="mlgp-actions mlgp-actions--bulk mlgp-actions--bulk-primary">
											<button type="button" class="mlgp-button mlgp-button--ghost" data-mlgp-select-all="1" ${filteredItems.length ? "" : "disabled"}>${escapeHtml(config.strings.bulkSelectAll || "Selecionar tudo")}</button>
											<button type="button" class="mlgp-button mlgp-button--ghost" data-mlgp-clear-selection="1" ${selectedCount ? "" : "disabled"}>${escapeHtml(config.strings.bulkClearSelection || "Limpar seleção")}</button>
											<button type="button" class="mlgp-button mlgp-button--ghost" data-mlgp-bulk-action="show" ${selectedCount ? "" : "disabled"}>${escapeHtml(config.strings.bulkShowSelected || "Exibir")}</button>
											<button type="button" class="mlgp-button mlgp-button--ghost" data-mlgp-bulk-action="hide" ${selectedCount ? "" : "disabled"}>${escapeHtml(config.strings.bulkHideSelected || "Ocultar")}</button>
											<button type="button" class="mlgp-button mlgp-button--danger" data-mlgp-bulk-action="delete" ${selectedCount ? "" : "disabled"}>${escapeHtml(config.strings.bulkDeleteSelected || "Excluir")}</button>
										</div>
									</div>
									<div class="mlgp-bulk-toolbar__tabs mlgp-shell__tabs">
										${renderBulkTabButton("titles", "Títulos")}
						${renderBulkTabButton("alt", "ALT")}
										${renderBulkTabButton("captions", "Legendas")}
										${renderBulkTabButton("tags", "Tags")}
										${renderBulkTabButton("tools", "Ferramentas")}
									</div>
									<div class="mlgp-bulk-toolbar__panels">
										${renderActiveBulkPanel(selectedCount)}
									</div>
								</div>
									${items.length
										? (getViewMode() === "visual"
											? `<div class="mlgp-thumb-grid">${filteredItems.map(renderItemCardVisual).join("")}</div>`
											: `<div class="mlgp-media-grid">${filteredItems.map(renderItemCard).join("")}</div>`)
										: '<div class="mlgp-empty">Nenhuma imagem vinculada ainda. Use "Adicionar imagens".</div>'}
							</section>
						</div>
					</div>
				</div>
			</section>
		`;
	}

	let scanDirsCache = null;
	let scanDirsCacheVersion = "";

	async function populateScanDropdown() {
		const select = root.querySelector("[data-mlgp-scan-folder-select]");
		if (!select) {
			return;
		}

		const cacheKey = String(state.editor.activeId || "");
		if (scanDirsCache && scanDirsCacheVersion === cacheKey) {
			fillScanSelect(select, scanDirsCache, "");
			return;
		}

		try {
			const response = await request("mlgp_list_storage_dirs", {});
			scanDirsCache = Array.isArray(response.dirs) ? response.dirs : [];
			scanDirsCacheVersion = cacheKey;
			fillScanSelect(select, scanDirsCache, "");
		} catch (error) {
			select.innerHTML = '<option value="">Erro ao carregar pastas</option>';
		}
	}

	function fillScanSelect(select, dirs, filter) {
		const term = (filter || "").toLowerCase();
		let html = "";
		for (const dir of dirs) {
			const name = dir.name || "";
			const label = dir.label || name;
			if (term && label.toLowerCase().indexOf(term) === -1) {
				continue;
			}
			html += `<option value="${escapeHtml(name)}">${escapeHtml(label)}</option>`;
		}
		select.innerHTML = html || '<option value="">Nenhuma pasta encontrada</option>';
		const filterInput = root.querySelector("[data-mlgp-scan-filter]");
		if (filterInput && !term) {
			filterInput.placeholder = `Filtrar entre ${dirs.length} pastas...`;
		}
	}

	function renderPage() {
		const totals = getTotals();
		const filtered = getFilteredGalleries();
		const emptyListMessage = state.searchTerm
			? String(config.strings.noGalleryFound || "Nenhuma galeria encontrada para a busca aplicada.")
			: "Nenhuma galeria criada ainda. Use o botao para adicionar a primeira.";

		if (state.editor.activeId) {
			root.innerHTML = `
				${renderEditor()}
				${state.createModal.open ? renderCreateModal() : ""}
			`;

			updateDirtyUi();
			updateUploadUi();
			updateCreateModalUi();
			populateScanDropdown();
			return;
		}

		if (isAddImagesPage) {
			root.innerHTML = `
				<section class="mlgp-stats mlgp-stats--dashboard">
					${renderMetricCard("Galerias", String(totals.galleries), "Base ativa do plugin")}
					${renderMetricCard("Imagens", String(totals.images), "Arquivos no storage proprio")}
					${renderMetricCard("Publicadas", String(totals.published), "Galerias prontas para shortcode")}
					${renderMetricCard("Storage", config.storageLabel || "wp-content/ml-gallery", "Diretorio proprio", "storage")}
				</section>
				${renderAddImagesPage()}
				${state.createModal.open ? renderCreateModal() : ""}
			`;
		} else {
			root.innerHTML = `
				<section class="mlgp-stats mlgp-stats--dashboard">
					${renderMetricCard("Galerias", String(totals.galleries), "Total cadastrado no produto")}
					${renderMetricCard("Imagens", String(totals.images), "Itens armazenados no plugin")}
					${renderMetricCard("Publicadas", String(totals.published), "Galerias prontas para shortcode")}
					${renderMetricCard("Storage", config.storageLabel || "wp-content/ml-gallery", "Diretorio proprio", "storage")}
				</section>
				<section class="mlgp-panel mlgp-gallery-dashboard">
					<div class="mlgp-gallery-toolbar">
						<div class="mlgp-gallery-toolbar__meta">
							<span class="mlgp-pill">${filtered.length} visiveis</span>
							<span>${totals.galleries} galerias no total</span>
							<span>${totals.images} imagens</span>
						</div>
						<div class="mlgp-actions">
							${renderSortControl(state.sortMode)}
							<div class="mlgp-gallery-search">
								<input type="search" value="${escapeHtml(state.searchTerm)}" placeholder="${escapeHtml(config.strings.searchGalleriesPlaceholder || "Buscar galerias...")}" data-mlgp-gallery-search="1">
							</div>
							<a class="mlgp-button mlgp-button--accent" href="${escapeHtml(String((config.pageUrls && config.pageUrls.addImages) || "#"))}">${escapeHtml(config.strings.addImagesPrimaryAction || "Criar galeria com imagens")}</a>
						</div>
					</div>
					<div class="mlgp-gallery-list">
						${filtered.length ? filtered.map(renderGalleryRow).join("") : `<div class="mlgp-empty">${escapeHtml(emptyListMessage)}</div>`}
					</div>
				</section>
				${state.createModal.open ? renderCreateModal() : ""}
			`;
		}

		updateDirtyUi();
		updateUploadUi();
		updateCreateModalUi();
	}

	function cleanupBootQuery() {
		if (!(window.history && window.history.replaceState && window.location)) {
			return;
		}

		const url = new URL(window.location.href);
		let changed = false;

		["mlgp_notice", "mlgp_message"].forEach((key) => {
			if (url.searchParams.has(key)) {
				url.searchParams.delete(key);
				changed = true;
			}
		});

		if (changed) {
			window.history.replaceState({}, document.title, url.toString());
		}
	}

	function getEditorItem(itemId) {
		return state.editor.items.find((item) => Number(item.id) === Number(itemId));
	}

	function serializeItems() {
		return state.editor.items.map((item) => ({
			id: Number(item.id),
			item_title: item.item_title || "",
			item_caption: item.item_caption || "",
			item_alt: item.item_alt || "",
			item_link: item.item_link || "",
			item_tags: item.item_tags || "",
			is_visible: Number(item.is_visible || 0) ? 1 : 0,
		}));
	}

	function setCover(itemId) {
		state.editor.coverItemId = Number(itemId || 0);
		state.editor.items.forEach((item) => {
			item.is_cover = Number(item.id) === state.editor.coverItemId;
		});

		const coverItem = state.editor.items.find((item) => item.is_cover);

		if (state.editor.gallery) {
			state.editor.gallery.cover_item_id = state.editor.coverItemId;
			state.editor.gallery.cover = coverItem ? coverItem.attachment : null;
		}

		markItemsDirty();
		renderPage();
	}

	function reorderItems(dragItemId, targetItemId) {
		const sourceId = Number(dragItemId || 0);
		const destinationId = Number(targetItemId || 0);

		if (!sourceId || !destinationId || sourceId === destinationId) {
			return;
		}

		const items = [...state.editor.items];
		const sourceIndex = items.findIndex((item) => Number(item.id) === sourceId);
		const targetIndex = items.findIndex((item) => Number(item.id) === destinationId);

		if (sourceIndex < 0 || targetIndex < 0 || sourceIndex === targetIndex) {
			return;
		}

		const [movingItem] = items.splice(sourceIndex, 1);
		const insertIndex = sourceIndex < targetIndex ? targetIndex - 1 : targetIndex;
		items.splice(insertIndex, 0, movingItem);
		state.editor.items = items;
		markItemsDirty();
		renderPage();
	}

	function clearDragState() {
		state.dragItemId = 0;
		root.querySelectorAll(".mlgp-media-card.is-dragging,.mlgp-media-card.is-drop-target").forEach((card) => {
			card.classList.remove("is-dragging", "is-drop-target");
		});
	}

	function moveItem(itemId, direction) {
		const index = state.editor.items.findIndex((item) => Number(item.id) === Number(itemId));

		if (index < 0) {
			return;
		}

		const targetIndex = direction === "up" ? index - 1 : index + 1;

		if (targetIndex < 0 || targetIndex >= state.editor.items.length) {
			return;
		}

		const items = [...state.editor.items];
		const current = items[index];
		items[index] = items[targetIndex];
		items[targetIndex] = current;
		state.editor.items = items;
		markItemsDirty();
		renderPage();
	}

	function removeItem(itemId) {
		state.editor.items = state.editor.items.filter((item) => Number(item.id) !== Number(itemId));
		setSelectedItemIds(getSelectedItemIds().filter((selectedId) => Number(selectedId) !== Number(itemId)));

		if (!state.editor.items.some((item) => Number(item.id) === Number(state.editor.coverItemId))) {
			state.editor.coverItemId = state.editor.items.length ? Number(state.editor.items[0].id) : 0;
		}

		state.editor.items.forEach((item) => {
			item.is_cover = Number(item.id) === Number(state.editor.coverItemId);
		});

		const coverItem = state.editor.items.find((item) => item.is_cover);

		if (state.editor.gallery) {
			state.editor.gallery.cover_item_id = state.editor.coverItemId;
			state.editor.gallery.cover = coverItem ? coverItem.attachment : null;
		}

		markItemsDirty();
		renderPage();
	}

	async function refreshGalleries(shouldRender = true) {
		const response = await request("mlgp_list_galleries", { sort_mode: state.sortMode || "id_desc" });
		state.galleries = response.items || [];
		state.sortMode = response.sort_mode || state.sortMode || "id_desc";

		if (state.editor.activeId && state.galleries.length && !state.galleries.some((item) => Number(item.id) === Number(state.editor.activeId))) {
			syncEditor(null);
		}

		if (shouldRender) {
			renderPage();
		}
	}

	async function openEditor(galleryId, force) {
		if (!force && hasPendingChanges() && Number(galleryId) !== Number(state.editor.activeId) && !window.confirm(config.strings.confirmDiscardChanges)) {
			return;
		}

		const response = await request("mlgp_get_gallery_editor", { gallery_id: galleryId });
		syncEditor(response);
		renderPage();
	}

	async function saveGalleryData(showNoticeAfterSave) {
		if (!state.editor.activeId) {
			return false;
		}

		const response = await request("mlgp_save_gallery", {
			id: state.editor.activeId,
			title: state.editor.form.title || "",
			slug: state.editor.form.slug || "",
			status: state.editor.form.status || "draft",
			description: state.editor.form.description || "",
			published_at: state.editor.form.publishedAt ? state.editor.form.publishedAt.replace("T", " ") : "",
			created_at: state.editor.form.createdAt ? state.editor.form.createdAt.replace("T", " ") : "",
			display_type: state.editor.form.displayType || "grid",
			settings: JSON.stringify(serializeGallerySettings()),
		});

		if (response.editor) {
			syncEditor(response.editor, { preserveSelection: true });
		}

		await refreshGalleries(false);
		renderPage();

		if (showNoticeAfterSave !== false) {
			showNotice(response.message || config.strings.gallerySettingsSaved || config.strings.saveSuccess);
		}

		return true;
	}

	async function saveItems(showNoticeAfterSave, preserveGalleryDraft, preserveSelection) {
		if (!state.editor.activeId) {
			showNotice(config.strings.selectGalleryFirst, "error");
			return false;
		}

		const response = await request("mlgp_save_gallery_items", {
			gallery_id: state.editor.activeId,
			cover_item_id: state.editor.coverItemId || 0,
			items: JSON.stringify(serializeItems()),
		});

		syncEditor(response.editor, {
			preserveGalleryDraft: !!preserveGalleryDraft,
			preserveSelection: !!preserveSelection,
		});
		await refreshGalleries(false);
		renderPage();

		if (showNoticeAfterSave !== false) {
			showNotice(response.message || config.strings.galleryItemsSaved);
		}

		return true;
	}

	async function flushPendingChanges() {
		if (!state.editor.activeId) {
			showNotice(config.strings.selectGalleryFirst, "error");
			return false;
		}

		if (state.editor.itemsDirty) {
			const keepGalleryDraft = state.editor.galleryDirty;
			const result = await saveItems(false, keepGalleryDraft, true);

			if (!result) {
				return false;
			}
		}

		if (state.editor.galleryDirty) {
			const result = await saveGalleryData(false);

			if (!result) {
				return false;
			}
		}

		return true;
	}

	async function saveAllChanges() {
		if (!state.editor.activeId) {
			showNotice(config.strings.selectGalleryFirst, "error");
			return;
		}

		let saved = false;

		if (state.editor.itemsDirty) {
			const keepGalleryDraft = state.editor.galleryDirty;
			const result = await saveItems(false, keepGalleryDraft, true);

			if (!result) {
				return;
			}

			saved = true;
		}

		if (state.editor.galleryDirty) {
			await saveGalleryData(false);
			saved = true;
		}

		if (saved) {
			showNotice(config.strings.editorChangesSaved || config.strings.saveSuccess);
		}
	}

	async function openMedia(type) {
		if (!state.editor.activeId) {
			showNotice(config.strings.selectGalleryFirst, "error");
			return;
		}

		const targetType = type || "files";
		const selector = "zip" === targetType
			? "#mlgp-gallery-zip-input"
			: ("folder" === targetType ? "#mlgp-gallery-folder-input" : "#mlgp-gallery-upload-input");
		const input = root.querySelector(selector);

		if (!(input instanceof HTMLInputElement)) {
			showNotice(config.strings.genericError, "error");
			return;
		}

		input.click();
	}

	function getUploadBatchSize() {
		const configured = integerValue(config.uploadBatchSize, 19);

		return Math.max(1, configured);
	}

	function splitFilesIntoBatches(files, batchSize) {
		const batches = [];
		const normalizedBatchSize = Math.max(1, batchSize);

		for (let index = 0; index < files.length; index += normalizedBatchSize) {
			batches.push(files.slice(index, index + normalizedBatchSize));
		}

		return batches;
	}

	function buildBatchStatusMessage(baseLabel, uploadedCount, totalFiles, batchIndex, batchTotal, progress) {
		const safeTotal = Math.max(1, Number(totalFiles || 0));
		const safeUploaded = Math.max(0, Math.min(safeTotal, Number(uploadedCount || 0)));
		let message = `${baseLabel} ${safeUploaded}/${safeTotal}`;

		if (Number(batchTotal || 0) > 1) {
			message += ` - lote ${Number(batchIndex || 1)}/${Number(batchTotal || 1)}`;
		}

		if (Number.isFinite(Number(progress))) {
			message += ` - ${Math.max(0, Math.min(100, Math.round(Number(progress || 0))))}%`;
		}

		return message;
	}

	function buildUploadSuccessMessage(count) {
		return Number(count || 0) === 1
			? "1 imagem enviada com sucesso."
			: `${Number(count || 0)} imagens enviadas com sucesso.`;
	}

	function buildCreateUploadSuccessMessage(count) {
		return Number(count || 0) === 1
			? "Galeria criada e 1 imagem enviada com sucesso."
			: `Galeria criada e ${Number(count || 0)} imagens enviadas com sucesso.`;
	}

	function createUploadError(error, uploadedCount, totalFiles, lastEditor) {
		const baseMessage = error instanceof Error && error.message
			? error.message
			: (config.strings.genericError || "Nao foi possivel concluir a operacao.");
		const wrappedError = new Error(
			uploadedCount > 0
				? `Falha apos enviar ${uploadedCount} de ${totalFiles} imagens. ${baseMessage}`
				: baseMessage
		);

		wrappedError.lastEditor = (error && error.lastEditor) || lastEditor || null;
		wrappedError.responseData = error && error.responseData ? error.responseData : null;
		wrappedError.uploadedCount = uploadedCount;
		wrappedError.totalFiles = totalFiles;

		return wrappedError;
	}

	function uploadFileBatch(galleryId, files, onProgress) {
		return new Promise((resolve, reject) => {
			const formData = new FormData();
			formData.append("action", "mlgp_upload_gallery_images");
			formData.append("nonce", config.nonce || "");
			formData.append("gallery_id", String(galleryId || 0));

			files.forEach((file) => {
				formData.append("files[]", file, file.name);
			});

			const xhr = new XMLHttpRequest();
			xhr.open("POST", config.ajaxUrl, true);
			xhr.withCredentials = true;

			xhr.upload.addEventListener("progress", (event) => {
				if (!event.lengthComputable || typeof onProgress !== "function") {
					return;
				}

				onProgress(Math.round((event.loaded / event.total) * 100));
			});

			xhr.addEventListener("load", () => {
				try {
					const response = JSON.parse(xhr.responseText || "{}");

					if (!response.success) {
						const error = new Error(response.data && response.data.message ? response.data.message : config.strings.genericError);
						error.responseData = response.data || null;
						error.lastEditor = response.data && response.data.editor ? response.data.editor : null;
						throw error;
					}

					resolve(response.data || {});
				} catch (error) {
					reject(error);
				}
			});

			xhr.addEventListener("error", () => {
				reject(new Error(config.strings.genericError));
			});

			xhr.send(formData);
		});
	}

	async function uploadFilesInBatches(galleryId, files, options) {
		const settings = options || {};
		const batches = splitFilesIntoBatches(files, getUploadBatchSize());
		let uploadedCount = 0;
		let lastEditor = settings.initialEditor || null;

		for (let index = 0; index < batches.length; index += 1) {
			const batchFiles = batches[index];

			try {
				const response = await uploadFileBatch(galleryId, batchFiles, (batchProgress) => {
					if (typeof settings.onProgress !== "function") {
						return;
					}

					const uploadedVisual = Math.min(
						files.length,
						uploadedCount + Math.round((batchFiles.length * Math.max(0, Math.min(100, batchProgress))) / 100)
					);
					const overallProgress = Math.round((uploadedVisual / Math.max(1, files.length)) * 100);

					settings.onProgress({
						progress: overallProgress,
						uploadedCount,
						uploadedVisual,
						totalFiles: files.length,
						batchIndex: index + 1,
						batchTotal: batches.length,
						batchProgress,
					});
				});

				uploadedCount += batchFiles.length;
				lastEditor = response.editor || lastEditor;
			} catch (error) {
				throw createUploadError(error, uploadedCount, files.length, lastEditor);
			}
		}

		return {
			editor: lastEditor,
			uploadedCount,
			totalFiles: files.length,
			batchTotal: batches.length,
		};
	}

	function uploadFiles(files, mode) {
		const validFiles = Array.from(files || []).filter((file) => isImageFile(file));

		if (!validFiles.length) {
			showNotice(config.strings.selectFilesFirst || config.strings.genericError, "error");
			return Promise.resolve(false);
		}

		const prepare = flushPendingChanges();

		return prepare.then((saved) => {
			if (!saved) {
				return false;
			}

			setUploadState({
				uploading: true,
				progress: 0,
				totalFiles: validFiles.length,
				mode: mode || "files",
				message: buildBatchStatusMessage(
					config.strings.uploadingImages || "Enviando imagens...",
					0,
					validFiles.length,
					1,
					splitFilesIntoBatches(validFiles, getUploadBatchSize()).length,
					0
				),
			});

			return uploadFilesInBatches(Number(state.editor.activeId || 0), validFiles, {
				onProgress: ({ progress, uploadedVisual, totalFiles, batchIndex, batchTotal }) => {
					setUploadState({
						uploading: true,
						progress,
						totalFiles,
						mode: mode || "files",
						message: buildBatchStatusMessage(
							config.strings.uploadingImages || "Enviando imagens...",
							uploadedVisual,
							totalFiles,
							batchIndex,
							batchTotal,
							progress
						),
					});
				},
			}).then(async (result) => {
				syncEditor(result.editor, { preserveSelection: true });
				await refreshGalleries(false);
				upsertGallerySummaryFromEditor(result.editor);
				renderPage();
				const successMessage = buildUploadSuccessMessage(result.uploadedCount);
				setUploadState({
					uploading: false,
					progress: 100,
					totalFiles: result.totalFiles,
					mode: mode || "files",
					message: successMessage,
				});
				showNotice(successMessage);
				return true;
			}).catch(async (error) => {
				if (error && error.lastEditor) {
					syncEditor(error.lastEditor, { preserveSelection: true });
					await refreshGalleries(false);
					upsertGallerySummaryFromEditor(error.lastEditor);
					renderPage();
				}

				setUploadState(emptyUploadState());
				throw error;
			});
		});
	}

	function uploadZip(file) {
		if (!file || !/\.zip$/i.test(String(file.name || ""))) {
			showNotice(config.strings.selectZipFirst || config.strings.genericError, "error");
			return Promise.resolve(false);
		}

		const prepare = flushPendingChanges();

		return prepare.then((saved) => {
			if (!saved) {
				return false;
			}

			return new Promise((resolve, reject) => {
				const formData = new FormData();
				formData.append("action", "mlgp_import_gallery_zip");
				formData.append("nonce", config.nonce || "");
				formData.append("gallery_id", String(state.editor.activeId || 0));
				formData.append("zip_file", file, file.name);

				const xhr = new XMLHttpRequest();
				xhr.open("POST", config.ajaxUrl, true);
				xhr.withCredentials = true;

				setUploadState({
					uploading: true,
					progress: 0,
					totalFiles: 1,
					mode: "zip",
					message: config.strings.importingZip || "Importando ZIP...",
				});

				xhr.upload.addEventListener("progress", (event) => {
					if (!event.lengthComputable) {
						return;
					}

					setUploadState({
						progress: Math.round((event.loaded / event.total) * 100),
						message: `${config.strings.importingZip || "Importando ZIP..."} ${Math.round((event.loaded / event.total) * 100)}%`,
					});
				});

				xhr.addEventListener("load", async () => {
					try {
						const response = JSON.parse(xhr.responseText || "{}");

						if (!response.success) {
							const error = new Error(response.data && response.data.message ? response.data.message : config.strings.genericError);
							error.responseData = response.data || null;
							error.lastEditor = response.data && response.data.editor ? response.data.editor : null;
							throw error;
						}

						syncEditor(response.data.editor);
						await refreshGalleries(false);
						upsertGallerySummaryFromEditor(response.data.editor);
						renderPage();
						setUploadState({
							uploading: false,
							progress: 100,
							totalFiles: 1,
							mode: "zip",
							message: response.data.message || config.strings.galleryZipImported || "ZIP importado com sucesso.",
						});
						showNotice(response.data.message || config.strings.galleryZipImported || "ZIP importado com sucesso.");
						resolve(true);
					} catch (error) {
						if (error && error.lastEditor) {
							syncEditor(error.lastEditor, { preserveSelection: true });
							await refreshGalleries(false);
							upsertGallerySummaryFromEditor(error.lastEditor);
							renderPage();
						}

						setUploadState(emptyUploadState());
						reject(error);
					}
				});

				xhr.addEventListener("error", () => {
					setUploadState(emptyUploadState());
					reject(new Error(config.strings.genericError));
				});

				xhr.send(formData);
			});
		});
	}

	function importServerDirectory() {
		const galleryId = Number(state.editor.activeId || 0);
		const serverPath = normalizeServerPath(state.upload.serverPath || "");

		if (!galleryId) {
			showNotice(config.strings.genericError, "error");
			return Promise.resolve(false);
		}

		if (!serverPath) {
			showNotice(config.strings.selectServerPathFirst || "Informe a pasta relativa do servidor antes de continuar.", "error");
			return Promise.resolve(false);
		}

		const prepare = flushPendingChanges();

		return prepare.then(async (saved) => {
			if (!saved) {
				return false;
			}

			setUploadState({
				uploading: true,
				progress: 20,
				totalFiles: 0,
				mode: "server",
				message: config.strings.serverImportButton || "Importando pasta do servidor...",
			});

			try {
				let offset = 0;
				let done = false;
				let lastResponse = null;
				const limit = 10;

				while (!done) {
					const response = await request("mlgp_import_gallery_directory", {
						gallery_id: galleryId,
						server_root: state.upload.serverRoot || getDefaultServerRoot(),
						server_path: serverPath,
						offset,
						limit,
					});

					lastResponse = response;
					done = Boolean(response.done);
					offset = Number(response.next_offset || offset + limit);

					if (response.editor) {
						syncEditor(response.editor, { preserveSelection: true });
						upsertGallerySummaryFromEditor(response.editor);
					}

					const total = Number(response.total || 0);
					const progress = total > 0
						? Math.min(98, Math.max(20, Math.round((offset / total) * 100)))
						: 50;

					setUploadState({
						uploading: !done,
						progress: done ? 100 : progress,
						totalFiles: total,
						mode: "server",
						message: response.message || config.strings.serverImportButton || "Importando pasta do servidor...",
					});
				}

				if (lastResponse && lastResponse.editor) {
					syncEditor(lastResponse.editor, { preserveSelection: true });
					await refreshGalleries(false);
					upsertGallerySummaryFromEditor(lastResponse.editor);
				}

				renderPage();
				setUploadState({
					uploading: false,
					progress: 100,
					totalFiles: Number((lastResponse && lastResponse.total) || 0),
					mode: "server",
					message: (lastResponse && lastResponse.message) || config.strings.galleryServerImported || "Pasta do servidor importada com sucesso.",
				});
				showNotice((lastResponse && lastResponse.message) || config.strings.galleryServerImported || "Pasta do servidor importada com sucesso.");
				return true;
			} catch (error) {
				if (error && error.lastEditor) {
					syncEditor(error.lastEditor, { preserveSelection: true });
					await refreshGalleries(false);
					upsertGallerySummaryFromEditor(error.lastEditor);
					renderPage();
				}

				setUploadState({
					uploading: false,
					progress: 0,
					totalFiles: 0,
					mode: "server",
					message: "",
				});
				throw error;
			}
		});
	}

	function collectCreateModalPayload() {
		return {
			title: state.createModal.title || "",
			slug: state.createModal.slug || "",
			description: state.createModal.description || "",
			status: state.createModal.status || "publish",
			source: state.createModal.source || "files",
			server_root: state.createModal.serverRoot || getDefaultServerRoot(),
			server_path: normalizeServerPath(state.createModal.serverPath || ""),
		};
	}

	function scrollToEditor() {
		const editor = root.querySelector("[data-mlgp-editor-shell]");

		if (editor && editor.scrollIntoView) {
			editor.scrollIntoView({ behavior: "smooth", block: "start" });
		}
	}

	function createGalleryWithUploads() {
		if (!String(state.createModal.title || "").trim()) {
			showNotice("Informe um titulo para a galeria.", "error");
			return Promise.resolve(false);
		}

		const files = state.createModal.files.filter((file) => isImageFile(file));
		const zipFile = state.createModal.zipFile;
		const serverPath = normalizeServerPath(state.createModal.serverPath || "");
		const hasServer = state.createModal.source === "server" && !!serverPath;
		const progressLabel = hasServer
			? String(config.strings.creatingGalleryServer || "Criando galeria e importando pasta do servidor...")
			: (zipFile
				? String(config.strings.creatingGalleryZip || "Criando galeria e importando ZIP...")
				: (files.length
					? String(config.strings.creatingGalleryUpload || "Criando galeria e enviando imagens...")
					: String(config.strings.creatingGallery || "Criando galeria...")));

		if (state.createModal.source === "server" && !hasServer) {
			showNotice(config.strings.selectServerPathFirst || "Informe a pasta relativa do servidor antes de continuar.", "error");
			return Promise.resolve(false);
		}

		if (files.length && !zipFile && !hasServer) {
			setCreateModalState({
				submitting: true,
				progress: 0,
				message: String(config.strings.creatingGallery || "Criando galeria..."),
			});

			return request("mlgp_create_gallery_with_uploads", collectCreateModalPayload()).then(async (response) => {
				const createdEditor = response.editor || null;
				const galleryId = Number(
					(response.item && response.item.id) ||
					(createdEditor && createdEditor.gallery && createdEditor.gallery.id) ||
					0
				);

				if (!galleryId) {
					throw new Error(config.strings.genericError);
				}

				const result = await uploadFilesInBatches(galleryId, files, {
					initialEditor: createdEditor,
					onProgress: ({ progress, uploadedVisual, totalFiles, batchIndex, batchTotal }) => {
						setCreateModalState({
							submitting: true,
							progress,
							message: buildBatchStatusMessage(
								config.strings.creatingGalleryUpload || "Criando galeria e enviando imagens...",
								uploadedVisual,
								totalFiles,
								batchIndex,
								batchTotal,
								progress
							),
						});
					},
				});

				resetCreateModal();
				syncEditor(result.editor || createdEditor);
				await refreshGalleries(false);
				upsertGallerySummaryFromEditor(result.editor || createdEditor);
				renderPage();
				scrollToEditor();
				showNotice(buildCreateUploadSuccessMessage(result.uploadedCount));
				return true;
			}).catch(async (error) => {
				setCreateModalState({
					submitting: false,
					progress: 0,
					message: "",
				});

				if (error && error.lastEditor) {
					resetCreateModal();
					syncEditor(error.lastEditor, { preserveSelection: true });
					await refreshGalleries(false);
					upsertGallerySummaryFromEditor(error.lastEditor);
					renderPage();
					scrollToEditor();
				}

				throw error;
			});
		}

		return new Promise((resolve, reject) => {
			const formData = new FormData();
			formData.append("action", "mlgp_create_gallery_with_uploads");
			formData.append("nonce", config.nonce || "");

			Object.entries(collectCreateModalPayload()).forEach(([key, value]) => {
				formData.append(key, value == null ? "" : value);
			});

			if (zipFile) {
				formData.append("zip_file", zipFile, zipFile.name);
			} else if (!hasServer) {
				files.forEach((file) => {
					formData.append("files[]", file, file.name);
				});
			}

			const xhr = new XMLHttpRequest();
			xhr.open("POST", config.ajaxUrl, true);
			xhr.withCredentials = true;

			setCreateModalState({
				submitting: true,
				progress: 0,
				message: progressLabel,
			});

			xhr.upload.addEventListener("progress", (event) => {
				if (!event.lengthComputable || (!files.length && !zipFile)) {
					return;
				}

				setCreateModalState({
					progress: Math.round((event.loaded / event.total) * 100),
					message: `${progressLabel} ${Math.round((event.loaded / event.total) * 100)}%`,
				});
			});

			xhr.addEventListener("load", async () => {
				try {
					const response = JSON.parse(xhr.responseText || "{}");

					if (!response.success) {
						const error = new Error(response.data && response.data.message ? response.data.message : config.strings.genericError);
						error.responseData = response.data || null;
						error.lastEditor = response.data && response.data.editor ? response.data.editor : null;
						throw error;
					}

					resetCreateModal();
					syncEditor(response.data.editor);
					await refreshGalleries(false);
					upsertGallerySummaryFromEditor(response.data.editor);
					renderPage();
					scrollToEditor();
					showNotice(
						response.data.message ||
						(hasServer
							? config.strings.galleryCreatedWithServer || "Galeria criada e pasta do servidor importada com sucesso."
							: (zipFile
								? config.strings.galleryCreatedWithZip || "Galeria criada e ZIP importado com sucesso."
								: (files.length ? config.strings.galleryCreatedWithImages : config.strings.galleryCreated)))
					);
					resolve(true);
				} catch (error) {
					if (error && error.lastEditor) {
						resetCreateModal();
						syncEditor(error.lastEditor, { preserveSelection: true });
						await refreshGalleries(false);
						upsertGallerySummaryFromEditor(error.lastEditor);
						renderPage();
						scrollToEditor();
					}

					setCreateModalState({
						submitting: false,
						progress: 0,
						message: "",
					});
					reject(error);
				}
			});

			xhr.addEventListener("error", () => {
				setCreateModalState({
					submitting: false,
					progress: 0,
					message: "",
				});
				reject(new Error(config.strings.genericError));
			});

			xhr.send(formData);
		});
	}

	function setCreateFiles(files, source) {
		state.createModal.source = source || state.createModal.source || "files";
		state.createModal.files = Array.from(files || []).filter((file) => isImageFile(file));
		state.createModal.zipFile = null;
		state.createModal.serverPath = "";
		renderPage();
	}

	function setCreateZip(file) {
		state.createModal.source = "zip";
		state.createModal.zipFile = file && /\.zip$/i.test(String(file.name || "")) ? file : null;
		state.createModal.files = [];
		state.createModal.serverPath = "";
		renderPage();
	}

	function copyText(value) {
		const text = String(value || "");

		if (!text) {
			return Promise.resolve(false);
		}

		if (navigator.clipboard && navigator.clipboard.writeText) {
			return navigator.clipboard.writeText(text).then(() => true);
		}

		const field = document.createElement("textarea");
		field.value = text;
		field.setAttribute("readonly", "readonly");
		field.style.position = "absolute";
		field.style.left = "-9999px";
		document.body.appendChild(field);
		field.select();
		document.execCommand("copy");
		document.body.removeChild(field);

		return Promise.resolve(true);
	}

	root.addEventListener("input", (event) => {
		const target = event.target;

		if (!(target instanceof HTMLElement)) {
			return;
		}

		if (target.dataset.mlgpScanFilter !== undefined && scanDirsCache) {
			const select = root.querySelector("[data-mlgp-scan-folder-select]");
			if (select) {
				fillScanSelect(select, scanDirsCache, target.value);
			}
			return;
		}

		const field = target.dataset.mlgpItemField;
		const itemId = target.dataset.itemId;

		if (field && itemId) {
			const item = getEditorItem(itemId);

			if (!item) {
				return;
			}

			item[field] = target instanceof HTMLInputElement && target.type === "checkbox" ? (target.checked ? 1 : 0) : target.value;
			markItemsDirty();
			return;
		}

		const galleryField = target.dataset.mlgpGalleryField;

		if (galleryField && isFieldControl(target)) {
			state.editor.form[galleryField] = readFieldControlValue(target);
			markGalleryDirty();
			return;
		}

		const createField = target.dataset.mlgpCreateField;

		if (createField) {
			state.createModal[createField] = target.value;
			updateCreateModalUi();
			return;
		}

		if (target.dataset.mlgpCreateServerPath) {
			const value = target.value;
			state.createModal.serverPath = value;
			renderPage();

			const nextInput = root.querySelector("[data-mlgp-create-server-path]");

			if (nextInput instanceof HTMLInputElement) {
				nextInput.focus();
				nextInput.setSelectionRange(value.length, value.length);
			}

			return;
		}

		if (target.dataset.mlgpUploadServerPath) {
			state.upload.serverPath = target.value;
			return;
		}

		if (target.dataset.mlgpBulkTags) {
			state.bulkTags = target.value;
			return;
		}

		if (target.dataset.mlgpBulkTitle) {
			state.bulkTitle = target.value;
			return;
		}

		if (target.dataset.mlgpBulkAlt) {
			state.bulkAlt = target.value;
			return;
		}

		if (target.dataset.mlgpBulkCaption) {
			state.bulkCaption = target.value;
			return;
		}

		if (target.dataset.mlgpGallerySearch) {
			const value = target.value;
			state.searchTerm = value;
			renderPage();

			const nextInput = root.querySelector("[data-mlgp-gallery-search]");

			if (nextInput instanceof HTMLInputElement) {
				nextInput.focus();
				nextInput.setSelectionRange(value.length, value.length);
			}

			return;
		}

		if (target.dataset.mlgpGallerySort) {
			state.sortMode = target.value || "updated_at_desc";
			refreshGalleries(true).catch((error) => {
				showNotice(error.message || config.strings.genericError, "error");
			});
			return;
		}

		if (target.dataset.mlgpItemSearch) {
			const value = target.value;
			state.itemFilters.search = value;
			renderPage();

			const nextInput = root.querySelector("[data-mlgp-item-search]");

			if (nextInput instanceof HTMLInputElement) {
				nextInput.focus();
				nextInput.setSelectionRange(value.length, value.length);
			}
		}
	});

	root.addEventListener("change", async (event) => {
		const target = event.target;

		if (!(target instanceof Element)) {
			return;
		}

		const galleryField = target.dataset ? target.dataset.mlgpGalleryField : "";

		if (galleryField && isFieldControl(target)) {
			state.editor.form[galleryField] = readFieldControlValue(target);
			markGalleryDirty();
			return;
		}

		const itemVisibility = target.dataset ? target.dataset.mlgpItemVisibility : "";
		const itemTag = target.dataset ? target.dataset.mlgpItemTag : "";

		if (itemVisibility && isFieldControl(target)) {
			state.itemFilters.visibility = String(readFieldControlValue(target) || "all");
			renderPage();
			return;
		}

		if (itemTag && isFieldControl(target)) {
			state.itemFilters.tag = String(readFieldControlValue(target) || "");
			renderPage();
			return;
		}

		if (target.dataset && target.dataset.mlgpCreateServerRoot && isFieldControl(target)) {
			state.createModal.serverRoot = String(readFieldControlValue(target) || getDefaultServerRoot());
			renderPage();
			return;
		}

		if (target.dataset && target.dataset.mlgpUploadServerRoot && isFieldControl(target)) {
			state.upload.serverRoot = String(readFieldControlValue(target) || getDefaultServerRoot());
			renderPage();
			return;
		}

		if (!(target instanceof HTMLInputElement)) {
			return;
		}

		if (target.dataset.mlgpSelectItem) {
			const selectedIds = getSelectedItemIds();
			const currentId = Number(target.dataset.mlgpSelectItem || 0);

			setSelectedItemIds(
				target.checked
					? [...selectedIds, currentId]
					: selectedIds.filter((itemId) => Number(itemId) !== currentId)
			);
			renderPage();
			return;
		}

		if (target.id === "mlgp-gallery-upload-input") {
			try {
				await uploadFiles(target.files, "files");
			} catch (error) {
				showNotice(error.message || config.strings.genericError, "error");
			} finally {
				target.value = "";
			}
			return;
		}

		if (target.id === "mlgp-gallery-folder-input") {
			try {
				await uploadFiles(target.files, "folder");
			} catch (error) {
				showNotice(error.message || config.strings.genericError, "error");
			} finally {
				target.value = "";
			}
			return;
		}

		if (target.id === "mlgp-gallery-zip-input") {
			try {
				await uploadZip(target.files && target.files.length ? target.files[0] : null);
			} catch (error) {
				showNotice(error.message || config.strings.genericError, "error");
			} finally {
				target.value = "";
			}
			return;
		}

		if (target.id === "mlgp-create-gallery-upload-input") {
			setCreateFiles(target.files, "files");
			target.value = "";
			return;
		}

		if (target.id === "mlgp-create-gallery-folder-input") {
			setCreateFiles(target.files, "folder");
			target.value = "";
			return;
		}

		if (target.id === "mlgp-create-gallery-zip-input") {
			setCreateZip(target.files && target.files.length ? target.files[0] : null);
			target.value = "";
		}
	});

	root.addEventListener("dragover", (event) => {
		if (!(event.target instanceof Element)) {
			return;
		}

		const uploadZone = event.target.closest("[data-mlgp-upload-dropzone],[data-mlgp-create-dropzone]");
		const dropTarget = state.dragItemId ? event.target.closest("[data-mlgp-drop-item]") : null;

		if (dropTarget instanceof HTMLElement && Number(dropTarget.dataset.itemId || dropTarget.dataset.mlgpDropItem || 0) !== Number(state.dragItemId || 0)) {
			event.preventDefault();
			root.querySelectorAll(".mlgp-media-card.is-drop-target").forEach((card) => {
				if (card !== dropTarget) {
					card.classList.remove("is-drop-target");
				}
			});
			dropTarget.classList.add("is-drop-target");
			return;
		}

		if (!uploadZone) {
			return;
		}

		event.preventDefault();
		uploadZone.classList.add("is-dragover");
	});

	root.addEventListener("dragleave", (event) => {
		if (!(event.target instanceof Element)) {
			return;
		}

		const zone = event.target.closest("[data-mlgp-upload-dropzone],[data-mlgp-create-dropzone]");
		const dropTarget = event.target.closest("[data-mlgp-drop-item]");

		if (dropTarget instanceof HTMLElement) {
			const nextTarget = event.relatedTarget;

			if (!(nextTarget instanceof Node) || !dropTarget.contains(nextTarget)) {
				dropTarget.classList.remove("is-drop-target");
			}
		}

		if (!zone) {
			return;
		}

		zone.classList.remove("is-dragover");
	});

	root.addEventListener("drop", async (event) => {
		if (!(event.target instanceof Element)) {
			return;
		}

		const uploadZone = event.target.closest("[data-mlgp-upload-dropzone]");
		const createZone = event.target.closest("[data-mlgp-create-dropzone]");
		const dropTarget = state.dragItemId ? event.target.closest("[data-mlgp-drop-item]") : null;

		if (dropTarget instanceof HTMLElement) {
			event.preventDefault();
			const targetItemId = Number(dropTarget.dataset.itemId || dropTarget.dataset.mlgpDropItem || 0);
			const dragItemId = Number(state.dragItemId || 0);

			clearDragState();
			reorderItems(dragItemId, targetItemId);
			return;
		}

		if (!uploadZone && !createZone) {
			return;
		}

		event.preventDefault();
		const droppedFiles = event.dataTransfer ? event.dataTransfer.files : [];
		const droppedZip = Array.from(droppedFiles || []).find((file) => file && /\.zip$/i.test(String(file.name || ""))) || null;

		if (uploadZone) {
			uploadZone.classList.remove("is-dragover");

			try {
				if (droppedZip && 1 === droppedFiles.length) {
					await uploadZip(droppedZip);
				} else {
					await uploadFiles(droppedFiles, "files");
				}
			} catch (error) {
				showNotice(error.message || config.strings.genericError, "error");
			}
			return;
		}

		createZone.classList.remove("is-dragover");

		if (state.createModal.source === "server") {
			return;
		}

		if (state.createModal.source === "zip" || (droppedZip && 1 === droppedFiles.length)) {
			setCreateZip(droppedZip);
			return;
		}

		setCreateFiles(droppedFiles, state.createModal.source || "files");
	});

	root.addEventListener("dragstart", (event) => {
		if (!(event.target instanceof Element)) {
			return;
		}

		const handle = event.target.closest("[data-mlgp-drag-handle]");

		if (!(handle instanceof HTMLElement)) {
			return;
		}

		const itemId = Number(handle.dataset.mlgpDragHandle || 0);
		const card = handle.closest("[data-mlgp-drop-item]");

		if (!itemId || !(card instanceof HTMLElement)) {
			return;
		}

		state.dragItemId = itemId;
		card.classList.add("is-dragging");

		if (event.dataTransfer) {
			event.dataTransfer.effectAllowed = "move";
			event.dataTransfer.setData("text/plain", String(itemId));
		}
	});

	root.addEventListener("dragend", () => {
		clearDragState();
	});

	root.addEventListener("click", async (event) => {
		if (!(event.target instanceof Element)) {
			return;
		}

		const target = event.target.closest("[data-mlgp-open-create-modal],[data-mlgp-close-create-modal],[data-mlgp-submit-create-gallery],[data-mlgp-open-create-source],[data-mlgp-open-editor],[data-mlgp-close-editor],[data-mlgp-delete-gallery],[data-mlgp-copy-shortcode],[data-mlgp-open-upload],[data-mlgp-import-server],[data-mlgp-save-all],[data-mlgp-set-cover],[data-mlgp-remove-item],[data-mlgp-move-item],[data-mlgp-select-all],[data-mlgp-clear-selection],[data-mlgp-clear-item-filters],[data-mlgp-bulk-action],[data-mlgp-apply-preset],[data-mlgp-bulk-tab],[data-mlgp-scan-folder],[data-mlgp-toggle-view]");

		if (!target) {
			return;
		}

		if (target.dataset.mlgpOpenCreateModal) {
			state.createModal = {
				...emptyCreateModalState(),
				open: true,
			};
			renderPage();
			return;
		}

		if (target.dataset.mlgpCloseCreateModal) {
			if (state.createModal.submitting) {
				return;
			}

			resetCreateModal();
			renderPage();
			return;
		}

		if (target.dataset.mlgpSubmitCreateGallery) {
			try {
				await createGalleryWithUploads();
			} catch (error) {
				showNotice(error.message || config.strings.genericError, "error");
			}
			return;
		}

		if (target.dataset.mlgpOpenCreateSource) {
			state.createModal.source = target.dataset.mlgpOpenCreateSource;
			state.createModal.serverPath = state.createModal.source === "server" ? state.createModal.serverPath : "";

			if (state.createModal.source === "server") {
				state.createModal.files = [];
				state.createModal.zipFile = null;
			} else if (state.createModal.source === "zip") {
				state.createModal.files = [];
			} else {
				state.createModal.zipFile = null;
			}

			renderPage();

			if (state.createModal.source === "server") {
				const serverInput = root.querySelector("#mlgp-create-server-path");

				if (serverInput instanceof HTMLInputElement) {
					serverInput.focus();
				}

				return;
			}

			const selector = state.createModal.source === "zip"
				? "#mlgp-create-gallery-zip-input"
				: (state.createModal.source === "folder" ? "#mlgp-create-gallery-folder-input" : "#mlgp-create-gallery-upload-input");
			const input = root.querySelector(selector);

			if (input instanceof HTMLInputElement) {
				input.click();
			}
			return;
		}

		if (target.dataset.mlgpOpenEditor) {
			try {
				await openEditor(target.dataset.mlgpOpenEditor, false);
			} catch (error) {
				showNotice(error.message || config.strings.genericError, "error");
			}
			return;
		}

		if (target.dataset.mlgpCloseEditor) {
			if (hasPendingChanges() && !window.confirm(config.strings.confirmDiscardChanges)) {
				return;
			}

			const backUrl = getBackUrl();
			if (backUrl && backUrl !== "#") {
				window.location.href = backUrl;
				return;
			}

			syncEditor(null);
			renderPage();
			return;
		}

		if (target.dataset.mlgpDeleteGallery) {
			if (!window.confirm(config.strings.confirmDeleteGallery)) {
				return;
			}

			try {
				await request("mlgp_delete_gallery", { id: target.dataset.mlgpDeleteGallery });

				if (Number(state.editor.activeId) === Number(target.dataset.mlgpDeleteGallery)) {
					syncEditor(null);
				}

				await refreshGalleries();
				showNotice(config.strings.deleteSuccess);
			} catch (error) {
				showNotice(error.message || config.strings.genericError, "error");
			}
			return;
		}

		if (target.dataset.mlgpOpenUpload) {
			try {
				await openMedia(target.dataset.mlgpOpenUpload || "files");
			} catch (error) {
				showNotice(error.message || config.strings.genericError, "error");
			}
			return;
		}

		if (target.dataset.mlgpImportServer) {
			try {
				await importServerDirectory();
			} catch (error) {
				showNotice(error.message || config.strings.genericError, "error");
			}
			return;
		}

		if (target.dataset.mlgpCopyShortcode) {
			try {
				await copyText(target.dataset.mlgpCopyShortcode);
				showNotice(config.strings.shortcodeCopied || "Shortcode copiado.");
			} catch (error) {
				showNotice(error.message || config.strings.genericError, "error");
			}
			return;
		}

		if (target.dataset.mlgpApplyPreset) {
			applyGalleryPreset(target.dataset.mlgpApplyPreset);
			return;
		}

		if (target.dataset.mlgpToggleView) {
			state.editor.viewMode = getViewMode() === "visual" ? "advanced" : "visual";
			renderPage();
			return;
		}

		if (target.dataset.mlgpScanFolder) {
			const select = root.querySelector("[data-mlgp-scan-folder-select]");
			const folderName = select ? select.value : "";
			if (!folderName) {
				showNotice("Selecione uma pasta para escanear.", "error");
				return;
			}
			const galleryId = Number(state.editor.activeId || 0);
			if (!galleryId) {
				showNotice("Abra uma galeria antes de escanear.", "error");
				return;
			}
			try {
				target.disabled = true;
				target.textContent = "Escaneando...";
				const response = await request("mlgp_scan_gallery_storage", { gallery_id: galleryId, folder_name: folderName });
				if (response.editor) {
					syncEditor(response.editor, { preserveSelection: true });
				}
				showNotice(response.message || `Escaneamento concluído: ${Number(response.synced || 0)} imagens sincronizadas.`);
				renderPage();
			} catch (error) {
				showNotice(error.message || config.strings.genericError, "error");
			} finally {
				target.disabled = false;
				target.textContent = "Escanear";
			}
			return;
		}

		if (target.dataset.mlgpBulkTab) {
			state.bulkTab = target.dataset.mlgpBulkTab;
			renderPage();
			return;
		}

		if (target.dataset.mlgpSaveAll) {
			try {
				await saveAllChanges();
			} catch (error) {
				showNotice(error.message || config.strings.genericError, "error");
			}
			return;
		}

		if (target.dataset.mlgpSetCover) {
			setCover(target.dataset.mlgpSetCover);
			return;
		}

		if (target.dataset.mlgpRemoveItem) {
			removeItem(target.dataset.mlgpRemoveItem);
			return;
		}

		if (target.dataset.mlgpMoveItem) {
			moveItem(target.dataset.mlgpMoveItem, target.dataset.direction || "up");
			return;
		}

		if (target.dataset.mlgpSelectAll) {
			setSelectedItemIds(getFilteredEditorItems().map((item) => Number(item.id || 0)));
			renderPage();
			return;
		}

		if (target.dataset.mlgpClearSelection) {
			setSelectedItemIds([]);
			renderPage();
			return;
		}

		if (target.dataset.mlgpClearItemFilters) {
			clearItemFilters();
			renderPage();
			return;
		}

		if (target.dataset.mlgpBulkAction) {
			const selectedIds = getSelectedItemIds();
			const action = String(target.dataset.mlgpBulkAction || "");
			const titlePayload = String(state.bulkTitle || "").trim();
			const altPayload = String(state.bulkAlt || "").trim();
			const captionPayload = String(state.bulkCaption || "").trim();
			const tagsPayload = String(state.bulkTags || "").trim();

			if (!selectedIds.length) {
				showNotice(config.strings.selectItemsFirst || "Selecione pelo menos uma imagem.", "error");
				return;
			}

			if ((action === "append_tags" || action === "replace_tags") && !tagsPayload) {
				showNotice(config.strings.bulkTagsRequired || "Informe ao menos uma tag para aplicar em lote.", "error");
				return;
			}

			if (action === "replace_titles" && !titlePayload) {
				showNotice(config.strings.bulkTitleRequired || "Informe um título para substituir em lote.", "error");
				return;
			}

			if (action === "replace_alts" && !altPayload) {
				showNotice(config.strings.bulkAltRequired || "Informe um ALT para substituir em lote.", "error");
				return;
			}

			if (action === "replace_captions" && !captionPayload) {
				showNotice(config.strings.bulkCaptionRequired || "Informe uma legenda para substituir em lote.", "error");
				return;
			}

			if (action === "delete" && !window.confirm(config.strings.confirmDeleteSelectedItems || "Deseja excluir as imagens selecionadas?")) {
				return;
			}

			if (action === "regenerate" && !window.confirm(config.strings.confirmRegenerateSelectedItems || "Deseja regenerar as previews das imagens selecionadas?")) {
				return;
			}

			if (action === "rotate_left" && !window.confirm(config.strings.confirmRotateLeftSelectedItems || "Deseja rotacionar 90 graus para a esquerda as imagens selecionadas?")) {
				return;
			}

			if (action === "rotate_right" && !window.confirm(config.strings.confirmRotateRightSelectedItems || "Deseja rotacionar 90 graus para a direita as imagens selecionadas?")) {
				return;
			}

			if (action === "clear_titles" && !window.confirm(config.strings.confirmClearSelectedTitles || "Deseja limpar os títulos das imagens selecionadas?")) {
				return;
			}

			if (action === "clear_alts" && !window.confirm(config.strings.confirmClearSelectedAlts || "Deseja limpar o ALT das imagens selecionadas?")) {
				return;
			}

			if (action === "clear_captions" && !window.confirm(config.strings.confirmClearSelectedCaptions || "Deseja limpar as legendas das imagens selecionadas?")) {
				return;
			}

			try {
				const prepared = await flushPendingChanges();

				if (!prepared) {
					return;
				}

				const response = await request("mlgp_bulk_update_gallery_items", {
					gallery_id: state.editor.activeId,
					bulk_action: action,
					item_ids: JSON.stringify(selectedIds),
					bulk_payload: JSON.stringify({
						title: titlePayload,
						alt: altPayload,
						caption: captionPayload,
						tags: tagsPayload,
					}),
				});

				syncEditor(response.editor);
				await refreshGalleries(false);
				state.bulkTitle = "";
				state.bulkAlt = "";
				state.bulkCaption = "";
				state.bulkTags = "";
				renderPage();
				showNotice(response.message || config.strings.bulkActionSuccess || "Ação em massa aplicada com sucesso.");
			} catch (error) {
				showNotice(error.message || config.strings.genericError, "error");
			}
		}
	});

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
			await copyText(target.dataset.mlgpCopyShortcode);
			showNotice(config.strings.shortcodeCopied || "Shortcode copiado.");
		} catch (error) {
			showNotice(error.message || config.strings.genericError, "error");
		}
	});

	async function boot() {
		try {
			await refreshGalleries(false);
			renderPage();

			if (Number(config.activeGalleryId || 0)) {
				await openEditor(config.activeGalleryId, true);
			}

			if (config.notice && config.notice.message) {
				showNotice(config.notice.message, config.notice.type || "success");
			}

			cleanupBootQuery();
		} catch (error) {
			showNotice(error.message || config.strings.genericError, "error");
		}
	}

	boot();
})();
