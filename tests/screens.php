<?php
/**
 * The redesigned admin screens: each one renders without PHP notices, keeps the
 * form fields its save handler reads, and shows the new building blocks (hero
 * ring, tiles, chips, empty states). Required from tests/integration.php.
 */

/** Render a screen and return its HTML plus any PHP notices raised on the way. */
function ssc_test_screen( $get, $render ) {
	$_GET     = $get;
	$notices  = array();
	$previous = set_error_handler(
		function ( $no, $message, $file, $line ) use ( &$notices ) {
			if ( false !== strpos( $file, 'vazho' ) || false !== strpos( $file, 'DbsChatBot' ) ) {
				$notices[] = $message . ' @ ' . basename( $file ) . ':' . $line;
			}
			return true;
		}
	);
	ob_start();
	$render();
	$html = ob_get_clean();
	set_error_handler( $previous );
	$_GET = array();
	return array( $html, $notices );
}

$screens_before = array(
	'modules'  => get_option( SSC_Modules::OPTION, null ),
	'settings' => get_option( SSC_Settings::OPTION_KEY, null ),
);

// Dashboard.
list( $html, $notices ) = ssc_test_screen( array( 'page' => 'ssc-dashboard' ), function () {
	( new SSC_Admin_Dashboard() )->render();
} );
check( array() === $notices, 'The Dashboard renders without PHP notices' );
check( false !== strpos( $html, 'ssc-dhero' ) && false !== strpos( $html, 'class="ssc-ring' ) && substr_count( $html, 'class="ssc-hstat"' ) + substr_count( $html, 'class="ssc-hstat ' ) >= 2, 'The Dashboard shows the readiness ring and icon tiles' );
check( substr_count( $html, 'ssc-dready__ico' ) === count( SSC_Setup::readiness() ), 'The readiness list has one row for every readiness check' );
check( false !== strpos( $html, 'page=ssc-knowledge#identity' ) && false !== strpos( $html, 'page=ssc-connection#engine' ), 'Each unfinished check links to the section where it is fixed' );

// The Gutenberg block carries the current brand and its editor texts come from the server (translated).
$ssc_block = WP_Block_Type_Registry::get_instance()->get_registered( 'ssc/chatbot' );
check( $ssc_block && 'Vazho' === $ssc_block->title && false === stripos( $ssc_block->title, 'smart' ), 'The block is named Vazho, not the old brand' );
check( false !== strpos( (string) file_get_contents( SSC_CHATBOT_DIR . 'blocks/chatbot/index.js' ), 'sscBlockI18n' ) && false === strpos( (string) file_get_contents( SSC_CHATBOT_DIR . 'blocks/chatbot/index.js' ), 'Smart Assistant' ), 'The block editor script reads its texts from the server and has no old brand' );

// About links work for marketplace customers without private repository access.
list( $html, $notices ) = ssc_test_screen( array( 'page' => 'ssc-about' ), function () {
	( new SSC_Admin_About() )->render();
} );
check( array() === $notices, 'The About screen renders its bundled changelog without PHP notices' );
check( false !== strpos( $html, 'https://dbsstudio.ir/issues' ) && false === strpos( $html, 'github.com' ), 'About reports problems to the public website and has no GitHub destinations' );
check( false !== strpos( $html, 'href="#ssc-about-changelog"' ) && false !== strpos( $html, 'id="ssc-about-changelog"' ) && false !== strpos( $html, '<details>' ), 'The changelog link opens release notes bundled in the installed plugin' );
check( ! empty( SSC_Admin_About::changelog() ) && SSC_CHATBOT_VERSION === SSC_Admin_About::changelog()[0]['version'], 'The bundled changelog starts with the installed plugin version' );
$ssc_log = SSC_Admin_About::changelog();
$ssc_kinds = array( '', 'fixed', 'new', 'changed', 'improved', 'removed', 'security', 'privacy', 'docs' );
$ssc_known = true;
foreach ( $ssc_log as $ssc_rel ) {
	foreach ( $ssc_rel['notes'] as $ssc_n ) {
		if ( ! in_array( $ssc_n['type'], $ssc_kinds, true ) || '' === trim( $ssc_n['text'] ) ) { $ssc_known = false; }
	}
}
check( $ssc_known, 'Every release note has a known kind (or none) and some text' );
check( false !== strpos( $html, 'ssc-achange__kind--' ) && false !== strpos( $html, 'ssc-achange__older' ) && 1 === substr_count( $html, 'ssc-achange__tag' ), 'The changelog labels each note, marks the installed version once and folds the older versions' );
check( '<code>x</code> &lt;b&gt;' === SSC_Admin_About::note_html( '`x` <b>' ), 'Changelog notes are escaped; only `code` spans become code' );

