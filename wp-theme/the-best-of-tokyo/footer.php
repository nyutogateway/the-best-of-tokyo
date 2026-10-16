<?php
/**
 * 共通フッター（静的サイトと同じマークアップ）
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$tbt_home = home_url( '/' );
$tbt_top  = is_front_page();
$tbt_link = function ( $hash ) use ( $tbt_home, $tbt_top ) {
	return $tbt_top ? $hash : $tbt_home . ltrim( $hash, '/' );
};
$tbt_station_url = tbt_text( 'station_url' );
?>
<footer class="l-footer">
  <div class="l-footer__inner">
    <p class="l-footer__station">
      <a href="<?php echo esc_url( $tbt_station_url ); ?>" target="_blank" rel="noopener"><img src="<?php echo esc_url( tbt_station_logo() ); ?>" alt="<?php echo esc_attr( tbt_text( 'station_name' ) ); ?>" width="432" height="98"></a>
    </p>
    <p class="l-footer__logo">
      <a href="<?php echo esc_url( $tbt_home ); ?>"><img src="<?php echo esc_url( tbt_footer_logo() ); ?>" alt="<?php bloginfo( 'name' ); ?>" width="1410" height="318"></a>
    </p>
    <nav class="l-footer__nav" aria-label="フッターメニュー">
      <ul>
        <li><a href="<?php echo esc_url( $tbt_link( '#concept' ) ); ?>">ABOUT</a></li>
        <li><a href="<?php echo esc_url( $tbt_link( '#stories' ) ); ?>">STORIES</a></li>
        <li><a href="<?php echo esc_url( $tbt_link( '#media' ) ); ?>">BROADCASTER</a></li>
        <li><a href="<?php echo esc_url( home_url( '/privacy/' ) ); ?>">PRIVACY</a></li>
        <li><a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>">CONTACT</a></li>
      </ul>
    </nav>
    <p class="l-footer__copy"><?php echo nl2br( esc_html( tbt_text( 'footer_copy' ) ) ); ?></p>
  </div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
