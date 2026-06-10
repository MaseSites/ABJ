// ABJ Admin – Lager-Tab (Bestandsverwaltung)
// CSP-konform: kein eval, kein Inline-Script
(function () {
  'use strict';

  const BASE_PATH = document.documentElement.getAttribute('data-base-path') || '';
  const tbody = document.getElementById('inv-rows');
  if (!tbody) return;

  const productId = (document.getElementById('product-id-val') || {}).value;
  const csrfInput = document.getElementById('csrf-val');
  const csrf = (csrfInput ? csrfInput.value : '') ||
    ((document.querySelector('meta[name="csrf-token"]') || {}).content || '');
  const alertEl = document.getElementById('inv-alert');
  const okEl = document.getElementById('inv-ok');

  function newRow(size, color, sku) {
    const tr = document.createElement('tr');
    const fields = [
      ['size', 'text', size || '', 'M'],
      ['color', 'text', color || '', ''],
      ['sku', 'text', sku || '', 'ABJ-001'],
    ];
    let html = fields.map(([n, t, v, ph]) =>
      `<td><input class="inv-field" name="${n}" type="${t}" value="${esc(v)}" maxlength="100" placeholder="${ph}"></td>`
    ).join('');
    html += `<td><input class="inv-field inv-stock" name="stock" type="number" min="0" max="999999" value="0" style="width:80px"></td>`;
    html += `<td class="muted">0</td>`;
    html += `<td class="inv-avail"><strong>0</strong></td>`;
    html += `<td><input class="inv-field" name="min_stock" type="number" min="0" max="1000" value="3" style="width:70px"></td>`;
    html += `<td><input class="inv-field" name="next_delivery" type="date" value=""></td>`;
    html += `<td><input class="inv-field" name="notes" type="text" value="" maxlength="500" style="min-width:160px"></td>`;
    html += `<td><button type="button" class="btn btn-danger btn-sm" data-remove-row>✕</button></td>`;
    tr.innerHTML = html;
    tbody.appendChild(tr);
    wireRow(tr);
    return tr;
  }

  function esc(s) {
    const d = document.createElement('div');
    d.textContent = String(s || '');
    return d.innerHTML;
  }

  function wireRow(tr) {
    const removeBtn = tr.querySelector('[data-remove-row]');
    if (removeBtn) removeBtn.addEventListener('click', () => tr.remove());
    const stockInput = tr.querySelector('[name="stock"]');
    if (stockInput) {
      stockInput.addEventListener('input', () => {
        const avail = tr.querySelector('.inv-avail');
        if (avail) avail.innerHTML = '<strong>' + Math.max(0, Number(stockInput.value) || 0) + '</strong>';
      });
    }
  }

  // Wire existing rows
  Array.from(tbody.querySelectorAll('tr')).forEach(wireRow);

  // Add row button
  const addBtn = document.getElementById('add-row');
  if (addBtn) addBtn.addEventListener('click', () => newRow());

  // Fill from product sizes
  const fillBtn = document.getElementById('fill-sizes');
  if (fillBtn) {
    fillBtn.addEventListener('click', () => {
      let sizes = [];
      try { sizes = JSON.parse(fillBtn.getAttribute('data-sizes') || '[]'); } catch {}
      const existingSizes = new Set(
        Array.from(tbody.querySelectorAll('[name="size"]')).map((i) => i.value)
      );
      sizes.forEach((s) => { if (!existingSizes.has(s)) newRow(s); });
    });
  }

  // Save
  const saveBtn = document.getElementById('save-inv');
  if (saveBtn) {
    saveBtn.addEventListener('click', async () => {
      if (alertEl) alertEl.hidden = true;
      if (okEl) okEl.hidden = true;
      saveBtn.disabled = true;
      saveBtn.textContent = 'Speichern…';

      const rows = Array.from(tbody.querySelectorAll('tr')).map((tr) => {
        const g = (name) => {
          const el = tr.querySelector('[name="' + name + '"]');
          return el ? el.value : '';
        };
        return {
          size: g('size'), color: g('color'), sku: g('sku'),
          stock: Math.max(0, Number(g('stock')) || 0),
          min_stock: Math.max(0, Number(g('min_stock')) || 3),
          next_delivery: g('next_delivery'), notes: g('notes'),
        };
      });

      const fd = new URLSearchParams();
      fd.set('product_id', productId);
      fd.set('variants', JSON.stringify(rows));

      try {
        const res = await fetch(BASE_PATH + '/admin/lager/speichern', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-CSRF-Token': csrf,
            'Accept': 'application/json',
          },
          body: fd.toString(),
        });
        const data = await res.json().catch(() => ({}));
        if (res.ok && data.ok) {
          if (okEl) { okEl.hidden = false; okEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' }); }
        } else {
          if (alertEl) { alertEl.textContent = data.error || 'Fehler beim Speichern.'; alertEl.hidden = false; }
        }
      } catch (e) {
        if (alertEl) { alertEl.textContent = 'Netzwerkfehler.'; alertEl.hidden = false; }
      } finally {
        saveBtn.disabled = false;
        saveBtn.textContent = 'Speichern';
      }
    });
  }
})();
