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
    const variantWrap = $('#vs-rows');
    const variantBox = $('#variant-simple');
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

    // ── Einfache Varianten-Tabelle (Name, Bestand, Preis, Bild) ──
    function variantRowEl(data) {
      const value = data.value || '';
      const stock = Number(data.stock || 0);
      const price = data.variant_price_cents != null ? (Number(data.variant_price_cents) / 100).toFixed(2) : '';
      const imgUrl = (data.images && data.images[0] && data.images[0].src) || data.image_url || '';
      const row = document.createElement('div');
      row.className = 'vs-row';
      row.setAttribute('data-vs-row', '');
      row.innerHTML = `
        <div class="vs-main">
          <input class="vs-name" type="text" maxlength="40" placeholder="z.B. M oder Rot" value="${esc(value)}">
          <input class="vs-stock" type="number" min="0" max="999999" placeholder="0" value="${stock}">
          <input class="vs-price" type="text" inputmode="decimal" placeholder="—" value="${price}">
          <label class="vs-default"><input type="radio" name="vs_default" ${data.is_default ? 'checked' : ''}></label>
          <button type="button" class="vs-remove" title="Entfernen" aria-label="Entfernen">×</button>
        </div>
        <div class="vs-img">
          <img class="vs-thumb" src="${esc(imgUrl)}" alt=""${imgUrl ? '' : ' hidden'}>
          <input class="vs-image-url" type="text" placeholder="Bild dieser Variante (z.B. Farbe) – URL oder hochladen" value="${esc(imgUrl)}">
          <button type="button" class="btn btn-ghost btn-sm vs-upload">↑ Bild</button>
        </div>
      `;
      row.querySelector('.vs-remove').addEventListener('click', () => { row.remove(); ensureOneDefault(); });

      const urlInput = row.querySelector('.vs-image-url');
      const thumb = row.querySelector('.vs-thumb');
      function syncThumb() {
        const v = urlInput.value.trim();
        if (v) { thumb.src = v; thumb.hidden = false; } else { thumb.hidden = true; }
      }
      urlInput.addEventListener('input', syncThumb);

      const upBtn = row.querySelector('.vs-upload');
      const fileEl = document.createElement('input');
      fileEl.type = 'file'; fileEl.accept = 'image/*'; fileEl.style.display = 'none';
      row.querySelector('.vs-img').appendChild(fileEl);
      upBtn.addEventListener('click', () => fileEl.click());
      fileEl.addEventListener('change', async () => {
        const file = fileEl.files[0];
        if (!file) return;
        upBtn.disabled = true; upBtn.textContent = 'Lädt…';
        try {
          const fd = new FormData();
          fd.append('image', file);
          const res = await fetch(BASE_PATH + '/admin/api/upload.php', { method: 'POST', body: fd });
          const d = await res.json().catch(() => ({}));
          if (d.ok && d.src) { urlInput.value = d.src; syncThumb(); upBtn.textContent = '✓ Bild'; }
          else { window.alert('Upload fehlgeschlagen'); upBtn.textContent = '↑ Bild'; }
        } catch { window.alert('Netzwerkfehler beim Upload'); upBtn.textContent = '↑ Bild'; }
        finally { upBtn.disabled = false; }
      });
      return row;
    }

    function ensureOneDefault() {
      const rows = $$('#vs-rows .vs-row');
      if (!rows.length) return;
      if (!rows.some((r) => r.querySelector('input[name="vs_default"]').checked)) {
        rows[0].querySelector('input[name="vs_default"]').checked = true;
      }
    }

    function renderVariantRows() {
      if (!variantWrap) return;
      variantWrap.innerHTML = '';
      (existingVariants || []).forEach((v) => {
        const value = (v.option_values && v.option_values[0] && v.option_values[0].value) || v.size || v.title || '';
        if (!String(value).trim()) return;
        variantWrap.appendChild(variantRowEl({
          value, stock: v.stock || 0, variant_price_cents: v.variant_price_cents, is_default: v.is_default,
        }));
      });
      ensureOneDefault();
    }

    function addVariantRow(data) {
      if (!variantWrap) return;
      // Doppelte Grössennamen vermeiden
      const name = (data && data.value || '').trim().toLowerCase();
      if (name && $$('#vs-rows .vs-name').some((i) => i.value.trim().toLowerCase() === name)) return;
      variantWrap.appendChild(variantRowEl(data || {}));
      ensureOneDefault();
    }

    function updateVariantMode() {
      const enabled = !!variantToggle?.checked;
      if (variantBox) variantBox.hidden = !enabled;
      if (noVariantStock) noVariantStock.hidden = enabled;
      if (enabled && !$$('#vs-rows .vs-row').length) {
        ['S', 'M', 'L'].forEach((s) => addVariantRow({ value: s, stock: 0 }));
      }
    }

    function collectVariants() {
      return $$('#vs-rows .vs-row').map((row) => {
        const value = row.querySelector('.vs-name').value.trim();
        if (!value) return null;
        return {
          option_values: [{ key: 'size', label: 'Variante', value }],
          stock: Math.max(0, Number(row.querySelector('.vs-stock').value) || 0),
          variant_price_cents: parseCents(row.querySelector('.vs-price').value),
          image_url: (row.querySelector('.vs-image-url')?.value || '').trim(),
          is_default: row.querySelector('input[name="vs_default"]').checked,
        };
      }).filter(Boolean);
    }

    function validateBeforeSubmit(formData, hasVariants) {
      if (!String(formData.get('name') || '').trim()) return 'Bitte gib einen Produktnamen ein.';
      if (parseCents(formData.get('price')) == null) return 'Bitte gib einen gültigen Preis ein.';
      if (hasVariants && !collectVariants().length) return 'Bitte gib mindestens einer Größe einen Namen.';
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
    try { renderVariantRows(); } catch (e) { /* noop */ }
    try { updateVariantMode(); } catch (e) { /* noop */ }

    if (fileInput) fileInput.addEventListener('change', renderUploadPreview);
    if (variantToggle) variantToggle.addEventListener('change', updateVariantMode);
    const addRowBtn = $('[data-vs-add]');
    if (addRowBtn) addRowBtn.addEventListener('click', () => addVariantRow());
    $$('[data-vs-quick]').forEach((b) => {
      b.addEventListener('click', () => addVariantRow({ value: b.getAttribute('data-vs-quick'), stock: 0 }));
    });

    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      clearError();

      const submit = form.querySelector('button[type="submit"]');
      const oldText = submit?.textContent || 'Speichern';
      if (submit) { submit.disabled = true; submit.textContent = 'Lädt hoch…'; }

      await uploadPendingFiles();

      if (submit) submit.textContent = 'Speichert…';

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

  initProductForm();
  initProductDelete();
  initProductFilter();
  initColorPreview();
  initConfirmForms();
  initMarkSeen();
  initStockSteppers();
})();
