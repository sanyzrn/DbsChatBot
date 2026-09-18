<?php
/**
 * Settings store: schema, defaults, access, sanitization, secret vault.
 *
 * Data-compatibility contract with 4.x:
 * - The option key `ssc_chatbot_settings` is unchanged, so every existing
 *   installation keeps its data through the 5.0 upgrade.
 * - New keys merge over defaults; unknown legacy keys are preserved.
 *
 * @package SmartSupportChatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

/**
 * Settings class.
 */
class SSC_Settings {

        const OPTION_KEY = 'ssc_chatbot_settings';

        /**
         * Runtime cache.
         *
         * @var array|null
         */
        protected static $cache = null;

        /**
         * Full default schema. Type hints drive sanitize_value().
         *
         * @return array
         */
        public static function defaults() {
                return array(
                        /* ---------- Layer A: Core Engine ---------- */

                        // Publication. The public widget additionally requires setup completion
                        // AND an explicit publish action (see SSC_Setup::is_live()).
                        'enabled'        => 'no',

                        // Structured business identity (wizard step 1).
                        'business'       => array(
                                'org_name'     => '',
                                'brand_name'   => '',
                                'category'     => '',
                                'industry'     => '',
                                'description'  => '',
                                'products'     => '',
                                'differentiators' => '',
                                'url'          => '',
                                'location'     => '',
                                'phone'        => '',
                                'email'        => '',
                                'support_phone' => '',
                                'support_email' => '',
                                'hours'        => '',
                                'language'     => '',
                                'assistant_name' => '',
                                'assistant_role' => '',
                                'tone'         => 'professional',
                        ),

                        // Structured knowledge entries (wizard step 2 / knowledge page).
                        'knowledge_items' => array(),

                        // Product & service catalog with flexible attributes.
                        'products'       => array(),

                        // AI provider connection (wizard step 3).
                        'ai_provider'    => 'none', // none | openai | gemini | claude | openrouter | custom | webhook.
                        'openai_api_key' => '',
                        'openai_model'   => 'gpt-4o-mini',
                        'gemini_api_key' => '',
                        'gemini_model'   => 'gemini-2.5-flash',
                        'claude_api_key' => '',
                        'claude_model'   => 'claude-sonnet-4-5',
                        'openrouter_api_key' => '',
                        'openrouter_model' => '',
                        'custom_api_key' => '',
                        'custom_endpoint' => '',
                        'custom_model'   => '',
                        'ai_webhook_url' => '',
                        'ai_webhook_secret' => '',

                        // Generation behaviour.
                        'ai_temperature' => '0.4',
                        'ai_max_tokens'  => 800,
                        'ai_history_limit' => 8,
                        'ai_system_prompt_extra' => '',
                        'ai_strict_knowledge' => 'no',
                        'ai_fallback_msg' => '',
                        'ai_cache_enabled' => 'yes',
                        'pharma_answer_mode' => 'approved_only',

                        // Response engine priority: ai_first | bank_first | bank_only.
                        'qa_mode'        => 'ai_first',

                        // Appearance (wizard step 4).
                        'theme_mode'     => 'light', // light | dark | auto.
                        'primary_color'  => '#b61615',
                        'position'       => 'right',
                        'direction'      => 'rtl',  // rtl | ltr | auto.
                        'assistant_display_name' => '',
                        'welcome_title'  => '',
                        'welcome_text'   => '',
                        'disclaimer'     => '',
                        'avatar_url'     => '',
                        'launcher_size'  => 60,
                        'launcher_icon'  => 'chat',
                        'launcher_icon_url' => '',
                        'font_family'    => 'vazirmatn', // vazirmatn | inter | roboto | system | custom.
                        'font_name'      => '',
                        'font_url'       => '',
                        'font_size'      => 14,
                        'window_width'   => 384,
                        'window_radius'  => 24,
                        'bubble_radius'  => 16,
                        'user_bubble_color' => '',
                        'bot_bubble_color' => '',

                        // Privacy & consent (never optional once forms collect data).
                        'consent_enabled' => 'no',
                        'consent_text'   => '',
                        'consent_link'   => '',
                        'privacy_acknowledged' => 'no',
                        'chatlog_retention_days' => 90,
                        'submissions_retention_days' => 0,

                        // Abuse protection per bucket (requests / day / identity).
                        'rate_limit_mode' => 'ip',
                        'chat_rate_limit' => 100,
                        'submit_rate_limit' => 20,
                        'session_rate_limit' => 50,
                        // Trusted reverse-proxy header for real client IPs (empty = REMOTE_ADDR).
                        // Required behind Cloudflare / nginx / load balancers so rate limits
                        // are per-visitor instead of per-proxy.
                        'trusted_proxy_header' => '',

                        // Display targeting (server-gated; pages filtered before assets load).
                        'display_mode'    => 'all', // all | include | exclude.
                        'display_paths'   => '',   // newline/comma path patterns with * wildcards.
                        'display_devices' => 'all', // all | desktop | mobile.
                        'display_users'   => 'all', // all | guest | logged_in.

                        // Business hours / offline mode.
                        'business_hours_enabled' => 'no',
                        'business_hours_start'   => '09:00',
                        'business_hours_end'     => '18:00',
                        'business_hours_days'    => '1,2,3,4,5',
                        'business_timezone'      => '',
                        'offline_message'        => '',

                        // Widget behaviour.
                        'sound_enabled'     => 'no',
                        'streaming_enabled' => 'yes',

                        /* ---------- Module settings (flat keys, module-scoped) ---------- */

                        // Voice module.
                        'voice_input'     => 'yes',
                        'voice_output'    => 'yes',
                        'voice_language'  => 'auto', // ISO code or auto (= site language).

                        // History module (conversation logging).
                        'chatlog_enabled' => 'no',

                        // Leads module.
                        'form_fields'     => array(),

                        // CSAT module.
                        'csat_enabled'    => 'yes',

                        // Handoff module.
                        'handoff_text'    => '',

                        // Proactive module.
                        'proactive_delay' => 12,
                        'proactive_text'  => '',

                        // Notifications module.
                        'notify_platform' => 'bale',
                        'notify_token'    => '',
                        'notify_chat_id'  => '',
                        'notify_email_enabled' => 'no',
                        'notify_email_to' => '',

                        // FAQ module.
                        'faq_menu_label'  => '',

                        // Knowledge retrieval tuning (core-adjacent, advanced).
                        'kb_max_chunks'   => 3,

                        /* ---------- Legacy keys kept for backward read compatibility ---------- */
                        // Old keys such as company_name, quick_replies, office_* are migrated
                        // into the new schema by migrate_v4() and are not listed here.
                );
        }

