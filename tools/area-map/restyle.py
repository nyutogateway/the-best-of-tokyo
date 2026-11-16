#!/usr/bin/env python3
"""放送エリア図（assets/img/area-map.svg）の見た目を組み直す

県境の形は makemap.py で起こした今の area-map.svg からそのまま引き継ぎ、
見た目（海と陸の塗り分け、受信エリアの光、電波の輪、目盛り・経緯線・縮尺・方位、文字）だけを作り直す。
makemap.py で県境を作り直したときは、そのあとにこれを流すこと。

    python3 tools/area-map/restyle.py

座標は makemap.py と同じ（中心＝東京スカイツリー (340, 245)、1km = 3.953px、経度1度 = 357.3px、緯度1度 = 440.0px）。
"""
import json
import math
import pathlib
import re

ROOT = pathlib.Path(__file__).resolve().parent.parent.parent
SVG = ROOT / "assets/img/area-map.svg"

W, H = 680, 490
CX, CY = 340.0, 245.0
KM = 3.953
R50 = 50 * KM
LON0, LAT0 = 139.8107, 35.7101
PX_LON, PX_LAT = 357.3, 440.0

GOLD = "#c9a45a"
GOLD_LIGHT = "#e3c98c"
CREAM = "#fff6dc"
SEA = "#0a1226"
LAND = "#16223c"
LAND_IN = "#24375f"
FONT = "'Hiragino Sans','Hiragino Kaku Gothic ProN','Yu Gothic',sans-serif"
NUM = "'Helvetica Neue',Arial,sans-serif"

src = SVG.read_text(encoding="utf-8")

# 形（県境・東京都・スカイツリーの形・市の点）は shapes.json に持つ。
# 無ければ makemap.py が書き出した素の area-map.svg から拾って作る
# （このスクリプトで組み直したあとの svg からは拾えないので、makemap.py で作り直したら shapes.json を消してから流す）
SHAPES = pathlib.Path(__file__).resolve().parent / "shapes.json"
if SHAPES.exists():
    shapes = json.loads(SHAPES.read_text(encoding="utf-8"))
else:
    land_g = re.search(r'<g fill="rgba\(255,255,255,\.05\)"[^>]*>(.*?)</g>', src, re.S).group(1)
    shapes = {
        "land": re.findall(r'<path d="([^"]+)"', land_g),
        "tokyo": re.search(r'<path d="([^"]+)" fill="url\(#tokyoFill\)"', src).group(1),
        "tower": re.search(r'<path d="([^"]+)" fill="#fff6dc"', src).group(1),
        "cities": [
            [float(cx), float(cy), name]
            for cx, cy, name in re.findall(
                r'<circle cx="([\d.]+)" cy="([\d.]+)" r="2.4"[^/]*/>\s*<text[^>]*>([^<]+)</text>', src
            )
        ],
    }
    SHAPES.write_text(json.dumps(shapes, ensure_ascii=False), encoding="utf-8")
land, tokyo, tower = shapes["land"], shapes["tokyo"], shapes["tower"]

# 市の点。東京都の中の市は外す（東京都はまるごと放送対象なので、点を打っても意味が薄い）
IN_TOKYO = {"立川市", "八王子市", "青梅市"}
cities = [(x, y, name) for x, y, name in shapes["cities"] if name not in IN_TOKYO]
assert land and cities, "元の図から形を拾えなかった"

halo = f'paint-order="stroke" stroke="{SEA}" stroke-width="3" stroke-linejoin="round"'
out = []
w = out.append

w(f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {W} {H}" width="{W}" height="{H}" role="img" '
  'aria-label="TOKYO MX の放送エリア図。東京都と、東京スカイツリーから半径50kmの範囲">')
w("""  <!-- 放送エリア図。**県境は実データから起こしている**
       （国土数値情報（国土交通省）の行政区域データ。dataofjapan/land 経由で取得し、
       Ramer–Douglas–Peucker で 0.7px まで間引いた）。
       県境の形は tools/area-map/makemap.py、見た目は tools/area-map/restyle.py で作っている。
       投影は正距円筒。経度に cos(35.71度) を掛けてあるので「円＝送信所から等距離」として扱える。
       中心は東京スカイツリー（北緯35.7101/東経139.8107）、縮尺は 1km = 3.953px -->""")
w(f"""  <style>
    .wave {{ transform-box: view-box; transform-origin: {CX}px {CY}px; animation: wave 5.4s cubic-bezier(.2,.6,.3,1) infinite; opacity: 0; }}
    .wave--2 {{ animation-delay: 1.8s; }}
    .wave--3 {{ animation-delay: 3.6s; }}
    @keyframes wave {{
      0% {{ transform: scale(.03); opacity: .75; }}
      100% {{ transform: scale(1); opacity: 0; }}
    }}
    @media (prefers-reduced-motion: reduce) {{ .wave {{ animation: none; }} }}
  </style>""")
