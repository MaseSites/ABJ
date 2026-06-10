import Link from 'next/link';

const BASE = '';

function formatPrice(cents, currency = 'EUR') {
  try {
    return new Intl.NumberFormat('de-DE', { style: 'currency', currency }).format(cents / 100);
  } catch {
    return `${(cents / 100).toFixed(2)} €`;
  }
}

function placeholder(name) {
  const n = (name || '?').trim().charAt(0).toUpperCase();
  const svg = `<svg xmlns='http://www.w3.org/2000/svg' width='600' height='750'><rect width='600' height='750' fill='%230c0d10'/><text x='300' y='430' font-size='300' font-weight='800' fill='%23c4a35a' text-anchor='middle' font-family='Arial'>${n}</text></svg>`;
  return `data:image/svg+xml,${encodeURIComponent(svg)}`;
}

function discountPercent(priceCents, salePriceCents) {
  if (!priceCents || !salePriceCents) return 0;
  return Math.round((1 - salePriceCents / priceCents) * 100);
}

export default function ProductCard({ p }) {
  const imgSrc = p.images && p.images[0] ? p.images[0].src : placeholder(p.name);
  const hasSizes = p.sizes && p.sizes.length > 0;

  return (
    <article className="product-card" data-product-card>
      <div className="product-card-media">
        <Link href={`/produkt/${p.slug}`} className="media-link" aria-label={p.name}>
          <img src={imgSrc} alt={p.name} loading="lazy" />
          <span className="card-shine" aria-hidden="true"></span>
        </Link>
        {p.sale_price_cents ? (
          <span className="badge-sale">
            -{discountPercent(p.price_cents, p.sale_price_cents)}%
          </span>
        ) : null}

        <button
          className="wish-btn"
          data-wish={p.id}
          aria-label="Zur Wunschliste"
          title="Merken"
        >
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
            <path d="M12 21s-7-4.5-9.5-9A5 5 0 0 1 12 6a5 5 0 0 1 9.5 6c-2.5 4.5-9.5 9-9.5 9z" />
          </svg>
        </button>

        {p.stock > 0 ? (
          <button
            className="quick-add"
            data-quick-add
            data-id={p.id}
            data-slug={p.slug}
            data-has-sizes={hasSizes ? '1' : '0'}
          >
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <path d="M12 5v14M5 12h14" />
            </svg>
            <span>In den Warenkorb</span>
          </button>
        ) : null}
      </div>

      <div className="product-card-body">
        <span className="product-cat">{p.category}</span>
        <h3 className="product-name">
          <Link href={`/produkt/${p.slug}`}>{p.name}</Link>
        </h3>
        <div className="product-price">
          {p.sale_price_cents ? (
            <>
              <span className="price-sale">{formatPrice(p.sale_price_cents)}</span>
              <span className="price-old">{formatPrice(p.price_cents)}</span>
            </>
          ) : (
            <span className="price-now">{formatPrice(p.price_cents)}</span>
          )}
          {p.stock <= 0 && (
            <span className="badge-soldout inline">Ausverkauft</span>
          )}
        </div>
      </div>
    </article>
  );
}
