export const dynamic = 'force-dynamic';
import { getSession } from '../../../../lib/session.js';
import { writeFile, mkdir } from 'node:fs/promises';
import path from 'node:path';
import { nanoid } from 'nanoid';

const UPLOADS_DIR = path.join(process.cwd(), 'public', 'uploads');
const ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/avif'];
const MAX_SIZE = 8 * 1024 * 1024; // 8MB

async function requireAdmin() {
  const session = await getSession();
  return session.adminId ? session : null;
}

export async function POST(request) {
  if (!await requireAdmin()) return Response.json({ error: 'Unauthorized' }, { status: 401 });

  try {
    await mkdir(UPLOADS_DIR, { recursive: true });
    const formData = await request.formData();
    const file = formData.get('image');

    if (!file || typeof file === 'string') {
      return Response.json({ error: 'Keine Datei hochgeladen.' }, { status: 400 });
    }

    if (!ALLOWED_TYPES.includes(file.type)) {
      return Response.json({ error: 'Nur JPEG, PNG, WebP und AVIF erlaubt.' }, { status: 400 });
    }

    const buffer = Buffer.from(await file.arrayBuffer());
    if (buffer.length > MAX_SIZE) {
      return Response.json({ error: 'Datei zu groß (max. 8 MB).' }, { status: 400 });
    }

    const ext = file.type.split('/')[1].replace('jpeg', 'jpg');
    const filename = `${nanoid(12)}.${ext}`;
    await writeFile(path.join(UPLOADS_DIR, filename), buffer);

    return Response.json({ ok: true, src: `/uploads/${filename}`, type: 'upload' });
  } catch (err) {
    console.error('Upload error:', err);
    return Response.json({ error: 'Upload fehlgeschlagen.' }, { status: 500 });
  }
}
