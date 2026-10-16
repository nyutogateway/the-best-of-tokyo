<?php
/**
 * OGP / Twitter カード メタタグと meta description の出力（SEOプラグイン不要）
 *
 * - トップ: サイト名 + キャッチフレーズ + 既定OGP画像
 * - 掲載者ページ: キャッチコピー + 氏名/会社/役職 + 人物写真
 * - Yoast SEO / SEO SIMPLE PACK / SEOPRESS を入れた場合は出力を止めてプラグインに任せます。
 *
 * 既定OGP画像は assets/img/ogp.png（1200×630推奨）を置けば自動採用されます。
 * 差し替えは add_filter( 'tbt_ogp_image', function () { return 'https://…'; } ); でも可能。
 */
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'wp_head', 'tbt_head_meta', 5 );

function tbt_head_meta() {
	// SEOプラグインが有効なら二重出力を避けて譲る
	if ( defined( 'WPSEO_VERSION' ) || defined( 'SIMPLE_SEO_PACK_VERSION' ) || class_exists( 'SEOPress' ) ) {
		return;
	}
	if ( ! apply_filters( 'tbt_enable_ogp', true ) ) {
		return;
	}

	$site = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	$type = 'website';

	if ( is_front_page() ) {
		$title = $site;
		$desc  = get_bloginfo( 'description' );
		$url   = home_url( '/' );
		$img   = tbt_ogp_image();
	} elseif ( is_singular() ) {
		$post  = get_queried_object();
		$type  = 'article';
		$title = wp_get_document_title();
		$desc  = tbt_meta_description( $post );
		$url   = get_permalink( $post );
		$img   = tbt_singular_image( $post );
	} else {
		$title = wp_get_document_title();
		$desc  = get_bloginfo( 'description' );
		$url   = home_url( add_query_arg( array(), isset( $GLOBALS['wp']->request ) ? $GLOBALS['wp']->request : '' ) );
		$img   = tbt_ogp_image();
	}

	$desc = mb_substr( trim( preg_replace( '/\s+/u', ' ', (string) $desc ) ), 0, 120 );

	echo "\n<!-- meta / OGP (the-best-of-tokyo) -->\n";
	if ( '' !== $desc ) {
		printf( '<meta name="description" content="%s">' . "\n", esc_attr( $desc ) );
	}

	$tags = array(
		'og:type'        => $type,
		'og:site_name'   => $site,
		'og:locale'      => 'ja_JP',
		'og:title'       => $title,
		'og:description' => $desc,
		'og:url'         => $url,
		'og:image'       => $img,
	);
	foreach ( $tags as $prop => $val ) {
		if ( '' === $val ) continue;
		printf( '<meta property="%s" content="%s">' . "\n", esc_attr( $prop ), esc_attr( $val ) );
	}
	printf( '<meta name="twitter:card" content="%s">' . "\n", $img ? 'summary_large_image' : 'summary' );
	printf( '<meta name="twitter:title" content="%s">' . "\n", esc_attr( $title ) );
	if ( '' !== $desc ) {
		printf( '<meta name="twitter:description" content="%s">' . "\n", esc_attr( $desc ) );
	}
	if ( $img ) {
		printf( '<meta name="twitter:image" content="%s">' . "\n", esc_attr( $img ) );
	}
	echo "<!-- /meta -->\n";
}

/** 個別ページの説明文（抜粋 → 掲載者の要約 → 本文の先頭） */
function tbt_meta_description( $post ) {
	if ( has_excerpt( $post ) ) {
		return get_the_excerpt( $post );
	}
	if ( 'story' === get_post_type( $post ) ) {
		return tbt_story_summary( $post->ID );
	}
	$content = wp_strip_all_tags( strip_shortcodes( $post->post_content ) );
	return '' !== $content ? $content : get_bloginfo( 'description' );
}

/** 個別ページのOGP画像（掲載者はヒーロー写真 → アイキャッチ → 既定） */
function tbt_singular_image( $post ) {
	if ( 'story' === get_post_type( $post ) ) {
		$hero = tbt_field( 'portrait', $post->ID );
		if ( $hero ) {
			return $hero;
		}
	}
	if ( has_post_thumbnail( $post ) ) {
		return get_the_post_thumbnail_url( $post, 'full' );
	}
	return tbt_ogp_image();
}

/** 既定OGP画像（assets/img/ogp.png → サイトアイコン → ロゴ の順にフォールバック） */
function tbt_ogp_image() {
	if ( file_exists( get_template_directory() . '/assets/img/ogp.png' ) ) {
		$url = get_template_directory_uri() . '/assets/img/ogp.png';
	} elseif ( function_exists( 'get_site_icon_url' ) && get_site_icon_url() ) {
		$url = get_site_icon_url( 1200 );
	} else {
		$url = get_template_directory_uri() . '/assets/img/logo-white-gold.webp';
	}
	return apply_filters( 'tbt_ogp_image', $url );
}

/* ------------------------------------------------------------------
 * 構造化データ（JSON-LD）
 *   トップ: WebSite + Organization ／ 掲載者ページ: Article
 *   静的サイトと同じ内容です。SEOプラグインを入れたときは出力しません。
 * ---------------------------------------------------------------- */
add_action( 'wp_head', 'tbt_json_ld', 6 );

function tbt_json_ld() {
	if ( defined( 'WPSEO_VERSION' ) || defined( 'SIMPLE_SEO_PACK_VERSION' ) || class_exists( 'SEOPress' ) ) {
		return;
	}
	if ( ! apply_filters( 'tbt_enable_ogp', true ) ) {
		return;
	}

	$site = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	$data = null;

	if ( is_front_page() ) {
		$data = array(
			'@context' => 'https://schema.org',
			'@graph'   => array(
				array(
					'@type'       => 'WebSite',
					'name'        => $site,
					'url'         => home_url( '/' ),
					'inLanguage'  => 'ja',
					'description' => get_bloginfo( 'description' ),
				),
				array(
					'@type' => 'Organization',
					'name'  => $site,
					'url'   => home_url( '/' ),
					'logo'  => tbt_footer_logo(),
				),
			),
		);
	} elseif ( is_singular( 'story' ) ) {
		$id    = get_queried_object_id();
		$catch = tbt_field( 'headline', $id );
		$co    = tbt_field( 'company', $id );
		$role  = tbt_field( 'role', $id );

		$person = array(
			'@type' => 'Person',
			'name'  => get_the_title( $id ),
		);
		if ( $role ) {
			$person['jobTitle'] = $role;
		}
		if ( $co ) {
			$person['worksFor'] = array( '@type' => 'Organization', 'name' => $co );
		}

		$data = array(
			'@context'   => 'https://schema.org',
			'@type'      => 'Article',
			'headline'   => $catch ? preg_replace( '/\s*\R\s*/u', '', $catch ) : get_the_title( $id ),
			'description' => tbt_story_summary( $id ),
			'inLanguage' => 'ja',
			'image'      => tbt_singular_image( get_post( $id ) ),
			'mainEntityOfPage' => get_permalink( $id ),
			'about'      => $person,
			'publisher'  => array( '@type' => 'Organization', 'name' => $site ),
		);
	}

	if ( ! $data ) {
		return;
	}

	echo "\n" . '<script type="application/ld+json">' . "\n"
		. wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT )
		. "\n</script>\n";
}