w(f"""  <defs>
    <clipPath id="frame"><rect x="0" y="0" width="{W}" height="{H}" rx="6"/></clipPath>
    <clipPath id="reachClip"><circle cx="{CX}" cy="{CY}" r="{R50:.1f}"/></clipPath>
    <radialGradient id="seaGlow" cx="{CX / W:.4f}" cy="{CY / H:.4f}" r="{R50 * 1.25 / W:.4f}" gradientUnits="objectBoundingBox">
      <stop offset="0" stop-color="#1b2a4a"/>
      <stop offset="1" stop-color="{SEA}"/>
    </radialGradient>
    <linearGradient id="tokyoFill" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0" stop-color="#f3e2b0"/>
      <stop offset=".55" stop-color="#d4b060"/>
      <stop offset="1" stop-color="#b48a3c"/>
    </linearGradient>
  </defs>""")

w('  <g clip-path="url(#frame)">')
# 海
w(f'    <rect width="{W}" height="{H}" fill="{SEA}"/>')
w(f'    <rect width="{W}" height="{H}" fill="url(#seaGlow)"/>')

# 経緯線（0.25度ごと）
w('    <g stroke="rgba(227,201,140,.07)" stroke-width="1">')
lon = 139.0
while lon <= 140.76:
    x = CX + (lon - LON0) * PX_LON
    if 0 < x < W:
        w(f'      <line x1="{x:.1f}" y1="0" x2="{x:.1f}" y2="{H}"/>')
    lon += 0.25
lat = 35.0
while lat <= 36.3:
    y = CY - (lat - LAT0) * PX_LAT
    if 0 < y < H:
        w(f'      <line x1="0" y1="{y:.1f}" x2="{W}" y2="{y:.1f}"/>')
    lat += 0.25
w("    </g>")

# 陸
d_land = " ".join(land)
w(f'    <path d="{d_land}" fill="{LAND}" stroke="rgba(255,255,255,.2)" stroke-width=".8" stroke-linejoin="round"/>')
# 受信エリアの中の陸だけを一段明るい紺にして浮かせる
# （金を薄く重ねると紺と混ざって濁った茶色になるので、金は東京都と輪だけに使う）
w(f'    <g clip-path="url(#reachClip)">')
w(f'      <path d="{d_land}" fill="{LAND_IN}" stroke="rgba(227,201,140,.4)" stroke-width=".8" stroke-linejoin="round"/>')
w("    </g>")

# 東京都（放送対象地域）
w(f'    <path d="{tokyo}" fill="url(#tokyoFill)" stroke="{CREAM}" stroke-opacity=".7" stroke-width="1" stroke-linejoin="round"/>')

# 距離の輪（10〜40kmは細く、50kmは金の破線）
w(f'    <g fill="none" stroke="{GOLD}">')
for km in (10, 20, 30, 40):
    w(f'      <circle cx="{CX}" cy="{CY}" r="{km * KM:.1f}" stroke-opacity=".22" stroke-width=".8"/>')
w(f'      <circle cx="{CX}" cy="{CY}" r="{R50:.1f}" stroke-width="1.6" stroke-dasharray="8 6"/>')
w("    </g>")

# 電波の輪（スカイツリーから広がる）
for k in (1, 2, 3):
    w(f'    <circle class="wave wave--{k}" cx="{CX}" cy="{CY}" r="{R50:.1f}" fill="none" stroke="{GOLD_LIGHT}" stroke-width="1.4"/>')

# 距離の目盛り（真下の東京湾の上に置く）
w(f'    <g font-family="{NUM}" font-size="9.5" fill="{GOLD_LIGHT}" letter-spacing=".5" {halo}>')
for km in (10, 20, 30, 40):
    w(f'      <text x="{CX + 14:.1f}" y="{CY + km * KM - 3:.1f}" opacity=".75">{km}</text>')
w(f'      <text x="{CX + 14:.1f}" y="{CY + R50 - 3:.1f}" font-weight="700">50km</text>')
w("    </g>")

