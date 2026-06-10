import './globals.css';
import Script from 'next/script';
import * as settings from '../src/models/settings.js';

const BASE = '';

export async function generateMetadata() {
  const shopName = settings.get('shop_name') || 'ABJ Store';
  return {
    title: shopName,
    description: settings.get('tagline') || '',
    metadataBase: new URL('https://example.com'),
  };
}

export default async function RootLayout({ children }) {
  const shopName = settings.get('shop_name') || 'ABJ Store';

  return (
    <html lang="de" data-base-path={BASE}>
      <head>
        <meta charSet="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <meta name="robots" content="noindex, nofollow" />
        <meta name="theme-color" content="#08090e" />
        <link rel="icon" type="image/svg+xml" href={`${BASE}/assets/favicon.svg`} />
        <link rel="stylesheet" href={`${BASE}/css/styles.css`} />
        <link rel="stylesheet" href={`${BASE}/css/theme.css`} />
        <link rel="stylesheet" href={`${BASE}/css/admin.css`} />
        <title>{shopName}</title>
      </head>
      <body suppressHydrationWarning>
        {children}
        <Script src={`${BASE}/js/shop.js`} strategy="afterInteractive" />
      </body>
    </html>
  );
}
