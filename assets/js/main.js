/**
 * THE BEST OF TOKYO
 * ヘッダーの状態切り替え / モバイルメニュー / 掲載者スライダー（依存ライブラリなし）
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
    '.p-stories__meta',
    '.p-spotnav li',
    '.p-index__head',
    '.p-index__group',
    '.p-station__logo',
    '.p-station__body',
    '.p-station__note',
    '.p-article__head',
    '.p-chapter',
    '.p-marquee',
    '.p-article__foot',
    '.p-page__head',
    '.p-page__lead',
    '.p-page__block',
    '.p-form__row',
    '.p-form__note',
    '.p-form__submit'
  ].join(',');

  var revealed = document.querySelectorAll(revealTargets);

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
  }

  /* 掲載者カードのスライダー（送り幅はカードのピッチから実測する） */
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

    var start = function () {
      if (timer) {
        window.clearInterval(timer);
      }
      if (reduceMotion.matches) {
        return;
      }
      timer = window.setInterval(function () {
        if (!document.hidden) {
          show(current + 1);
        }
      }, ROW_WAIT);
    };

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

  document.addEventListener('click', function (e) {
    var link = e.target.closest && e.target.closest('a[href*="#spot"]');
    if (link) {
      jumpToGroup(link.getAttribute('href').split('#')[1]);
    }
  });

  if (location.hash.indexOf('#spot') === 0) {
    jumpToGroup(location.hash.slice(1));
  }

  window.addEventListener('hashchange', function () {
    if (location.hash.indexOf('#spot') === 0) {
      jumpToGroup(location.hash.slice(1));
    }
  });
})();