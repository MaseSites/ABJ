# ABJ Store

Sicherer E-Commerce-Shop mit privatem Admin-Dashboard. Eigenständiger Code & eigenes Design
(konzeptionell an einen Vintage-/Marken-Box-Shop angelehnt, aber vollständig eigene Umsetzung).

## Features

- **Shop (Dark Theme)**: Hero mit Sale-Countdown & Bewertungs-Sterne, Trust-Badges,
  Kollektionen, Bestseller, Bewertungen, FAQ, Newsletter; Produktraster mit
  **Kategorie-Filter & Sortierung** (Neueste/Preis/Name); Produktdetail mit Bildergalerie,
  Größenauswahl, Lager-Dringlichkeit & verwandten Artikeln.
- **AJAX-Warenkorb**: In-den-Warenkorb ohne Seitenreload, Slide-in-Drawer, Toast-Benachrichtigungen,
  Live-Aktualisierung der Warenkorb-Anzahl; Kasse mit Bestellbestätigung.
- **Live-Suche** (Header), **Wunschliste** (lokal im Browser), **Newsletter-Anmeldung**.
- **Responsive**: Desktop-Navigation im Header, auf Mobile kompaktes Hamburger-Menü;
  Scroll-Fortschrittsbalken, Back-to-Top, Scroll-Reveal-Animationen.
- **Privates Admin-Dashboard** (nirgends öffentlich verlinkt):
  - Produkte anlegen/bearbeiten/löschen mit Bildern (**Upload und URL**, Vorschau & Reihenfolge),
    Preis, Sale-Preis, Größen, Lagerbestand, Bestseller-/Aktiv-Status; Produkt-Filter.
  - **Analytics** (Umsatz, Bestellungen, 7-Tage-Diagramm), **Bestellverwaltung** mit Status,
    **Newsletter-Abonnenten**, **Theme-Farbwähler** (live), Ankündigungs-Leiste,
    Hero-Texte, Countdown, Social-Proof, Kontakt.
- **Zwei getrennte Passwörter**:
  1. **Gate-Passwort** – seitenweite "Coming Soon"-Sperre (niemand sieht den Shop ohne Passwort).
  2. **Admin-Login** – separater Zugang zum Dashboard (nur per direkter URL `/admin`).
- **Sicherheit**: Helmet + strenge CSP (keine Inline-Skripte/-Styles), CSRF-Schutz, Rate-Limiting,
  bcrypt-Passwörter, sichere Session-Cookies (httpOnly/sameSite/secure), Eingabevalidierung (zod),
  geprüfter Bild-Upload (Typ-/Magic-Byte-/Größenprüfung), parametrisierte SQL-Queries.
- **Performance**: gzip-Kompression, Cache-Header für statische Assets, Lazy-Loading,
  schlanker Render-Pfad (Ziel < 2 s).
- **Barrierefreiheit**: Skip-Link, Fokus-Zustände, aria-Labels, `prefers-reduced-motion`.
- **Zahlungen**: aktuell manuelle Abwicklung ohne externen Zahlungsanbieter.

## Technik

Node.js · Express · EJS · SQLite (better-sqlite3) · keine Build-Pipeline nötig.

## Schnellstart

```bash
# 1) Abhängigkeiten installieren
npm install

# 2) Konfiguration anlegen
#    .env.example nach .env kopieren und Secrets setzen
#    (unter Windows PowerShell: Copy-Item .env.example .env)
cp .env.example .env

# 3) Admin-Account + Gate-Passwort einrichten
npm run create-admin
#    oder direkt: npm run create-admin -- meinadmin meinSicheresPasswort

# 4) (optional) Demo-Daten laden
npm run seed

# 5) Starten
npm run dev        # Entwicklung (auto-reload)
# oder
npm start          # Produktion
```

Danach im Browser öffnen: **http://localhost:3000**

- Zuerst erscheint die **Zugangs-Sperre** → Gate-Passwort eingeben.
- Admin-Dashboard: **http://localhost:3000/admin** → mit Admin-Account anmelden.

## Wichtige Secrets in `.env`

| Variable | Bedeutung |
|----------|-----------|
| `SESSION_SECRET` | Geheimnis für Sessions (lange Zufallskette) |
| `CSRF_SECRET` | Geheimnis für CSRF (lange Zufallskette) |
| `GATE_PASSWORD` | Start-Zugangspasswort (später im Dashboard änderbar) |
| `COOKIE_SECURE` | `true` in Produktion (nur über HTTPS) |
| `NODE_ENV` | `production` für den Live-Betrieb |

Zufalls-Secret erzeugen:

```bash
node -e "console.log(require('crypto').randomBytes(48).toString('hex'))"
```

## Tests

```bash
npm test
```

## Deployment (portabel)

1. Auf dem Server Node.js (≥18) installieren, Projekt kopieren, `npm install --omit=dev`.
2. `.env` mit `NODE_ENV=production`, starken Secrets und `COOKIE_SECURE=true` anlegen.
3. Hinter einen HTTPS-Reverse-Proxy (z.B. Nginx/Caddy) stellen. `trust proxy` ist aktiviert.
4. `npm run create-admin` ausführen, dann `npm start` (idealerweise via systemd/PM2).

Die Datenbank liegt portabel in `data/app.db`, hochgeladene Bilder in `src/public/uploads/`.

## Hinweis Urheberrecht

Sämtlicher Code, das Design und die Texte wurden eigenständig erstellt. Es wurden keine
HTML-/CSS-Dateien, Bilder oder Logos von Drittseiten übernommen. Demo-Produkte sind generische
Platzhalter ohne fremde Markennamen.
