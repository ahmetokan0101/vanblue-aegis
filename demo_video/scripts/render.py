"""VanBlue Aegis - 29.6 s NASA Space Apps demo renderer.

Renders every frame with Pillow/numpy, synthesizes the score, mixes the
Turkish voiceover and pipes everything to ffmpeg. Optional: drop a screen
recording of the app at assets/app_demo.mp4 (>= 5.5 s) and it replaces the
hologram mock-up scene (16.0 s - 21.5 s).

Usage: python -I render.py <demo_video_dir>
"""
import math, os, subprocess, sys, wave
import numpy as np
from PIL import Image, ImageDraw, ImageFilter, ImageFont

ROOT = sys.argv[1]
A = os.path.join(ROOT, 'assets')
OUT = os.path.join(ROOT, 'VanBlue_Aegis_Demo.mp4')
W, H, FPS, DUR = 1920, 1080, 30, 29.6
NF = round(DUR * FPS)
SR = 48000
FONTS = 'C:/Windows/Fonts/'
CYAN = (90, 220, 255)
RED = (255, 70, 60)
AMBER = (255, 176, 60)

# Voice placement (global start time of each tightened clip)
VO = [('s1.wav', 0.25), ('s2.wav', 5.38), ('s3.wav', 10.84), ('s4.wav', 21.61)]

SUBS = [
    (0.25, 3.30, 'On the way to Mars, every message from Earth…'),
    (3.30, 5.30, '…arrives 22 minutes late.'),
    (5.38, 6.30, 'Radiation.'),
    (6.30, 7.30, 'Bone loss.'),
    (7.30, 8.50, 'Vision damage.'),
    (8.50, 10.75, 'The crew has no time to wait.'),
    (10.84, 15.05, 'VanBlue Aegis: the onboard AI flight surgeon.'),
    (15.05, 18.90, 'It monitors every astronaut in real time and predicts risk,'),
    (18.90, 21.50, "and takes action when Earth can't answer."),
    (21.61, 25.50, 'Powered by NASA standards and real space data.'),
    (25.50, 27.95, 'Aegis: even when Earth is far away,'),
    (27.95, 29.45, 'you are never alone.'),
]


# ---------------------------------------------------------------- helpers
def font(name, size, variation=None):
    f = ImageFont.truetype(FONTS + name, size)
    if variation:
        try:
            f.set_variation_by_name(variation)
        except Exception:
            pass
    return f


def clamp(x, a=0.0, b=1.0):
    return max(a, min(b, x))


def ease(x):
    x = clamp(x)
    return x * x * (3 - 2 * x)


def ease_out(x):
    x = clamp(x)
    return 1 - (1 - x) ** 3


def window(t, t0, t1, fin=0.35, fout=0.35):
    """Opacity envelope for an element visible between t0 and t1."""
    if t < t0 or t > t1:
        return 0.0
    return min(ease((t - t0) / fin) if fin else 1, ease((t1 - t) / fout) if fout else 1)


def text_sprite(text, fnt, fill=(255, 255, 255), tracking=0, glow=None, glow_r=0, stroke=0, stroke_fill=(0, 0, 0)):
    widths = [fnt.getlength(c) + tracking for c in text]
    tw = int(sum(widths) - tracking) + 1
    asc, desc = fnt.getmetrics()
    pad = glow_r * 3 + stroke + 4
    img = Image.new('RGBA', (tw + 2 * pad, asc + desc + 2 * pad), (0, 0, 0, 0))
    d = ImageDraw.Draw(img)
    x = pad
    for c, w in zip(text, widths):
        d.text((x, pad), c, font=fnt, fill=fill + (255,), stroke_width=stroke, stroke_fill=stroke_fill)
        x += w
    if glow:
        g = Image.new('RGBA', img.size, glow + (0,))
        g.putalpha(img.getchannel('A').filter(ImageFilter.GaussianBlur(glow_r)).point(lambda v: min(255, int(v * 1.6))))
        img = Image.alpha_composite(g, img)
    return np.asarray(img).astype(np.float32)


def comp(dst, spr, x, y, a=1.0, center=False):
    if a <= 0.003:
        return
    h, w = spr.shape[:2]
    if center:
        x, y = x - w // 2, y - h // 2
    x, y = int(round(x)), int(round(y))
    x0, y0, x1, y1 = max(x, 0), max(y, 0), min(x + w, W), min(y + h, H)
    if x0 >= x1 or y0 >= y1:
        return
    s = spr[y0 - y:y1 - y, x0 - x:x1 - x]
    al = s[..., 3:4] * (a / 255.0)
    region = dst[y0:y1, x0:x1]
    region *= 1 - al
    region += s[..., :3] * al


def rounded_panel(w, h, r=18, fill=(8, 20, 34, 200), border=CYAN + (200,), bw=2):
    img = Image.new('RGBA', (w, h), (0, 0, 0, 0))
    ImageDraw.Draw(img).rounded_rectangle([0, 0, w - 1, h - 1], r, fill=fill, outline=border, width=bw)
    return img


