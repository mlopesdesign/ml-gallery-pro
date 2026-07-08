/**
 * ML Gallery Pro — Gutenberg block editor.
 *
 * Single dynamic block: ml-gallery-pro/gallery
 * Renders a gallery, album, or tag gallery via server-side render.
 * Uses useBlockProps so Gutenberg can select, move, delete and manage the block.
 */
(function (wp, config) {
	if (!wp || !wp.blocks || !config) {
		return;
	}

	const { registerBlockType } = wp.blocks;
	const { createElement: el, Fragment, useState } = wp.element;
	const { InspectorControls, BlockControls, useBlockProps } = wp.blockEditor || wp.editor;
	const {
		BaseControl,
		Button,
		Notice,
		PanelBody,
		Placeholder,
		SelectControl,
		TextControl,
		ColorPalette,
		ToolbarGroup,
		ToolbarButton,
		Modal,
	} = wp.components;
	const { __ } = wp.i18n;
	const ServerSideRenderModule = wp.serverSideRender;
	const ServerSideRender = ServerSideRenderModule && ServerSideRenderModule.default
		? ServerSideRenderModule.default
		: ServerSideRenderModule;

	// ─── helpers ────────────────────────────────────────────────────────────────

	function clampNumber(value, min, max) {
		const normalized = String(value || "").trim();
		if (!normalized) { return ""; }
		const parsed = Number.parseInt(normalized, 10);
		if (!Number.isFinite(parsed)) { return ""; }
		return String(Math.min(max, Math.max(min, parsed)));
	}

	function getToggleOptions() {
		return [
			{ value: "", label: config.strings.defaultOption || "Usar padrao da galeria/plugin" },
			{ value: "1", label: config.strings.enabledOption || "Ligado" },
			{ value: "0", label: config.strings.disabledOption || "Desligado" },
		];
	}

	function getEntityOptions(items, emptyLabel) {
		return [{ value: 0, label: emptyLabel }].concat(items || []);
	}

	function renderOptionalNumberControl(label, attributeName, attributes, setAttributes, help, min, max) {
		return el(Fragment, {},
			el(TextControl, {
				label,
				type: "number",
				min,
				max,
				value: attributes[attributeName] || "",
				help,
				onChange: function (value) {
					setAttributes({ [attributeName]: clampNumber(value, min, max) });
				},
			}),
			attributes[attributeName]
				? el(Button, {
					variant: "secondary",
					isSmall: true,
					onClick: function () { setAttributes({ [attributeName]: "" }); },
				}, config.strings.clearNumber || "Usar valor padrao")
				: null
		);
	}

	function renderOptionalColorControl(label, attributeName, attributes, setAttributes) {
		return el(BaseControl, { label },
			el("div", { className: "mlgp-block-editor__color" },
				el(ColorPalette, {
					value: attributes[attributeName] || undefined,
					onChange: function (value) { setAttributes({ [attributeName]: value || "" }); },
					clearable: false,
				}),
				el("div", { className: "mlgp-block-editor__color-actions" },
					el("code", {}, attributes[attributeName] || "padrao"),
					el(Button, {
						variant: "secondary",
						isSmall: true,
						onClick: function () { setAttributes({ [attributeName]: "" }); },
					}, config.strings.clearColor || "Usar cor padrao")
				)
			)
		);
	}

	// ─── Quick-select modal ──────────────────────────────────────────────────────
	// Opens when the user clicks "Add Gallery", "Add Album", "Change Gallery",
	// or "Change Album". Calls onSelect({ type, id }) on confirm.

	function MLGPSelectModal({ type, onSelect, onClose }) {
		const isAlbum = type === "album";
		const items = isAlbum ? (config.albums || []) : (config.galleries || []);
		const [selected, setSelected] = useState(0);

		const title = isAlbum
			? (config.strings.insertAlbumTitle || "Selecionar Album")
			: (config.strings.insertGalleryTitle || "Selecionar Galeria");

		const emptyMsg = isAlbum
			? (config.strings.noAlbums || "Nenhum album encontrado.")
			: (config.strings.noGalleries || "Nenhuma galeria encontrada.");

		const canConfirm = Number(selected) > 0;

		return el(Modal, {
			title,
			onRequestClose: onClose,
			className: "mlgp-insert-modal",
			size: "medium",
		},
			el("div", { className: "mlgp-insert-modal__body" },
				items.length === 0
					? el("p", { className: "mlgp-insert-modal__empty" }, emptyMsg)
					: el(SelectControl, {
						label: isAlbum ? __("Album", "ml-gallery-pro") : __("Galeria", "ml-gallery-pro"),
						value: selected,
						options: getEntityOptions(items, "— " + __("Selecionar", "ml-gallery-pro") + " —"),
						onChange: function (val) { setSelected(Number(val) || 0); },
					})
			),
			el("div", { className: "mlgp-insert-modal__footer" },
				el(Button, {
					variant: "primary",
					disabled: !canConfirm,
					onClick: function () {
						if (canConfirm) {
							onSelect({ type, id: Number(selected) });
							onClose();
						}
					},
				}, config.strings.insertAction || "Inserir"),
				el(Button, {
					variant: "secondary",
					onClick: onClose,
				}, __("Cancelar", "ml-gallery-pro"))
			)
		);
	}

	// ─── Block registration ──────────────────────────────────────────────────────

	registerBlockType("ml-gallery-pro/gallery", {
		apiVersion: 2,
		title: config.strings.blockTitle || "ML Gallery Pro",
		description: config.strings.blockDescription || "Insira galerias, albuns e galerias por tag com preview nativo no editor.",
		icon: "format-gallery",
		category: "media",
		keywords: ["galeria", "album", "tag", "shortcode", "gallery", "photos", "images", "ml gallery pro"],
		attributes: {
			sourceType:       { type: "string", default: "gallery" },
			entityId:         { type: "number", default: 0 },
			tag:              { type: "string", default: "" },
			displayType:      { type: "string", default: "" },
			albumDisplayType: { type: "string", default: "" },
			columnsDesktop:   { type: "string", default: "" },
			columnsTablet:    { type: "string", default: "" },
			columnsMobile:    { type: "string", default: "" },
			gap:              { type: "string", default: "" },
			rowHeight:        { type: "string", default: "" },
			roundedCorners:   { type: "string", default: "" },
			pagination:       { type: "string", default: "" },
			perPage:          { type: "string", default: "" },
			showTitles:       { type: "string", default: "" },
			showCaptions:     { type: "string", default: "" },
			autoplay:         { type: "string", default: "" },
			interval:         { type: "string", default: "" },
			showArrows:       { type: "string", default: "" },
			showThumbs:       { type: "string", default: "" },
			headingFontSize:  { type: "string", default: "" },
			headingColor:     { type: "string", default: "" },
			itemTitleFontSize:{ type: "string", default: "" },
			itemTitleColor:   { type: "string", default: "" },
		},
		supports: {
			html:     false,
			multiple: true,
			align:    ["wide", "full"],
		},

		edit: function (props) {
			const { attributes, setAttributes } = props;
			const [modalType, setModalType] = useState(null); // null | 'gallery' | 'album'

			// useBlockProps gives Gutenberg the block wrapper it needs for
			// selection, toolbar, deletion, drag-and-drop, etc.
			const blockProps = useBlockProps
				? useBlockProps({ className: "mlgp-block-editor__wrapper" })
				: { className: "mlgp-block-editor__wrapper" };

			const sourceType     = attributes.sourceType || "gallery";
			const isAlbum        = sourceType === "album";
			const isTag          = sourceType === "tag";
			const isGalleryLike  = !isAlbum;
			const displayType    = attributes.displayType || "";
			const hasSelection   = (isTag && String(attributes.tag || "").trim())
				|| (!isTag && Number(attributes.entityId || 0) > 0);

			const showGalleryLayoutControls = isGalleryLike;
			const showSlideshowControls     = isGalleryLike && (displayType === "slideshow" || displayType === "filmstrip");
			const showJustifiedControls     = isGalleryLike && displayType === "justified";

			// Apply selection from modal — updates THIS block's attributes,
			// never inserts a new block.
			function handleSelect({ type: selectedType, id }) {
				setAttributes({
					sourceType:       selectedType === "album" ? "album" : "gallery",
					entityId:         id,
					tag:              "",
					displayType:      "",
					albumDisplayType: "",
				});
				setModalType(null);
			}

			function openGalleryModal() { setModalType("gallery"); }
			function openAlbumModal()   { setModalType("album");   }
			function closeModal()        { setModalType(null);      }

			// Label for the "change" toolbar button (shown when something is selected)
			const changeLabel = isAlbum
				? (config.strings.changeAlbumButton  || "Change Album")
				: (config.strings.changeGalleryButton || "Change Gallery");

			// Canvas content: Placeholder (empty state) or preview (selected state)
			let canvasContent;
			if (!hasSelection) {
				// ── Empty state: show prominent Add Gallery / Add Album buttons ──
				canvasContent = el(Placeholder, {
					icon: "format-gallery",
					label: config.strings.placeholderTitle || "ML Gallery Pro",
					instructions: config.strings.placeholderDescription || "Escolha uma galeria ou album para inserir.",
				},
					el("div", { className: "mlgp-block-editor__insert-buttons" },
						el(Button, {
							variant: "primary",
							className: "mlgp-block-editor__insert-btn",
							onClick: openGalleryModal,
						},
							el("span", { className: "dashicons dashicons-format-gallery", "aria-hidden": "true" }),
							" ",
							config.strings.addGalleryButton || "Add Gallery"
						),
						el(Button, {
							variant: "secondary",
							className: "mlgp-block-editor__insert-btn",
							onClick: openAlbumModal,
						},
							el("span", { className: "dashicons dashicons-images-alt2", "aria-hidden": "true" }),
							" ",
							config.strings.addAlbumButton || "Add Album"
						)
					)
				);
			} else if (ServerSideRender) {
				// ── Preview state: server-side rendered gallery ──
				// Wrapped in a div with pointer-events:none so clicks reach the
				// Gutenberg block wrapper rather than the preview content.
				canvasContent = el("div", { className: "mlgp-block-editor__preview" },
					el("div", { className: "mlgp-block-editor__preview-label" },
						(config.strings.previewLabel || "Preview"),
						el(Button, {
							variant: "link",
							isSmall: true,
							className: "mlgp-block-editor__preview-change",
							onClick: isAlbum ? openAlbumModal : openGalleryModal,
						}, changeLabel)
					),
					el("div", { className: "mlgp-block-editor__preview-body" },
						el(ServerSideRender, {
							block: "ml-gallery-pro/gallery",
							attributes,
						})
					)
				);
			} else {
				// ServerSideRender unavailable — show a text summary
				canvasContent = el("div", { className: "mlgp-block-editor__preview mlgp-block-editor__preview--text" },
					el("p", {},
						isAlbum
							? sprintf(__("Album #%d", "ml-gallery-pro"), Number(attributes.entityId))
							: sprintf(__("Galeria #%d", "ml-gallery-pro"), Number(attributes.entityId))
					),
					el(Button, { variant: "secondary", isSmall: true, onClick: isAlbum ? openAlbumModal : openGalleryModal }, changeLabel)
				);
			}

			return el("div", blockProps,

				// ── BlockControls toolbar ─────────────────────────────────────
				// Only show "Change Gallery / Album" when something is already selected.
				// When empty, the Placeholder buttons guide the user instead.
				hasSelection
					? el(BlockControls, {},
						el(ToolbarGroup, {},
							el(ToolbarButton, {
								icon: isAlbum ? "images-alt2" : "format-gallery",
								label: changeLabel,
								title: changeLabel,
								onClick: isAlbum ? openAlbumModal : openGalleryModal,
							})
						)
					)
					: null,

				// ── InspectorControls sidebar ─────────────────────────────────
				el(InspectorControls, {},
					el(PanelBody, {
						title: config.strings.sourcePanel || "Origem",
						initialOpen: true,
					},
						el(SelectControl, {
							label: __("Tipo de origem", "ml-gallery-pro"),
							value: sourceType,
							options: config.sourceTypes || [],
							onChange: function (value) {
								setAttributes({
									sourceType:       value || "gallery",
									entityId:         0,
									tag:              "",
									displayType:      "",
									albumDisplayType: "",
								});
							},
						}),
						isTag
							? el(Fragment, {},
								(config.tags || []).length
									? el(SelectControl, {
										label: __("Tag", "ml-gallery-pro"),
										value: attributes.tag || "",
										options: getEntityOptions(config.tags || [], config.strings.noTags || "Nenhuma tag encontrada."),
										onChange: function (value) { setAttributes({ tag: value || "" }); },
									})
									: el(Notice, { status: "warning", isDismissible: false },
										config.strings.noTags || "Nenhuma tag encontrada."
									),
								el(TextControl, {
									label: __("Slug manual da tag", "ml-gallery-pro"),
									value: attributes.tag || "",
									help: config.strings.optionalControl || "Opcional.",
									onChange: function (value) {
										setAttributes({ tag: String(value || "").toLowerCase().trim() });
									},
								})
							)
							: el(Fragment, {},
								el(SelectControl, {
									label: isAlbum ? __("Album", "ml-gallery-pro") : __("Galeria", "ml-gallery-pro"),
									value: Number(attributes.entityId || 0),
									options: getEntityOptions(
										isAlbum ? (config.albums || []) : (config.galleries || []),
										isAlbum
											? (config.strings.noAlbums || "Nenhum album encontrado.")
											: (config.strings.noGalleries || "Nenhuma galeria encontrada.")
									),
									onChange: function (value) {
										setAttributes({ entityId: Number(value || 0) });
									},
								}),
								// Sidebar quick-open buttons (contextual: gallery vs album)
								el("div", { className: "mlgp-block-editor__sidebar-insert" },
									el(Button, {
										variant: "secondary",
										isSmall: true,
										onClick: openGalleryModal,
									}, config.strings.addGalleryButton || "Add Gallery"),
									el(Button, {
										variant: "secondary",
										isSmall: true,
										onClick: openAlbumModal,
									}, config.strings.addAlbumButton || "Add Album")
								)
							)
					),

					el(PanelBody, {
						title: config.strings.layoutPanel || "Layout",
						initialOpen: false,
					},
						isAlbum
							? el(SelectControl, {
								label: __("Modo do album", "ml-gallery-pro"),
								value: attributes.albumDisplayType || "",
								options: [{ value: "", label: config.strings.defaultOption || "Usar padrao da galeria/plugin" }].concat(config.albumDisplayTypes || []),
								onChange: function (value) { setAttributes({ albumDisplayType: value || "" }); },
							})
							: el(SelectControl, {
								label: __("Tipo de exibicao", "ml-gallery-pro"),
								value: attributes.displayType || "",
								options: [{ value: "", label: config.strings.defaultOption || "Usar padrao da galeria/plugin" }].concat(config.galleryDisplayTypes || []),
								onChange: function (value) { setAttributes({ displayType: value || "" }); },
							}),
						showGalleryLayoutControls
							? el(Fragment, {},
								renderOptionalNumberControl(__("Colunas desktop", "ml-gallery-pro"), "columnsDesktop", attributes, setAttributes, config.strings.optionalControl, 1, 8),
								renderOptionalNumberControl(__("Colunas tablet", "ml-gallery-pro"),  "columnsTablet",  attributes, setAttributes, config.strings.optionalControl, 1, 6),
								renderOptionalNumberControl(__("Colunas mobile", "ml-gallery-pro"),   "columnsMobile",  attributes, setAttributes, config.strings.optionalControl, 1, 4),
								renderOptionalNumberControl(__("Espacamento", "ml-gallery-pro"),      "gap",            attributes, setAttributes, config.strings.optionalControl, 0, 60),
								showJustifiedControls
									? renderOptionalNumberControl(__("Altura da linha", "ml-gallery-pro"), "rowHeight", attributes, setAttributes, config.strings.optionalControl, 120, 520)
									: null,
								el(SelectControl, {
									label: __("Cantos arredondados", "ml-gallery-pro"),
									value: attributes.roundedCorners || "",
									options: getToggleOptions(),
									onChange: function (value) { setAttributes({ roundedCorners: value || "" }); },
								})
							)
							: el(SelectControl, {
								label: __("Cantos arredondados", "ml-gallery-pro"),
								value: attributes.roundedCorners || "",
								options: getToggleOptions(),
								onChange: function (value) { setAttributes({ roundedCorners: value || "" }); },
							})
					),

					showGalleryLayoutControls
						? el(PanelBody, {
							title: config.strings.contentPanel || "Conteudo",
							initialOpen: false,
						},
							el(SelectControl, {
								label: __("Titulos", "ml-gallery-pro"),
								value: attributes.showTitles || "",
								options: getToggleOptions(),
								onChange: function (value) { setAttributes({ showTitles: value || "" }); },
							}),
							el(SelectControl, {
								label: __("Legendas", "ml-gallery-pro"),
								value: attributes.showCaptions || "",
								options: getToggleOptions(),
								onChange: function (value) { setAttributes({ showCaptions: value || "" }); },
							}),
							el(SelectControl, {
								label: __("Paginacao", "ml-gallery-pro"),
								value: attributes.pagination || "",
								options: getToggleOptions(),
								onChange: function (value) { setAttributes({ pagination: value || "" }); },
							}),
							renderOptionalNumberControl(__("Itens por pagina", "ml-gallery-pro"), "perPage", attributes, setAttributes, config.strings.optionalControl, 1, 5000)
						)
						: null,

					showSlideshowControls
						? el(PanelBody, {
							title: config.strings.navigationPanel || "Navegacao",
							initialOpen: false,
						},
							el(SelectControl, {
								label: __("Setas", "ml-gallery-pro"),
								value: attributes.showArrows || "",
								options: getToggleOptions(),
								onChange: function (value) { setAttributes({ showArrows: value || "" }); },
							}),
							el(SelectControl, {
								label: __("Miniaturas", "ml-gallery-pro"),
								value: attributes.showThumbs || "",
								options: getToggleOptions(),
								onChange: function (value) { setAttributes({ showThumbs: value || "" }); },
							}),
							el(SelectControl, {
								label: __("Autoplay", "ml-gallery-pro"),
								value: attributes.autoplay || "",
								options: getToggleOptions(),
								onChange: function (value) { setAttributes({ autoplay: value || "" }); },
							}),
							renderOptionalNumberControl(__("Intervalo (ms)", "ml-gallery-pro"), "interval", attributes, setAttributes, config.strings.optionalControl, 1500, 20000)
						)
						: null,

					showGalleryLayoutControls
						? el(PanelBody, {
							title: config.strings.typographyPanel || "Tipografia",
							initialOpen: false,
						},
							renderOptionalNumberControl(__("Tamanho do titulo principal", "ml-gallery-pro"), "headingFontSize",    attributes, setAttributes, config.strings.optionalControl, 20, 96),
							renderOptionalColorControl( __("Cor do titulo principal", "ml-gallery-pro"),      "headingColor",       attributes, setAttributes),
							renderOptionalNumberControl(__("Tamanho do titulo do item", "ml-gallery-pro"),   "itemTitleFontSize",  attributes, setAttributes, config.strings.optionalControl, 10, 72),
							renderOptionalColorControl( __("Cor do titulo do item", "ml-gallery-pro"),        "itemTitleColor",     attributes, setAttributes)
						)
						: null
				),

				// ── Canvas ────────────────────────────────────────────────────
				canvasContent,

				// ── Modal (rendered inside block wrapper, outside preview) ────
				modalType
					? el(MLGPSelectModal, {
						type: modalType,
						onSelect: handleSelect,
						onClose: closeModal,
					})
					: null
			);
		},

		save: function () {
			// Dynamic block — PHP renders frontend output.
			return null;
		},
	});
})(window.wp, window.MLGPBlockEditor);
