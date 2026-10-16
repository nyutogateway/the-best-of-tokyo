<?php
/**
 * THE BEST OF TOKYO 後世に残す言葉 テーマ functions
 *
 * 静的サイト（index.html / stories/*.html）と同じマークアップを WordPress から出します。
 * CSS・JS はそのまま流用しているため、class 名を変えると見た目が崩れます。
 */
if ( ! defined( 'ABSPATH' ) ) exit;

define( 'TBT_VER', '1.0.0' );

/* ------------------------------------------------------------------
 * テーマサポート
 * ---------------------------------------------------------------- */
function tbt_setup() {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );   // 掲載者のカード写真＝アイキャッチ（正方形）
	add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script' ) );
	add_theme_support( 'automatic-feed-links' );
	add_image_size( 'tbt-square', 640, 640, true );    // トップのカード
	add_image_size( 'tbt-portrait', 600, 800, true );  // 記事ヒーローの人物写真
	register_nav_menus( array(
		'primary' => 'ヘッダーナビ',
		'footer'  => 'フッターナビ',
	) );
}
add_action( 'after_setup_theme', 'tbt_setup' );

/* ------------------------------------------------------------------
 * CSS / JS 読み込み
 *   静的サイトと同じ 6 ファイル構成（FLOCSS）。ページ種別で出し分ける。
 * ---------------------------------------------------------------- */
function tbt_assets() {
	$uri = get_template_directory_uri();
	$dir = get_template_directory();
	// ファイル更新時刻をバージョンに使う（アップし直すたび自動でキャッシュ更新）
	$v = function ( $rel ) use ( $dir ) {
		$p = $dir . '/' . ltrim( $rel, '/' );
		return file_exists( $p ) ? (string) filemtime( $p ) : TBT_VER;
	};

	wp_enqueue_style(
		'tbt-gfonts',
		'https://fonts.googleapis.com/css2?family=Anton&family=Shippori+Mincho+B1:wght@400;600;800&family=Zen+Kaku+Gothic+New:wght@400;500;700&display=swap',
		array(),
		null
	);

	// 共通（foundation → layout → component の順。あとからページ固有を足す）
	$common = array( 'foundation', 'layout', 'component' );
	$deps   = array( 'tbt-gfonts' );
	foreach ( $common as $name ) {
		wp_enqueue_style( 'tbt-' . $name, $uri . '/assets/css/' . $name . '.css', $deps, $v( 'assets/css/' . $name . '.css' ) );
		$deps[] = 'tbt-' . $name;
	}

	// ページ固有
	if ( is_front_page() ) {
		$page = 'project-top';
	} elseif ( is_singular( 'story' ) ) {
		$page = 'project-article';
	} else {
		$page = 'project-page';
	}
	wp_enqueue_style( 'tbt-' . $page, $uri . '/assets/css/' . $page . '.css', $deps, $v( 'assets/css/' . $page . '.css' ) );

	wp_enqueue_script( 'tbt-main', $uri . '/assets/js/main.js', array(), $v( 'assets/js/main.js' ), true );
}
add_action( 'wp_enqueue_scripts', 'tbt_assets' );

/* ------------------------------------------------------------------
 * WordPress が自動で足す CSS・JS を止める
 *   このサイトのHTMLには <style> とインラインの <script> を置かない決まりです
 *   （構造化データの application/ld+json だけが例外）。
 *   ブロックエディタのスタイルも使っていないため、読み込む必要がありません。
 * ---------------------------------------------------------------- */
add_action( 'wp_enqueue_scripts', function () {
	wp_dequeue_style( 'wp-block-library' );
	wp_dequeue_style( 'wp-block-library-theme' );
	wp_dequeue_style( 'wc-blocks-style' );
	wp_dequeue_style( 'classic-theme-styles' );
	wp_dequeue_style( 'global-styles' );
}, 100 );

// ブロックごとのCSS・テーマ全体のCSS変数（どちらもインラインで入る）
add_action( 'init', function () {
	remove_action( 'wp_enqueue_scripts', 'wp_enqueue_global_styles' );
	remove_action( 'wp_footer', 'wp_enqueue_global_styles', 1 );
	remove_action( 'wp_body_open', 'wp_global_styles_render_svg_filters' );
} );
add_filter( 'should_load_separate_core_block_assets', '__return_false' );

// 絵文字の読み込み（インラインのCSS・JSが入るため）
add_action( 'init', function () {
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'wp_head', 'wp_enqueue_emoji_styles' );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
	remove_action( 'admin_print_styles', 'print_emoji_styles' );
	remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
	remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
	remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
} );

// 画像の sizes を補正するインラインCSS（WP 6.7〜）／先読みルールのJSON
add_filter( 'wp_img_tag_add_auto_sizes', '__return_false' );
add_filter( 'wp_speculation_rules_configuration', '__return_null' );

/* ------------------------------------------------------------------
 * Google Fonts の先読み
 * ---------------------------------------------------------------- */
add_filter( 'wp_resource_hints', function ( $urls, $relation ) {
	if ( 'preconnect' === $relation ) {
		$urls[] = array( 'href' => 'https://fonts.gstatic.com', 'crossorigin' );
	}
	return $urls;
}, 10, 2 );

/* ------------------------------------------------------------------
 * inc/ 配下を読み込む（万一ファイルが未アップロードでもサイトを落とさない）
 * ---------------------------------------------------------------- */
foreach ( array( 'cpt', 'contact-form', 'ogp', 'setup-pages', 'reorder', 'docx-import' ) as $tbt_inc ) {
	$tbt_inc_path = get_template_directory() . '/inc/' . $tbt_inc . '.php';
	if ( file_exists( $tbt_inc_path ) ) {
		require_once $tbt_inc_path;
	}
}
unset( $tbt_inc, $tbt_inc_path );

