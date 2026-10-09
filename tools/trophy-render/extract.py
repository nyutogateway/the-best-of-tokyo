import sys, re, base64
s = sys.stdin.read()
m = re.search(r'<pre id="out">(.*?)</pre>', s, re.S)
if not m:
    print('NO PRE'); raise SystemExit
t = m.group(1)
if not t.startswith('OK'):
    print(t[:1200]); raise SystemExit
head, url = t.split('\n', 1)
print(head)
b = base64.b64decode(url.split(',', 1)[1])
open('cup.png', 'wb').write(b)
print('png bytes', len(b))
