export const dynamic = 'force-dynamic';
import { redirect } from 'next/navigation';
import Script from 'next/script';
import Header from '../../components/Header.jsx';
import Footer from '../../components/Footer.jsx';
import { getSession } from '../../lib/session.js';
import * as products from '../../src/models/products.js';
import * as inventory from '../../src/models/inventory.js';
import * as orders from '../../src/models/orders.js';
import * as settings from '../../src/models/settings.js';

const SHIPPING_FREE_ABOVE = 4900; // Cents — ab 49 € kostenlos
const SHIPPING_RATE       = 490;  // Cents — sonst 4,90 €

const COUNTRIES = [
  ['DE', 'Deutschland'],
  ['AT', 'Österreich'],
  ['CH', 'Schweiz'],
  ['LI', 'Liechtenstein'],
  ['LU', 'Luxemburg'],
  ['BE', 'Belgien'],
  ['NL', 'Niederlande'],
  ['FR', 'Frankreich'],
  ['IT', 'Italien'],
  ['ES', 'Spanien'],
  ['PL', 'Polen'],
  ['CZ', 'Tschechien'],
  ['DK', 'Dänemark'],
  ['SE', 'Schweden'],
  ['NO', 'Norwegen'],
  ['GB', 'Großbritannien'],
  ['US', 'USA'],
];

function formatPrice(cents) {
  try {
    const currency = settings.get('currency') || 'EUR';
    return new Intl.NumberFormat('de-DE', { style: 'currency', currency }).format(cents / 100);
  } catch { return `${(cents / 100).toFixed(2)} €`; }
}

async function checkoutAction(formData) {
  'use server';
  const { redirect } = await import('next/navigation');
  const { getSession } = await import('../../lib/session.js');
  const inv   = await import('../../src/models/inventory.js');
  const prods = await import('../../src/models/products.js');
  const ord   = await import('../../src/models/orders.js');

  const firstname       = String(formData.get('firstname') || '').trim();
  const lastname        = String(formData.get('lastname')  || '').trim();
  const email           = String(formData.get('email')     || '').trim();
  const phone           = String(formData.get('phone')     || '').trim();
  const street          = String(formData.get('street')    || '').trim();
  const housenr         = String(formData.get('housenr')   || '').trim();
  const zip             = String(formData.get('zip')       || '').trim();
  const city            = String(formData.get('city')      || '').trim();
  const country         = String(formData.get('country')   || 'DE').trim();
  const payment_method  = String(formData.get('payment_method') || 'vorkasse').trim();

  if (!firstname || !lastname || !email.includes('@') || !street || !zip || !city) {
    redirect('/kasse?error=validation');
  }
  if (payment_method === 'kreditkarte') {
    const cardNr = String(formData.get('card_number') || '').replace(/\s/g, '');
    const expiry = String(formData.get('card_expiry') || '');
    const cvc    = String(formData.get('card_cvc')    || '');
    if (cardNr.length < 13 || !expiry || cvc.length < 3) {
      redirect('/kasse?error=card');
    }
  }

  const session = await getSession();
  const cart = session.cart || [];
  if (cart.length === 0) redirect('/warenkorb');

  const items = [];
  let subtotal = 0;
  for (const line of cart) {
    const p = prods.getById(line.productId);
    if (!p || !p.is_active) continue;
    const variantRow = inv.byVariant(line.productId, line.size || '', '');
    const unit = variantRow?.variant_price_cents != null
      ? variantRow.variant_price_cents
      : (p.sale_price_cents ?? p.price_cents);
    const avail   = inv.stockForVariant(line.productId, line.size || '', '');
    const safeQty = Math.min(line.qty, Math.max(0, avail));
    if (safeQty === 0) continue;
    subtotal += unit * safeQty;
    items.push({ productId: p.id, slug: p.slug, name: p.name, size: line.size, qty: safeQty, unitCents: unit, lineCents: unit * safeQty });
  }
  if (items.length === 0) redirect('/warenkorb');

  const shipping_cents = subtotal >= SHIPPING_FREE_ABOVE ? 0 : SHIPPING_RATE;
  inv.deductStock(items);

  const reference = ord.create({
    customer_name:  `${firstname} ${lastname}`,
    email,
    phone,
    address: { firstname, lastname, street, housenr, zip, city, country },
    items,
    total_cents:    subtotal,
    shipping_cents,
    payment_method,
  });

  session.cart = [];
  session.lastOrder = reference;
  await session.save();
  redirect(`/bestellung/${reference}`);
}

