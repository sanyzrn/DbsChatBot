<?php
/**
 * Learning from the site: document extraction (DOCX/PDF, Persian included),
 * site sync, AI suggestions parsing/applying and industry templates.
 * Required from tests/integration.php (check() and a disposable site).
 */

$modules_before  = get_option( SSC_Modules::OPTION );
$settings_before = get_option( SSC_Settings::OPTION_KEY );
$setup_before    = get_option( 'ssc_chatbot_setup' );
$fixtures        = __DIR__ . '/fixtures/';

// Documents.
$docx = SSC_Doc_Extract::file( $fixtures . 'brochure.docx', 'docx' );
check( ! is_wp_error( $docx ) && false !== strpos( $docx, 'بروشور قرص ویتامین D3 هزار واحد' ) && false !== strpos( $docx, "\nVitamin D3 1000 IU" ), 'Word documents keep paragraphs and Persian text' );
$pdf = SSC_Doc_Extract::file( $fixtures . 'persian-chrome.pdf', 'pdf' );
check( ! is_wp_error( $pdf ) && false !== strpos( $pdf, 'عوارض احتمالی: تهوع خفیف، سردرد.' ) && false !== strpos( $pdf, 'ویتامین D3 هزار واحد' ), 'Persian PDFs (visual glyph order, CID fonts) come out in reading order' );
$simple = SSC_Doc_Extract::file( $fixtures . 'simple.pdf', 'pdf' );
check( ! is_wp_error( $simple ) && "Opening hours: Sat(urday) to Wed\nPhone: 021-1234" === $simple, 'Classic PDFs: literal strings, escapes and line breaks' );
check( is_wp_error( SSC_Doc_Extract::pdf( 'not a pdf' ) ) && is_wp_error( SSC_Doc_Extract::pdf( "%PDF-1.4\n1 0 obj << /Encrypt 2 0 R >> endobj" ) ), 'Non-PDF and encrypted files are refused with a reason' );
check( 'سلام دنیا D3 خوب' === SSC_Doc_Extract::visual_to_logical( 'بوخ D3 ایند مالس' ) && 'Hello world' === SSC_Doc_Extract::visual_to_logical( 'Hello world' ), 'Right-to-left lines are reordered; Latin text is left alone' );

// Site sync.
$sync_was_booted = SSC_Modules::is_active( 'sitesync' );
SSC_Modules::activate( 'sitesync' );
if ( ! $sync_was_booted ) {
	SSC_Modules::get( 'sitesync' )->register();
}
$sync_page = wp_insert_post(
	array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => 'ساعات کاری و آدرس',
		'post_content' => '<!-- wp:paragraph --><p>ما شنبه تا چهارشنبه از ساعت ۹ تا ۱۷ پاسخگو هستیم. آدرس: تهران، خیابان ولیعصر.</p><!-- /wp:paragraph --><script>alert(1)</script>',
	)
);
$chunks = SSC_Module_Sitesync::index_post( $sync_page );
$docs   = wp_list_pluck( SSC_Module_Sitesync::documents(), 'doc_id' );
check( $chunks > 0 && in_array( 'wp-' . $sync_page, $docs, true ), 'Published pages become knowledge documents' );
// Stored content loses <script> to kses, so render an in-memory post to prove scripts never reach the index.
$raw_post = new WP_Post( (object) array( 'ID' => 0, 'post_type' => 'page', 'post_title' => 'x', 'post_excerpt' => '', 'post_content' => '<p>خیابان ولیعصر</p><script>alert(1)</script><style>p{}</style>', 'filter' => 'raw' ) );
$raw_text = SSC_Module_Sitesync::post_text( $raw_post );
check( false === strpos( $raw_text, 'alert(' ) && false === strpos( $raw_text, 'p{}' ) && false !== strpos( $raw_text, 'خیابان ولیعصر' ), 'Page text is rendered without scripts' );
wp_update_post(
	array(
		'ID'          => $sync_page,
		'post_status' => 'draft',
	)
);
check( ! in_array( 'wp-' . $sync_page, wp_list_pluck( SSC_Module_Sitesync::documents(), 'doc_id' ), true ), 'Unpublished content leaves the knowledge base immediately' );
wp_update_post(
	array(
		'ID'          => $sync_page,
		'post_status' => 'publish',
	)
);
check( false !== wp_next_scheduled( SSC_Module_Sitesync::POST_HOOK, array( (int) $sync_page ) ), 'Saving published content queues a re-index' );
SSC_Settings::update( array( 'sitesync_exclude' => array( $sync_page ) ) );
check( 0 === SSC_Module_Sitesync::index_post( $sync_page ), 'Excluded content is never indexed' );
SSC_Settings::update( array( 'sitesync_exclude' => array() ) );
SSC_Module_Sitesync::start();
$state = SSC_Module_Sitesync::run_batch( 10 );
check( ! $state['running'] && $state['done'] >= 1 && in_array( 'wp-' . $sync_page, wp_list_pluck( SSC_Module_Sitesync::documents(), 'doc_id' ), true ), 'A full sync covers the site and finishes' );
wp_trash_post( $sync_page );
check( ! in_array( 'wp-' . $sync_page, wp_list_pluck( SSC_Module_Sitesync::documents(), 'doc_id' ), true ), 'Trashed content is removed' );
wp_delete_post( $sync_page, true );
SSC_Module_Sitesync::clear();
wp_clear_scheduled_hook( SSC_Module_Sitesync::BATCH_HOOK );
wp_clear_scheduled_hook( SSC_Module_Sitesync::DAILY_HOOK );

