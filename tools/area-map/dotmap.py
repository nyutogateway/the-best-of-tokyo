#!/usr/bin/env python3
"""放送エリア図（assets/img/area-map.svg）を「点描（ドット）」で作る

陸を細かい点の並びで描き、東京都の点だけを金にする。50kmの円の内側の点は少し明るく。
枠も四角い地も付けない（背景は透明）。図の端は放射状にぼかして、サイトの地（紺）に溶かす。
点は SVG の pattern で敷いているので、ファイルは軽い。
県境・東京都・市の位置は shapes.json（restyle.py / nightmap.py と同じもの）から。

    python3 tools/area-map/dotmap.py

座標は makemap.py と同じ（中心＝東京スカイツリー (340, 245)、1km = 3.953px）。
"""
import json
import pathlib
import re

HERE = pathlib.Path(__file__).resolve().parent
ROOT = HERE.parent.parent
SVG = ROOT / "assets/img/area-map.svg"

W, H = 680, 490
CX, CY = 340.0, 245.0
KM = 3.953
R50 = 50 * KM
STEP = 5.2  # 点の間隔(px)

GOLD = "#d9b86a"
GOLD_LIGHT = "#e3c98c"
CREAM = "#f3ead2"
NAVY = "#0e1932"
FONT = "'Shippori Mincho B1','Hiragino Mincho ProN','Yu Mincho',serif"

shapes = json.loads((HERE / "shapes.json").read_text(encoding="utf-8"))
land, tokyo = shapes["land"], shapes["tokyo"]
KEEP = ["さいたま市", "千葉市", "横浜市", "つくば市", "成田市", "横須賀市"]
cities = [c for c in shapes["cities"] if c[2] in KEEP]
d_land = " ".join(land)

MARK = (ROOT / "assets/img/mark.svg").read_text(encoding="utf-8")
mark_lines = re.findall(r"<line[^>]+/>", MARK)
pts = [tuple(map(float, m)) for m in re.findall(r'x1="([\d.]+)" y1="([\d.]+)"', MARK)]
mcx = sum(p[0] for p in pts) / len(pts)
mcy = sum(p[1] for p in pts) / len(pts)

halo = f'paint-order="stroke" stroke="{NAVY}" stroke-width="4" stroke-linejoin="round"'


def dots(pid, r, color, op):
    return (f'<pattern id="{pid}" width="{STEP}" height="{STEP}" patternUnits="userSpaceOnUse">'
            f'<circle cx="{STEP / 2}" cy="{STEP / 2}" r="{r}" fill="{color}" fill-opacity="{op}"/></pattern>')


out = []
w = out.append
w(f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {W} {H}" width="{W}" height="{H}" role="img" '
  'aria-label="TOKYO MX の放送エリア図。放送対象地域の東京都と、東京スカイツリーから約50kmの受信エリアの目安">')
w("""  <!-- 放送エリア図（点描）。作り方は tools/area-map/dotmap.py。
       県境：国土数値情報（国土交通省）の行政区域データを加工 -->""")
w(f"""  <defs>
    {dots("dotLand", 1.05, "#ffffff", ".22")}
    {dots("dotReach", 1.25, CREAM, ".55")}
    {dots("dotTokyo", 1.6, GOLD, "1")}
    <clipPath id="reach"><circle cx="{CX}" cy="{CY}" r="{R50:.1f}"/></clipPath>
    <!-- 端を背景に溶かす（枠を出さない） -->
    <radialGradient id="fadeG" cx="50%" cy="50%" r="62%">
      <stop offset=".62" stop-color="#fff"/><stop offset="1" stop-color="#fff" stop-opacity="0"/>
    </radialGradient>
    <mask id="fade" maskUnits="userSpaceOnUse" x="0" y="0" width="{W}" height="{H}">
      <rect width="{W}" height="{H}" fill="url(#fadeG)"/>
    </mask>
  </defs>""")
