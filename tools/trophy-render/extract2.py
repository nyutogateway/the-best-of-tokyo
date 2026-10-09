import sys, re, base64
s = sys.stdin.read()
m = re.search(r'<pre id="out">(.*?)</pre>', s, re.S)
t = m.group(1)
if not t.startswith('OK'):
    print(t[:400]); raise SystemExit
head, rest = t.split('\n', 1)
png, webp = rest.split('\n@@@\n')
print(head)
open('cup_small.png', 'wb').write(base64.b64decode(png.split(',', 1)[1]))
open('cup_small.webp', 'wb').write(base64.b64decode(webp.split(',', 1)[1]))
