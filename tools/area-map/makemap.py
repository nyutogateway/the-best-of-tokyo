# 国土数値情報（国土交通省）由来の県境データから、放送エリア図のSVGを起こす。
# 投影は正距円筒。経度に cos(基準緯度) を掛けるので、この縮尺では円＝等距離として扱える。
import json, math

SRC = 'japan.geojson'
PREF = ['東京都', '埼玉県', '千葉県', '神奈川県', '茨城県', '栃木県', '群馬県', '山梨県', '静岡県']
TREE = (35.7101, 139.8107)          # 東京スカイツリー
LAT0 = TREE[0]
KM_PER_DEG = 111.32                  # 緯度1度の距離
RADIUS_KM = 50

# 図の範囲（スカイツリーから何km見せるか）
HALF_W_KM, HALF_H_KM = 86, 62
W, H = 680, int(680 * HALF_H_KM / HALF_W_KM)
SCALE = (W / 2) / HALF_W_KM          # px per km

def to_xy(lon, lat):
    dx = (lon - TREE[1]) * math.cos(math.radians(LAT0)) * KM_PER_DEG
    dy = (lat - TREE[0]) * KM_PER_DEG
    return (W / 2 + dx * SCALE, H / 2 - dy * SCALE)

def rdp(pts, eps):
    if len(pts) < 3: return pts
    def d(p, a, b):
        if a == b: return math.hypot(p[0]-a[0], p[1]-a[1])
        t = max(0, min(1, ((p[0]-a[0])*(b[0]-a[0]) + (p[1]-a[1])*(b[1]-a[1])) / ((b[0]-a[0])**2 + (b[1]-a[1])**2)))
        return math.hypot(p[0]-(a[0]+t*(b[0]-a[0])), p[1]-(a[1]+t*(b[1]-a[1])))
    dmax, idx = 0, 0
    for i in range(1, len(pts)-1):
        dd = d(pts[i], pts[0], pts[-1])
        if dd > dmax: dmax, idx = dd, i
    if dmax > eps:
        return rdp(pts[:idx+1], eps)[:-1] + rdp(pts[idx:], eps)
    return [pts[0], pts[-1]]

d = json.load(open(SRC, encoding='utf-8'))
paths = {}
for f in d['features']:
    nm = f['properties']['nam_ja']
    if nm not in PREF: continue
    g = f['geometry']
    polys = g['coordinates'] if g['type'] == 'MultiPolygon' else [g['coordinates']]
    out = []
    for poly in polys:
        for ring in poly:
            pts = [to_xy(lon, lat) for lon, lat in ring]
            # 画面の外にしか無い島などは捨てる
            if not any(-60 <= x <= W + 60 and -60 <= y <= H + 60 for x, y in pts): continue
            pts = rdp(pts, 0.7)
            if len(pts) < 4: continue
            # 面積の小さい島は落とす
            a = abs(sum(pts[i][0]*pts[i-1][1] - pts[i-1][0]*pts[i][1] for i in range(len(pts)))) / 2
            if a < 12: continue
            out.append('M' + ' '.join('%.1f %.1f' % p for p in pts) + 'Z')
    if out: paths[nm] = ' '.join(out)

print('W,H =', W, H, ' 半径50km =', round(RADIUS_KM * SCALE, 1), 'px')
for k, v in paths.items():
    print(k, len(v), '文字')
json.dump({'paths': paths, 'W': W, 'H': H, 'scale': SCALE}, open('paths.json', 'w'))