# 県名
prefs = [(173, 134, "埼玉", "SAITAMA"), (460, 362, "千葉", "CHIBA"), (215, 402, "神奈川", "KANAGAWA"), (560, 70, "茨城", "IBARAKI")]
w(f'    <g text-anchor="middle" {halo}>')
for x, y, ja, en in prefs:
    w(f'      <text x="{x}" y="{y}" font-family="{FONT}" font-size="12" letter-spacing="3" fill="rgba(227,201,140,.7)">{ja}</text>')
    w(f'      <text x="{x}" y="{y + 13}" font-family="{NUM}" font-size="7.5" letter-spacing="2.5" fill="rgba(227,201,140,.5)">{en}</text>')
w("    </g>")

# 東京都の名前（西側の空いたところへ）
w(f'    <text x="104" y="203" text-anchor="middle" font-family="{FONT}" font-size="16" font-weight="700" letter-spacing="4" fill="#1a1408">東京都</text>')
w(f'    <text x="104" y="217" text-anchor="middle" font-family="{NUM}" font-size="8" letter-spacing="3" fill="rgba(26,20,8,.7)">TOKYO</text>')

# 市の点
w(f'    <g font-family="{FONT}" font-size="10.5" letter-spacing=".8">')
for x, y, name in cities:
    w(f'      <circle cx="{x}" cy="{y}" r="2.2" fill="{CREAM}"/>')
    w(f'      <text x="{x + 5.5:.1f}" y="{y + 3.6:.1f}" fill="rgba(255,255,255,.82)" {halo}>{name}</text>')
w("    </g>")

# スカイツリー（送信所）
w(f'    <circle cx="{CX}" cy="{CY}" r="16" fill="{GOLD_LIGHT}" opacity=".18"/>')
w(f'    <circle cx="{CX}" cy="{CY}" r="10" fill="none" stroke="{CREAM}" stroke-width="1.2" opacity=".75"/>')
w(f'    <path d="{tower}" fill="{CREAM}"/>')
w(f'    <g font-family="{FONT}" {halo}>')
w(f'      <text x="356" y="207" font-size="12.5" font-weight="700" letter-spacing="1.5" fill="#fff">東京スカイツリー</text>')
w(f'      <text x="356" y="222" font-size="10.5" letter-spacing="1.5" fill="{GOLD_LIGHT}">送信所・地上デジタル 9ch</text>')
w("    </g>")

# 凡例（左下）
w(f'    <g {halo}>')
w(f'      <line x1="22" y1="404" x2="22" y2="468" stroke="{GOLD}" stroke-width="1.5"/>')
w(f'      <text x="32" y="414" font-family="{NUM}" font-size="9" letter-spacing="3" fill="{GOLD_LIGHT}">BROADCAST AREA</text>')
w(f'      <text x="32" y="432" font-family="{FONT}" font-size="11" letter-spacing="1.5" fill="rgba(255,255,255,.85)">東京スカイツリーから半径</text>')
w(f'      <text x="31" y="466" font-family="{NUM}" font-size="34" font-weight="700" letter-spacing="1" fill="#fff">50<tspan font-size="16" dx="3" fill="{GOLD_LIGHT}">km</tspan></text>')
w("    </g>")

# 縮尺（右下）
sx, sy = W - 30 - 20 * KM, H - 26
w(f'    <g stroke="{GOLD_LIGHT}" stroke-width="1.2" fill="none">')
w(f'      <path d="M{sx:.1f} {sy - 5} V{sy} H{sx + 20 * KM:.1f} V{sy - 5} M{sx + 10 * KM:.1f} {sy} V{sy - 3.5}"/>')
w("    </g>")
w(f'    <g font-family="{NUM}" font-size="8.5" fill="{GOLD_LIGHT}" text-anchor="middle" letter-spacing=".5">')
for km in (0, 10, 20):
    label = "20km" if km == 20 else str(km)
    w(f'      <text x="{sx + km * KM:.1f}" y="{sy - 9}">{label}</text>')
w("    </g>")

# 方位（右上）
nx, ny = W - 34, 34
w(f'    <g fill="{GOLD_LIGHT}">')
w(f'      <path d="M{nx} {ny - 14} L{nx + 6} {ny + 4} L{nx} {ny} L{nx - 6} {ny + 4} Z"/>')
w(f'      <text x="{nx}" y="{ny + 19}" text-anchor="middle" font-family="{NUM}" font-size="10" font-weight="700" letter-spacing="1">N</text>')
w("    </g>")

w("  </g>")
# 枠
w(f'  <rect x=".5" y=".5" width="{W - 1}" height="{H - 1}" rx="6" fill="none" stroke="{GOLD}" stroke-opacity=".55"/>')
w("</svg>")

SVG.write_text("\n".join(out) + "\n", encoding="utf-8")
print(f"{SVG.relative_to(ROOT)} を書き出しました（市 {len(cities)} 件、陸 {len(land)} 面）")
