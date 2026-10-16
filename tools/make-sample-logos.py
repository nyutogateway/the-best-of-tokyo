#!/usr/bin/env python3
"""サンプル用の「ロゴが入ります」プレースホルダを作る（assets/img/sample/logo-NN.svg）

実在の企業ロゴの代わりに置くものなので、どう見てもサンプルだと分かる形にしている。
ただし FV背面のロゴの壁・流れる帯・カードの表に大量に並ぶので、
実際のロゴ集のように **形・組み方・色・縦横比をばらけさせている**。

- 白地に置かれるので背景は透明、図形は濃い色
- 組み方は4種（マークの下に文字／マークの右に文字／文字だけ／ベタのバッジ）
- どれも「LOGO」の文字を残して、サンプルだと分かるようにしている
"""
import math
import pathlib

OUT = pathlib.Path(__file__).resolve().parent.parent / "assets" / "img" / "sample"

# 企業ロゴらしく見える範囲で、濃さの違う色をいくつか
INKS = {
    "navy": "#1f2a44",
    "charcoal": "#2f3338",
    "green": "#1f4038",
    "wine": "#4a2230",
    "steel": "#3b4a5a",
    "brown": "#4a3a2a",
    "indigo": "#2a2f55",
    "slate": "#44505c",
    "teal": "#1e3a44",
}
SUB = "#9aa3ae"   # 「ロゴが入ります」の色


# ------------------------------------------------------------------
# マーク（cx, cy を中心に、半径 r でおさまる図形を返す）
# ------------------------------------------------------------------
def m_circle(cx, cy, r, ink):
    return (
        f'<circle cx="{cx:.1f}" cy="{cy:.1f}" r="{r:.1f}" fill="none" stroke="{ink}" stroke-width="{r*0.16:.1f}"/>'
        f'<circle cx="{cx:.1f}" cy="{cy:.1f}" r="{r*0.4:.1f}" fill="{ink}"/>'
    )


def m_ring(cx, cy, r, ink):
    """右上が開いた太い輪（Cの形）"""
    a0, a1 = math.radians(45), math.radians(-45)
    x0, y0 = cx + r * math.cos(a0), cy + r * math.sin(a0)
    x1, y1 = cx + r * math.cos(a1), cy + r * math.sin(a1)
    return (
        f'<path d="M {x0:.1f} {y0:.1f} A {r:.1f} {r:.1f} 0 1 1 {x1:.1f} {y1:.1f}" '
        f'fill="none" stroke="{ink}" stroke-width="{r*0.26:.1f}" stroke-linecap="round"/>'
    )


def m_square(cx, cy, r, ink):
    s = r * 1.7
    return (
        f'<rect x="{cx-s/2:.1f}" y="{cy-s/2:.1f}" width="{s:.1f}" height="{s:.1f}" rx="{s*0.08:.1f}" '
        f'fill="none" stroke="{ink}" stroke-width="{r*0.16:.1f}"/>'
        f'<rect x="{cx-s*0.2:.1f}" y="{cy-s*0.2:.1f}" width="{s*0.4:.1f}" height="{s*0.4:.1f}" fill="{ink}"/>'
    )


def m_triangle(cx, cy, r, ink):
    return (
        f'<path d="M {cx:.1f} {cy-r:.1f} L {cx+r*0.92:.1f} {cy+r*0.72:.1f} L {cx-r*0.92:.1f} {cy+r*0.72:.1f} Z" '
        f'fill="none" stroke="{ink}" stroke-width="{r*0.16:.1f}" stroke-linejoin="round"/>'
    )


def m_diamond(cx, cy, r, ink):
    return (
        f'<path d="M {cx:.1f} {cy-r:.1f} L {cx+r:.1f} {cy:.1f} L {cx:.1f} {cy+r:.1f} L {cx-r:.1f} {cy:.1f} Z" '
        f'fill="{ink}"/>'
        f'<path d="M {cx:.1f} {cy-r*0.38:.1f} L {cx+r*0.38:.1f} {cy:.1f} L {cx:.1f} {cy+r*0.38:.1f} L {cx-r*0.38:.1f} {cy:.1f} Z" '
        f'fill="#ffffff"/>'
    )


