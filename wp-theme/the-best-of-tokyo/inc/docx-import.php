<?php
/**
 * 原稿取り込み（管理画面 → 掲載者 → 原稿取り込み）
 *
 * インタビュー原稿の Word ファイル（.docx）をアップロードすると、
 * 中身を確認したうえで「掲載者」の下書きとして登録します。
 *
 * - 原稿の読み方は inc/docx-parser.php
 * - 写真・業種は取り込まない（登録後に編集画面で入れる）
 * - アップロードしたファイルはサーバーに残さない（読んだ内容だけを1時間だけ一時保存）
 * - 同じ原稿の二重登録を防ぐため、投稿に原稿のハッシュとファイル名を記録する
 */
if ( ! defined( 'ABSPATH' ) ) exit;

require_once __DIR__ . '/docx-parser.php';

const TBT_DOCX_PAGE = 'tbt-docx';
const TBT_DOCX_MAX  = 10 * 1024 * 1024;   // 1ファイルの上限（10MB）

/* ------------------------------------------------------------------
 * 管理メニュー
 * ---------------------------------------------------------------- */
add_action( 'admin_menu', function () {
	add_submenu_page(
		'edit.php?post_type=story',
		'原稿取り込み',
		'原稿取り込み',
		'edit_posts',
		TBT_DOCX_PAGE,
		'tbt_docx_screen',
		2
	);
} );

function tbt_docx_page_url( $args = array() ) {
	return add_query_arg( array_merge( array( 'post_type' => 'story', 'page' => TBT_DOCX_PAGE ), $args ), admin_url( 'edit.php' ) );
}

/** 一時保存のキー（ユーザーごと） */
function tbt_docx_key( $kind, $token ) {
	return 'tbt_docx_' . $kind . '_' . get_current_user_id() . '_' . preg_replace( '/[^A-Za-z0-9]/', '', $token );
}

/** ファイル名を比べられる形に（macOS の濁点分離などをそろえる） */
function tbt_docx_norm_name( $name ) {
	return class_exists( 'Normalizer' ) ? Normalizer::normalize( $name, Normalizer::FORM_C ) : $name;
}

/* ------------------------------------------------------------------
 * 画面
 * ---------------------------------------------------------------- */
function tbt_docx_screen() {
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_die( '権限がありません。' );
	}
	// phpcs:disable WordPress.Security.NonceVerification
	$token  = isset( $_GET['token'] ) ? sanitize_key( wp_unslash( $_GET['token'] ) ) : '';
	$result = isset( $_GET['result'] ) ? sanitize_key( wp_unslash( $_GET['result'] ) ) : '';
	$msg    = isset( $_GET['tbt_msg'] ) ? sanitize_key( wp_unslash( $_GET['tbt_msg'] ) ) : '';
	// phpcs:enable
	?>
	<div class="wrap tbt-docx">
		<h1>原稿取り込み</h1>

		<?php if ( 'nofile' === $msg ) : ?>
			<div class="notice notice-error"><p>ファイルが選ばれていません。</p></div>
		<?php elseif ( 'expired' === $msg ) : ?>
			<div class="notice notice-error"><p>確認画面の有効期限（1時間）が切れました。もう一度アップロードしてください。</p></div>
		<?php elseif ( 'none' === $msg ) : ?>
			<div class="notice notice-warning"><p>登録する原稿にチェックが入っていませんでした。</p></div>
		<?php endif; ?>

		<?php
		if ( $result ) {
			$rows = get_transient( tbt_docx_key( 'r', $result ) );
			if ( is_array( $rows ) ) {
				tbt_docx_screen_result( $rows );
				echo '</div>';
				return;
			}
		}
		if ( $token ) {
			$items = get_transient( tbt_docx_key( 'p', $token ) );
			if ( is_array( $items ) ) {
				tbt_docx_screen_preview( $items, $token );
				echo '</div>';
				return;
			}
			echo '<div class="notice notice-error"><p>確認画面の有効期限（1時間）が切れました。もう一度アップロードしてください。</p></div>';
		}
		tbt_docx_screen_upload();
		?>
	</div>
	<?php
}

