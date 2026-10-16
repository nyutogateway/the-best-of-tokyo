<?php
/**
 * お問い合わせフォームのネイティブ送信（プラグイン不要）
 *
 * page-contact.php のフォームが admin-post.php に POST し、ここで受けて wp_mail で送ります。
 * Contact Form 7 を使う場合は固定ページ本文にショートコードを貼れば、
 * page-contact.php 側で自動的にそちらが優先され、この送信処理は使われません。
 *
 * 送信先は既定で「設定 → 一般 → 管理者メールアドレス」。変更したい場合は
 *   add_filter( 'tbt_contact_to', function () { return 'info@example.com'; } );
 */
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'admin_post_nopriv_tbt_contact', 'tbt_handle_contact' );
add_action( 'admin_post_tbt_contact', 'tbt_handle_contact' );

function tbt_handle_contact() {
	$back = wp_get_referer();
	if ( ! $back ) {
		$back = home_url( '/contact/' );
	}

	// nonce 検証
	if ( ! isset( $_POST['tbt_contact_nonce'] )
		|| ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['tbt_contact_nonce'] ) ), 'tbt_contact' ) ) {
		tbt_contact_redirect( $back, 'error' );
	}

	// ハニーポット（ボットが埋めたら成功風に握りつぶす）
	if ( ! empty( $_POST['website'] ) ) {
		tbt_contact_redirect( $back, '1' );
	}

	$name    = isset( $_POST['name'] )    ? sanitize_text_field( wp_unslash( $_POST['name'] ) )        : '';
	$company = isset( $_POST['company'] ) ? sanitize_text_field( wp_unslash( $_POST['company'] ) )     : '';
	$email   = isset( $_POST['email'] )   ? sanitize_email( wp_unslash( $_POST['email'] ) )            : '';
	$tel     = isset( $_POST['tel'] )     ? sanitize_text_field( wp_unslash( $_POST['tel'] ) )         : '';
	$type    = isset( $_POST['type'] )    ? sanitize_text_field( wp_unslash( $_POST['type'] ) )        : '';
	$body    = isset( $_POST['body'] )    ? sanitize_textarea_field( wp_unslash( $_POST['body'] ) )    : '';
	$agree   = ! empty( $_POST['agree'] );

	// 「種類」は用意した選択肢のいずれかであること
	if ( '' !== $type && ! in_array( $type, tbt_contact_types(), true ) ) {
		$type = '';
	}

	// 必須チェック
	if ( '' === $name || ! is_email( $email ) || '' === $type || '' === $body || ! $agree ) {
		tbt_contact_redirect( $back, 'error' );
	}

	$blogname = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	$to       = apply_filters( 'tbt_contact_to', get_option( 'admin_email' ) );
	$subject  = sprintf( '[%s] お問い合わせ: %s', $blogname, $name );

	$lines = array(
		'サイト「' . $blogname . '」のお問い合わせフォームから送信がありました。',
		'',
		'──────────────',
		'お名前　　　: ' . $name,
		'会社・団体名: ' . ( '' !== $company ? $company : '（未入力）' ),
		'メール　　　: ' . $email,
		'電話番号　　: ' . ( '' !== $tel ? $tel : '（未入力）' ),
		'種類　　　　: ' . $type,
		'──────────────',
		'',
		'【お問い合わせ内容】',
		$body,
		'',
		'──────────────',
		'送信日時: ' . wp_date( 'Y-m-d H:i:s' ),
	);

	$headers = array(
		'Content-Type: text/plain; charset=UTF-8',
		sprintf( 'Reply-To: %s <%s>', $name, $email ),
	);

	$ok = wp_mail( $to, $subject, implode( "\n", $lines ), $headers );

	tbt_contact_redirect( $back, $ok ? '1' : 'error' );
}

/** 結果クエリを付けて元ページへ戻す */
function tbt_contact_redirect( $url, $status ) {
	$url = remove_query_arg( 'tbt_sent', $url );
	wp_safe_redirect( add_query_arg( 'tbt_sent', $status, $url ) . '#cform' );
	exit;
}
