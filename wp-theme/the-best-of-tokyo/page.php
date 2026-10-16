<?php
/**
 * 汎用の固定ページ（プライバシーポリシーなど）
 *   英字見出しは ACF「英字見出し」（page_en）。未入力ならスラッグを大文字にして使います。
 *   本文は functions.php の tbt_build_page() が .p-page__block に組み直します。
 */
if ( ! defined( 'ABSPATH' ) ) exit;

get_header();

while ( have_posts() ) : the_post();
	$en = tbt_page_en( get_the_ID() );
	?>
<main id="main">
  <section class="p-page">
    <div class="p-page__inner">
      <header class="p-page__head">
        <p class="p-page__en" data-text="<?php echo esc_attr( $en ); ?>"><?php echo esc_html( $en ); ?></p>
        <h1 class="p-page__title"><?php the_title(); ?></h1>
      </header>
      <?php the_content(); ?>
    </div>
  </section>
</main>
<?php endwhile; ?>
<?php get_footer(); ?>
