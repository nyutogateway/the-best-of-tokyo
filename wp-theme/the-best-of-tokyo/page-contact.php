<?php
/**
 * お問い合わせページ（スラッグ contact の固定ページで自動適用）
 *
 * フォームは次の優先順で表示されます。
 *   1) 本文に Contact Form 7 を置いた場合 → そちらを表示（テーマ内蔵フォームは非表示）
 *      意匠クラス .p-form はテーマ側が自動で付けます。
 *   2) 置いていない場合 → テーマ内蔵フォーム（inc/contact-form.php が wp_mail で送信）
 */
if ( ! defined( 'ABSPATH' ) ) exit;

get_header();

while ( have_posts() ) : the_post();
	$en   = tbt_page_en( get_the_ID(), 'CONTACT' );
	$sent = isset( $_GET['tbt_sent'] ) ? sanitize_key( wp_unslash( $_GET['tbt_sent'] ) ) : '';   // phpcs:ignore WordPress.Security.NonceVerification
	?>
<main id="main">
  <section class="p-page">
    <div class="p-page__inner">
      <header class="p-page__head">
        <p class="p-page__en" data-text="<?php echo esc_attr( $en ); ?>"><?php echo esc_html( $en ); ?></p>
        <h1 class="p-page__title"><?php the_title(); ?></h1>
      </header>

      <?php the_content(); // 導入文＋（任意で）Contact Form 7 のショートコード ?>

      <?php if ( ! tbt_has_cf7( get_the_content() ) ) : ?>
        <a id="cform"></a>
        <?php if ( '1' === $sent ) : ?>
          <p class="p-form__note" role="status">送信が完了しました。お問い合わせありがとうございます。担当者より折り返しご連絡いたします。</p>
        <?php elseif ( 'error' === $sent ) : ?>
          <p class="p-form__note" role="alert">送信に失敗しました。お手数ですが、必須項目をご確認のうえ、もう一度お試しください。</p>
        <?php endif; ?>

        <form class="p-form" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
          <input type="hidden" name="action" value="tbt_contact">
          <?php wp_nonce_field( 'tbt_contact', 'tbt_contact_nonce' ); ?>
          <?php // ハニーポット（人間には見えない位置。ボットが入力すると送信を破棄） ?>
          <div class="p-form__trap" aria-hidden="true">
            <label for="website">Webサイト</label>
            <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
          </div>

          <div class="p-form__row">
            <label class="p-form__label" for="type">お問い合わせの種類<span class="p-form__required">必須</span></label>
            <div class="p-form__field">
              <select class="p-form__select" id="type" name="type" required>
                <option value="">選択してください</option>
                <?php foreach ( tbt_contact_types() as $t ) : ?>
                  <option><?php echo esc_html( $t ); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="p-form__row">
            <label class="p-form__label" for="name">お名前<span class="p-form__required">必須</span></label>
            <div class="p-form__field">
              <input class="p-form__input" type="text" id="name" name="name" autocomplete="name" required>
            </div>
          </div>

          <div class="p-form__row">
            <label class="p-form__label" for="company">会社名・団体名<span class="p-form__optional">任意</span></label>
            <div class="p-form__field">
              <input class="p-form__input" type="text" id="company" name="company" autocomplete="organization">
            </div>
          </div>

          <div class="p-form__row">
            <label class="p-form__label" for="email">メールアドレス<span class="p-form__required">必須</span></label>
            <div class="p-form__field">
              <input class="p-form__input" type="email" id="email" name="email" autocomplete="email" inputmode="email" aria-describedby="email-note" required>
              <p class="p-form__hint" id="email-note">ご返信先のアドレスです。お間違いのないようご確認ください。</p>
            </div>
          </div>

          <div class="p-form__row">
            <label class="p-form__label" for="tel">電話番号<span class="p-form__optional">任意</span></label>
            <div class="p-form__field">
              <input class="p-form__input" type="tel" id="tel" name="tel" autocomplete="tel" inputmode="tel">
            </div>
          </div>

          <div class="p-form__row">
            <label class="p-form__label" for="body">お問い合わせ内容<span class="p-form__required">必須</span></label>
            <div class="p-form__field">
              <textarea class="p-form__textarea" id="body" name="body" aria-describedby="body-note" required></textarea>
              <p class="p-form__hint" id="body-note">掲載のご相談は、会社名・事業内容・ご希望の時期を書き添えていただけると、やり取りが早くなります。</p>
            </div>
          </div>

          <div class="p-form__consent">
            <input class="p-form__check" type="checkbox" id="agree" name="agree" value="同意する" required>
            <label class="p-form__checkLabel" for="agree"><a class="c-textLink" href="<?php echo esc_url( home_url( '/privacy/' ) ); ?>">プライバシーポリシー</a>に同意します<span class="p-form__required">必須</span></label>
          </div>

          <button class="p-form__submit" type="submit">送信する</button>
        </form>
      <?php endif; ?>
    </div>
  </section>
</main>
<?php endwhile; ?>
<?php get_footer(); ?>