/** 1) アップロード */
function tbt_docx_screen_upload() {
	$max = min( TBT_DOCX_MAX, wp_max_upload_size() );
	?>
	<p>インタビュー原稿の Word ファイル（.docx）を選ぶと、内容を確認してから「掲載者」の<strong>下書き</strong>として登録します。写真は登録後に編集画面で入れてください。</p>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" class="tbt-docx__form">
		<input type="hidden" name="action" value="tbt_docx_parse">
		<?php wp_nonce_field( 'tbt_docx_parse' ); ?>
		<label class="tbt-docx__drop">
			<span class="tbt-docx__drop-ttl">ここに .docx をドラッグ、またはクリックして選択</span>
			<span class="tbt-docx__drop-sub">複数まとめて選べます（1ファイル <?php echo esc_html( size_format( $max ) ); ?> まで）</span>
			<input type="file" name="docx[]" accept=".docx,application/vnd.openxmlformats-officedocument.wordprocessingml.document" multiple required>
		</label>
		<p><button type="submit" class="button button-primary button-hero">読み込んで内容を確認する</button></p>
		<p class="description">この段階ではまだ登録されません。</p>
	</form>

	<h2>原稿の書き方</h2>
	<table class="widefat striped tbt-docx__rules">
		<tbody>
			<tr><th>1〜6段落目</th><td>キャッチコピー／英字名／氏名／肩書き／プロフィール／URL の順（URL の <code>&lt;&gt;</code> は自動で外します）</td></tr>
			<tr><th>本文</th><td>中見出し → <code>Q：</code>質問 → <code>A：</code>回答 の繰り返し。回答の2段落目以降は <code>A：</code> なしで続けて構いません</td></tr>
			<tr><th>最後</th><td>メッセージ見出し（1行）→ メッセージ本文</td></tr>
			<tr><th>見出しの決まり</th><td>中見出し・メッセージ見出しは<strong>短い1行で、「。」で終えない</strong>（これで見出しと判定します）</td></tr>
			<tr><th>読まないもの</th><td><code>※</code> で始まる段落（制作メモ）、画像、表、テキストボックス</td></tr>
			<tr><th>Google ドキュメント</th><td>「ファイル → ダウンロード → Microsoft Word（.docx）」で保存してから選んでください</td></tr>
			<tr><th>重複の確認</th><td>同じ中身の原稿は登録できません。<strong>会社名・氏名が既存の掲載者と同じ</strong>ときは確認画面で知らせ、チェックを外した状態にします（別人・別法人なら、チェックを入れれば登録できます）</td></tr>
		</tbody>
	</table>
	<?php
}

/** 2) 内容の確認 */
function tbt_docx_screen_preview( $items, $token ) {
	$can = 0;
	foreach ( $items as $it ) {
		if ( $it['data'] && ! ( $it['dup'] && 'same' === $it['dup']['type'] ) ) {
			$can++;
		}
	}
	?>
	<p>読み込んだ内容を確認してください。登録するものにチェックを入れて、いちばん下のボタンを押します。</p>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="tbt_docx_commit">
		<input type="hidden" name="token" value="<?php echo esc_attr( $token ); ?>">
		<?php wp_nonce_field( 'tbt_docx_commit' ); ?>

		<?php foreach ( $items as $i => $it ) : ?>
			<?php tbt_docx_preview_card( $i, $it ); ?>
		<?php endforeach; ?>

		<p class="tbt-docx__actions">
			<?php if ( $can ) : ?>
				<button type="submit" class="button button-primary button-hero">チェックした原稿を下書きとして登録</button>
			<?php endif; ?>
			<a class="button button-hero" href="<?php echo esc_url( tbt_docx_page_url() ); ?>">やり直す</a>
		</p>
	</form>
	<?php
}

