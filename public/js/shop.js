// ABJ Store – Shop-Frontend. Keine Inline-Skripte (CSP-konform).
(function () {
  'use strict';

  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));
  const CSRF = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
  const BASE_PATH = document.documentElement.getAttribute('data-base-path') || '';
  const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const WISH_KEY = 'abj_wishlist';

  /* ---------------- Toasts ---------------- */
  const toastWrap = $('[data-toasts]');
  function toast(msg, type = 'ok') {
    if (!toastWrap) return;
    const t = document.createElement('div');
    t.className = 'toast toast-' + type;
    t.textContent = msg;
    toastWrap.appendChild(t);
    requestAnimationFrame(() => t.classList.add('in'));
    setTimeout(() => {
      t.classList.remove('in');
      setTimeout(() => t.remove(), 350);
    }, 3200);
  }

  /* ---------------- Countdown ---------------- */
  const countdowns = $$('[data-countdown]');
  countdowns.forEach((cd) => {
    const target = new Date(cd.getAttribute('data-countdown')).getTime();
    const set = (k, v) => { const el = cd.querySelector('[data-cd="' + k + '"]'); if (el) el.textContent = String(v).padStart(2, '0'); };
    function tick() {
      const diff = target - Date.now();
      if (isNaN(target) || diff <= 0) { ['days', 'hours', 'mins', 'secs'].forEach((k) => set(k, 0)); return; }
      const s = Math.floor(diff / 1000);
      set('days', Math.floor(s / 86400));
      set('hours', Math.floor((s % 86400) / 3600));
      set('mins', Math.floor((s % 3600) / 60));
      set('secs', s % 60);
    }
    tick();
    setInterval(tick, 1000);
  });

  /* ---------------- Produktgalerie ---------------- */
  const gallery = $('[data-gallery]');
  if (gallery) {
    const main = $('[data-gallery-main]', gallery);
    $$('.thumb', gallery).forEach((thumb) => {
      thumb.addEventListener('click', () => {
        const src = thumb.getAttribute('data-src');
        if (main && src) main.setAttribute('src', src);
        $$('.thumb', gallery).forEach((t) => t.classList.remove('active'));
        thumb.classList.add('active');
      });
    });
  }

  /* ---------------- Varianten auf Produktseite (Buttons, Bild/Preis wechseln) ---------------- */
  const variantsDataEl = document.getElementById('product-variants-data');
  const optionGroupsDataEl = document.getElementById('product-option-groups-data');
  const priceDataEl = document.getElementById('product-price-data');
  let productPrice = null;
  if (priceDataEl) {
    try { productPrice = JSON.parse(priceDataEl.textContent || '{}'); } catch { productPrice = null; }
  }
  if (variantsDataEl && optionGroupsDataEl) {
    let variants = [];
    let optionGroups = [];
    try { variants = JSON.parse(variantsDataEl.textContent || '[]'); } catch { variants = []; }
    try { optionGroups = JSON.parse(optionGroupsDataEl.textContent || '[]'); } catch { optionGroups = []; }
    if (variants.length && optionGroups.length) {
      const variantRow = document.getElementById('variant-row');
      const mainImg = document.querySelector('[data-gallery-main] img') || document.querySelector('[data-gallery-main]');
      const priceBlock = document.querySelector('.product-price.big');
      const hiddenSize = document.querySelector('input[name="size"]');
      const selected = {};
      const defaultVariant = variants.find((x) => x.is_default) || variants[0];

      function fmtPrice(cents) {
        try { return cents == null ? null : 'CHF ' + (cents / 100).toFixed(2); } catch { return String(cents / 100); }
      }

      function renderPriceBlock(nowCents, oldCents) {
        if (nowCents == null || !priceBlock) return;
        let html = '';
        if (oldCents != null && oldCents > nowCents) {
          const pct = Math.round((1 - nowCents / oldCents) * 100);
          html = '<span class="price-sale">' + fmtPrice(nowCents) + '</span>' +
                 '<span class="price-old">' + fmtPrice(oldCents) + '</span>' +
                 (pct > 0 ? '<span class="badge-sale">-' + pct + '%</span>' : '');
        } else {
          html = '<span class="price-now">' + fmtPrice(nowCents) + '</span>';
        }
        priceBlock.innerHTML = html;
      }

      function currentSignatureFromSelection() {
        return optionGroups.map((group) => {
          const value = selected[group.key] || '';
          return group.key + ':' + value;
        }).join(' | ');
      }

      function findVariantBySelection() {
        return variants.find((variant) => {
          return optionGroups.every((group) => {
            const selectedValue = selected[group.key] || '';
            const option = (variant.option_values || []).find((entry) => (entry.key || entry.group) === group.key);
            return option && option.value === selectedValue;
          });
        });
      }

      function applyVisual(variant) {
        if (!variant) return;
        const img = variant.images && variant.images[0] && variant.images[0].src;
        if (img && mainImg) mainImg.setAttribute('src', img);
        const priceNow = variant.variant_price_cents != null ? Number(variant.variant_price_cents) : (productPrice?.sale_price_cents != null ? productPrice.sale_price_cents : productPrice?.price_cents);
        const oldPrice = variant.variant_price_cents != null ? productPrice?.price_cents ?? null : (productPrice?.sale_price_cents != null ? productPrice.price_cents : null);
        renderPriceBlock(priceNow, oldPrice);
      }

      // Warenkorb-Schlüssel nur setzen, wenn die Auswahl vollständig ist.
      function setCartKey(variant) {
        if (!hiddenSize) return;
        hiddenSize.value = variant ? (variant.key || variant.size || variant.title || '') : '';
      }

      // Bestes Match für die aktuelle (ggf. unvollständige) Auswahl – damit
      // z.B. nach der Farbwahl schon das passende Bild erscheint.
      function findVariantForImage() {
        const full = findVariantBySelection();
        if (full) return full;
        const selKeys = optionGroups.map((g) => g.key).filter((k) => selected[k]);
        if (!selKeys.length) return null;
        const matches = variants.filter((variant) => selKeys.every((k) => {
          const opt = (variant.option_values || []).find((e) => (e.key || e.group) === k);
          return opt && opt.value === selected[k];
        }));
        return matches.find((v) => v.stock > 0) || matches[0] || null;
      }

      function isValueAvailable(groupKey, value) {
        const testSelected = { ...selected, [groupKey]: value };
        return variants.some((variant) =>
          optionGroups.every((g) => {
            const v = testSelected[g.key] || '';
            if (!v) return true;
            const opt = (variant.option_values || []).find((e) => (e.key || e.group) === g.key);
            return opt && opt.value === v;
          }) && variant.stock > 0
        );
      }

      function renderOptionButtons() {
        if (!variantRow) return;
        variantRow.innerHTML = '';
        optionGroups.forEach((group) => {
          const wrap = document.createElement('div');
          wrap.className = 'variant-group';
          wrap.innerHTML = '<div class="variant-group-title">' + group.label + '</div>';
          const row = document.createElement('div');
          row.className = 'variant-group-btns';
          (group.values || []).forEach((value) => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn-variant';
            btn.textContent = value;
            btn.dataset.group = group.key;
            btn.dataset.value = value;
            const avail = isValueAvailable(group.key, value);
            if (!avail) btn.classList.add('soldout');
            btn.addEventListener('click', () => {
              if (btn.classList.contains('soldout')) return;
              selected[group.key] = value;
              renderOptionButtons();
              const full = findVariantBySelection();
              applyVisual(full || findVariantForImage());
              setCartKey(full);
            });
            if (selected[group.key] === value) btn.classList.add('active');
            row.appendChild(btn);
          });
          wrap.appendChild(row);
          variantRow.appendChild(wrap);
        });
      }

      (defaultVariant.option_values || []).forEach((entry) => {
        selected[entry.key || entry.group] = entry.value;
      });
      renderOptionButtons();
      const initFull = findVariantBySelection();
      applyVisual(initFull || defaultVariant);
      setCartKey(initFull);
    }
  }

  /* ---------------- Scroll-Reveal ---------------- */
  if (!reduce && 'IntersectionObserver' in window) {
    $$('.review-card, .section-title, .feature-card, .collection-card, .testimonial, .newsletter').forEach((el, i) => {
      if (!el.classList.contains('reveal')) el.classList.add('reveal');
      el.style.transitionDelay = (Math.min(i % 4, 3) * 60) + 'ms';
    });
    const io = new IntersectionObserver((entries) => {
      entries.forEach((e) => { if (e.isIntersecting) { e.target.classList.add('in'); io.unobserve(e.target); } });
    }, { threshold: 0, rootMargin: '0px' });
    $$('.reveal').forEach((el) => io.observe(el));
  } else {
    $$('.reveal').forEach((el) => el.classList.add('in'));
  }

  /* ---------------- Cart Badge & Drawer ---------------- */
  function setCartBadge(count) {
    $$('[data-cart-count]').forEach((b) => {
      b.textContent = count;
      b.hidden = !(count > 0);
    });
  }

  const drawer = $('[data-cart-drawer]');
  function openDrawer() { if (drawer) { drawer.classList.add('open'); drawer.setAttribute('aria-hidden', 'false'); document.body.classList.add('no-scroll'); } }
  function closeDrawer() { if (drawer) { drawer.classList.remove('open'); drawer.setAttribute('aria-hidden', 'true'); document.body.classList.remove('no-scroll'); } }

  function renderDrawer(state) {
    setCartBadge(state.count);
    const body = $('[data-drawer-items]');
    const total = $('[data-drawer-total]');
    if (total) total.textContent = state.totalText;
    if (!body) return;
    if (!state.items.length) {
      body.innerHTML = '<p class="drawer-empty muted">Dein Warenkorb ist leer.</p>';
      return;
    }
    body.innerHTML = state.items.map((it) =>
      '<div class="drawer-item">' +
        '<img src="' + (it.image || fallbackImg(it.name)) + '" alt="">' +
        '<div class="drawer-item-info">' +
          '<a href="' + it.url + '">' + esc(it.name) + '</a>' +
          (it.size ? '<span class="muted">Größe: ' + esc(it.sizeLabel || it.size) + '</span>' : '') +
          '<span class="muted">' + it.qty + '× · ' + it.lineText + '</span>' +
        '</div>' +
        '<button class="drawer-remove" data-drawer-remove data-id="' + it.productId + '" data-size="' + esc(it.size || '') + '" aria-label="Entfernen" title="Entfernen">' +
          '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path d="M6 6l12 12M18 6 6 18"/></svg>' +
        '</button>' +
      '</div>'
    ).join('');
    $$('[data-drawer-remove]', body).forEach((btn) => {
      btn.addEventListener('click', () => {
        btn.disabled = true;
        const fd = new URLSearchParams();
        fd.set('productId', btn.getAttribute('data-id'));
        fd.set('size', btn.getAttribute('data-size') || '');
        fd.set('qty', 0);
        fetch(BASE_PATH + '/api/cart-update.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded', Accept: 'application/json' },
          body: fd.toString(),
        }).then((r) => r.json()).then((d) => {
          if (d.ok) { renderDrawer(d); toast('Artikel entfernt'); if ($('[data-cart-page]')) window.location.reload(); }
          else toast(d.error || 'Fehler', 'err');
        }).catch(() => { btn.disabled = false; toast('Netzwerkfehler', 'err'); });
      });
    });
  }

  function refreshDrawer() {
    fetch(BASE_PATH + '/api/cart-state.php', { headers: { Accept: 'application/json' } })
      .then((r) => r.json()).then(renderDrawer).catch(() => {});
  }

  $$('[data-cart-toggle]').forEach((b) => b.addEventListener('click', (e) => {
    e.preventDefault(); refreshDrawer(); openDrawer();
  }));
  $$('[data-cart-close]').forEach((b) => b.addEventListener('click', closeDrawer));
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape') { closeDrawer(); closeSearch(); closeMobile(); } });

  function addToCart(productId, size, qty) {
    const fd = new URLSearchParams();
    fd.set('productId', productId);
    fd.set('size', size || '');
    fd.set('qty', qty || 1);
    return fetch(BASE_PATH + '/api/cart-add.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': CSRF, Accept: 'application/json' },
      body: fd.toString(),
    }).then((r) => r.json().then((d) => ({ ok: r.ok, d })));
  }

  // Quick-Add auf Karten
  $$('[data-quick-add]').forEach((btn) => {
    btn.addEventListener('click', (e) => {
      e.preventDefault();
      if (btn.getAttribute('data-has-sizes') === '1') {
        window.location.href = BASE_PATH + '/produkt.php?slug=' + encodeURIComponent(btn.getAttribute('data-slug'));
        return;
      }
      btn.classList.add('loading');
      addToCart(btn.getAttribute('data-id'), '', 1).then(({ ok, d }) => {
        btn.classList.remove('loading');
        if (ok && d.ok) { renderDrawer(d); toast('„' + d.added + '" hinzugefügt ✓'); }
        else toast(d.error || 'Konnte nicht hinzufügen', 'err');
      }).catch(() => { btn.classList.remove('loading'); toast('Netzwerkfehler', 'err'); });
    });
  });

  // AJAX auf der Produktdetailseite
  const addForm = $('[data-ajax-add]');
  if (addForm) {
    addForm.addEventListener('submit', (e) => {
      e.preventDefault();
      const pid = addForm.getAttribute('data-product-id');
      const qty = (addForm.querySelector('[name="qty"]') || {}).value || 1;
      const sizeEl = addForm.querySelector('input[name="size"]');
      const size = sizeEl ? sizeEl.value : '';
      const submit = addForm.querySelector('button[type="submit"]');
      if (submit) submit.classList.add('loading');
      addToCart(pid, size, qty).then(({ ok, d }) => {
        if (submit) submit.classList.remove('loading');
        if (ok && d.ok) { renderDrawer(d); toast('„' + d.added + '" hinzugefügt ✓'); }
        else toast(d.error || 'Konnte nicht hinzufügen', 'err');
      }).catch(() => { if (submit) submit.classList.remove('loading'); toast('Netzwerkfehler', 'err'); });
    });
  }

  /* ---------------- Wunschliste ---------------- */
  function getWish() { try { return JSON.parse(localStorage.getItem(WISH_KEY)) || []; } catch { return []; } }
  function setWish(arr) { localStorage.setItem(WISH_KEY, JSON.stringify(arr)); updateWishBadge(); }
  function updateWishBadge() {
    const n = getWish().length;
    $$('[data-wish-count]').forEach((b) => { b.textContent = n; b.hidden = !(n > 0); });
  }
  function syncWishButtons() {
    const wish = getWish();
    $$('[data-wish]').forEach((b) => b.classList.toggle('active', wish.includes(Number(b.getAttribute('data-wish')))));
  }
  $$('[data-wish]').forEach((btn) => {
    btn.addEventListener('click', (e) => {
      e.preventDefault();
      const id = Number(btn.getAttribute('data-wish'));
      let wish = getWish();
      if (wish.includes(id)) { wish = wish.filter((x) => x !== id); toast('Von der Wunschliste entfernt'); }
      else { wish.push(id); toast('Zur Wunschliste hinzugefügt ♥'); }
      setWish(wish); syncWishButtons();
      if ($('[data-wish-page]')) renderWishlist();
    });
  });
  updateWishBadge(); syncWishButtons();

  // Wunschlisten-Seite
  function renderWishlist() {
    const grid = $('[data-wish-grid]');
    const empty = $('[data-wish-empty]');
    if (!grid) return;
    const ids = getWish();
    if (!ids.length) { grid.innerHTML = ''; if (empty) empty.hidden = false; return; }
    fetch(BASE_PATH + '/api/produkte.php', { headers: { Accept: 'application/json' } })
      .then((r) => r.json()).then((data) => {
        const items = data.items.filter((p) => ids.includes(p.id));
        if (!items.length) { grid.innerHTML = ''; if (empty) empty.hidden = false; return; }
        if (empty) empty.hidden = true;
        grid.innerHTML = items.map((p) =>
          '<article class="product-card">' +
            '<div class="product-card-media">' +
              '<a class="media-link" href="' + p.url + '"><img src="' + (p.image || fallbackImg(p.name)) + '" alt=""><span class="card-shine"></span></a>' +
              '<button class="wish-btn active" data-wish-remove="' + p.id + '" aria-label="Entfernen"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 21s-7-4.5-9.5-9A5 5 0 0 1 12 6a5 5 0 0 1 9.5 6c-2.5 4.5-9.5 9-9.5 9z"/></svg></button>' +
            '</div>' +
            '<div class="product-card-body"><span class="product-cat">' + esc(p.category) + '</span>' +
            '<h3 class="product-name"><a href="' + p.url + '">' + esc(p.name) + '</a></h3>' +
            '<div class="product-price"><span class="price-now">' + p.priceText + '</span>' +
            (p.oldPriceText ? '<span class="price-old">' + p.oldPriceText + '</span>' : '') + '</div></div>' +
          '</article>'
        ).join('');
        $$('[data-wish-remove]', grid).forEach((b) => b.addEventListener('click', () => {
          setWish(getWish().filter((x) => x !== Number(b.getAttribute('data-wish-remove'))));
          renderWishlist();
        }));
      }).catch(() => {});
  }
  if ($('[data-wish-page]')) renderWishlist();

  /* ---------------- Suche ---------------- */
  const searchBar = $('[data-search-bar]');
  const searchInput = $('[data-search-input]');
  const searchResults = $('[data-search-results]');
  let catalog = null;
  function loadCatalog() {
    if (catalog) return Promise.resolve(catalog);
    return fetch(BASE_PATH + '/api/produkte.php', { headers: { Accept: 'application/json' } })
      .then((r) => r.json()).then((d) => { catalog = d.items; return catalog; }).catch(() => []);
  }
  function openSearch() { if (searchBar) { searchBar.hidden = false; loadCatalog(); setTimeout(() => searchInput && searchInput.focus(), 50); } }
  function closeSearch() { if (searchBar) searchBar.hidden = true; }
  $$('[data-search-toggle]').forEach((b) => b.addEventListener('click', () => {
    if (searchBar.hidden) openSearch(); else closeSearch();
  }));
  $$('[data-search-close]').forEach((b) => b.addEventListener('click', closeSearch));
  if (searchInput) {
    searchInput.addEventListener('input', () => {
      const q = searchInput.value.trim().toLowerCase();
      if (!q) { searchResults.innerHTML = ''; return; }
      loadCatalog().then((items) => {
        const hits = items.filter((p) => (p.name + ' ' + p.category + ' ' + (p.tags || '')).toLowerCase().includes(q)).slice(0, 6);
        searchResults.innerHTML = hits.length
          ? hits.map((p) => '<a class="search-hit" href="' + p.url + '"><img src="' + (p.image || fallbackImg(p.name)) + '" alt=""><span><strong>' + esc(p.name) + '</strong><small>' + esc(p.category) + ' · ' + p.priceText + '</small></span></a>').join('')
          : '<div class="search-none"><p class="muted">Nichts gefunden für „' + esc(q) + '".</p><a class="btn btn-primary btn-sm" href="' + BASE_PATH + '/anfrage.php">Produkt anfragen &rarr;</a></div>';
      });
    });
  }

  /* ---------------- Mobile-Menü ---------------- */
  const mobileMenu = $('[data-mobile-menu]');
  const navToggle = $('[data-nav-toggle]');
  function closeMobile() { if (mobileMenu) { mobileMenu.hidden = true; document.body.classList.remove('no-scroll'); } if (navToggle) navToggle.setAttribute('aria-expanded', 'false'); }
  if (navToggle && mobileMenu) {
    navToggle.addEventListener('click', () => {
      const show = mobileMenu.hidden;
      mobileMenu.hidden = !show;
      navToggle.setAttribute('aria-expanded', String(show));
      document.body.classList.toggle('no-scroll', show);
    });
  }

  /* ---------------- Newsletter (AJAX) ---------------- */
  const nl = $('[data-newsletter]');
  if (nl) {
    nl.addEventListener('submit', (e) => {
      e.preventDefault();
      const email = (nl.querySelector('[name="email"]') || {}).value;
      fetch(BASE_PATH + '/newsletter.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': CSRF, Accept: 'application/json' },
        body: 'email=' + encodeURIComponent(email),
      }).then((r) => r.json()).then(() => { nl.reset(); toast('Danke! Du bist dabei ✓'); }).catch(() => toast('Bitte später erneut versuchen', 'err'));
    });
  }

  /* ---------------- Zuletzt angesehen ---------------- */
  const RECENT_KEY = 'abj_recent';
  function getRecent() { try { return JSON.parse(localStorage.getItem(RECENT_KEY)) || []; } catch { return []; } }
  // Aktuelle Produktansicht aufzeichnen
  const tracker = $('[data-track-view]');
  if (tracker) {
    const id = Number(tracker.getAttribute('data-track-view'));
    let recent = getRecent().filter((x) => x !== id);
    recent.unshift(id);
    localStorage.setItem(RECENT_KEY, JSON.stringify(recent.slice(0, 12)));
  }
  // Auf der Startseite anzeigen
  const recentSection = $('[data-recent-section]');
  const recentGrid = $('[data-recent-grid]');
  if (recentSection && recentGrid) {
    const ids = getRecent();
    if (ids.length) {
      loadCatalog().then((items) => {
        const map = new Map(items.map((p) => [p.id, p]));
        const list = ids.map((id) => map.get(id)).filter(Boolean).slice(0, 6);
        if (!list.length) return;
        recentSection.hidden = false;
        recentGrid.innerHTML = list.map(cardHtml).join('');
        wireCardButtons(recentGrid);
      });
    }
  }

  /* ---------------- Sortierung (Auto-Submit) ---------------- */
  const sortSelect = $('[data-sort-select]');
  if (sortSelect) sortSelect.addEventListener('change', () => {
    const f = sortSelect.closest('form'); if (f) f.submit();
  });

  /* ---------------- Bild-Lightbox ---------------- */
  const zoomable = $('[data-zoomable]');
  if (zoomable) {
    zoomable.style.cursor = 'zoom-in';
    zoomable.addEventListener('click', () => {
      const box = document.createElement('div');
      box.className = 'lightbox';
      box.innerHTML = '<img src="' + zoomable.getAttribute('src') + '" alt="">';
      box.addEventListener('click', () => { box.classList.remove('in'); setTimeout(() => box.remove(), 250); document.body.classList.remove('no-scroll'); });
      document.body.appendChild(box);
      document.body.classList.add('no-scroll');
      requestAnimationFrame(() => box.classList.add('in'));
    });
    // Galerie-Thumbnails sollen das zoombare Bild aktualisieren -> schon über data-gallery gelöst
  }

  /* ---------------- Scroll-Fortschritt + Nach-oben ---------------- */
  const progress = $('[data-progress]');
  const toTop = $('[data-to-top]');
  function onScroll() {
    const st = window.scrollY || document.documentElement.scrollTop;
    const h = document.documentElement.scrollHeight - window.innerHeight;
    if (progress) progress.style.width = (h > 0 ? (st / h) * 100 : 0) + '%';
    if (toTop) toTop.hidden = st < 600;
  }
  window.addEventListener('scroll', onScroll, { passive: true });
  onScroll();
  if (toTop) toTop.addEventListener('click', () => window.scrollTo({ top: 0, behavior: reduce ? 'auto' : 'smooth' }));

  /* ---------------- Warenkorbseite: Menge ohne OK-Button ---------------- */
  $$('[data-cart-qty]').forEach((form) => {
    const input = form.querySelector('input[name="qty"]');
    if (!input) return;
    let t = null;
    const submit = () => {
      const min = Number(input.min) || 1;
      const max = Number(input.max) || 99;
      let v = Math.round(Number(input.value) || min);
      input.value = Math.min(max, Math.max(min, v));
      form.submit();
    };
    const minus = form.querySelector('[data-qty-minus]');
    const plus  = form.querySelector('[data-qty-plus]');
    if (minus) minus.addEventListener('click', () => { input.stepDown(); submit(); });
    if (plus)  plus.addEventListener('click',  () => { input.stepUp();  submit(); });
    input.addEventListener('change', () => { clearTimeout(t); t = setTimeout(submit, 150); });
  });

  /* ---------------- Bewertungsformular ---------------- */
  const reviewForm = $('[data-review-form]');
  if (reviewForm) {
    reviewForm.addEventListener('submit', (e) => {
      e.preventDefault();
      const fd = new FormData(reviewForm);
      fd.set('product_id', reviewForm.getAttribute('data-product-id') || '');
      const msg = $('[data-review-msg]', reviewForm);
      const btn = reviewForm.querySelector('button[type="submit"]');
      if (btn) btn.classList.add('loading');
      fetch(BASE_PATH + '/api/review.php', { method: 'POST', body: fd, headers: { Accept: 'application/json' } })
        .then((r) => r.json().then((d) => ({ ok: r.ok, d })))
        .then(({ ok, d }) => {
          if (btn) btn.classList.remove('loading');
          if (msg) {
            msg.hidden = false;
            msg.textContent = (d && (d.message || d.error)) || 'Unbekannter Fehler';
            msg.style.color = ok && d.ok ? 'var(--ok)' : 'var(--danger)';
          }
          if (ok && d.ok) { reviewForm.reset(); toast('Danke für deine Bewertung!'); }
        })
        .catch(() => { if (btn) btn.classList.remove('loading'); toast('Netzwerkfehler', 'err'); });
    });
  }

  /* ---------------- Helpers ---------------- */
  function esc(s) { const d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; }

  // Standard-Produktkarte (für dynamisch gerenderte Bereiche)
  function cardHtml(p) {
    const img = p.image || fallbackImg(p.name);
    let badge = '';
    if (p.oldPriceText && p.price_cents && p.sale_price_cents) {
      const pct = Math.round((1 - p.sale_price_cents / p.price_cents) * 100);
      badge = '<span class="badge-sale">-' + pct + '%</span>';
    }
    const sold = p.stock <= 0 ? '<span class="badge-soldout">Ausverkauft</span>' : '';
    const quick = p.stock > 0
      ? '<button class="quick-add" data-quick-add data-id="' + p.id + '" data-slug="' + p.slug + '" data-has-sizes="0"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg><span>In den Warenkorb</span></button>'
      : '';
    const priceBlock = p.oldPriceText
      ? '<span class="price-sale">' + p.priceText + '</span><span class="price-old">' + p.oldPriceText + '</span>'
      : '<span class="price-now">' + p.priceText + '</span>';
    return '<article class="product-card"><div class="product-card-media">' +
      '<a class="media-link" href="' + p.url + '"><img src="' + img + '" alt="" loading="lazy"><span class="card-shine"></span></a>' +
      badge + sold +
      '<button class="wish-btn" data-wish="' + p.id + '" aria-label="Merken"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 21s-7-4.5-9.5-9A5 5 0 0 1 12 6a5 5 0 0 1 9.5 6c-2.5 4.5-9.5 9-9.5 9z"/></svg></button>' +
      quick + '</div>' +
      '<div class="product-card-body"><span class="product-cat">' + esc(p.category) + '</span>' +
      '<h3 class="product-name"><a href="' + p.url + '">' + esc(p.name) + '</a></h3>' +
      '<div class="product-price">' + priceBlock + '</div></div></article>';
  }

  // Buttons in dynamisch gerenderten Karten verdrahten
  function wireCardButtons(container) {
    $$('[data-quick-add]', container).forEach((btn) => {
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        if (btn.getAttribute('data-has-sizes') === '1') { window.location.href = BASE_PATH + '/produkt.php?slug=' + encodeURIComponent(btn.getAttribute('data-slug')); return; }
        btn.classList.add('loading');
        addToCart(btn.getAttribute('data-id'), '', 1).then(({ ok, d }) => {
          btn.classList.remove('loading');
          if (ok && d.ok) { renderDrawer(d); toast('„' + d.added + '" hinzugefügt ✓'); }
          else toast(d.error || 'Konnte nicht hinzufügen', 'err');
        }).catch(() => { btn.classList.remove('loading'); toast('Netzwerkfehler', 'err'); });
      });
    });
    $$('[data-wish]', container).forEach((btn) => {
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        const id = Number(btn.getAttribute('data-wish'));
        let wish = getWish();
        if (wish.includes(id)) { wish = wish.filter((x) => x !== id); toast('Von der Wunschliste entfernt'); }
        else { wish.push(id); toast('Zur Wunschliste hinzugefügt ♥'); }
        setWish(wish); syncWishButtons();
      });
    });
    syncWishButtons();
  }
  function fallbackImg(name) {
    const n = (name || '?').trim().charAt(0).toUpperCase();
    const svg = "<svg xmlns='http://www.w3.org/2000/svg' width='600' height='750'><rect width='600' height='750' fill='%2316161a'/><text x='300' y='430' font-size='300' font-weight='800' fill='%23a5b4fc' text-anchor='middle' font-family='Arial'>" + n + "</text></svg>";
    return 'data:image/svg+xml,' + encodeURIComponent(svg).replace(/%23/g, '#');
  }
})();
