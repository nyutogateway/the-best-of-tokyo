<?php
/**
 * 404 ページ
 */
if ( ! defined( 'ABSPATH' ) ) exit;
get_header();
?>
<main id="main">
  <section class="p-page">
    <div class="p-page__inner">
      <header class="p-page__head">
        <p class="p-page__en" data-text="NOT FOUND">NOT FOUND</p>
        <h1 class="p-page__title">ページが見つかりません</h1>
      </header>
      <p class="p-page__lead">お探しのページは存在しないか、移動した可能性があります。<br>URL をご確認のうえ、トップページからお進みください。</p>
      <div class="p-page__block">
        <p><a class="c-textLink" href="<?php echo esc_url( home_url( '/' ) ); ?>">トップページへ戻る</a></p>
      </div>
    </div>
  </section>
</main>
<?php get_footer(); ?>
