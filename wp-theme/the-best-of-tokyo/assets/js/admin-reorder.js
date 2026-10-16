/* 掲載者の並び替え画面（管理画面）
   ドラッグして落とした時点で menu_order を保存します。 */
jQuery(function ($) {
  'use strict';

  var CFG = window.TBT_REORDER || {};
  var $list = $('.tbt-reorder');
  if (!$list.length || !CFG.ajax) {
    return;
  }

  var $status = $('.tbt-reorder-status');

  /* 通し番号を振り直す */
  function renumber() {
    $list.children('.tbt-reorder__item').each(function (i) {
      $(this).find('.tbt-reorder__no').text(('0' + (i + 1)).slice(-2));
    });
  }

  function status(text, cls) {
    $status.attr('class', 'tbt-reorder-status ' + (cls || '')).text(text);
  }

  var timer = null;

  function save() {
    var ids = $list.children('.tbt-reorder__item').map(function () {
      return $(this).data('id');
    }).get();

    status('保存中…', 'is-busy');
    $.post(CFG.ajax, { action: 'tbt_reorder', nonce: CFG.nonce, ids: ids })
      .done(function (res) {
        if (res && res.success) {
          status('保存しました', 'is-ok');
          window.clearTimeout(timer);
          timer = window.setTimeout(function () { status(''); }, 2500);
        } else {
          status((res && res.data && res.data.message) || '保存に失敗しました', 'is-err');
        }
      })
      .fail(function () {
        status('保存に失敗しました。通信状況をご確認ください。', 'is-err');
      });
  }

  $list.sortable({
    items: '> .tbt-reorder__item',
    placeholder: 'tbt-reorder__slot',
    forcePlaceholderSize: true,
    cursor: 'grabbing',
    tolerance: 'pointer',
    cancel: 'a',
    update: function () {
      renumber();
      save();
    }
  });

  renumber();
});
