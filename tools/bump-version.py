#!/usr/bin/env python3
"""CSS/JS の更新番号（?v=）を中身のハッシュで振り直す。

**見た目を変えたら必ず実行すること。** 番号が変わらないと、ブラウザは前のCSSを
使い続けるので、直したはずの変更がまったく反映されない（この取り違えを何度かやった）。

    python3 tools/bump-version.py
"""
import glob, re, hashlib, os, sys

root = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
os.chdir(root)

h = hashlib.sha1()
for f in sorted(glob.glob('assets/css/*.css')) + sorted(glob.glob('assets/js/*.js')):
    h.update(open(f, 'rb').read())
ver = h.hexdigest()[:8]

files = ['index.html', 'contact.html', 'privacy.html'] + sorted(glob.glob('stories/*.html'))
changed, old = 0, None
for f in files:
    s = open(f, encoding='utf-8').read()
    m = re.search(r'\?v=([0-9a-z]+)', s)
    if m:
        old = m.group(1)
    s2 = re.sub(r'\?v=[0-9a-z]+', '?v=' + ver, s)
    if s2 != s:
        open(f, 'w', encoding='utf-8').write(s2)
        changed += 1
print('更新番号 %s → %s（%d ファイル）' % (old, ver, changed))