function tbt_docx_preview_card( $i, $it ) {
	$m     = $it['data'];
	$dup   = $it['dup'];
	$clash = isset( $it['clash'] ) ? $it['clash'] : array();
	?>
	<div class="tbt-docx__card">
		<h2 class="tbt-docx__file">
			<?php if ( $m && ! ( $dup && 'same' === $dup['type'] ) ) : ?>
				<label>
					<input type="checkbox" name="items[]" value="<?php echo (int) $i; ?>" <?php checked( ! $dup && ! $clash ); ?>>
					<?php echo esc_html( $it['name'] ); ?>
				</label>
			<?php else : ?>
				<?php echo esc_html( $it['name'] ); ?>
			<?php endif; ?>
		</h2>

		<?php if ( $it['error'] ) : ?>
			<div class="notice notice-error inline"><p><strong>登録できません：</strong><?php echo esc_html( $it['error'] ); ?></p></div>
			<?php return; ?>
		<?php endif; ?>

		<?php if ( $dup && 'same' === $dup['type'] ) : ?>
			<div class="notice notice-info inline"><p>この原稿は取り込み済みです → <?php tbt_docx_post_link( $dup['post_id'] ); ?></p></div>
			<?php return; ?>
		<?php elseif ( $dup ) : ?>
			<div class="notice notice-warning inline"><p>
				同じファイル名の原稿を取り込み済みで、<strong>内容が変わっています</strong>（<?php tbt_docx_post_link( $dup['post_id'] ); ?>）。<br>
				既存の記事は上書きしません。差分は編集画面で直してください。別の下書きとして登録したいときだけチェックを入れてください。
			</p></div>
		<?php endif; ?>

		<?php if ( $clash ) : ?>
			<div class="notice notice-warning inline">
				<p><strong>すでに同じ名前のものがあります。</strong>チェックを外してあります。別人・別法人であることを確かめたうえで登録してください。</p>
				<ul>
					<?php foreach ( $clash as $c ) : ?>
						<li>
							<?php echo 'company' === $c['type'] ? '会社名・団体名' : '氏名'; ?>
							「<strong><?php echo esc_html( $c['value'] ); ?></strong>」が
							<?php if ( $c['post_id'] ) : ?>
								登録済みの掲載者と同じです → <?php tbt_docx_post_link( $c['post_id'] ); ?>
							<?php else : ?>
								この回の別の原稿「<?php echo esc_html( $c['file'] ); ?>」と同じです
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>

		<?php if ( $m['warnings'] ) : ?>
			<div class="notice notice-warning inline"><ul>
				<?php foreach ( $m['warnings'] as $w ) : ?>
					<li><?php echo esc_html( $w ); ?></li>
				<?php endforeach; ?>
			</ul></div>
		<?php endif; ?>

		<table class="widefat tbt-docx__fields">
			<tbody>
				<tr><th>キャッチコピー</th><td><?php echo nl2br( esc_html( $m['catch'] ) ); ?></td></tr>
				<tr><th>氏名（タイトル）</th><td><?php echo esc_html( $m['name'] ); ?></td></tr>
				<tr><th>英字名</th><td><?php echo esc_html( $m['en_name'] ); ?></td></tr>
				<tr><th>パーマリンク</th><td><?php echo esc_html( home_url( '/stories/' . tbt_docx_slug( $m['en_name'] ) . '/' ) ); ?></td></tr>
				<tr><th>会社名・団体名</th><td><?php echo esc_html( $m['company'] ); ?></td></tr>
				<tr><th>役職</th><td><?php echo '' !== $m['role'] ? esc_html( $m['role'] ) : '<span class="tbt-docx__none">（なし）</span>'; ?></td></tr>
				<tr><th>プロフィール</th><td><?php echo nl2br( esc_html( $m['profile'] ) ); ?></td></tr>
				<tr><th>URL</th><td><?php echo '' !== $m['url'] ? esc_html( $m['url'] ) : '<span class="tbt-docx__none">（なし）</span>'; ?></td></tr>
			</tbody>
		</table>

		<details class="tbt-docx__body">
			<summary>本文を確認する（中見出し <?php echo count( $m['sections'] ); ?> 個＋メッセージ）</summary>
			<div class="tbt-docx__article">
				<?php foreach ( $m['intro'] as $t ) : ?>
					<p><?php echo nl2br( esc_html( $t ) ); ?></p>
				<?php endforeach; ?>
				<?php foreach ( $m['sections'] as $sec ) : ?>
					<?php if ( '' !== $sec['heading'] ) : ?>
						<h3><?php echo esc_html( $sec['heading'] ); ?></h3>
					<?php endif; ?>
					<?php foreach ( $sec['qa'] as $qa ) : ?>
						<p class="tbt-docx__q">Q：<?php echo nl2br( esc_html( $qa['q'] ) ); ?></p>
						<?php foreach ( $qa['a'] as $n => $a ) : ?>
							<p><?php echo 0 === $n ? 'A：' : ''; ?><?php echo nl2br( esc_html( $a ) ); ?></p>
						<?php endforeach; ?>
					<?php endforeach; ?>
				<?php endforeach; ?>
				<h3><span class="tbt-docx__chip">MESSAGE</span><?php echo esc_html( $m['message']['heading'] ); ?></h3>
				<?php foreach ( $m['message']['body'] as $t ) : ?>
					<p><?php echo nl2br( esc_html( $t ) ); ?></p>
				<?php endforeach; ?>
			</div>
		</details>

		<?php if ( $m['memos'] ) : ?>
			<details class="tbt-docx__memos">
				<summary>読み飛ばした制作メモ（<?php echo count( $m['memos'] ); ?> 件）</summary>
				<ul>
					<?php foreach ( $m['memos'] as $t ) : ?>
						<li><?php echo esc_html( $t ); ?></li>
					<?php endforeach; ?>
				</ul>
			</details>
		<?php endif; ?>
	</div>
	<?php
}