def m_hexagon(cx, cy, r, ink):
    pts = []
    for i in range(6):
        a = math.radians(60 * i - 30)
        pts.append(f"{cx + r*math.cos(a):.1f} {cy + r*math.sin(a):.1f}")
    return (
        f'<path d="M {" L ".join(pts)} Z" fill="none" stroke="{ink}" stroke-width="{r*0.16:.1f}" stroke-linejoin="round"/>'
        f'<path d="M {cx-r*0.45:.1f} {cy:.1f} L {cx+r*0.45:.1f} {cy:.1f}" stroke="{ink}" stroke-width="{r*0.16:.1f}" stroke-linecap="round"/>'
    )


def m_bars(cx, cy, r, ink):
    """高さの違う3本の柱"""
    w = r * 0.36
    out = ""
    for i, h in enumerate((1.1, 1.7, 0.7)):
        x = cx - r + i * (r * 0.78)
        out += f'<rect x="{x:.1f}" y="{cy + r*0.8 - r*h:.1f}" width="{w:.1f}" height="{r*h:.1f}" fill="{ink}"/>'
    return out


def m_mountain(cx, cy, r, ink):
    return (
        f'<path d="M {cx-r:.1f} {cy+r*0.7:.1f} L {cx-r*0.2:.1f} {cy-r*0.5:.1f} L {cx+r*0.25:.1f} {cy+r*0.1:.1f} '
        f'L {cx+r*0.55:.1f} {cy-r*0.3:.1f} L {cx+r:.1f} {cy+r*0.7:.1f} Z" fill="{ink}"/>'
    )


def m_cross(cx, cy, r, ink):
    t = r * 0.3
    return (
        f'<circle cx="{cx:.1f}" cy="{cy:.1f}" r="{r:.1f}" fill="{ink}"/>'
        f'<rect x="{cx-t/2:.1f}" y="{cy-r*0.62:.1f}" width="{t:.1f}" height="{r*1.24:.1f}" fill="#ffffff"/>'
        f'<rect x="{cx-r*0.62:.1f}" y="{cy-t/2:.1f}" width="{r*1.24:.1f}" height="{t:.1f}" fill="#ffffff"/>'
    )


def m_waves(cx, cy, r, ink):
    out = ""
    for i, dy in enumerate((-r * 0.45, 0, r * 0.45)):
        out += (
            f'<path d="M {cx-r:.1f} {cy+dy:.1f} q {r*0.5:.1f} {-r*0.4:.1f} {r:.1f} 0 q {r*0.5:.1f} {r*0.4:.1f} {r:.1f} 0" '
            f'fill="none" stroke="{ink}" stroke-width="{r*0.13:.1f}" stroke-linecap="round"/>'
        )
    return out


def m_twin(cx, cy, r, ink):
    """重なった2つの輪"""
    return (
        f'<circle cx="{cx-r*0.42:.1f}" cy="{cy:.1f}" r="{r*0.68:.1f}" fill="none" stroke="{ink}" stroke-width="{r*0.17:.1f}"/>'
        f'<circle cx="{cx+r*0.42:.1f}" cy="{cy:.1f}" r="{r*0.68:.1f}" fill="none" stroke="{ink}" stroke-width="{r*0.17:.1f}"/>'
    )


def m_petal(cx, cy, r, ink):
    """4枚の花弁"""
    out = ""
    for a in (0, 90, 180, 270):
        out += (
            f'<path d="M {cx:.1f} {cy:.1f} q {r*0.55:.1f} {-r*0.55:.1f} {r:.1f} 0 q {-r*0.45:.1f} {r*0.55:.1f} {-r:.1f} 0 Z" '
            f'fill="{ink}" opacity="0.9" transform="rotate({a} {cx:.1f} {cy:.1f})"/>'
        )
    return out


