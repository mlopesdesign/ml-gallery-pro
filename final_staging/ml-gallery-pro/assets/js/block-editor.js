(function (wp, config) {
	if (!wp || !wp.blocks || !config) {
		return;
	}

	const { registerBlockType } = wp.blocks;
	const { createElement: el, Fragment } = wp.element;
	const { InspectorControls } = wp.blockEditor || wp.editor;
	const {
		BaseControl,
		Button,
		Notice,
		PanelBody,
		Placeholder,
		SelectControl,
		TextControl,
		ColorPalette,
	} = wp.components;
	const { __ } = wp.i18n;
	const ServerSideRenderModule = wp.serverSideRender;
	const ServerSideRender = ServerSideRenderModule && ServerSideRenderModule.default
		? ServerSideRenderModule.default
		: ServerSideRenderModule;

	function clampNumber(value, min, max) {
		const normalized = String(value || "").trim();

		if (!normalized) {
			return "";
		}

		const parsed = Number.parseInt(normalized, 10);

		if (!Number.isFinite(parsed)) {
			return "";
		}

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
					const next = clampNumber(value, min, max);
					setAttributes({ [attributeName]: next });
				},
			}),
			attributes[attributeName]
				? el(Button, {
					variant: "secondary",
					isSmall: true,
					onClick: function () {
						setAttributes({ [attributeName]: "" });
					},
				}, config.strings.clearNumber || "Usar valor padrao")
				: null
		);
	}

	function renderOptionalColorControl(label, attributeName, attributes, setAttributes) {
		return el(BaseControl, { label },
			el("div", { className: "mlgp-block-editor__color" },
				el(ColorPalette, {
					value: attributes[attributeName] || undefined,
					onChange: function (value) {
						setAttributes({ [attributeName]: value || "" });
					},
					clearable: false,
				}),
				el("div", { className: "mlgp-block-editor__color-actions" },
					el("code", {}, attributes[attributeName] || "padrao"),
					el(Button, {
						variant: "secondary",
						isSmall: true,
						onClick: function () {
							setAttributes({ [attributeName]: "" });
						},
					}, config.strings.clearColor || "Usar cor padrao")
				)
			)
		);
	}

	function renderEmptyState(attributes) {
		let label = config.strings.chooseEntity || "Selecione um item para gerar a preview.";

		if (attributes.sourceType === "gallery" && !(config.galleries || []).length) {
			label = config.strings.noGalleries || "Nenhuma galeria encontrada.";
		} else if (attributes.sourceType === "album" && !(config.albums || []).length) {
			label = config.strings.noAlbums || "Nenhum album encontrado.";
		} else if (attributes.sourceType === "tag" && !(config.tags || []).length) {
			label = config.strings.noTags || "Nenhuma tag encontrada.";
		}

		return el(Placeholder, {
			icon: "format-gallery",
			label: config.strings.placeholderTitle || "ML Gallery Pro",
			instructions: config.strings.placeholderDescription || label,
		}, el("p", { className: "mlgp-block-editor__empty-copy" }, label));
	}

	registerBlockType("ml-gallery-pro/gallery", {
		apiVersion: 2,
		title: config.strings.blockTitle || "ML Gallery Pro",
		description: config.strings.blockDescription || "Insira galerias, albuns e galerias por tag com preview nativo no editor.",
		icon: "format-gallery",
		category: "widgets",
		keywords: ["galeria", "album", "tag", "shortcode"],
		attributes: {
			sourceType: { type: "string", default: "gallery" },
			entityId: { type: "number", default: 0 },
			tag: { type: "string", default: "" },
			displayType: { type: "string", default: "" },
			albumDisplayType: { type: "string", default: "" },
			columnsDesktop: { type: "string", default: "" },
			columnsTablet: { type: "string", default: "" },
			columnsMobile: { type: "string", default: "" },
			gap: { type: "string", default: "" },
			rowHeight: { type: "string", default: "" },
			roundedCorners: { type: "string", default: "" },
			pagination: { type: "string", default: "" },
			perPage: { type: "string", default: "" },
			showTitles: { type: "string", default: "" },
			showCaptions: { type: "string", default: "" },
			autoplay: { type: "string", default: "" },
			interval: { type: "string", default: "" },
			showArrows: { type: "string", default: "" },
			showThumbs: { type: "string", default: "" },
			headingFontSize: { type: "string", default: "" },
			headingColor: { type: "string", default: "" },
			itemTitleFontSize: { type: "string", default: "" },
			itemTitleColor: { type: "string", default: "" },
		},
		supports: {
			html: false,
			multiple: true,
		},
		edit: function (props) {
			const { attributes, setAttributes } = props;
			const sourceType = attributes.sourceType || "gallery";
			const isAlbum = sourceType === "album";
			const isTag = sourceType === "tag";
			const isGalleryLike = !isAlbum;
			const displayType = attributes.displayType || "";
			const showGalleryLayoutControls = isGalleryLike;
			const showSlideshowControls = isGalleryLike && (displayType === "slideshow" || displayType === "filmstrip");
			const showJustifiedControls = isGalleryLike && displayType === "justified";
			const hasSelection = (isTag && String(attributes.tag || "").trim()) || (!isTag && Number(attributes.entityId || 0) > 0);

			let preview = renderEmptyState(attributes);

			if (hasSelection && ServerSideRender) {
				preview = el("div", { className: "mlgp-block-editor__preview" },
					el("div", { className: "mlgp-block-editor__preview-label" }, config.strings.previewLabel || "Preview do bloco"),
					el(ServerSideRender, {
						block: "ml-gallery-pro/gallery",
						attributes,
					})
				);
			}

			return el(Fragment, {},
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
									sourceType: value || "gallery",
									entityId: 0,
									tag: "",
									displayType: "",
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
										onChange: function (value) {
											setAttributes({ tag: value || "" });
										},
									})
									: el(Notice, { status: "warning", isDismissible: false }, config.strings.noTags || "Nenhuma tag encontrada."),
								el(TextControl, {
									label: __("Slug manual da tag", "ml-gallery-pro"),
									value: attributes.tag || "",
									help: config.strings.optionalControl || "Opcional. Se vazio, o bloco usa a configuracao da galeria ou o padrao global do plugin.",
									onChange: function (value) {
										setAttributes({ tag: String(value || "").toLowerCase().trim() });
									},
								})
							)
							: el(SelectControl, {
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
							})
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
								onChange: function (value) {
									setAttributes({ albumDisplayType: value || "" });
								},
							})
							: el(SelectControl, {
								label: __("Tipo de exibicao", "ml-gallery-pro"),
								value: attributes.displayType || "",
								options: [{ value: "", label: config.strings.defaultOption || "Usar padrao da galeria/plugin" }].concat(config.galleryDisplayTypes || []),
								onChange: function (value) {
									setAttributes({ displayType: value || "" });
								},
							}),
						showGalleryLayoutControls
							? el(Fragment, {},
								renderOptionalNumberControl(__("Colunas desktop", "ml-gallery-pro"), "columnsDesktop", attributes, setAttributes, config.strings.optionalControl, 1, 8),
								renderOptionalNumberControl(__("Colunas tablet", "ml-gallery-pro"), "columnsTablet", attributes, setAttributes, config.strings.optionalControl, 1, 6),
								renderOptionalNumberControl(__("Colunas mobile", "ml-gallery-pro"), "columnsMobile", attributes, setAttributes, config.strings.optionalControl, 1, 4),
								renderOptionalNumberControl(__("Espacamento", "ml-gallery-pro"), "gap", attributes, setAttributes, config.strings.optionalControl, 0, 60),
								showJustifiedControls
									? renderOptionalNumberControl(__("Altura da linha", "ml-gallery-pro"), "rowHeight", attributes, setAttributes, config.strings.optionalControl, 120, 520)
									: null,
								el(SelectControl, {
									label: __("Cantos arredondados", "ml-gallery-pro"),
									value: attributes.roundedCorners || "",
									options: getToggleOptions(),
									onChange: function (value) {
										setAttributes({ roundedCorners: value || "" });
									},
								})
							)
							: el(SelectControl, {
								label: __("Cantos arredondados", "ml-gallery-pro"),
								value: attributes.roundedCorners || "",
								options: getToggleOptions(),
								onChange: function (value) {
									setAttributes({ roundedCorners: value || "" });
								},
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
								onChange: function (value) {
									setAttributes({ showTitles: value || "" });
								},
							}),
							el(SelectControl, {
								label: __("Legendas", "ml-gallery-pro"),
								value: attributes.showCaptions || "",
								options: getToggleOptions(),
								onChange: function (value) {
									setAttributes({ showCaptions: value || "" });
								},
							}),
							el(SelectControl, {
								label: __("Paginacao", "ml-gallery-pro"),
								value: attributes.pagination || "",
								options: getToggleOptions(),
								onChange: function (value) {
									setAttributes({ pagination: value || "" });
								},
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
								onChange: function (value) {
									setAttributes({ showArrows: value || "" });
								},
							}),
							el(SelectControl, {
								label: __("Miniaturas", "ml-gallery-pro"),
								value: attributes.showThumbs || "",
								options: getToggleOptions(),
								onChange: function (value) {
									setAttributes({ showThumbs: value || "" });
								},
							}),
							el(SelectControl, {
								label: __("Autoplay", "ml-gallery-pro"),
								value: attributes.autoplay || "",
								options: getToggleOptions(),
								onChange: function (value) {
									setAttributes({ autoplay: value || "" });
								},
							}),
							renderOptionalNumberControl(__("Intervalo (ms)", "ml-gallery-pro"), "interval", attributes, setAttributes, config.strings.optionalControl, 1500, 20000)
						)
						: null,
					showGalleryLayoutControls
						? el(PanelBody, {
							title: config.strings.typographyPanel || "Tipografia",
							initialOpen: false,
						},
							renderOptionalNumberControl(__("Tamanho do titulo principal", "ml-gallery-pro"), "headingFontSize", attributes, setAttributes, config.strings.optionalControl, 20, 96),
							renderOptionalColorControl(__("Cor do titulo principal", "ml-gallery-pro"), "headingColor", attributes, setAttributes),
							renderOptionalNumberControl(__("Tamanho do titulo do item", "ml-gallery-pro"), "itemTitleFontSize", attributes, setAttributes, config.strings.optionalControl, 10, 72),
							renderOptionalColorControl(__("Cor do titulo do item", "ml-gallery-pro"), "itemTitleColor", attributes, setAttributes)
						)
						: null
				),
				preview
			);
		},
		save: function () {
			return null;
		},
	});
})(window.wp, window.MLGPBlockEditor);