/* ------------------------------------------------------------------
 * ACF ローカルJSON（同梱フィールド群を自動読込。インポート作業は不要）
 * ---------------------------------------------------------------- */
add_filter( 'acf/settings/save_json', function () {
	return get_stylesheet_directory() . '/acf-json';
} );
add_filter( 'acf/settings/load_json', function ( $paths ) {
	$paths[] = get_stylesheet_directory() . '/acf-json';
	return $paths;
} );

/* ------------------------------------------------------------------
 * ヘルパー: ACF get_field の安全ラッパー（ACF未導入でも致命エラーにしない）
 * ---------------------------------------------------------------- */
function tbt_field( $name, $post_id = false ) {
	if ( function_exists( 'get_field' ) ) {
		return get_field( $name, $post_id );
	}
	$post_id = $post_id ? $post_id : get_the_ID();
	return $post_id ? get_post_meta( $post_id, $name, true ) : '';
}

/* ==================================================================
 * カスタマイザー（ロゴ・文言）
 * ============================================================== */
add_action( 'customize_register', function ( $wp_customize ) {

	/* ---- ロゴ ---- */
	$wp_customize->add_section( 'tbt_logos', array(
		'title'       => 'サイトロゴ',
		'description' => 'ヘッダー・ファーストビュー・フッターのロゴ画像。未設定ならテーマ同梱のロゴを使います。',
		'priority'    => 30,
	) );
	$logos = array(
		'tbt_header_logo'  => array( 'ヘッダーロゴ（白＋金・横組み）', '全ページ左上と、スマホのメニュー内に出るロゴ。' ),
		'tbt_hero_logo'    => array( 'FVロゴ（トップ中央）', 'トップのファーストビュー中央に出るワードマーク。黒地に映える色のものを。' ),
		'tbt_footer_logo'  => array( 'フッターロゴ（黒＋金）', 'フッターは白地です。濃い色のロゴを入れてください。' ),
		'tbt_station_logo' => array( '放送局ロゴ（TOKYO MX）', 'メニュー・放送局セクション・フッターに出ます。' ),
	);
	foreach ( $logos as $key => $meta ) {
		$wp_customize->add_setting( $key, array(
			'default'           => '',
			'sanitize_callback' => 'esc_url_raw',
			'transport'         => 'refresh',
		) );
		$wp_customize->add_control( new WP_Customize_Image_Control( $wp_customize, $key, array(
			'label'       => $meta[0],
			'description' => $meta[1],
			'section'     => 'tbt_logos',
			'settings'    => $key,
		) ) );
	}

	/* ---- サイト文言 ---- */
	$wp_customize->add_section( 'tbt_copy', array(
		'title'       => 'サイト文言',
		'description' => 'トップページの文言。空欄にすると既定の文言に戻ります。',
		'priority'    => 31,
	) );

	$texts = array(
		'tbt_hero_text' => array(
			'label' => 'FVのリード文',
			'desc'  => 'ロゴの下に出る2行。改行したいところで Enter を押してください。',
			'type'  => 'textarea',
			'def'   => tbt_default( 'hero_text' ),
		),
		'tbt_concept_lead' => array(
			'label' => 'ABOUT の大きな一文',
			'desc'  => "1行＝表示上の1行。行の中で折り返したくないまとまりは「｜」で区切ります。《 》で囲むと金色の強調になります。",
			'type'  => 'textarea',
			'def'   => tbt_default( 'concept_lead' ),
		),
		'tbt_concept_body' => array(
			'label' => 'ABOUT の本文',
			'desc'  => '書き方は上と同じ（1行＝1行、「｜」で区切り、《 》で強調）。',
			'type'  => 'textarea',
			'def'   => tbt_default( 'concept_body' ),
		),
		'tbt_concept_closing' => array(
			'label' => 'ABOUT の締めの文',
			'desc'  => '書き方は上と同じ。',
			'type'  => 'textarea',
			'def'   => tbt_default( 'concept_closing' ),
		),
		'tbt_station_name' => array(
			'label' => '放送局名',
			'desc'  => '例: TOKYO MX',
			'type'  => 'text',
			'def'   => tbt_default( 'station_name' ),
		),
		'tbt_station_text' => array(
			'label' => '放送局の紹介文',
			'desc'  => '放送局セクションの本文。',
			'type'  => 'textarea',
			'def'   => tbt_default( 'station_text' ),
		),
		'tbt_station_area' => array(
			'label' => '放送エリア',
			'desc'  => '例: 東京都（地上デジタル9ch）',
			'type'  => 'text',
			'def'   => tbt_default( 'station_area' ),
		),
		'tbt_station_url' => array(
			'label' => '放送局の公式サイト URL',
			'desc'  => '例: https://s.mxtv.jp/',
			'type'  => 'text',
			'def'   => tbt_default( 'station_url' ),
		),
		'tbt_footer_copy' => array(
			'label' => 'フッターの表記',
			'desc'  => '改行できます。',
			'type'  => 'textarea',
			'def'   => tbt_default( 'footer_copy' ),
		),
	);
	foreach ( $texts as $key => $meta ) {
		$wp_customize->add_setting( $key, array(
			'default'           => $meta['def'],
			'sanitize_callback' => 'textarea' === $meta['type'] ? 'sanitize_textarea_field' : 'sanitize_text_field',
			'transport'         => 'refresh',
		) );
		$wp_customize->add_control( $key, array(
			'label'       => $meta['label'],
			'description' => $meta['desc'],
			'type'        => $meta['type'],
			'section'     => 'tbt_copy',
		) );
	}

	/* ---- 放送エリア図 ---- */
	$wp_customize->add_setting( 'tbt_area_map', array(
		'default'           => '',
		'sanitize_callback' => 'esc_url_raw',
		'transport'         => 'refresh',
	) );
	$wp_customize->add_control( new WP_Customize_Image_Control( $wp_customize, 'tbt_area_map', array(
		'label'       => '放送エリア図',
		'description' => '未設定ならテーマ同梱のベクター図（assets/img/area-map.svg）を使います。差し替えるときは出典表記もフッターで直してください。',
		'section'     => 'tbt_copy',
		'settings'    => 'tbt_area_map',
	) ) );
} );

