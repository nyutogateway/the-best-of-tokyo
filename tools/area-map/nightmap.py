#!/usr/bin/env python3
"""放送エリア図（assets/img/area-map.svg）を「夜景の衛星写真」で作る

下に敷くのは NASA の夜間光画像 Black Marble（VIIRS、2016年。NASA の画像なのでパブリックドメイン）。
取得は NASA GIBS の WMS から、この図と同じ範囲（正距円筒＝EPSG:4326）で:

    curl -o tools/area-map/blackmarble.jpg "https://gibs.earthdata.nasa.gov/wms/epsg4326/best/wms.cgi?SERVICE=WMS&REQUEST=GetMap&VERSION=1.3.0&LAYERS=VIIRS_Black_Marble&STYLES=&CRS=EPSG:4326&BBOX=35.1533,138.8591,36.2669,140.7623&WIDTH=1360&HEIGHT=980&FORMAT=image/jpeg"

写真は明るさだけを使い、サイトの金で染めて紺の地に重ねる（SVG の filter）。
その上に、東京都の縁・50kmの円・県境（ごくうすく）・地名を細い線と明朝で載せる。
県境・東京都・市の位置は shapes.json（restyle.py と同じもの）から。

    python3 tools/area-map/nightmap.py

座標は makemap.py と同じ（中心＝東京スカイツリー (340, 245)、1km = 3.953px、経度1度 = 357.3px、緯度1度 = 440.0px）。
写真の範囲 BBOX はこの座標の枠（0〜680, 0〜490）をそのまま経緯度に直したもの。
"""
import base64
import json
import math
import pathlib
import re

HERE = pathlib.Path(__file__).resolve().parent
ROOT = HERE.parent.parent
SVG = ROOT / "assets/img/area-map.svg"

W, H = 680, 490
CX, CY = 340.0, 245.0
KM = 3.953
R50 = 50 * KM

GOLD = "#c9a45a"
GOLD_LIGHT = "#e3c98c"
CREAM = "#f3ead2"
NAVY = "#0e1932"
FONT = "'Shippori Mincho B1','Hiragino Mincho ProN','Yu Mincho',serif"

shapes = json.loads((HERE / "shapes.json").read_text(encoding="utf-8"))
land, tokyo = shapes["land"], shapes["tokyo"]
KEEP = ["さいたま市", "千葉市", "横浜市", "つくば市", "成田市", "横須賀市"]
cities = [c for c in shapes["cities"] if c[2] in KEEP]

photo = base64.b64encode((HERE / "blackmarble.jpg").read_bytes()).decode()

MARK = (ROOT / "assets/img/mark.svg").read_text(encoding="utf-8")
mark_lines = re.findall(r"<line[^>]+/>", MARK)
pts = [tuple(map(float, m)) for m in re.findall(r'x1="([\d.]+)" y1="([\d.]+)"', MARK)]
mcx = sum(p[0] for p in pts) / len(pts)
mcy = sum(p[1] for p in pts) / len(pts)

halo = f'paint-order="stroke" stroke="{NAVY}" stroke-opacity=".85" stroke-width="3.5" stroke-linejoin="round"'
out = []
w = out.append

w(f'<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 {W} {H}" width="{W}" height="{H}" role="img" '
  'aria-label="TOKYO MX の放送エリア図。首都圏の夜の明かりの上に、放送対象地域の東京都と、東京スカイツリーから約50kmの受信エリアの目安">')
w("""  <!-- 放送エリア図（夜景の衛星写真）。作り方は tools/area-map/nightmap.py。
       夜間光：NASA Black Marble（VIIRS、2016年。パブリックドメイン）。
       県境：国土数値情報（国土交通省）の行政区域データを加工 -->""")
w(f"""  <defs>
    <clipPath id="frame"><rect width="{W}" height="{H}"/></clipPath>
    <!-- 写真の明るさだけを取り出して色を付ける。
         東京都の外：青白くうすく（地の質感にとどめる）。東京都の中：金で光らせる（放送対象地域がひと目で分かるように） -->
    <filter id="dim" x="0" y="0" width="100%" height="100%" color-interpolation-filters="sRGB">
      <feGaussianBlur stdDeviation=".8"/>
      <feColorMatrix type="matrix" values="0 0 0 0 .72  0 0 0 0 .78  0 0 0 0 .92  .3 .59 .11 0 0"/>
      <feComponentTransfer><feFuncA type="gamma" amplitude=".3" exponent="1.6" offset="0"/></feComponentTransfer>
    </filter>
    <filter id="gold" x="0" y="0" width="100%" height="100%" color-interpolation-filters="sRGB">
      <feGaussianBlur stdDeviation=".6"/>
      <feColorMatrix type="matrix" values="0 0 0 0 .89  0 0 0 0 .76  0 0 0 0 .45  .3 .59 .11 0 0"/>
      <feComponentTransfer><feFuncA type="gamma" amplitude=".95" exponent="1.4" offset="0"/></feComponentTransfer>
    </filter>
    <clipPath id="tokyoClip"><path d="{tokyo}"/></clipPath>
  </defs>""")
