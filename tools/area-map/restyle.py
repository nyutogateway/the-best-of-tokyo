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
CREAM = "#f3ead2"
# 陸はサイトの地と同じ紺（図が地に溶けるように）、海はそれより一段暗い紺
LAND = "#0e1932"
SEA = "#0a1226"
FONT = "'Shippori Mincho B1','Hiragino Mincho ProN','Yu Mincho',serif"
NUM = FONT

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

halo = f'paint-order="stroke" stroke="{LAND}" stroke-width="3.2" stroke-linejoin="round"'
out = []
w = out.append

# 日輪マーク（assets/img/mark.svg）の線を、送信所の印として小さく描く
MARK = (ROOT / "assets/img/mark.svg").read_text(encoding="utf-8")
mark_lines = re.findall(r"<line[^>]+/>", MARK)
pts = [tuple(map(float, m)) for m in re.findall(r'x1="([\d.]+)" y1="([\d.]+)"', MARK)]
mcx = sum(p[0] for p in pts) / len(pts)
mcy = sum(p[1] for p in pts) / len(pts)

# 載せる市は主なものだけ（多いと賑やかになり安っぽく見える）
KEEP = ["さいたま市", "川越市", "所沢市", "千葉市", "横浜市", "横須賀市", "木更津市", "つくば市", "成田市"]
cities = [c for c in cities if c[2] in KEEP]

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
d_land = " ".join(land)
# 陸：地と同じ紺。県境と海岸は金の髪の毛ほどの線
w(f'    <path d="{d_land}" fill="{LAND}"/>')
# 受信エリアの目安：円の中の陸だけ、金をごくうすく
w(f'    <path d="{d_land}" fill="{GOLD}" fill-opacity=".05" clip-path="url(#reachClip)"/>')
w(f'    <path d="{d_land}" fill="none" stroke="{GOLD}" stroke-opacity=".32" stroke-width=".6" stroke-linejoin="round"/>')
# 放送対象地域：東京都（金をうすく敷き、縁だけ金の実線）
w(f'    <path d="{tokyo}" fill="{GOLD}" fill-opacity=".24" stroke="{GOLD_LIGHT}" stroke-width=".9" stroke-linejoin="round"/>')
# 50kmの円＝レコードのいちばん外側のトラック（記事の「TRACK 01…」と同じ見立て）。
# 縁は金の細い実線、その内側に細い溝の同心円を幅およそ6kmぶん刻む（内側ほどうすく）
GROOVES = 14
w(f'    <g fill="none" stroke="{GOLD_LIGHT}" stroke-width=".45">')
for n in range(1, GROOVES + 1):
    r = R50 - n * 1.7
    w(f'      <circle cx="{CX}" cy="{CY}" r="{r:.1f}" stroke-opacity="{0.42 * (1 - n / (GROOVES + 2)):.2f}"/>')
w("    </g>")
w(f'    <circle cx="{CX}" cy="{CY}" r="{R50:.1f}" fill="none" stroke="{GOLD_LIGHT}" stroke-width=".9"/>')
# 円の真上に「半径 約50km」
w(f'    <text x="{CX}" y="{CY - R50 - 7:.1f}" text-anchor="middle" font-family="{FONT}" font-size="9.5" letter-spacing="2" fill="{GOLD_LIGHT}">半径 約50km</text>')

# 県名（明朝、字間を広く、うすく）
prefs = [(173, 134, "埼玉県"), (488, 380, "千葉県"), (215, 412, "神奈川県"), (575, 70, "茨城県")]
w(f'    <g font-family="{FONT}" font-size="12" letter-spacing="7" fill="#fff" fill-opacity=".34" text-anchor="middle">')
for x, y, ja in prefs:
    w(f'      <text x="{x}" y="{y}">{ja}</text>')
w("    </g>")

# 東京都の名前
w(f'    <text x="112" y="210" text-anchor="middle" font-family="{FONT}" font-size="15" font-weight="600" letter-spacing="9" fill="{GOLD_LIGHT}">東京都</text>')

# 市（小さな金の点と、小さな明朝）
w(f'    <g font-family="{FONT}" font-size="9.5" letter-spacing="1" fill="#fff" fill-opacity=".72">')
for x, y, name in cities:
    w(f'      <circle cx="{x}" cy="{y}" r="1.5" fill="{GOLD_LIGHT}" fill-opacity="1"/>')
    w(f'      <text x="{x + 5:.1f}" y="{y + 3.3:.1f}" {halo}>{name}</text>')
w("    </g>")

# 送信所（東京スカイツリー）：日輪マーク
sc = 0.06
w(f'    <g transform="translate({CX - mcx * sc:.2f} {CY - mcy * sc:.2f}) scale({sc})" fill="none" stroke="{GOLD_LIGHT}" stroke-width="16" stroke-linecap="round">')
for ln in mark_lines:
    w("      " + ln)
w("    </g>")
w(f'    <circle cx="{CX}" cy="{CY}" r="1.8" fill="{CREAM}"/>')
w(f'    <g font-family="{FONT}" {halo}>')
w(f'      <text x="{CX + 22}" y="{CY - 6}" font-size="12" font-weight="600" letter-spacing="2" fill="{CREAM}">東京スカイツリー</text>')
w(f'      <text x="{CX + 22}" y="{CY + 8}" font-size="8.5" letter-spacing="2" fill="{GOLD_LIGHT}">送信所・地上デジタル 9ch</text>')
w("    </g>")

# 凡例（左下。箱は付けない）
lx0, ly0 = 22, 444
w(f'    <g font-family="{FONT}" font-size="9" letter-spacing="1.5" fill="#fff" fill-opacity=".72">')
w(f'      <rect x="{lx0}" y="{ly0}" width="14" height="8" fill="{GOLD}" fill-opacity=".24" stroke="{GOLD_LIGHT}" stroke-width=".7"/>')
w(f'      <text x="{lx0 + 22}" y="{ly0 + 7.5}">放送対象地域（東京都）</text>')
w(f'      <path d="M{lx0} {ly0 + 23} h14 M{lx0} {ly0 + 25.5} h14 M{lx0} {ly0 + 28} h14" stroke="{GOLD_LIGHT}" stroke-width=".6"/>')
w(f'      <text x="{lx0 + 22}" y="{ly0 + 26.5}">受信エリアの目安（送信所から約50km）</text>')
w("    </g>")

# 縮尺（右下）
sx, sy = W - 26 - 20 * KM, H - 24
w(f'    <path d="M{sx:.1f} {sy - 3} V{sy} H{sx + 20 * KM:.1f} V{sy - 3}" fill="none" stroke="{GOLD_LIGHT}" stroke-opacity=".7" stroke-width=".7"/>')
w(f'    <text x="{sx + 10 * KM:.1f}" y="{sy - 7}" text-anchor="middle" font-family="{FONT}" font-size="8.5" letter-spacing="1" fill="{GOLD_LIGHT}" fill-opacity=".8">20km</text>')

w("  </g>")
w("</svg>")

SVG.write_text("\n".join(out) + "\n", encoding="utf-8")
print(f"{SVG.relative_to(ROOT)} を書き出しました（市 {len(cities)} 件、陸 {len(land)} 面）")
