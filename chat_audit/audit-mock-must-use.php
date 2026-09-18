<?php
/**
 * AUDIT MUST-USE MOCK — scripted AI provider responses for the isolated test site only.
 * Logs every outbound model request SSC makes (URL, body) so the auditor can inspect
 * the exact prompt/messages the plugin assembles. Registered via pre_http_request.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! defined( 'SSC_AUDIT_MOCK' ) ) {
        define( 'SSC_AUDIT_MOCK', true );
}

function ssc_audit_mock_get_script() {
        $file = wp_upload_dir()['basedir'] . '/audit-mock-script.json';
        if ( ! file_exists( $file ) ) { return array( 'default' => 'MOCK-OK default reply' ); }
        $data = json_decode( (string) file_get_contents( $file ), true );
        return is_array( $data ) ? $data : array( 'default' => 'MOCK-OK default reply' );
}

add_filter( 'pre_http_request', function ( $pre, $args, $url ) {
        // Never intercept WP core/update traffic to wordpress.org.
        if ( false !== strpos( (string) $url, 'wordpress.org' ) || false !== strpos( (string) $url, 'api.wordpress.org' ) ) {
                return $pre;
        }
        $script = ssc_audit_mock_get_script();
        $body   = json_decode( (string) ( $args['body'] ?? '' ), true );
        $entry  = array(
                'time' => microtime( true ),
                'url'  => $url,
                'body' => $body,
        );
        $script_name = isset( $script['next'] ) ? (string) $script['next'] : 'default';
        $reply_text  = isset( $script[ $script_name ] ) ? (string) $script[ $script_name ] : 'MOCK-OK default reply';
        $entry['script'] = $script_name;

        // Record capture (append; keep last 50).
        $cap_file = wp_upload_dir()['basedir'] . '/audit-mock-captured.json';
        $cap = file_exists( $cap_file ) ? json_decode( (string) file_get_contents( $cap_file ), true ) : array();
        if ( ! is_array( $cap ) ) { $cap = array(); }
        $cap[] = $entry;
        $cap = array_slice( $cap, -50 );
        file_put_contents( $cap_file, wp_json_encode( $cap ) );

        // Consume the script entry (one-shot unless 'sticky' set).
        if ( empty( $script['sticky'] ) ) {
                unset( $script['next'] );
                file_put_contents( wp_upload_dir()['basedir'] . '/audit-mock-script.json', wp_json_encode( $script ) );
        }

        // Answer in the dialect the caller expects.
        if ( false !== strpos( $url, 'generativelanguage.googleapis.com' ) ) {
                $payload = array( 'candidates' => array( array( 'content' => array( 'parts' => array( array( 'text' => $reply_text ) ), 'role' => 'model' ) ) ) );
        } elseif ( false !== strpos( $url, 'api.anthropic.com' ) ) {
                $payload = array( 'content' => array( array( 'type' => 'text', 'text' => $reply_text ) ) );
        } else {
                $payload = array( 'choices' => array( array( 'index' => 0, 'finish_reason' => 'stop', 'message' => array( 'role' => 'assistant', 'content' => $reply_text ) ) ) );
        }
        return array(
                'headers'  => array( 'content-type' => 'application/json' ),
                'body'     => wp_json_encode( $payload ),
                'response' => array(
                        'code'    => 200,
                        'message' => 'OK',
                ),
        );
}, 10, 3 );