export default async function KassePage({ searchParams }) {
  const session = await getSession();
  const cart = session.cart || [];
  if (cart.length === 0) redirect('/warenkorb');

  const items = [];
  let subtotal = 0;
  for (const line of cart) {
    const p = products.getById(line.productId);
    if (!p || !p.is_active) continue;
    const variantRow = inventory.byVariant(line.productId, line.size || '', '');
    const unit = variantRow?.variant_price_cents != null
      ? variantRow.variant_price_cents
      : (p.sale_price_cents ?? p.price_cents);
    const avail   = inventory.stockForVariant(line.productId, line.size || '', '');
    const safeQty = Math.min(line.qty, Math.max(0, avail));
    if (safeQty === 0) continue;
    subtotal += unit * safeQty;
    items.push({ name: p.name, size: line.size, qty: safeQty, unitCents: unit, lineCents: unit * safeQty, img: p.images?.[0]?.src });
  }

  const shipping  = subtotal >= SHIPPING_FREE_ABOVE ? 0 : SHIPPING_RATE;
  const total     = subtotal + shipping;
  const cartCount = items.reduce((n, it) => n + it.qty, 0);
  const sp        = await searchParams;

  return (
    <>
      <Header cartCount={cartCount} currentPath="/kasse" />
      <main id="main" className="container section">
        <h1 className="checkout-title">Kasse</h1>

        {sp?.error === 'validation' && (
          <div className="alert alert-error" style={{ maxWidth: '680px' }}>Bitte fülle alle Pflichtfelder korrekt aus.</div>
        )}
        {sp?.error === 'card' && (
          <div className="alert alert-error" style={{ maxWidth: '680px' }}>Bitte gib gültige Kreditkartendaten ein.</div>
        )}

        <div className="checkout-grid">
          {/* ── Linke Spalte: Formular ── */}
          <form action={checkoutAction} className="checkout-form" id="checkout-form">

            {/* Kontakt */}
            <div className="checkout-section">
              <h2 className="checkout-section-title">
                <span className="checkout-step">1</span> Kontakt
              </h2>
              <div className="form-row-2">
                <label className="field">
                  <span>E-Mail *</span>
                  <input type="email" name="email" required maxLength={200} autoComplete="email" placeholder="name@beispiel.de" />
                </label>
                <label className="field">
                  <span>Telefon <small className="muted">(optional)</small></span>
                  <input type="tel" name="phone" maxLength={30} autoComplete="tel" placeholder="+49 170 1234567" />
                </label>
              </div>
            </div>

            {/* Lieferadresse */}
            <div className="checkout-section">
              <h2 className="checkout-section-title">
                <span className="checkout-step">2</span> Lieferadresse
              </h2>
              <div className="form-row-2">
                <label className="field">
                  <span>Vorname *</span>
                  <input type="text" name="firstname" required maxLength={80} autoComplete="given-name" placeholder="Max" />
                </label>
                <label className="field">
                  <span>Nachname *</span>
                  <input type="text" name="lastname" required maxLength={80} autoComplete="family-name" placeholder="Mustermann" />
                </label>
              </div>
              <div className="form-row-2" style={{ gridTemplateColumns: '2fr 1fr' }}>
                <label className="field">
                  <span>Straße *</span>
                  <input type="text" name="street" required maxLength={120} autoComplete="address-line1" placeholder="Musterstraße" />
                </label>
                <label className="field">
                  <span>Nr. *</span>
                  <input type="text" name="housenr" required maxLength={20} placeholder="12a" />
                </label>
              </div>
              <div className="form-row-2" style={{ gridTemplateColumns: '1fr 2fr' }}>
                <label className="field">
                  <span>PLZ *</span>
                  <input type="text" name="zip" required maxLength={10} autoComplete="postal-code" placeholder="12345" />
                </label>
                <label className="field">
                  <span>Stadt *</span>
                  <input type="text" name="city" required maxLength={80} autoComplete="address-level2" placeholder="Berlin" />
                </label>
              </div>
              <label className="field">
                <span>Land *</span>
                <select name="country" autoComplete="country" defaultValue="DE">
                  {COUNTRIES.map(([code, name]) => (
                    <option key={code} value={code}>{name}</option>
                  ))}
                </select>
              </label>
            </div>

            {/* Zahlungsmethode */}
            <div className="checkout-section">
              <h2 className="checkout-section-title">
                <span className="checkout-step">3</span> Zahlungsmethode
              </h2>

              <div className="pay-methods">
                <label className="pay-option" htmlFor="pay-card">
                  <input type="radio" id="pay-card" name="payment_method" value="kreditkarte" defaultChecked />
                  <div className="pay-option-inner">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                    <div>
                      <strong>Kreditkarte</strong>
                      <small>Visa, Mastercard, Amex</small>
                    </div>
                    <div className="pay-check">
                      <svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="2"><polyline points="2 8 6 12 14 4"/></svg>
                    </div>
                  </div>
                </label>

                <label className="pay-option" htmlFor="pay-paypal">
                  <input type="radio" id="pay-paypal" name="payment_method" value="paypal" />
                  <div className="pay-option-inner">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round"><path d="M7 11c0 4 3 6 6 6h1c2 0 4-1.5 4-4s-2-4-4-4H9C7 9 7 7 9 7h5c1 0 2 .5 2.5 1.5"/></svg>
                    <div>
                      <strong>PayPal</strong>
                      <small>Schnell &amp; sicher</small>
                    </div>
                    <div className="pay-check">
                      <svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="2"><polyline points="2 8 6 12 14 4"/></svg>
                    </div>
                  </div>
                </label>

                <label className="pay-option" htmlFor="pay-vorkasse">
                  <input type="radio" id="pay-vorkasse" name="payment_method" value="vorkasse" />
                  <div className="pay-option-inner">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/><path d="M6 15h4"/></svg>
                    <div>
                      <strong>Banküberweisung</strong>
                      <small>Zahlung nach Bestellung</small>
                    </div>
                    <div className="pay-check">
                      <svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="2"><polyline points="2 8 6 12 14 4"/></svg>
                    </div>
                  </div>
                </label>
              </div>

              {/* Kreditkarte Felder */}
              <div id="pay-kreditkarte" className="pay-section">
                <div className="pay-fields-box">
                  <label className="field">
                    <span>Kartennummer *</span>
                    <input type="text" name="card_number" maxLength={19} inputMode="numeric" autoComplete="cc-number" placeholder="1234 5678 9012 3456" data-card-format />
                  </label>
                  <label className="field">
                    <span>Karteninhaber *</span>
                    <input type="text" name="card_name" maxLength={80} autoComplete="cc-name" placeholder="MAX MUSTERMANN" style={{ textTransform: 'uppercase' }} />
                  </label>
                  <div className="form-row-2">
                    <label className="field">
                      <span>Ablaufdatum *</span>
                      <input type="text" name="card_expiry" maxLength={5} inputMode="numeric" autoComplete="cc-exp" placeholder="MM/JJ" data-expiry-format />
                    </label>
                    <label className="field">
                      <span>Sicherheitscode *</span>
                      <input type="password" name="card_cvc" maxLength={4} inputMode="numeric" autoComplete="cc-csc" placeholder="CVC" />
                    </label>
                  </div>
                  <p className="pay-hint muted">
                    <svg width="12" height="12" viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="1.8"><rect x="2" y="6" width="12" height="9" rx="1.5"/><path d="M5 6V4a3 3 0 016 0v2"/></svg>
                    {' '}Deine Kartendaten werden verschlüsselt übertragen.
                  </p>
                </div>
              </div>

              {/* PayPal */}
              <div id="pay-paypal" className="pay-section" hidden>
                <div className="pay-fields-box">
                  <p className="muted" style={{ fontSize: '.88rem', lineHeight: 1.6 }}>
                    Nach dem Absenden wirst du zur PayPal-Zahlung weitergeleitet. Deine Bestellung wird nach Zahlungseingang bearbeitet.
                  </p>
                </div>
              </div>

              {/* Vorkasse */}
              <div id="pay-vorkasse" className="pay-section" hidden>
                <div className="pay-fields-box">
                  <p className="muted" style={{ fontSize: '.88rem', lineHeight: 1.6 }}>
                    Nach deiner Bestellung erhältst du unsere Bankverbindung per E-Mail. Deine Bestellung wird nach Zahlungseingang versendet.
                  </p>
                  <div className="bank-info">
                    <div><span>Empfänger:</span><strong>ABJ Store GmbH</strong></div>
                    <div><span>IBAN:</span><strong>DE89 3704 0044 0532 0130 00</strong></div>
                    <div><span>BIC:</span><strong>COBADEFFXXX</strong></div>
                    <div><span>Bank:</span><strong>Commerzbank</strong></div>
                  </div>
                </div>
              </div>
            </div>

            <button className="btn btn-primary btn-block checkout-submit" type="submit">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><rect x="2" y="6" width="20" height="14" rx="2"/><path d="M2 12h20"/><path d="M7 16h4"/></svg>
              Kostenpflichtig bestellen · {formatPrice(total)}
            </button>

            <ul className="checkout-trust">
              <li>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
                SSL-verschlüsselte Übertragung
              </li>
              <li>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                Käuferschutz &amp; sichere Zahlung
              </li>
              <li>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
                {shipping === 0 ? 'Kostenloser Versand' : `Versand: ${formatPrice(shipping)}`}
              </li>
              <li>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 102.13-9.36L1 10"/></svg>
                14 Tage Rückgaberecht
              </li>
            </ul>
          </form>

          {/* ── Rechte Spalte: Bestellübersicht ── */}
          <div className="order-summary">
            <h2 style={{ fontSize: '1rem', fontWeight: 700, marginBottom: '1.2rem', letterSpacing: '-.01em' }}>Bestellübersicht</h2>

            <div style={{ display: 'flex', flexDirection: 'column', gap: '.8rem', marginBottom: '1.2rem' }}>
              {items.map((it, i) => (
                <div key={i} className="checkout-item">
                  {it.img && <img src={it.img} alt={it.name} className="checkout-item-img" />}
                  <div className="checkout-item-info">
                    <span className="checkout-item-name">{it.name}{it.size ? ` (${it.size})` : ''}</span>
                    <span className="muted" style={{ fontSize: '.78rem' }}>× {it.qty}</span>
                  </div>
                  <span className="checkout-item-price">{formatPrice(it.lineCents)}</span>
                </div>
              ))}
            </div>

            <div className="summary-row">
              <span>Zwischensumme</span>
              <span>{formatPrice(subtotal)}</span>
            </div>
            <div className="summary-row">
              <span>Versand</span>
              <span>{shipping === 0 ? <span style={{ color: '#a8e6b8' }}>Kostenlos</span> : formatPrice(shipping)}</span>
            </div>
            {shipping > 0 && (
              <div className="summary-row" style={{ fontSize: '.75rem', color: 'var(--ink-dim)', borderBottom: 'none', paddingTop: 0 }}>
                <span>Noch {formatPrice(SHIPPING_FREE_ABOVE - subtotal)} bis kostenloser Versand</span>
              </div>
            )}
            <div className="summary-total">
              <strong>Gesamt</strong>
              <strong>{formatPrice(total)}</strong>
            </div>
            <p className="muted" style={{ fontSize: '.72rem', marginTop: '.8rem' }}>inkl. MwSt.</p>
          </div>
        </div>
      </main>
      <Footer />

      <Script id="checkout-js" strategy="afterInteractive" dangerouslySetInnerHTML={{ __html: `
        (function() {
          // Payment method toggle
          function updatePay() {
            var val = (document.querySelector('[name="payment_method"]:checked') || {}).value || '';
            document.querySelectorAll('.pay-section').forEach(function(s) {
              s.hidden = s.id !== 'pay-' + val;
            });
            document.querySelectorAll('.pay-option').forEach(function(l) {
              var inp = l.querySelector('input[type="radio"]');
              l.classList.toggle('active', inp && inp.checked);
            });
          }
          document.querySelectorAll('[name="payment_method"]').forEach(function(r) {
            r.addEventListener('change', updatePay);
          });
          updatePay();

          // Kartennummer formatieren
          var cardInput = document.querySelector('[data-card-format]');
          if (cardInput) {
            cardInput.addEventListener('input', function() {
              var v = cardInput.value.replace(/\\D/g,'').slice(0,16);
              cardInput.value = v.replace(/(\\d{4})(?=\\d)/g,'$1 ');
            });
          }

          // Ablaufdatum formatieren
          var expInput = document.querySelector('[data-expiry-format]');
          if (expInput) {
            expInput.addEventListener('input', function() {
              var v = expInput.value.replace(/\\D/g,'').slice(0,4);
              if (v.length >= 3) v = v.slice(0,2) + '/' + v.slice(2);
              expInput.value = v;
            });
          }
        })();
      ` }} />
    </>
  );
}
