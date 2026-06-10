import express from 'express';
import morgan from 'morgan';
import compression from 'compression';
import path from 'node:path';
import { config, PUBLIC_DIR, VIEWS_DIR } from './config/env.js';
import './config/db.js'; // initialisiert DB-Schema

import {
  sessionMiddleware,
  helmetMiddleware,
  csrfMiddleware,
  apiLimiter,
} from './middleware/security.js';
import { siteGate } from './middleware/siteGate.js';
import { requireAdmin, exposeAdmin } from './middleware/adminAuth.js';

import * as settings from './models/settings.js';
import { formatPrice, placeholder } from './lib/format.js';

import shopRouter from './routes/shop.js';
import cartRouter from './routes/cart.js';
import adminAuthRouter from './routes/adminAuth.js';
import adminRouter from './routes/admin.js';
import apiRouter from './routes/api.js';
import themeRouter from './routes/theme.js';
import publicApiRouter from './routes/publicApi.js';

const app = express();
const basePath = '/testserver/client/0192481/site/abj';

// Hinter Reverse-Proxy (Hosting) korrekte Client-IPs & sichere Cookies
app.set('trust proxy', 1);
app.disable('x-powered-by');

// Views
app.set('view engine', 'ejs');
app.set('views', VIEWS_DIR);

// Logging
if (process.stdout.isTTY) {
  app.use(morgan(config.isProd ? 'combined' : 'dev'));
}

// gzip-Kompression (Geschwindigkeit)
app.use(compression());

// Sicherheits-Header
app.use(helmetMiddleware);

// Body-Parser (vor CSRF, damit _csrf aus Formularen gelesen werden kann)
app.use(express.urlencoded({ extended: false, limit: '100kb' }));
app.use(express.json({ limit: '100kb' }));

// Statische Dateien: CSS/JS/Assets sind immer erlaubt (vor dem Gate).
// Uploads werden erst nach dem Gate (siehe unten) ausgeliefert.
const staticOpts = { maxAge: config.isProd ? '7d' : 0, etag: true, lastModified: true };
app.use(basePath + '/css', express.static(path.join(PUBLIC_DIR, 'css'), staticOpts));
app.use(basePath + '/css', themeRouter); // dynamisches /css/theme.css (admin-konfigurierbare Farben)
app.use(basePath + '/js', express.static(path.join(PUBLIC_DIR, 'js'), staticOpts));
app.use(basePath + '/assets', express.static(path.join(PUBLIC_DIR, 'assets'), { maxAge: config.isProd ? '30d' : 0, etag: true }));

// Session
app.use(sessionMiddleware);

// View-Helfer + globale Locals (VOR dem CSRF-Check, damit Fehlerseiten sie nutzen können)
app.use(exposeAdmin);
app.use((req, res, next) => {
  res.locals.basePath = basePath;
  res.locals.currency = settings.get('currency');
  res.locals.price = (cents) => formatPrice(cents, res.locals.currency);
  res.locals.shopName = settings.get('shop_name');
  res.locals.announcement = settings.get('announcement');
  res.locals.saleEndsAt = settings.get('sale_ends_at') || '2026-06-30T23:59:59';
  res.locals.placeholder = placeholder;
  res.locals.cartCount = (req.session.cart || []).reduce((n, l) => n + l.qty, 0);
  res.locals.path = req.path;
  next();
});

// CSRF (setzt zusätzlich res.locals.csrfToken)
app.use(csrfMiddleware);

// --- Seitenweite Zugangs-Sperre deaktiviert ---
app.use(siteGate);

// Uploads erst nach dem Gate ausliefern (privat bis Release)
app.use(basePath + '/uploads', express.static(config.paths.UPLOADS_DIR, { index: false }));
app.use(basePath + '/img', express.static(config.paths.IMG_DIR, { index: false }));

// --- Routen (alle unter basePath) ---
// Leitet die alte URL /dashboard auf /admin weiter
app.get(basePath + '/dashboard', (req, res) => res.redirect(basePath + '/admin'));

// Admin
app.use(basePath + '/admin', adminAuthRouter); // /admin/login, /admin/logout
app.use(basePath + '/admin/api', requireAdmin, apiLimiter, apiRouter);

// WICHTIG: Der adminRouter verarbeitet nun sowohl /admin als auch /admin/dashboard
app.use(basePath + '/admin', requireAdmin, adminRouter); 

// Öffentliche JSON-API (nach dem Gate -> privat bis Release)
app.use(basePath + '/api', apiLimiter, publicApiRouter);

// Öffentlicher Shop
app.use(basePath + '/', cartRouter);
app.use(basePath + '/', shopRouter);

// 404
app.use((req, res) => {
  res.status(404).render('errors/error', {
    title: 'Nicht gefunden',
    message: 'Die angeforderte Seite existiert nicht.',
    status: 404,
  });
});

// Fehler-Handler (ohne Stacktrace-Leak in Produktion)
app.use((err, req, res, next) => {
  // eslint-disable-next-line no-console
  console.error(err);
  if (res.headersSent) return next(err);
  const status = err.status || 500;
  if (req.accepts('json') && !req.accepts('html')) {
    return res.status(status).json({ error: config.isProd ? 'Serverfehler' : err.message });
  }
  res.status(status).render('errors/error', {
    title: 'Fehler',
    message: config.isProd ? 'Es ist ein Fehler aufgetreten.' : err.message,
    status,
  });
});

import { pathToFileURL } from 'node:url';

// Nur lauschen, wenn die Datei direkt gestartet wird (nicht beim Import in Tests).
const isMain = import.meta.url === pathToFileURL(process.argv[1] || '').href;
if (isMain) {
  const server = app.listen(config.port, () => {
    // eslint-disable-next-line no-console
    console.log(`ABJ Shop läuft auf http://localhost:${config.port}  (${config.isProd ? 'production' : 'development'})`);
  });

  server.on('error', (err) => {
    if (err.code === 'EADDRINUSE') {
      console.error(`❌ Port ${config.port} ist bereits belegt. Bitte beende andere Instanzen oder ändere den PORT in der .env Datei.`);
    } else {
      console.error('❌ Serverfehler beim Starten:', err);
    }
    process.exit(1);
  });
}

export default app;
