<?php
/**
 * テーマ有効化時の初期設定
 *
 * - 業種（医療〜経営・資産の10件）を作成
 * - 固定ページを作成
 *     contact（お問い合わせ）… テンプレート page-contact.php が自動適用。本文は導入文のみ。
 *     privacy（プライバシーポリシー）… 静的サイトと同じ全文を投入。
 *
 * 既に同じスラッグのページ・業種があれば何もしません（重複作成・上書きなし）。
 * 有効化後に手動で消した場合は、テーマを切り替え直すと作り直されます。
 */
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'after_switch_theme', 'tbt_provision' );

function tbt_provision() {
	tbt_provision_industries();
	tbt_ensure_page( 'contact', 'お問い合わせ', tbt_default_contact_content(), 'CONTACT' );
	tbt_ensure_page( 'privacy', 'プライバシーポリシー', tbt_default_privacy_content(), 'PRIVACY POLICY' );
	flush_rewrite_rules();   // 掲載者の /stories/… を有効にする
}

/** スラッグのページが無ければ作成する */
function tbt_ensure_page( $slug, $title, $content, $page_en = '' ) {
	if ( get_page_by_path( $slug, OBJECT, 'page' ) ) {
		return;
	}
	$id = wp_insert_post( array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_name'    => $slug,
		'post_title'   => $title,
		'post_content' => $content,
	) );
	if ( $id && ! is_wp_error( $id ) && $page_en ) {
		update_post_meta( $id, 'page_en', $page_en );
		update_post_meta( $id, '_page_en', 'field_tbt_page_en' );   // ACF に紐づける
	}
}

/** お問い合わせページの導入文（フォーム本体は page-contact.php が描画） */
function tbt_default_contact_content() {
	return '<p>本メディアへの掲載に関するご相談、取材のご依頼、その他のご質問は、こちらのフォームからお送りください。内容を確認のうえ、担当者より3営業日以内にご返信いたします。</p>';
}

/** プライバシーポリシー本文（静的サイト privacy.html と同じ内容） */
function tbt_default_privacy_content() {
	$contact = esc_url( home_url( '/contact/' ) );
	return <<<HTML
<p>THE BEST OF TOKYO 後世に残す言葉（以下「本メディア」といいます）は、本メディア上で収集したすべての個人情報について、以下の方針に基づき、その利用目的および安全対策を定めます。</p>

<h2>運営会社</h2>
<p>株式会社イッツ</p>

<h2>個人情報の利用目的</h2>
<p>本メディア上で収集した個人情報は、次の目的で利用いたします。</p>
<ol>
<li>本メディアの利用状況を分析し、サービスの改善を図るため</li>
<li>ユーザーへのお知らせやマーケティング情報を提供するため</li>
<li>他事業者へ業務を委託する場合（委託先に対しては、本メディアと同等の個人情報保護措置を求めます）</li>
</ol>

<h2>個人情報の収集</h2>
<p>本メディアのサービス向上および機能実現のために個人情報を収集する場合は、あらかじめ利用目的を明示し、同意を得たうえで収集いたします。</p>
<p>収集した個人情報は、同意いただいた利用目的の範囲内でのみ使用し、無断でその他の目的に利用することはありません。</p>

<h2>個人情報の安全管理</h2>
<p>本メディアは、個人情報の安全管理のために以下の措置を講じます。</p>
<ol>
<li>社内規程および管理体制の整備</li>
<li>従業員への適切な教育・指導</li>
<li>個人情報への不正アクセス、紛失、破壊、改ざん、漏洩を防止するための技術的および物理的な対策</li>
<li>継続的な運用改善の実施</li>
</ol>

<h2>個人情報の開示・訂正・削除</h2>
<p>ご登録いただいた個人情報の開示・訂正・削除をご希望の場合は、お問い合わせフォームよりご連絡ください。内容を確認のうえ、速やかに対応いたします。ただし、情報の開示が困難な場合や費用が発生する場合は、事前にその旨をご案内いたします。</p>

<h2>個人情報に関するお問い合わせ窓口</h2>
<p>本ポリシーに関するお問い合わせは、<a class="c-textLink" href="{$contact}">お問い合わせフォーム</a>よりご連絡ください。</p>

<h2>アクセス解析ツールについて</h2>
<p>本メディアでは、利用状況の把握のためにアクセス解析ツールを使用する場合があります。取得されるデータは匿名で収集されており、個人を特定するものではありません。</p>
HTML;
}