/** 3) 登録結果 */
function tbt_docx_screen_result( $rows ) {
	$ok = array_filter( $rows, function ( $r ) { return ! empty( $r['post_id'] ); } );
	?>
	<?php if ( $ok ) : ?>
		<div class="notice notice-success"><p><strong><?php echo count( $ok ); ?> 件</strong>を下書きとして登録しました。</p></div>
	<?php endif; ?>

	<table class="widefat striped">
		<thead><tr><th>原稿</th><th>結果</th></tr></thead>
		<tbody>
			<?php foreach ( $rows as $r ) : ?>
				<tr>
					<td><?php echo esc_html( $r['name'] ); ?></td>
					<td>
						<?php if ( ! empty( $r['post_id'] ) ) : ?>
							登録しました → <?php tbt_docx_post_link( $r['post_id'] ); ?>
						<?php else : ?>
							<span class="tbt-docx__err"><?php echo esc_html( $r['error'] ); ?></span>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<?php if ( $ok ) : ?>
		<h2>このあとの作業</h2>
		<ol>
			<li><strong>業種</strong>を選ぶ（トップの GROUP 01〜 の振り分けに使います）</li>
			<li><strong>企業ロゴ</strong>（カードの表・ファーストビュー背面のロゴの壁に使います）</li>
			<li><strong>アイキャッチ画像</strong>（カードの正方形写真。マウスを乗せるとロゴから切り替わります）</li>
			<li>記事ページの人物写真（縦長）</li>
			<li>本文の各章の写真（章の見出しのすぐ下に画像ブロックを置く）</li>
			<li>流し写真</li>
			<li>プレビューで確認して「公開」</li>
		</ol>
	<?php endif; ?>

	<p><a class="button button-primary" href="<?php echo esc_url( tbt_docx_page_url() ); ?>">続けて取り込む</a></p>
	<?php
}

