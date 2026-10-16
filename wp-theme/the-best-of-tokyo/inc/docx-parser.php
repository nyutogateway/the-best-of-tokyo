<?php
/**
 * 原稿（.docx）の解析 … inc/docx-import.php（管理画面「原稿取り込み」）から使う
 *
 * 原稿の形（段落を上から順に読む。空段落は無視）
 *   1 キャッチコピー / 2 英字名 / 3 氏名 / 4 肩書き / 5 プロフィール / 6 URL
 *   7〜 中見出し → Q：… → A：… の繰り返し
 *   最後 メッセージ見出し → メッセージ本文
 *
 *   - 「※」で始まる段落は制作メモとして読まない
 *   - 中見出し・メッセージ見出しは「短い1行で、。で終わらない」もの
 *   - 回答の2段落目以降は「A：」なしで続けてよい
 *   - 画像・表・テキストボックスは読まない
 *
 * WordPress の関数は使わない（単体で動かして確認できるように）。
 */
if ( ! defined( 'ABSPATH' ) ) exit;

const TBT_DOCX_W  = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
const TBT_DOCX_MC = 'http://schemas.openxmlformats.org/markup-compatibility/2006';

const TBT_DOCX_RE_Q    = '/^[QＱqｑ][0-9０-９]*\s*[:：]\s*/u';
const TBT_DOCX_RE_A    = '/^[AＡaａ][0-9０-９]*\s*[:：]\s*/u';
const TBT_DOCX_RE_URL  = '/^(?:URL|ＵＲＬ)?\s*[:：]?\s*[<＜]?\s*((?:https?:\/\/|www\.)[^\s<>＜＞]+?)\s*[>＞]?$/iu';
const TBT_DOCX_RE_JA   = '/[\x{3040}-\x{30ff}\x{3400}-\x{9fff}]/u';

// 肩書きを「会社名」と「役職」に分ける目印（この語で始まる語から後ろを役職とみなす）
const TBT_DOCX_ROLE_WORDS = array(
	'代表取締役', '取締役', '代表理事', '理事長', '理事', '代表社員', '代表', '会長', '副社長', '社長',
	'専務', '常務', '執行役員', '院長', '副院長', '園長', '校長', '学長', '塾長', '館長', '所長', '局長',
	'部長', '室長', '店長', '支配人', '料理長', '総料理長', '組合長', '頭取', '監督', '主宰', '顧問',
	'オーナー', '創業者', '共同創業者', 'ファウンダー', '代表者',
	'CEO', 'COO', 'CTO', 'CFO', 'Founder', 'Co-Founder', 'President', 'Owner', 'Director',
);

// いつもの構成（違っていても登録はできる。画面で知らせる）
const TBT_DOCX_EXPECTED_SECTIONS = 3;
const TBT_DOCX_EXPECTED_QA       = 2;

/** 想定と違う原稿（登録しない） */
class TBT_Docx_Error extends Exception {}

/** 前後の空白（全角スペース・ノーブレークスペースも）を取る */
function tbt_docx_trim( $s ) {
	return preg_replace( '/^[\s\x{3000}\x{00a0}]+|[\s\x{3000}\x{00a0}]+$/u', '', $s );
}

/**
 * .docx の本文段落を上から順に返す
 *
 * @return array array( 段落テキストの配列, 注意書きの配列 )
 */