/** 既定の文言（カスタマイザーが空欄のときに使う） */
function tbt_default( $key ) {
	$d = array(
		'hero_text'       => "東京の第一線で挑む人々の哲学とストーリーを、\n未来へ届けるインタビューメディア。",
		'concept_lead'    => "東京の地には、\n次代を担う｜リーダーたちが｜存在する。",
		'concept_body'    => "ビジネスの最前線で｜挑み続ける経営者や、\n表現の可能性を広げる｜アーティスト、\n受け継がれてきた技を守る｜担い手など……\n彼らの哲学とストーリーに｜焦点を当てた企画。",
		'concept_closing' => "時代を超えて受け継がれる｜《「一生モノの言葉」》を、\n未来へ届けていきます。",
		'station_name'    => 'TOKYO MX',
		'station_text'    => '1995年11月1日に開局した、東京都を放送対象地域とする独立放送局です。首都圏の暮らしに根ざした情報番組から、スポーツ、アニメ、東京都議会中継まで、この都市そのものを映す編成を続けています。「THE BEST OF TOKYO 後世に残す言葉」は、同局の公式サイトと連動して展開するインタビューメディアです。',
		'station_area'    => '東京都（地上デジタル9ch）',
		'station_url'     => 'https://s.mxtv.jp/',
		'footer_copy'     => '©THE BEST OF TOKYO All Rights Reserved.',
	);
	return isset( $d[ $key ] ) ? $d[ $key ] : '';
}

/** カスタマイザーの文言（空欄なら既定値） */
function tbt_text( $key ) {
	$v = trim( (string) get_theme_mod( 'tbt_' . $key, tbt_default( $key ) ) );
	return '' !== $v ? $v : tbt_default( $key );
}

/** カスタマイザーの画像URL（未設定ならテーマ同梱の既定画像） */
function tbt_image_url( $mod_key, $default_asset ) {
	$url = get_theme_mod( $mod_key, '' );
	if ( ! $url ) {
		$url = get_template_directory_uri() . '/assets/img/' . $default_asset;
	}
	return $url;
}
function tbt_header_logo()  { return tbt_image_url( 'tbt_header_logo',  'logo-white-gold.webp' ); }
function tbt_hero_logo()    { return tbt_image_url( 'tbt_hero_logo',    'wordmark-blackgold.webp' ); }
function tbt_footer_logo()  { return tbt_image_url( 'tbt_footer_logo',  'logo-blackgold.webp' ); }
function tbt_station_logo() { return tbt_image_url( 'tbt_station_logo', 'tokyo-mx.svg' ); }
function tbt_area_map()     { return tbt_image_url( 'tbt_area_map',     'area-map.svg' ); }

/**
 * カスタマイザーの文章を「行（.p-concept__line）＋まとまり（.p-concept__phrase）」に組む
 *   1行＝1行、行の中は「｜」で区切り、《 》で囲んだところは <em>（金色）になります。
 */
function tbt_lines_html( $raw, $base = 'p-concept' ) {
	$out = '';
	foreach ( preg_split( '/\R/u', (string) $raw ) as $line ) {
		$line = trim( $line );
		if ( '' === $line ) {
			continue;
		}
		$phrases = array_values( array_filter( array_map( 'trim', explode( '｜', $line ) ), 'strlen' ) );
		$inner   = '';
		if ( count( $phrases ) > 1 ) {
			foreach ( $phrases as $p ) {
				$inner .= '<span class="' . esc_attr( $base . '__phrase' ) . '">' . tbt_em_html( $p ) . '</span>';
			}
		} else {
			$inner = tbt_em_html( $phrases ? $phrases[0] : '' );
		}
		$out .= '<span class="' . esc_attr( $base . '__line' ) . '">' . $inner . '</span>';
	}
	return $out;
}

/** 《 》で囲んだところを <em> にする（それ以外はすべてエスケープ） */
function tbt_em_html( $text ) {
	$parts = preg_split( '/(《[^》]*》)/u', (string) $text, -1, PREG_SPLIT_DELIM_CAPTURE );
	$out   = '';
	foreach ( $parts as $p ) {
		if ( '' === $p ) {
			continue;
		}
		if ( preg_match( '/^《(.*)》$/u', $p, $m ) ) {
			$out .= '<em>' . esc_html( $m[1] ) . '</em>';
		} else {
			$out .= esc_html( $p );
		}
	}
	return $out;
}

/* ==================================================================
 * 掲載者・業種の取り出し
 * ============================================================== */

/** 掲載者すべて（menu_order 昇順）。記事ページの MORE STORIES などで共通に使う */
function tbt_story_ids() {
	static $ids = null;
	if ( null !== $ids ) {
		return $ids;
	}
	$ids = get_posts( array(
		'post_type'      => 'story',
		'posts_per_page' => -1,
		'orderby'        => 'menu_order date',
		'order'          => 'ASC',
		'post_status'    => 'publish',
		'fields'         => 'ids',
		'no_found_rows'  => true,
	) );
	return $ids;
}