def m_star(cx, cy, r, ink):
    pts = []
    for i in range(10):
        rad = r if i % 2 == 0 else r * 0.44
        a = math.radians(36 * i - 90)
        pts.append(f"{cx + rad*math.cos(a):.1f} {cy + rad*math.sin(a):.1f}")
    return f'<path d="M {" L ".join(pts)} Z" fill="{ink}"/>'


def m_arrow(cx, cy, r, ink):
    return (
        f'<path d="M {cx-r*0.9:.1f} {cy+r*0.55:.1f} L {cx:.1f} {cy-r*0.75:.1f} L {cx+r*0.9:.1f} {cy+r*0.55:.1f}" '
        f'fill="none" stroke="{ink}" stroke-width="{r*0.2:.1f}" stroke-linecap="round" stroke-linejoin="round"/>'
        f'<path d="M {cx-r*0.9:.1f} {cy+r*0.95:.1f} L {cx+r*0.9:.1f} {cy+r*0.95:.1f}" '
        f'stroke="{ink}" stroke-width="{r*0.2:.1f}" stroke-linecap="round"/>'
    )


MARKS = {
    "circle": m_circle,
    "ring": m_ring,
    "square": m_square,
    "triangle": m_triangle,
    "diamond": m_diamond,
    "hexagon": m_hexagon,
    "bars": m_bars,
    "mountain": m_mountain,
    "cross": m_cross,
    "waves": m_waves,
    "twin": m_twin,
    "petal": m_petal,
    "star": m_star,
    "arrow": m_arrow,
}

# 文字の組み方（フォント・字間・大文字小文字）をばらけさせる
TYPES = {
    "wide":   dict(text="LOGO", family="Helvetica, Arial, sans-serif", weight="400", track=0.26, upper=True),
    "bold":   dict(text="LOGO", family="Helvetica, Arial, sans-serif", weight="700", track=0.04, upper=True),
    "serif":  dict(text="LOGO", family="Georgia, 'Times New Roman', serif", weight="400", track=0.18, upper=True),
    "lower":  dict(text="logo", family="Helvetica, Arial, sans-serif", weight="700", track=0.0, upper=False),
    "serifb": dict(text="Logo", family="Georgia, 'Times New Roman', serif", weight="700", track=0.06, upper=False),
}

# 50人ぶん＝50種類。実際のロゴ集のように、形・組み方・文字・色・縦横比をずらして組み合わせる。
#   layout: stack=マークの下に文字 / row=マークの右に文字 / type=文字だけ / badge=ベタ四角に白抜き
LAYOUT_CYCLE = ["stack", "row", "stack", "row", "stack", "type", "row", "badge"]

WIDE = [(420, 130), (440, 140), (460, 140), (430, 135), (480, 120), (450, 128), (410, 150)]
SQUARE = [(500, 500), (360, 360), (340, 340), (420, 420), (380, 380)]
TALL = [(300, 420), (240, 330), (260, 360), (280, 400)]

MARK_KEYS = list(MARKS.keys())
TYPE_KEYS = list(TYPES.keys())
INK_KEYS = list(INKS.keys())


def make_specs(n=50):
    """同じ組み合わせが続かないよう、互いに素な歩幅でずらしながら n 種類つくる"""
    specs = []
    for i in range(n):
        layout = LAYOUT_CYCLE[i % len(LAYOUT_CYCLE)]
        mark = MARK_KEYS[(i * 3) % len(MARK_KEYS)]
        type_key = TYPE_KEYS[(i * 2) % len(TYPE_KEYS)]
        # 色数（9）と組み方の周期（8）は互いに素なので、縦にも横にも同じ色が並ばない
        ink_key = INK_KEYS[(i * 5) % len(INK_KEYS)]

        if layout == "row" or layout == "type":
            w, h = WIDE[(i * 3) % len(WIDE)]
        elif layout == "badge":
            w, h = (SQUARE if i % 2 else WIDE)[(i * 2) % 5]
        else:   # stack … 正方形と縦長を混ぜる
            w, h = (TALL[(i * 3) % len(TALL)] if i % 3 == 1 else SQUARE[(i * 2) % len(SQUARE)])

        specs.append((i + 1, w, h, layout, mark, type_key, ink_key))
    return specs


