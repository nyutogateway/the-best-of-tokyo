<?php
/**
 * 汎用フォールバック（検索結果・アーカイブなど）
 *   ふだんは front-page.php / single-story.php / page.php が使われます。
 */
if ( ! defined( 'ABSPATH' ) ) exit;
get_header();
?>
<main id="main">
  <section class="p-page">
    <div class="p-page__inner">
      <?php if ( have_posts() ) : ?>
        <header class="p-page__head">
          <p class="p-page__en" data-text="ARCHIVE">ARCHIVE</p>
          <h1 class="p-page__title"><?php echo esc_html( wp_get_document_title() ); ?></h1>
        </header>
        <div class="p-page__block">
          <ul class="p-page__list">
            <?php while ( have_posts() ) : the_post(); ?>
              <li><a class="c-textLink" href="<?php the_permalink(); ?>"><?php the_title(); ?></a></li>
            <?php endwhile; ?>
          </ul>
          <?php the_posts_pagination(); ?>
        </div>
      <?php else : ?>
        <header class="p-page__head">
          <p class="p-page__en" data-text="NOT FOUND">NOT FOUND</p>
          <h1 class="p-page__title">ページが見つかりません</h1>
        </header>
        <p class="p-page__lead">お探しのページは存在しないか、移動した可能性があります。</p>
        <div class="p-page__block">
          <p><a class="c-textLink" href="<?php echo esc_url( home_url( '/' ) ); ?>">トップページへ戻る</a></p>
        </div>
      <?php endif; ?>
    </div>
  </section>
</main>
<?php get_footer(); ?>