/** トップのカードは1グループ5名（CSSが5列で組んであるため） */
function tbt_cards_per_group() {
	return (int) apply_filters( 'tbt_cards_per_group', 5 );
}

/**
 * 業種（トップの GROUP 01〜）
 *   表示順（タームの「表示順」→ 無ければ名前順）に並べ、掲載者のいない業種は外す。
 *
 * @return array array( array( 'no' => '01', 'en' => 'MEDICAL', 'ja' => '医療', 'slug' => …, 'ids' => int[] ) )
 */
function tbt_industries() {
	static $groups = null;
	if ( null !== $groups ) {
		return $groups;
	}

	$groups = array();
	if ( ! taxonomy_exists( 'industry' ) ) {
		return $groups;
	}

	$terms = get_terms( array(
		'taxonomy'   => 'industry',
		'hide_empty' => true,
	) );
	if ( is_wp_error( $terms ) || ! $terms ) {
		return $groups;
	}

	// 「表示順」で並べる（未入力は後ろ）
	usort( $terms, function ( $a, $b ) {
		$oa = get_term_meta( $a->term_id, 'tbt_order', true );
		$ob = get_term_meta( $b->term_id, 'tbt_order', true );
		$oa = '' === (string) $oa ? PHP_INT_MAX : (int) $oa;
		$ob = '' === (string) $ob ? PHP_INT_MAX : (int) $ob;
		if ( $oa === $ob ) {
			return strcmp( $a->name, $b->name );
		}
		return $oa < $ob ? -1 : 1;
	} );

	$no = 0;
	foreach ( $terms as $term ) {
		$ids = get_posts( array(
			'post_type'      => 'story',
			'posts_per_page' => -1,
			'orderby'        => 'menu_order date',
			'order'          => 'ASC',
			'post_status'    => 'publish',
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'tax_query'      => array(
				array( 'taxonomy' => 'industry', 'field' => 'term_id', 'terms' => $term->term_id ),
			),
		) );
		if ( ! $ids ) {
			continue;
		}
		$no++;
		$en = (string) get_term_meta( $term->term_id, 'tbt_en', true );
		$groups[] = array(
			'no'   => sprintf( '%02d', $no ),
			'id'   => 'spot' . $no,
			'en'   => '' !== $en ? $en : strtoupper( $term->slug ),
			'ja'   => $term->name,
			'slug' => $term->slug,
			'ids'  => $ids,
		);
	}
	return $groups;
}

/** その掲載者が属する業種（最初の1件。記事ページの MORE STORIES に使う） */
function tbt_story_industry( $post_id ) {
	foreach ( tbt_industries() as $i => $group ) {
		if ( in_array( (int) $post_id, array_map( 'intval', $group['ids'] ), true ) ) {
			$group['index'] = $i + 1;
			return $group;
		}
	}
	return null;
}

/* ==================================================================
 * 部品（カード・ロゴ）
 * ============================================================== */

/** 企業ロゴのURL（ACF「企業ロゴ」） */
function tbt_logo_src( $post_id ) {
	$logo = tbt_field( 'logo', $post_id );
	return is_string( $logo ) ? $logo : '';
}

/** カードの正方形写真（アイキャッチ） */
function tbt_square_src( $post_id ) {
	$url = get_the_post_thumbnail_url( $post_id, 'tbt-square' );
	return $url ? $url : '';
}

/**
 * 英字名を2行に折る
 *   「hiroki hara」→「hiroki<br>hara」。3語以上なら最後の語だけ2行目に置く。
 */
function tbt_en_two_lines( $en ) {
	$en = trim( preg_replace( '/\s+/u', ' ', (string) $en ) );
	if ( '' === $en ) {
		return '';
	}
	$pos = strrpos( $en, ' ' );
	if ( false === $pos ) {
		return esc_html( $en );
	}
	return esc_html( substr( $en, 0, $pos ) ) . '<br>' . esc_html( substr( $en, $pos + 1 ) );
}

/**
 * カード1枚（トップの .p-card と記事ページの .p-related__card は同じ作り）
 *
 * @param int    $post_id 掲載者の投稿ID
 * @param string $base    'p-card' か 'p-related'
 */
function tbt_card_html( $post_id, $base = 'p-card' ) {
	$card_class = 'p-related' === $base ? 'p-related__card' : 'p-card';
	$logo       = tbt_logo_src( $post_id );
	$photo      = tbt_square_src( $post_id );
	$name       = get_the_title( $post_id );
	$en         = tbt_field( 'en_name', $post_id );
	$company    = tbt_field( 'company', $post_id );
	$role       = tbt_field( 'role', $post_id );

	$html  = '<a class="' . esc_attr( $card_class ) . '" href="' . esc_url( get_permalink( $post_id ) ) . '">';
	$html .= '<span class="' . esc_attr( $base . '__visual' ) . '">';
	if ( $logo ) {
		$html .= '<img class="' . esc_attr( $base . '__logo' ) . '" src="' . esc_url( $logo ) . '" alt="" loading="lazy" decoding="async">';
	}
	if ( $photo ) {
		$html .= '<img src="' . esc_url( $photo ) . '" alt="' . esc_attr( $name ) . '" width="640" height="640" loading="lazy" decoding="async">';
	}
	$html .= '</span>';

	$html .= '<span class="' . esc_attr( $base . '__body' ) . '">';
	if ( $en ) {
		$html .= '<span class="' . esc_attr( $base . '__romaji' ) . '">' . tbt_en_two_lines( $en ) . '</span>';
	}
	$html .= '<span class="' . esc_attr( $base . '__name' ) . '">' . esc_html( $name ) . '</span>';
	if ( $company || $role ) {
		$html .= '<span class="' . esc_attr( $base . '__company' ) . '">';
		if ( $company ) {
			$html .= '<span class="' . esc_attr( $base . '__corp' ) . '">' . esc_html( $company ) . '</span>';
		}
		if ( $role ) {
			$html .= '<span class="' . esc_attr( $base . '__role' ) . '">' . esc_html( $role ) . '</span>';
		}
		$html .= '</span>';
	}
	$html .= '</span></a>';

	return $html;
}

