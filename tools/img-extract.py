import sys, re, base64, os
s = sys.stdin.read()
m = re.search(r'<pre id="out">(.*?)</pre>', s, re.S)
t = m.group(1)
if not t.startswith('OK'):
    print(t[:300]); raise SystemExit
tot = 0
for line in t.split('\n')[1:]:
    if not line.strip(): continue
    name, size, url = line.split('\t')
    if size == 'ERROR':
        print('ERROR', name); continue
    b = base64.b64decode(url.split(',', 1)[1])
    p = os.path.join('../assets/img', name)
    open(p, 'wb').write(b)
    old = os.path.getsize(os.path.join('../assets/img', name.replace('.webp', '.png').replace('mark-icon.png', 'mark.png')))
    tot += len(b)
    print('%-28s %-10s %6.0fKB  (元 %6.0fKB)' % (name, size, len(b)/1024, old/1024))
print('合計 %.0fKB' % (tot/1024))