/** 投稿の編集リンク（ゴミ箱などで無くなっていればその旨） */
function tbt_docx_post_link( $post_id ) {
	$post = get_post( $post_id );
	if ( ! $post ) {
		echo '（削除済み）';
		return;
	}
	$status = get_post_status_object( $post->post_status );
	printf(
		'<a href="%s">%s を編集</a>（%s）',
		esc_url( get_edit_post_link( $post_id, 'raw' ) ),
		esc_html( get_the_title( $post ) ),
		esc_html( $status ? $status->label : $post->post_status )
	);
}

/* ------------------------------------------------------------------
 * 読み込み（アップロード → 解析 → 確認画面へ）
 * ---------------------------------------------------------------- */
add_action( 'admin_post_tbt_docx_parse', function () {
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_die( '権限がありません。' );
	}
	check_admin_referer( 'tbt_docx_parse' );

	$files = tbt_docx_uploaded_files();
	if ( ! $files ) {
		wp_safe_redirect( tbt_docx_page_url( array( 'tbt_msg' => 'nofile' ) ) );
		exit;
	}

	$items = array();
	foreach ( $files as $f ) {
		$name = tbt_docx_norm_name( sanitize_text_field( wp_unslash( $f['name'] ) ) );
		$item = array( 'name' => $name, 'hash' => '', 'data' => null, 'error' => '', 'dup' => null, 'clash' => array() );

		if ( UPLOAD_ERR_OK !== $f['error'] ) {
			$item['error'] = in_array( $f['error'], array( UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE ), true )
				? 'ファイルが大きすぎてアップロードできませんでした'
				: 'アップロードに失敗しました（エラー ' . (int) $f['error'] . '）';
		} elseif ( 'docx' !== strtolower( pathinfo( $name, PATHINFO_EXTENSION ) ) ) {
			$item['error'] = 'Word（.docx）ファイルではありません'
				. ( 'doc' === strtolower( pathinfo( $name, PATHINFO_EXTENSION ) ) ? '。古い形式（.doc）は Word で「.docx」として保存し直してください' : '' );
		} elseif ( ! is_uploaded_file( $f['tmp_name'] ) ) {
			$item['error'] = 'アップロードに失敗しました';
		} elseif ( filesize( $f['tmp_name'] ) > TBT_DOCX_MAX ) {
			$item['error'] = 'ファイルが大きすぎます（' . size_format( TBT_DOCX_MAX ) . ' まで）';
		} else {
			$item['hash'] = hash_file( 'sha256', $f['tmp_name'] );
			$item['dup']  = tbt_docx_find_dup( $name, $item['hash'] );
			try {
				$item['data'] = tbt_docx_parse( $f['tmp_name'] );
			} catch ( TBT_Docx_Error $e ) {
				$item['error'] = $e->getMessage();
			}
		}
		// アップロードされたファイル自体は PHP がリクエスト終了時に消す（サーバーには残さない）
		$items[] = $item;
	}

	// 会社名・氏名の重複を調べる（登録済みの掲載者と、この回に選んだ原稿どうしの両方）
	tbt_docx_mark_clashes( $items );

	$token = strtolower( wp_generate_password( 16, false ) );
	set_transient( tbt_docx_key( 'p', $token ), $items, HOUR_IN_SECONDS );

	wp_safe_redirect( tbt_docx_page_url( array( 'token' => $token ) ) );
	exit;
} );

/** $_FILES['docx'] を1ファイルずつの配列に */
function tbt_docx_uploaded_files() {
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- 中身はこのあと個別に検証する
	$f = isset( $_FILES['docx'] ) ? $_FILES['docx'] : null;
	if ( ! $f || ! is_array( $f['name'] ) ) {
		return array();
	}
	$out = array();
	foreach ( $f['name'] as $i => $name ) {
		if ( '' === $name && UPLOAD_ERR_NO_FILE === $f['error'][ $i ] ) {
			continue;
		}
		$out[] = array(
			'name'     => $name,
			'tmp_name' => $f['tmp_name'][ $i ],
			'error'    => (int) $f['error'][ $i ],
		);
	}
	return $out;
}