/**
 * グループ切り替えの NEXT ボタン（同じ行のもう一方の業種を名指しする）
 *
 * @param array $next tbt_industries() の1件
 */
function tbt_group_next_button( $next ) {
	?>
	<button class="p-group__next" type="button" data-row-next>
		<span class="p-group__nextLead">NEXT</span>
		<span class="p-group__nextNo"><?php echo esc_html( $next['no'] ); ?></span>
		<span class="p-group__nextName"><?php echo esc_html( $next['en'] ); ?><span class="p-group__nextJa"><?php echo esc_html( $next['ja'] ); ?></span></span>
		<span class="p-group__arrow" aria-hidden="true"></span>
	</button>
	<?php
}

/* ==================================================================
 * トップの帯（FVのロゴの壁／流れるロゴ）
 * ============================================================== */

/** 掲載者の企業ロゴURL一覧（登録が無いあいだはテーマ同梱のサンプルロゴ） */
function tbt_logo_list() {
	static $logos = null;
	if ( null !== $logos ) {
		return $logos;
	}

	$logos = array();
	foreach ( tbt_story_ids() as $id ) {
		$url = tbt_logo_src( $id );
		if ( $url ) {
			$logos[] = array( 'url' => $url, 'alt' => (string) tbt_field( 'company', $id ) );
		}
	}
	if ( $logos ) {
		return $logos;
	}

	// 未登録のあいだは同梱のサンプルで壁を組む（空の黒い壁にしないため）
	$dir = get_template_directory() . '/assets/img/logos';
	$uri = get_template_directory_uri() . '/assets/img/logos';
	foreach ( (array) glob( $dir . '/logo-*.webp' ) as $file ) {
		$logos[] = array( 'url' => $uri . '/' . basename( $file ), 'alt' => '' );
	}
	return $logos;
}

/**
 * FV背面のロゴの壁（4列）
 *   CSS は「同じ並びを2セット並べて -50% 動かす」前提。1セットが画面より高くなるよう
 *   TBT_TILE_ROWS 枚まで繰り返してから、そのセットを2回出します。
 */
define( 'TBT_TILE_COLS', 4 );
define( 'TBT_TILE_ROWS', 22 );

function tbt_hero_tiles_html() {
	$logos = tbt_logo_list();
	if ( ! $logos ) {
		return '';
	}

	$mods = array( '', ' p-hero__col--down', ' p-hero__col--third', ' p-hero__col--fourth' );
	$n    = count( $logos );
	$html = '';

	for ( $c = 0; $c < TBT_TILE_COLS; $c++ ) {
		// 列ごとに拾いはじめをずらし、4枚とばしで拾う（隣の列と同じ並びにならないように）
		$set = array();
		for ( $r = 0; $r < TBT_TILE_ROWS; $r++ ) {
			$set[] = $logos[ ( $c + $r * TBT_TILE_COLS ) % $n ];
		}

		$cells = '';
		foreach ( array_merge( $set, $set ) as $logo ) {   // 2セット＝ループ1周ぶん
			$cells .= '<img src="' . esc_url( $logo['url'] ) . '" alt="">';
		}
		$html .= '<div class="p-hero__col' . $mods[ $c ] . '">' . $cells . '</div>';
	}
	return $html;
}

/**
 * 流れるロゴの帯（.p-logos）
 *   CSS は「同じ並びを2セット」で1周するので、全ロゴ×2を出します。
 *
 * @param bool $rev 逆向きの帯（並びも逆順）
 */
function tbt_logos_band_html( $rev = false ) {
	$logos = tbt_logo_list();
	if ( ! $logos ) {
		return '';
	}
	if ( $rev ) {
		$logos = array_reverse( $logos );
	}

	$items = '';
	foreach ( array_merge( $logos, $logos ) as $logo ) {
		$items .= '<li class="p-logos__item"><img src="' . esc_url( $logo['url'] ) . '" alt="' . esc_attr( $logo['alt'] ) . '" loading="lazy" decoding="async"></li>';
	}

	return '<div class="p-logos' . ( $rev ? ' p-logos--rev' : '' ) . '">'
		. '<div class="p-logos__viewport"><ul class="p-logos__track">' . $items . '</ul></div></div>';
}

/* ==================================================================
 * 記事本文の変換
 *   編集者は「見出し(H2)」で章を区切り、段落を「Q：」「A：」で書くだけ。
 *   ここで静的サイトと同じ .p-chapter / .p-qa 構造に組み直します。
 *
 *   - H2 ごとに <section class="p-chapter">（ラベルは CHAPTER 01, 02 …）
 *   - 最後の章は <section class="p-chapter p-chapter--message">（ラベルが MESSAGE）
 *   - 章の中の画像は <figure class="p-figure">（見出しの直後に移動）
 *   - 「Q：」「A：」で始まる段落は Q&A（.p-qa__item）に組む
 *   - 2章目の下に写真の帯（ACF「流し写真」）を自動で挟む
 * ============================================================== */
