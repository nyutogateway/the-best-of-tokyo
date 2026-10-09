# SVGの断面から、なめらかな回転断面（r, y）を作る
def cubic(p0, c1, c2, p3, t):
    u = 1 - t
    return tuple(u*u*u*a + 3*u*u*t*b + 3*u*t*t*c + t*t*t*d for a, b, c, d in zip(p0, c1, c2, p3))

def to_world(x, y):           # SVG座標 → (r, y)。足の底を y=0、軸を r=0 に
    return ((280.0 - x) / 100.0, (375.0 - y) / 100.0)

pts = []
# 器の外側（SVGの2本の三次曲線）
for t in [i / 26.0 for i in range(27)]:
    pts.append(to_world(*cubic((178, 104), (180, 146), (188, 196), (208, 238), t)))
for t in [i / 14.0 for i in range(1, 9)]:
    pts.append(to_world(*cubic((208, 238), (222, 268), (248, 290), (280, 290), t)))

# 脚：(r, y) で直接、Catmull-Rom でなめらかにする
key = [(0.230, 0.930), (0.168, 0.800), (0.176, 0.660), (0.290, 0.505),
       (0.176, 0.355), (0.160, 0.185), (0.300, 0.060), (0.560, 0.012), (0.578, 0.000)]
def catmull(p, n=4):
    out = []
    ext = [p[0]] + p + [p[-1]]
    for i in range(len(p) - 1):
        p0, p1, p2, p3 = ext[i], ext[i+1], ext[i+2], ext[i+3]
        for j in range(n):
            t = j / float(n)
            t2, t3 = t*t, t*t*t
            out.append(tuple(0.5 * ((2*a1) + (-a0 + a2)*t + (2*a0 - 5*a1 + 4*a2 - a3)*t2 + (-a0 + 3*a1 - 3*a2 + a3)*t3)
                             for a0, a1, a2, a3 in zip(p0, p1, p2, p3)))
    out.append(p[-1])
    return out
pts += catmull(key, 8)[1:]
pts.append((0.0, 0.0))        # 底（軸）
pts.append((0.0, 1.010))      # 器の内側の底（軸）
# 内側の壁 → 縁へ
inner = [(0.0, 1.010), (0.320, 1.110), (0.560, 1.330), (0.760, 1.780), (0.900, 2.300), (0.964, 2.710)]
ip = catmull(inner, 8)[1:]
ip = [(min(r, 0.964), min(y, 2.710)) for r, y in ip]
pts += ip

# 近すぎる点を間引く
clean = [pts[0]]
for p in pts[1:]:
    if (p[0] - clean[-1][0]) ** 2 + (p[1] - clean[-1][1]) ** 2 > 0.00025:
        clean.append(p)

def seg_int(a, b, c, d):
    def cr(o, p, q): return (p[0]-o[0])*(q[1]-o[1]) - (p[1]-o[1])*(q[0]-o[0])
    d1, d2, d3, d4 = cr(c, d, a), cr(c, d, b), cr(a, b, c), cr(a, b, d)
    return ((d1 > 0) != (d2 > 0)) and ((d3 > 0) != (d4 > 0))
bad = 0
n = len(clean)
for i in range(n):
    for j in range(i + 2, n):
        if i == 0 and j == n - 1: continue
        if seg_int(clean[i], clean[(i+1) % n], clean[j], clean[(j+1) % n]): bad += 1
print('count', len(clean), 'self-intersections', bad, 'min r', min(p[0] for p in clean))
body = ',\n  '.join('vec2(%.4f, %.4f)' % p for p in clean)
open('profile.glsl', 'w').write(
    'const int NV = %d;\nconst vec2 VERT[%d] = vec2[%d](\n  %s\n);\n' % (len(clean), len(clean), len(clean), body))
