#!/usr/bin/env python3
"""放送エリア図（assets/img/area-map.svg）の見た目を組み直す

県境の形は makemap.py で起こした今の area-map.svg からそのまま引き継ぎ、
見た目（海と陸の塗り分け、受信エリアの塗り、50kmの円、文字、凡例、縮尺）だけを作り直す。
新聞やテレビ局の実際のエリア図に寄せ、光・グラデーション・動き・英字の飾りは使わない（使うと作り物っぽく見える）。
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
# 海はサイトの背景（foundation.css の --c-indigo-deep）、陸はそれより一段明るい紺。カードの地も同じ紺
SEA = "#0e1932"
LAND = "#17264a"
REACH = "#2a3a62"  # 受信エリアの陸（さらに一段明るい紺）
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
  'aria-label="TOKYO MX の放送エリア図。放送対象地域の東京都と、東京スカイツリーから約50kmの受信エリアの目安">')
w("""  <!-- 放送エリア図。**県境は実データから起こしている**
       （国土数値情報（国土交通省）の行政区域データ。dataofjapan/land 経由で取得し、
       Ramer–Douglas–Peucker で 0.7px まで間引いた）。
       県境の形は tools/area-map/makemap.py、見た目は tools/area-map/restyle.py で作っている。
       投影は正距円筒。経度に cos(35.71度) を掛けてあるので「円＝送信所から等距離」として扱える。
       中心は東京スカイツリー（北緯35.7101/東経139.8107）、縮尺は 1km = 3.953px -->""")
w(f"""  <defs>
    <clipPath id="frame"><rect width="{W}" height="{H}"/></clipPath>
    <clipPath id="reachClip"><circle cx="{CX}" cy="{CY}" r="{R50:.1f}"/></clipPath>
  </defs>""")

w('  <g clip-path="url(#frame)">')
w(f'    <rect width="{W}" height="{H}" fill="{SEA}"/>')

# 陸（平らに塗る）
d_land = " ".join(land)
w(f'    <path d="{d_land}" fill="{LAND}" stroke="rgba(255,255,255,.28)" stroke-width=".7" stroke-linejoin="round"/>')
# 受信エリアの目安：円の中の陸を一段明るい焦げ茶で平らに塗る（斜線は作り物っぽく見えるので使わない）
w(f'    <path d="{d_land}" fill="{REACH}" stroke="rgba(255,255,255,.28)" stroke-width=".7" stroke-linejoin="round" clip-path="url(#reachClip)"/>')
# 放送対象地域：東京都（金の単色）
w(f'    <path d="{tokyo}" fill="{GOLD}" stroke="{GOLD_LIGHT}" stroke-width=".8" stroke-linejoin="round"/>')
# 50kmの円（細い実線1本）
w(f'    <circle cx="{CX}" cy="{CY}" r="{R50:.1f}" fill="none" stroke="{GOLD_LIGHT}" stroke-width="1"/>')
# 円の上に「約50km」（右上、線を抜いて置く）
ang = math.radians(-38)
lx, ly = CX + R50 * math.cos(ang), CY + R50 * math.sin(ang)
w(f'    <text x="{lx:.1f}" y="{ly + 4:.1f}" text-anchor="middle" font-family="{FONT}" font-size="11" fill="{GOLD_LIGHT}" '
  f'paint-order="stroke" stroke="{SEA}" stroke-width="6" stroke-linejoin="round">約50km</text>')

# 県名（日本語だけ。控えめに）
prefs = [(173, 134, "埼玉県"), (468, 368, "千葉県"), (215, 402, "神奈川県"), (560, 70, "茨城県")]
w(f'    <g font-family="{FONT}" font-size="11.5" letter-spacing="2" fill="rgba(255,255,255,.42)" text-anchor="middle" {halo}>')
for x, y, ja in prefs:
    w(f'      <text x="{x}" y="{y}">{ja}</text>')
w("    </g>")

# 東京都の名前
w(f'    <text x="104" y="207" text-anchor="middle" font-family="{FONT}" font-size="14" font-weight="700" letter-spacing="3" fill="{SEA}">東京都</text>')

# 市の点
w(f'    <g font-family="{FONT}" font-size="10" letter-spacing=".5">')
for x, y, name in cities:
    w(f'      <circle cx="{x}" cy="{y}" r="1.9" fill="#fff"/>')
    w(f'      <text x="{x + 5:.1f}" y="{y + 3.5:.1f}" fill="rgba(255,255,255,.78)" {halo}>{name}</text>')
w("    </g>")

# 送信所（東京スカイツリー）
w(f'    <path d="{tower}" fill="#fff"/>')
w(f'    <g font-family="{FONT}" {halo}>')
w(f'      <text x="354" y="210" font-size="11.5" font-weight="700" letter-spacing="1" fill="#fff">東京スカイツリー</text>')
w(f'      <text x="354" y="224" font-size="9.5" letter-spacing="1" fill="rgba(255,255,255,.7)">送信所（地上デジタル 9ch）</text>')
w("    </g>")

# 凡例（左下）
lx0, ly0 = 18, 410
w(f'    <rect x="{lx0}" y="{ly0}" width="210" height="62" fill="{SEA}" fill-opacity=".86" stroke="rgba(255,255,255,.18)" stroke-width=".8"/>')
w(f'    <rect x="{lx0 + 12}" y="{ly0 + 13}" width="16" height="11" fill="{REACH}" stroke="rgba(255,255,255,.3)" stroke-width=".6"/>')
w(f'    <rect x="{lx0 + 12}" y="{ly0 + 37}" width="16" height="11" fill="{GOLD}" stroke="{GOLD_LIGHT}" stroke-width=".6"/>')
w(f'    <g font-family="{FONT}" font-size="10" fill="rgba(255,255,255,.85)" letter-spacing=".5">')
w(f'      <text x="{lx0 + 36}" y="{ly0 + 22}">受信エリアの目安（送信所から約50km）</text>')
w(f'      <text x="{lx0 + 36}" y="{ly0 + 46}">放送対象地域（東京都）</text>')
w("    </g>")

# 縮尺（右下）
sx, sy = W - 24 - 20 * KM, H - 22
w(f'    <path d="M{sx:.1f} {sy - 4} V{sy} H{sx + 20 * KM:.1f} V{sy - 4} M{sx + 10 * KM:.1f} {sy} V{sy - 3}" fill="none" stroke="rgba(255,255,255,.7)" stroke-width="1"/>')
w(f'    <g font-family="{NUM}" font-size="8.5" fill="rgba(255,255,255,.7)" text-anchor="middle">')
for km in (0, 10, 20):
    w(f'      <text x="{sx + km * KM:.1f}" y="{sy - 8}">{"20km" if km == 20 else km}</text>')
w("    </g>")

w("  </g>")
w(f'  <rect x=".5" y=".5" width="{W - 1}" height="{H - 1}" fill="none" stroke="rgba(255,255,255,.18)"/>')
w("</svg>")

SVG.write_text("\n".join(out) + "\n", encoding="utf-8")
print(f"{SVG.relative_to(ROOT)} を書き出しました（市 {len(cities)} 件、陸 {len(land)} 面）")