w('  <g clip-path="url(#frame)">')
w(f'    <rect width="{W}" height="{H}" fill="{NAVY}"/>')
# 写真は1回だけ埋め込み、<use> で2回使う（ファイルを軽くするため）
w(f'    <defs><image id="night" width="{W}" height="{H}" preserveAspectRatio="none" '
  f'href="data:image/jpeg;base64,{photo}"/></defs>')
w(f'    <g filter="url(#dim)"><use href="#night"/></g>')
w(f'    <g clip-path="url(#tokyoClip)"><path d="{tokyo}" fill="{GOLD}" fill-opacity=".3"/>'
  f'<g filter="url(#gold)"><use href="#night"/></g></g>')
# 県境と海岸：ごくうすい線（場所の手がかりだけ）
w(f'    <path d="{" ".join(land)}" fill="none" stroke="#fff" stroke-opacity=".28" stroke-width=".6" stroke-linejoin="round"/>')
# 放送対象地域：東京都の縁を金の実線で
# 明かりの上でも読めるよう、下に紺の縁取りを敷く
w(f'    <path d="{tokyo}" fill="none" stroke="{NAVY}" stroke-opacity=".75" stroke-width="3.6" stroke-linejoin="round"/>')
w(f'    <path d="{tokyo}" fill="none" stroke="{GOLD_LIGHT}" stroke-width="1.3" stroke-linejoin="round"/>')
# 50kmの円
w(f'    <circle cx="{CX}" cy="{CY}" r="{R50:.1f}" fill="none" stroke="{NAVY}" stroke-opacity=".7" stroke-width="3"/>')
w(f'    <circle cx="{CX}" cy="{CY}" r="{R50:.1f}" fill="none" stroke="{CREAM}" stroke-width="1.1" stroke-dasharray="4 3"/>')
w(f'    <text x="{CX}" y="{CY - R50 - 7:.1f}" text-anchor="middle" font-family="{FONT}" font-size="11" letter-spacing="2" fill="{CREAM}" {halo}>半径 約50km</text>')

# 県名
prefs = [(173, 134, "埼玉県"), (500, 392, "千葉県"), (200, 420, "神奈川県"), (575, 70, "茨城県")]
w(f'    <g font-family="{FONT}" font-size="13" letter-spacing="7" fill="#fff" fill-opacity=".7" text-anchor="middle" {halo}>')
for x, y, ja in prefs:
    w(f'      <text x="{x}" y="{y}">{ja}</text>')
w("    </g>")
w(f'    <text x="112" y="210" text-anchor="middle" font-family="{FONT}" font-size="17" font-weight="700" letter-spacing="9" fill="{GOLD_LIGHT}" {halo}>東京都</text>')

# 市
w(f'    <g font-family="{FONT}" font-size="11" letter-spacing="1" fill="#fff">')
for x, y, name in cities:
    w(f'      <circle cx="{x}" cy="{y}" r="1.4" fill="{CREAM}"/>')
    w(f'      <text x="{x + 5:.1f}" y="{y + 3.3:.1f}" {halo}>{name}</text>')
w("    </g>")

# 送信所（東京スカイツリー）：日輪マーク
sc = 0.06
w(f'    <g transform="translate({CX - mcx * sc:.2f} {CY - mcy * sc:.2f}) scale({sc})" fill="none" stroke="{CREAM}" stroke-width="16" stroke-linecap="round">')
for ln in mark_lines:
    w("      " + ln)
w("    </g>")
w(f'    <g font-family="{FONT}" {halo}>')
w(f'      <text x="{CX + 22}" y="{CY - 6}" font-size="13.5" font-weight="700" letter-spacing="2" fill="{CREAM}">東京スカイツリー</text>')
w(f'      <text x="{CX + 22}" y="{CY + 10}" font-size="10" letter-spacing="2" fill="{GOLD_LIGHT}">送信所・地上デジタル 9ch</text>')
w("    </g>")

# 凡例（左下）
lx0, ly0 = 22, 436
w(f'    <g font-family="{FONT}" font-size="10.5" letter-spacing="1.5" fill="#fff" {halo}>')
w(f'      <line x1="{lx0}" y1="{ly0 + 4}" x2="{lx0 + 16}" y2="{ly0 + 4}" stroke="{GOLD_LIGHT}" stroke-width="1.3"/>')
w(f'      <text x="{lx0 + 24}" y="{ly0 + 7.5}">放送対象地域（東京都）</text>')
w(f'      <line x1="{lx0}" y1="{ly0 + 23}" x2="{lx0 + 16}" y2="{ly0 + 23}" stroke="{CREAM}" stroke-width=".8" stroke-dasharray="2 3"/>')
w(f'      <text x="{lx0 + 24}" y="{ly0 + 26.5}">受信エリアの目安（送信所から約50km）</text>')
w("    </g>")
w(f'    <text x="{W - 16}" y="{H - 12}" text-anchor="end" font-family="{FONT}" font-size="7" letter-spacing=".5" fill="#fff" fill-opacity=".45">夜間光：NASA Black Marble</text>')
w("  </g>")
w("</svg>")

SVG.write_text("\n".join(out) + "\n", encoding="utf-8")
print(f"{SVG.relative_to(ROOT)} を書き出しました（{SVG.stat().st_size // 1024}KB）")
