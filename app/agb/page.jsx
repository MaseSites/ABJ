import Header from '../../components/Header.jsx';
import Footer from '../../components/Footer.jsx';
import { getSession } from '../../lib/session.js';
import * as settings from '../../src/models/settings.js';
import { legalPages } from '../../src/lib/legal.js';

export default async function AgbPage() {
  const session = await getSession();
  const cartCount = (session.cart || []).reduce((n, l) => n + (l.qty || 0), 0);
  const pages = legalPages(settings.all());
  const page = pages.agb;
  return (
    <>
      <Header cartCount={cartCount} />
      <main id="main" className="container section narrow">
        <h1>{page?.title || 'AGB'}</h1>
        {page?.content && <div dangerouslySetInnerHTML={{ __html: page.content }} />}
      </main>
      <Footer />
    </>
  );
}