// AI connection.
list( $html, $notices ) = ssc_test_screen( array( 'page' => 'ssc-connection' ), function () {
	( new SSC_Admin_Connection() )->render();
} );
check( array() === $notices, 'The AI Connection screen renders without PHP notices' );
check( false !== strpos( $html, 'data-ssc-hub="connection"' ) && substr_count( $html, 'role="tabpanel"' ) === 4 && substr_count( $html, 'role="tab"' ) === 4, 'The AI Connection screen has four sections' );
check( false !== strpos( $html, 'id="ai_provider"' ) && false !== strpos( $html, 'id="ssc-test-connection"' ) && false !== strpos( $html, 'name="web_search"' ) && false !== strpos( $html, 'name="qa_mode"' ) && false !== strpos( $html, 'name="answer_scope"' ), 'It keeps the fields and the test button the script and the save handler use' );

// Appearance.
list( $html, $notices ) = ssc_test_screen( array( 'page' => 'ssc-appearance' ), function () {
	( new SSC_Admin_Appearance() )->render();
} );
check( array() === $notices, 'The Appearance screen renders without PHP notices' );
check( substr_count( $html, 'class="ssc-card ssc-hacc"' ) === 5 && false !== strpos( $html, 'id="ssc-posbox"' ) && false !== strpos( $html, 'id="ssc-live-preview"' ) && false !== strpos( $html, 'data-ssc-hubsave' ), 'Appearance has five collapsible sections, the preview and one save bar' );
$old_fields = array( 'primary_color', 'theme_mode', 'position', 'launcher_style', 'direction', 'widget_language', 'assistant_display_name', 'avatar_url', 'welcome_title', 'welcome_text', 'disclaimer', 'launcher_offset_y_mobile', 'launcher_offset_x_mobile', 'launcher_avoid_bars', 'launcher_offset_y', 'launcher_offset_x', 'font_family', 'font_size', 'window_width', 'window_radius', 'bubble_radius', 'user_bubble_color', 'bot_bubble_color', 'launcher_size', 'launcher_icon_url', 'font_name', 'font_url' );
$missing    = array();
foreach ( $old_fields as $field ) {
	if ( false === strpos( $html, 'name="' . $field . '"' ) ) {
		$missing[] = $field;
	}
}
check( array() === $missing, 'Appearance keeps every field name the save handler reads' . ( $missing ? ' (missing: ' . implode( ', ', $missing ) . ')' : '' ) );

// Settings, and the Modules screen that links into it.
list( $settings_html, $notices ) = ssc_test_screen( array( 'page' => 'ssc-settings' ), function () {
	( new SSC_Admin_Settings() )->render();
} );
check( array() === $notices, 'The Settings screen renders without PHP notices' );
check( false !== strpos( $settings_html, 'data-ssc-hub="settings"' ) && substr_count( $settings_html, 'role="tabpanel"' ) >= 4 && false !== strpos( $settings_html, 'data-ssc-hubsave' ), 'Settings is a hub with its sections and one save bar' );