# ---- 主な市（いただいたエリア図に載っているもの）----
CITIES = [
    ('古河市', 36.178, 139.756, 'e'), ('行田市', 36.139, 139.456, 'e'),
    ('つくば市', 36.084, 140.077, 'e'), ('東松山市', 36.042, 139.400, 'e'),
    ('野田市', 35.955, 139.875, 'e'), ('川越市', 35.925, 139.485, 'e'),
    ('越谷市', 35.891, 139.791, 'e'), ('さいたま市', 35.861, 139.646, 'e'),
    ('飯能市', 35.856, 139.327, 'e'), ('所沢市', 35.799, 139.469, 'e'),
    ('青梅市', 35.788, 139.276, 'e'), ('成田市', 35.776, 140.318, 'e'),
    ('市川市', 35.722, 139.931, 'e'), ('立川市', 35.714, 139.407, 'e'),
    ('八王子市', 35.666, 139.316, 'e'), ('八街市', 35.666, 140.318, 'e'),
    ('千葉市', 35.607, 140.106, 'e'), ('横浜市', 35.444, 139.638, 'e'),
    ('厚木市', 35.443, 139.363, 'e'), ('木更津市', 35.376, 139.917, 'e'),
    ('横須賀市', 35.281, 139.672, 'e'),
]

# ---- SVG を書き出す ----
import re
def centroid(dstr):
    # いちばん大きい環の重心
    best, bestarea = None, 0
    for seg in dstr.split('Z'):
        seg = seg.strip().lstrip('M')
        if not seg: continue
        nums = [float(x) for x in seg.split()]
        pts = list(zip(nums[0::2], nums[1::2]))
        if len(pts) < 3: continue
        a = abs(sum(pts[i][0]*pts[i-1][1] - pts[i-1][0]*pts[i][1] for i in range(len(pts)))) / 2
        if a > bestarea:
            bestarea = a
            cx = sum(p[0] for p in pts) / len(pts)
            cy = sum(p[1] for p in pts) / len(pts)
            best = (cx, cy)
    return best

LAB = {'東京都': '東京都', '埼玉県': '埼玉', '千葉県': '千葉', '神奈川県': '神奈川',
       '茨城県': '茨城', '栃木県': '栃木', '群馬県': '群馬', '山梨県': '山梨', '静岡県': '静岡'}
cx, cy = W / 2, H / 2
R = RADIUS_KM * SCALE
o = []
o.append('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %d %d" width="%d" height="%d" role="img" aria-label="TOKYO MX の放送エリア図。東京都と、東京スカイツリーから半径50kmの範囲">' % (W, H, W, H))
o.append('''  <!-- 放送エリア図。**県境は実データから起こしている**
       （国土数値情報（国土交通省）の行政区域データ。dataofjapan/land 経由で取得し、
       Ramer–Douglas–Peucker で 0.7px まで間引いた）。
       投影は正距円筒。経度に cos(35.71度) を掛けてあるので、この縮尺では
       「円＝送信所から等距離」として扱える。
       中心は東京スカイツリー（北緯35.7101/東経139.8107）、縮尺は 1km = %.3fpx。
       作り直すときは tools/trophy-render の隣にある makemap.py を参照 -->''' % SCALE)
o.append('''  <defs>
    <radialGradient id="reach" cx=".5" cy=".5" r=".5">
      <stop offset="0" stop-color="#c9a45a" stop-opacity=".2"/>
      <stop offset=".72" stop-color="#c9a45a" stop-opacity=".07"/>
      <stop offset="1" stop-color="#c9a45a" stop-opacity="0"/>
    </radialGradient>
    <linearGradient id="tokyoFill" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0" stop-color="#e8d2a0"/>
      <stop offset="1" stop-color="#c09645"/>
    </linearGradient>
  </defs>''')
o.append('  <g fill="rgba(255,255,255,.05)" stroke="rgba(255,255,255,.18)" stroke-width="0.8" stroke-linejoin="round">')
for nm, dstr in paths.items():
    if nm == '東京都': continue
    o.append('    <path d="%s"/>' % dstr)
