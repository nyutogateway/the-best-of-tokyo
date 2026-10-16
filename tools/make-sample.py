#!/usr/bin/env python3
"""静的サイトを「サンプル状態」に差し替える

実在の人物・企業の情報と写真をすべて外し、
  ・写真  … AI生成のサンプル画像（assets/img/sample/face-*.jpg, scene-*.jpg）
  ・ロゴ  … 「ロゴが入ります」のプレースホルダ（assets/img/sample/logo-*.svg）
  ・文言  … 「氏名が入ります」「会社名が入ります」などの差し込み見本
に置き換える。納品前のデモや、WordPressに入れる前の見た目確認に使う。

元の内容は git の履歴に残っているので、戻したいときは差し戻せばよい。
画像ファイル（assets/img/photos, assets/img/logos）は消さずに残している。
"""
import glob
import pathlib
import re

ROOT = pathlib.Path(__file__).resolve().parent.parent

N_FACE = 13   # assets/img/sample/face-01..13.jpg
N_SCENE = 4   # assets/img/sample/scene-01..04.jpg
N_LOGO = 50   # assets/img/sample/logo-01..50.svg（50人ぶん＝1人1種類）

FACE_W = FACE_H = 640
SCENE_W, SCENE_H = 750, 494

NAME = "氏名が入ります"
ROMAJI = "name<br>name"          # カード（2行）
ROMAJI_1L = "name name"          # 記事ページ（1行）
CORP = "会社名が入ります"
ROLE = "役職が入ります"
CATCH = "キャッチコピーが入ります"
PROFILE = "プロフィールが入ります。プロフィールが入ります。プロフィールが入ります。プロフィールが入ります。"
CHAPTER = "中見出しが入ります"
MESSAGE_TITLE = "メッセージの見出しが入ります"
MESSAGE_BODY = "メッセージが入ります。" * 5
QUESTION = "質問が入ります。質問が入ります。"
# 長さの違う回答を順に使って、実際の記事に近い見え方にする
ANSWERS = [
    "回答が入ります。" * 9,
    "回答が入ります。" * 4 + "<br>" + "回答が入ります。" * 5,
    "回答が入ります。" * 3,
    "回答が入ります。" * 14,
]


def face(i):
    return f"assets/img/sample/face-{(i - 1) % N_FACE + 1:02d}.jpg"


def scene(i):
    return f"assets/img/sample/scene-{(i - 1) % N_SCENE + 1:02d}.jpg"


def logo(i):
    return f"assets/img/sample/logo-{(i - 1) % N_LOGO + 1:02d}.svg"


def img_tag(src, w, h, cls=None, alt="", lazy=True, up=""):
    """1行の <img>。up は ../ などの相対パスの前置き"""
    c = f'class="{cls}" ' if cls else ""
    l = ' loading="lazy" decoding="async"' if lazy else ""
    return f'<img {c}src="{up}{src}" alt="{alt}" width="{w}" height="{h}"{l}>'