// AI suggestions: tolerant parsing, safe values, review-then-apply.
$parsed = SSC_Suggest::parse( "Here you go:\n```json\n{\"persona\":{\"assistant_name\":\"سارا\",\"tone\":\"friendly\",\"assistant_role\":\"<b>مشاور</b>\",\"welcome_text\":\"سلام!\"},\"faqs\":[{\"q\":\"ساعت کاری؟\",\"a\":\"شنبه تا چهارشنبه ۹ تا ۱۷.\"},{\"q\":\"\",\"a\":\"x\"},{\"q\":\"bad\"}]}\n```" );
check( $parsed && 'سارا' === $parsed['persona']['assistant_name'] && 'مشاور' === $parsed['persona']['assistant_role'] && 1 === count( $parsed['faqs'] ), 'AI suggestions are parsed from fenced JSON and cleaned' );
check( null === SSC_Suggest::parse( 'no json here' ) && null === SSC_Suggest::parse( '{"persona":{"tone":"angry"}}' ), 'Unusable AI output is rejected' );
update_option( SSC_Modules::OPTION, array_values( array_diff( (array) get_option( SSC_Modules::OPTION, array() ), array( 'faq' ) ) ), false );
SSC_Settings::update( array( 'knowledge_items' => array() ) );
$applied = SSC_Suggest::apply( array( 'assistant_name' => 'سارا', 'welcome_text' => 'سلام!' ), $parsed['faqs'] );
$items   = SSC_Settings::get( 'knowledge_items' );
check( 'knowledge' === $applied['target'] && 1 === $applied['faqs'] && 'faq' === $items[0]['type'] && 'سارا' === SSC_Settings::business()['assistant_name'] && 'سلام!' === SSC_Settings::get( 'welcome_text' ), 'Picked suggestions are applied (FAQ as knowledge entries without the FAQ module)' );

// Industry templates: fill only what is empty; recommend, never activate.
SSC_Settings::update(
	array(
		'business'               => SSC_Settings::sanitize_business( array_merge( SSC_Settings::business(), array( 'category' => 'دندانپزشکی', 'assistant_role' => '', 'industry' => '' ) ) ),
		'welcome_text'           => '',
		'ai_system_prompt_extra' => '',
		'form_fields'            => array(),
	)
);
$result   = SSC_Templates::apply( 'clinic' );
$business = SSC_Settings::business();
check( 'دندانپزشکی' === $business['category'] && '' !== $business['assistant_role'] && '' !== SSC_Settings::get( 'welcome_text' ) && '' !== SSC_Settings::get( 'ai_system_prompt_extra' ) && 2 === count( SSC_Settings::form_fields() ), 'A template fills empty fields and keeps what the admin wrote' );
check( ! is_wp_error( $result ) && in_array( 'leads', $result['modules'], true ) && ! SSC_Modules::is_active( 'leads' ), 'Template modules are recommended, not switched on' );
check( is_wp_error( SSC_Templates::apply( 'nope' ) ), 'Unknown templates are refused' );

update_option( SSC_Modules::OPTION, $modules_before, false );
update_option( SSC_Settings::OPTION_KEY, $settings_before );
update_option( 'ssc_chatbot_setup', $setup_before );