o.append('  </g>')
o.append('  <circle cx="%.1f" cy="%.1f" r="%.1f" fill="url(#reach)"/>' % (cx, cy, R))
o.append('  <path d="%s" fill="url(#tokyoFill)" opacity=".95" stroke="rgba(255,246,220,.5)" stroke-width="0.8" stroke-linejoin="round"/>' % paths['東京都'])
o.append('  <circle cx="%.1f" cy="%.1f" r="%.1f" fill="none" stroke="#c9a45a" stroke-width="1.5" stroke-dasharray="7 7" opacity=".9"/>' % (cx, cy, R))
# 送信所
o.append('  <path d="M%.1f %.1f l9 28 h-18 z" fill="#fff6dc"/>' % (cx, cy - 28))
o.append('  <circle cx="%.1f" cy="%.1f" r="5" fill="#fff6dc"/>' % (cx, cy))
o.append('  <circle cx="%.1f" cy="%.1f" r="11" fill="none" stroke="#fff6dc" stroke-width="1.2" opacity=".6"/>' % (cx, cy))
# ラベル
o.append('  <g font-family="sans-serif" letter-spacing="2">')
for nm, dstr in paths.items():
    c = centroid(dstr)
    if not c: continue
    x, y = c
    if not (30 <= x <= W - 30 and 24 <= y <= H - 24): continue
    if nm == '東京都':
        o.append('    <text x="%.0f" y="%.0f" fill="#121c2e" font-size="16" font-weight="700" letter-spacing="4" text-anchor="middle">東京都</text>' % (x - 24, y + 5))
    else:
        o.append('    <text x="%.0f" y="%.0f" fill="rgba(255,255,255,.45)" font-size="12.5" text-anchor="middle">%s</text>' % (x, y, LAB[nm]))
o.append('  </g>')
# 主な市
o.append('  <g font-family="sans-serif" font-size="10.5" letter-spacing="1">')
for nm, la, lo, side in CITIES:
    x, y = to_xy(lo, la)
    # **受信エリアの外にある市は出さない**（エリア図なので、入っていない街を載せる意味がない）
    if math.hypot(x - W / 2, y - H / 2) > RADIUS_KM * SCALE: continue
    if not (8 <= x <= W - 8 and 10 <= y <= H - 10): continue
    o.append('    <circle cx="%.1f" cy="%.1f" r="2.4" fill="rgba(255,255,255,.72)"/>' % (x, y))
    o.append('    <text x="%.1f" y="%.1f" fill="rgba(255,255,255,.62)">%s</text>' % (x + 5.5, y + 3.6, nm))
o.append('  </g>')
o.append('  <text x="%.0f" y="%.0f" fill="rgba(255,255,255,.9)" font-family="sans-serif" font-size="12" letter-spacing="1.5" font-weight="700">東京スカイツリー</text>' % (cx + 14, cy - 36))
o.append('  <text x="%.0f" y="%.0f" fill="rgba(255,255,255,.72)" font-family="sans-serif" font-size="11" letter-spacing="1.5">地上デジタル 9ch</text>' % (cx + 14, cy - 21))
# 左下のバッジ（いただいた図と同じ内容）
bx, by = 122, H - 58
o.append('  <g>')
o.append('    <rect x="%.0f" y="%.0f" width="212" height="78" rx="39" fill="rgba(14,25,50,.72)" stroke="#c9a45a" stroke-width="1.2"/>' % (bx - 106, by - 39))
o.append('    <text x="%.0f" y="%.0f" fill="#e3c98c" font-family="sans-serif" font-size="10.5" letter-spacing="1" text-anchor="middle">地上デジタル 放送9ch 受信エリア</text>' % (bx, by - 14))
o.append('    <text x="%.0f" y="%.0f" fill="rgba(255,255,255,.78)" font-family="sans-serif" font-size="11" letter-spacing="1.5" text-anchor="middle">東京スカイツリーから</text>' % (bx, by + 4))
o.append('    <text x="%.0f" y="%.0f" fill="#fff" font-family="sans-serif" font-size="19" font-weight="700" letter-spacing="1" text-anchor="middle">50km</text>' % (bx, by + 26))
o.append('  </g>')
o.append('</svg>')
open('area-map.svg', 'w', encoding='utf-8').write('\n'.join(o) + '\n')
print('書き出し', len('\n'.join(o)), '文字')
