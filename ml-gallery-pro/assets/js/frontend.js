/**
 * ML Gallery Pro — Frontend runtime
 *
 * v0.26.12: Lightbox robusto contra CSS/lazy-load do tema e fallback de imagem.
 */
(function () {

	// ── Lightbox state ──────────────────────────────────────────────────────────
	let lightbox = null;
	let lightboxImage = null;
	let lightboxCaption = null;
	let lightboxCounter = null;
	let lightboxPrev = null;
	let lightboxNext = null;
	let lightboxLoader = null;
	let lightboxError = null;

	// Currently active gallery items ([{ url, caption }]) and index
	let currentItems = [];
	let currentIndex = 0;

	// Touch/swipe tracking
	let touchStartX = 0;
	let touchStartY = 0;

	// ── Build lightbox DOM (once) ───────────────────────────────────────────────
	function ensureLightbox() {
		if (lightbox) {
			return;
		}

		lightbox = document.createElement("div");
		lightbox.className = "mlgp-lightbox";
		lightbox.setAttribute("role", "dialog");
		lightbox.setAttribute("aria-modal", "true");
		lightbox.setAttribute("aria-label", "Lightbox");
		lightbox.innerHTML =
			'<div class="mlgp-lightbox__dialog">' +
				'<button type="button" class="mlgp-lightbox__close" aria-label="Fechar">' +
					'<svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">' +
						'<line x1="2" y1="2" x2="18" y2="18" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>' +
						'<line x1="18" y1="2" x2="2" y2="18" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>' +
					'</svg>' +
				'</button>' +
				'<button type="button" class="mlgp-lightbox__prev" aria-label="Anterior">' +
					'<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">' +
						'<polyline points="15 18 9 12 15 6" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>' +
					'</svg>' +
				'</button>' +
				'<div class="mlgp-lightbox__stage">' +
					'<div class="mlgp-lightbox__loader" aria-hidden="true"></div>' +
					'<img class="mlgp-lightbox__image" alt="" decoding="async">' +
					'<div class="mlgp-lightbox__error" role="status">Não foi possível carregar esta imagem.</div>' +
				'</div>' +
				'<button type="button" class="mlgp-lightbox__next" aria-label="Próximo">' +
					'<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">' +
						'<polyline points="9 18 15 12 9 6" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>' +
					'</svg>' +
				'</button>' +
				'<div class="mlgp-lightbox__footer">' +
					'<div class="mlgp-lightbox__caption"></div>' +
					'<div class="mlgp-lightbox__counter"></div>' +
				'</div>' +
			'</div>';

		document.body.appendChild(lightbox);

		lightboxImage   = lightbox.querySelector(".mlgp-lightbox__image");
		lightboxCaption = lightbox.querySelector(".mlgp-lightbox__caption");
		lightboxCounter = lightbox.querySelector(".mlgp-lightbox__counter");
		lightboxPrev    = lightbox.querySelector(".mlgp-lightbox__prev");
		lightboxNext    = lightbox.querySelector(".mlgp-lightbox__next");
		lightboxLoader  = lightbox.querySelector(".mlgp-lightbox__loader");
		lightboxError   = lightbox.querySelector(".mlgp-lightbox__error");

		lightboxImage.addEventListener("load", function () {
			lightbox.classList.remove("is-loading", "has-error");
		});

		lightboxImage.addEventListener("error", function () {
			const fallbackUrl = lightboxImage.getAttribute("data-fallback-src") || "";
			const attemptedFallback = lightboxImage.getAttribute("data-fallback-attempted") === "1";

			if (fallbackUrl && !attemptedFallback && fallbackUrl !== lightboxImage.currentSrc && fallbackUrl !== lightboxImage.src) {
				lightboxImage.setAttribute("data-fallback-attempted", "1");
				lightboxImage.src = fallbackUrl;
				return;
			}

			lightbox.classList.remove("is-loading");
			lightbox.classList.add("has-error");
		});

		// Close on backdrop click or close button
		lightbox.addEventListener("click", function (event) {
			if (
				event.target === lightbox ||
				event.target.closest(".mlgp-lightbox__close")
			) {
				closeLightbox();
			}
		});

		// Prev / Next
		lightboxPrev.addEventListener("click", function (event) {
			event.stopPropagation();
			navigate(-1);
		});

		lightboxNext.addEventListener("click", function (event) {
			event.stopPropagation();
			navigate(1);
		});

		// Touch / swipe
		lightbox.addEventListener("touchstart", function (event) {
			touchStartX = event.touches[0].clientX;
			touchStartY = event.touches[0].clientY;
		}, { passive: true });

		lightbox.addEventListener("touchend", function (event) {
			const dx = event.changedTouches[0].clientX - touchStartX;
			const dy = event.changedTouches[0].clientY - touchStartY;

			// Only handle horizontal swipes longer than 40 px and more horizontal than vertical
			if (Math.abs(dx) > 40 && Math.abs(dx) > Math.abs(dy)) {
				navigate(dx < 0 ? 1 : -1);
			}
		}, { passive: true });
	}

	// ── Collect all lightbox items inside the same gallery wrapper ──────────────
	function collectItems(clickedLink) {
		// Walk up to find the closest gallery container
		const galleryWrapper =
			clickedLink.closest("[data-mlgp-gallery-id]") ||
			clickedLink.closest(".mlgp-grid") ||
			clickedLink.closest(".mlgp-frontend") ||
			clickedLink.parentElement;

		const links = Array.from(
			galleryWrapper
				? galleryWrapper.querySelectorAll("a[data-mlgp-lightbox]")
				: document.querySelectorAll("a[data-mlgp-lightbox]")
		);

		return {
			items: links.map(function (link) {
				return {
					url:     link.getAttribute("href") || "",
					caption: link.getAttribute("data-caption") || "",
					fallbackUrl: link.getAttribute("data-mlgp-preview-src") || "",
				};
			}),
			index: links.indexOf(clickedLink),
		};
	}

	// ── Show a specific index from currentItems ─────────────────────────────────
	function showIndex(index) {
		const total = currentItems.length;

		if (!total) {
			return;
		}

		// Wrap around
		currentIndex = ((index % total) + total) % total;

		const item = currentItems[currentIndex];

		lightbox.classList.remove("has-error");
		lightbox.classList.add("is-loading");
		lightboxImage.removeAttribute("src");
		lightboxImage.setAttribute("data-fallback-src", item.fallbackUrl || "");
		lightboxImage.setAttribute("data-fallback-attempted", "0");
		lightboxImage.alt           = item.caption || "";
		lightboxCaption.textContent = item.caption || "";

		// Definir o src por último evita que load/error disparem antes do estado visual.
		lightboxImage.src = item.url;
		lightboxCounter.textContent = total > 1 ? (currentIndex + 1) + " / " + total : "";

		// Hide navigation arrows when only one item
		const showNav = total > 1;
		lightboxPrev.hidden = !showNav;
		lightboxNext.hidden = !showNav;
	}

	// ── Navigate prev/next ──────────────────────────────────────────────────────
	function navigate(direction) {
		showIndex(currentIndex + direction);
	}

	// ── Open lightbox ───────────────────────────────────────────────────────────
	function openLightbox(url, caption, clickedLink) {
		if (!url) {
			return;
		}

		ensureLightbox();

		// Collect sibling items if a link element was provided
		if (clickedLink) {
			const result = collectItems(clickedLink);
			currentItems = result.items;
			currentIndex = result.index >= 0 ? result.index : 0;
		} else {
			// Fallback: single item mode
			currentItems = [{ url: url, caption: caption || "", fallbackUrl: "" }];
			currentIndex = 0;
		}

		showIndex(currentIndex);
		lightbox.classList.add("is-active");
		document.body.classList.add("mlgp-lightbox-open");
	}

	// ── Close lightbox ──────────────────────────────────────────────────────────
	function closeLightbox() {
		if (!lightbox) {
			return;
		}

		lightbox.classList.remove("is-active");
		document.body.classList.remove("mlgp-lightbox-open");
		lightboxImage.removeAttribute("src");
		lightboxImage.removeAttribute("data-fallback-src");
		lightboxImage.removeAttribute("data-fallback-attempted");
		lightbox.classList.remove("is-loading", "has-error");
		lightboxCaption.textContent = "";
		lightboxCounter.textContent = "";
		currentItems = [];
		currentIndex = 0;
	}

	// ── Keyboard navigation ─────────────────────────────────────────────────────
	document.addEventListener("keydown", function (event) {
		if (!lightbox || !lightbox.classList.contains("is-active")) {
			return;
		}

		switch (event.key) {
			case "Escape":
				closeLightbox();
				break;
			case "ArrowLeft":
				navigate(-1);
				break;
			case "ArrowRight":
				navigate(1);
				break;
		}
	});

	// ── Lightbox click delegation ───────────────────────────────────────────────
	document.addEventListener("click", function (event) {
		const link = event.target.closest("a[data-mlgp-lightbox]");

		if (!link) {
			return;
		}

		event.preventDefault();
		openLightbox(
			link.getAttribute("href"),
			link.getAttribute("data-caption"),
			link
		);
	});

	// ── Slideshow setup ─────────────────────────────────────────────────────────
	function setupSlideshow(node) {
		if (!node || node.dataset.mlgpReady === "1") {
			return;
		}

		const slides    = Array.from(node.querySelectorAll("[data-mlgp-slide]"));
		const thumbs    = Array.from(node.querySelectorAll("[data-mlgp-slide-thumb]"));
		const prev      = node.querySelector("[data-mlgp-slide-prev]");
		const next      = node.querySelector("[data-mlgp-slide-next]");
		const thumbTrack = node.querySelector("[data-mlgp-slide-thumb-track]");
		const thumbPrev  = node.querySelector("[data-mlgp-slide-thumb-prev]");
		const thumbNext  = node.querySelector("[data-mlgp-slide-thumb-next]");
		const autoplay   = node.dataset.autoplay === "1";
		const interval   = Number.parseInt(node.dataset.interval || "4000", 10) || 4000;
		let activeIndex  = 0;
		let timer        = 0;

		if (!slides.length) {
			return;
		}

		function updateThumbRail() {
			if (!thumbTrack || !thumbPrev || !thumbNext) {
				return;
			}

			const maxScroll     = Math.max(0, thumbTrack.scrollWidth - thumbTrack.clientWidth);
			const currentScroll = Math.max(0, thumbTrack.scrollLeft);
			const isScrollable  = maxScroll > 8;

			thumbPrev.disabled = !isScrollable || currentScroll <= 4;
			thumbNext.disabled = !isScrollable || currentScroll >= maxScroll - 4;
			thumbPrev.classList.toggle("is-hidden", !isScrollable);
			thumbNext.classList.toggle("is-hidden", !isScrollable);
		}

		function revealActiveThumb() {
			if (!thumbTrack || !thumbs[activeIndex]) {
				return;
			}

			const thumb      = thumbs[activeIndex];
			const targetLeft = Math.max(0, thumb.offsetLeft - ((thumbTrack.clientWidth - thumb.offsetWidth) / 2));
			thumbTrack.scrollTo({ left: targetLeft, behavior: "smooth" });
		}

		function stepThumbRail(direction) {
			if (!thumbTrack) {
				return;
			}

			const fallbackStep = thumbs[0] ? thumbs[0].offsetWidth * 3 : 264;
			const distance     = Math.max(thumbTrack.clientWidth * 0.82, fallbackStep);
			thumbTrack.scrollBy({ left: direction * distance, behavior: "smooth" });
		}

		function setActive(nextIndex) {
			activeIndex = nextIndex < 0
				? slides.length - 1
				: (nextIndex >= slides.length ? 0 : nextIndex);

			slides.forEach(function (slide, index) {
				const active = index === activeIndex;
				slide.hidden = !active;
				slide.classList.toggle("is-active", active);
			});

			thumbs.forEach(function (thumb, index) {
				thumb.classList.toggle("is-active", index === activeIndex);
			});

			revealActiveThumb();
			updateThumbRail();
		}

		function stopAutoplay() {
			if (timer) {
				window.clearInterval(timer);
				timer = 0;
			}
		}

		function startAutoplay() {
			stopAutoplay();

			if (!autoplay || slides.length < 2) {
				return;
			}

			timer = window.setInterval(function () {
				setActive(activeIndex + 1);
			}, Math.max(1500, interval));
		}

		function goTo(index) {
			setActive(index);
			startAutoplay();
		}

		if (prev) { prev.addEventListener("click", function () { goTo(activeIndex - 1); }); }
		if (next) { next.addEventListener("click", function () { goTo(activeIndex + 1); }); }

		if (thumbPrev) { thumbPrev.addEventListener("click", function () { stepThumbRail(-1); }); }
		if (thumbNext) { thumbNext.addEventListener("click", function () { stepThumbRail(1); }); }

		thumbs.forEach(function (thumb) {
			thumb.addEventListener("click", function () {
				goTo(Number.parseInt(thumb.getAttribute("data-mlgp-slide-thumb") || "0", 10));
			});
		});

		if (thumbTrack) {
			thumbTrack.addEventListener("scroll", updateThumbRail, { passive: true });
			window.addEventListener("resize", updateThumbRail);
		}

		node.addEventListener("mouseenter", stopAutoplay);
		node.addEventListener("mouseleave", startAutoplay);
		node.dataset.mlgpReady = "1";
		setActive(0);
		updateThumbRail();
		startAutoplay();
	}

	// ── Image browser setup ─────────────────────────────────────────────────────
	function setupImageBrowser(node) {
		if (!node || node.dataset.mlgpReady === "1") {
			return;
		}

		const slides = Array.from(node.querySelectorAll("[data-mlgp-browser-slide]"));
		const thumbs = Array.from(node.querySelectorAll("[data-mlgp-browser-thumb]"));
		const prev   = node.querySelector("[data-mlgp-browser-prev]");
		const next   = node.querySelector("[data-mlgp-browser-next]");
		let activeIndex = 0;

		if (!slides.length) {
			return;
		}

		function setActive(nextIndex) {
			activeIndex = nextIndex < 0
				? slides.length - 1
				: (nextIndex >= slides.length ? 0 : nextIndex);

			slides.forEach(function (slide, index) {
				const active = index === activeIndex;
				slide.hidden = !active;
				slide.classList.toggle("is-active", active);
			});

			thumbs.forEach(function (thumb, index) {
				thumb.classList.toggle("is-active", index === activeIndex);
			});
		}

		if (prev) { prev.addEventListener("click", function () { setActive(activeIndex - 1); }); }
		if (next) { next.addEventListener("click", function () { setActive(activeIndex + 1); }); }

		thumbs.forEach(function (thumb) {
			thumb.addEventListener("click", function () {
				setActive(Number.parseInt(thumb.getAttribute("data-mlgp-browser-thumb") || "0", 10));
			});
		});

		node.dataset.mlgpReady = "1";
		setActive(0);
	}

	// ── Boot ────────────────────────────────────────────────────────────────────
	function boot() {
		document.querySelectorAll("[data-mlgp-slideshow]").forEach(setupSlideshow);
		document.querySelectorAll("[data-mlgp-imagebrowser]").forEach(setupImageBrowser);
	}

	if (document.readyState === "loading") {
		document.addEventListener("DOMContentLoaded", boot);
	} else {
		boot();
	}

	if ("MutationObserver" in window && document.body) {
		const observer = new MutationObserver(function () {
			boot();
		});

		observer.observe(document.body, {
			childList: true,
			subtree:   true,
		});
	}

})();
