export const dynamic = 'force-dynamic';
import * as settings from '../../../src/models/settings.js';

const BASE = '';

function safeColor(value, fallback) {
  return /^#[0-9a-fA-F]{3,8}$/.test(String(value || '')) ? value : fallback;
}

function safeImage(value) {
  const v = String(value || '').trim();
  if (/^\/(assets|uploads|img)\/[\w\-.\/]+\.(jpe?g|png|webp|avif|svg)$/i.test(v)) return v;
  if (/^https:\/\/[^\s"')]+$/i.test(v)) return v;
  return null;
}

export function GET() {
  const accent = safeColor(settings.get('accent'), '#B89C67');
  const accent2 = safeColor(settings.get('accent_2'), '#B89C67');
  const accent3 = safeColor(settings.get('accent_3'), '#CDB27E');
  const heroImage = safeImage(settings.get('hero_image'));

  const css = `:root{
  --accent:${accent};
  --accent-2:${accent2};
  --accent-3:${accent3};
  --grad:linear-gradient(135deg, ${accent3} 0%, ${accent} 100%);
  --grad-soft:linear-gradient(135deg, ${accent}1a, ${accent}0a);
  --glow:0 24px 60px -28px rgba(0,0,0,.9);
  ${heroImage ? `--hero-image:url("${BASE}${heroImage}");` : ''}
}`;

  return new Response(css, {
    headers: {
      'Content-Type': 'text/css',
      'Cache-Control': 'no-cache',
    },
  });
}