def load_cover(path, maxw=2880):
    im = Image.open(path).convert('RGB')
    if im.width > maxw:
        im = im.resize((maxw, round(im.height * maxw / im.width)), Image.LANCZOS)
    return im


def kenburns(src, z, cx, cy):
    sw, sh = src.size
    rw = min(sw / z, sh * W / H / z)
    rh = rw * H / W
    x0 = clamp(cx * sw - rw / 2, 0, sw - rw)
    y0 = clamp(cy * sh - rh / 2, 0, sh - rh)
    a = rw / W
    im = src.transform((W, H), Image.AFFINE, (a, 0, x0, 0, a, y0), resample=Image.BICUBIC)
    return np.asarray(im).astype(np.float32)


# ---------------------------------------------------------------- assets
print('loading assets…', flush=True)
img1 = load_cover(os.path.join(A, 'img1.png'))
img2 = load_cover(os.path.join(A, 'img2.png'))
img3 = load_cover(os.path.join(A, 'img3.png'))
bg4 = img1.resize((1920, round(1920 * img1.height / img1.width)), Image.LANCZOS).filter(ImageFilter.GaussianBlur(14))
bg4 = Image.eval(bg4, lambda v: int(v * 0.6))

f_title = font('bahnschrift.ttf', 168, 'Bold')
f_title2 = font('bahnschrift.ttf', 132, 'Bold')
f_h = font('bahnschrift.ttf', 40, 'SemiBold')
f_hs = font('bahnschrift.ttf', 30, 'SemiBold')
f_small = font('bahnschrift.ttf', 24, 'Regular')
f_num = font('bahnschrift.ttf', 150, 'Bold')
f_val = font('bahnschrift.ttf', 54, 'Bold')
f_card = font('bahnschrift.ttf', 36, 'Bold')
f_card2 = font('bahnschrift.ttf', 23, 'Regular')
f_sub = font('segoeuib.ttf', 46)
f_wm = font('segoeui.ttf', 22)

# Scene 1 sprites
sp_team = text_sprite('TEAM VANBLUE  PRESENTS', f_hs, CYAN, tracking=8)
sp_aegis = text_sprite('AEGIS', f_title, (255, 255, 255), tracking=30, glow=CYAN, glow_r=18)
sp_aegis_sub = text_sprite('AUTONOMOUS CREW HEALTH MONITOR FOR DEEP SPACE', f_hs, (220, 235, 245), tracking=6)
sp_delay_lbl = text_sprite('EARTH TO SHIP  ·  SIGNAL DELAY', f_hs, (230, 230, 230), tracking=6)
sp_min = text_sprite('MIN', f_h, AMBER, tracking=6)
sp_hud1 = text_sprite('ARES-I  ·  MARS TRANSIT  ·  DAY 142', f_small, CYAN, tracking=4)
digit_cache = {}

# Scene 2 sprites
warn_labels = [
    (5.38, 'RADIATION', 'GCR + SOLAR PARTICLE EVENTS'),
    (6.30, 'BONE LOSS', '~1.5 % BONE MASS / MONTH'),
    (7.30, 'VISION  ·  SANS', 'NEURO-OCULAR SYNDROME'),
]


def warn_sprite(title, sub):
    img = rounded_panel(560, 108, 14, fill=(40, 6, 6, 190), border=RED + (230,), bw=3)
    d = ImageDraw.Draw(img)
    d.polygon([(28, 78), (58, 26), (88, 78)], outline=RED + (255,), width=5)
    d.line([(58, 44), (58, 62)], fill=RED + (255,), width=5)
    d.ellipse([55, 67, 61, 73], fill=RED + (255,))
    d.text((112, 16), title, font=f_h, fill=(255, 255, 255, 255))
    d.text((114, 66), sub, font=f_small, fill=(255, 190, 180, 255))
    return np.asarray(img).astype(np.float32)


sp_warn = [(t, warn_sprite(a, b)) for t, a, b in warn_labels]
sp_crit = text_sprite('CREW STATUS: CRITICAL WINDOW', f_hs, RED, tracking=6)

# Scene 3a sprites
sp_vb = text_sprite('VANBLUE AEGIS', f_title2, (255, 255, 255), tracking=14, glow=CYAN, glow_r=22)
sp_vb_sub = text_sprite('THE ONBOARD AI FLIGHT SURGEON', f_h, CYAN, tracking=10)

# Scene 3b: hologram + live panel
astro = Image.open(os.path.join(ROOT, '..', 'public', 'images', 'astronaut.png')).convert('RGB')
astro = astro.resize((round(astro.width * 820 / astro.height), 820), Image.LANCZOS)
lum = np.asarray(astro.convert('L')).astype(np.float32)
holo = np.zeros(lum.shape + (4,), np.float32)
k = (lum / 255.0) ** 0.85
holo[..., 0] = 60 + 195 * k ** 1.6
holo[..., 1] = 160 + 95 * k
holo[..., 2] = 255
alpha = np.clip((lum - 12) * 2.2, 0, 255)
alpha[::4] *= 0.55
holo[..., 3] = alpha
holo_glow = np.asarray(Image.fromarray(holo.astype(np.uint8), 'RGBA').filter(ImageFilter.GaussianBlur(16))).astype(np.float32)
holo_glow[..., 3] *= 0.9

