<?php
/**
 * Leads & consultation module: request forms, dynamic fields, validation,
 * consent enforcement, storage and CSV export.
 *
 * Server-side submission handling: every field is validated against the
 * CONFIGURED form definition (never trusted from the client), submission
 * types are whitelisted, and the anti-bot honeypot is enforced here.
 *
 * @package SmartSupportChatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

/**
 * Leads module.
 */
class SSC_Module_Leads extends SSC_Module {

        /**
         * Module id.
         *
         * @return string
         */
        public function id() {
                return 'leads';
        }

        /**
         * Title.
         *
         * @return string
         */
        public function title() {
                return __( 'Consultation & Lead Collection', 'smart-support-chatbot' );
        }

        /**
         * Description.
         *
         * @return string
         */
        public function description() {
                return __( 'Structured request forms inside the chat with custom fields, consent capture and a request inbox.', 'smart-support-chatbot' );
        }

        /**
         * Benefit.
         *
         * @return string
         */
        public function benefit() {
                return __( 'Turn conversations into actionable requests with clean, validated contact data.', 'smart-support-chatbot' );
        }

        /**
         * Category.
         *
         * @return string
         */
        public function category() {
                return 'business';
        }

        /**
         * Icon.
         *
         * @return string
         */
        public function icon() {
                return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>';
        }

        /**
         * Needs configuration (form fields are optional but the module is useful
         * out of the box; returns false by design).
         *
         * @return bool
         */
        public function needs_config() {
                return false;
        }

        /**
         * Admin hooks: requests inbox page.
         */
        public function register_admin() {
                add_action( 'admin_menu', array( $this, 'menu' ), 40 );
                add_action( 'admin_post_ssc_export_submissions', array( $this, 'export_csv' ) );
        }

        /**
         * Requests inbox menu entry (only rendered while the module is active).
         */
        public function menu() {
                if ( ! SSC_Modules::is_active( 'leads' ) ) {
                        return;
                }
                add_submenu_page(
                        'ssc-dashboard',
                        __( 'Requests', 'smart-support-chatbot' ),
                        __( 'Requests', 'smart-support-chatbot' ),
                        'manage_options',
                        'ssc-requests',
                        array( $this, 'render_page' )
                );
        }

        /**
         * Render the requests inbox.
         */
        public function render_page() {
                if ( ! current_user_can( 'manage_options' ) ) {
                        wp_die( esc_html__( 'Insufficient permissions.', 'smart-support-chatbot' ) );
                }
                // Request handling (PRG pattern - refresh never re-posts).
                $this->handle_row_actions();

                $filters = array(
                        'type'   => isset( $_GET['type'] ) ? sanitize_key( wp_unslash( $_GET['type'] ) ) : '',
                        'status' => isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '',
                        'search' => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '',
                        'page'   => isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1,
                );
                // The inbox shows non-pharma requests when the pharma module owns its cases.
                $filters['exclude_type'] = SSC_Modules::is_active( 'pharma' ) ? 'pharma_adr' : '';
                $result = $this->query( $filters );

                require SSC_CHATBOT_DIR . 'includes/admin/views/page-requests.php';
        }

