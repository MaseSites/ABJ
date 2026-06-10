export const dynamic = 'force-dynamic';
import Header from '../../components/Header.jsx';
import Footer from '../../components/Footer.jsx';
import { getSession } from '../../lib/session.js';

export default async function WishlistPage() {
  const session = await getSession();
  const cartCount = (session.cart || []).reduce((n, l) => n + (l.qty || 0), 0);

  return (
    <>
      <Header cartCount={cartCount} currentPath="/wunschliste" />
      <main id="main" className="container section" data-wish-page>
        <h1 className="section-title">Wunschliste</h1>
        <div className="product-grid" data-wish-grid></div>
        <p className="muted" data-wish-empty>Deine Wunschliste ist leer.</p>
      </main>
      <Footer />
    </>
  );
}
