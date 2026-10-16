<?php
/**
 * 共通ヘッダー（静的サイト index.html / stories/*.html と同じマークアップ）
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$tbt_home = home_url( '/' );
$tbt_top  = is_front_page();   // トップ以外はページ内リンクをトップ宛てにする
$tbt_link = function ( $hash ) use ( $tbt_home, $tbt_top ) {
	return $tbt_top ? $hash : $tbt_home . ltrim( $hash, '/' );
};
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width,initial-scale=1">
<?php if ( ! has_site_icon() ) : ?>
<link rel="icon" href="<?php echo esc_url( get_template_directory_uri() ); ?>/assets/img/mark-icon.png">
<?php endif; ?>
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<a class="c-skip" href="#main">本文へスキップ</a>

<header class="l-header<?php echo $tbt_top ? '' : ' l-header--solid'; ?>" id="js-header">
  <div class="l-header__inner">
    <p class="l-header__logo">
      <a href="<?php echo esc_url( $tbt_home ); ?>"><img src="<?php echo esc_url( tbt_header_logo() ); ?>" alt="<?php bloginfo( 'name' ); ?>" width="1410" height="318"></a>
    </p>
    <nav class="l-nav" id="js-nav" aria-label="メインメニュー">
      <div class="l-nav__inner">
        <p class="l-nav__logo">
          <img src="<?php echo esc_url( tbt_header_logo() ); ?>" alt="" width="1410" height="318" aria-hidden="true">
        </p>
        <ul class="l-nav__list">
          <li><a href="<?php echo esc_url( $tbt_link( '#concept' ) ); ?>"><span class="l-nav__en">ABOUT</span><span class="l-nav__ja">企画について</span></a></li>
          <li><a href="<?php echo esc_url( $tbt_link( '#stories' ) ); ?>"><span class="l-nav__en">STORIES</span><span class="l-nav__ja">インタビュー</span></a></li>
          <li><a href="<?php echo esc_url( $tbt_link( '#media' ) ); ?>"><span class="l-nav__en">BROADCASTER</span><span class="l-nav__ja">放送局について</span></a></li>
          <li><a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>"><span class="l-nav__en">CONTACT</span><span class="l-nav__ja">お問い合わせ</span></a></li>
        </ul>
        <?php $tbt_groups = tbt_industries(); ?>
        <?php if ( $tbt_groups ) : ?>
          <div class="l-nav__spots">
            <p class="l-nav__spotsLabel">BEST OF TOKYO</p>
            <ul>
              <?php foreach ( $tbt_groups as $tbt_g ) : ?>
                <li><a href="<?php echo esc_url( $tbt_link( '#' . $tbt_g['id'] ) ); ?>"><?php echo esc_html( $tbt_g['no'] ); ?></a></li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>
        <p class="l-nav__station">
          <img src="<?php echo esc_url( tbt_station_logo() ); ?>" alt="<?php echo esc_attr( tbt_text( 'station_name' ) ); ?>" width="432" height="98">
        </p>
      </div>
    </nav>
    <button class="l-header__toggle" id="js-toggle" type="button" aria-expanded="false" aria-controls="js-nav" aria-label="メニューを開く">
      <span class="l-header__bar"></span>
    </button>
  </div>
</header>