SPECS = make_specs(50)


def label(x, y, size, ink, ts, anchor="start"):
    t = ts["text"]
    return (
        f'<text x="{x:.1f}" y="{y:.1f}" fill="{ink}" text-anchor="{anchor}" '
        f'font-family="{ts["family"]}" font-weight="{ts["weight"]}" font-size="{size:.1f}" '
        f'letter-spacing="{size*ts["track"]:.1f}">{t}</text>'
    )


def sub(x, y, size, anchor="start"):
    return (
        f'<text x="{x:.1f}" y="{y:.1f}" fill="{SUB}" text-anchor="{anchor}" '
        f'font-family="Hiragino Sans, Yu Gothic, sans-serif" font-size="{size:.1f}">ロゴが入ります</text>'
    )


def build(w, h, layout, mark, type_key, ink_key):
    ink = INKS[ink_key]
    ts = TYPES[type_key]
    body = ""

    if layout == "row":
        r = h * 0.28
        cx, cy = h * 0.5, h * 0.5
        body += MARKS[mark](cx, cy, r, ink)
        size = h * 0.3
        body += label(h * 1.0, h * 0.52 + size * 0.34, size, ink, ts)
        body += sub(h * 1.02, h * 0.82, h * 0.145)

    elif layout == "stack":
        r = min(w, h) * (0.25 if h > w * 1.2 else 0.2)
        body += MARKS[mark](w * 0.5, h * 0.36, r, ink)
        size = min(w, h) * 0.145
        body += label(w * 0.5, h * 0.72, size, ink, ts, anchor="middle")
        body += sub(w * 0.5, h * 0.86, min(w, h) * 0.075, anchor="middle")

    elif layout == "type":
        size = h * 0.4
        body += label(w * 0.5, h * 0.52, size, ink, ts, anchor="middle")
        body += (
            f'<path d="M {w*0.3:.1f} {h*0.66:.1f} L {w*0.7:.1f} {h*0.66:.1f}" '
            f'stroke="{ink}" stroke-width="{h*0.035:.1f}"/>'
        )
        body += sub(w * 0.5, h * 0.86, h * 0.14, anchor="middle")

    else:  # badge … ベタの四角に白抜き
        pad = min(w, h) * 0.12
        bw, bh = w - pad * 2, h * (0.52 if h > w * 0.8 else 0.66)
        body += (
            f'<rect x="{pad:.1f}" y="{pad:.1f}" width="{bw:.1f}" height="{bh:.1f}" fill="{ink}"/>'
        )
        size = bh * 0.42
        white = dict(ts)
        body += (
            f'<text x="{w*0.5:.1f}" y="{pad + bh*0.5 + size*0.35:.1f}" fill="#ffffff" text-anchor="middle" '
            f'font-family="{white["family"]}" font-weight="{white["weight"]}" font-size="{size:.1f}" '
            f'letter-spacing="{size*white["track"]:.1f}">{white["text"]}</text>'
        )
        body += sub(w * 0.5, h * 0.88, min(w, h) * 0.085, anchor="middle")

    return (
        f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {w} {h}" width="{w}" height="{h}" '
        f'role="img" aria-label="ロゴが入ります">{body}</svg>\n'
    )


def main():
    OUT.mkdir(parents=True, exist_ok=True)
    for no, w, h, layout, mark, type_key, ink_key in SPECS:
        (OUT / f"logo-{no:02d}.svg").write_text(
            build(w, h, layout, mark, type_key, ink_key), encoding="utf-8"
        )
    print(f"{len(SPECS)} 個のサンプルロゴを書き出しました → {OUT}")


if __name__ == "__main__":
    main()
