// Befüllt den Shop mit Beispiel-Einstellungen und einigen Demo-Produkten.
// Eigene, generische Platzhalter-Daten (keine fremden Marken/Bilder).
import * as products from '../src/models/products.js';
import * as settings from '../src/models/settings.js';

function inDays(n) {
  const d = new Date();
  d.setDate(d.getDate() + n);
  return d.toISOString().slice(0, 16); // passt zu <input type="datetime-local">
}

settings.setMany({
  shop_name: 'ABJ Store',
  tagline: 'Kuratierte Vintage- & Premiummode',
  hero_title: 'Frühlings-Sale - bis zu -70%',
  hero_subtitle: 'Limitierte Boxen & Einzelstücke. Nur solange der Vorrat reicht.',
  sale_ends_at: inDays(14),
  members_count: '20000',
  ratings_count: '1000',
  contact_email: 'kontakt@abj-store.example',
});

const demo = [
  {
    name: 'Vintage Hoodie - Heritage', category: 'Hoodies',
    description: '<p>Schwerer Baumwoll-Hoodie im Used-Look. Unisex-Schnitt.</p>',
    price_cents: 7900, sale_price_cents: 4900, sizes: ['S', 'M', 'L', 'XL'],
    stock: 25, is_bestseller: true, is_active: true, images: [],
  },
  {
    name: 'Premium Mystery Box - Größe M', category: 'Boxen',
    description: '<p>Überraschungs-Box mit kuratierten Vintage-Teilen. Wert deutlich über dem Preis.</p>',
    price_cents: 14900, sale_price_cents: 8900, sizes: ['M'],
    stock: 12, is_bestseller: true, is_active: true, images: [],
  },
  {
    name: 'Classic Polo - Marine', category: 'Polos',
    description: '<p>Zeitloses Polo aus Piqué-Baumwolle.</p>',
    price_cents: 3900, sale_price_cents: null, sizes: ['S', 'M', 'L'],
    stock: 40, is_bestseller: false, is_active: true, images: [],
  },
  {
    name: 'Sweatpants - Relaxed Fit', category: 'Hosen',
    description: '<p>Bequeme Jogginghose mit elastischem Bund.</p>',
    price_cents: 5900, sale_price_cents: 3900, sizes: ['S', 'M', 'L', 'XL'],
    stock: 0, is_bestseller: false, is_active: true, images: [],
  },
];

let created = 0;
for (const p of demo) {
  // Doppelte Anlage vermeiden, falls Seed mehrfach läuft
  const slug = products.slugify(p.name);
  if (!products.getBySlug(slug)) {
    products.create(p);
    created++;
  }
}

console.log(`✓ Einstellungen gesetzt, ${created} Demo-Produkte angelegt.`);
console.log('Hinweis: Bilder kannst du im Admin-Dashboard hochladen oder per URL hinzufügen.');
process.exit(0);
