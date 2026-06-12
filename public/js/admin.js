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
    const groupsWrap = $('#option-groups-list');
    const previewWrap = $('#variant-preview');
    const optionBuilder = $('#option-builder');
    const variantToggle = form.querySelector('input[name="has_variants"]');
    const productNameInput = form.querySelector('input[name="name"]');

    let existingImages = parseJsonScript('existing-images-data', []);
    let optionGroups = parseJsonScript('existing-option-groups-data', []).map(normalizeGroup).filter((group) => group.label);
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

    function renderOptionGroups() {
      if (!groupsWrap) return;
      groupsWrap.innerHTML = '';
      if (!optionGroups.length) {
        groupsWrap.innerHTML = '<p class="muted small">Keine Optionen aktiv. Füge Size, Color oder eine eigene Option hinzu.</p>';
        renderVariantPreview();
        return;
      }
      optionGroups.forEach((group, index) => {
        const card = document.createElement('div');
        card.className = 'option-card';
        const presetValues = PRESET_OPTIONS[group.key] || [];
        const customValues = group.values.filter((value) => !presetValues.includes(value));
        card.innerHTML = `
          <div class="variant-header">
            <strong>${esc(group.label || `Option ${index + 1}`)}</strong>
            <button type="button" class="btn btn-ghost btn-sm" data-remove-group>Entfernen</button>
          </div>
          <div class="option-card-body">
            <label class="field"><span>Name</span><input type="text" name="group_label" maxlength="80" value="${esc(group.label)}" placeholder="Size"></label>
            <div>
              <span class="field-caption">Werte anklicken</span>
              <div class="option-chip-grid" data-chip-grid>
                ${presetValues.map((value) => `
                  <label class="option-chip">
                    <input type="checkbox" value="${esc(value)}" ${group.values.includes(value) ? 'checked' : ''}>
                    <span>${esc(value)}</span>
                  </label>
                `).join('')}
                ${customValues.map((value) => `
                  <label class="option-chip custom-chip">
                    <input type="checkbox" value="${esc(value)}" checked>
                    <span>${esc(value)}</span>
                  </label>
                `).join('')}
              </div>
              <div class="option-add-row">
                <input type="text" data-new-value placeholder="${group.key === 'color' ? 'Eigene Farbe' : 'Eigener Wert'}" maxlength="40">
                <button type="button" class="btn btn-ghost btn-sm" data-add-value>Hinzufügen</button>
              </div>
            </div>
          </div>
        `;
        card.querySelector('[name="group_label"]').addEventListener('input', (event) => {
          optionGroups[index].label = event.target.value.trim();
          if (!PRESET_OPTIONS[optionGroups[index].key]) {
            optionGroups[index].key = slugify(event.target.value) || optionGroups[index].key || `option-${index + 1}`;
          }
          renderVariantPreview();
        });
        function syncValuesFromChips() {
          optionGroups[index].values = $$('[data-chip-grid] input:checked', card).map((input) => input.value);
          renderVariantPreview();
        }
        $$('[data-chip-grid] input', card).forEach((input) => {
          input.addEventListener('change', syncValuesFromChips);
        });
        card.querySelector('[data-add-value]').addEventListener('click', () => {
          const input = card.querySelector('[data-new-value]');
          const value = input.value.trim();
          if (!value) return;
          if (!optionGroups[index].values.includes(value)) optionGroups[index].values.push(value);
          input.value = '';
          renderOptionGroups();
        });
        card.querySelector('[data-new-value]').addEventListener('keydown', (event) => {
          if (event.key !== 'Enter') return;
          event.preventDefault();
          card.querySelector('[data-add-value]').click();
        });
        card.querySelector('[data-remove-group]').addEventListener('click', () => {
          optionGroups.splice(index, 1);
          renderOptionGroups();
        });
        groupsWrap.appendChild(card);
      });
      renderVariantPreview();
    }

    function variantMap() {
      return new Map(existingVariants.map((variant) => [String(variant.size || variant.signature || ''), variant]));
    }

    function renderVariantPreview() {
      if (!previewWrap) return;
      const combos = buildCombinations(optionGroups);
      const productName = (productNameInput?.value || form.getAttribute('data-product-name') || 'Produkt').trim() || 'Produkt';
      const map = variantMap();
      const selectedDefault = existingVariants.find((variant) => variant.is_default)?.size || combos[0]?.signature || '';

      if (!combos.length) {
        previewWrap.innerHTML = '<p class="muted small">Sobald Optionen Werte haben, erscheinen hier automatisch alle Varianten.</p>';
        return;
      }

      previewWrap.innerHTML = combos.map((combo) => {
        const saved = map.get(combo.signature) || {};
        const checked = saved.is_default || combo.signature === selectedDefault;
        const imageUrl = (saved.images && saved.images[0] && saved.images[0].src) || '';
        return `
          <div class="variant-row" data-signature="${esc(combo.signature)}" data-option-values='${esc(JSON.stringify(combo.values))}'>
            <div class="variant-header">
              <strong>${esc(productName)} <span>${esc(combo.titleSuffix)}</span></strong>
              <label class="checkbox"><input type="radio" name="variant_default_signature" value="${esc(combo.signature)}" ${checked ? 'checked' : ''}> Standard</label>
            </div>
            <div class="field-grid">
              <label class="field"><span>SKU</span><input name="variant_sku" value="${esc(saved.sku || '')}" maxlength="100" placeholder="optional"></label>
              <label class="field"><span>Bestand</span><input type="number" name="variant_stock" min="0" value="${Number(saved.stock || 0)}"></label>
              <label class="field"><span>Preis (CHF) <small class="muted">optional</small></span><input type="text" name="variant_price" value="${saved.variant_price_cents != null ? (Number(saved.variant_price_cents) / 100).toFixed(2) : ''}" placeholder="wie Produktpreis"></label>
              <label class="field span-2"><span>Variantenbild <small class="muted">optional</small></span><input type="text" name="variant_image_url" value="${esc(imageUrl)}" placeholder="https://... oder Datei hochladen"></label>
            </div>
            <p class="muted small">${esc(combo.values.map((entry) => `${entry.label}: ${entry.value}`).join(' · '))}</p>
          </div>
        `;
      }).join('');

      // Attach upload buttons next to each variant image URL input
      $$('.variant-row [name="variant_image_url"]', previewWrap).forEach((urlInput) => {
        const wrap = urlInput.closest('.field');
        if (!wrap) return;
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn-ghost btn-sm';
        btn.style.cssText = 'margin-top:4px;font-size:.75rem';
        btn.textContent = '↑ Bild hochladen';
        const fi = document.createElement('input');
        fi.type = 'file';
        fi.accept = 'image/*';
        fi.style.display = 'none';
        btn.addEventListener('click', () => fi.click());
        fi.addEventListener('change', async () => {
          const file = fi.files[0];
          if (!file) return;
          btn.disabled = true; btn.textContent = 'Lädt…';
          try {
            const fd = new FormData();
            fd.append('image', file);
            const res = await fetch(BASE_PATH + '/admin/api/upload.php', { method: 'POST', body: fd });
            const data = await res.json().catch(() => ({}));
            if (data.ok && data.src) {
              urlInput.value = data.src;
              btn.textContent = '✓ Hochgeladen';
            } else {
              window.alert('Upload fehlgeschlagen');
              btn.disabled = false; btn.textContent = '↑ Bild hochladen';
            }
          } catch {
            window.alert('Netzwerkfehler beim Upload');
            btn.disabled = false; btn.textContent = '↑ Bild hochladen';
          }
        });
        wrap.appendChild(btn);
        wrap.appendChild(fi);
      });
    }

    function addOption(kind) {
      if (kind === 'size') {
        optionGroups.push({ key: 'size', label: 'Größe', values: ['S', 'M', 'L', 'XL'] });
      } else if (kind === 'color') {
        optionGroups.push({ key: 'color', label: 'Farbe', values: ['Schwarz', 'Weiß'] });
      } else {
        optionGroups.push({ key: `option-${optionGroups.length + 1}`, label: 'Variante', values: [] });
      }
      renderOptionGroups();
    }

    const noVariantStock = $('#no-variant-stock');

    function updateVariantMode() {
      const enabled = !!variantToggle?.checked;
      if (optionBuilder) optionBuilder.hidden = !enabled;
      if (noVariantStock) noVariantStock.hidden = enabled;
      if (enabled && !optionGroups.length) {
        optionGroups.push({ key: 'size', label: 'Größe', values: ['S', 'M', 'L', 'XL'] });
        renderOptionGroups();
      }
    }

    function collectVariants() {
      return $$('#variant-preview .variant-row').map((row) => {
        const get = (name) => row.querySelector(`[name="${name}"]`)?.value || '';
        const signature = row.getAttribute('data-signature') || '';
        const checked = document.querySelector('input[name="variant_default_signature"]:checked')?.value;
        return {
          signature,
          option_values: JSON.parse(row.getAttribute('data-option-values') || '[]'),
          sku: get('variant_sku').trim(),
          stock: Math.max(0, Number(get('variant_stock')) || 0),
          min_stock: 3,
          variant_price_cents: parseCents(get('variant_price')),
          image_url: get('variant_image_url').trim(),
          is_default: checked === signature,
        };
      });
    }

    function validateBeforeSubmit(formData, hasVariants) {
      if (!String(formData.get('name') || '').trim()) return 'Bitte gib einen Produktnamen ein.';
      if (parseCents(formData.get('price')) == null) return 'Bitte gib einen gültigen Preis ein.';
      if (hasVariants) {
        const groups = optionGroups.map(normalizeGroup).filter((group) => group.label && group.values.length);
        if (!groups.length) return 'Bitte füge mindestens eine Option mit Werten hinzu.';
        if (!buildCombinations(groups).length) return 'Bitte prüfe die Varianten-Werte.';
      }
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

    renderExistingImages();
    renderOptionGroups();
    updateVariantMode();

    if (fileInput) fileInput.addEventListener('change', renderUploadPreview);
    if (variantToggle) variantToggle.addEventListener('change', updateVariantMode);
    if (productNameInput) productNameInput.addEventListener('input', renderVariantPreview);
    $$('[data-add-option]').forEach((button) => {
      button.addEventListener('click', () => addOption(button.getAttribute('data-add-option')));
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

      if (hasVariants) {
        const groups = optionGroups.map(normalizeGroup).filter((group) => group.label && group.values.length);
        formData.set('option_groups', JSON.stringify(groups));
        formData.set('variants', JSON.stringify(collectVariants()));
      } else {
        formData.set('option_groups', '[]');
        formData.set('variants', '[]');
      }

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