list( $html, $notices ) = ssc_test_screen( array( 'page' => 'ssc-modules' ), function () {
	( new SSC_Admin_Modules() )->render();
} );
check( array() === $notices, 'The Modules screen renders without PHP notices' );
check( substr_count( $html, 'data-ssc-modstatus=' ) === count( SSC_Modules::all() ) && false !== strpos( $html, 'data-ssc-modfilter' ), 'Every module has a card and the status filter is there' );
preg_match_all( '/page=ssc-settings#(mod-[a-z]+)/', $html, $links );
$dead = array();
foreach ( array_unique( $links[1] ) as $anchor ) {
	if ( false === strpos( $settings_html, 'id="' . $anchor . '"' ) ) {
		$dead[] = $anchor;
	}
}
check( ! empty( $links[1] ) && array() === $dead, 'Every Configure link on the Modules screen lands on a real card in Settings' . ( $dead ? ' (dead: ' . implode( ', ', $dead ) . ')' : '' ) );
list( $html, ) = ssc_test_screen( array( 'page' => 'ssc-modules', 'ssc_search' => 'zzzz-no-such-module' ), function () {
	( new SSC_Admin_Modules() )->render();
} );
check( false !== strpos( $html, 'No modules match your search.' ) && false === strpos( $html, 'data-ssc-modstatus=' ), 'A search with no match shows the empty message' );

// Lists.
$leads = SSC_Modules::get( 'leads' );
list( $html, $notices ) = ssc_test_screen( array( 'page' => 'ssc-requests' ), function () use ( $leads ) {
	$leads->render_page();
} );
check( array() === $notices, 'The Requests screen renders without PHP notices' );
check( substr_count( $html, '<a class="ssc-fchip' ) === 5 && false !== strpos( $html, 'class="ssc-hstats"' ) && false !== strpos( $html, 'name="type"' ) && false !== strpos( $html, 'name="s"' ), 'Requests shows summary tiles, five status chips and the type and search filters' );
list( $html, ) = ssc_test_screen( array( 'page' => 'ssc-requests', 's' => 'zzzz-nobody-has-this-name' ), function () use ( $leads ) {
	$leads->render_page();
} );
check( false !== strpos( $html, 'ssc-empty2' ) && false !== strpos( $html, 'Clear filters' ), 'A filtered list with no rows offers to clear the filters' );

$faq = SSC_Modules::get( 'faq' );
list( $html, $notices ) = ssc_test_screen( array( 'page' => 'ssc-faq' ), function () use ( $faq ) {
	$faq->render_page();
} );
check( array() === $notices, 'The FAQ screen renders without PHP notices' );
check( false !== strpos( $html, 'name="ssc_faq_add"' ) && false !== strpos( $html, 'name="faq_file"' ) && false !== strpos( $html, 'class="ssc-card ssc-hacc"' ) && false !== strpos( $html, 'class="ssc-hstats"' ), 'FAQ keeps its add and import forms inside collapsible cards, under the summary tiles' );

$history = SSC_Modules::get( 'history' );
list( $html, $notices ) = ssc_test_screen( array( 'page' => 'ssc-conversations' ), function () use ( $history ) {
	$history->render_page();
} );
check( array() === $notices, 'The Conversations screen renders without PHP notices' );
check( false !== strpos( $html, 'class="ssc-hstats"' ) && false !== strpos( $html, 'aria-label="Answer source"' ) && false !== strpos( $html, 'aria-label="Rating"' ), 'Conversations shows tiles and the source and rating chips' );
list( $html, ) = ssc_test_screen( array( 'page' => 'ssc-conversations', 'conv' => str_repeat( 'a', 64 ) ), function () use ( $history ) {
	$history->render_page();
} );
check( false === strpos( $html, 'class="ssc-hstats"' ) && false !== strpos( $html, 'Back to all conversations' ), 'One whole conversation is shown without the summary' );
$counts = SSC_Schema::chatlog_counts();
check( $counts['total'] === array_sum( $counts['sources'] ) && $counts['up'] >= 0 && $counts['down'] >= 0, 'Chat log counts add up by source' );

