#!/usr/bin/env python3
"""
Render PNG fallbacks for the MangazScans theme:
- images/favicon.png (32x32)
- images/favicon-180.png (apple touch icon)
- images/logo.png (480x96, transparent bg, dark wordmark)
- images/logo-light.png (480x96, transparent bg, light wordmark)
- screenshot.png (1200x900, theme directory listing screenshot)
"""
import os
import io
from pathlib import Path
import cairosvg
from PIL import Image, ImageDraw, ImageFont

THEME = Path("mangazscans")
IMG = THEME / "images"


def svg_to_png(svg_path: Path, out_path: Path, w: int, h: int) -> None:
    cairosvg.svg2png(
        url=str(svg_path),
        write_to=str(out_path),
        output_width=w,
        output_height=h,
    )
    print(f"wrote {out_path} ({w}x{h})")


# Favicons & logo PNGs
svg_to_png(IMG / "favicon.svg", IMG / "favicon.png", 32, 32)
svg_to_png(IMG / "favicon.svg", IMG / "favicon-180.png", 180, 180)
svg_to_png(IMG / "logo.svg", IMG / "logo.png", 480, 96)
svg_to_png(IMG / "logo-light.svg", IMG / "logo-light.png", 480, 96)


# Theme directory screenshot — 1200x900, dark gradient bg + logo + tagline
W, H = 1200, 900
INK = (26, 26, 46, 255)
ACCENT = (255, 107, 53, 255)
WHITE = (255, 255, 255, 255)
SUBTLE = (200, 200, 215, 255)

img = Image.new("RGB", (W, H), (15, 15, 28))
d = ImageDraw.Draw(img)

# Vertical gradient: top dark navy -> bottom deep black
for y in range(H):
    t = y / H
    r = int(26 * (1 - t) + 8 * t)
    g = int(26 * (1 - t) + 8 * t)
    b = int(46 * (1 - t) + 16 * t)
    d.line([(0, y), (W, y)], fill=(r, g, b))

# Decorative manga-panel grid in the background (very subtle)
panel_color = (255, 255, 255, 16)
overlay = Image.new("RGBA", (W, H), (0, 0, 0, 0))
od = ImageDraw.Draw(overlay)
for x in range(80, W, 220):
    for y in range(80, H, 280):
        od.rectangle([x, y, x + 200, y + 260], outline=(255, 255, 255, 18), width=2)
img = Image.alpha_composite(img.convert("RGBA"), overlay).convert("RGB")
d = ImageDraw.Draw(img)

def load_font(size: int, bold: bool = False) -> ImageFont.FreeTypeFont:
    if bold:
        candidates = [
            "/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf",
            "/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf",
        ]
    else:
        candidates = [
            "/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf",
            "/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf",
        ]
    for p in candidates:
        if os.path.exists(p):
            return ImageFont.truetype(p, size)
    return ImageFont.load_default()


# Hand-render the wordmark with the bold font so it rasterizes cleanly.
# Compose mark + wordmark, measure total width, then center the whole group.
img_rgba = img.convert("RGBA")
d = ImageDraw.Draw(img_rgba)

mark_size = 140
gap = 32
word_font = load_font(82, bold=True)
mangaz_w = d.textlength("Mangaz", font=word_font)
scans_w = d.textlength("Scans", font=word_font)
group_w = mark_size + gap + mangaz_w + scans_w

mark_x = (W - group_w) // 2
mark_y = (H // 2) - 140

d.rounded_rectangle(
    [mark_x, mark_y, mark_x + mark_size, mark_y + mark_size],
    radius=24, fill=WHITE,
)
mark_font = load_font(102, bold=True)
m_w = d.textlength("M", font=mark_font)
d.text(
    (mark_x + (mark_size - m_w) // 2, mark_y + 6),
    "M", font=mark_font, fill=INK,
)
d.rounded_rectangle(
    [mark_x + 26, mark_y + mark_size - 22, mark_x + mark_size - 26, mark_y + mark_size - 14],
    radius=4, fill=ACCENT,
)

word_x = mark_x + mark_size + gap
word_y = mark_y + 24
d.text((word_x, word_y), "Mangaz", font=word_font, fill=WHITE)
d.text((word_x + mangaz_w, word_y), "Scans", font=word_font, fill=ACCENT)

img = img_rgba.convert("RGB")
d = ImageDraw.Draw(img)

tagline_font = load_font(28, bold=False)
foot_font = load_font(20, bold=True)

tagline = "Lightweight, security-hardened manga & comic theme for WordPress"
tw = d.textlength(tagline, font=tagline_font)
d.text(((W - tw) // 2, (H // 2) + 90), tagline, font=tagline_font, fill=SUBTLE)

foot = "v2.5.0  ·  forked from Madara 1.7.3.1"
fw = d.textlength(foot, font=foot_font)
d.text(((W - fw) // 2, (H // 2) + 140), foot, font=foot_font, fill=ACCENT[:3])

# Accent bar across bottom
d.rectangle([0, H - 8, W, H], fill=ACCENT[:3])

screenshot_path = THEME / "screenshot.png"
img.save(screenshot_path, "PNG", optimize=True)
print(f"wrote {screenshot_path} (1200x900)  size={screenshot_path.stat().st_size:,} bytes")
