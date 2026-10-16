<?php
/**
 * 掲載者の個別ページ（インタビュー記事）
 *   ヘッダー部分＝ACFの入力欄／章・Q&A＝本文エディタ
 *   本文は functions.php の tbt_build_article() が .p-chapter / .p-qa に組み直します。
 */
if ( ! defined( 'ABSPATH' ) ) exit;

get_header();

while ( have_posts() ) : the_post();

	$id      = get_the_ID();
	$name    = get_the_title();
	$catch   = tbt_field( 'headline', $id );
	if ( ! $catch ) { $catch = $name; }
	$en      = tbt_field( 'en_name', $id );
	$co      = tbt_field( 'company', $id );
	$role    = tbt_field( 'role', $id );
	$lead    = tbt_field( 'lead', $id );
	$site    = tbt_field( 'url', $id );
	$face    = tbt_field( 'portrait', $id );
	if ( ! $face ) { $face = get_the_post_thumbnail_url( $id, 'tbt-portrait' ); }

	// MORE STORIES は同じ業種の他の掲載者（最大4人）
	$group   = tbt_story_industry( $id );
	$related = array();
	if ( $group ) {
		foreach ( $group['ids'] as $rid ) {
			if ( (int) $rid !== (int) $id ) {
				$related[] = $rid;
			}
		}
		$related = array_slice( $related, 0, 4 );
	}
	?>

<main id="main">
  <article class="p-article">
    <div class="p-article__inner">

      <div class="p-article__head">
        <?php if ( $face ) : ?>
          <figure class="p-article__visual">
            <img src="<?php echo esc_url( $face ); ?>" alt="<?php echo esc_attr( $name . '氏' ); ?>" width="600" height="800">
          </figure>
        <?php endif; ?>
        <div class="p-article__intro">
          <h1 class="p-article__title"><?php echo nl2br( esc_html( $catch ) ); ?></h1>
          <p class="p-article__name"><?php echo esc_html( $name ); ?><?php if ( $en ) : ?><span class="p-article__romaji"><?php echo esc_html( $en ); ?></span><?php endif; ?></p>
          <?php if ( $co || $role ) : ?>
            <p class="p-article__company"><?php echo esc_html( trim( $co . ( $co && $role ? ' ' : '' ) . $role ) ); ?></p>
          <?php endif; ?>
          <?php if ( $lead ) : ?>
            <p class="p-article__profile"><?php echo nl2br( esc_html( $lead ) ); ?></p>
          <?php endif; ?>
          <?php if ( $site ) : ?>
            <a class="c-textLink" href="<?php echo esc_url( $site ); ?>" target="_blank" rel="noopener">企業サイトを見る</a>
          <?php endif; ?>
        </div>
      </div>

      <?php the_content(); // 章（.p-chapter）と写真の帯（.p-marquee）に組み直されて出力されます ?>

      <?php if ( $related ) : ?>
        <section class="p-related p-related--g<?php echo (int) $group['index']; ?>">
          <header class="p-related__head">
            <h2 class="p-related__en" data-text="MORE STORIES">MORE STORIES</h2>
          </header>
          <ul class="p-related__list">
            <?php foreach ( $related as $rid ) : ?>
              <li><?php echo tbt_card_html( $rid, 'p-related' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></li>
            <?php endforeach; ?>
          </ul>
        </section>
      <?php endif; ?>

      <div class="p-article__foot">
        <a class="c-back" href="<?php echo esc_url( home_url( '/#stories' ) ); ?>">インタビュー一覧へ戻る</a>
      </div>

    </div>
  </article>
</main>

<?php endwhile; ?>
<?php get_footer(); ?>