/**
 * 取り込み済みかを調べる
 *
 * @return array|null array( 'type' => 'same'（同じ中身）| 'changed'（同名で中身が違う）, 'post_id' => int )
 */
function tbt_docx_find_dup( $name, $hash ) {
	$base = array(
		'post_type'      => 'story',
		'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
	);

	$same = get_posts( $base + array( 'meta_key' => '_tbt_docx_hash', 'meta_value' => $hash ) );
	if ( $same ) {
		return array( 'type' => 'same', 'post_id' => (int) $same[0] );
	}
	$named = get_posts( $base + array( 'meta_key' => '_tbt_docx_name', 'meta_value' => $name ) );
	if ( $named ) {
		return array( 'type' => 'changed', 'post_id' => (int) $named[0] );
	}
	return null;
}

/* ------------------------------------------------------------------
 * 会社名・氏名の重複
 *   ファイル名やファイルの中身が違っても、同じ会社・同じ人の原稿なら気づけるようにする。
 *   同じ会社から2人目を載せることは実際にあるため、登録そのものは止めず、
 *   確認画面で知らせてチェックを外しておく（そのままボタンを押せば登録されない）。
 * ---------------------------------------------------------------- */

/**
 * 比べるための形にそろえる
 *   全角・半角、大文字・小文字、空白をならし、会社名は「株式会社」などの法人格を外す。
 */
function tbt_docx_norm_key( $text, $is_company = false ) {
	$text = (string) $text;
	if ( class_exists( 'Normalizer' ) ) {
		$text = Normalizer::normalize( $text, Normalizer::FORM_KC );
	}
	$text = preg_replace( '/[\s\x{3000}\x{00a0}]+/u', '', $text );
	if ( $is_company ) {
		$text = str_replace(
			array( '株式会社', '有限会社', '合同会社', '合資会社', '合名会社', '一般社団法人', '一般財団法人',
				'公益社団法人', '公益財団法人', '社会福祉法人', '医療法人社団', '医療法人', '学校法人',
				'宗教法人', 'NPO法人', '特定非営利活動法人', '(株)', '(有)' ),
			'',
			$text
		);
	}
	return mb_strtolower( $text, 'UTF-8' );
}

/** 登録済みの掲載者（氏名・会社名）の一覧。array( post_id => array( 'name' => …, 'company' => … ) ) */
function tbt_docx_existing_people() {
	static $people = null;
	if ( null !== $people ) {
		return $people;
	}
	$people = array();
	$ids    = get_posts( array(
		'post_type'      => 'story',
		'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
	) );
	foreach ( $ids as $id ) {
		$people[ (int) $id ] = array(
			'name'    => get_the_title( $id ),
			'company' => (string) tbt_field( 'company', $id ),
		);
	}
	return $people;
}

/**
 * 読み込んだ原稿に、会社名・氏名の重複の印をつける
 *   $items[n]['clash'] に array( 'type' => 'person'|'company', 'post_id' => int|null,
 *   'file' => string|null, 'value' => string ) が入る（post_id は登録済み、file は同じ回の別の原稿）
 */
