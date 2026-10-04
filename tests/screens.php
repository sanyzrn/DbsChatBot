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
			if ( false !== strpos( $file, 'nexachat-ai' ) || false !== strpos( $file, 'DbsChatBot' ) ) {
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
	'modules' => get_option( SSC_Modules::OPTION, null ),
);

// Dashboard.
list( $html, $notices ) = ssc_test_screen( array( 'page' => 'ssc-dashboard' ), function () {
	( new SSC_Admin_Dashboard() )->render();
} );
check( array() === $notices, 'The Dashboard renders without PHP notices' );
check( false !== strpos( $html, 'ssc-dhero' ) && false !== strpos( $html, 'class="ssc-ring' ) && substr_count( $html, 'class="ssc-hstat"' ) + substr_count( $html, 'class="ssc-hstat ' ) >= 2, 'The Dashboard shows the readiness ring and icon tiles' );
check( substr_count( $html, 'ssc-dready__ico' ) === count( SSC_Setup::readiness() ), 'The readiness list has one row for every readiness check' );
check( false !== strpos( $html, 'page=ssc-knowledge#identity' ) && false !== strpos( $html, 'page=ssc-connection#engine' ), 'Each unfinished check links to the section where it is fixed' );

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

if ( null === $screens_before['modules'] ) {
	delete_option( SSC_Modules::OPTION );
} else {
	update_option( SSC_Modules::OPTION, $screens_before['modules'], false );
}
