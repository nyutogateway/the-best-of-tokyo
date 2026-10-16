<?php
/**
 * 並び替え画面（ドラッグ＆ドロップ）… 掲載者 → 並び替え
 *
 * カードをドラッグして落とすとその場で保存し、menu_order を 1 から振り直します。
 * ページ属性の「順序」に数字を入れる方法も今までどおり使えます（同じ値を書き換えています）。
 *
 * トップページのカードは「業種」ごとに分かれ、その中をこの並び順で表示します。
 */
if ( ! defined( 'ABSPATH' ) ) exit;

const TBT_REORDER_PAGE = 'tbt-reorder';

/* ------------------------------------------------------------------
 * 管理メニュー
 * ---------------------------------------------------------------- */
add_action( 'admin_menu', function () {
	add_submenu_page(
		'edit.php?post_type=story',
		'掲載者の並び替え',
		'並び替え',
		'edit_posts',
		TBT_REORDER_PAGE,
		'tbt_reorder_screen'
	);
} );

/** いま並び替え画面を開いているか */
function tbt_is_reorder_screen() {
	// phpcs:ignore WordPress.Security.NonceVerification
	return isset( $_GET['page'] ) && TBT_REORDER_PAGE === sanitize_key( wp_unslash( $_GET['page'] ) );
}

/* ------------------------------------------------------------------
 * 画面のアセット（jQuery UI Sortable は WordPress 同梱）
 * ---------------------------------------------------------------- */
add_action( 'admin_enqueue_scripts', function () {
	if ( ! tbt_is_reorder_screen() ) {
		return;
	}
	$dir  = get_template_directory();
	$file = '/assets/js/admin-reorder.js';

	wp_enqueue_script(
		'tbt-reorder',
		get_template_directory_uri() . $file,
		array( 'jquery', 'jquery-ui-sortable' ),
		file_exists( $dir . $file ) ? (string) filemtime( $dir . $file ) : TBT_VER,
		true
	);
	wp_localize_script( 'tbt-reorder', 'TBT_REORDER', array(
		'ajax'  => admin_url( 'admin-ajax.php' ),
		'nonce' => wp_create_nonce( 'tbt_reorder' ),
	) );
} );

/* ------------------------------------------------------------------
 * 画面
 * ---------------------------------------------------------------- */
function tbt_reorder_screen() {
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_die( '権限がありません。' );
	}

	$ids = get_posts( array(
		'post_type'      => 'story',
		'posts_per_page' => -1,
		'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
		'orderby'        => 'menu_order date',
		'order'          => 'ASC',
		'fields'         => 'ids',
		'no_found_rows'  => true,
	) );
	?>
	<div class="wrap tbt-reorder-wrap">
		<h1>掲載者の並び替え
			<span class="tbt-reorder-status" aria-live="polite"></span>
		</h1>

		<?php if ( ! $ids ) : ?>
			<div class="notice notice-warning"><p>掲載者がまだ登録されていません。</p></div>
			</div>
			<?php return; ?>
		<?php endif; ?>

		<p class="description">
			カードをドラッグして並べ替えると、その場で保存されます。<br>
			トップページのカードは<strong>業種</strong>ごとに分かれ、その中をこの並び順で、先頭から
			<strong><?php echo (int) tbt_cards_per_group(); ?>人</strong>まで表示します（INDEX には全員出ます）。
		</p>

		<ul class="tbt-reorder">
			<?php
			foreach ( $ids as $id ) :
				$thumb  = get_the_post_thumbnail_url( $id, 'thumbnail' );
				$co     = tbt_field( 'company', $id );
				$status = get_post_status( $id );
				$terms  = get_the_terms( $id, 'industry' );
				?>
				<li class="tbt-reorder__item" data-id="<?php echo (int) $id; ?>">
					<span class="tbt-reorder__no"></span>
					<span class="tbt-reorder__ph">
						<?php if ( $thumb ) : ?>
							<img src="<?php echo esc_url( $thumb ); ?>" alt="">
						<?php endif; ?>
					</span>
					<span class="tbt-reorder__body">
						<strong><?php echo esc_html( get_the_title( $id ) ); ?></strong>
						<?php if ( $co ) : ?><em><?php echo esc_html( $co ); ?></em><?php endif; ?>
						<span class="tbt-reorder__tags">
							<?php if ( $terms && ! is_wp_error( $terms ) ) : ?>
								<?php foreach ( $terms as $t ) : ?>
									<span class="tbt-reorder__tag"><?php echo esc_html( $t->name ); ?></span>
								<?php endforeach; ?>
							<?php else : ?>
								<span class="tbt-reorder__tag is-none">業種が未設定</span>
							<?php endif; ?>
							<?php if ( 'publish' !== $status ) : ?>
								<span class="tbt-reorder__draft"><?php echo esc_html( get_post_status_object( $status )->label ); ?></span>
							<?php endif; ?>
						</span>
					</span>
					<a class="tbt-reorder__edit" href="<?php echo esc_url( get_edit_post_link( $id ) ); ?>">編集</a>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
	<?php
}

/* ------------------------------------------------------------------
 * 保存（Ajax）
 * ---------------------------------------------------------------- */
add_action( 'wp_ajax_tbt_reorder', function () {
	check_ajax_referer( 'tbt_reorder', 'nonce' );

	$obj = get_post_type_object( 'story' );
	if ( ! $obj || ! current_user_can( $obj->cap->edit_posts ) ) {
		wp_send_json_error( array( 'message' => '権限がありません。' ), 403 );
	}

	$ids = isset( $_POST['ids'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['ids'] ) ) : array();
	if ( ! $ids ) {
		wp_send_json_error( array( 'message' => '並び順が空です。' ), 400 );
	}

	global $wpdb;

	$saved = 0;
	foreach ( $ids as $i => $id ) {
		if ( ! $id || 'story' !== get_post_type( $id ) ) {
			continue;
		}
		if ( ! current_user_can( 'edit_post', $id ) ) {
			continue;
		}
		// menu_order だけを書き換える。
		// wp_update_post だと更新日時（post_modified）まで動いてしまうため直接更新する。
		$wpdb->update( $wpdb->posts, array( 'menu_order' => $i + 1 ), array( 'ID' => $id ), array( '%d' ), array( '%d' ) );
		clean_post_cache( $id );
		$saved++;
	}

	wp_send_json_success( array( 'saved' => $saved ) );
} );
