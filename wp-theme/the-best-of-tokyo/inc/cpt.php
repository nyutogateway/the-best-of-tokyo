<?php
/**
 * カスタム投稿タイプ・タクソノミー
 *   story    … 掲載者（インタビュー記事）
 *   industry … 業種（トップの 01 MEDICAL〜 のグループ）
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/* ------------------------------------------------------------------
 * 掲載者（story）
 * ---------------------------------------------------------------- */
add_action( 'init', function () {
	register_post_type( 'story', array(
		'labels' => array(
			'name'          => '掲載者',
			'singular_name' => '掲載者',
			'add_new'       => '新規追加',
			'add_new_item'  => '掲載者を追加',
			'edit_item'     => '掲載者を編集',
			'new_item'      => '新規掲載者',
			'view_item'     => '掲載者を表示',
			'search_items'  => '掲載者を検索',
			'not_found'     => '掲載者が見つかりません',
			'all_items'     => '掲載者一覧',
			'menu_name'     => '掲載者',
		),
		'public'        => true,
		'has_archive'   => false,
		'menu_position' => 5,
		'menu_icon'     => 'dashicons-groups',
		// title=氏名, editor=記事本文, thumbnail=カードの正方形写真, page-attributes=並び順(menu_order)
		'supports'      => array( 'title', 'editor', 'thumbnail', 'page-attributes' ),
		'rewrite'       => array( 'slug' => 'stories' ),
		'show_in_rest'  => true,
	) );
} );

/* ------------------------------------------------------------------
 * 業種（industry）… トップの GROUP 01〜10
 *   タームごとに「英字表記（MEDICAL）」と「表示順」を持たせる。
 * ---------------------------------------------------------------- */
add_action( 'init', function () {
	register_taxonomy( 'industry', array( 'story' ), array(
		'labels' => array(
			'name'              => '業種',
			'singular_name'     => '業種',
			'add_new_item'      => '業種を追加',
			'edit_item'         => '業種を編集',
			'update_item'       => '業種を更新',
			'search_items'      => '業種を検索',
			'all_items'         => '業種一覧',
			'not_found'         => '業種が登録されていません',
			'menu_name'         => '業種',
			'back_to_items'     => '業種一覧へ戻る',
		),
		'public'            => true,
		'hierarchical'      => true,   // チェックボックスで選ばせる（新規タームの誤爆を防ぐため）
		'show_admin_column' => true,
		'show_in_rest'      => true,
		'rewrite'           => array( 'slug' => 'industry' ),
	) );
} );

/** 既定の業種（テーマ有効化時に作成。あとから管理画面で増減・改名できます） */
function tbt_default_industries() {
	return array(
		array( 'slug' => 'medical',        'ja' => '医療',         'en' => 'MEDICAL' ),
		array( 'slug' => 'care',           'ja' => '介護・福祉',   'en' => 'CARE' ),
		array( 'slug' => 'food',           'ja' => '食と農',       'en' => 'FOOD' ),
		array( 'slug' => 'manufacturing',  'ja' => 'ものづくり',   'en' => 'MANUFACTURING' ),
		array( 'slug' => 'technology',     'ja' => '技術・IT',     'en' => 'TECHNOLOGY' ),
		array( 'slug' => 'infrastructure', 'ja' => '暮らしの基盤', 'en' => 'INFRASTRUCTURE' ),
		array( 'slug' => 'retail',         'ja' => '小売・装い',   'en' => 'RETAIL' ),
		array( 'slug' => 'sports',         'ja' => 'スポーツ',     'en' => 'SPORTS' ),
		array( 'slug' => 'education',      'ja' => '教育・人材',   'en' => 'EDUCATION' ),
		array( 'slug' => 'finance',        'ja' => '経営・資産',   'en' => 'FINANCE' ),
	);
}

/** 既定の業種をまだ無いぶんだけ作る（既存のタームは触らない） */
function tbt_provision_industries() {
	if ( ! taxonomy_exists( 'industry' ) ) {
		return;
	}
	$i = 0;
	foreach ( tbt_default_industries() as $row ) {
		$i++;
		$term = get_term_by( 'slug', $row['slug'], 'industry' );
		if ( ! $term ) {
			$res = wp_insert_term( $row['ja'], 'industry', array( 'slug' => $row['slug'] ) );
			if ( is_wp_error( $res ) ) {
				continue;
			}
			$term_id = (int) $res['term_id'];
		} else {
			$term_id = (int) $term->term_id;
		}
		if ( '' === (string) get_term_meta( $term_id, 'tbt_en', true ) ) {
			update_term_meta( $term_id, 'tbt_en', $row['en'] );
		}
		if ( '' === (string) get_term_meta( $term_id, 'tbt_order', true ) ) {
			update_term_meta( $term_id, 'tbt_order', $i );
		}
	}
}

/* ------------------------------------------------------------------
 * 業種の追加・編集画面に「英字表記」「表示順」の入力欄を足す
 * ---------------------------------------------------------------- */
