<?php
/** Dependency-free tests for pure boundaries; integration.php uses actual WordPress. */
define( 'ABSPATH', __DIR__ );
function __( $text, $domain = '' ) { return $text; }
function sanitize_text_field( $text ) { return trim( strip_tags( $text ) ); }
function sanitize_textarea_field( $text ) { return trim( strip_tags( $text ) ); }
function apply_filters( $hook, $value ) { return $value; }
class SSC_Settings {
    public static $values = array();
    public static function get( $key, $default = null ) { return self::$values[ $key ] ?? $default; }
}
foreach ( array( 'input', 'availability', 'http', 'provider' ) as $file ) {
    require __DIR__ . '/../includes/core/class-ssc-' . $file . '.php';
}
foreach ( array( 'openai-compat', 'openai', 'claude', 'gemini' ) as $file ) {
    require __DIR__ . '/../includes/core/providers/class-ssc-provider-' . $file . '.php';
}
$count = 0;
function check( $condition, $label ) {
    global $count;
    if ( ! $condition ) { fwrite( STDERR, "FAIL: $label\n" ); exit( 1 ); }
    ++$count;
}
foreach ( array( false, 'false', '0', 0, '', array( 1 ), new stdClass() ) as $value ) {
    check( ! SSC_Input::consent( $value ), 'negative/structured consent rejected' );
}
check( SSC_Input::consent( '1' ) && SSC_Input::consent( true ), 'explicit consent accepted' );
check( SSC_Input::phone( '۰۹۱۲۳۴۵۶۷۸۹' ) === '09123456789', 'Persian digit normalization' );
check( SSC_Input::text( array( 'bad' ) ) === '', 'structured text rejected' );
check( SSC_Input::text( 'سلام دنیا', 4 ) === 'سلام', 'Unicode character limit' );
check( SSC_Input::list_value( '["death",["bad"],"hospitalization"]' ) === array( 'death', 'hospitalization' ), 'JSON list sanitization' );
foreach ( array( '=SUM(A1)', '+1', '-1', '@SUM(A1)', "\t=1", '  =1' ) as $value ) {
    check( SSC_Input::csv_cell( $value ) === "'" . $value, 'CSV injection blocked' );
}
check( SSC_Input::csv_cell( '' ) === '' && SSC_Input::csv_cell( 'safe' ) === 'safe', 'safe CSV cells preserved' );
SSC_Settings::$values = array( 'business_hours_days' => '1', 'business_hours_start' => '22:00', 'business_hours_end' => '06:00' );
check( SSC_Availability::is_online_at( new DateTime( '2026-09-15 02:00' ) ), 'overnight shift spills into following day' );
check( ! SSC_Availability::is_online_at( new DateTime( '2026-09-14 02:00' ) ), 'overnight shift does not spill backwards' );
check( ! SSC_Availability::is_online_at( new DateTime( '2026-09-15 06:00' ) ), 'closing boundary' );
check( SSC_HTTP::map_status( 429, 'insufficient_quota' ) === 'credits', 'credits error' );
check( SSC_HTTP::map_status( 429, 'Too many requests' ) === 'rate_limit', 'rate error' );
check( SSC_HTTP::map_status( 403, 'Country not supported' ) === 'region', 'regional error' );
$openai = new SSC_Provider_Openai();
$request = $openai->request_parts( 'test-key', 'test-model', 'system instruction', array( array( 'role' => 'user', 'content' => 'hello' ) ) );
check( $request['body']['messages'][0]['role'] === 'system', 'system prompt kept separate' );
check( $request['headers']['Authorization'] === 'Bearer test-key', 'credential travels as a header' );
check( $openai->extract_text( array( 'choices' => array( array( 'message' => array( 'content' => array( array( 'text' => 'hello' ), array( 'text' => ' world' ) ) ) ) ) ) ) === 'hello world', 'compatible content-part parsing' );
$claude = new SSC_Provider_Claude();
check( $claude->extract_text( array( 'content' => array( array( 'type' => 'text', 'text' => 'hello' ) ) ) ) === 'hello', 'Claude text parsing' );
$gemini = new SSC_Provider_Gemini();
check( $gemini->extract_text( array( 'candidates' => array( array( 'content' => array( 'parts' => array( array( 'text' => 'hello' ) ) ) ) ) ) ) === 'hello', 'Gemini text parsing' );
$csv = fopen( 'php://temp', 'w+' );
SSC_Input::write_csv( $csv, array( '=1+1', 'a,"b"', 'back\\slash', '' ) );
rewind( $csv );
check( fgetcsv( $csv, 0, ',', '"', '' ) === array( "'=1+1", 'a,"b"', 'back\\slash', '' ), 'CSV exports prevent formulas and preserve literal values' );
fclose( $csv );
check( $gemini->extract_text( array( 'candidates' => array( array( 'content' => array( 'parts' => array( array( 'text' => 'internal', 'thought' => true ), array( 'text' => 'answer' ) ) ) ) ) ) ) === 'answer', 'Gemini thought parts are not exposed as the answer' );
echo "$count unit checks passed.\n";