function tbt_docx_paragraphs( $file ) {
	if ( ! class_exists( 'ZipArchive' ) || ! class_exists( 'DOMDocument' ) ) {
		throw new TBT_Docx_Error( 'サーバーで Word ファイルを読む機能（ZipArchive / DOM）が使えません。サーバー管理者に確認してください' );
	}

	$zip = new ZipArchive();
	if ( true !== $zip->open( $file ) ) {
		throw new TBT_Docx_Error( 'Word ファイルとして開けません（壊れているか、パスワード付きの可能性があります）' );
	}
	$stat = $zip->statName( 'word/document.xml' );
	if ( ! $stat ) {
		$zip->close();
		throw new TBT_Docx_Error( 'Word ファイルとして開けません（本文が見つかりません）' );
	}
	if ( $stat['size'] > 30 * 1024 * 1024 ) {
		$zip->close();
		throw new TBT_Docx_Error( '本文が大きすぎます' );
	}
	$xml = $zip->getFromName( 'word/document.xml' );
	$zip->close();

	// 外部参照・エンティティ展開をさせない（Word が出力する本文に DOCTYPE は無い）
	if ( false === $xml || false !== stripos( $xml, '<!DOCTYPE' ) ) {
		throw new TBT_Docx_Error( 'Word ファイルとして読めません' );
	}

	$dom  = new DOMDocument();
	$prev = libxml_use_internal_errors( true );
	$ok   = $dom->loadXML( $xml, LIBXML_NONET | LIBXML_COMPACT );
	libxml_clear_errors();
	libxml_use_internal_errors( $prev );
	if ( ! $ok ) {
		throw new TBT_Docx_Error( 'Word ファイルとして読めません（本文の形式が不正です）' );
	}

	$xp = new DOMXPath( $dom );
	$xp->registerNamespace( 'w', TBT_DOCX_W );
	$xp->registerNamespace( 'mc', TBT_DOCX_MC );

	$body = $xp->query( '/w:document/w:body' )->item( 0 );
	if ( ! $body ) {
		throw new TBT_Docx_Error( 'Word ファイルとして読めません（本文が見つかりません）' );
	}

	$paras = array();
	$notes = array();

	$walk = function ( DOMNode $container ) use ( &$walk, &$paras, &$notes, $xp ) {
		foreach ( $container->childNodes as $child ) {
			if ( XML_ELEMENT_NODE !== $child->nodeType || TBT_DOCX_W !== $child->namespaceURI ) {
				continue;
			}
			if ( 'p' === $child->localName ) {
				$t = tbt_docx_para_text( $child, $xp );
				if ( '' !== $t ) {
					$paras[] = $t;
				}
			} elseif ( 'sdt' === $child->localName ) {   // コンテンツコントロールの中も読む
				$content = $xp->query( 'w:sdtContent', $child )->item( 0 );
				if ( $content ) {
					$walk( $content );
				}
			} elseif ( 'tbl' === $child->localName ) {
				if ( '' !== tbt_docx_trim( $child->textContent ) ) {
					$notes['tbl'] = '表（テーブル）の中の文字は取り込んでいません';
				}
			}
		}
	};
	$walk( $body );

	if ( $xp->query( '//w:ins | //w:del | //w:moveFrom' )->length ) {
		$notes['rev'] = '変更履歴が残っています（すべて承諾した状態として読みました）';
	}
	if ( $xp->query( '//w:txbxContent' )->length ) {
		$notes['txbx'] = 'テキストボックス内の文字は取り込んでいません';
	}

	return array( $paras, array_values( $notes ) );
}

/**
 * 段落のテキスト
 *   変更履歴（挿入）・ハイパーリンクの文字も拾い、削除済みの文字・テキストボックス・
 *   変更履歴で移動する前の文字は除く。Shift+Enter の改行は "\n" にする。
 */
function tbt_docx_para_text( DOMNode $p, DOMXPath $xp ) {
	$skip  = 'not(ancestor::w:txbxContent) and not(ancestor::w:moveFrom) and not(ancestor::mc:Fallback) and not(ancestor::w:pPr)';
	$query = ".//w:t[$skip] | .//w:tab[$skip] | .//w:br[$skip] | .//w:cr[$skip] | .//w:noBreakHyphen[$skip]";

	$out = '';
	foreach ( $xp->query( $query, $p ) as $el ) {
		switch ( $el->localName ) {
			case 't':
				$out .= $el->textContent;
				break;
			case 'tab':
				$out .= ' ';
				break;
			case 'br':
				$type = $el->getAttributeNS( TBT_DOCX_W, 'type' );
				if ( 'page' !== $type && 'column' !== $type ) {
					$out .= "\n";
				}
				break;
			case 'cr':
				$out .= "\n";
				break;
			case 'noBreakHyphen':
				$out .= '-';
				break;
		}
	}

	$out   = str_replace( "\xc2\xa0", ' ', $out );
	$lines = array_filter( array_map( 'tbt_docx_trim', explode( "\n", $out ) ), 'strlen' );
	return implode( "\n", $lines );
}