yy, xx = np.mgrid[0:H, 0:W].astype(np.float32)
bg3b = np.zeros((H, W, 3), np.float32)
grad = 1 - np.sqrt(((xx - 560) / 1400) ** 2 + ((yy - 520) / 900) ** 2)
bg3b[..., 0] = 4 + 14 * grad
bg3b[..., 1] = 10 + 32 * grad
bg3b[..., 2] = 20 + 52 * grad
grid = ((xx % 60) < 1.2) | ((yy % 60) < 1.2)
bg3b[grid] += np.array([10, 30, 45], np.float32)
bg3b = np.clip(bg3b, 0, 255)
ring = Image.new('RGBA', (620, 160), (0, 0, 0, 0))
ImageDraw.Draw(ring).ellipse([10, 10, 610, 150], outline=CYAN + (220,), width=4)
ImageDraw.Draw(ring).ellipse([90, 40, 530, 120], outline=CYAN + (140,), width=2)
ring = np.asarray(ring.filter(ImageFilter.GaussianBlur(1.5))).astype(np.float32)

panel_bg = np.asarray(rounded_panel(800, 640, 20)).astype(np.float32)
sp_ph = text_sprite('CREW HEALTH  ·  ASTRONAUT 2', f_hs, (235, 245, 255), tracking=4)
sp_live = text_sprite('LIVE', f_hs, RED, tracking=4)
vital_lbl = [text_sprite(s, f_small, (150, 200, 225), tracking=3) for s in ('HEART RATE  bpm', 'SpO2  %', 'RESP  /min', 'DOSE TODAY  mSv')]
chips = [
    (16.00, '•  LIVE MONITORING'),
    (17.35, '•  RISK PREDICTION'),
    (18.94, '•  AUTONOMOUS ACTION'),
]
sp_chips = [(t, text_sprite(s, f_hs, CYAN, tracking=4)) for t, s in chips]
alert = rounded_panel(800, 92, 14, fill=(70, 8, 8, 235), border=RED + (255,), bw=3)
ImageDraw.Draw(alert).text((26, 12), 'SOLAR PARTICLE EVENT DETECTED', font=f_hs, fill=(255, 255, 255, 255))
ImageDraw.Draw(alert).text((26, 52), 'AEGIS: crew to storm shelter · dose plan updated · logged', font=f_small, fill=(255, 200, 190, 255))
sp_alert = np.asarray(alert).astype(np.float32)

# Scene 4 sprites
sp_built = text_sprite('BUILT ON NASA DATA', f_h, CYAN, tracking=12)
cards_def = [
    ('NASA-STD-3001', 'Radiation & health limits'),
    ('MSL RAD', 'Deep-space dosimetry'),
    ('NASA DONKI', 'Live flares & CMEs'),
    ('NASA OSDR', 'Spaceflight biology'),
    ('NASA HRP', 'Human risk evidence'),
]


def card_sprite(t1, t2):
    img = rounded_panel(320, 150, 16, fill=(6, 18, 32, 215), border=CYAN + (210,), bw=2)
    d = ImageDraw.Draw(img)
    tw = d.textlength(t1, font=f_card)
    d.text(((320 - tw) / 2, 30), t1, font=f_card, fill=(255, 255, 255, 255))
    tw = d.textlength(t2, font=f_card2)
    d.text(((320 - tw) / 2, 88), t2, font=f_card2, fill=(160, 215, 240, 255))
    return np.asarray(img).astype(np.float32)


sp_cards = [card_sprite(a, b) for a, b in cards_def]
sp_end = text_sprite('VANBLUE AEGIS', f_title, (255, 255, 255), tracking=16, glow=CYAN, glow_r=26)
sp_end_sub = text_sprite('AUTONOMOUS CREW HEALTH  ·  FOR DEEP SPACE', f_h, CYAN, tracking=10)
sp_end_team = text_sprite('Team VanBlue  ·  NASA Space Apps Challenge 2026', f_hs, (200, 210, 220), tracking=3)

shade = Image.new('L', (1500, 520), 0)
ImageDraw.Draw(shade).ellipse([150, 60, 1350, 460], fill=170)
shade = shade.filter(ImageFilter.GaussianBlur(70))
sp_shade = np.zeros((520, 1500, 4), np.float32)
sp_shade[..., 3] = np.asarray(shade)
top_grad = np.zeros((360, W, 4), np.float32)
top_grad[..., 3] = (np.linspace(200, 0, 360) ** 1.0)[:, None]

