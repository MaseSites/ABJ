import * as settings from '../src/models/settings.js';

export default function AdminHeader({ currentPath = '' }) {
  const shopName = settings.get('shop_name') || 'ABJ';
  const links = [
    { href: '/admin', label: 'Übersicht' },
    { href: '/admin/produkte', label: 'Produkte' },
    { href: '/admin/lager', label: 'Lager' },
    { href: '/admin/bestellungen', label: 'Bestellungen' },
    { href: '/admin/nachrichten', label: 'Nachrichten' },
    { href: '/admin/newsletter', label: 'Newsletter' },
    { href: '/admin/einstellungen', label: 'Einstellungen' },
  ];

  return (
    <header className="admin-topbar">
      <div className="admin-topbar-inner">
        <a className="admin-brand" href="/admin">{shopName} · Admin</a>
        <nav className="admin-nav">
          {links.map((l) => (
            <a
              key={l.href}
              href={l.href}
              className={
                l.href === '/admin'
                  ? currentPath === '/admin' ? 'active' : ''
                  : currentPath.startsWith(l.href) ? 'active' : ''
              }
            >
              {l.label}
            </a>
          ))}
        </nav>
        <div className="admin-actions">
          <a href="/" target="_blank" rel="noopener">Shop ansehen</a>
          <form method="post" action="/admin/logout">
            <button className="btn btn-ghost btn-sm" type="submit">Abmelden</button>
          </form>
        </div>
      </div>
    </header>
  );
}
