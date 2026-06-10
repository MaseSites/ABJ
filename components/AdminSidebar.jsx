import { headers } from 'next/headers';
import * as settings from '../src/models/settings.js';

const NAV = [
  {
    label: 'Übersicht',
    href: '/admin',
    exact: true,
    icon: (
      <svg className="nav-icon" viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round">
        <rect x="1" y="1" width="6" height="6" rx="1.5"/>
        <rect x="9" y="1" width="6" height="6" rx="1.5"/>
        <rect x="1" y="9" width="6" height="6" rx="1.5"/>
        <rect x="9" y="9" width="6" height="6" rx="1.5"/>
      </svg>
    ),
  },
  {
    label: 'Produkte',
    href: '/admin/produkte',
    icon: (
      <svg className="nav-icon" viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round">
        <path d="M2 4l1.5-2.5h9L14 4"/>
        <rect x="1" y="4" width="14" height="10" rx="1.5"/>
        <path d="M5 4v1.5a3 3 0 006 0V4"/>
      </svg>
    ),
  },
  {
    label: 'Lager',
    href: '/admin/lager',
    icon: (
      <svg className="nav-icon" viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round">
        <path d="M2 6l6-4 6 4v8H2V6z"/>
        <path d="M5.5 14V9.5h5V14"/>
      </svg>
    ),
  },
  {
    label: 'Bestellungen',
    href: '/admin/bestellungen',
    icon: (
      <svg className="nav-icon" viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round">
        <path d="M1.5 1.5h2l1.8 8h7.4l1.3-5.5H4.5"/>
        <circle cx="6.5" cy="13" r="1"/><circle cx="12" cy="13" r="1"/>
      </svg>
    ),
  },
  {
    label: 'Nachrichten',
    href: '/admin/nachrichten',
    icon: (
      <svg className="nav-icon" viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round">
        <path d="M14 10.5a1.5 1.5 0 01-1.5 1.5H4L1.5 14.5V2.5A1.5 1.5 0 013 1h10.5A1.5 1.5 0 0115 2.5v8z"/>
      </svg>
    ),
  },
  {
    label: 'Newsletter',
    href: '/admin/newsletter',
    icon: (
      <svg className="nav-icon" viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round">
        <rect x="1" y="3" width="14" height="10" rx="1.5"/>
        <path d="M1 3l7 5.5L15 3"/>
      </svg>
    ),
  },
  {
    label: 'Einstellungen',
    href: '/admin/einstellungen',
    icon: (
      <svg className="nav-icon" viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round">
        <circle cx="8" cy="8" r="2.5"/>
        <path d="M8 1v1.5M8 13.5V15M1 8h1.5M13.5 8H15M3.05 3.05l1.06 1.06M11.89 11.89l1.06 1.06M12.95 3.05l-1.06 1.06M4.11 11.89l-1.06 1.06"/>
      </svg>
    ),
  },
];

export default async function AdminSidebar() {
  const hdrs = await headers();
  const pathname = hdrs.get('x-pathname') || '/admin';
  const shopName = settings.get('shop_name') || 'ABJ';

  return (
    <aside className="admin-sidebar">
      <div className="sidebar-logo">
        <a href="/admin">
          <span className="sidebar-logo-name">{shopName}</span>
          <span className="sidebar-logo-sub">Admin</span>
        </a>
      </div>

      <nav className="sidebar-nav">
        <span className="sidebar-section-label">Navigation</span>
        {NAV.map((item) => {
          const active = item.exact
            ? pathname === item.href
            : pathname.startsWith(item.href);
          return (
            <a key={item.href} href={item.href} className={active ? 'active' : ''}>
              {item.icon}
              {item.label}
            </a>
          );
        })}
        <hr style={{border:'none',borderTop:'1px solid rgba(255,255,255,.06)',margin:'.7rem .9rem'}}/>
        <a href="/" target="_blank" rel="noopener">
          <svg className="nav-icon" viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round">
            <path d="M7 2H2.5A1.5 1.5 0 001 3.5v10A1.5 1.5 0 002.5 15h10a1.5 1.5 0 001.5-1.5V9"/>
            <path d="M10 1h5v5M15 1L7.5 8.5"/>
          </svg>
          Shop ansehen
        </a>
      </nav>

      <div className="sidebar-footer">
        <span className="sidebar-footer-user">Angemeldet als <strong>admin</strong></span>
        <form method="post" action="/admin/logout">
          <button className="sidebar-logout" type="submit">Abmelden</button>
        </form>
      </div>
    </aside>
  );
}
