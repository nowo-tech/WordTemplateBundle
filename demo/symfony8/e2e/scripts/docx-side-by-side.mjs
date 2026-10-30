// Builds "template in Word → generated document" images from LibreOffice PNG renders.
// Usage: node scripts/docx-side-by-side.mjs <png-dir> <out-dir> <id...>
// Expects <png-dir>/<id>-template.png and <png-dir>/<id>-result.png; writes <out-dir>/docx-<id>.webp.
import { join } from 'node:path';
import sharp from 'sharp';

const [pngDir, outDir, ...ids] = process.argv.slice(2);
if (!pngDir || !outDir || ids.length === 0) {
  console.error('usage: node scripts/docx-side-by-side.mjs <png-dir> <out-dir> <id...>');
  process.exit(2);
}

const PAD = 24;
const LABEL = 44;
const GAP = 56;

/** Bounding box of the non-white pixels (the text of the page). */
async function contentBox(file) {
  const { data, info } = await sharp(file).flatten({ background: '#ffffff' }).removeAlpha().raw().toBuffer({ resolveWithObject: true });
  let minX = info.width, minY = info.height, maxX = -1, maxY = -1;
  for (let y = 0; y < info.height; y++) {
    for (let x = 0; x < info.width; x++) {
      const i = (y * info.width + x) * info.channels;
      if (data[i] < 230 || data[i + 1] < 230 || data[i + 2] < 230) {
        if (x < minX) minX = x;
        if (x > maxX) maxX = x;
        if (y < minY) minY = y;
        if (y > maxY) maxY = y;
      }
    }
  }
  if (maxX < 0) {
    return { left: 0, top: 0, width: info.width, height: info.height };
  }
  return { left: minX, top: minY, width: maxX - minX + 1, height: maxY - minY + 1 };
}

async function page(file) {
  // Crop the page to its text, then keep a white border.
  const box = await contentBox(file);
  const buffer = await sharp(file)
    .flatten({ background: '#ffffff' })
    .extract(box)
    .extend({ top: PAD, bottom: PAD, left: PAD, right: PAD, background: '#ffffff' })
    .png()
    .toBuffer({ resolveWithObject: true });
  return { data: buffer.data, width: buffer.info.width, height: buffer.info.height };
}

function label(text, width, color) {
  return Buffer.from(
    `<svg width="${width}" height="${LABEL}" xmlns="http://www.w3.org/2000/svg">
       <rect width="100%" height="100%" rx="6" fill="${color}"/>
       <text x="14" y="29" font-family="DejaVu Sans, Arial, sans-serif" font-size="20" font-weight="bold" fill="#ffffff">${text}</text>
     </svg>`,
  );
}

for (const id of ids) {
  const left = await page(join(pngDir, `${id}-template.png`));
  const right = await page(join(pngDir, `${id}-result.png`));
  const width = Math.max(left.width, right.width, 420);
  const height = Math.max(left.height, right.height);
  const canvasWidth = PAD + width + GAP + width + PAD;
  const canvasHeight = PAD + LABEL + 12 + height + PAD;
  const arrow = Buffer.from(
    `<svg width="${GAP}" height="40" xmlns="http://www.w3.org/2000/svg"><path d="M8 20 H40 M30 10 L44 20 L30 30" stroke="#4a5568" stroke-width="4" fill="none"/></svg>`,
  );
  const frame = (w, h) =>
    Buffer.from(`<svg width="${w}" height="${h}" xmlns="http://www.w3.org/2000/svg"><rect x="1" y="1" width="${w - 2}" height="${h - 2}" fill="none" stroke="#cbd5e0" stroke-width="2"/></svg>`);
  const top = PAD + LABEL + 12;

  await sharp({ create: { width: canvasWidth, height: canvasHeight, channels: 3, background: '#f0f4f8' } })
    .composite([
      { input: label('Template in Word', width, '#2c5282'), top: PAD, left: PAD },
      { input: label('Generated document', width, '#2f855a'), top: PAD, left: PAD + width + GAP },
      { input: left.data, top, left: PAD },
      { input: frame(left.width, left.height), top, left: PAD },
      { input: arrow, top: top + Math.round(height / 2) - 20, left: PAD + width },
      { input: right.data, top, left: PAD + width + GAP },
      { input: frame(right.width, right.height), top, left: PAD + width + GAP },
    ])
    .webp({ quality: 90, effort: 6 })
    .toFile(join(outDir, `docx-${id}.webp`));
  console.log(`webp: ${join(outDir, `docx-${id}.webp`)}`);
}