$analytics = SSC_Modules::get( 'analytics' );
list( $html, $notices ) = ssc_test_screen( array( 'page' => 'ssc-analytics' ), function () use ( $analytics ) {
	$analytics->render_page();
} );
check( array() === $notices, 'The Analytics screen renders without PHP notices' );
check( false !== strpos( $html, 'class="ssc-hstats"' ) && false !== strpos( $html, 'ssc-bars__col' ), 'Analytics shows tiles and the daily volume' );

$pharma = SSC_Modules::get( 'pharma' );
list( $html, $notices ) = ssc_test_screen( array( 'page' => 'ssc-pharma' ), function () use ( $pharma ) {
	$pharma->render_page();
} );
check( array() === $notices, 'The ADR cases screen renders without PHP notices' );
check( false !== strpos( $html, 'class="ssc-hstats"' ) && substr_count( $html, '<a class="ssc-fchip' ) === count( SSC_Module_Pharma::case_statuses() ) + 1, 'ADR cases show tiles and one chip per case status' );

$live = SSC_Modules::get( 'live' );
list( $html, $notices ) = ssc_test_screen( array( 'page' => 'ssc-live' ), function () use ( $live ) {
	$live->render_page();
} );
check( array() === $notices && false !== strpos( $html, 'id="ssc-live-threads"' ) && false !== strpos( $html, 'ssc-live__emptyico' ), 'The Live chat inbox still has its list and pane, with a friendlier empty pane' );

// Wizard: the knowledge step.
list( $html, $notices ) = ssc_test_screen( array( 'page' => 'ssc-wizard', 'step' => 'knowledge' ), function () {
	$wizard = new SSC_Wizard();
	$wizard->render();
} );
check( array() === $notices, 'The wizard knowledge step renders without PHP notices' );
check( false !== strpos( $html, 'data-ssc-wk' ) && substr_count( $html, 'data-ssc-wkstart' ) === 5 && false !== strpos( $html, 'id="ssc-ki-list"' ) && false !== strpos( $html, 'name="ki[0][content]"' ) && false !== strpos( $html, 'name="import_url"' ), 'The knowledge step has its progress ring, five quick-start topics and the same fields' );

// The settings health ring is wired to real settings.
$health_env = array( 'https' => is_ssl(), 'crypto' => true, 'behind_proxy' => false, 'forms' => true, 'history' => true );
$health     = SSC_Settings_Health::compute( SSC_Settings::all(), $health_env );
check( $health['percent'] >= 0 && $health['percent'] <= 100, 'The health score stays between 0 and 100 for the live settings' );


// An OpenAI-compatible endpoint can take an API key (optional); a webhook is signed instead.
list( $html, ) = ssc_test_screen( array( 'page' => 'ssc-connection' ), function () {
	( new SSC_Admin_Connection() )->render();
} );
check( false !== strpos( $html, 'name="custom_api_key"' ) && false !== strpos( $html, 'API key (optional)' ) && false !== strpos( $html, 'name="openai_api_key"' ) && false === strpos( $html, 'name="ai_webhook_url_api_key"' ) && false === strpos( $html, 'name="webhook_api_key"' ), 'The custom endpoint has an optional API key field on the Connection screen; the webhook still uses a secret' );
check( ( new SSC_Provider_Custom() )->accepts_key() && ! ( new SSC_Provider_Custom() )->needs_key() && ! ( new SSC_Provider_Webhook() )->accepts_key() && ( new SSC_Provider_OpenAI() )->needs_key(), 'Providers say whether a key can be entered and whether it is required' );
SSC_Settings::set_secret( 'custom_api_key', 'sk-test-custom-key' );
$creds = ( new SSC_Provider_Custom() )->saved_credentials();
$parts = ( new SSC_Provider_Custom() )->request_parts( $creds['api_key'], 'm', 'sys', array( array( 'role' => 'user', 'content' => 'hi' ) ), array( 'endpoint' => 'https://gw.example/v1/chat/completions' ) );
check( 'sk-test-custom-key' === $creds['api_key'] && false !== strpos( wp_json_encode( $parts ), 'Bearer sk-test-custom-key' ), 'A stored custom key is sent as a Bearer token' );
SSC_Settings::set_secret( 'custom_api_key', '' );