add_filter( 'the_content', 'tbt_build_article', 20 );

function tbt_build_article( $content ) {
	if ( ! is_singular( 'story' ) || ! in_the_loop() || ! is_main_query() ) {
		return $content;
	}

	// H2 で章に割る（[前置き, 見出し1, 本文1, 見出し2, 本文2, ...]）
	$parts = preg_split( '#<h2\b[^>]*>(.*?)</h2>#is', $content, -1, PREG_SPLIT_DELIM_CAPTURE );
	if ( ! $parts ) {
		return $content;
	}

	$intro = array_shift( $parts );
	$out   = '';

	if ( count( $parts ) < 2 ) {
		// 見出しが無い原稿は、章立てせず1枚の塊として出す
		$body = trim( $intro );
		return '' === $body ? '' : tbt_chapter_html( '', $body, 0 );
	}

	if ( '' !== trim( wp_strip_all_tags( $intro ) ) || false !== strpos( $intro, '<img' ) ) {
		$out .= tbt_chapter_html( '', $intro, 0 );   // 見出しの無い前置き
	}

	$marquee  = tbt_marquee_html( get_the_ID() );
	$sections = (int) floor( count( $parts ) / 2 );

	for ( $i = 0; $i < $sections; $i++ ) {
		$title = $parts[ $i * 2 ];
		$body  = isset( $parts[ $i * 2 + 1 ] ) ? $parts[ $i * 2 + 1 ] : '';
		$last  = ( $i === $sections - 1 );
		$out  .= tbt_chapter_html( $title, $body, $last ? 0 : $i + 1 );

		// 静的サイトと同じ位置（2章目の下）に写真の帯を挟む
		if ( 1 === $i && '' !== $marquee ) {
			$out    .= $marquee;
			$marquee = '';
		}
	}
	if ( '' !== $marquee ) {
		$out .= $marquee;
	}

	return $out;
}

/**
 * 章ひとつぶんの HTML
 *
 * @param string $title 見出し（空なら見出し・ラベルを出さない）
 * @param string $body  章の本文HTML
 * @param int    $no    章番号（1〜）。0 なら MESSAGE 章として組む
 */
function tbt_chapter_html( $title, $body, $no = 0 ) {
	$figure = '';
	$body   = tbt_extract_images( $body, $figure );

	$is_msg = ( 0 === (int) $no );
	$cls    = 'p-chapter' . ( $is_msg && '' !== $title ? ' p-chapter--message' : '' );

	$html = '<section class="' . esc_attr( $cls ) . '">';
	if ( '' !== $title ) {
		$label = $is_msg ? 'MESSAGE' : sprintf( 'CHAPTER %02d', (int) $no );
		$html .= '<p class="p-chapter__label">' . esc_html( $label ) . '</p>';
		$html .= '<h2 class="p-chapter__title">' . $title . '</h2>';
	}
	$html .= $figure;
	$html .= tbt_qa_html( $body );
	$html .= '</section>';

	return $html;
}

/**
 * 段落を Q&A（.p-qa）と地の文（.p-chapter__text）に組み直す
 *   「Q：」で始まる段落＝質問、「A：」で始まる段落＝回答。
 *   回答に続く「A：」なしの段落は、同じ回答の続きとして <br> でつなぎます。
 */
function tbt_qa_html( $body ) {
	// 段落・それ以外（リストなど）を順番に取り出す
	$chunks = preg_split( '#(<p\b[^>]*>.*?</p>)#is', $body, -1, PREG_SPLIT_DELIM_CAPTURE );

	$out   = '';
	$items = array();   // 組み立て中の Q&A
	$state = '';        // '' / 'q' / 'a'

	$flush = function () use ( &$items, &$out, &$state ) {
		if ( $items ) {
			$out  .= '<div class="p-qa">' . implode( '', $items ) . '</div>';
			$items = array();
		}
		$state = '';
	};

	foreach ( $chunks as $chunk ) {
		if ( '' === trim( $chunk ) ) {
			continue;
		}

		if ( preg_match( '#^<p\b[^>]*>(.*?)</p>$#is', trim( $chunk ), $m ) ) {
			$inner = trim( $m[1] );
			$plain = trim( wp_strip_all_tags( $inner ) );
			if ( '' === $plain ) {
				continue;
			}

			if ( preg_match( '/^[QqＱｑ][0-9０-９]*\s*[:：]\s*/u', $plain ) ) {
				$text    = preg_replace( '/^[QqＱｑ][0-9０-９]*\s*[:：]\s*/u', '', $inner, 1 );
				$items[] = '<div class="p-qa__item"><p class="p-qa__q"><span class="p-qa__mark">Q</span><span>' . $text . '</span></p>';
				$state   = 'q';
				continue;
			}
			if ( preg_match( '/^[AaＡａ][0-9０-９]*\s*[:：]\s*/u', $plain ) && 'q' === $state ) {
				$text = preg_replace( '/^[AaＡａ][0-9０-９]*\s*[:：]\s*/u', '', $inner, 1 );
				$items[ count( $items ) - 1 ] .= '<p class="p-qa__a"><span class="p-qa__mark">A</span><span>' . $text . '</span></p></div>';
				$state = 'a';
				continue;
			}
			if ( 'a' === $state ) {
				// 回答の続き（「A：」なしの2段落目以降）
				$i = count( $items ) - 1;
				$items[ $i ] = preg_replace( '#</span></p></div>$#', '<br>' . $inner . '</span></p></div>', $items[ $i ] );
				continue;
			}

			$flush();
			$out .= '<p class="p-chapter__text">' . $inner . '</p>';
			continue;
		}

		$flush();
		$out .= $chunk;
	}

	// 回答の来ないまま終わった Q は、そのまま閉じる
	if ( 'q' === $state ) {
		$items[ count( $items ) - 1 ] .= '</div>';
	}
	$flush();

	return $out;
}