        /**
         * Row actions (status change / delete) with nonces + redirect preserving
         * current filters (4.x bug fixed: filters are no longer lost).
         */
        protected function handle_row_actions() {
                if ( ! isset( $_GET['ssc_req_action'], $_GET['id'], $_GET['_wpnonce'] ) ) {
                        return;
                }
                $action = sanitize_key( wp_unslash( $_GET['ssc_req_action'] ) );
                $id     = (int) $_GET['id'];
                if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'ssc_req_' . $id ) ) {
                        return;
                }
                if ( 'delete' === $action ) {
                        SSC_Schema::delete_submission( $id );
                } elseif ( 'status' === $action && isset( $_GET['status_to'] ) ) {
                        SSC_Schema::update_status( $id, sanitize_key( wp_unslash( $_GET['status_to'] ) ) );
                }
                // PRG: preserve every filter.
                $args = array( 'page' => 'ssc-requests' );
                foreach ( array( 'type', 'status', 's', 'paged' ) as $keep ) {
                        if ( isset( $_GET[ $keep ] ) && '' !== (string) $_GET[ $keep ] ) {
                                $args[ $keep ] = rawurlencode( sanitize_text_field( wp_unslash( $_GET[ $keep ] ) ) );
                        }
                }
                wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
                exit;
        }

        /**
         * Query the inbox (excludes a type when requested).
         *
         * @param array $filters Filters.
         * @return array
         */
        public function query( $filters ) {
                return SSC_Schema::get_submissions( wp_parse_args( $filters, array( 'per_page' => 20 ) ) );
        }

        /**
         * Mark: unused; see query().
         *
         * @param array $types Types.
         * @return array
         */
        public function strip_excluded_type( $types ) {
                return $types;
        }

        /* ------------------------------------------------------------------ *
         * Submission handling (called by REST/AJAX transports).
         * ------------------------------------------------------------------ */

        /**
         * Handle a consultation submission.
         *
         * @param array  $params Raw input.
         * @param string $ip     Client ip.
         * @return array|WP_Error
         */
        public function handle_submission( $params, $ip = '' ) {
                $params = is_array( $params ) ? $params : array();

                // Honeypot: bots filling the hidden field get a fake success.
                if ( ! empty( $params['ssc_hp'] ) ) {
                        return array( 'ok' => true, 'fake' => true );
                }

                $type = isset( $params['type'] ) ? sanitize_key( $params['type'] ) : 'consult';
                if ( 'pharma_adr' === $type ) {
                        // ADR reports belong to the pharma module (when active).
                        if ( SSC_Modules::is_active( 'pharma' ) ) {
                                $pharma = SSC_Modules::get( 'pharma' );
                                return $pharma->handle_submission( $params, $ip );
                        }
                        return new WP_Error( 'ssc_module_off', __( 'ADR reporting is disabled on this site.', 'smart-support-chatbot' ), array( 'status' => 404 ) );
                }
                if ( 'consult' !== $type ) {
                        return new WP_Error( 'ssc_bad_type', __( 'Invalid submission type.', 'smart-support-chatbot' ), array( 'status' => 400 ) );
                }
                // Module isolation: consultation forms require the leads module.
                if ( ! SSC_Modules::is_active( 'leads' ) ) {
                        return new WP_Error( 'ssc_module_off', __( 'Form submission is disabled on this site.', 'smart-support-chatbot' ), array( 'status' => 404 ) );
                }

                $name  = isset( $params['name'] ) ? SSC_Input::text( $params['name'], 81 ) : '';
                $phone = isset( $params['phone'] ) ? SSC_Input::phone( $params['phone'] ) : '';
                $desc  = isset( $params['description'] ) ? SSC_Input::text( $params['description'], 10001, true ) : '';

                // Validation.
                $errors = array();
                if ( mb_strlen( $name ) < 2 || mb_strlen( $name ) > 80 ) {
                        $errors[] = __( 'Please enter your name (2-80 characters).', 'smart-support-chatbot' );
                }
                /**
                 * Filter the phone validation pattern.
                 *
                 * @param string $pattern Regex.
                 */
                $pattern = (string) apply_filters( 'ssc_phone_pattern', '/^\+?\d[\d\s\-]{6,18}\d$/' );
                if ( ! preg_match( $pattern, $phone ) ) {
                        $errors[] = __( 'Please enter a valid phone number.', 'smart-support-chatbot' );
                }
                if ( mb_strlen( $desc ) < 10 || mb_strlen( $desc ) > 1000 ) {
                        $errors[] = __( 'Please describe your request (10-1000 characters).', 'smart-support-chatbot' );
                }

                // Dynamic custom fields validated against the CONFIGURED definitions.
                $extra = $this->validate_extra_fields( isset( $params['extra'] ) ? $params['extra'] : '' );
                if ( is_wp_error( $extra ) ) {
                        $errors[] = $extra->get_error_message();
                }

                // Consent enforcement (when enabled, submission is refused without it).
                $consent_meta = '';
                if ( 'yes' === SSC_Settings::get( 'consent_enabled', 'no' ) ) {
                        $consent = SSC_Input::consent( $params['consent'] ?? false );
                        if ( ! $consent ) {
                                $errors[] = __( 'Your consent is required to submit this form.', 'smart-support-chatbot' );
                        } else {
                                $consent_meta = wp_json_encode(
                                        array(
                                                '_consent'        => 1,
                                                '_consent_hash'   => hash( 'sha256', SSC_Input::consent_text( false ) ),
                                                '_consent_policy' => (string) SSC_Settings::get( 'consent_link', '' ),
                                                '_consent_at'     => current_time( 'mysql' ),
                                        )
                                );
                        }
                }

                if ( $errors ) {
                        return new WP_Error( 'ssc_validation', implode( ' ', $errors ), array( 'status' => 400 ) );
                }

                $extra_json = $extra;
                if ( '' !== $consent_meta ) {
                        $decoded = json_decode( $extra, true );
                        $decoded = is_array( $decoded ) ? $decoded : array();
                        $decoded['_consent'] = json_decode( $consent_meta, true );
                        $extra_json = wp_json_encode( $decoded );
                }

                $id = SSC_Schema::insert_submission(
                        array(
                                'type'         => 'consult',
                                'name'         => $name,
                                'phone'        => $phone,
                                'description'  => $desc,
                                'extra_fields' => $extra_json,
                                'ip'           => $ip,
                        )
                );
                if ( ! $id ) {
                        return new WP_Error( 'ssc_storage', __( 'The request could not be stored. Please try again.', 'smart-support-chatbot' ), array( 'status' => 500 ) );
                }

                // Notify (module-gated).
                if ( SSC_Modules::is_active( 'notifications' ) ) {
                        $notifications = SSC_Modules::get( 'notifications' );
                        $notifications->dispatch_for_submission( $id, 'consult' );
                }
                return array( 'ok' => true );
        }

        /**
         * Validate dynamic extra fields against configured definitions.
         *
         * @param mixed $raw JSON string or array.
         * @return string|WP_Error JSON payload or error.
         */
        public function validate_extra_fields( $raw ) {
                $definitions = SSC_Settings::form_fields();
                if ( empty( $definitions ) ) {
                        return '';
                }
                $decoded = is_array( $raw ) ? $raw : json_decode( (string) $raw, true );
                $decoded = is_array( $decoded ) ? $decoded : array();

                $out = array();
                foreach ( $definitions as $field ) {
                        $key   = $field['key'];
                        $value = isset( $decoded[ $key ] ) ? $decoded[ $key ] : '';
                        if ( ! is_scalar( $value ) ) { $value = ''; }
                        switch ( $field['type'] ) {
                                case 'email':
                                        $value = sanitize_email( $value );
                                        break;
                                case 'number':
                                        $value = is_numeric( $value ) ? (float) $value : '';
                                        break;
                                case 'tel':
                                case 'text':
                                default:
                                        $value = sanitize_text_field( (string) $value );
                                        break;
                                case 'textarea':
                                        $value = sanitize_textarea_field( (string) $value );
                                        break;
                                case 'checkbox':
                                        $value = SSC_Input::consent( $value ) ? 'yes' : '';
                                        break;
                                case 'select':
                                case 'radio':
                                        $value = in_array( (string) $value, array_map( 'strval', $field['options'] ), true ) ? (string) $value : '';
                                        break;
                        }
                        if ( $field['required'] && '' === (string) $value ) {
                                /* translators: %s: field label. */
                                return new WP_Error( 'ssc_field', sprintf( __( 'The field "%s" is required.', 'smart-support-chatbot' ), $field['label'] ), array( 'status' => 400 ) );
                        }
                        if ( 'email' === $field['type'] && '' !== (string) $value && ! is_email( $value ) ) {
                                /* translators: %s: field label. */
                                return new WP_Error( 'ssc_field', sprintf( __( 'The field "%s" must be a valid email.', 'smart-support-chatbot' ), $field['label'] ), array( 'status' => 400 ) );
                        }
                        if ( '' !== (string) $value ) {
                                $out[ $key ] = $value;
                        }
                }
                return wp_json_encode( $out );
        }

        /* ------------------------------------------------------------------ *
         * Export.
         * ------------------------------------------------------------------ */

        /**
         * CSV export (admin_post).
         */
        public function export_csv() {
                if ( ! current_user_can( 'manage_options' ) ) {
                        wp_die( esc_html__( 'Insufficient permissions.', 'smart-support-chatbot' ) );
                }
                if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'ssc_export' ) ) {
                        wp_die( esc_html__( 'Invalid nonce.', 'smart-support-chatbot' ) );
                }

                $type   = isset( $_GET['type'] ) ? sanitize_key( wp_unslash( $_GET['type'] ) ) : '';
                $status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';

                nocache_headers();
                header( 'Content-Type: text/csv; charset=utf-8' );
                header( 'Content-Disposition: attachment; filename=ssc-requests-' . gmdate( 'Ymd-Hi' ) . '.csv' );

                $out = fopen( 'php://output', 'w' );
                fwrite( $out, "\xEF\xBB\xBF" ); // UTF-8 BOM for Excel.
                SSC_Input::write_csv( $out, array( 'id', 'type', 'name', 'phone', 'product', 'description', 'status', 'created_at' ) );

                $page = 1;
                do {
                        $result = SSC_Schema::get_submissions(
                                array(
                                        'type'     => $type,
                                        'status'   => $status,
                                        'per_page' => 500,
                                        'page'     => $page,
                                )
                        );
                        foreach ( $result['items'] as $row ) {
                                // write_csv() already runs every cell through csv_cell().
                                SSC_Input::write_csv(
                                        $out,
                                        array( $row['id'], $row['type'], $row['name'], $row['phone'], $row['product'], $row['description'], $row['status'], $row['created_at'] )
                                );
                        }
                        ++$page;
                } while ( $page <= $result['total_pages'] );
                fclose( $out );
                exit;
        }
}
