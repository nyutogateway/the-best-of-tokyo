#!/usr/bin/env python3
"""サンプル用の「ロゴが入ります」プレースホルダを作る（assets/img/sample/logo-NN.svg）

実在の企業ロゴの代わりに置くものなので、どう見てもサンプルだと分かる形にしている。
- 白地に置かれる（カードの表・FV背面のロゴの壁・流れる帯）ので、背景は透明・図形は墨色
- 大きさがまちまちのほうが壁が単調にならないため、正方形・横長・縦長を混ぜている
"""
import pathlib

OUT = pathlib.Path(__file__).resolve().parent.parent / "assets" / "img" / "sample"

INK = "#2b3444"
SUB = "#8c97a8"

# (ファイル番号, 幅, 高さ, マークの形, 文字を出すか)
SHAPES = [
    (1, 500, 500, "circle", True),
    (2, 420, 130, "square", True),
    (3, 300, 420, "triangle", True),
    (4, 420, 150, "diamond", True),
    (5, 500, 500, "hexagon", True),
    (6, 420, 120, "circle", True),
    (7, 220, 320, "square", True),
    (8, 460, 140, "triangle", True),
    (9, 500, 500, "diamond", True),
    (10, 420, 128, "hexagon", True),
    (11, 360, 360, "square", True),
    (12, 480, 120, "circle", True),
]


def mark(kind, cx, cy, r):
    """マークのパス。線だけで組み、ロゴらしい重さを出す"""
    if kind == "circle":
        return (
            f'<circle cx="{cx}" cy="{cy}" r="{r}" fill="none" stroke="{INK}" stroke-width="{r*0.16:.1f}"/>'
            f'<circle cx="{cx}" cy="{cy}" r="{r*0.42:.1f}" fill="{INK}"/>'
        )
    if kind == "square":
        s = r * 1.7
        return (
            f'<rect x="{cx-s/2:.1f}" y="{cy-s/2:.1f}" width="{s:.1f}" height="{s:.1f}" fill="none" stroke="{INK}" stroke-width="{r*0.16:.1f}"/>'
            f'<rect x="{cx-s*0.22:.1f}" y="{cy-s*0.22:.1f}" width="{s*0.44:.1f}" height="{s*0.44:.1f}" fill="{INK}"/>'
        )
    if kind == "triangle":
        return (
            f'<path d="M {cx:.1f} {cy-r:.1f} L {cx+r*0.92:.1f} {cy+r*0.72:.1f} L {cx-r*0.92:.1f} {cy+r*0.72:.1f} Z" '
            f'fill="none" stroke="{INK}" stroke-width="{r*0.16:.1f}" stroke-linejoin="round"/>'
            f'<circle cx="{cx}" cy="{cy+r*0.18:.1f}" r="{r*0.26:.1f}" fill="{INK}"/>'
        )
    if kind == "diamond":
        return (
            f'<path d="M {cx:.1f} {cy-r:.1f} L {cx+r:.1f} {cy:.1f} L {cx:.1f} {cy+r:.1f} L {cx-r:.1f} {cy:.1f} Z" '
            f'fill="none" stroke="{INK}" stroke-width="{r*0.16:.1f}" stroke-linejoin="round"/>'
            f'<path d="M {cx:.1f} {cy-r*0.4:.1f} L {cx+r*0.4:.1f} {cy:.1f} L {cx:.1f} {cy+r*0.4:.1f} L {cx-r*0.4:.1f} {cy:.1f} Z" fill="{INK}"/>'
        )
    # hexagon
    pts = []
    import math
    for i in range(6):
        a = math.radians(60 * i - 30)
        pts.append(f"{cx + r*math.cos(a):.1f} {cy + r*math.sin(a):.1f}")
    return (
        f'<path d="M {" L ".join(pts)} Z" fill="none" stroke="{INK}" stroke-width="{r*0.16:.1f}" stroke-linejoin="round"/>'
        f'<circle cx="{cx}" cy="{cy}" r="{r*0.3:.1f}" fill="{INK}"/>'
    )


def build(no, w, h, kind, with_text):
    tall = h > w * 1.2
    wide = w > h * 1.6

    if wide:
        r = h * 0.3
        cx, cy = h * 0.5, h * 0.5
        body = mark(kind, cx, cy, r)
        if with_text:
            body += (
                f'<text x="{h*1.02:.1f}" y="{h*0.5+h*0.1:.1f}" fill="{INK}" '
                f'font-family="Helvetica, Arial, sans-serif" font-size="{h*0.3:.1f}" '
                f'letter-spacing="{h*0.06:.1f}">LOGO</text>'
                f'<text x="{h*1.04:.1f}" y="{h*0.5+h*0.34:.1f}" fill="{SUB}" '
                f'font-family="Hiragino Sans, Yu Gothic, sans-serif" font-size="{h*0.15:.1f}">ロゴが入ります</text>'
            )
    else:
        r = min(w, h) * (0.26 if tall else 0.20)
        cx = w * 0.5
        cy = h * (0.36 if with_text else 0.5)
        body = mark(kind, cx, cy, r)
        if with_text:
            body += (
                f'<text x="{cx:.1f}" y="{h*0.72:.1f}" fill="{INK}" text-anchor="middle" '
                f'font-family="Helvetica, Arial, sans-serif" font-size="{min(w,h)*0.14:.1f}" '
                f'letter-spacing="{min(w,h)*0.03:.1f}">LOGO</text>'
                f'<text x="{cx:.1f}" y="{h*0.86:.1f}" fill="{SUB}" text-anchor="middle" '
                f'font-family="Hiragino Sans, Yu Gothic, sans-serif" font-size="{min(w,h)*0.075:.1f}">ロゴが入ります</text>'
            )

    return (
        f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {w} {h}" width="{w}" height="{h}" '
        f'role="img" aria-label="ロゴが入ります">{body}</svg>\n'
    )


def main():
    OUT.mkdir(parents=True, exist_ok=True)
    for no, w, h, kind, with_text in SHAPES:
        path = OUT / f"logo-{no:02d}.svg"
        path.write_text(build(no, w, h, kind, with_text), encoding="utf-8")
    print(f"{len(SHAPES)} 個のサンプルロゴを書き出しました → {OUT}")


if __name__ == "__main__":
    main()
