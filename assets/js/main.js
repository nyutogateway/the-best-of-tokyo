/**
 * THE BEST OF TOKYO
 * 依存ライブラリなし。次の5つだけを受け持つ。
 *   1. ヘッダーの状態切り替え
 *   2. モバイルメニューの開閉
 *   3. スクロール連動のフェードイン（並び順に段階的に出す）
 *   4. グループの塊ごとの入れ替え（1行に2グループ）
 *   5. 目次の開閉と、ページ内リンクの中央寄せ
 */
(function () {
  'use strict';

  var header = document.getElementById('js-header');
  var toggle = document.getElementById('js-toggle');
  var nav = document.getElementById('js-nav');

  /* スクロール位置に応じてヘッダーを紙面色に切り替える */
  if (header && !header.classList.contains('l-header--solid')) {
    var onScroll = function () {
      header.classList.toggle('is-scrolled', window.scrollY > 80);
    };
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
  }

  /* モバイルメニューの開閉 */
  if (toggle && nav) {
    /* ボタンには文字を置いていないので、読み上げ用の名前だけ入れ替える */
    var setLabel = function (open) {
      toggle.setAttribute('aria-label', open ? 'メニューを閉じる' : 'メニューを開く');
    };

    var closeNav = function () {
      toggle.setAttribute('aria-expanded', 'false');
      nav.classList.remove('is-open');
      document.body.classList.remove('is-navOpen');
      setLabel(false);
    };

    toggle.addEventListener('click', function () {
      var isOpen = toggle.getAttribute('aria-expanded') === 'true';
      if (isOpen) {
        closeNav();
      } else {
        toggle.setAttribute('aria-expanded', 'true');
        nav.classList.add('is-open');
        document.body.classList.add('is-navOpen');
        setLabel(true);
      }
    });

    nav.addEventListener('click', function (e) {
      if (e.target.closest('a')) {
        closeNav();
      }
    });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') {
        closeNav();
      }
    });
  }

  /* スクロールで要素を送り出す */
  var revealTargets = [
    '.p-concept__head',
    '.p-concept__line',
    '.p-stories__head',
    '.p-spotnav li',
    '.p-index__head',
    '.p-index__group',
    '.p-station__head',
    '.p-station__logo',
    '.p-station__body',
    '.p-station__note',
    '.p-article__head',
    '.p-chapter',
    '.p-marquee',
    '.p-related__head',
    '.p-related__list > li',
    '.p-article__foot',
    '.p-page__head',
    '.p-page__lead',
    '.p-page__block',
    '.p-form__row',
    '.p-form__note',
    '.p-form__submit'
  ].join(',');

  var revealed = document.querySelectorAll(revealTargets);

  /* グループの行は、行そのものはフェードさせず、画面に入ったことだけを知らせる
     （カードの写真の幕が上がる合図。CSS側で .p-grouprow.is-revealed を見ている） */
  var watchedRows = document.querySelectorAll('.p-grouprow');

  /* .js が付いて初めて初期状態（非表示）が効く。ここまで到達しなければ素で見える */
  document.documentElement.classList.add('js');

  /* 同じ親の中では、並び順に少しずつ遅らせて段階的に出す。
     遅れの値は data-reveal（0〜7）でCSS側に渡す */
  Array.prototype.forEach.call(revealed, function (el) {
    var step = 0;
    var sib = el.previousElementSibling;

    while (sib && step < 7) {
      if (sib.matches && sib.matches(revealTargets)) {
        step += 1;
      }
      sib = sib.previousElementSibling;
    }

    el.setAttribute('data-reveal', String(step));
  });

  if (!('IntersectionObserver' in window)) {
    Array.prototype.forEach.call(revealed, function (el) {
      el.classList.add('is-revealed');
    });
    Array.prototype.forEach.call(watchedRows, function (el) {
      el.classList.add('is-revealed');
    });
  } else {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        /* すでに通り過ぎている位置のものは待たせない */
        if (entry.isIntersecting || entry.boundingClientRect.bottom < 0) {
          entry.target.classList.add('is-revealed');
          io.unobserve(entry.target);
        }
      });
    }, { threshold: 0.12, rootMargin: '0px 0px -8% 0px' });

    Array.prototype.forEach.call(revealed, function (el) {
      io.observe(el);
    });
    Array.prototype.forEach.call(watchedRows, function (el) {
      io.observe(el);
    });
  }

  var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

  /* グループは行ごとに、一定時間で塊ごと入れ替わる。
     NEXT ボタン、タブ・目次からの移動でも切り替わり、そのつど間隔を数え直す */
  var ROW_WAIT = 6000;

  Array.prototype.forEach.call(document.querySelectorAll('.p-grouprow'), function (row, rowIndex) {
    var panels = Array.prototype.filter.call(row.children, function (el) {
      return el.classList.contains('p-group');
    });
    if (panels.length < 2) {
      return;
    }

    var current = 0;
    var timer = null;

    var show = function (i) {
      current = (i + panels.length) % panels.length;
      panels.forEach(function (panel, n) {
        panel.classList.toggle('is-active', n === current);
      });
    };

    /* 触っている行は止める。読んでいる最中に中身が変わらないように */
    var held = false;

    var start = function () {
      if (timer) {
        window.clearInterval(timer);
      }
      if (reduceMotion.matches) {
        return;
      }
      timer = window.setInterval(function () {
        if (!document.hidden && !held) {
          show(current + 1);
        }
      }, ROW_WAIT);
    };

    var hold = function () {
      held = true;
    };

    var release = function () {
      held = false;
    };

    row.addEventListener('pointerenter', hold);
    row.addEventListener('pointerleave', release);
    row.addEventListener('focusin', hold);
    row.addEventListener('focusout', release);

    /* 触れている間は止め、指を離してしばらくしてから再開する */
    row.addEventListener('touchstart', hold, { passive: true });
    row.addEventListener('touchend', function () {
      window.setTimeout(release, 4000);
    });

    row.classList.add('is-ready');
    show(0);

    /* 行ごとに切り替わる時刻をずらす */
    window.setTimeout(start, rowIndex * 1200);

    Array.prototype.forEach.call(row.querySelectorAll('[data-row-next]'), function (btn) {
      btn.addEventListener('click', function () {
        show(current + 1);
        start();
      });
    });

    row.addEventListener('jumpto', function (e) {
      var i = panels.indexOf(e.detail);
      if (i >= 0) {
        show(i);
        start();
      }
    });
  });

  /* タブ・目次からグループへ飛んだら、その塊を出す */
  var jumpToGroup = function (id) {
    var panel = document.getElementById(id);
    if (!panel || !panel.classList.contains('p-group')) {
      return;
    }
    panel.parentNode.dispatchEvent(new CustomEvent('jumpto', { detail: panel }));
  };

  /* 目次の開閉をなめらかに。<details> の即時開閉を、高さの動きに置き換える */
  Array.prototype.forEach.call(document.querySelectorAll('.p-index__group'), function (details) {
    var summary = details.querySelector('summary');
    var panel = details.querySelector('.p-index__list');

    if (!summary || !panel) {
      return;
    }

    var DURATION = 420;
    var EASING = 'cubic-bezier(0.2, 0.8, 0.2, 1)';
    var running = null;

    summary.addEventListener('click', function (e) {
      if (reduceMotion.matches || typeof panel.animate !== 'function') {
        return;
      }

      e.preventDefault();

      if (running) {
        running.cancel();
      }

      if (details.open) {
        /* fill: forwards で閉じ切った状態を保持したまま details を閉じる。
           保持しないと、閉じる直前の1フレームだけ元の高さに戻ってカクつく */
        var closing = panel.animate(
          [
            { height: panel.offsetHeight + 'px', opacity: 1 },
            { height: '0px', opacity: 0 }
          ],
          { duration: DURATION, easing: EASING, fill: 'forwards' }
        );
        running = closing;
        closing.onfinish = function () {
          details.open = false;
          closing.cancel();
          running = null;
        };
      } else {
        details.open = true;
        running = panel.animate(
          [
            { height: '0px', opacity: 0 },
            { height: panel.offsetHeight + 'px', opacity: 1 }
          ],
          { duration: DURATION, easing: EASING }
        );
        running.onfinish = function () {
          running = null;
        };
      }
    });
  });

  /* ページ内リンクは、飛び先が画面の中心に来るように送る。
     画面より背の高い相手は中心に置けないので、ヘッダーのぶんだけ下げて頭を出す */
  var scrollToTarget = function (el) {
    var rect = el.getBoundingClientRect();
    var headerH = header ? header.offsetHeight : 0;
    var view = window.innerHeight;
    var top;

    if (rect.height + 40 < view - headerH) {
      top = window.scrollY + rect.top - (view - rect.height) / 2;
    } else {
      top = window.scrollY + rect.top - headerH - 24;
    }

    window.scrollTo({
      top: Math.max(0, Math.round(top)),
      behavior: reduceMotion.matches ? 'auto' : 'smooth'
    });
  };

  var goToHash = function (hash) {
    if (!hash || hash.charAt(0) !== '#' || hash.length < 2) {
      return null;
    }
    var el = document.getElementById(hash.slice(1));
    if (!el) {
      return null;
    }
    if (hash.indexOf('#spot') === 0) {
      jumpToGroup(hash.slice(1));
    }
    scrollToTarget(el);
    return el;
  };

  document.addEventListener('click', function (e) {
    var link = e.target.closest && e.target.closest('a[href]');
    if (!link) {
      return;
    }

    var href = link.getAttribute('href');
    if (!href || href.charAt(0) !== '#') {
      return;
    }

    if (goToHash(href)) {
      e.preventDefault();
      if (window.history && history.pushState) {
        history.pushState(null, '', href);
      }
    }
  });

  /* 別ページから飛んできたときも同じ位置に置き直す */
  if (location.hash) {
    window.setTimeout(function () {
      goToHash(location.hash);
    }, 60);
  }

  window.addEventListener('hashchange', function () {
    goToHash(location.hash);
  });
})();