# ------------------------------------------------------------------
# TOP（index.html）
# ------------------------------------------------------------------
def convert_index(html):
    # カード：ロゴと写真は、リンク先の記事番号で決める
    def card(m):
        no = int(m.group("no"))
        return (
            f'<a class="p-card" href="stories/{no:02d}.html">'
            f'<span class="p-card__visual">'
            f'{img_tag(logo(no), 420, 420, cls="p-card__logo")}'
            f'{img_tag(face(no), FACE_W, FACE_H, alt=NAME)}'
            f"</span>"
            f'<span class="p-card__body">'
            f'<span class="p-card__romaji">{ROMAJI}</span>'
            f'<span class="p-card__name">{NAME}</span>'
            f'<span class="p-card__company"><span class="p-card__corp">{CORP}</span>'
            f'<span class="p-card__role">{ROLE}</span></span>'
            f"</span></a>"
        )

    html = re.sub(
        r'<a class="p-card" href="stories/(?P<no>\d+)\.html">.*?</a\s*>',
        card,
        html,
        flags=re.S,
    )

    # INDEX の一覧
    def index_row(m):
        return (
            f'<a href="stories/{int(m.group("no")):02d}.html">'
            f'<span class="p-index__name">{NAME}</span>'
            f'<span class="p-index__company"><span class="p-index__corp">{CORP}</span>'
            f'<span class="p-index__role">{ROLE}</span></span></a>'
        )

    html = re.sub(
        r'<a href="stories/(?P<no>\d+)\.html">\s*<span class="p-index__name">.*?</a\s*>',
        index_row,
        html,
        flags=re.S,
    )

    # FV背面のロゴの壁（列ごとに並びをずらす）
    def tiles(m):
        cols = re.findall(r'<div class="p-hero__col([^"]*)">(.*?)</div>', m.group(0), re.S)
        out = []
        for ci, (mod, body) in enumerate(cols):
            rows = len(re.findall(r"<img", body))
            cells = "".join(
                f'\n            {img_tag(logo(ci + 1 + r * 4), 420, 420, lazy=False)}'
                for r in range(rows)
            )
            out.append(f'<div class="p-hero__col{mod}">{cells}\n          </div>')
        return (
            '<div class="p-hero__tiles" aria-hidden="true">\n          '
            + "\n          ".join(out)
            + "\n        </div>"
        )

    html = re.sub(
        r'<div class="p-hero__tiles" aria-hidden="true">.*?\n        </div>',
        tiles,
        html,
        flags=re.S,
    )

    # 流れるロゴの帯
    def band(m):
        items = len(re.findall(r"<li", m.group(2)))
        cells = "".join(
            f'\n            <li class="p-logos__item">{img_tag(logo(i + 1), 420, 420)}</li>'
            for i in range(items)
        )
        return (
            f'<div class="p-logos{m.group(1)}">\n        <div class="p-logos__viewport">\n'
            f'          <ul class="p-logos__track">{cells}\n          </ul>\n        </div>\n      </div>'
        )

    html = re.sub(
        r'<div class="p-logos([^"]*)">\s*<div class="p-logos__viewport">\s*<ul class="p-logos__track">(.*?)</ul>\s*</div>\s*</div>',
        band,
        html,
        flags=re.S,
    )

    return html


