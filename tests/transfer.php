<?php
/**
 * Export & import: the file round trip, password-protected keys, hostile
 * files, merge vs replace, undo, and the new admin screens.
 * Required from tests/integration.php (check() and a disposable site).
 */

$before = array(
	'options' => array(),
	'faq'     => SSC_Schema::qa_all(),
);
foreach ( array( SSC_Settings::OPTION_KEY, SSC_Modules::OPTION, SSC_Transfer::BACKUP_OPTION ) as $name ) {
	$before['options'][ $name ] = get_option( $name, null );
}
foreach ( SSC_Settings::SPLIT_OPTIONS as $name ) {
	$before['options'][ $name ] = get_option( $name, null );
}

// A configured site to export.
SSC_Schema::qa_import( array(), true );
SSC_Schema::kb_delete_document( 'doc-transfer-a' );
SSC_Schema::kb_delete_document( 'wp-9991' );
SSC_Settings::update(
	array(
		'primary_color'            => '#0a7d4a',
		'welcome_text'             => 'سلام از فایل صادرشده',
		'launcher_offset_y_mobile' => 96,
		'launcher_avoid_bars'      => 'no',
		'enabled'                  => 'yes',
		'live_operators'           => array( 1 ),
		'business'                 => SSC_Settings::sanitize_business( array( 'org_name' => 'شرکت نمونه', 'phone' => '021-123', 'tone' => 'friendly' ) ),
		'products'                 => array(
			array( 'id' => 'p-one', 'name' => 'محصول یک', 'summary' => 'خلاصه یک', 'attributes' => array( 'گارانتی' => '۱۸ ماه', 'رنگ' => 'سبز' ) ),
			array( 'id' => 'p-two', 'name' => 'Product two', 'summary' => 'Two', 'attributes' => array() ),
		),
		'knowledge_items'          => array(
			array( 'id' => 'ki-one', 'type' => 'policy', 'title' => 'مرجوعی', 'content' => 'هفت روز' ),
			array( 'id' => 'ki-two', 'type' => 'general', 'title' => 'Hours', 'content' => '9 to 5' ),
		),
	)
);
SSC_Settings::set_secret( 'openai_api_key', 'sk-test-transfer-secret-123' );
SSC_Settings::set_secret( 'notify_token', 'bot-token-xyz' );
SSC_Schema::qa_insert( array( 'question' => 'ساعت کاری؟', 'keywords' => 'ساعت', 'answer' => '۹ تا ۱۷' ) );
SSC_Schema::qa_insert( array( 'question' => 'Do you ship?', 'answer' => 'Yes' ) );
SSC_Schema::kb_insert_chunks( 'doc-transfer-a', 'بروشور', array( 'بخش اول سند', 'بخش دوم سند' ), 'general', 'https://example.org/b.pdf' );
SSC_Schema::kb_insert_chunks( 'wp-9991', 'Website page', array( 'mirrored from the site' ) );
update_option( SSC_Modules::OPTION, array( 'history', 'faq' ), false );

// Building.
$all = array_keys( SSC_Transfer::sections() );
check( is_wp_error( SSC_Transfer::build( array() ) ), 'An export with nothing selected is refused' );
check( is_wp_error( SSC_Transfer::build( array( 'secrets' ), 'short' ) ), 'API keys cannot be exported without a real password' );
$bundle = SSC_Transfer::build( $all, 'correct horse battery' );
check( ! is_wp_error( $bundle ) && SSC_Transfer::FORMAT === $bundle['format'] && SSC_CHATBOT_VERSION === $bundle['plugin_version'], 'The export carries its format and plugin version' );
$json = SSC_Transfer::encode( $bundle );
check( false === strpos( $json, 'sk-test-transfer-secret-123' ) && false === strpos( $json, 'bot-token-xyz' ), 'API keys never appear in the file in plain text' );
check( false === strpos( $json, 'enc::v2::' ), 'Keys stored with this site\'s own encryption are not copied either' );
check( ! isset( $bundle['settings']['enabled'] ) && ! isset( $bundle['settings']['live_operators'] ) && ! isset( $bundle['settings']['openai_api_key'] ) && ! isset( $bundle['settings']['products'] ), 'Publication, user ids, keys and the big lists stay out of the settings section' );
check( '#0a7d4a' === $bundle['settings']['primary_color'] && 96 === $bundle['settings']['launcher_offset_y_mobile'], 'Appearance and button spacing travel with the settings' );
check( 'شرکت نمونه' === $bundle['business']['org_name'] && 2 === count( $bundle['products'] ) && 2 === count( $bundle['knowledge'] ) && 2 === count( $bundle['faq'] ), 'Business, products, knowledge entries and FAQ are in the file' );
check( 'گارانتی' === array_key_first( $bundle['products'][0]['attributes'] ), 'Product specifications keep their Persian names' );
check( 1 === count( $bundle['documents'] ) && 'doc-transfer-a' === $bundle['documents'][0]['doc_id'] && array( 'بخش اول سند', 'بخش دوم سند' ) === $bundle['documents'][0]['chunks'], 'Uploaded documents keep their chunks; pages mirrored from the website are left out' );
check( array( 'openai_api_key', 'notify_token' ) === $bundle['secret_names'], 'The file lists which keys it holds, never their values' );

