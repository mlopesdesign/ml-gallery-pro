/**
 * ML Gallery Pro — Frontend runtime
 *
 * v0.26.8: Full lightbox navigation (prev/next/keyboard/swipe/touch).
 */
(function () {

	// ── Lightbox state ──────────────────────────────────────────────────────────
	let lightbox = null;
	let lightboxImage = null;
	let lightboxCaption = null;
	let lightboxCounter = null;
	let lightboxPrev = null;
	let lightboxNext = null;

	// Currently active gallery items ([{ url, caption }]) and index
	let currentItems = [];
	let currentIndex = 0;

	// Touch/swipe tracking
	let touchStartX = 0;
	let touchStartY = 0;

	function ensureLightbox() {
		if (lightbox) { return; }
		lightbox = document.createElement("div");
		lightbox.className = "mlgp-lightbox";
		lightbox.setAttribute("role", "dialog");
		lightbox.setAttribute("aria-modal", "true");
		lightbox.setAttribute("aria-label", "Lightbox");
		lightbox.innerHTML = '<div class="mlgp-lightbox__dialog">' +
			'<button type="button" class="mlgp-lightbox__close" aria-label="Fechar"><svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><line x1="2" y1="2" x2="18" y2="18" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/><line x1="18" y1="2" x2="2" y2="18" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/></svg></button>' +
			'<button type="button" class="mlgp-lightbox__prev" aria-label="Anterior"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><polyline points="15 18 9 12 15 6" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg></button>' +
			'<div class="mlgp-lightbox__stage"><img class="mlgp-lightbox__image" alt=""></div>' +
			'<button type="button" class="mlgp-lightbox__next" aria-label="Pr\u00f3ximo"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><polyline points="9 18 15 12 9 6" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg></button>' +
			'<div class="mlgp-lightbox__footer"><div class="mlgp-lightbox__caption"></div><div class="mlgp-lightbox__counter"></div></div>' +
			'</div>';
		document.body.appendChild(lightbox);
		lightboxImage = lightbox.querySelector(".mlgp-lightbox__image");
		lightboxCaption = lightbox.querySelector(".mlgp-lightbox__caption");
		lightboxCounter = lightbox.querySelector(".mlgp-lightbox__counter");
		lightboxPrev = lightbox.querySelector(".mlgp-lightbox__prev");
		lightboxNext = lightbox.querySelector(".mlgp-lightbox__next");
		lightbox.addEventListener("click", function(e) { if (e.target === lightbox || e.target.closest(".mlgp-lightbox__close")) { closeLightbox(); } });
		lightboxPrev.addEventListener("click", function(e) { e.stopPropagation(); navigate(-1); });
		lightboxNext.addEventListener("click", function(e) { e.stopPropagation(); navigate(1); });
		lightbox.addEventListener("touchstart", function(e) { touchStartX = e.touches[0].clientX; touchStartY = e.touches[0].clientY; }, { passive: true });
		lightbox.addEventListener("touchend", function(e) { var dx = e.changedTouches[0].clientX - touchStartX; var dy = e.changedTouches[0].clientY - touchStartY; if (Math.abs(dx) > 40 && Math.abs(dx) > Math.abs(dy)) { navigate(dx < 0 ? 1 : -1); } }, { passive: true });
	}

	function collectItems(clickedLink) {
		var wrapper = clickedLink.closest("[data-mlgp-gallery-id]") || clickedLink.closest(".mlgp-grid") || clickedLink.closest(".mlgp-frontend") || clickedLink.parentElement;
		var links = Array.from(wrapper ? wrapper.querySelectorAll("a[data-mlgp-lightbox]") : document.querySelectorAll("a[data-mlgp-lightbox]"));
		return { items: links.map(function(l) { return { url: l.getAttribute("href") || "", caption: l.getAttribute("data-caption") || "" }; }), index: links.indexOf(clickedLink) };
	}

	function showIndex(index) {
		var total = currentItems.length;
		if (!total) { return; }
		currentIndex = ((index % total) + total) % total;
		var item = currentItems[currentIndex];
		lightboxImage.src = item.url;
		lightboxImage.alt = item.caption || "";
		lightboxCaption.textContent = item.caption || "";
		lightboxCounter.textContent = total > 1 ? (currentIndex + 1) + " / " + total : "";
		lightboxPrev.hidden = total <= 1;
		lightboxNext.hidden = total <= 1;
	}

	function navigate(direction) { showIndex(currentIndex + direction); }

	function openLightbox(url, caption, clickedLink) {
		if (!url) { return; }
		ensureLightbox();
		if (clickedLink) { var r = collectItems(clickedLink); currentItems = r.items; currentIndex = r.index >= 0 ? r.index : 0; }
		else { currentItems = [{ url: url, caption: caption || "" }]; currentIndex = 0; }
		showIndex(currentIndex);
		lightbox.classList.add("is-active");
		document.body.classList.add("mlgp-lightbox-open");
	}

	function closeLightbox() {
		if (!lightbox) { return; }
		lightbox.classList.remove("is-active");
		document.body.classList.remove("mlgp-lightbox-open");
		lightboxImage.src = "";
		lightboxCaption.textContent = "";
		lightboxCounter.textContent = "";
		currentItems = [];
		currentIndex = 0;
	}

	document.addEventListener("keydown", function(e) {
		if (!lightbox || !lightbox.classList.contains("is-active")) { return; }
		if (e.key === "Escape") { closeLightbox(); }
		else if (e.key === "ArrowLeft") { navigate(-1); }
		else if (e.key === "ArrowRight") { navigate(1); }
	});

	document.addEventListener("click", function(e) {
		var link = e.target.closest("a[data-mlgp-lightbox]");
		if (!link) { return; }
		e.preventDefault();
		openLightbox(link.getAttribute("href"), link.getAttribute("data-caption"), link);
	});

	function setupSlideshow(node) {
		if (!node || node.dataset.mlgpReady === "1") { return; }
		var slides = Array.from(node.querySelectorAll("[data-mlgp-slide]"));
		var thumbs = Array.from(node.querySelectorAll("[data-mlgp-slide-thumb]"));
		var prev = node.querySelector("[data-mlgp-slide-prev]");
		var next = node.querySelector("[data-mlgp-slide-next]");
		var thumbTrack = node.querySelector("[data-mlgp-slide-thumb-track]");
		var thumbPrev = node.querySelector("[data-mlgp-slide-thumb-prev]");
		var thumbNext = node.querySelector("[data-mlgp-slide-thumb-next]");
		var autoplay = node.dataset.autoplay === "1";
		var interval = Number.parseInt(node.dataset.interval || "4000", 10) || 4000;
		var activeIndex = 0;
		var timer = 0;
		if (!slides.length) { return; }
		function updateThumbRail() {
			if (!thumbTrack || !thumbPrev || !thumbNext) { return; }
			var maxScroll = Math.max(0, thumbTrack.scrollWidth - thumbTrack.clientWidth);
			var currentScroll = Math.max(0, thumbTrack.scrollLeft);
			var isScrollable = maxScroll > 8;
			thumbPrev.disabled = !isScrollable || currentScroll <= 4;
			thumbNext.disabled = !isScrollable || currentScroll >= maxScroll - 4;
			thumbPrev.classList.toggle("is-hidden", !isScrollable);
			thumbNext.classList.toggle("is-hidden", !isScrollable);
		}
		function revealActiveThumb() {
			if (!thumbTrack || !thumbs[activeIndex]) { return; }
			var thumb = thumbs[activeIndex];
			var targetLeft = Math.max(0, thumb.offsetLeft - ((thumbTrack.clientWidth - thumb.offsetWidth) / 2));
			thumbTrack.scrollTo({ left: targetLeft, behavior: "smooth" });
		}
		function stepThumbRail(dir) {
			if (!thumbTrack) { return; }
			var d = Math.max(thumbTrack.clientWidth * 0.82, thumbs[0] ? thumbs[0].offsetWidth * 3 : 264);
			thumbTrack.scrollBy({ left: dir * d, behavior: "smooth" });
		}
		function setActive(ni) {
			activeIndex = ni < 0 ? slides.length - 1 : (ni >= slides.length ? 0 : ni);
			slides.forEach(function(s, i) { s.hidden = i !== activeIndex; s.classList.toggle("is-active", i === activeIndex); });
			thumbs.forEach(function(t, i) { t.classList.toggle("is-active", i === activeIndex); });
			revealActiveThumb(); updateThumbRail();
		}
		function stopAutoplay() { if (timer) { window.clearInterval(timer); timer = 0; } }
		function startAutoplay() {
			stopAutoplay();
			if (!autoplay || slides.length < 2) { return; }
			timer = window.setInterval(function() { setActive(activeIndex + 1); }, Math.max(1500, interval));
		}
		function goTo(i) { setActive(i); startAutoplay(); }
		if (prev) { prev.addEventListener("click", function() { goTo(activeIndex - 1); }); }
		if (next) { next.addEventListener("click", function() { goTo(activeIndex + 1); }); }
		if (thumbPrev) { thumbPrev.addEventListener("click", function() { stepThumbRail(-1); }); }
		if (thumbNext) { thumbNext.addEventListener("click", function() { stepThumbRail(1); }); }
		thumbs.forEach(function(t) { t.addEventListener("click", function() { goTo(Number.parseInt(t.getAttribute("data-mlgp-slide-thumb") || "0", 10)); }); });
		if (thumbTrack) { thumbTrack.addEventListener("scroll", updateThumbRail, { passive: true }); window.addEventListener("resize", updateThumbRail); }
		node.addEventListener("mouseenter", stopAutoplay);
		node.addEventListener("mouseleave", startAutoplay);
		node.dataset.mlgpReady = "1";
		setActive(0); updateThumbRail(); startAutoplay();
	}

	function setupImageBrowser(node) {
		if (!node || node.dataset.mlgpReady === "1") { return; }
		var slides = Array.from(node.querySelectorAll("[data-mlgp-browser-slide]"));
		var thumbs = Array.from(node.querySelectorAll("[data-mlgp-browser-thumb]"));
		var prev = node.querySelector("[data-mlgp-browser-prev]");
		var next = node.querySelector("[data-mlgp-browser-next]");
		var activeIndex = 0;
		if (!slides.length) { return; }
		function setActive(ni) {
			activeIndex = ni < 0 ? slides.length - 1 : (ni >= slides.length ? 0 : ni);
			slides.forEach(function(s, i) { s.hidden = i !== activeIndex; s.classList.toggle("is-active", i === activeIndex); });
			thumbs.forEach(function(t, i) { t.classList.toggle("is-active", i === activeIndex); });
		}
		if (prev) { prev.addEventListener("click", function() { setActive(activeIndex - 1); }); }
		if (next) { next.addEventListener("click", function() { setActive(activeIndex + 1); }); }
		thumbs.forEach(function(t) { t.addEventListener("click", function() { setActive(Number.parseInt(t.getAttribute("data-mlgp-browser-thumb") || "0", 10)); }); });
		node.dataset.mlgpReady = "1";
		setActive(0);
	}

	function boot() {
		document.querySelectorAll("[data-mlgp-slideshow]").forEach(setupSlideshow);
		document.querySelectorAll("[data-mlgp-imagebrowser]").forEach(setupImageBrowser);
	}

	if (document.readyState === "loading") { document.addEventListener("DOMContentLoaded", boot); } else { boot(); }

	if ("MutationObserver" in window && document.body) {
		var observer = new MutationObserver(function() { boot(); });
		observer.observe(document.body, { childList: true, subtree: true });
	}

})();