# Global layers
vign = 1 - 0.55 * np.clip(np.sqrt(((xx - W / 2) / (W * 0.62)) ** 2 + ((yy - H / 2) / (H * 0.62)) ** 2) - 0.35, 0, 1) ** 1.4
vign = vign[..., None].astype(np.float32)
rng = np.random.default_rng(7)
grain_base = rng.normal(0, 1, (H // 2 + 8, W // 2 + 8)).astype(np.float32)
sp_wm = text_sprite('AI-generated space imagery & AI voice', f_wm, (255, 255, 255), stroke=2, stroke_fill=(0, 0, 0))
sub_cache = {}


def sub_sprite(text):
    if text not in sub_cache:
        sub_cache[text] = text_sprite(text, f_sub, (255, 255, 255), stroke=4, stroke_fill=(0, 0, 0))
    return sub_cache[text]


# Optional real app footage
app_frames = None
app_path = os.path.join(A, 'app_demo.mp4')
if os.path.exists(app_path):
    print('using app_demo.mp4 for 16.0–21.5 s', flush=True)
    raw = subprocess.run(['ffmpeg', '-v', 'error', '-i', app_path, '-t', '5.9', '-vf',
                          f'fps={FPS},scale={W}:{H}:force_original_aspect_ratio=decrease,pad={W}:{H}:(ow-iw)/2:(oh-ih)/2:color=0x06101c',
                          '-f', 'rawvideo', '-pix_fmt', 'rgb24', '-'], capture_output=True, check=True).stdout
    app_frames = np.frombuffer(raw, np.uint8).reshape(-1, H, W, 3)


# ---------------------------------------------------------------- scenes
def scene1(t):
    p = clamp(t / 5.8)
    f = kenburns(img1, 1.0 + 0.16 * ease(p), 0.47 + 0.02 * p, 0.5)
    comp(f, sp_hud1, 70, 150, window(t, 0.2, 5.6, 0.6, 0.3))
    a = window(t, 0.35, 2.95, 0.5, 0.35)
    if a:
        rise = 18 * (1 - ease_out((t - 0.35) / 1.2))
        comp(f, sp_shade, W / 2, 530, a, center=True)
        comp(f, sp_team, W / 2, 380 + rise, a, center=True)
        comp(f, sp_aegis, W / 2, 520 + rise, a, center=True)
        comp(f, sp_aegis_sub, W / 2, 660 + rise, a, center=True)
    a = window(t, 3.0, 6.0, 0.3, 0.3)
    if a:
        q = ease_out((t - 3.25) / 1.55)
        secs = int(round(q * 22 * 60))
        txt = f'{secs // 60:02d}:{secs % 60:02d}'
        col = tuple(int(255 + (c - 255) * q) for c in AMBER)
        key = (txt, col)
        if key not in digit_cache:
            digit_cache.clear()
            digit_cache[key] = text_sprite(txt, f_num, col, tracking=6, glow=col, glow_r=14)
        comp(f, sp_shade, W / 2, 490, a, center=True)
        comp(f, sp_delay_lbl, W / 2, 400, a, center=True)
        comp(f, digit_cache[key], W / 2 - 40, 535, a, center=True)
        comp(f, sp_min, W / 2 + 250, 565, a * ease((t - 4.6) / 0.3), center=True)
    return f


def scene2(t):
    lt = t - 5.3
    p = clamp(lt / 5.9)
    sh = 0.0025 * math.sin(lt * 37) + 0.002 * math.sin(lt * 23 + 1)
    f = kenburns(img2, 1.04 + 0.13 * ease(p), 0.52 + sh, 0.48 + sh * 0.8)
    pulse = 0.5 + 0.5 * math.sin(lt * 2 * math.pi * 1.1)
    f[..., 0] *= 1 + 0.10 * pulse
    f[..., 1:] *= 1 - 0.10 * pulse
    for i, (t0, spr) in enumerate(sp_warn):
        a = window(t, t0, 11.2, 0.25, 0.3)
        if a:
            slide = -60 * (1 - ease_out((t - t0) / 0.35))
            flick = 0.75 + 0.25 * math.sin(t * 30) if t - t0 < 0.3 else 1
            comp(f, spr, 90 + slide, 250 + i * 130, a * flick)
    comp(f, sp_crit, 90, 650, window(t, 8.5, 11.2, 0.3, 0.3) * (0.65 + 0.35 * pulse))
    comp(f, sp_hud1, 70, 150, window(t, 5.0, 11.2, 0.3, 0.3))
    return f


def scene3a(t):
    lt = t - 10.78
    p = clamp(lt / 5.6)
    f = kenburns(img3, 1.12 - 0.08 * ease(p), 0.56 - 0.06 * p, 0.5)
    a = window(t, 10.95, 16.6, 0.5, 0.4)
    if a:
        trk = 1 - ease_out((t - 10.95) / 1.4)
        comp(f, top_grad, 0, 0, a)
        comp(f, sp_vb, W / 2 + 0, 150 - 10 * trk, a, center=True)
        comp(f, sp_vb_sub, W / 2, 262, a * ease((t - 11.6) / 0.5), center=True)
    return f


def scene3b(t):
    lt = t - 16.0
    if app_frames is not None:
        i = min(len(app_frames) - 1, max(0, int((lt + 0.2) * FPS)))
        f = app_frames[i].astype(np.float32)
        for k_, (t0, spr) in enumerate(sp_chips):
            comp(f, spr, 70, 70 + k_ * 50, window(t, t0, 22.0, 0.25, 0.3))
        return f
    f = bg3b.copy()
    fl = 0.92 + 0.08 * math.sin(lt * 9) * math.sin(lt * 3.3)
    bob = 6 * math.sin(lt * 1.6)
    comp(f, ring, 560, 905, 0.9, center=True)
    comp(f, holo_glow, 560, 470 + bob, 0.8 * fl, center=True)
    comp(f, holo, 560, 470 + bob, fl, center=True)
    # scan bar
    sy = int(60 + ((lt * 380) % 860))
    f[sy:sy + 3, 300:820] = f[sy:sy + 3, 300:820] * 0.3 + np.array(CYAN, np.float32) * 0.7
    f[sy - 40:sy, 300:820] += np.linspace(0, 1, 40)[:, None, None] * np.array([0, 25, 35], np.float32)
    # panel
    px, py = 1020, 90
    comp(f, panel_bg, px, py, 0.95)
    comp(f, sp_ph, px + 28, py + 18)
    comp(f, sp_live, px + 680, py + 18, 0.5 + 0.5 * (math.sin(lt * 6) > 0))
    hr = 74 + 4 * math.sin(lt * 1.7) + (14 * ease((t - 19.6) / 0.6) if t > 19.6 else 0)
    vals = [f'{hr:.0f}', f'{97.6 + 0.3 * math.sin(lt):.1f}', f'{14 + math.sin(lt * 0.8):.0f}', f'{0.48 + 0.02 * lt + (0.9 * ease((t - 19.6) / 1.2) if t > 19.6 else 0):.2f}']
    for i, v in enumerate(vals):
        cx, cy = px + 30 + (i % 2) * 390, py + 82 + (i // 2) * 120
        comp(f, vital_lbl[i], cx, cy)
        col = RED if (i in (0, 3) and t > 19.6) else (255, 255, 255)
        comp(f, text_sprite(v, f_val, col), cx - 4, cy + 26)
    # ECG strip
    ecg = Image.new('RGBA', (740, 110), (0, 0, 0, 0))
    pts = []
    for x in range(0, 740, 3):
        ph = ((x / 740) * 4 - lt * 1.25) % 1.0
        y = 55 - (math.exp(-((ph - 0.3) / 0.012) ** 2) * 48 - math.exp(-((ph - 0.33) / 0.015) ** 2) * 16
                  + math.exp(-((ph - 0.5) / 0.05) ** 2) * 10)
        pts.append((x, y))
    ImageDraw.Draw(ecg).line(pts, fill=(80, 255, 170, 255), width=3)
    comp(f, np.asarray(ecg).astype(np.float32), px + 30, py + 330)
    for k_, (t0, spr) in enumerate(sp_chips):
        comp(f, spr, px + 30, py + 460 + k_ * 48, window(t, t0, 22.0, 0.25, 0.3))
    if t > 19.6:
        a = ease((t - 19.6) / 0.15) * (0.75 + 0.25 * (math.sin((t - 19.6) * 14) > 0))
        comp(f, sp_alert, px, py + 655, a)
        f[..., 0] += 18 * a
    return f


# ---------------------------------------------------------------- real app screens
SHOTS = os.path.join(A, 'app')


class Screen:
    """A 3840x2160 app screenshot with a half-res copy to avoid aliasing on wide views."""

    def __init__(self, name):
        self.full = Image.open(os.path.join(SHOTS, name + '.png')).convert('RGB')
        self.half = self.full.resize((W, H), Image.LANCZOS)

    def view(self, z, cx, cy):
        src = self.half if z < 1.3 else self.full
        return kenburns(src, z, cx, cy)


scr = {n: Screen(n) for n in ('overview', 'assistant', 'crew', 'radiation', 'spe_overview', 'spe_radiation')}
data_bg = Image.open(os.path.join(SHOTS, 'data.png')).convert('RGB').resize((W, H), Image.LANCZOS).filter(ImageFilter.GaussianBlur(1.5))
data_bg = np.asarray(data_bg).astype(np.float32) * 0.55
bottom_grad = np.zeros((300, W, 4), np.float32)
bottom_grad[..., 3] = np.linspace(0, 215, 300)[:, None]
_tag_txt = text_sprite('LIVE PROTOTYPE  ·  REAL APP SCREEN', f_small, (255, 255, 255), tracking=4)
sp_live_tag = np.asarray(rounded_panel(_tag_txt.shape[1] + 24, _tag_txt.shape[0] + 8, 12, fill=(6, 14, 24, 235), border=(80, 255, 170, 230), bw=2)).astype(np.float32)
_ph, _pw = _tag_txt.shape[:2]
_dst = sp_live_tag[4:4 + _ph, 12:12 + _pw]
_al = _tag_txt[..., 3:4] / 255.0
_dst[..., :3] = _dst[..., :3] * (1 - _al) + _tag_txt[..., :3] * _al
_dst[..., 3:4] = np.maximum(_dst[..., 3:4], _tag_txt[..., 3:4])


def chip_sprite(text, red=False):
    col = RED if red else CYAN
    w_ = int(f_hs.getlength(text) + 4 * len(text)) + 70
    img = rounded_panel(w_, 64, 14, fill=(50, 6, 6, 225) if red else (6, 18, 32, 225), border=col + (255,), bw=3)
    d = ImageDraw.Draw(img)
    d.ellipse([22, 25, 36, 39], fill=col + (255,))
    x = 52
    for c in text:
        d.text((x, 14), c, font=f_hs, fill=(255, 255, 255, 255))
        x += f_hs.getlength(c) + 4
    return np.asarray(img).astype(np.float32)


def app_shot(name, t, t0, t1, z0, z1, c0, c1, chip=None, red=False):
    p = ease((t - t0) / (t1 - t0 + 0.3))
    f = scr[name].view(z0 + (z1 - z0) * p, c0[0] + (c1[0] - c0[0]) * p, c0[1] + (c1[1] - c0[1]) * p)
    if red:
        pulse = 0.5 + 0.5 * math.sin((t - t0) * 2 * math.pi * 1.4)
        f[..., 0] *= 1 + 0.08 * pulse
    comp(f, bottom_grad, 0, H - 300)
    comp(f, sp_live_tag, W - sp_live_tag.shape[1] - 40, 112, 0.95)
    if chip is not None:
        slide = -40 * (1 - ease_out((t - t0 - 0.1) / 0.4))
        comp(f, chip, 60 + slide, 40, ease((t - t0 - 0.1) / 0.3))
    return f


chip_ai = chip_sprite('AI FLIGHT SURGEON  ·  REPLIES IN MILLISECONDS')
chip_crew = chip_sprite('LIVE MONITORING  ·  EVERY ASTRONAUT')
chip_risk = chip_sprite('RISK PREDICTION  ·  DOSE VS. NASA LIMIT')
chip_act = chip_sprite('AUTONOMOUS ACTION  ·  CREW TO STORM SHELTER', red=True)
chip_act2 = chip_sprite('SOLAR STORM DETECTED  ·  DOSE RATE 7.5 mSv/day', red=True)


def appA(t):  # 10.78 – 12.30 mission control + title reveal
    f = app_shot('overview', t, 10.78, 12.3, 1.0, 1.07, (0.5, 0.45), (0.5, 0.42))
    a = window(t, 10.9, 12.5, 0.3, 0.3)
    if a:
        f *= 1 - 0.55 * a
        trk = 1 - ease_out((t - 10.9) / 1.2)
        comp(f, sp_shade, W / 2, 470, a, center=True)
        comp(f, sp_vb, W / 2, 440 - 10 * trk, a, center=True)
        comp(f, sp_vb_sub, W / 2, 560, a * ease((t - 11.3) / 0.4), center=True)
    return f


def appB(t):  # 12.30 – 15.00 AI flight surgeon conversation
    return app_shot('assistant', t, 12.3, 15.0, 1.45, 1.75, (0.33, 0.36), (0.36, 0.45), chip_ai)


def appC(t):  # 15.00 – 17.30 crew health hologram + vitals
    return app_shot('crew', t, 15.0, 17.3, 1.25, 1.5, (0.45, 0.42), (0.52, 0.4), chip_crew)


def appD(t):  # 17.30 – 18.90 radiation dose projection
    return app_shot('radiation', t, 17.3, 18.9, 1.75, 1.95, (0.68, 0.28), (0.72, 0.29), chip_risk)


def appE1(t):  # 18.90 – 20.20 SPE red alert on mission control
    return app_shot('spe_overview', t, 18.9, 20.2, 1.35, 1.55, (0.55, 0.22), (0.6, 0.18), chip_act, red=True)


def appE2(t):  # 20.20 – 21.50 SPE dose gauge
    return app_shot('spe_radiation', t, 20.2, 21.5, 1.6, 1.85, (0.22, 0.3), (0.22, 0.28), chip_act2, red=True)


def scene4(t):
    lt = t - 21.5
    z = 1.0 + 0.05 * lt / 8
    sw, sh = bg4.size
    rw, rh = W / z, H / z
    f = np.asarray(bg4.transform((W, H), Image.AFFINE, (rw / W, 0, (sw - rw) / 2, 0, rh / H, (sh - rh) / 2), resample=Image.BILINEAR)).astype(np.float32)
    wd = 1 - ease((t - 25.2) / 0.6)
    if wd > 0:
        f = f * (1 - wd) + data_bg * wd
    a = window(t, 21.6, 25.55, 0.4, 0.35)
    if a:
        comp(f, sp_built, W / 2, 330, a, center=True)
        for i, spr in enumerate(sp_cards):
            t0 = 21.85 + i * 0.42
            ai = a * ease((t - t0) / 0.3)
            comp(f, spr, 100 + i * 350, 440 + 30 * (1 - ease_out((t - t0) / 0.4)), ai)
    a = window(t, 25.55, 40, 0.6, 0.1)
    if a:
        s = 1 - ease_out((t - 25.55) / 1.5)
        comp(f, sp_end, W / 2, 430 - 12 * s, a, center=True)
        comp(f, sp_end_sub, W / 2, 565, a * ease((t - 26.1) / 0.5), center=True)
        comp(f, sp_end_team, W / 2, 650, a * ease((t - 26.6) / 0.5), center=True)
    return f


SCENES = [(0.0, scene1), (5.3, scene2), (10.78, appA), (12.3, appB), (15.0, appC), (17.3, appD),
          (18.9, appE1), (20.2, appE2), (21.5, scene4)]
XF = {5.3: 0.4, 12.3: 0.3, 15.0: 0.3, 17.3: 0.3, 18.9: 0.25, 20.2: 0.25, 21.5: 0.5}


def frame(t):
    idx = max(i for i, (s, _) in enumerate(SCENES) if s <= t)
    f = SCENES[idx][1](t)
    for b, d in XF.items():
        if b - d / 2 <= t < b + d / 2:
            i = [s for s, _ in SCENES].index(b)
            w = ease((t - (b - d / 2)) / d)
            f_prev = SCENES[i - 1][1](t)
            f_next = SCENES[i][1](t)
            f = f_prev * (1 - w) + f_next * w
    if 10.78 <= t < 11.08:  # reveal flash
        f += 255 * (1 - (t - 10.78) / 0.3) ** 2 * 0.85
    if 9.9 <= t < 10.78:  # dip before reveal
        f *= 1 - 0.8 * ease((t - 9.9) / 0.88)
    f *= vign
    gx, gy = rng.integers(0, 8, 2)
    g = grain_base[gy:gy + H // 2, gx:gx + W // 2]
    f += np.repeat(np.repeat(g, 2, 0), 2, 1)[..., None] * 5.0
    comp(f, sp_wm, W - sp_wm.shape[1] - 18, H - sp_wm.shape[0] - 10, 0.85)
    for s0, s1, txt in SUBS:
        if s0 <= t < s1:
            comp(f, sub_sprite(txt), W / 2, 975, window(t, s0, s1, 0.08, 0.08), center=True)
    if t < 0.35:
        f *= ease(t / 0.35)
    if t > DUR - 0.25:
        f *= ease((DUR - t) / 0.25)
    return np.clip(f, 0, 255).astype(np.uint8)


# ---------------------------------------------------------------- audio
def read_wav(path):
    with wave.open(path) as w_:
        n, ch, sr = w_.getnframes(), w_.getnchannels(), w_.getframerate()
        d = np.frombuffer(w_.readframes(n), np.int16).astype(np.float32) / 32768
    d = d.reshape(-1, ch).mean(1)
    assert sr == SR, sr
    return d


def build_audio():
    n = int(DUR * SR)
    tt = np.arange(n) / SR
    voice = np.zeros(n, np.float32)
    for name, st in VO:
        d = read_wav(os.path.join(A, name))
        i = int(st * SR)
        voice[i:i + len(d)] += d[:n - i]
    voice *= 0.95 / max(1e-6, np.abs(voice).max())

    music = np.zeros(n, np.float32)
    chords = [
        (0.0, 10.78, [55.0, 82.41, 110.0, 130.81, 164.81]),
        (10.78, 21.5, [43.65, 87.31, 130.81, 174.61, 196.0, 220.0]),
        (21.5, 25.55, [65.41, 98.0, 130.81, 164.81, 196.0]),
        (25.55, DUR, [55.0, 82.41, 110.0, 138.59, 164.81, 220.0, 277.18]),
    ]
    for c0, c1, fr in chords:
        env = np.clip((tt - c0 + 0.6) / 1.2, 0, 1) * np.clip((c1 + 0.6 - tt) / 1.2, 0, 1)
        sig = np.zeros(n, np.float32)
        for j, f0 in enumerate(fr):
            for hmc, amp in ((1, 1.0), (2, 0.35), (3, 0.12)):
                for det in (-0.6, 0.6):
                    sig += amp * np.sin(2 * np.pi * (f0 * hmc + det) * tt + j * 1.3 + hmc) / (1 + j * 0.35)
        music += sig * env * (0.5 + 0.5 * np.sin(2 * np.pi * 0.18 * tt) ** 2) * 0.05
    music *= np.clip(tt / 2.0, 0, 1) * 0.8 + 0.2 * np.clip(tt / 0.4, 0, 1)

    def add(at, sig, gain=1.0):
        i = int(at * SR)
        m = min(len(sig), n - i)
        if m > 0:
            music[i:i + m] += sig[:m] * gain

    def boom(dur=1.6, f1=90, f2=32):
        tl = np.arange(int(dur * SR)) / SR
        fr = f2 + (f1 - f2) * np.exp(-tl * 5)
        ph = 2 * np.pi * np.cumsum(fr) / SR
        return (np.sin(ph) * np.exp(-tl * 2.6) + rng.normal(0, 0.25, len(tl)) * np.exp(-tl * 18)).astype(np.float32)

    def riser(dur):
        tl = np.arange(int(dur * SR)) / SR
        nz = rng.normal(0, 1, len(tl)).astype(np.float32)
        nz = np.convolve(nz, np.ones(24) / 24, mode='same')
        return nz * (tl / dur) ** 2.2 * 2.2

    def thump():
        tl = np.arange(int(0.22 * SR)) / SR
        return np.sin(2 * np.pi * 52 * tl) * np.exp(-tl * 22)

    def beep(f0=1250, dur=0.09):
        tl = np.arange(int(dur * SR)) / SR
        return np.sin(2 * np.pi * f0 * tl) * np.minimum(1, (dur - tl) * 80) * np.minimum(1, tl * 400)

    add(0.0, boom(2.2, 70, 28), 0.55)
    add(4.4, riser(0.9), 0.10)
    add(5.3, boom(), 0.6)
    for k_ in range(7):
        add(5.45 + k_ * 0.75, thump(), 0.55)
        add(5.45 + k_ * 0.75 + 0.2, thump(), 0.35)
    add(9.8, riser(0.98), 0.22)
    add(10.78, boom(2.4, 110, 30), 0.85)
    tick = beep(1760, 0.03)
    for k_ in range(int((21.5 - 11.5) / 0.25)):
        add(11.5 + k_ * 0.25, tick, 0.05 if k_ % 2 else 0.08)
    for k_ in range(3):
        add(19.6 + k_ * 0.22, beep(), 0.16)
    add(21.0, riser(0.5), 0.10)
    add(21.5, boom(1.4), 0.4)
    add(24.8, riser(0.75), 0.16)
    add(25.55, boom(2.6, 100, 30), 0.75)

    hop = SR // 100
    blk = np.abs(voice[:n // hop * hop]).reshape(-1, hop).max(1)
    held = np.zeros_like(blk)
    acc = 0.0
    for i_, v_ in enumerate(blk):
        acc = max(v_, acc * 0.93)
        held[i_] = acc
    env = np.interp(np.arange(n) / hop, np.arange(len(held)), held).astype(np.float32)
    env = env / max(1e-6, env.max())
    duck = 1 - 0.55 * np.clip(env * 3, 0, 1)
    music *= duck
    mix = voice + music * 0.9
    fade = np.clip((DUR - tt) / 0.6, 0, 1)
    mix *= fade
    mix *= 0.89 / max(1e-6, np.abs(mix).max())
    out = os.path.join(A, 'mix.wav')
    with wave.open(out, 'wb') as w_:
        w_.setnchannels(2)
        w_.setsampwidth(2)
        w_.setframerate(SR)
        st = (np.repeat(mix[:, None], 2, 1) * 32767).astype(np.int16)
        w_.writeframes(st.tobytes())
    return out


def write_srt():
    def ts(x):
        ms = int(round(x * 1000))
        return f'{ms // 3600000:02d}:{ms // 60000 % 60:02d}:{ms // 1000 % 60:02d},{ms % 1000:03d}'
    with open(os.path.join(ROOT, 'VanBlue_Aegis_Demo_EN.srt'), 'w', encoding='utf-8') as fh:
        for i, (a, b, s) in enumerate(SUBS, 1):
            fh.write(f'{i}\n{ts(a)} --> {ts(b)}\n{s}\n\n')


if __name__ == '__main__':
    if len(sys.argv) > 2 and sys.argv[2] == 'still':
        for ts_ in map(float, sys.argv[3:]):
            Image.fromarray(frame(ts_)).resize((960, 540)).save(os.path.join(A, f'still_{ts_:05.2f}.jpg'), quality=85)
        sys.exit()
    write_srt()
    print('audio…', flush=True)
    mix = build_audio()
    print('video…', flush=True)
    cmd = ['ffmpeg', '-v', 'error', '-y', '-f', 'rawvideo', '-pix_fmt', 'rgb24', '-s', f'{W}x{H}', '-r', str(FPS), '-i', '-',
           '-i', mix, '-c:v', 'libx264', '-preset', 'slow', '-crf', '17', '-pix_fmt', 'yuv420p', '-c:a', 'aac', '-b:a', '192k',
           '-t', str(DUR), '-movflags', '+faststart',
           '-metadata', 'title=VanBlue Aegis - NASA Space Apps Challenge 2026 Demo',
           '-metadata', 'comment=AI disclosure: Turkish voiceover is AI-generated (ElevenLabs TTS via Higgsfield). Space background images are AI-generated (Higgsfield / Nano Banana Pro) and carry a visible AI-generated watermark. Product scenes are real screen captures of the VanBlue Aegis app. Music and motion graphics generated procedurally by the team.',
           '-metadata', 'language=tur', '-metadata:s:a:0', 'language=tur', OUT]
    proc = subprocess.Popen(cmd, stdin=subprocess.PIPE)
    for i in range(NF):
        proc.stdin.write(frame(i / FPS).tobytes())
        if i % 60 == 0:
            print(f'  frame {i}/{NF}', flush=True)
    proc.stdin.close()
    proc.wait()
    print('done ->', OUT)