// Passwords.
check( false === SSC_Transfer::unseal( $bundle['secrets'], 'wrong password!' ), 'A wrong password opens nothing' );
$opened = SSC_Transfer::unseal( $bundle['secrets'], 'correct horse battery' );
check( is_array( $opened ) && 'sk-test-transfer-secret-123' === $opened['openai_api_key'], 'The right password opens the keys' );
$damaged         = $bundle['secrets'];
$damaged['data'] = base64_encode( 'tampered' . $damaged['data'] ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- test.
check( false === SSC_Transfer::unseal( $damaged, 'correct horse battery' ), 'A modified key block is rejected, not decrypted' );

// Reading files.
check( is_wp_error( SSC_Transfer::parse( '{broken' ) ) && 'ssc_transfer_json' === SSC_Transfer::parse( '{broken' )->get_error_code(), 'A file that is not JSON is explained' );
check( 'ssc_transfer_format' === SSC_Transfer::parse( '{"format":"something-else"}' )->get_error_code(), 'A JSON file from another tool is refused' );
check( 'ssc_transfer_version' === SSC_Transfer::parse( wp_json_encode( array( 'format' => SSC_Transfer::FORMAT, 'format_version' => 99 ) ) )->get_error_code(), 'A file from a newer plugin version is refused' );
$parsed = SSC_Transfer::parse( "\xEF\xBB\xBF" . $json );
check( is_array( $parsed ) && 'شرکت نمونه' === $parsed['business']['org_name'], 'A file saved with a byte-order mark is still read' );
$summary = SSC_Transfer::summarize( $parsed );
check( 2 === $summary['counts']['products'] && 1 === $summary['counts']['documents'] && 2 === $summary['counts']['secrets'] && in_array( 'faq', $summary['present'], true ), 'The review screen can tell what the file holds' );

// Review step: the file waits server-side.
check( SSC_Transfer::stage( $parsed, 1 ) && SSC_Transfer::staged( 1 ) === $parsed, 'A parsed file is kept for review and read back intact' );
SSC_Transfer::unstage( 1 );
check( null === SSC_Transfer::staged( 1 ), 'A cancelled review leaves nothing behind' );

// Restore into an emptied site.
SSC_Settings::update(
	array(
		'primary_color'            => '#111111',
		'welcome_text'             => 'changed',
		'launcher_offset_y_mobile' => 18,
		'launcher_avoid_bars'      => 'yes',
		'enabled'                  => 'no',
		'business'                 => SSC_Settings::sanitize_business( array( 'org_name' => 'Somewhere else' ) ),
		'products'                 => array(),
		'knowledge_items'          => array(),
	)
);
SSC_Settings::set_secret( 'openai_api_key', '' );
SSC_Settings::set_secret( 'notify_token', '' );
SSC_Schema::qa_import( array(), true );
SSC_Schema::kb_delete_document( 'doc-transfer-a' );
update_option( SSC_Modules::OPTION, array( 'history' ), false );

$report = SSC_Transfer::apply( $parsed, $all, 'merge', 'correct horse battery' );
$status = wp_list_pluck( $report, 'status', 'id' );
check( array( 'ok' ) === array_values( array_unique( $status ) ), 'Every section imports cleanly' );
check( '#0a7d4a' === SSC_Settings::get( 'primary_color' ) && 'سلام از فایل صادرشده' === SSC_Settings::get( 'welcome_text' ) && 96 === (int) SSC_Settings::get( 'launcher_offset_y_mobile' ) && 'no' === SSC_Settings::get( 'launcher_avoid_bars' ), 'Settings and button spacing are restored' );
check( 'شرکت نمونه' === SSC_Settings::business()['org_name'] && 'friendly' === SSC_Settings::business()['tone'], 'The business profile is restored' );
check( 'no' === SSC_Settings::get( 'enabled' ), 'Importing never publishes the assistant' );
check( 2 === count( SSC_Settings::get( 'products' ) ) && '۱۸ ماه' === SSC_Settings::get( 'products' )[0]['attributes']['گارانتی'], 'Products come back with their specifications' );
check( 2 === count( SSC_Settings::get( 'knowledge_items' ) ), 'Knowledge entries come back' );
check( 2 === SSC_Schema::qa_count(), 'The FAQ bank comes back' );
$restored_docs = wp_list_pluck( SSC_Schema::kb_documents(), 'chunks', 'doc_id' );
check( isset( $restored_docs['doc-transfer-a'] ) && 2 === (int) $restored_docs['doc-transfer-a'], 'Documents come back with the same chunks' );
check( SSC_Modules::is_active( 'faq' ) && SSC_Modules::is_active( 'history' ), 'Modules listed in the file are switched on' );
check( 'sk-test-transfer-secret-123' === SSC_Settings::get_secret( 'openai_api_key' ) && 'bot-token-xyz' === SSC_Settings::get_secret( 'notify_token' ), 'API keys are decrypted with the password and stored encrypted for this site' );
check( 0 === strpos( (string) SSC_Settings::get( 'openai_api_key' ), 'enc::v2::' ), 'Imported keys are encrypted at rest, never plain text' );
check( SSC_Transfer::backup_time() > 0, 'An import leaves an undo snapshot' );

// The same file again: nothing doubles.
SSC_Transfer::apply( $parsed, $all, 'merge', 'correct horse battery' );
check( 2 === count( SSC_Settings::get( 'products' ) ) && 2 === count( SSC_Settings::get( 'knowledge_items' ) ) && 2 === SSC_Schema::qa_count(), 'Importing the same file twice does not duplicate anything' );
$restored_docs = wp_list_pluck( SSC_Schema::kb_documents(), 'chunks', 'doc_id' );
check( 2 === (int) $restored_docs['doc-transfer-a'], 'A re-imported document replaces itself instead of doubling' );

// Wrong or missing password: everything else still imports.
SSC_Settings::set_secret( 'openai_api_key', '' );
$report = wp_list_pluck( SSC_Transfer::apply( $parsed, array( 'secrets', 'business' ), 'merge', 'nope-nope-nope' ), 'status', 'id' );
check( 'warn' === $report['secrets'] && 'ok' === $report['business'] && ! SSC_Settings::has_secret( 'openai_api_key' ), 'A wrong password skips the keys with a warning and imports the rest' );
$report = wp_list_pluck( SSC_Transfer::apply( $parsed, array( 'secrets' ), 'merge', '' ), 'status', 'id' );
check( 'warn' === $report['secrets'], 'No password: keys are skipped with a warning' );

// Merge keeps what is here; replace makes the lists match the file.
SSC_Settings::update(
	array(
		'products'        => array( array( 'id' => 'only-here', 'name' => 'Only here', 'summary' => '' ), array( 'id' => 'p-one', 'name' => 'Old name', 'summary' => '' ) ),
		'knowledge_items' => array( array( 'id' => 'ki-local', 'type' => 'general', 'title' => 'Local', 'content' => 'keep me' ) ),
	)
);
SSC_Schema::qa_insert( array( 'question' => 'Only here?', 'answer' => 'yes' ) );
SSC_Transfer::apply( $parsed, array( 'products', 'knowledge', 'faq' ), 'merge' );
$ids = wp_list_pluck( SSC_Settings::get( 'products' ), 'name', 'id' );
check( isset( $ids['only-here'] ) && 'محصول یک' === $ids['p-one'] && isset( $ids['p-two'] ), 'Merge keeps local products, updates matching ones and adds new ones' );
check( 3 === count( SSC_Settings::get( 'knowledge_items' ) ) && 3 === SSC_Schema::qa_count(), 'Merge keeps local knowledge entries and questions' );
SSC_Transfer::apply( $parsed, array( 'products', 'knowledge', 'faq', 'documents' ), 'replace' );
check( array( 'p-one', 'p-two' ) === wp_list_pluck( SSC_Settings::get( 'products' ), 'id' ) && 2 === count( SSC_Settings::get( 'knowledge_items' ) ) && 2 === SSC_Schema::qa_count(), 'Replace makes products, entries and questions match the file' );
check( isset( wp_list_pluck( SSC_Schema::kb_documents(), 'chunks', 'doc_id' )['doc-transfer-a'] ), 'Replace keeps the documents that are in the file' );

// Undo.
SSC_Transfer::apply( $parsed, array( 'products' ), 'replace' );
SSC_Settings::update( array( 'products' => array( array( 'id' => 'mine', 'name' => 'Mine', 'summary' => '' ) ) ) );
SSC_Transfer::apply( $parsed, array( 'products', 'faq', 'modules' ), 'replace' );
check( array( 'p-one', 'p-two' ) === wp_list_pluck( SSC_Settings::get( 'products' ), 'id' ), 'Setup for undo: the import replaced the catalog' );
check( SSC_Transfer::undo() && array( 'mine' ) === wp_list_pluck( SSC_Settings::get( 'products' ), 'id' ), 'Undo puts the previous catalog back' );
check( 0 === SSC_Transfer::backup_time() && ! SSC_Transfer::undo(), 'An undo can be used once' );

// Old undo snapshots expire; fresh ones stay.
SSC_Transfer::snapshot();
SSC_Transfer::purge_backup( 30 * DAY_IN_SECONDS );
check( SSC_Transfer::backup_time() > 0, 'A fresh undo snapshot is kept' );
$stale       = get_option( SSC_Transfer::BACKUP_OPTION );
$stale['at'] = time() - 31 * DAY_IN_SECONDS;
update_option( SSC_Transfer::BACKUP_OPTION, $stale, false );
SSC_Transfer::purge_backup( 30 * DAY_IN_SECONDS );
check( 0 === SSC_Transfer::backup_time(), 'An undo snapshot older than 30 days is dropped' );

// Hostile files.
$evil = array(
	'format'         => SSC_Transfer::FORMAT,
	'format_version' => 1,
	'sections'       => array( 'settings', 'products', 'documents', 'secrets', 'modules', 'faq' ),
	'settings'       => array(
		'enabled'                  => 'yes',
		'position'                 => 'upside-down',
		'launcher_offset_x'        => 99999,
		'launcher_offset_y_mobile' => -50,
		'launcher_avoid_bars'      => 'maybe',
		'primary_color'            => 'red;}</style><script>alert(1)</script>',
		'font_family'              => 'comic-sans',
		'not_a_real_setting'       => 'x',
		'welcome_text'             => 'Hi <script>alert(1)</script><b>there</b>',
		'products'                 => array( array( 'id' => 'smuggled', 'name' => 'Smuggled' ) ),
		'ai_provider'              => 'does-not-exist',
	),
	'products'       => array(
		array( 'id' => 'x', 'name' => '<script>alert(1)</script>Evil', 'summary' => '<img src=x onerror=alert(1)>ok', 'brochure' => 'javascript:alert(1)', 'attributes' => array( '<b>k</b>' => '<i>v</i>' ) ),
		'not an array',
		array( 'name' => '' ),
	),
	'documents'      => array(
		array( 'doc_id' => 'wp-7777', 'title' => 'Claims to be a site page', 'chunks' => array( 'x' ) ),
		array( 'doc_id' => 'doc-evil', 'title' => '<b>Evil</b>', 'url' => 'javascript:alert(1)', 'chunks' => array( '<script>alert(1)</script>Chunk text' ) ),
		array( 'doc_id' => '', 'chunks' => array( 'no id' ) ),
	),
	'modules'        => array( 'history', 'not-a-module', '../etc/passwd' ),
	'faq'            => array( array( 'question' => '<b>Q</b>', 'answer' => 'A <script>x</script>' ), array( 'question' => '', 'answer' => 'no question' ) ),
	'secrets'        => array( 'kdf' => 'none', 'data' => 'plain' ),
);
$evil_report = SSC_Transfer::apply( $evil, array_keys( SSC_Transfer::sections() ), 'merge', 'whatever-password' );
check( 'no' === SSC_Settings::get( 'enabled' ), 'A file cannot publish the assistant' );
check( 'right' === SSC_Settings::get( 'position' ) && 200 === (int) SSC_Settings::get( 'launcher_offset_x' ) && 0 === (int) SSC_Settings::get( 'launcher_offset_y_mobile' ) && 'no' === SSC_Settings::get( 'launcher_avoid_bars' ), 'Out-of-range and invalid settings are corrected, not stored' );
check( '' === SSC_Settings::get( 'primary_color' ) && 'vazirmatn' === SSC_Settings::get( 'font_family' ) && 'none' === SSC_Settings::get( 'ai_provider' ), 'Colours, fonts and providers are validated' );
check( ! array_key_exists( 'not_a_real_setting', SSC_Settings::all() ) && false === strpos( (string) SSC_Settings::get( 'welcome_text' ), '<script' ), 'Unknown keys are dropped and markup is cleaned' );
$evil_products = SSC_Settings::get( 'products' );
check( ! in_array( 'smuggled', wp_list_pluck( $evil_products, 'id' ), true ), 'The big lists cannot be smuggled in through the settings section' );
$evil_product = null;
foreach ( $evil_products as $evil_candidate ) {
	if ( false !== strpos( $evil_candidate['name'], 'Evil' ) ) {
		$evil_product = $evil_candidate;
	}
}
check( null !== $evil_product && false === strpos( $evil_product['name'], '<' ) && false === strpos( $evil_product['summary'], 'onerror' ) && '' === $evil_product['brochure'], 'Product text is sanitized and script links are removed' );
$evil_docs = wp_list_pluck( SSC_Schema::kb_documents(), 'source_title', 'doc_id' );
check( ! isset( $evil_docs['wp-7777'] ) && isset( $evil_docs['doc-evil'] ) && false === strpos( $evil_docs['doc-evil'], '<' ), 'A file cannot plant pages that the website sync owns' );
check( SSC_Modules::is_active( 'history' ) && ! in_array( 'not-a-module', SSC_Modules::active_ids(), true ), 'Unknown modules are ignored' );
check( 'warn' === wp_list_pluck( $evil_report, 'status', 'id' )['secrets'], 'A forged key block is refused' );
SSC_Schema::kb_delete_document( 'doc-evil' );

// Settings that decide where data goes travel only with the (password-protected) keys.
SSC_Settings::update( array( 'custom_endpoint' => 'https://mine.example/v1', 'notify_email_to' => 'me@example.com', 'sms_admin_phone' => '09120000000', 'ai_webhook_url' => '', 'notify_chat_id' => '111' ) );
$redirect = array(
	'format'         => SSC_Transfer::FORMAT,
	'format_version' => 1,
	'settings'       => array( 'custom_endpoint' => 'https://attacker.example/steal', 'notify_email_to' => 'attacker@example.com', 'sms_admin_phone' => '09129999999', 'ai_webhook_url' => 'https://attacker.example/hook', 'notify_chat_id' => '666', 'welcome_text' => 'Harmless text' ),
);
$report = SSC_Transfer::apply( $redirect, array( 'settings' ), 'merge' );
check( 'https://mine.example/v1' === SSC_Settings::get( 'custom_endpoint' ) && 'me@example.com' === SSC_Settings::get( 'notify_email_to' ) && '09120000000' === SSC_Settings::get( 'sms_admin_phone' ) && '' === SSC_Settings::get( 'ai_webhook_url' ) && '111' === SSC_Settings::get( 'notify_chat_id' ), 'A file without keys cannot redirect the AI endpoint, notifications or SMS alerts' );
check( 'Harmless text' === SSC_Settings::get( 'welcome_text' ) && 'warn' === $report[0]['status'] && '' !== $report[0]['note'], 'Its other settings still import, and the held-back ones are reported' );
$forged  = array_merge( $redirect, array( 'secrets' => array( 'kdf' => 'pbkdf2-sha256', 'rounds' => 150000, 'salt' => 'AAAA', 'iv' => 'AAAA', 'data' => 'AAAA', 'mac' => 'AAAA' ) ) );
SSC_Transfer::apply( $forged, array( 'settings', 'secrets' ), 'merge', 'any-password-1' );
check( 'https://mine.example/v1' === SSC_Settings::get( 'custom_endpoint' ), 'A forged key block does not unlock the destination settings' );
$trusted = SSC_Transfer::build( array( 'settings', 'secrets' ), 'owner-password-1' );
$trusted['settings']['custom_endpoint'] = 'https://new-home.example/v1';
$trusted['settings']['notify_email_to'] = 'new-admin@example.com';
SSC_Transfer::apply( $trusted, array( 'settings', 'secrets' ), 'merge', 'owner-password-1' );
check( 'https://new-home.example/v1' === SSC_Settings::get( 'custom_endpoint' ) && 'new-admin@example.com' === SSC_Settings::get( 'notify_email_to' ), 'With the right password the destination settings travel with the keys' );
SSC_Settings::update( array( 'custom_endpoint' => 'https://mine.example/v1' ) );
SSC_Transfer::apply( $trusted, array( 'settings', 'secrets' ), 'merge', 'wrong-password-1' );
check( 'https://mine.example/v1' === SSC_Settings::get( 'custom_endpoint' ), 'With the wrong password they stay as they were' );
check( 16777216 === SSC_Transfer::MAX_BYTES && 'ssc_transfer_big' === SSC_Transfer::parse( str_repeat( ' ', SSC_Transfer::MAX_BYTES + 1 ) )->get_error_code(), 'Oversized files are refused before decoding' );

// Screens.
$_GET = array( 'page' => 'ssc-transfer' );
ob_start();
( new SSC_Admin_Transfer() )->render();
$transfer_html = ob_get_clean();
check( false !== strpos( $transfer_html, 'ssc_transfer_export' ) && false !== strpos( $transfer_html, 'name="export_password"' ) && false !== strpos( $transfer_html, 'ssc_transfer_undo' ), 'The Export & Import screen renders (export form, password field, undo)' );
ob_start();
( new SSC_Admin_About() )->render();
$about_html = ob_get_clean();
check( false !== strpos( $about_html, 'DbsStudio.ir' ) && false !== strpos( $about_html, 'https://dbsstudio.ir' ) && false !== strpos( $about_html, SSC_CHATBOT_VERSION ), 'The About screen names the maker, links DbsStudio.ir and shows the version' );
$_GET = array( 'page' => 'ssc-knowledge' );
ob_start();
( new SSC_Admin_Knowledge() )->render();
$knowledge_html = ob_get_clean();
check( false !== strpos( $knowledge_html, 'data-ssc-kring="overall"' ) && substr_count( $knowledge_html, 'role="tabpanel"' ) === 8 && substr_count( $knowledge_html, 'role="tab"' ) === 8, 'The Business Knowledge screen renders its hero, eight tiles and eight sections' );
check( false !== strpos( $knowledge_html, 'name="business[org_name]"' ) && false !== strpos( $knowledge_html, 'name="ki[0][content]"' ) && false !== strpos( $knowledge_html, 'name="products[0][name]"' ), 'The form keeps the field names the save handler reads' );
check( false !== strpos( $knowledge_html, 'id="ssc-ki-template"' ) && false !== strpos( $knowledge_html, 'id="ssc-product-template"' ), 'Blank row templates exist so an empty list can still grow' );
$_GET = array();
check( false === strpos( SSC_Admin_About::report(), 'sk-' ) && false === strpos( SSC_Admin_About::report(), 'enc::' ), 'The support report holds no keys' );
$_GET = array();

// Button spacing reaches the visitor's page and survives bad values.
$config = SSC_Plugin::instance()->frontend->build_config();
check( 96 === $config['offsetYMobile'] || isset( $config['offsetYMobile'] ), 'The widget receives the button spacing' );
check( is_bool( $config['avoidBars'] ), 'The widget receives the bottom-bar option as a boolean' );
check( 24 === SSC_Settings::clamp_int( 'launcher_offset_y', 24 ) && 300 === SSC_Settings::clamp_int( 'launcher_offset_y', 5000 ) && 0 === SSC_Settings::clamp_int( 'launcher_offset_x_mobile', -9 ), 'Spacing values are kept inside sensible bounds' );

// Put everything back.
foreach ( $before['options'] as $name => $value ) {
	if ( null === $value ) {
		delete_option( $name );
	} else {
		update_option( $name, $value, false );
	}
}
SSC_Settings::reset_cache();
SSC_Schema::qa_import( array(), true );
foreach ( $before['faq'] as $row ) {
	SSC_Schema::qa_insert( $row );
}
SSC_Schema::kb_delete_document( 'doc-transfer-a' );
SSC_Schema::kb_delete_document( 'wp-9991' );
SSC_Schema::kb_delete_document( 'doc-evil' );