function tbt_docx_mark_clashes( array &$items ) {
	$existing = tbt_docx_existing_people();

	foreach ( $items as $i => $item ) {
		if ( ! $item['data'] ) {
			continue;
		}
		// 取り込み済みの原稿（中身が同じ）は、別途その旨を出すのでここでは見ない
		if ( $item['dup'] && 'same' === $item['dup']['type'] ) {
			continue;
		}

		$m     = $item['data'];
		$clash = array();

		$keys = array(
			'person'  => array( tbt_docx_norm_key( $m['name'] ), $m['name'] ),
			'company' => array( tbt_docx_norm_key( $m['company'], true ), $m['company'] ),
		);

		foreach ( $keys as $type => $pair ) {
			list( $key, $label ) = $pair;
			if ( '' === $key ) {
				continue;
			}

			// 登録済みの掲載者と
			foreach ( $existing as $post_id => $p ) {
				$other = 'person' === $type ? tbt_docx_norm_key( $p['name'] ) : tbt_docx_norm_key( $p['company'], true );
				if ( '' !== $other && $other === $key ) {
					$clash[] = array( 'type' => $type, 'post_id' => $post_id, 'file' => null, 'value' => $label );
					break;
				}
			}

			// 同じ回に選んだ、ひとつ前までの原稿と
			foreach ( $items as $j => $other_item ) {
				if ( $j >= $i || ! $other_item['data'] ) {
					continue;
				}
				$o = $other_item['data'];
				$other = 'person' === $type ? tbt_docx_norm_key( $o['name'] ) : tbt_docx_norm_key( $o['company'], true );
				if ( '' !== $other && $other === $key ) {
					$clash[] = array( 'type' => $type, 'post_id' => null, 'file' => $other_item['name'], 'value' => $label );
					break;
				}
			}
		}

		$items[ $i ]['clash'] = $clash;
	}
}

/* ------------------------------------------------------------------
 * 登録（確認画面でチェックしたものを下書きに）
 * ---------------------------------------------------------------- */
add_action( 'admin_post_tbt_docx_commit', function () {
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_die( '権限がありません。' );
	}
	check_admin_referer( 'tbt_docx_commit' );

	$token = isset( $_POST['token'] ) ? sanitize_key( wp_unslash( $_POST['token'] ) ) : '';
	$key   = tbt_docx_key( 'p', $token );
	$items = $token ? get_transient( $key ) : false;
	if ( ! is_array( $items ) ) {
		wp_safe_redirect( tbt_docx_page_url( array( 'tbt_msg' => 'expired' ) ) );
		exit;
	}

	$picked = isset( $_POST['items'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['items'] ) ) : array();
	$picked = array_values( array_unique( array_intersect( $picked, array_keys( $items ) ) ) );
	if ( ! $picked ) {
		wp_safe_redirect( tbt_docx_page_url( array( 'token' => $token, 'tbt_msg' => 'none' ) ) );
		exit;
	}

	// 二重送信（ボタン連打・戻る→再送信）で同じ原稿が2件できないよう、先に確認画面を破棄する
	delete_transient( $key );

	$order = tbt_docx_next_order();
	$rows  = array();
	foreach ( $picked as $i ) {
		$it = $items[ $i ];
		if ( ! $it['data'] ) {
			continue;
		}
		$dup = tbt_docx_find_dup( $it['name'], $it['hash'] );
		if ( $dup && 'same' === $dup['type'] ) {
			$rows[] = array( 'name' => $it['name'], 'error' => '取り込み済みのため登録しませんでした' );
			continue;
		}

		$post_id = tbt_docx_insert( $it, $order );
		if ( is_wp_error( $post_id ) ) {
			$rows[] = array( 'name' => $it['name'], 'error' => '登録に失敗しました：' . $post_id->get_error_message() );
			continue;
		}
		$order++;
		$rows[] = array( 'name' => $it['name'], 'post_id' => $post_id );
	}

	$result = strtolower( wp_generate_password( 16, false ) );
	set_transient( tbt_docx_key( 'r', $result ), $rows, HOUR_IN_SECONDS );

	wp_safe_redirect( tbt_docx_page_url( array( 'result' => $result ) ) );
	exit;
} );

/** 並び順の末尾（既存の最大 menu_order + 1） */
function tbt_docx_next_order() {
	$last = get_posts( array(
		'post_type'      => 'story',
		'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
		'posts_per_page' => 1,
		'orderby'        => 'menu_order',
		'order'          => 'DESC',
		'no_found_rows'  => true,
	) );
	return $last ? (int) $last[0]->menu_order + 1 : 1;
}