w('  <g mask="url(#fade)">')
w(f'    <path d="{d_land}" fill="url(#dotLand)"/>')
w(f'    <path d="{d_land}" fill="url(#dotReach)" clip-path="url(#reach)"/>')
w(f'    <path d="{tokyo}" fill="{NAVY}"/>')
w(f'    <path d="{tokyo}" fill="url(#dotTokyo)"/>')
w("  </g>")
# 東京都の縁（ごく細い金）
w(f'  <path d="{tokyo}" fill="none" stroke="{GOLD_LIGHT}" stroke-opacity=".7" stroke-width=".7" stroke-linejoin="round"/>')
# 50kmの円
w(f'  <circle cx="{CX}" cy="{CY}" r="{R50:.1f}" fill="none" stroke="{CREAM}" stroke-opacity=".7" stroke-width=".8"/>')
w(f'  <text x="{CX}" y="{CY - R50 - 8:.1f}" text-anchor="middle" font-family="{FONT}" font-size="11" letter-spacing="2" fill="{CREAM}" {halo}>半径 約50km</text>')

prefs = [(150, 130, "埼玉県"), (520, 392, "千葉県"), (196, 420, "神奈川県"), (560, 88, "茨城県")]
w(f'  <g font-family="{FONT}" font-size="13" letter-spacing="7" fill="#fff" fill-opacity=".62" text-anchor="middle" {halo}>')
for x, y, ja in prefs:
    w(f'    <text x="{x}" y="{y}">{ja}</text>')
w("  </g>")
w(f'  <text x="112" y="205" text-anchor="middle" font-family="{FONT}" font-size="17" font-weight="700" letter-spacing="9" fill="{GOLD_LIGHT}" {halo}>東京都</text>')

w(f'  <g font-family="{FONT}" font-size="11" letter-spacing="1" fill="#fff">')
for x, y, name in cities:
    w(f'    <circle cx="{x}" cy="{y}" r="1.8" fill="{CREAM}"/>')
    w(f'    <text x="{x + 6:.1f}" y="{y + 3.6:.1f}" {halo}>{name}</text>')
w("  </g>")

sc = 0.065
w(f'  <g transform="translate({CX - mcx * sc:.2f} {CY - mcy * sc:.2f}) scale({sc})" fill="none" stroke="{CREAM}" stroke-width="16" stroke-linecap="round">')
for ln in mark_lines:
    w("    " + ln)
w("  </g>")
w(f'  <g font-family="{FONT}" {halo}>')
w(f'    <text x="{CX + 24}" y="{CY - 6}" font-size="13.5" font-weight="700" letter-spacing="2" fill="{CREAM}">東京スカイツリー</text>')
w(f'    <text x="{CX + 24}" y="{CY + 10}" font-size="10" letter-spacing="2" fill="{GOLD_LIGHT}">送信所・地上デジタル 9ch</text>')
w("  </g>")

lx0, ly0 = 30, 430
w(f'  <g font-family="{FONT}" font-size="10.5" letter-spacing="1.5" fill="#fff" {halo}>')
for i in range(3):
    w(f'    <circle cx="{lx0 + 2 + i * 5.2:.1f}" cy="{ly0 + 4}" r="1.6" fill="{GOLD}" stroke="none"/>')
w(f'    <text x="{lx0 + 24}" y="{ly0 + 8}">放送対象地域（東京都）</text>')
w(f'    <circle cx="{lx0 + 8}" cy="{ly0 + 24}" r="7" fill="none" stroke="{CREAM}" stroke-opacity=".7" stroke-width=".8"/>')
w(f'    <text x="{lx0 + 24}" y="{ly0 + 28}">受信エリアの目安（送信所から約50km）</text>')
w("  </g>")
w("</svg>")

SVG.write_text("\n".join(out) + "\n", encoding="utf-8")
print(f"{SVG.relative_to(ROOT)} を書き出しました（{SVG.stat().st_size // 1024}KB）")