# ------------------------------------------------------------------
# 記事ページ（stories/NN.html）
# ------------------------------------------------------------------
def convert_story(html, no):
    desc = f"{CORP} {ROLE} {NAME}へのインタビュー。THE BEST OF TOKYO 後世に残す言葉。"

    html = re.sub(r"<title>.*?</title>", f"<title>{CATCH} ｜ THE BEST OF TOKYO</title>", html, flags=re.S)
    html = re.sub(
        r'(<meta name="description" content=")[^"]*(")', rf"\1{desc}\2", html
    )
    html = re.sub(
        r'(<meta property="og:title" content=")[^"]*(")',
        rf"\1{CATCH} ｜ THE BEST OF TOKYO\2",
        html,
    )
    html = re.sub(
        r'(<meta property="og:description" content=")[^"]*(")', rf"\1{desc}\2", html
    )

    # 構造化データ
    def jsonld(m):
        body = m.group(1)
        body = re.sub(r'("headline":\s*")[^"]*(")', rf"\1{CATCH}\2", body)
        body = re.sub(r'("description":\s*")[^"]*(")', rf"\1{desc}\2", body)
        body = re.sub(
            r'("image":\s*")[^"]*(")',
            lambda _: f'"image": "https://the-best-of-tokyo.com/{face(no)}"',
            body,
        )
        body = re.sub(r'("name":\s*")(?!THE BEST)[^"]*(")', rf"\1{NAME}\2", body, count=1)
        body = re.sub(r'("jobTitle":\s*")[^"]*(")', rf"\1{ROLE}\2", body)
        # worksFor の name（Person の name より後ろにある2つ目）
        names = list(re.finditer(r'("name":\s*")([^"]*)(")', body))
        if len(names) >= 2:
            s, e = names[1].span(2)
            body = body[:s] + CORP + body[e:]
        return f'<script type="application/ld+json">{body}</script>'

    html = re.sub(
        r'<script type="application/ld\+json">(.*?)</script>', jsonld, html, flags=re.S
    )

    # 記事ヘッダー
    html = re.sub(
        r'<figure class="p-article__visual">\s*<img[^>]*>\s*</figure>',
        f'<figure class="p-article__visual">\n          '
        f'{img_tag(face(no), FACE_W, FACE_H, alt=NAME, lazy=False, up="../")}\n        </figure>',
        html,
        flags=re.S,
    )
    html = re.sub(
        r'(<h1 class="p-article__title">).*?(</h1>)', rf"\1{CATCH}\2", html, flags=re.S
    )
    html = re.sub(
        r'(<p class="p-article__name">).*?(</p>)',
        rf'\1{NAME}<span class="p-article__romaji">{ROMAJI_1L}</span>\2',
        html,
        flags=re.S,
    )
    html = re.sub(
        r'(<p class="p-article__company">).*?(</p>)',
        rf"\1{CORP} {ROLE}\2",
        html,
        flags=re.S,
    )
    html = re.sub(
        r'(<p class="p-article__profile">).*?(</p>)', rf"\1{PROFILE}\2", html, flags=re.S
    )
    html = re.sub(
        r'(<a class="c-textLink" href=")[^"]*(")', r"\1https://example.com/\2", html
    )

    # 章の見出し・本文
    html = re.sub(
        r'(<h2 class="p-chapter__title">).*?(</h2>)', rf"\1{CHAPTER}\2", html, flags=re.S
    )
    html = re.sub(
        r'(<p class="p-chapter__text">).*?(</p>)', rf"\1{MESSAGE_BODY}\2", html, flags=re.S
    )
    # MESSAGE 章の見出しだけ別の文言に（ラベルが MESSAGE の章）
    html = re.sub(
        r'(<p class="p-chapter__label">MESSAGE</p>\s*<h2 class="p-chapter__title">).*?(</h2>)',
        rf"\1{MESSAGE_TITLE}\2",
        html,
        flags=re.S,
    )

    # 章の写真
    fig = {"n": 0}

    def figure(m):
        fig["n"] += 1
        src = scene(no + fig["n"] - 1)
        return (
            f'<figure class="p-figure">\n          '
            f'{img_tag(src, SCENE_W, SCENE_H, up="../")}\n        </figure>'
        )

    html = re.sub(
        r'<figure class="p-figure">\s*<img[^>]*>\s*</figure>', figure, html, flags=re.S
    )

    # 質問と回答
    html = re.sub(
        r'(<p class="p-qa__q"><span class="p-qa__mark">Q</span><span>).*?(</span></p>)',
        rf"\1{QUESTION}\2",
        html,
        flags=re.S,
    )
    ans = {"n": 0}

    def answer(m):
        a = ANSWERS[(no + ans["n"]) % len(ANSWERS)]
        ans["n"] += 1
        return f'<p class="p-qa__a"><span class="p-qa__mark">A</span><span>{a}</span></p>'

    html = re.sub(
        r'<p class="p-qa__a"><span class="p-qa__mark">A</span><span>.*?</span></p>',
        answer,
        html,
        flags=re.S,
    )

    # 流れる写真の帯
    def marquee(m):
        n_items = len(re.findall(r"<li>", m.group(2)))
        cells = "".join(
            f'\n            <li>{img_tag(scene(no + i), SCENE_W, SCENE_H, up="../")}</li>'
            for i in range(n_items)
        )
        return f'<ul class="p-marquee__set"{m.group(1)}>{cells}\n          </ul>'

    html = re.sub(
        r'<ul class="p-marquee__set"([^>]*)>(.*?)</ul>', marquee, html, flags=re.S
    )

    # 関連カード
    def related(m):
        rno = int(m.group("no"))
        return (
            f'<a class="p-related__card" href="{rno:02d}.html">'
            f'<span class="p-related__visual">'
            f'{img_tag(logo(rno), 420, 420, cls="p-related__logo", up="../")}'
            f'{img_tag(face(rno), FACE_W, FACE_H, alt=NAME, up="../")}'
            f"</span>"
            f'<span class="p-related__body">'
            f'<span class="p-related__romaji">{ROMAJI}</span>'
            f'<span class="p-related__name">{NAME}</span>'
            f'<span class="p-related__company"><span class="p-related__corp">{CORP}</span>'
            f'<span class="p-related__role">{ROLE}</span></span>'
            f"</span></a>"
        )

    html = re.sub(
        r'<a class="p-related__card" href="(?P<no>\d+)\.html">.*?</a\s*>',
        related,
        html,
        flags=re.S,
    )

    return html


def main():
    index = ROOT / "index.html"
    index.write_text(convert_index(index.read_text(encoding="utf-8")), encoding="utf-8")
    print("index.html を差し替えました")

    for path in sorted(glob.glob(str(ROOT / "stories" / "*.html"))):
        no = int(pathlib.Path(path).stem)
        p = pathlib.Path(path)
        p.write_text(convert_story(p.read_text(encoding="utf-8"), no), encoding="utf-8")
    print(f"記事ページ {len(glob.glob(str(ROOT / 'stories' / '*.html')))} 件を差し替えました")


if __name__ == "__main__":
    main()