/** 1件を下書きとして登録して投稿IDを返す */
function tbt_docx_insert( $it, $order ) {
	$m = $it['data'];

	$post_id = wp_insert_post( wp_slash( array(
		'post_type'    => 'story',
		'post_status'  => 'draft',
		'post_title'   => $m['name'],
		'post_name'    => tbt_docx_slug( $m['en_name'] ),
		'post_content' => tbt_docx_content( $m ),
		'menu_order'   => $order,
	) ), true );
	if ( is_wp_error( $post_id ) ) {
		return $post_id;
	}

	tbt_docx_set_field( $post_id, 'headline', $m['catch'],   'field_tbt_headline' );
	tbt_docx_set_field( $post_id, 'en_name',  $m['en_name'], 'field_tbt_en_name' );
	tbt_docx_set_field( $post_id, 'company',  $m['company'], 'field_tbt_company' );
	tbt_docx_set_field( $post_id, 'role',     $m['role'],    'field_tbt_role' );
	tbt_docx_set_field( $post_id, 'lead',     $m['profile'], 'field_tbt_lead' );
	tbt_docx_set_field( $post_id, 'url',      $m['url'],     'field_tbt_url' );

	update_post_meta( $post_id, '_tbt_docx_hash', $it['hash'] );
	update_post_meta( $post_id, '_tbt_docx_name', wp_slash( $it['name'] ) );

	return $post_id;
}

/**
 * パーマリンク（スラッグ）は「名字-名前」
 *   英字名は「名前 名字」の順（KEN HASEGAWA）なので、最後の語を先頭に回す → hasegawa-ken
 */
function tbt_docx_slug( $en_name ) {
	$words = preg_split( '/\s+/u', trim( $en_name ), -1, PREG_SPLIT_NO_EMPTY );
	if ( count( $words ) > 1 ) {
		array_unshift( $words, array_pop( $words ) );
	}
	return sanitize_title( implode( ' ', $words ) );
}

/** ACF があれば update_field、無ければ生のメタに書く（ACF未導入でも登録はできる） */
function tbt_docx_set_field( $post_id, $name, $value, $field_key ) {
	if ( function_exists( 'update_field' ) ) {
		update_field( $field_key, $value, $post_id );
		return;
	}
	update_post_meta( $post_id, $name, wp_slash( $value ) );
	update_post_meta( $post_id, '_' . $name, $field_key );
}

/* ------------------------------------------------------------------
 * 本文（Gutenberg ブロック）
 *   中見出し = H2（テーマが章に組み直す。最後の H2 は MESSAGE 章）
 *   質問・回答は「Q：」「A：」で始まる段落（テーマが「Q：」の段落を質問行にする）
 * ---------------------------------------------------------------- */
function tbt_docx_content( $m ) {
	$out = array();
	foreach ( $m['intro'] as $t ) {
		$out[] = tbt_docx_p( $t );
	}
	foreach ( $m['sections'] as $sec ) {
		if ( '' !== $sec['heading'] ) {
			$out[] = tbt_docx_h2( $sec['heading'] );
		}
		foreach ( $sec['qa'] as $qa ) {
			$out[] = tbt_docx_p( 'Q：' . $qa['q'] );
			foreach ( $qa['a'] as $n => $a ) {
				$out[] = tbt_docx_p( ( 0 === $n ? 'A：' : '' ) . $a );
			}
		}
	}
	$out[] = tbt_docx_h2( $m['message']['heading'] );
	foreach ( $m['message']['body'] as $t ) {
		$out[] = tbt_docx_p( $t );
	}
	return implode( "\n\n", $out );
}

function tbt_docx_h2( $text ) {
	return "<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">" . esc_html( str_replace( "\n", ' ', $text ) ) . "</h2>\n<!-- /wp:heading -->";
}

/** 段落内の改行は <br> に */
function tbt_docx_p( $text ) {
	return "<!-- wp:paragraph -->\n<p>" . implode( '<br>', array_map( 'esc_html', explode( "\n", $text ) ) ) . "</p>\n<!-- /wp:paragraph -->";
}