add_action( 'industry_add_form_fields', function () {
	?>
	<div class="form-field">
		<label for="tbt_en">英字表記</label>
		<input type="text" name="tbt_en" id="tbt_en" value="">
		<p>トップページに大きく出る英字。例: MEDICAL</p>
	</div>
	<div class="form-field">
		<label for="tbt_order">表示順</label>
		<input type="number" name="tbt_order" id="tbt_order" value="" step="1" min="0">
		<p>小さい数字ほど先に出ます（01、02…の番号もこの順で振られます）。</p>
	</div>
	<?php
} );

add_action( 'industry_edit_form_fields', function ( $term ) {
	$en    = get_term_meta( $term->term_id, 'tbt_en', true );
	$order = get_term_meta( $term->term_id, 'tbt_order', true );
	?>
	<tr class="form-field">
		<th scope="row"><label for="tbt_en">英字表記</label></th>
		<td>
			<input type="text" name="tbt_en" id="tbt_en" value="<?php echo esc_attr( $en ); ?>">
			<p class="description">トップページに大きく出る英字。例: MEDICAL</p>
		</td>
	</tr>
	<tr class="form-field">
		<th scope="row"><label for="tbt_order">表示順</label></th>
		<td>
			<input type="number" name="tbt_order" id="tbt_order" value="<?php echo esc_attr( $order ); ?>" step="1" min="0">
			<p class="description">小さい数字ほど先に出ます（01、02…の番号もこの順で振られます）。</p>
		</td>
	</tr>
	<?php
} );

foreach ( array( 'created_industry', 'edited_industry' ) as $tbt_hook ) {
	add_action( $tbt_hook, 'tbt_save_industry_meta' );
}
unset( $tbt_hook );

function tbt_save_industry_meta( $term_id ) {
	// タームの保存画面は WordPress 側で nonce 検証済み
	// phpcs:disable WordPress.Security.NonceVerification
	if ( isset( $_POST['tbt_en'] ) ) {
		update_term_meta( $term_id, 'tbt_en', sanitize_text_field( wp_unslash( $_POST['tbt_en'] ) ) );
	}
	if ( isset( $_POST['tbt_order'] ) && '' !== $_POST['tbt_order'] ) {
		update_term_meta( $term_id, 'tbt_order', absint( wp_unslash( $_POST['tbt_order'] ) ) );
	}
	// phpcs:enable
}

/** 業種一覧に「英字表記」「表示順」列を出す */
add_filter( 'manage_edit-industry_columns', function ( $cols ) {
	$new = array();
	foreach ( $cols as $key => $label ) {
		if ( 'posts' === $key ) {
			$new['tbt_en']    = '英字表記';
			$new['tbt_order'] = '表示順';
		}
		$new[ $key ] = $label;
	}
	return $new;
} );

add_filter( 'manage_industry_custom_column', function ( $out, $col, $term_id ) {
	if ( 'tbt_en' === $col || 'tbt_order' === $col ) {
		$v = get_term_meta( $term_id, $col, true );
		return '' !== (string) $v ? esc_html( $v ) : '—';
	}
	return $out;
}, 10, 3 );

/* ------------------------------------------------------------------
 * 入力欄のプレースホルダ
 * ---------------------------------------------------------------- */
add_filter( 'enter_title_here', function ( $t, $post ) {
	if ( $post && 'story' === $post->post_type ) {
		return '氏名（例: 原 弘樹）';
	}
	return $t;
}, 10, 2 );

/* ------------------------------------------------------------------
 * 掲載者一覧に「会社名」「役職」列を追加（氏名だけだと管理しづらいため）
 * ---------------------------------------------------------------- */
add_filter( 'manage_story_posts_columns', function ( $cols ) {
	$new = array();
	foreach ( $cols as $key => $label ) {
		$new[ $key ] = $label;
		if ( 'title' === $key ) {
			$new['tbt_company'] = '会社名・団体名';
			$new['tbt_role']    = '役職';
		}
	}
	return $new;
} );

add_action( 'manage_story_posts_custom_column', function ( $col, $post_id ) {
	if ( 'tbt_company' === $col ) {
		$v = tbt_field( 'company', $post_id );
		echo $v ? esc_html( $v ) : '—';
	} elseif ( 'tbt_role' === $col ) {
		$v = tbt_field( 'role', $post_id );
		echo $v ? esc_html( $v ) : '—';
	}
}, 10, 2 );

/* ------------------------------------------------------------------
 * 管理一覧の既定の並びを menu_order 昇順に
 *   （トップページのカードの並び順とそろえるため）
 * ---------------------------------------------------------------- */
add_action( 'pre_get_posts', function ( $q ) {
	if ( ! is_admin() || ! $q->is_main_query() ) return;
	if ( 'story' !== $q->get( 'post_type' ) ) return;
	if ( $q->get( 'orderby' ) ) return;
	$q->set( 'orderby', 'menu_order' );
	$q->set( 'order', 'ASC' );
} );
