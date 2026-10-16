<?php
/**
 * トップページ（静的サイト index.html と同じマークアップ）
 *
 * 掲載者は「掲載者」（CPT: story）、グループ分けは「業種」（タクソノミー: industry）。
 * 業種は 2つで1行（.p-grouprow）になり、その行の中で一定時間ごとに入れ替わります。
 */
if ( ! defined( 'ABSPATH' ) ) exit;

get_header();

$groups = tbt_industries();
$per    = tbt_cards_per_group();
$rows   = array_chunk( $groups, 2 );
?>

<main id="main">
  <section class="p-hero">
    <div class="p-hero__drape" aria-hidden="true"></div>
    <div class="p-hero__inner">
      <div class="p-hero__meta">
        <h1 class="p-hero__wordmark">
          <img src="<?php echo esc_url( tbt_hero_logo() ); ?>" alt="<?php bloginfo( 'name' ); ?>" width="1208" height="313">
        </h1>
        <p class="p-hero__text"><?php echo nl2br( esc_html( tbt_text( 'hero_text' ) ) ); ?></p>
      </div>
      <p class="p-hero__trophy" aria-hidden="true"></p>
    </div>
    <?php $tiles = tbt_hero_tiles_html(); ?>
    <?php if ( $tiles ) : ?>
      <div class="p-hero__tiles" aria-hidden="true"><?php echo $tiles; // phpcs:ignore WordPress.Security.EscapeOutput -- 生成済み・エスケープ済み ?></div>
    <?php endif; ?>
  </section>

  <?php echo tbt_logos_band_html( false ); // phpcs:ignore WordPress.Security.EscapeOutput ?>

  <section class="p-concept" id="concept">
    <div class="p-concept__inner">
      <header class="p-concept__head">
        <p class="p-concept__en" data-text="ABOUT">ABOUT</p>
        <h2 class="p-concept__title">企画について</h2>
      </header>
      <div class="p-concept__body">
        <p class="p-concept__lead"><?php echo tbt_lines_html( tbt_text( 'concept_lead' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?></p>
        <p class="p-concept__manifesto"><?php echo tbt_lines_html( tbt_text( 'concept_body' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?></p>
        <p class="p-concept__closing"><?php echo tbt_lines_html( tbt_text( 'concept_closing' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?></p>
      </div>
    </div>
  </section>

  <?php echo tbt_logos_band_html( true ); // phpcs:ignore WordPress.Security.EscapeOutput ?>

  <?php if ( $groups ) : ?>
    <section class="p-stories" id="stories">
      <div class="p-stories__masthead">
        <header class="p-stories__head">
          <p class="p-stories__label" data-text="STORIES">STORIES</p>
          <h2 class="p-stories__title">インタビュー</h2>
        </header>
        <nav class="p-spotnav" aria-label="業種で選ぶ">
          <ul>
            <?php foreach ( $groups as $g ) : ?>
              <li>
                <a href="#<?php echo esc_attr( $g['id'] ); ?>"><span class="p-spotnav__label"><?php echo esc_html( $g['en'] ); ?><span class="p-spotnav__ja"><?php echo esc_html( $g['ja'] ); ?></span></span><span class="p-spotnav__no"><?php echo esc_html( $g['no'] ); ?></span></a>
              </li>
            <?php endforeach; ?>
          </ul>
        </nav>
      </div>

      <div class="p-stories__inner">
        <?php foreach ( $rows as $row ) : ?>
          <div class="p-grouprow">
            <?php
            foreach ( $row as $i => $g ) :
              // 同じ行のもう一方が「NEXT」（業種が奇数で1つだけの行は、切り替えが無いのでボタンを出さない）
              $next  = isset( $row[ 1 - $i ] ) ? $row[ 1 - $i ] : null;
              $cards = array_slice( $g['ids'], 0, $per );
              ?>
              <div class="p-group<?php echo 0 === $i ? ' is-active' : ''; ?>" id="<?php echo esc_attr( $g['id'] ); ?>">
                <div class="p-group__head">
                  <h3 class="p-group__title">
                    <span class="p-group__no"><?php echo esc_html( $g['no'] ); ?></span><span class="p-group__label"><?php echo esc_html( $g['en'] ); ?><span class="p-group__ja"><?php echo esc_html( $g['ja'] ); ?></span></span>
                  </h3>
                  <?php if ( $next ) : tbt_group_next_button( $next ); endif; ?>
                </div>
                <div class="p-slider">
                  <ul class="p-cards" aria-label="<?php echo esc_attr( $g['en'] . '（' . $g['ja'] . '）の掲載者' ); ?>">
                    <?php foreach ( $cards as $id ) : ?>
                      <li><?php echo tbt_card_html( $id, 'p-card' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></li>
                    <?php endforeach; ?>
                  </ul>
                </div>
                <?php if ( $next ) : ?>
                  <div class="p-group__foot">
                    <?php tbt_group_next_button( $next ); ?>
                  </div>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="p-index" id="index">
      <div class="p-index__inner">
        <header class="p-index__head">
          <p class="p-index__label" data-text="INDEX">INDEX</p>
          <h2 class="p-index__title">掲載者・企業一覧</h2>
        </header>

        <?php foreach ( $groups as $n => $g ) : ?>
          <details class="p-index__group"<?php echo 0 === $n ? ' open' : ''; ?>>
            <summary class="p-index__group-title">
              <span class="p-index__group-no"><?php echo esc_html( $g['no'] ); ?></span><span class="p-index__group-label"><?php echo esc_html( $g['en'] ); ?><span class="p-index__group-ja"><?php echo esc_html( $g['ja'] ); ?></span></span>
            </summary>
            <ul class="p-index__list">
              <?php foreach ( $g['ids'] as $id ) : ?>
                <?php $co = tbt_field( 'company', $id ); $role = tbt_field( 'role', $id ); ?>
                <li>
                  <a href="<?php echo esc_url( get_permalink( $id ) ); ?>">
                    <span class="p-index__name"><?php echo esc_html( get_the_title( $id ) ); ?></span>
                    <?php if ( $co || $role ) : ?>
                      <span class="p-index__company"><?php if ( $co ) : ?><span class="p-index__corp"><?php echo esc_html( $co ); ?></span><?php endif; ?><?php if ( $role ) : ?><span class="p-index__role"><?php echo esc_html( $role ); ?></span><?php endif; ?></span>
                    <?php endif; ?>
                  </a>
                </li>
              <?php endforeach; ?>
            </ul>
          </details>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>

  <div class="p-ticker" aria-hidden="true">
    <div class="p-ticker__viewport">
      <div class="p-ticker__track">
        <?php for ( $i = 0; $i < 8; $i++ ) : ?>
          <span class="p-ticker__item">BEST OF TOKYO</span>
          <span class="p-ticker__mark"></span>
        <?php endfor; ?>
      </div>
    </div>
  </div>

  <?php
  $station_url  = tbt_text( 'station_url' );
  $station_name = tbt_text( 'station_name' );
  $station_host = preg_replace( '#^https?://|/$#', '', $station_url );
  ?>
  <section class="p-station" id="media">
    <div class="p-station__inner">
      <header class="p-station__head">
        <p class="p-station__en" data-text="BROADCASTER">BROADCASTER</p>
        <h2 class="p-station__title">放送局のご紹介</h2>
      </header>
      <div class="p-station__box">
        <p class="p-station__logo">
          <a href="<?php echo esc_url( $station_url ); ?>" target="_blank" rel="noopener">
            <img src="<?php echo esc_url( tbt_station_logo() ); ?>" alt="<?php echo esc_attr( $station_name ); ?>" width="432" height="98">
          </a>
        </p>
        <p class="p-station__name"><?php echo esc_html( $station_name ); ?></p>
        <p class="p-station__text"><?php echo nl2br( esc_html( tbt_text( 'station_text' ) ) ); ?></p>
        <dl class="p-station__data">
          <div>
            <dt>放送エリア</dt>
            <dd><?php echo esc_html( tbt_text( 'station_area' ) ); ?></dd>
          </div>
          <div>
            <dt>公式サイト</dt>
            <dd><a href="<?php echo esc_url( $station_url ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $station_host ); ?></a></dd>
          </div>
        </dl>
        <?php
        /* 放送エリア図はベクター（SVG）なので、どの大きさでも粗が出ません。
           県境は国土数値情報（国土交通省）の行政区域データから起こしたものです。
           出典の表記（フッター）は消さないでください。 */
        ?>
        <figure class="p-station__area">
          <img src="<?php echo esc_url( tbt_area_map() ); ?>" alt="<?php echo esc_attr( $station_name . ' の放送エリア図' ); ?>" width="680" height="490" loading="lazy" decoding="async">
        </figure>
      </div>
    </div>
  </section>
</main>

<?php get_footer(); ?>
