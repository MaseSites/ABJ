export const dynamic = 'force-dynamic';
import * as settings from '../../../src/models/settings.js';

async function saveSettingsAction(formData) {
  'use server';
  const { redirect } = await import('next/navigation');
  const s = await import('../../../src/models/settings.js');

  const fields = [
    'shop_name', 'tagline', 'hero_title', 'hero_subtitle', 'hero_image',
    'sale_ends_at', 'contact_email', 'announcement', 'members_count', 'ratings_count',
    'accent', 'accent_2', 'accent_3',
  ];
  const data = {};
  for (const f of fields) {
    const v = formData.get(f);
    if (v !== null) data[f] = String(v).trim();
  }
  s.setMany(data);
  redirect('/admin/einstellungen?saved=1');
}

export default async function AdminSettings({ searchParams }) {
  const s = settings.all();
  const params = await searchParams;

  return (
    <main className="admin-main narrow">
      <p className="admin-kicker">Konfiguration</p>
      <h1>Einstellungen</h1>

      {params?.saved === '1' && <div className="alert alert-success">Einstellungen gespeichert.</div>}

      <section className="admin-section">
        <h2>Shop</h2>
        <form action={saveSettingsAction} className="admin-form">
          <div className="form-grid">
            <label className="field span-2">
              <span>Shop-Name</span>
              <input type="text" name="shop_name" defaultValue={s.shop_name} maxLength={120} required />
            </label>
            <label className="field span-2">
              <span>Slogan</span>
              <input type="text" name="tagline" defaultValue={s.tagline} maxLength={200} />
            </label>
            <label className="field span-2">
              <span>Hero-Titel</span>
              <input type="text" name="hero_title" defaultValue={s.hero_title} maxLength={200} />
            </label>
            <label className="field span-2">
              <span>Hero-Untertitel</span>
              <input type="text" name="hero_subtitle" defaultValue={s.hero_subtitle} maxLength={400} />
            </label>
            <label className="field span-2">
              <span>Sale-Countdown bis <small className="muted">(z.B. 2026-06-30T23:59)</small></span>
              <input type="datetime-local" name="sale_ends_at" defaultValue={s.sale_ends_at} />
            </label>
            <label className="field">
              <span>Mitglieder-Zahl</span>
              <input type="number" name="members_count" defaultValue={s.members_count} min="0" />
            </label>
            <label className="field">
              <span>Bewertungs-Zahl</span>
              <input type="number" name="ratings_count" defaultValue={s.ratings_count} min="0" />
            </label>
            <label className="field span-2">
              <span>Kontakt-E-Mail</span>
              <input type="email" name="contact_email" defaultValue={s.contact_email} maxLength={200} required />
            </label>
            <label className="field span-2">
              <span>Ankündigungs-Leiste <small className="muted">(oben auf jeder Seite, leer = aus)</small></span>
              <input type="text" name="announcement" defaultValue={s.announcement} maxLength={200} />
            </label>
            <label className="field span-2">
              <span>Hero-Bild <small className="muted">(Pfad /assets/hero.jpg oder HTTPS-URL)</small></span>
              <input type="text" name="hero_image" defaultValue={s.hero_image} maxLength={400} />
            </label>

            <div className="field span-2">
              <span>Theme-Farben (Akzent / Verlauf)</span>
              <div
                className="color-row"
                data-color-row=""
                data-a={s.accent}
                data-b={s.accent_2}
                data-c={s.accent_3}
              >
                <label className="color-pick">
                  <input type="color" name="accent" defaultValue={s.accent} data-color="a" />
                  <span>Primär</span>
                </label>
                <label className="color-pick">
                  <input type="color" name="accent_2" defaultValue={s.accent_2} data-color="b" />
                  <span>Sekundär</span>
                </label>
                <label className="color-pick">
                  <input type="color" name="accent_3" defaultValue={s.accent_3} data-color="c" />
                  <span>Akzent 3</span>
                </label>
                <span className="color-preview" data-color-preview=""></span>
              </div>
            </div>
          </div>
          <button className="btn btn-primary" type="submit">Speichern</button>
        </form>
      </section>
    </main>
  );
}