        /**
         * All settings merged with defaults (cached per request).
         *
         * @return array
         */
        public static function all() {
                if ( null !== self::$cache ) {
                        return self::$cache;
                }
                $saved = get_option( self::OPTION_KEY, array() );
                $saved = is_array( $saved ) ? $saved : array();
                self::$cache = self::merge_defaults( $saved, self::defaults() );
                return self::$cache;
        }

        /**
         * Deep-ish merge that preserves unknown legacy keys and structured arrays.
         *
         * @param array $saved    Persisted values.
         * @param array $defaults Schema defaults.
         * @return array
         */
        public static function merge_defaults( $saved, $defaults ) {
                $out = $defaults;
                foreach ( $saved as $k => $v ) {
                        if ( 'business' === $k && array_key_exists( $k, $defaults ) && is_array( $defaults[ $k ] ) && is_array( $v ) ) {
                                // Structured bucket (business) merges per-key.
                                $out[ $k ] = array_merge( $defaults[ $k ], $v );
                        } else {
                                $out[ $k ] = $v;
                        }
                }
                return $out;
        }

        /**
         * Single setting.
         *
         * @param string $key     Key.
         * @param mixed  $default Fallback default.
         * @return mixed
         */
        public static function get( $key, $default = null ) {
                $all = self::all();
                if ( array_key_exists( $key, $all ) ) {
                        return $all[ $key ];
                }
                return $default;
        }

        /**
         * Business profile (merged with defaults, empty strings stripped).
         *
         * @return array
         */
        public static function business() {
                $b = self::get( 'business', array() );
                return is_array( $b ) ? array_merge( self::defaults()['business'], $b ) : self::defaults()['business'];
        }

        /**
         * Persist settings (merged over current state), with cache invalidation.
         *
         * @param array $settings Partial or full settings.
         */
        public static function update( $settings ) {
                $merged = self::merge_defaults( is_array( $settings ) ? $settings : array(), self::all() );
                // The option can grow large with product catalogs: autoload off.
                update_option( self::OPTION_KEY, $merged, false );
                self::$cache = null;
                self::flush_ai_cache();
        }

        /**
         * Delete one settings key entirely.
         *
         * @param string $key Key.
         */
        public static function delete( $key ) {
                $all = self::all();
                if ( array_key_exists( $key, $all ) ) {
                        unset( $all[ $key ] );
                        update_option( self::OPTION_KEY, $all, false );
                        self::$cache = null;
                }
        }

        /**
         * Seed the option on first activation only (never overwrite existing data).
         */
        public static function seed_defaults() {
                if ( false === get_option( self::OPTION_KEY, false ) ) {
                        add_option( self::OPTION_KEY, self::defaults(), '', 'no' );
                }
        }

