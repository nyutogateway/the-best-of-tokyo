#!/usr/bin/env python3
"""サーバーに上げるぶんだけを dist/ にまとめる

いまのリポジトリには、差し替え前の実写真・実ロゴ（約22MB）が参照されないまま残っている。
そのまま上げるとURLを直接叩けば見えてしまうので、**HTML・CSSから実際に参照している
ファイルだけ**を集めて dist/ を作る。dist/ の中身をそのままサーバーの公開フォルダに置けばよい。

使い方:  python3 tools/build-dist.py
"""
import glob
import os
import pathlib
import re
import shutil

ROOT = pathlib.Path(__file__).resolve().parent.parent
DIST = ROOT / "dist"
SITE = "https://the-best-of-tokyo.com/"

# HTML 以外で、そのまま入れるもの
EXTRA = ["robots.txt", "sitemap.xml"]


def html_files():
    out = ["index.html", "contact.html", "privacy.html"]
    out += sorted(
        str(pathlib.Path(p).relative_to(ROOT)) for p in glob.glob(str(ROOT / "stories" / "*.html"))
    )
    return [f for f in out if (ROOT / f).exists()]


def collect():
    """参照されているファイルを集める（CSS の url() と、本番URLでの絶対指定も追う）"""
    used = set()
    queue = []

    for f in html_files():
        used.add(f)
        queue.append(f)

    while queue:
        rel = queue.pop()
        path = ROOT / rel
        if path.suffix.lower() not in (".html", ".css", ".js", ".svg"):
            continue
        base = pathlib.PurePosixPath(rel).parent
        text = path.read_text(encoding="utf-8", errors="ignore")

        found = re.findall(r'(?:src|href)="([^"]+)"', text)
        found += re.findall(r'url\("?([^")]+)"?\)', text)
        # OGP画像や構造化データのように、属性名が src/href でないものもある。
        # 本番URLで書かれたものは、どこに出てきても拾う
        found += [SITE + m for m in re.findall(re.escape(SITE) + r'([A-Za-z0-9_\-./]+)', text)]

        for u in found:
            if u.startswith("data:") or u.startswith("#") or u.startswith("mailto:"):
                continue
            if u.startswith(SITE):          # 本番URLでの絶対指定（OGP画像など）
                target = u[len(SITE):]
            elif u.startswith(("http://", "https://")):
                continue                     # 外部（フォントなど）
            else:
                target = str(pathlib.PurePosixPath(os.path.normpath(base / u.split("?")[0].split("#")[0])))
            if not target or target in used:
                continue
            if (ROOT / target).is_file():
                used.add(target)
                queue.append(target)

    for extra in EXTRA:
        if (ROOT / extra).exists():
            used.add(extra)

    return sorted(used)


def main():
    if DIST.exists():
        shutil.rmtree(DIST)

    files = collect()
    total = 0
    for rel in files:
        src = ROOT / rel
        dst = DIST / rel
        dst.parent.mkdir(parents=True, exist_ok=True)
        shutil.copy2(src, dst)
        total += src.stat().st_size

    print(f"dist/ に {len(files)} ファイル（{total / 1024 / 1024:.1f}MB）をまとめました")
    print("このフォルダの中身を、そのままサーバーの公開フォルダに置いてください。")


if __name__ == "__main__":
    main()