function tbt_docx_kind( $t ) {
	if ( preg_match( TBT_DOCX_RE_Q, $t ) ) {
		return 'q';
	}
	if ( preg_match( TBT_DOCX_RE_A, $t ) ) {
		return 'a';
	}
	return 'other';
}

/** 中見出し・メッセージ見出しらしいか（短い1行で、句点で終わらない） */
function tbt_docx_is_heading( $t ) {
	if ( false !== strpos( $t, "\n" ) || mb_strlen( $t, 'UTF-8' ) > 60 ) {
		return false;
	}
	$last = mb_substr( $t, -1, 1, 'UTF-8' );
	return ! in_array( $last, array( '。', '．', '.' ), true );
}

/** URL らしければ URL を返す（<> は外す。www. で始まれば https:// を付ける） */
function tbt_docx_url( $t ) {
	if ( ! preg_match( TBT_DOCX_RE_URL, tbt_docx_trim( $t ), $m ) ) {
		return '';
	}
	$url = $m[1];
	if ( 0 === stripos( $url, 'www.' ) ) {
		$url = 'https://' . $url;
	}
	return $url;
}

/** 肩書きを array( 会社名, 役職 ) に分ける。分けられなければ array( 全体, '' ) */
function tbt_docx_split_affiliation( $text ) {
	if ( preg_match_all( '/[ \x{3000}]+/u', $text, $m, PREG_OFFSET_CAPTURE ) ) {
		foreach ( $m[0] as $sp ) {
			$end  = $sp[1] + strlen( $sp[0] );
			$rest = substr( $text, $end );
			foreach ( TBT_DOCX_ROLE_WORDS as $w ) {
				if ( 0 === strpos( $rest, $w ) ) {
					return array( tbt_docx_trim( substr( $text, 0, $sp[1] ) ), tbt_docx_trim( $rest ) );
				}
			}
		}
	}
	return array( tbt_docx_trim( $text ), '' );
}

function tbt_docx_short( $t ) {
	return mb_strimwidth( str_replace( "\n", ' ', $t ), 0, 40, '…', 'UTF-8' );
}

/**
 * 原稿を読んで、登録に必要な項目を返す
 *
 * @throws TBT_Docx_Error 想定と違う原稿
 */
