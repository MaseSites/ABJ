// Befüllt fehlende Lager-Zeilen aus dem gepoolten products.stock.
//
// Für jedes Produkt OHNE Lager-Zeilen wird pro Grösse eine Zeile angelegt und
// der products.stock gleichmässig verteilt (Rest auf die ersten Grössen, damit
// die Summe exakt dem ursprünglichen Bestand entspricht). Produkte ohne Grössen
// erhalten eine einzige Zeile (size='').
//
// Die Werte sind ein Startpunkt - die genaue Aufteilung pro Grösse passt der
// Admin anschliessend im Dashboard unter /admin/lager/[ID] an.
//
// Idempotent: Produkte, die bereits Lager-Zeilen haben, bleiben unberührt.
//
//   npm run populate-inventory
import * as products from '../src/models/products.js';
import * as inventory from '../src/models/inventory.js';

const all = products.listAll();
let touched = 0;
let createdRows = 0;

for (const p of all) {
  if (inventory.hasInventory(p.id)) continue;

  const sizes = Array.isArray(p.sizes) && p.sizes.length ? p.sizes : [''];
  const total = Math.max(0, Number(p.stock) || 0);
  const base = Math.floor(total / sizes.length);
  let rest = total - base * sizes.length;

  const batch = sizes.map((size) => {
    const extra = rest > 0 ? 1 : 0;
    rest -= extra;
    return { product_id: p.id, size, color: '', stock: base + extra, min_stock: 3 };
  });

  inventory.upsertMany(batch);
  touched++;
  createdRows += batch.length;
  console.log(
    `  #${p.id} ${p.name}: ${total} → ` +
      batch.map((b) => `${b.size || '-'}:${b.stock}`).join('  ')
  );
}

if (touched === 0) {
  console.log('✓ Alle Produkte haben bereits Lager-Zeilen - nichts zu tun.');
} else {
  console.log(`\n✓ ${touched} Produkt(e) befüllt, ${createdRows} Lager-Zeilen angelegt.`);
  console.log('Feinjustierung pro Grösse: Dashboard → Lager → Produkt wählen.');
}
process.exit(0);
