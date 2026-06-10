import { headers } from 'next/headers';
import Script from 'next/script';
import AdminSidebar from '../../components/AdminSidebar.jsx';

export default async function AdminLayout({ children }) {
  const hdrs = await headers();
  const pathname = hdrs.get('x-pathname') || '';
  const isLogin = pathname === '/admin/login' || pathname.endsWith('/admin/login');

  if (isLogin) {
    return <>{children}</>;
  }

  return (
    <div className="admin-shell">
      <AdminSidebar />
      <div className="admin-content">
        {children}
      </div>
      <Script src="/js/admin.js" strategy="afterInteractive" />
      <Script src="/js/inventory.js" strategy="afterInteractive" />
    </div>
  );
}
