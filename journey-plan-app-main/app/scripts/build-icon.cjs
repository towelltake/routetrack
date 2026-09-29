// Generate app/build/icon.ico from docs/brand/logo-source.png.
//
// electron-builder picks this up via `win.icon: build/icon.ico` so every
// Windows shell surface (taskbar, titlebar, Alt-Tab, .exe, installer, Start
// Menu / desktop shortcut) shows the Towell logo instead of the default
// Electron atom. The pipeline:
//   1. Read the 1254x1254 source PNG.
//   2. Resize to each target dimension via `sharp` — high-quality Lanczos
//      downsample; alpha preserved so the logo sits on whatever background
//      Windows composites it onto.
//   3. Bundle the size variants into a multi-resolution ICO via `to-ico`.
//
// Run with `node app/scripts/build-icon.cjs` (or `pnpm icon`). The output
// is committed so a clean clone can `pnpm dist` without re-running this
// script, but the script is kept for regenerations whenever the brand
// source changes.

const fs = require('node:fs');
const path = require('node:path');
const sharp = require('sharp');

const SOURCE = path.resolve(__dirname, '..', '..', 'docs', 'brand', 'logo-source.png');
const OUTPUT = path.resolve(__dirname, '..', 'build', 'icon.ico');

// Standard Windows icon sizes — the OS picks the closest match for each
// surface (16/24/32 for taskbar + tray, 48 for Start menu, 256 for high-DPI
// + installer / desktop shortcut). Anything past 256 is ignored by Windows
// for .ico embedding.
const SIZES = [16, 24, 32, 48, 64, 128, 256];

// Embeds raw PNG buffers as PNG-format frames inside the ICO container
// (a format Windows Vista+ supports natively). The previous v0.12.2
// pipeline used `to-ico`, which transcoded to 24bpp DIB but stamped the
// directory entry as a mismatched bpp — Windows then rendered garbage.
// Writing PNG frames sidesteps the DIB encoding surface entirely.
function buildIco(pngBuffers, sizes) {
  const count = pngBuffers.length;
  const dir = Buffer.alloc(6 + count * 16);
  dir.writeUInt16LE(0, 0);          // reserved
  dir.writeUInt16LE(1, 2);          // type = 1 (icon)
  dir.writeUInt16LE(count, 4);      // image count

  let offset = dir.length;
  for (let i = 0; i < count; i++) {
    const size = sizes[i];
    const buf = pngBuffers[i];
    const o = 6 + i * 16;
    dir.writeUInt8(size >= 256 ? 0 : size, o);     // width  (0 = 256)
    dir.writeUInt8(size >= 256 ? 0 : size, o + 1); // height (0 = 256)
    dir.writeUInt8(0, o + 2);          // colorCount (0 for true color)
    dir.writeUInt8(0, o + 3);          // reserved
    dir.writeUInt16LE(1, o + 4);       // color planes
    dir.writeUInt16LE(32, o + 6);      // bits per pixel
    dir.writeUInt32LE(buf.length, o + 8);  // bytes in image
    dir.writeUInt32LE(offset, o + 12);     // offset to image data
    offset += buf.length;
  }

  return Buffer.concat([dir, ...pngBuffers]);
}

async function main() {
  if (!fs.existsSync(SOURCE)) {
    throw new Error(`Source PNG missing at ${SOURCE}`);
  }
  fs.mkdirSync(path.dirname(OUTPUT), { recursive: true });

  const buffers = await Promise.all(
    SIZES.map((size) =>
      sharp(SOURCE)
        .resize(size, size, { fit: 'contain', background: { r: 0, g: 0, b: 0, alpha: 0 } })
        .png()
        .toBuffer(),
    ),
  );

  const ico = buildIco(buffers, SIZES);
  fs.writeFileSync(OUTPUT, ico);

  const totalKb = (ico.length / 1024).toFixed(1);
  console.log(`Wrote ${OUTPUT} (${totalKb} KB, sizes ${SIZES.join(', ')})`);
}

main().catch((err) => {
  console.error('icon build failed:', err);
  process.exitCode = 1;
});