/**
 * 本文の画像を取り出して <figure class="p-figure"> にする（見出しの直後に置くため）
 *
 * @param string $body   本文HTML
 * @param string $figure （参照）できあがった figure
 * @return string 画像を取り除いた本文HTML
 */
function tbt_extract_images( $body, &$figure ) {
	$figure   = '';
	$patterns = array(
		'#<figure\b[^>]*>\s*(?:<a\b[^>]*>\s*)?<img\b[^>]*>.*?</figure>#is',
		'#<p\b[^>]*>\s*(?:<a\b[^>]*>\s*)?<img\b[^>]*>\s*(?:</a>\s*)?</p>#is',
		'#<img\b[^>]*>#i',
	);

	foreach ( $patterns as $pat ) {
		while ( preg_match( $pat, $body, $m ) ) {
			if ( ! preg_match( '#<img\b[^>]*>#i', $m[0], $im ) ) {
				break;
			}
			$img = preg_replace( '/\sclass\s*=\s*("[^"]*"|\'[^\']*\')/i', '', $im[0] );
			if ( ! preg_match( '/\bloading\s*=/i', $img ) ) {
				$img = preg_replace( '#^<img\b#i', '<img loading="lazy" decoding="async"', $img );
			}
			$figure .= '<figure class="p-figure">' . $img . '</figure>';

			$pos  = strpos( $body, $m[0] );
			$body = substr_replace( $body, '', $pos, strlen( $m[0] ) );
		}
	}

	return $body;
}

/* ------------------------------------------------------------------
 * 写真の帯（記事の2章目の下を横に流れる）
 *   ACF「流し写真 1〜8」（band_1 … band_8）
 * ---------------------------------------------------------------- */
define( 'TBT_BAND_MAX', 8 );

function tbt_marquee_html( $post_id ) {
	$photos = array();
	for ( $i = 1; $i <= TBT_BAND_MAX; $i++ ) {
		$url = tbt_field( 'band_' . $i, $post_id );
		if ( $url ) {
			$photos[] = $url;
		}
	}
	if ( ! $photos ) {
		return '';
	}

	$cells = '';
	foreach ( $photos as $url ) {
		$cells .= '<li><img src="' . esc_url( $url ) . '" alt="" loading="lazy" decoding="async"></li>';
	}

	// 同じ並びを2セット並べて途切れなく流す（2セット目は読み上げから外す）
	return '<div class="p-marquee" data-marquee><div class="p-marquee__track">'
		. '<ul class="p-marquee__set" aria-label="' . esc_attr( get_the_title( $post_id ) . ' の取材写真' ) . '">' . $cells . '</ul>'
		. '<ul class="p-marquee__set" aria-hidden="true">' . $cells . '</ul>'
		. '</div></div>';
}

/* ==================================================================
 * 固定ページの本文を .p-page__block に組み直す
 *   H2 ごとにブロックへ。最初の見出しより前の段落はリード文になります。
 * ============================================================== */
add_filter( 'the_content', 'tbt_build_page', 20 );

function tbt_build_page( $content ) {
	if ( ! is_page() || ! in_the_loop() || ! is_main_query() ) {
		return $content;
	}
	// お問い合わせページはフォームが主役。導入文だけリード文にして、あとは触らない
	if ( is_page( 'contact' ) ) {
		return preg_replace( '#^(\s*)<p\b[^>]*>(.*?)</p>#is', '$1<p class="p-page__lead">$2</p>', $content, 1 );
	}

	$parts = preg_split( '#<h2\b[^>]*>(.*?)</h2>#is', $content, -1, PREG_SPLIT_DELIM_CAPTURE );
	if ( ! $parts || count( $parts ) < 2 ) {
		return tbt_page_lead( $content );
	}

	$out = tbt_page_lead( array_shift( $parts ) );

	$sections = (int) floor( count( $parts ) / 2 );
	for ( $i = 0; $i < $sections; $i++ ) {
		$title = $parts[ $i * 2 ];
		$body  = isset( $parts[ $i * 2 + 1 ] ) ? $parts[ $i * 2 + 1 ] : '';
		$body  = preg_replace( '#<(ol|ul)\b([^>]*)>#i', '<$1 class="p-page__list"$2>', $body );
		$out  .= '<div class="p-page__block"><h2 class="p-page__heading">' . $title . '</h2>' . $body . '</div>';
	}
	return $out;
}

/** 最初の見出しより前は、1段落目だけリード文にして残りはブロックに入れる */
function tbt_page_lead( $html ) {
	$html = trim( (string) $html );
	if ( '' === $html ) {
		return '';
	}
	if ( preg_match( '#^<p\b([^>]*)>(.*?)</p>#is', $html, $m ) ) {
		$lead = '<p class="p-page__lead">' . $m[2] . '</p>';
		$rest = trim( substr( $html, strlen( $m[0] ) ) );
		return $lead . ( '' !== $rest ? '<div class="p-page__block">' . $rest . '</div>' : '' );
	}
	return '<div class="p-page__block">' . $html . '</div>';
}

/* ==================================================================
 * その他
 * ============================================================== */

/** 記事ごとのCSS用クラスを body に付ける（例: tbt-story-hara-hiroki） */
add_filter( 'body_class', function ( $classes ) {
	if ( ! is_singular( 'story' ) ) {
		return $classes;
	}
	$slug = get_post_field( 'post_name', get_queried_object_id() );
	if ( $slug ) {
		$classes[] = sanitize_html_class( 'tbt-story-' . $slug );
	}
	return $classes;
} );