// The Appearance screen and the wizard follow the page direction (they used to force left-to-right).
list( $html, ) = ssc_test_screen( array( 'page' => 'ssc-appearance' ), function () {
	( new SSC_Admin_Appearance() )->render();
} );
check( false === strpos( $html, 'class="ssc-page ssc-appearance" dir="ltr"' ) && false !== strpos( $html, 'id="ssc-preview-panel"' ), 'Appearance does not force left-to-right and still has the live preview' );
list( $html, ) = ssc_test_screen( array( 'page' => 'ssc-wizard', 'step' => 'appearance' ), function () {
	( new SSC_Wizard() )->render();
} );
check( false === strpos( $html, 'class="ssc-wizard" dir="ltr"' ) && false !== strpos( $html, 'id="ssc-preview-panel"' ) && false === strpos( $html, 'ssc-preview__win' ), 'The wizard does not force left-to-right and its Appearance step uses the real live preview' );

// Hours, phone, email and addresses are written once; places carry map links.
$old_settings = SSC_Settings::all();
SSC_Settings::update(
	array(
		'business'                => SSC_Settings::sanitize_business(
			array(
				'org_name'       => 'Atlas',
				'phone'          => '021-92001520',
				'email'          => 'info@atlas.example',
				'hours'          => '',
				'assistant_name' => '',
				'locations'      => array(
					array( 'type' => 'hq', 'name' => 'دفتر مرکزی', 'address' => 'تهران، ولیعصر ۱۲', 'phone' => '021-1', 'map' => 'https://maps.app.goo.gl/AbC' ),
					array( 'type' => 'factory', 'address' => 'Qazvin plot 5', 'map' => 'javascript:alert(1)' ),
				),
			)
		),
		'assistant_display_name'  => 'نفس‌یار',
		'business_hours_enabled'  => 'yes',
		'business_hours_days'     => '6,7,1,2,3',
		'business_hours_start'    => '09:00',
		'business_hours_end'      => '17:00',
		'business_timezone'       => '',
	)
);
$saved = SSC_Settings::business();
check( 2 === count( $saved['locations'] ) && '' === $saved['locations'][1]['map'] && 'تهران، ولیعصر ۱۲' === $saved['location'], 'Saved places keep their map links, drop script links and mirror the first address' );
$prompt = SSC_Prompt_Builder::build_for_chat( 'where is the factory?', 'general', '', 'web' );
check( false !== strpos( $prompt, 'https://maps.app.goo.gl/AbC' ) && false !== strpos( $prompt, 'Qazvin plot 5' ) && false === strpos( $prompt, '- Location:' ), 'The assistant is given every place with its map link, instead of one address' );
check( false !== strpos( $prompt, 'Working hours: ' . SSC_Availability::describe_schedule() ) && false !== strpos( SSC_Availability::describe_schedule(), '09:00–17:00' ), 'With no hours written in the profile the assistant quotes the chat schedule' );
check( false !== strpos( $prompt, 'نفس‌یار' ), 'A name set only in Appearance is the name the assistant introduces itself with' );
check( 'نفس‌یار' === SSC_Frontend::display_name( SSC_Settings::all() ) && 'Sara' === SSC_Frontend::display_name( array( 'assistant_display_name' => '', 'business' => array( 'assistant_name' => 'Sara' ) ) ) && 'Vazho' === SSC_Frontend::display_name( array( 'assistant_display_name' => '', 'business' => array() ) ), 'The chat header shows the Appearance name, else the persona name, else Vazho' );
SSC_Settings::update( array( 'business' => array_merge( SSC_Settings::business(), array( 'hours' => 'شنبه تا چهارشنبه ۹ تا ۱۷' ) ) ) );
check( false !== strpos( SSC_Prompt_Builder::build_for_chat( 'hours?', 'general', '', 'web' ), 'Working hours: شنبه تا چهارشنبه ۹ تا ۱۷' ), 'Hours written in the profile win over the schedule');