function tbt_docx_parse( $file ) {
	list( $raw, $warnings ) = tbt_docx_paragraphs( $file );

	$memos = array();
	$paras = array();
	foreach ( $raw as $t ) {
		if ( 0 === strpos( $t, '※' ) ) {
			$memos[] = $t;
		} else {
			$paras[] = $t;
		}
	}

	$kinds   = array_map( 'tbt_docx_kind', $paras );
	$first_q = array_search( 'q', $kinds, true );
	if ( false === $first_q ) {
		throw new TBT_Docx_Error( '「Q：」で始まる段落が1つもありません' );
	}

	// ---- 先頭の項目（キャッチ・英字名・氏名・肩書き・プロフィール・URL） ----
	$head = array_slice( $paras, 0, $first_q );
	if ( count( $head ) < 5 ) {
		throw new TBT_Docx_Error( sprintf( '先頭の項目が足りません（Q より前に %d 段落。キャッチ〜プロフィール〜URL の6項目が必要です）', count( $head ) ) );
	}

	list( $catch, $en_name, $name, $affiliation ) = $head;

	if ( preg_match( TBT_DOCX_RE_JA, $en_name ) ) {
		throw new TBT_Docx_Error( sprintf( '2段落目（英字名）に日本語が入っています:「%s」— 項目の順番がずれていないか確認してください', tbt_docx_short( $en_name ) ) );
	}
	if ( ! preg_match( TBT_DOCX_RE_JA, $name ) ) {
		$warnings[] = sprintf( '3段落目（氏名）に日本語がありません:「%s」', $name );
	}
	foreach ( array( 'キャッチコピー' => $catch, '英字名' => $en_name, '氏名' => $name, '肩書き' => $affiliation ) as $label => $v ) {
		if ( tbt_docx_url( $v ) ) {
			throw new TBT_Docx_Error( $label . 'の位置に URL があります — 項目が欠けていないか確認してください' );
		}
	}

	$url_idx = null;
	for ( $i = 4; $i < count( $head ); $i++ ) {
		if ( tbt_docx_url( $head[ $i ] ) ) {
			$url_idx = $i;
			break;
		}
	}
	if ( null !== $url_idx ) {
		$url        = tbt_docx_url( $head[ $url_idx ] );
		$profile    = array_slice( $head, 4, $url_idx - 4 );
		$body_start = $url_idx + 1;
	} else {
		$url        = '';
		$warnings[] = 'URL が見つかりません（「公式サイトを見る」ボタンは出ません）';
		// Q の直前が見出しらしければ、それは1つ目の中見出し
		if ( count( $head ) >= 6 && tbt_docx_is_heading( end( $head ) ) ) {
			$profile    = array_slice( $head, 4, -1 );
			$body_start = count( $head ) - 1;
		} else {
			$profile    = array_slice( $head, 4 );
			$body_start = count( $head );
		}
	}

	if ( ! $profile ) {
		throw new TBT_Docx_Error( 'プロフィールがありません（肩書きと URL の間が空です）' );
	}
	if ( count( $profile ) > 1 ) {
		$warnings[] = sprintf( 'プロフィールが %d 段落あります（空行をはさんでつなげました）', count( $profile ) );
	}

	list( $company, $role ) = tbt_docx_split_affiliation( $affiliation );
	if ( '' === $role ) {
		$warnings[] = sprintf( '肩書きを会社名と役職に分けられませんでした（すべて会社名に入れました）:「%s」', $affiliation );
	}

	// ---- 本文（中見出し → Q → A の繰り返し → メッセージ） ----
	$body       = array_slice( $paras, $body_start );
	$body_kinds = array_map( 'tbt_docx_kind', $body );
	$last_q     = max( array_keys( $body_kinds, 'q', true ) );

	$intro       = array();   // 最初の中見出しより前の地の文（ふつうは無い）
	$sections    = array();   // array( array( 'heading' => string, 'qa' => array( array( 'q' => string, 'a' => string[] ) ) ) )
	$msg_heading = null;
	$msg_body    = array();
	$last        = null;      // 直前の段落: null / 'h' / 'q' / 'a'

	foreach ( $body as $i => $t ) {
		$k   = $body_kinds[ $i ];
		$nxt = isset( $body_kinds[ $i + 1 ] ) ? $body_kinds[ $i + 1 ] : null;

		if ( null !== $msg_heading ) {
			if ( 'other' !== $k ) {
				throw new TBT_Docx_Error( 'メッセージより後ろに Q/A があります:「' . tbt_docx_short( $t ) . '」' );
			}
			$msg_body[] = $t;
			continue;
		}

		if ( 'q' === $k ) {
			if ( 'q' === $last ) {
				$prev = end( $sections );
				$qa   = end( $prev['qa'] );
				throw new TBT_Docx_Error( 'A が無いまま次の Q が来ています:「' . tbt_docx_short( $qa['q'] ) . '」' );
			}
			if ( ! $sections ) {
				$sections[] = array( 'heading' => '', 'qa' => array() );
				$warnings[] = '最初の Q の前に中見出しがありません';
			}
			$sections[ count( $sections ) - 1 ]['qa'][] = array(
				'q' => preg_replace( TBT_DOCX_RE_Q, '', $t, 1 ),
				'a' => array(),
			);
			$last = 'q';
		} elseif ( 'a' === $k ) {
			if ( 'q' !== $last ) {
				throw new TBT_Docx_Error( '対応する Q の無い A があります:「' . tbt_docx_short( $t ) . '」' );
			}
			tbt_docx_append_answer( $sections, preg_replace( TBT_DOCX_RE_A, '', $t, 1 ) );
			$last = 'a';
		} elseif ( tbt_docx_is_heading( $t ) && 'q' === $nxt ) {
			if ( 'q' === $last ) {
				throw new TBT_Docx_Error( 'A が無いまま次の中見出しが来ています:「' . tbt_docx_short( $t ) . '」' );
			}
			$sections[] = array( 'heading' => $t, 'qa' => array() );
			$last       = 'h';
		} elseif ( $i > $last_q && 'a' === $last && tbt_docx_is_heading( $t ) ) {
			$msg_heading = $t;
		} elseif ( 'a' === $last ) {
			tbt_docx_append_answer( $sections, $t );   // 回答が複数段落にわたる
		} elseif ( 'q' === $last ) {
			throw new TBT_Docx_Error( 'Q の次の段落が「A：」で始まっていません:「' . tbt_docx_short( $t ) . '」' );
		} elseif ( null === $last ) {
			$intro[] = $t;
		} else {
			throw new TBT_Docx_Error( 'どこに入るか判定できない段落があります:「' . tbt_docx_short( $t ) . '」' );
		}
	}

	if ( 'q' === $last ) {
		throw new TBT_Docx_Error( '最後の Q に A がありません' );
	}
	if ( null === $msg_heading ) {
		throw new TBT_Docx_Error( 'メッセージ見出しが見つかりません（最後の回答の後ろに、短い1行の見出しが必要です）' );
	}
	if ( ! $msg_body ) {
		throw new TBT_Docx_Error( 'メッセージ本文がありません（見出し「' . $msg_heading . '」の後ろが空です）' );
	}

	if ( $intro ) {
		$warnings[] = sprintf( '最初の中見出しより前に地の文が %d 段落あります（見出しなしの章として入れます）', count( $intro ) );
	}
	$n_qa = array_map( function ( $s ) { return count( $s['qa'] ); }, $sections );
	if ( TBT_DOCX_EXPECTED_SECTIONS !== count( $sections ) || array_diff( $n_qa, array( TBT_DOCX_EXPECTED_QA ) ) ) {
		$warnings[] = sprintf(
			'構成がいつもと違います: 中見出し %d 個／Q&A %s 問（いつもは %d 個×%d 問）',
			count( $sections ), implode( '・', $n_qa ), TBT_DOCX_EXPECTED_SECTIONS, TBT_DOCX_EXPECTED_QA
		);
	}

	return array(
		'catch'       => $catch,
		'en_name'     => $en_name,
		'name'        => $name,
		'affiliation' => $affiliation,
		'company'     => $company,
		'role'        => $role,
		'profile'     => implode( "\n\n", $profile ),
		'url'         => $url,
		'intro'       => $intro,
		'sections'    => $sections,
		'message'     => array( 'heading' => $msg_heading, 'body' => $msg_body ),
		'memos'       => $memos,
		'warnings'    => $warnings,
	);
}

/** 直前の Q の回答に段落を足す */
function tbt_docx_append_answer( array &$sections, $text ) {
	$s = count( $sections ) - 1;
	$q = count( $sections[ $s ]['qa'] ) - 1;
	$sections[ $s ]['qa'][ $q ]['a'][] = $text;
}