        /**
         * Invalidate AI response cache when anything that influences answers changes.
         */
        public static function flush_ai_cache() {
                global $wpdb;
                if ( ! isset( $wpdb ) || ! is_object( $wpdb ) || empty( $wpdb->options ) ) {
                        return;
                }
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- targeted transient purge without object-cache API for prefixes.
                $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_ssc\\_ai\\_%' OR option_name LIKE '\\_transient\\_timeout\\_ssc\\_ai\\_%'" );
        }

        /* ------------------------------------------------------------------ *
         * Secret vault (AES-256-CBC + HMAC-SHA256, encrypt-then-MAC).
         * ------------------------------------------------------------------ */

        /**
         * Keys that are stored encrypted at rest.
         *
         * @return string[]
         */
        public static function secret_fields() {
                return array( 'openai_api_key', 'gemini_api_key', 'claude_api_key', 'openrouter_api_key', 'custom_api_key', 'ai_webhook_secret', 'notify_token' );
        }

        /**
         * Encrypt a value (v2 authenticated format).
         *
         * Returns false when encryption is unavailable or fails — callers must
         * NOT fall back to storing the plaintext secret.
         *
         * @param string $value Plain value.
         * @return string|false Encrypted blob, or false on failure.
         */
        public static function encrypt( $value ) {
                $value = (string) $value;
                if ( '' === $value ) {
                        return '';
                }
                if ( ! function_exists( 'openssl_encrypt' ) || ! defined( 'AUTH_KEY' ) || '' === AUTH_KEY ) {
                        return false;
                }
                $enc_key   = hash( 'sha256', AUTH_KEY, true );
                $mac_key   = self::mac_key();
                $iv        = openssl_random_pseudo_bytes( 16 );
                $encrypted = openssl_encrypt( $value, 'aes-256-cbc', $enc_key, OPENSSL_RAW_DATA, $iv );
                if ( false === $encrypted ) {
                        return false;
                }
                $cipher = $iv . $encrypted;
                $mac    = hash_hmac( 'sha256', $cipher, $mac_key, true );
                return 'enc::v2::' . base64_encode( $mac . $cipher ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
        }

        /**
         * MAC derivation key.
         *
         * @return string
         */
        protected static function mac_key() {
                return hash( 'sha256', ( defined( 'LOGGED_IN_KEY' ) ? LOGGED_IN_KEY : AUTH_KEY ) . '|ssc-mac', true );
        }

        /**
         * Decrypt a stored value (v2 authenticated, v1 legacy, or plain).
         *
         * @param string $value Stored value.
         * @return string
         */
        public static function decrypt( $value ) {
                $value = (string) $value;

                if ( 0 === strpos( $value, 'enc::v2::' ) ) {
                        if ( ! function_exists( 'openssl_decrypt' ) || ! defined( 'AUTH_KEY' ) ) {
                                return '';
                        }
                        $raw = base64_decode( substr( $value, 9 ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
                        if ( false === $raw || strlen( $raw ) < 49 ) {
                                return '';
                        }
                        $mac    = substr( $raw, 0, 32 );
                        $cipher = substr( $raw, 32 );
                        if ( ! hash_equals( hash_hmac( 'sha256', $cipher, self::mac_key(), true ), $mac ) ) {
                                return '';
                        }
                        $iv   = substr( $cipher, 0, 16 );
                        $data = substr( $cipher, 16 );
                        $dec  = openssl_decrypt( $data, 'aes-256-cbc', hash( 'sha256', AUTH_KEY, true ), OPENSSL_RAW_DATA, $iv );
                        return ( false === $dec ) ? '' : $dec;
                }

                if ( 0 === strpos( $value, 'enc::v1::' ) ) {
                        if ( ! function_exists( 'openssl_decrypt' ) || ! defined( 'AUTH_KEY' ) ) {
                                return '';
                        }
                        $raw = base64_decode( substr( $value, 9 ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
                        if ( false === $raw || strlen( $raw ) < 17 ) {
                                return '';
                        }
                        $iv        = substr( $raw, 0, 16 );
                        $encrypted = substr( $raw, 16 );
                        $dec       = openssl_decrypt( $encrypted, 'aes-256-cbc', hash( 'sha256', AUTH_KEY, true ), OPENSSL_RAW_DATA, $iv );
                        return ( false === $dec ) ? '' : $dec;
                }

                return $value;
        }

        /**
         * Decrypted secret value.
         *
         * @param string $key Secret key name.
         * @return string
         */
        public static function get_secret( $key ) {
                return self::decrypt( (string) self::get( $key, '' ) );
        }

        /**
         * Whether a non-empty secret exists (without exposing it).
         *
         * @param string $key Secret key name.
         * @return bool
         */
        public static function has_secret( $key ) {
                return '' !== trim( self::get_secret( $key ) );
        }

        /**
         * Store a secret encrypted. Empty value DELETES the secret (clearable keys).
         *
         * Returns false when encryption fails — the secret is never stored in
         * plaintext. Callers should surface an admin-facing error.
         *
         * @param string $key   Secret key name.
         * @param string $value Plain value ('' removes).
         * @return bool True on success (including clear), false if encrypt failed.
         */
        public static function set_secret( $key, $value ) {
                $all   = self::all();
                $value = trim( (string) $value );
                if ( '' === $value ) {
                        unset( $all[ $key ] );
                        update_option( self::OPTION_KEY, $all, false );
                        self::$cache = null;
                        return true;
                }
                $encrypted = self::encrypt( $value );
                if ( false === $encrypted ) {
                        return false;
                }
                $all[ $key ] = $encrypted;
                update_option( self::OPTION_KEY, $all, false );
                self::$cache = null;
                return true;
        }

        /* ------------------------------------------------------------------ *
         * Sanitization (schema-driven, shared by admin pages, wizard, REST).
         * ------------------------------------------------------------------ */

        /**
         * Sanitize one value by key according to the default schema type.
         *
         * @param string $key   Setting key.
         * @param mixed  $value Raw value.
         * @return mixed Sanitized value.
         */
        public static function sanitize_value( $key, $value ) {
                $defaults = self::defaults();
                if ( ! array_key_exists( $key, $defaults ) ) {
                        return null; // Unknown keys are dropped by policy.
                }
                $dv = $defaults[ $key ];

                if ( is_array( $dv ) ) {
                        return self::sanitize_list( $key, is_array( $value ) ? $value : array() );
                }
                if ( is_int( $dv ) ) {
                        return (int) $value;
                }
                if ( is_bool( $dv ) ) {
                        return (bool) $value;
                }

                switch ( $key ) {
                        case 'pharma_answer_mode':
                                return in_array( $value, array( 'approved_only', 'general_education' ), true ) ? $value : 'approved_only';
                        case 'enabled':
                        case 'ai_strict_knowledge':
                        case 'ai_cache_enabled':
                        case 'consent_enabled':
                        case 'privacy_acknowledged':
                        case 'voice_input':
                        case 'voice_output':
                        case 'chatlog_enabled':
                        case 'csat_enabled':
                        case 'notify_email_enabled':
                        case 'business_hours_enabled':
                        case 'sound_enabled':
                        case 'streaming_enabled':
                                return ( 'yes' === $value || '1' === (string) $value || true === $value ) ? 'yes' : 'no';

                        case 'ai_provider':
                                $providers = array_keys( SSC_Providers::all() );
                                return in_array( $value, $providers, true ) ? $value : 'none';

                        case 'qa_mode':
                                return in_array( $value, array( 'ai_first', 'bank_first', 'bank_only' ), true ) ? $value : 'ai_first';

                        case 'theme_mode':
                                return in_array( $value, array( 'light', 'dark', 'auto' ), true ) ? $value : 'light';

                        case 'position':
                                return in_array( $value, array( 'right', 'left' ), true ) ? $value : 'right';

                        case 'direction':
                                return in_array( $value, array( 'rtl', 'ltr', 'auto' ), true ) ? $value : 'rtl';

                        case 'rate_limit_mode':
                                return in_array( $value, array( 'ip', 'session', 'both', 'off' ), true ) ? $value : 'ip';

                        case 'trusted_proxy_header':
                                $allowed = array( '', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CF_CONNECTING_IP', 'HTTP_TRUE_CLIENT_IP', 'HTTP_X_CLIENT_IP', 'HTTP_FASTLY_CLIENT_IP' );
                                $allowed = apply_filters( 'ssc_trusted_proxy_headers', $allowed );
                                return in_array( $value, $allowed, true ) ? $value : '';

                        case 'display_mode':
                                return in_array( $value, array( 'all', 'include', 'exclude' ), true ) ? $value : 'all';

                        case 'display_devices':
                                return in_array( $value, array( 'all', 'desktop', 'mobile' ), true ) ? $value : 'all';

                        case 'display_users':
                                return in_array( $value, array( 'all', 'guest', 'logged_in' ), true ) ? $value : 'all';

                        case 'business_hours_enabled':
                        case 'sound_enabled':
                        case 'streaming_enabled':
                                return ( 'yes' === $value || '1' === (string) $value || true === $value ) ? 'yes' : 'no';

                        case 'business_hours_start':
                        case 'business_hours_end':
                                return preg_match( '/^\d{1,2}:\d{2}$/', trim( (string) $value ) ) ? trim( (string) $value ) : '';

                        case 'business_hours_days':
                                $days = array();
                                foreach ( preg_split( '/[\s,]+/', (string) $value ) as $d ) {
                                        $d = (int) $d;
                                        if ( $d >= 1 && $d <= 7 ) {
                                                $days[] = $d;
                                        }
                                }
                                $days = array_values( array_unique( $days ) );
                                return $days ? implode( ',', $days ) : '1,2,3,4,5';

                        case 'business_timezone':
                                $tz = trim( (string) $value );
                                if ( '' === $tz ) {
                                        return '';
                                }
                                try {
                                        new DateTimeZone( $tz );
                                        return $tz;
                                } catch ( Exception $e ) {
                                        return '';
                                }

                        case 'display_paths':
                        case 'offline_message':
                                return sanitize_textarea_field( (string) $value );

                        case 'font_family':
                                return in_array( $value, array( 'vazirmatn', 'inter', 'roboto', 'system', 'custom' ), true ) ? $value : 'vazirmatn';

                        case 'tone':
                                return in_array( $value, array( 'professional', 'friendly', 'formal', 'casual' ), true ) ? $value : 'professional';

                        case 'notify_platform':
                                return in_array( $value, array( 'bale', 'telegram' ), true ) ? $value : 'bale';

                        case 'voice_language':
                        case 'launcher_icon':
                                return sanitize_key( $value );

                        case 'primary_color':
                        case 'user_bubble_color':
                        case 'bot_bubble_color':
                                $c = sanitize_hex_color( $value );
                                return ( null === $c || '' === $c ) ? '' : $c;

                        case 'custom_endpoint':
                        case 'ai_webhook_url':
                        case 'font_url':
                        case 'avatar_url':
                        case 'launcher_icon_url':
                        case 'consent_link':
                                return esc_url_raw( trim( (string) $value ) );

                        case 'notify_email_to':
                                return sanitize_email( $value );

                        case 'ai_temperature':
                                $t = (float) $value;
                                return (string) max( 0, min( 1, $t ) );

                        case 'ai_fallback_msg':
                        case 'welcome_text':
                        case 'disclaimer':
                        case 'handoff_text':
                        case 'consent_text':
                        case 'proactive_text':
                        case 'ai_system_prompt_extra':
                        case 'products': // string container handled above; unreachable.
                                return wp_kses_post( (string) $value );

                        default:
                                // Text keys inside the business bucket are handled by sanitize_business().
                                return sanitize_text_field( (string) $value );
                }
        }

        /**
         * Sanitize list-type settings (products, knowledge_items, form_fields).
         *
         * @param string $key   Setting key.
         * @param array  $value Raw list.
         * @return array
         */
        protected static function sanitize_list( $key, $value ) {
                $out = array();
                switch ( $key ) {
                        case 'products':
                                $taken = array();
                                foreach ( $value as $p ) {
                                        if ( ! is_array( $p ) ) {
                                                continue;
                                        }
                                        $name = isset( $p['name'] ) ? sanitize_text_field( $p['name'] ) : '';
                                        if ( '' === $name ) {
                                                continue;
                                        }
                                        $id = self::make_unique_id( isset( $p['id'] ) ? $p['id'] : '', $name, $taken );
                                        if ( '' === $id ) {
                                                continue;
                                        }
                                        $item = array(
                                                'id'       => $id,
                                                'name'     => $name,
                                                'summary'  => isset( $p['summary'] ) ? wp_kses_post( $p['summary'] ) : '',
                                                'brochure' => isset( $p['brochure'] ) ? esc_url_raw( $p['brochure'] ) : '',
                                                'image'    => isset( $p['image'] ) ? esc_url_raw( $p['image'] ) : '',
                                        );
                                        // Flexible attributes: any key => sanitized text (no pharma-only schema).
                                        $attrs = array();
                                        if ( isset( $p['attributes'] ) && is_array( $p['attributes'] ) ) {
                                                foreach ( $p['attributes'] as $ak => $av ) {
                                                        $ak = sanitize_key( $ak );
                                                        if ( '' !== $ak && '' !== ( $av = sanitize_text_field( (string) $av ) ) ) {
                                                                $attrs[ $ak ] = $av;
                                                        }
                                                }
                                        }
                                        $item['attributes'] = $attrs;
                                        $out[] = $item;
                                }
                                break;

                        case 'knowledge_items':
                                foreach ( $value as $item ) {
                                        if ( ! is_array( $item ) ) {
                                                continue;
                                        }
                                        $title = isset( $item['title'] ) ? sanitize_text_field( $item['title'] ) : '';
                                        $body  = isset( $item['content'] ) ? wp_kses_post( (string) $item['content'] ) : '';
                                        if ( '' === $title && '' === $body ) {
                                                continue;
                                        }
                                        $type = isset( $item['type'] ) ? sanitize_key( $item['type'] ) : 'general';
                                        if ( ! in_array( $type, array( 'general', 'product', 'service', 'faq', 'policy', 'support', 'custom' ), true ) ) {
                                                $type = 'general';
                                        }
                                        $out[] = array(
                                                'id'      => isset( $item['id'] ) ? sanitize_key( $item['id'] ) : ( 'ki-' . uniqid() ),
                                                'type'    => $type,
                                                'title'   => $title,
                                                'content' => $body,
                                        );
                                }
                                break;

                        case 'form_fields':
                                $types = self::form_field_types();
                                foreach ( $value as $f ) {
                                        if ( ! is_array( $f ) || empty( $f['label'] ) ) {
                                                continue;
                                        }
                                        $type = isset( $f['type'] ) && in_array( $f['type'], $types, true ) ? $f['type'] : 'text';
                                        $row  = array(
                                                'key'         => isset( $f['key'] ) ? sanitize_key( $f['key'] ) : '',
                                                'label'       => sanitize_text_field( $f['label'] ),
                                                'type'        => $type,
                                                'required'    => ! empty( $f['required'] ),
                                                'options'     => array(),
                                                'placeholder' => isset( $f['placeholder'] ) ? sanitize_text_field( $f['placeholder'] ) : '',
                                        );
                                        if ( '' === $row['key'] ) {
                                                $row['key'] = 'f' . uniqid();
                                        }
                                        if ( in_array( $type, array( 'select', 'radio' ), true ) && ! empty( $f['options'] ) ) {
                                                $row['options'] = array_values( array_filter( array_map( 'sanitize_text_field', (array) $f['options'] ) ) );
                                        }
                                        $out[] = $row;
                                }
                                break;

                        case 'business':
                                return self::sanitize_business( $value );
                }
                return $out;
        }

        /**
         * Sanitize the structured business profile.
         *
         * @param array $value Raw business data.
         * @return array
         */
        public static function sanitize_business( $value ) {
                $out   = array();
                $plain = array( 'org_name', 'brand_name', 'category', 'industry', 'url', 'location', 'phone', 'email', 'support_phone', 'support_email', 'hours', 'language', 'assistant_name', 'assistant_role' );
                foreach ( $plain as $k ) {
                        $out[ $k ] = isset( $value[ $k ] ) ? sanitize_text_field( (string) $value[ $k ] ) : '';
                }
                $out['description']     = isset( $value['description'] ) ? wp_kses_post( (string) $value['description'] ) : '';
                $out['products']        = isset( $value['products'] ) ? wp_kses_post( (string) $value['products'] ) : '';
                $out['differentiators'] = isset( $value['differentiators'] ) ? wp_kses_post( (string) $value['differentiators'] ) : '';
                $out['tone'] = ( isset( $value['tone'] ) && in_array( $value['tone'], array( 'professional', 'friendly', 'formal', 'casual' ), true ) ) ? $value['tone'] : 'professional';
                // URLs & emails validated properly.
                foreach ( array( 'url' ) as $k ) {
                        if ( '' !== $out[ $k ] ) {
                                $out[ $k ] = esc_url_raw( $out[ $k ] );
                        }
                }
                foreach ( array( 'email', 'support_email' ) as $k ) {
                        if ( '' !== $out[ $k ] && ! is_email( $out[ $k ] ) ) {
                                $out[ $k ] = '';
                        }
                }
                return $out;
        }

        /**
         * Allowed dynamic form field types.
         *
         * @return string[]
         */
        public static function form_fields() {
                return self::sanitize_list( 'form_fields', (array) self::get( 'form_fields', array() ) );
        }

        public static function form_field_types() {
                return array( 'text', 'textarea', 'tel', 'email', 'number', 'select', 'checkbox', 'radio' );
        }

        /**
         * Stable unique ID builder tolerant of non-latin names (Persian slugs kept).
         *
         * @param string $raw_id Raw id input.
         * @param string $name   Display name fallback.
         * @param array  $taken  Already-taken ids (by ref).
         * @return string
         */
        public static function make_unique_id( $raw_id, $name, &$taken = array() ) {
                $raw_id = trim( (string) $raw_id );
                $name   = trim( (string) $name );
                $id     = sanitize_key( $raw_id );
                if ( '' === $id && '' !== $raw_id ) {
                        $id = sanitize_title( $raw_id );
                }
                if ( '' === $id && '' !== $name ) {
                        $id = sanitize_title( $name );
                }
                if ( '' === $id ) {
                        return '';
                }
                $base = $id;
                $n    = 2;
                while ( isset( $taken[ $id ] ) ) {
                        $id = $base . '-' . $n;
                        ++$n;
                }
                $taken[ $id ] = true;
                return $id;
        }

        /* ------------------------------------------------------------------ *
         * 4.x -> 5.0 migration (settings shape only; tables handled by Schema).
         * ------------------------------------------------------------------ */

        /**
         * Migrate a 4.x settings array into the 5.0 shape. Pure function
         * (unit-tested) - persistence is done by the caller.
         *
         * @param array $old 4.x settings (already read).
         * @param array $new 5.0 defaults.
         * @return array Migrated settings.
         */
        public static function migrate_v4( $old, $new = null ) {
                $new = null === $new ? self::defaults() : $new;

                $business = isset( $new['business'] ) ? $new['business'] : array();

                // Business identity from old flat keys.
                $map = array(
                        'company_name'    => 'org_name',
                        'support_phone'   => 'support_phone',
                );
                foreach ( $map as $old_key => $new_key ) {
                        if ( isset( $old[ $old_key ] ) && '' !== trim( (string) $old[ $old_key ] ) ) {
                                $business[ $new_key ] = sanitize_text_field( (string) $old[ $old_key ] );
                        }
                }
                $new['business'] = array_merge( self::defaults()['business'], $business );

                // Old product knowledge "company" bucket becomes a knowledge item.
                $old_knowledge = isset( $old['product_knowledge'] ) && is_array( $old['product_knowledge'] ) ? $old['product_knowledge'] : array();
                $company_id    = isset( $old['company_id'] ) ? (string) $old['company_id'] : 'company';
                $items         = isset( $new['knowledge_items'] ) ? $new['knowledge_items'] : array();
                if ( ! empty( $old_knowledge[ $company_id ] ) ) {
                        $items[] = array(
                                'id'      => 'ki-org-profile',
                                'type'    => 'general',
                                'title'   => isset( $new['business']['org_name'] ) && '' !== $new['business']['org_name'] ? $new['business']['org_name'] : __( 'About the organization', 'smart-support-chatbot' ),
                                'content' => wp_kses_post( (string) $old_knowledge[ $company_id ] ),
                        );
                }
                // Per-product knowledge moves into product summaries/attributes.
                $products = isset( $new['products'] ) ? $new['products'] : array();
                if ( isset( $old['products'] ) && is_array( $old['products'] ) ) {
                        $taken = array();
                        foreach ( $old['products'] as $p ) {
                                if ( ! is_array( $p ) || empty( $p['name'] ) ) {
                                        continue;
                                }
                                $pid   = isset( $p['id'] ) ? (string) $p['id'] : '';
                                $entry = array(
                                        'id'       => self::make_unique_id( $pid, $p['name'], $taken ),
                                        'name'     => sanitize_text_field( $p['name'] ),
                                        'summary'  => isset( $old_knowledge[ $pid ] ) ? wp_kses_post( (string) $old_knowledge[ $pid ] ) : ( isset( $p['summary'] ) ? wp_kses_post( (string) $p['summary'] ) : '' ),
                                        'brochure' => isset( $p['brochure'] ) ? esc_url_raw( $p['brochure'] ) : '',
                                        'image'    => isset( $p['image'] ) ? esc_url_raw( $p['image'] ) : '',
                                        'attributes' => array(),
                                );
                                if ( '' === $entry['id'] ) {
                                        continue;
                                }
                                $products[] = $entry;
                        }
                }
                $new['products']        = $products;
                $new['knowledge_items'] = $items;

                // Straight key remaps.
                $remap = array(
                        'gemini_api_key'  => 'gemini_api_key',
                        'openai_api_key'  => 'openai_api_key',
                        'claude_api_key'  => 'claude_api_key',
                        'custom_api_key'  => 'custom_api_key',
                        'ai_webhook_url'  => 'ai_webhook_url',
                        'ai_webhook_secret' => 'ai_webhook_secret',
                        'custom_endpoint' => 'custom_endpoint',
                        'custom_model'    => 'custom_model',
                        'ai_temperature'  => 'ai_temperature',
                        'ai_max_tokens'   => 'ai_max_tokens',
                        'ai_history_limit' => 'ai_history_limit',
                        'ai_cache_enabled' => 'ai_cache_enabled',
                        'theme_mode'      => 'theme_mode',
                        'position'        => 'position',
                        'font_family'     => 'font_family',
                        'font_size'       => 'font_size',
                        'window_width'    => 'window_width',
                        'window_radius'   => 'window_radius',
                        'bubble_radius'   => 'bubble_radius',
                        'user_bubble_color' => 'user_bubble_color',
                        'bot_bubble_color' => 'bot_bubble_color',
                        'consent_text'    => 'consent_text',
                        'consent_link'    => 'consent_link',
                        'notify_platform' => 'notify_platform',
                        'notify_token'    => 'notify_token',
                        'notify_chat_id'  => 'notify_chat_id',
                        'chatlog_retention_days' => 'chatlog_retention_days',
                        'submissions_retention_days' => 'submissions_retention_days',
                        'chat_rate_limit' => 'ai_rate_limit',
                        'session_rate_limit' => 'session_rate_limit',
                        'rate_limit_mode' => 'rate_limit_mode',
                        'qa_mode'         => 'qa_mode',
                );
                foreach ( $remap as $new_key => $old_key ) {
                        if ( isset( $old[ $old_key ] ) && ( ! isset( $new[ $new_key ] ) || '' === $new[ $new_key ] || 0 === $new[ $new_key ] || array() === $new[ $new_key ] ) ) {
                                $new[ $new_key ] = $old[ $old_key ];
                        }
                }

                // Numeric settings the admin explicitly tuned in 4.x always win over
                // v5 defaults (rate limits, retention).
                foreach ( array( 'chat_rate_limit' => 'ai_rate_limit', 'chatlog_retention_days' => 'chatlog_retention_days', 'submissions_retention_days' => 'submissions_retention_days' ) as $new_key => $old_key ) {
                        if ( isset( $old[ $old_key ] ) && (int) $old[ $old_key ] > 0 ) {
                                $new[ $new_key ] = (int) $old[ $old_key ];
                        }
                }

                // Provider rename: 4.x "fallback" == 5.x "none".
                if ( isset( $old['ai_provider'] ) && 'fallback' === $old['ai_provider'] ) {
                        $new['ai_provider'] = 'none';
                } elseif ( isset( $old['ai_provider'] ) && in_array( $old['ai_provider'], array( 'openai', 'gemini', 'claude', 'custom', 'webhook' ), true ) ) {
                        $new['ai_provider'] = $old['ai_provider'];
                }

                // Old custom system prompt is kept as an add-on block (core builder owns identity).
                if ( ! empty( $old['ai_system_prompt'] ) ) {
                        $new['ai_system_prompt_extra'] = wp_kses_post( (string) $old['ai_system_prompt'] );
                }
                if ( ! empty( $old['ai_fallback_msg'] ) ) {
                        $new['ai_fallback_msg'] = wp_kses_post( (string) $old['ai_fallback_msg'] );
                }

                // Appearance texts.
                $texts = array(
                        'welcome_title' => 'welcome_title',
                        'welcome_text'  => 'welcome_text',
                        'disclaimer'    => 'disclaimer',
                        'header_title'  => 'assistant_display_name',
                );
                foreach ( $texts as $old_key => $new_key ) {
                        if ( isset( $old[ $old_key ] ) && '' !== trim( (string) $old[ $old_key ] ) && empty( $new[ $new_key ] ) ) {
                                $new[ $new_key ] = wp_kses_post( (string) $old[ $old_key ] );
                        }
                }
                if ( ! empty( $old['button_icon_url'] ) ) {
                        $new['launcher_icon_url'] = esc_url_raw( $old['button_icon_url'] );
                }
                if ( isset( $old['button_size'] ) ) {
                        $new['launcher_size'] = (int) $old['button_size'];
                }

                // Module-scoped settings carried over (module activation handled separately).
                $mod = array(
                        'voice_input'    => 'voice_enabled',
                        'voice_output'   => 'voice_enabled',
                        'chatlog_enabled' => 'chatlog_enabled',
                        'form_fields'    => 'form_fields',
                        'csat_enabled'   => 'csat_enabled',
                        'handoff_text'   => 'handoff_text',
                        'proactive_delay' => 'proactive_delay',
                        'proactive_text' => 'proactive_text',
                        'notify_email_enabled' => 'email_enabled',
                        'notify_email_to' => 'email_to',
                        'kb_max_chunks'  => 'kb_max_chunks',
                        'consent_enabled' => 'consent_enabled',
                );
                foreach ( $mod as $new_key => $old_key ) {
                        if ( isset( $old[ $old_key ] ) && ( ! isset( $new[ $new_key ] ) || '' === $new[ $new_key ] || array() === $new[ $new_key ] ) ) {
                                $new[ $new_key ] = $old[ $old_key ];
                        }
                }

                // Old 'enabled' -> published state is handled by SSC_Setup, not copied here.

                return $new;
        }
}
