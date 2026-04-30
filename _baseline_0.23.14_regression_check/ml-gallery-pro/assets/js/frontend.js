(function () {
	let lightbox;
	let lightboxImage;
	let lightboxCaption;

	function ensureLightbox() {
		if (lightbox) {
			return;
		}

		lightbox = document.createElement("div");
		lightbox.className = "mlgp-lightbox";
		lightbox.innerHTML = `
			<div class="mlgp-lightbox__dialog">
				<button type="button" class="mlgp-lightbox__close" aria-label="Fechar">×</button>
				<img class="mlgp-lightbox__image" alt="">
				<div class="mlgp-lightbox__caption"></div>
			</div>
		`;

		document.body.appendChild(lightbox);

		lightbox.querySelector(".mlgp-lightbox__close").textContent = "x";
		lightboxImage = lightbox.querySelector(".mlgp-lightbox__image");
		lightboxCaption = lightbox.querySelector(".mlgp-lightbox__caption");

		lightbox.addEventListener("click", function (event) {
			if (event.target === lightbox || event.target.closest(".mlgp-lightbox__close")) {
				closeLightbox();
			}
		});

	}

	function openLightbox(url, text) {
		if (!url) {
			return;
		}

		ensureLightbox();
		lightboxImage.src = url;
		lightboxCaption.textContent = text || "";
		lightbox.classList.add("is-active");
		document.body.style.overflow = "hidden";
	}

	function closeLightbox() {
		if (!lightbox) {
			return;
		}

		lightbox.classList.remove("is-active");
		lightboxImage.src = "";
		lightboxCaption.textContent = "";
		document.body.style.overflow = "";
	}

	function setupSlideshow(node) {
		if (!node || node.dataset.mlgpReady === "1") {
			return;
		}

		const slides = Array.from(node.querySelectorAll("[data-mlgp-slide]"));
		const thumbs = Array.from(node.querySelectorAll("[data-mlgp-slide-thumb]"));
		const prev = node.querySelector("[data-mlgp-slide-prev]");
		const next = node.querySelector("[data-mlgp-slide-next]");
		const thumbTrack = node.querySelector("[data-mlgp-slide-thumb-track]");
		const thumbPrev = node.querySelector("[data-mlgp-slide-thumb-prev]");
		const thumbNext = node.querySelector("[data-mlgp-slide-thumb-next]");
		const autoplay = node.dataset.autoplay === "1";
		const interval = Number.parseInt(node.dataset.interval || "4000", 10) || 4000;
		let activeIndex = 0;
		let timer = 0;

		if (!slides.length) {
			return;
		}

		function updateThumbRail() {
			if (!thumbTrack || !thumbPrev || !thumbNext) {
				return;
			}

			const maxScroll = Math.max(0, thumbTrack.scrollWidth - thumbTrack.clientWidth);
			const currentScroll = Math.max(0, thumbTrack.scrollLeft);
			const isScrollable = maxScroll > 8;

			thumbPrev.disabled = !isScrollable || currentScroll <= 4;
			thumbNext.disabled = !isScrollable || currentScroll >= maxScroll - 4;
			thumbPrev.classList.toggle("is-hidden", !isScrollable);
			thumbNext.classList.toggle("is-hidden", !isScrollable);
		}

		function revealActiveThumb() {
			if (!thumbTrack || !thumbs[activeIndex]) {
				return;
			}

			const thumb = thumbs[activeIndex];
			const targetLeft = Math.max(0, thumb.offsetLeft - ((thumbTrack.clientWidth - thumb.offsetWidth) / 2));
			thumbTrack.scrollTo({
				left: targetLeft,
				behavior: "smooth",
			});
		}

		function stepThumbRail(direction) {
			if (!thumbTrack) {
				return;
			}

			const fallbackStep = thumbs[0] ? thumbs[0].offsetWidth * 3 : 264;
			const distance = Math.max(thumbTrack.clientWidth * 0.82, fallbackStep);
			thumbTrack.scrollBy({
				left: direction * distance,
				behavior: "smooth",
			});
		}

		function setActive(nextIndex) {
			activeIndex = nextIndex < 0 ? slides.length - 1 : (nextIndex >= slides.length ? 0 : nextIndex);

			slides.forEach(function (slide, index) {
				const active = index === activeIndex;
				slide.hidden = !active;
				slide.classList.toggle("is-active", active);
			});

			thumbs.forEach(function (thumb, index) {
				const active = index === activeIndex;
				thumb.classList.toggle("is-active", active);
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

		if (prev) {
			prev.addEventListener("click", function () {
				goTo(activeIndex - 1);
			});
		}

		if (next) {
			next.addEventListener("click", function () {
				goTo(activeIndex + 1);
			});
		}

		if (thumbPrev) {
			thumbPrev.addEventListener("click", function () {
				stepThumbRail(-1);
			});
		}

		if (thumbNext) {
			thumbNext.addEventListener("click", function () {
				stepThumbRail(1);
			});
		}

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

	function setupImageBrowser(node) {
		if (!node || node.dataset.mlgpReady === "1") {
			return;
		}

		const slides = Array.from(node.querySelectorAll("[data-mlgp-browser-slide]"));
		const thumbs = Array.from(node.querySelectorAll("[data-mlgp-browser-thumb]"));
		const prev = node.querySelector("[data-mlgp-browser-prev]");
		const next = node.querySelector("[data-mlgp-browser-next]");
		let activeIndex = 0;

		if (!slides.length) {
			return;
		}

		function setActive(nextIndex) {
			activeIndex = nextIndex < 0 ? slides.length - 1 : (nextIndex >= slides.length ? 0 : nextIndex);

			slides.forEach(function (slide, index) {
				const active = index === activeIndex;
				slide.hidden = !active;
				slide.classList.toggle("is-active", active);
			});

			thumbs.forEach(function (thumb, index) {
				thumb.classList.toggle("is-active", index === activeIndex);
			});
		}

		if (prev) {
			prev.addEventListener("click", function () {
				setActive(activeIndex - 1);
			});
		}

		if (next) {
			next.addEventListener("click", function () {
				setActive(activeIndex + 1);
			});
		}

		thumbs.forEach(function (thumb) {
			thumb.addEventListener("click", function () {
				setActive(Number.parseInt(thumb.getAttribute("data-mlgp-browser-thumb") || "0", 10));
			});
		});

		node.dataset.mlgpReady = "1";
		setActive(0);
	}

	function boot() {
		document.querySelectorAll("[data-mlgp-slideshow]").forEach(setupSlideshow);
		document.querySelectorAll("[data-mlgp-imagebrowser]").forEach(setupImageBrowser);
	}

	document.addEventListener("click", function (event) {
		const link = event.target.closest("a[data-mlgp-lightbox]");

		if (!link) {
			return;
		}

		event.preventDefault();
		openLightbox(link.getAttribute("href"), link.getAttribute("data-caption"));
	});

	document.addEventListener("keydown", function (event) {
		if (event.key === "Escape") {
			closeLightbox();
		}
	});

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
			subtree: true,
		});
	}
})();
