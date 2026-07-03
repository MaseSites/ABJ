// ABJ Admin: product editor, product deletion/filtering, settings color preview.
(function () {
  'use strict';

  const $ = (selector, root = document) => root.querySelector(selector);
  const $$ = (selector, root = document) => Array.from(root.querySelectorAll(selector));
  const BASE_PATH = document.documentElement.getAttribute('data-base-path') || '';

  function csrf(form) {
    const input = (form && form.querySelector('input[name="_csrf"]')) || document.querySelector('input[name="_csrf"]');
    return input ? input.value : ((document.querySelector('meta[name="csrf-token"]') || {}).content || '');
  }

  function esc(value) {
    const div = document.createElement('div');
    div.textContent = value == null ? '' : String(value);
    return div.innerHTML;
  }

  function parseJsonScript(id, fallback) {
    const el = document.getElementById(id);
    if (!el) return fallback;
    try { return JSON.parse(el.textContent.trim() || 'null') ?? fallback; } catch { return fallback; }
  }

  function slugify(text) {
    return String(text || '')
      .toLowerCase()
      .replace(/ä/g, 'ae').replace(/ö/g, 'oe').replace(/ü/g, 'ue').replace(/ß/g, 'ss')
      .normalize('NFKD')
      .replace(/[\u0300-\u036f]/g, '')
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/^-+|-+$/g, '');
  }

  function parseCents(value) {
    const raw = String(value || '').trim();
    if (!raw) return null;
    const normalized = raw.replace(',', '.').replace(/[^0-9.]/g, '');
    const parsed = Number.parseFloat(normalized);
    return Number.isFinite(parsed) ? Math.round(parsed * 100) : null;
  }

  function normalizeGroup(group) {
    const label = String(group?.label || group?.name || '').trim();
    const key = String(group?.key || slugify(label)).trim() || slugify(label);
    const values = String(Array.isArray(group?.values) ? group.values.join('\n') : group?.values || '')
      .split(/[\n,]/)
      .map((value) => value.trim())
      .filter(Boolean);
    return { key, label, values: [...new Set(values)] };
  }

  const PRESET_OPTIONS = {
    size:  ['XS', 'S', 'M', 'L', 'XL', 'XXL', '3XL', 'One Size'],
    color: ['Schwarz', 'Weiß', 'Grau', 'Beige', 'Blau', 'Navy', 'Rot', 'Grün', 'Khaki', 'Olive', 'Braun', 'Orange', 'Lila', 'Pink'],
  };

  function buildCombinations(groups) {
    const normalized = groups.map(normalizeGroup).filter((group) => group.label && group.values.length);
    if (!normalized.length) return [];
    let combos = [{ values: [] }];
    normalized.forEach((group) => {
      const next = [];
      combos.forEach((combo) => {
        group.values.forEach((value) => {
          next.push({ values: combo.values.concat({ key: group.key, label: group.label, value }) });
        });
      });
      combos = next;
    });
    return combos.map((combo, index) => {
      const signature = combo.values.map((entry) => `${slugify(entry.key)}:${slugify(entry.value)}`).join('__');
      return {
        signature: signature || `variant-${index + 1}`,
        titleSuffix: combo.values.map((entry) => entry.value).join(' - '),
        values: combo.values,
      };
    });
  }

  function initProductForm() {
    const form = $('#product-form');
    if (!form) return;

    const alertBox = $('#form-alert');
    const existingWrap = $('#existing-images');
    const uploadPreview = $('#upload-preview');
    const fileInput = $('[data-file-input]');
    const vbColors = $('#vb-colors');
    const vbSizes = $('#vb-sizes');
    const vbMatrix = $('#vb-matrix');
    const variantBuilder = $('#variant-builder');
    const variantToggle = form.querySelector('input[name="has_variants"]');
    const noVariantStock = $('#no-variant-stock');

    let existingImages = parseJsonScript('existing-images-data', []);
    let existingVariants = parseJsonScript('existing-variants-data', []);

    function showError(message, issues) {
      const details = Array.isArray(issues) && issues.length
        ? ': ' + issues.map((issue) => `${issue.path ? issue.path.join('.') + ' ' : ''}${issue.message}`).join(', ')
        : '';
      if (!alertBox) {
        window.alert(message + details);
        return;
      }
      alertBox.textContent = message + details;
      alertBox.hidden = false;
      window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function clearError() {
      if (!alertBox) return;
      alertBox.hidden = true;
      alertBox.textContent = '';
    }

    function renderExistingImages() {
      if (!existingWrap) return;
      existingWrap.innerHTML = '';
      if (!existingImages.length) {
        existingWrap.innerHTML = '<p class="muted small">Noch keine bestehenden Bilder.</p>';
        return;
      }
      existingImages.forEach((image, index) => {
        const card = document.createElement('div');
        card.className = 'existing-image';
        card.innerHTML = `
          <img src="${esc(image.src)}" alt="Bild ${index + 1}">
          ${index === 0 ? '<span class="main-badge">Hauptbild</span>' : ''}
          <div class="img-tools">
            ${index > 0 ? '<button type="button" data-move="-1" title="Nach vorne">‹</button>' : ''}
            ${index < existingImages.length - 1 ? '<button type="button" data-move="1" title="Nach hinten">›</button>' : ''}
            <button type="button" data-remove title="Entfernen">×</button>
          </div>
        `;
        card.querySelector('[data-remove]').addEventListener('click', () => {
          existingImages.splice(index, 1);
          renderExistingImages();
        });
        $$('[data-move]', card).forEach((button) => {
          button.addEventListener('click', () => {
            const nextIndex = index + Number(button.getAttribute('data-move'));
            if (nextIndex < 0 || nextIndex >= existingImages.length) return;
            [existingImages[index], existingImages[nextIndex]] = [existingImages[nextIndex], existingImages[index]];
            renderExistingImages();
          });
        });
        existingWrap.appendChild(card);
      });
    }

    function renderUploadPreview() {
      if (!fileInput || !uploadPreview) return;
      uploadPreview.innerHTML = '';
      Array.from(fileInput.files || []).slice(0, 8).forEach((file) => {
        if (!file.type.startsWith('image/')) return;
        const card = document.createElement('div');
        card.className = 'existing-image is-new';
        card.innerHTML = '<span class="new-badge">Neu</span>';
        uploadPreview.appendChild(card);
        const reader = new FileReader();
        reader.onload = (event) => {
          card.insertAdjacentHTML('afterbegin', `<img src="${event.target.result}" alt="">`);
        };
        reader.readAsDataURL(file);
      });
    }

    // ===== Varianten-Builder: Farben (mit Bild) × Größen × Bestand =====
    let colors = [];   // [{ name, image }]
    let sizes  = [];    // ['S','M', ...]
    let stockMap = {};  // "color||size" -> stock
    let defaultKey = null;

    (function loadExistingVariants() {
      const colorImg = {};
      const colorOrder = [];
      (existingVariants || []).forEach((v) => {
        const c = v.color || '';
        const s = v.size || '';
        if (c) {
          if (!(c in colorImg)) { colorImg[c] = v.image || ''; colorOrder.push(c); }
          else if (v.image && !colorImg[c]) colorImg[c] = v.image;
        }
        if (s && !sizes.includes(s)) sizes.push(s);
        stockMap[c + '||' + s] = Number(v.stock || 0);
        if (v.is_default) defaultKey = c + '||' + s;
      });
      colors = colorOrder.map((name) => ({ name, image: colorImg[name] || '' }));
    })();

    function combos() {
      const cs = colors.map((c) => c.name.trim()).filter(Boolean);
      const ss = sizes.map((s) => s.trim()).filter(Boolean);
      if (cs.length && ss.length) {
        const out = [];
        cs.forEach((c) => ss.forEach((s) => out.push({ color: c, size: s })));
        return out;
      }
      if (cs.length) return cs.map((c) => ({ color: c, size: '' }));
      if (ss.length) return ss.map((s) => ({ color: '', size: s }));
      return [];
    }

    // ---- Farben rendern ----
    function renderColors() {
      if (!vbColors) return;
      vbColors.innerHTML = '';
      colors.forEach((color, idx) => {
        const row = document.createElement('div');
        row.className = 'vb-color';
        row.innerHTML = `
          <img class="vb-color-thumb" src="${esc(color.image)}" alt=""${color.image ? '' : ' hidden'}>
          <input class="vb-color-name" type="text" maxlength="40" placeholder="Farbe (z.B. Schwarz)" value="${esc(color.name)}">
          <input class="vb-color-img" type="text" placeholder="Bild-URL (optional)" value="${esc(color.image)}">
          <button type="button" class="btn btn-ghost btn-sm vb-color-upload">↑ Bild</button>
          <button type="button" class="vs-remove vb-color-remove" title="Entfernen">×</button>
        `;
        const nameI = row.querySelector('.vb-color-name');
        const imgI = row.querySelector('.vb-color-img');
        const thumb = row.querySelector('.vb-color-thumb');
        nameI.addEventListener('input', () => { colors[idx].name = nameI.value; renderMatrix(); });
        imgI.addEventListener('input', () => {
          colors[idx].image = imgI.value.trim();
          if (imgI.value.trim()) { thumb.src = imgI.value.trim(); thumb.hidden = false; } else thumb.hidden = true;
        });
        row.querySelector('.vb-color-remove').addEventListener('click', () => { colors.splice(idx, 1); renderColors(); renderMatrix(); });

        const upBtn = row.querySelector('.vb-color-upload');
        const fileEl = document.createElement('input');
        fileEl.type = 'file'; fileEl.accept = 'image/*'; fileEl.style.display = 'none';
        row.appendChild(fileEl);
        upBtn.addEventListener('click', () => fileEl.click());
        fileEl.addEventListener('change', async () => {
          const file = fileEl.files[0];
          if (!file) return;
          upBtn.disabled = true; upBtn.textContent = 'Lädt...';
          try {
            const fd = new FormData(); fd.append('image', file);
            const res = await fetch(BASE_PATH + '/admin/api/upload.php', { method: 'POST', body: fd });
            const d = await res.json().catch(() => ({}));
            if (d.ok && d.src) { colors[idx].image = d.src; imgI.value = d.src; thumb.src = d.src; thumb.hidden = false; upBtn.textContent = '✓ Bild'; }
            else { window.alert('Upload fehlgeschlagen'); upBtn.textContent = '↑ Bild'; }
          } catch { window.alert('Netzwerkfehler beim Upload'); upBtn.textContent = '↑ Bild'; }
          finally { upBtn.disabled = false; }
        });
        vbColors.appendChild(row);
      });
    }

    function addColor(name) {
      const n = (name || '').trim();
      if (n && colors.some((c) => c.name.trim().toLowerCase() === n.toLowerCase())) return;
      colors.push({ name: n, image: '' });
      renderColors(); renderMatrix();
    }

    // ---- Größen rendern ----
    function renderSizes() {
      if (!vbSizes) return;
      vbSizes.innerHTML = '';
      sizes.forEach((size, idx) => {
        const chip = document.createElement('span');
        chip.className = 'vb-chip';
        chip.innerHTML = `${esc(size)} <button type="button" title="Entfernen">×</button>`;
        chip.querySelector('button').addEventListener('click', () => { sizes.splice(idx, 1); renderSizes(); renderMatrix(); });
        vbSizes.appendChild(chip);
      });
    }

    function addSize(name) {
      const n = (name || '').trim();
      if (!n || sizes.some((s) => s.toLowerCase() === n.toLowerCase())) return;
      sizes.push(n);
      renderSizes(); renderMatrix();
    }

    // ---- Bestand-Matrix rendern ----
    function renderMatrix() {
      if (!vbMatrix) return;
      const list = combos();
      if (!list.length) {
        vbMatrix.innerHTML = '<p class="muted small">Füge mindestens eine Farbe oder Größe hinzu.</p>';
        return;
      }
      vbMatrix.innerHTML = '';
      list.forEach((combo) => {
        const key = combo.color + '||' + combo.size;
        const label = [combo.color, combo.size].filter(Boolean).join(' / ');
        const stock = stockMap[key] != null ? stockMap[key] : 0;
        const row = document.createElement('div');
        row.className = 'vb-matrix-row';
        row.innerHTML = `
          <span class="vb-matrix-label">${esc(label)}</span>
          <input type="number" class="vb-stock" min="0" max="999999" value="${Number(stock)}">
          <label class="vb-default"><input type="radio" name="vb_default" ${defaultKey === key ? 'checked' : ''}> Standard</label>
        `;
        const stockI = row.querySelector('.vb-stock');
        stockI.addEventListener('input', () => { stockMap[key] = Math.max(0, Number(stockI.value) || 0); });
        row.querySelector('input[name="vb_default"]').addEventListener('change', () => { defaultKey = key; });
        vbMatrix.appendChild(row);
      });
      if (!vbMatrix.querySelector('input[name="vb_default"]:checked')) {
        const first = vbMatrix.querySelector('input[name="vb_default"]');
        if (first) { first.checked = true; }
      }
    }

    function renderVariantBuilder() {
      renderColors();
      renderSizes();
      renderMatrix();
    }

    function updateVariantMode() {
      const enabled = !!variantToggle?.checked;
      if (variantBuilder) variantBuilder.hidden = !enabled;
      if (noVariantStock) noVariantStock.hidden = enabled;
      if (enabled && !colors.length && !sizes.length) {
        sizes = ['S', 'M', 'L'];
        renderVariantBuilder();
      }
    }

    function collectVariants() {
      const def = vbMatrix ? (vbMatrix.querySelector('input[name="vb_default"]:checked') ? defaultKey : null) : null;
      const colorImg = {};
      colors.forEach((c) => { if (c.name.trim()) colorImg[c.name.trim()] = c.image || ''; });
      return combos().map((combo) => {
        const key = combo.color + '||' + combo.size;
        const ov = [];
        if (combo.color) ov.push({ key: 'color', label: 'Farbe', value: combo.color });
        if (combo.size)  ov.push({ key: 'size',  label: 'Größe', value: combo.size });
        return {
          option_values: ov,
          stock: Math.max(0, Number(stockMap[key]) || 0),
          image_url: combo.color ? (colorImg[combo.color] || '') : '',
          is_default: def === key,
        };
      });
    }

    function validateBeforeSubmit(formData, hasVariants) {
      if (!String(formData.get('name') || '').trim()) return 'Bitte gib einen Produktnamen ein.';
      if (parseCents(formData.get('price')) == null) return 'Bitte gib einen gültigen Preis ein.';
      if (hasVariants && !collectVariants().length) return 'Bitte füge mindestens eine Farbe oder Größe hinzu.';
      return null;
    }

    async function uploadPendingFiles() {
      if (!fileInput || !fileInput.files.length) return;
      const uploaded = [];
      for (const file of Array.from(fileInput.files).slice(0, 8)) {
        if (!file.type.startsWith('image/')) continue;
        try {
          const fd = new FormData();
          fd.append('image', file);
          const res = await fetch(BASE_PATH + '/admin/api/upload.php', { method: 'POST', body: fd });
          const data = await res.json().catch(() => ({}));
          if (data.ok && data.src) uploaded.push({ type: 'upload', src: data.src });
        } catch { /* ignore single-file failures */ }
      }
      if (uploaded.length) existingImages = [...existingImages, ...uploaded];
    }

    // Rendern abgesichert: selbst wenn hier etwas schiefgeht, bleibt der
    // Submit-Handler (weiter unten) aktiv und Speichern funktioniert weiter.
    try { renderExistingImages(); } catch (e) { /* noop */ }
    try { renderVariantBuilder(); } catch (e) { /* noop */ }
    try { updateVariantMode(); } catch (e) { /* noop */ }

    if (fileInput) fileInput.addEventListener('change', renderUploadPreview);
    if (variantToggle) variantToggle.addEventListener('change', updateVariantMode);

    const addColorBtn = $('[data-vb-add-color]');
    if (addColorBtn) addColorBtn.addEventListener('click', () => addColor(''));

    const sizeInput = $('#vb-size-input');
    const addSizeBtn = $('[data-vb-add-size]');
    function commitSizeInput() { if (sizeInput) { addSize(sizeInput.value); sizeInput.value = ''; sizeInput.focus(); } }
    if (addSizeBtn) addSizeBtn.addEventListener('click', commitSizeInput);
    if (sizeInput) sizeInput.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); commitSizeInput(); } });
    $$('[data-vb-size-quick]').forEach((b) => {
      b.addEventListener('click', () => addSize(b.getAttribute('data-vb-size-quick')));
    });

    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      clearError();

      const submit = form.querySelector('button[type="submit"]');
      const oldText = submit?.textContent || 'Speichern';
      if (submit) { submit.disabled = true; submit.textContent = 'Lädt hoch...'; }

      await uploadPendingFiles();

      if (submit) submit.textContent = 'Speichert...';

      const formData = new FormData(form);
      formData.delete('_csrf');
      formData.set('existing_images', JSON.stringify(existingImages));

      const hasVariants = !!variantToggle?.checked;
      formData.set('has_variants', hasVariants ? '1' : '0');

      const validationError = validateBeforeSubmit(formData, hasVariants);
      if (validationError) {
        showError(validationError);
        if (submit) { submit.disabled = false; submit.textContent = oldText; }
        return;
      }

      // Optionsgruppen leitet der Server aus den Varianten ab (immer synchron).
      formData.set('option_groups', '[]');
      formData.set('variants', hasVariants ? JSON.stringify(collectVariants()) : '[]');

      try {
        const response = await fetch(form.getAttribute('data-action'), {
          method: form.getAttribute('data-method') || 'POST',
          headers: { 'X-CSRF-Token': csrf(form), Accept: 'application/json' },
          body: formData,
        });
        const data = await response.json().catch(() => ({}));
        if (response.ok && data.ok) {
          window.location.href = BASE_PATH + '/admin/produkte.php';
          return;
        }
        showError(data.error || 'Speichern fehlgeschlagen.', data.issues);
      } catch {
        showError('Netzwerkfehler. Bitte erneut versuchen.');
      } finally {
        if (submit) {
          submit.disabled = false;
          submit.textContent = oldText;
        }
      }
    });
  }

  function initProductDelete() {
    $$('[data-delete-product]').forEach((button) => {
      button.addEventListener('click', async () => {
        const id = button.getAttribute('data-delete-product');
        const name = button.getAttribute('data-name') || 'dieses Produkt';
        if (!window.confirm(`"${name}" wirklich löschen?`)) return;
        try {
          const response = await fetch(BASE_PATH + '/admin/api/products.php?id=' + encodeURIComponent(id), {
            method: 'DELETE',
            headers: { 'X-CSRF-Token': csrf(null), Accept: 'application/json' },
          });
          if (response.ok) {
            button.closest('tr')?.remove();
          } else {
            window.alert('Löschen fehlgeschlagen.');
          }
        } catch {
          window.alert('Netzwerkfehler beim Löschen.');
        }
      });
    });
  }

  function initProductFilter() {
    const filter = $('[data-product-filter]') || $('[data-table-filter]');
    if (!filter) return;
    const table = $('[data-filter-table]') || $('.data-table');
    filter.addEventListener('input', () => {
      const query = filter.value.trim().toLowerCase();
      $$('tbody tr', table).forEach((row) => {
        row.style.display = row.textContent.toLowerCase().includes(query) ? '' : 'none';
      });
    });
  }

  function initColorPreview() {
    const colorRow = $('[data-color-row]');
    if (!colorRow) return;
    const preview = $('[data-color-preview]', colorRow);
    const get = (key) => colorRow.querySelector(`[data-color="${key}"]`)?.value;
    function paint() {
      const a = get('a');
      const b = get('b');
      const c = get('c');
      if (preview) preview.style.background = `linear-gradient(135deg, ${a}, ${b}, ${c})`;
      document.documentElement.style.setProperty('--accent', a);
      document.documentElement.style.setProperty('--accent-2', b);
      document.documentElement.style.setProperty('--accent-3', c);
      document.documentElement.style.setProperty('--grad', `linear-gradient(135deg, ${a} 0%, ${b} 55%, ${c} 130%)`);
    }
    $$('[data-color]', colorRow).forEach((input) => input.addEventListener('input', paint));
    paint();
  }

  function initConfirmForms() {
    $$('form[data-confirm]').forEach((form) => {
      form.addEventListener('submit', (event) => {
        const msg = form.getAttribute('data-confirm') || 'Wirklich?';
        if (!window.confirm(msg)) event.preventDefault();
      });
    });
  }

  // Lager: Bestand direkt per +/- ändern (ohne Bearbeiten-Seite)
  function initStockSteppers() {
    $$('[data-stock-stepper]').forEach((stepper) => {
      const row   = stepper.closest('[data-stock-row]');
      const input = $('[data-stock-input]', stepper);
      if (!row || !input) return;
      const id    = row.getAttribute('data-id');
      const minus = $('[data-stock-minus]', stepper);
      const plus  = $('[data-stock-plus]', stepper);
      let timer = null;

      function save(absoluteValue) {
        const fd = new URLSearchParams();
        fd.set('id', id);
        fd.set('stock', Math.max(0, Math.round(Number(absoluteValue) || 0)));
        stepper.classList.add('saving');
        fetch(BASE_PATH + '/admin/api/stock-adjust.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded', Accept: 'application/json' },
          body: fd.toString(),
        }).then((r) => r.json()).then((d) => {
          stepper.classList.remove('saving');
          if (!d || !d.ok) return;
          input.value = d.stock;
          const tag = $('[data-avail-tag]', row);
          if (tag) {
            tag.textContent = d.available;
            tag.className = 'tag ' + (d.is_out ? 'tag-off' : (d.is_low ? 'tag-warn' : 'tag-ok'));
          }
          const valCell = $('[data-value-cell]', row);
          if (valCell) valCell.textContent = d.valueText;
          row.classList.toggle('row-danger', d.is_out);
          row.classList.toggle('row-warn', !d.is_out && d.is_low);
          const tv = $('[data-total-value]'); if (tv) tv.textContent = d.totalValueText;
          const ts = $('[data-total-stock]'); if (ts) ts.textContent = d.totalStock;
          row.classList.add('just-saved');
          setTimeout(() => row.classList.remove('just-saved'), 600);
        }).catch(() => { stepper.classList.remove('saving'); });
      }

      if (minus) minus.addEventListener('click', () => { input.value = Math.max(0, (Number(input.value) || 0) - 1); save(input.value); });
      if (plus)  plus.addEventListener('click',  () => { input.value = (Number(input.value) || 0) + 1; save(input.value); });
      input.addEventListener('change', () => { clearTimeout(timer); timer = setTimeout(() => save(input.value), 200); });
    });
  }

  // Klick auf das rote NEU-Badge: Bestellung als gelesen markieren, Badge entfernen
  function initMarkSeen() {
    $$('[data-mark-seen]').forEach((badge) => {
      badge.addEventListener('click', () => {
        const ref = badge.getAttribute('data-ref');
        if (!ref || badge.classList.contains('removing')) return;
        const row = badge.closest('[data-order-row]');
        const fd = new URLSearchParams();
        fd.set('action', 'mark_seen');
        fd.set('ref', ref);
        badge.classList.add('removing');
        fetch(BASE_PATH + '/admin/bestellungen.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded', Accept: 'application/json' },
          body: fd.toString(),
        }).then((r) => r.json()).then((d) => {
          if (d && d.ok) {
            setTimeout(() => badge.remove(), 200);
            if (row) row.classList.remove('order-row-new');
          } else {
            badge.classList.remove('removing');
          }
        }).catch(() => { badge.classList.remove('removing'); });
      });
    });
  }

  // Mobile-Sidebar (Off-Canvas-Drawer)
  function initSidebarDrawer() {
    const shell = $('.admin-shell');
    const burger = $('[data-admin-burger]');
    const overlay = $('[data-admin-overlay]');
    if (!shell || !burger) return;
    function open() { shell.classList.add('nav-open'); document.body.classList.add('admin-nav-locked'); burger.setAttribute('aria-expanded', 'true'); }
    function close() { shell.classList.remove('nav-open'); document.body.classList.remove('admin-nav-locked'); burger.setAttribute('aria-expanded', 'false'); }
    function toggle() { shell.classList.contains('nav-open') ? close() : open(); }
    burger.addEventListener('click', toggle);
    if (overlay) overlay.addEventListener('click', close);
    // Beim Tippen auf einen Navigationslink schließen
    $$('.admin-sidebar a').forEach((a) => a.addEventListener('click', close));
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') close(); });
  }

  initProductForm();
  initProductDelete();
  initProductFilter();
  initColorPreview();
  initConfirmForms();
  initMarkSeen();
  initStockSteppers();
  initSidebarDrawer();
})();