/** タイトルタグの区切りは「｜」（静的サイトと同じ体裁） */
add_filter( 'document_title_separator', function () {
	return '｜';
} );

/** 記事のタイトルタグは「キャッチコピー｜サイト名」にする（静的サイトと同じ体裁） */
add_filter( 'document_title_parts', function ( $parts ) {
	if ( ! is_singular( 'story' ) ) {
		return $parts;
	}
	$catch = tbt_field( 'headline', get_queried_object_id() );
	if ( $catch ) {
		$parts['title'] = preg_replace( '/\s*\R\s*/u', '', $catch );
	}
	return $parts;
} );

/** 記事の説明文「氏名（会社 役職）— キャッチ」。OGP は inc/ogp.php が出力します */
function tbt_story_summary( $post_id ) {
	$name  = get_the_title( $post_id );
	$co    = tbt_field( 'company', $post_id );
	$role  = tbt_field( 'role', $post_id );
	$catch = tbt_field( 'headline', $post_id );

	$paren = trim( $co . ( $co && $role ? ' ' : '' ) . $role );
	$out   = $name . ( $paren ? '（' . $paren . '）' : '' );
	if ( $catch ) {
		$out .= ' — ' . preg_replace( '/\s*\R\s*/u', '', $catch );
	}
	return $out;
}

/** 固定ページの英字見出し（ACF「英字見出し」→ 既定 → スラッグを大文字に） */
function tbt_page_en( $post_id, $default = '' ) {
	$en = tbt_field( 'page_en', $post_id );
	if ( $en ) {
		return $en;
	}
	if ( $default ) {
		return $default;
	}
	$slug = get_post_field( 'post_name', $post_id );
	return strtoupper( str_replace( array( '-', '_' ), ' ', $slug ) );
}

/** お問い合わせの「種類」選択肢（add_filter( 'tbt_contact_types', … ) で差し替え可） */
function tbt_contact_types() {
	return (array) apply_filters( 'tbt_contact_types', array(
		'掲載についてのご相談',
		'取材・出演のご依頼',
		'掲載中の記事について',
		'その他',
	) );
}

/* ------------------------------------------------------------------
 * Contact Form 7 連携
 *   固定ページ本文に [contact-form-7 …] を貼ると、page-contact.php 側で
 *   テーマ内蔵フォームが自動的に無効になります。
 * ---------------------------------------------------------------- */
function tbt_has_cf7( $content ) {
	$content = (string) $content;
	if ( '' === $content ) {
		return false;
	}
	return has_shortcode( $content, 'contact-form-7' ) || false !== strpos( $content, 'wp:contact-form-7/' );
}

// CF7 が勝手に <p> / <br> を入れるのを止める（テーマ側のマークアップで組むため）
add_filter( 'wpcf7_autop_or_not', '__return_false' );

// すべての CF7 フォームにテーマの意匠クラス .p-form を自動付与する
add_filter( 'wpcf7_form_class_attr', function ( $class ) {
	$classes = preg_split( '/\s+/', trim( (string) $class ), -1, PREG_SPLIT_NO_EMPTY );
	if ( ! in_array( 'p-form', $classes, true ) ) {
		$classes[] = 'p-form';
	}
	return implode( ' ', $classes );
} );

// フォームが無いページでは CF7 の CSS/JS を読み込まない
add_action( 'wp_enqueue_scripts', function () {
	if ( ! defined( 'WPCF7_VERSION' ) ) {
		return;
	}
	if ( is_page( 'contact' ) ) {
		return;
	}
	if ( is_singular() && tbt_has_cf7( get_post_field( 'post_content', get_queried_object_id() ) ) ) {
		return;
	}
	wp_dequeue_style( 'contact-form-7' );
	wp_dequeue_script( 'contact-form-7' );
	wp_dequeue_script( 'swv' );
}, 99 );

/* ------------------------------------------------------------------
 * 管理画面
 * ---------------------------------------------------------------- */

/** 管理画面用のCSS（原稿取り込み・並び替え） */
add_action( 'admin_enqueue_scripts', function () {
	$dir  = get_template_directory();
	$file = '/assets/css/admin.css';
	wp_enqueue_style(
		'tbt-admin',
		get_template_directory_uri() . $file,
		array(),
		file_exists( $dir . $file ) ? (string) filemtime( $dir . $file ) : TBT_VER
	);
} );

/** ACF未導入なら通知 */
add_action( 'admin_notices', function () {
	if ( ! class_exists( 'ACF' ) ) {
		echo '<div class="notice notice-warning"><p><strong>THE BEST OF TOKYO 後世に残す言葉 テーマ:</strong> プラグイン「Advanced Custom Fields（無料版で動きます）」を有効化してください。掲載者の入力欄はACFで提供されます。</p></div>';
	}
} );

/** 使わない「投稿」を隠す（記事は「掲載者」で管理するため） */
add_action( 'admin_menu', function () {
	remove_menu_page( 'edit.php' );
}, 999 );

add_action( 'admin_bar_menu', function ( $bar ) {
	$bar->remove_node( 'new-post' );
}, 999 );

add_action( 'admin_init', function () {
	global $pagenow;
	$is_post = ! isset( $_GET['post_type'] ) || 'post' === $_GET['post_type'];   // phpcs:ignore WordPress.Security.NonceVerification
	if ( in_array( $pagenow, array( 'edit.php', 'post-new.php' ), true ) && $is_post ) {
		wp_safe_redirect( admin_url() );
		exit;
	}
} );