// Places survive an export and an import.
$bundle = SSC_Transfer::build( array( 'business' ), '' );
SSC_Settings::update( array( 'business' => SSC_Settings::sanitize_business( array( 'org_name' => 'Other' ) ) ) );
SSC_Transfer::apply( $bundle, array( 'business' ), 'replace', '' );
$after = SSC_Settings::business();
check( 2 === count( $after['locations'] ) && 'https://maps.app.goo.gl/AbC' === $after['locations'][0]['map'], 'Places travel in the export file and come back on import' );

// The wizard's identity step must not wipe the places.
$_POST = array( 'business' => array( 'org_name' => 'Atlas', 'location' => 'ignored' ) );
$method = new ReflectionMethod( 'SSC_Wizard', 'posted_business' );
$method->setAccessible( true );
$posted = $method->invoke( new SSC_Wizard() );
$_POST  = array();
check( 2 === count( $posted['locations'] ) && 'تهران، ولیعصر ۱۲' === $posted['location'], 'Saving the wizard identity step keeps a saved list of places' );

// Knowledge screen: places editor, hours hint, and notes on entries that repeat the profile.
SSC_Settings::update(
	array(
		'knowledge_items' => array(
			array( 'id' => 'ki-a', 'type' => 'general', 'title' => 'ساعات کاری', 'content' => 'شنبه تا پنجشنبه' ),
			array( 'id' => 'ki-b', 'type' => 'support', 'title' => 'سفارش تلفنی', 'content' => 'با ۰۹۱۲۳۴۵۶۷۸۹ تماس بگیرید' ),
			array( 'id' => 'ki-c', 'type' => 'policy', 'title' => 'مرجوعی', 'content' => 'هفت روز' ),
		),
	)
);
list( $html, $notices ) = ssc_test_screen( array( 'page' => 'ssc-knowledge' ), function () {
	( new SSC_Admin_Knowledge() )->render();
} );
check( array() === $notices && substr_count( $html, 'class="ssc-koverlap"' ) === 2 && substr_count( $html, 'has-overlap' ) >= 2, 'Two of the three entries are flagged: the hours and the other phone number; the policy is not' );
check( false !== strpos( $html, 'id="ssc-loc-list"' ) && false !== strpos( $html, 'id="ssc-loc-template"' ) && false !== strpos( $html, 'name="business[locations][1][map]"' ) && false === strpos( $html, 'name="business[location]"' ), 'The Contact section edits places (with a blank template) instead of one address' );
check( false === strpos( $html, 'data-title="Working hours"' ) && false !== strpos( $html, 'data-ssc-fill="b_hours"' ), 'Working hours are no longer offered as an entry, and the hours field can take the chat schedule' );
list( $html, ) = ssc_test_screen( array( 'page' => 'ssc-wizard', 'step' => 'knowledge' ), function () {
	( new SSC_Wizard() )->render();
} );
check( substr_count( $html, 'data-ssc-wkstart' ) === 5 && false === strpos( $html, 'data-title="Opening hours"' ) && false === strpos( $html, 'data-title="How to contact us"' ), 'The wizard quick-start topics leave hours and contact details to the profile' );
list( $html, ) = ssc_test_screen( array( 'page' => 'ssc-settings' ), function () {
	( new SSC_Admin_Settings() )->render();
} );
check( false !== strpos( $html, 'ssc-hoursnote' ) && false !== strpos( $html, 'page=ssc-knowledge#contact' ), 'Settings says the schedule only decides when the chat is online and points to where the hours are written' );

// Put the settings back.
if ( null === $screens_before['settings'] ) {
	delete_option( SSC_Settings::OPTION_KEY );
} else {
	update_option( SSC_Settings::OPTION_KEY, $screens_before['settings'] );
}
SSC_Settings::reset_cache();

if ( null === $screens_before['modules'] ) {
	delete_option( SSC_Modules::OPTION );
} else {
	update_option( SSC_Modules::OPTION, $screens_before['modules'], false );
}
