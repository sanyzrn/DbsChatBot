<?php
/**
 * Database schema, migrations, and persistence primitives.
 *
 * Data preservation contract:
 * - Table names and columns from 4.x are unchanged (plus one new audit table),
 *   so every existing installation upgrades in place without data loss.
 * - Legacy `nafas_*` namespace migration from earlier releases is retained.
 * - Submission `type` values are canonicalized ('pharma_adr', 'consult', ...)
 *   while keeping the legacy Persian display strings readable via type_label().
 *
 * @package SmartSupportChatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

/**
 * Schema / DB class.
 */
class SSC_Schema {

        const TABLE          = 'ssc_chatbot_submissions';
        const CHATLOG_TABLE = 'ssc_chatbot_chatlog';
        const QA_TABLE      = 'ssc_chatbot_qa';
        const KB_TABLE      = 'ssc_chatbot_kb';
        const STATS_TABLE   = 'ssc_chatbot_stats';
        const AUDIT_TABLE   = 'ssc_chatbot_audit';
        const DB_VERSION    = '10';
        const DB_VERSION_OPTION = 'ssc_chatbot_db_version';

        /* ------------------------------------------------------------------ *
         * Table name helpers.
         * ------------------------------------------------------------------ */

        /**
         * Submissions table name.
         *
         * @return string
         */
        public static function table_name() {
                global $wpdb;
                return $wpdb->prefix . self::TABLE;
        }

        /**
         * Chatlog table name.
         *
         * @return string
         */
        public static function chatlog_table_name() {
                global $wpdb;
                return $wpdb->prefix . self::CHATLOG_TABLE;
        }

        /**
         * QA bank table name.
         *
         * @return string
         */
        public static function qa_table_name() {
                global $wpdb;
                return $wpdb->prefix . self::QA_TABLE;
        }

        /**
         * Knowledge base table name.
         *
         * @return string
         */
        public static function kb_table_name() {
                global $wpdb;
                return $wpdb->prefix . self::KB_TABLE;
        }

        /**
         * Stats table name.
         *
         * @return string
         */
        public static function stats_table_name() {
                global $wpdb;
                return $wpdb->prefix . self::STATS_TABLE;
        }

        /**
         * Audit trail table name.
         *
         * @return string
         */
        public static function audit_table_name() {
                global $wpdb;
                return $wpdb->prefix . self::AUDIT_TABLE;
        }

        /* ------------------------------------------------------------------ *
         * Install / upgrade.
         * ------------------------------------------------------------------ */

        /**
         * Create/refresh all tables (dbDelta is idempotent).
         *
         * @param bool $stamp_version Whether to write DB_VERSION after schema creation.
         *                            Pass false from maybe_upgrade() so the version is
         *                            only stamped after ALL migrations succeed.
         */
        public static function install( $stamp_version = true ) {
                global $wpdb;
                if ( ! function_exists( 'dbDelta' ) ) {
                        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
                }

                $charset = $wpdb->get_charset_collate();

                $submissions = self::table_name();
                $chatlog     = self::chatlog_table_name();
                $qa          = self::qa_table_name();
                $kb          = self::kb_table_name();
                $stats       = self::stats_table_name();
                $audit       = self::audit_table_name();
                $notifications = SSC_Notification_Queue::table();

                // phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- static DDL via dbDelta.
                dbDelta( "CREATE TABLE {$submissions} (
                        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                        type VARCHAR(100) NOT NULL DEFAULT '',
                        name VARCHAR(191) NOT NULL DEFAULT '',
                        phone VARCHAR(50) NOT NULL DEFAULT '',
                        product VARCHAR(191) NULL,
                        description TEXT NULL,
                        severity VARCHAR(50) NULL,
                        outcome VARCHAR(100) NULL,
                        batch_number VARCHAR(100) NULL,
                        concomitant_drugs TEXT NULL,
                        reporter_type VARCHAR(50) NULL,
                        extra_fields LONGTEXT NULL,
                        status VARCHAR(20) NOT NULL DEFAULT 'new',
                        ip VARCHAR(100) NULL,
                        created_at DATETIME NULL,
                        PRIMARY KEY  (id),
                        KEY type (type),
                        KEY status (status),
                        KEY created_at (created_at)
                ) {$charset};" );

                dbDelta( "CREATE TABLE {$chatlog} (
                        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                        product VARCHAR(191) NULL,
                        question TEXT NULL,
                        answer TEXT NULL,
                        source VARCHAR(20) NOT NULL DEFAULT 'ai',
                        in_bank TINYINT(1) NOT NULL DEFAULT 0,
                        rating TINYINT(1) NOT NULL DEFAULT 0,
                        ip VARCHAR(100) NULL,
                        created_at DATETIME NULL,
                        PRIMARY KEY  (id),
                        KEY source (source),
                        KEY created_at (created_at)
                ) {$charset};" );

                dbDelta( "CREATE TABLE {$qa} (
                        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                        product_id VARCHAR(100) NOT NULL DEFAULT 'general',
                        question TEXT NOT NULL,
                        keywords TEXT NULL,
                        answer LONGTEXT NOT NULL,
                        usage_count INT UNSIGNED NOT NULL DEFAULT 0,
                        created_at DATETIME NULL,
                        PRIMARY KEY  (id),
                        KEY product_id (product_id),
                        FULLTEXT KEY ft_qa (question, keywords)
                ) {$charset};" );

                dbDelta( "CREATE TABLE {$kb} (
                        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                        doc_id VARCHAR(40) NOT NULL DEFAULT '',
                        product_id VARCHAR(100) NOT NULL DEFAULT 'general',
                        source_title VARCHAR(191) NOT NULL DEFAULT '',
                        chunk LONGTEXT NOT NULL,
                        search_text LONGTEXT NULL,
                        created_at DATETIME NULL,
                        PRIMARY KEY  (id),
                        KEY product_id (product_id),
                        KEY doc_id (doc_id),
                        FULLTEXT KEY ft_kb (search_text)
                ) {$charset};" );

                dbDelta( "CREATE TABLE {$stats} (
                        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                        stat_date DATE NOT NULL,
                        metric VARCHAR(80) NOT NULL DEFAULT '',
                        cnt BIGINT(20) NOT NULL DEFAULT 0,
                        PRIMARY KEY  (id),
                        UNIQUE KEY date_metric (stat_date, metric),
                        KEY metric (metric),
                        KEY stat_date (stat_date)
                ) {$charset};" );

                dbDelta( "CREATE TABLE {$audit} (
                        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                        submission_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
                        action VARCHAR(50) NOT NULL DEFAULT '',
                        from_status VARCHAR(20) NOT NULL DEFAULT '',
                        to_status VARCHAR(20) NOT NULL DEFAULT '',
                        actor VARCHAR(191) NOT NULL DEFAULT '',
                        note TEXT NULL,
                        created_at DATETIME NULL,
                        PRIMARY KEY  (id),
                        KEY submission_id (submission_id),
                        KEY created_at (created_at)
                ) {$charset};" );
                dbDelta( "CREATE TABLE {$notifications} (
                        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                        submission_id BIGINT(20) UNSIGNED NOT NULL,
                        channel VARCHAR(20) NOT NULL,
                        status VARCHAR(20) NOT NULL DEFAULT 'pending',
                        attempts INT UNSIGNED NOT NULL DEFAULT 0,
                        next_at BIGINT(20) NOT NULL DEFAULT 0,
                        locked_until BIGINT(20) NOT NULL DEFAULT 0,
                        lease VARCHAR(64) NOT NULL DEFAULT '',
                        last_error VARCHAR(100) NOT NULL DEFAULT '',
                        updated_at BIGINT(20) NOT NULL DEFAULT 0,
                        PRIMARY KEY  (id),
                        UNIQUE KEY delivery (submission_id, channel),
                        KEY due_jobs (status, next_at),
                        KEY expired_leases (status, locked_until)
                ) {$charset};" );
                SSC_Notification_Queue::migrate_legacy();
                // phpcs:enable

                if ( $stamp_version ) {
                        update_option( self::DB_VERSION_OPTION, self::DB_VERSION, false );
                }
        }

        /**
         * Run once-per-version upgrades. Guarded by a short transient lock so
         * concurrent requests during an upgrade cannot interleave.
         *
         * Version is stamped ONLY after every migration step returns, so a
         * mid-flight failure is retried on the next request instead of being
         * silently marked complete.
         */
        public static function maybe_upgrade() {
                $installed = get_option( self::DB_VERSION_OPTION, '0' );
                if ( self::DB_VERSION === $installed ) {
                        return;
                }
                $lock = get_transient( 'ssc_chatbot_upgrade_lock' );
                if ( '1' === $lock ) {
                        return;
                }
                set_transient( 'ssc_chatbot_upgrade_lock', '1', 60 );

                try {
                        self::migrate_legacy_namespace();
                        self::install( false );
                        self::migrate_settings_v5();
                        self::migrate_submission_types();
                        self::migrate_qa_from_options();
                        self::migrate_stats_from_options();
                        // Setup state safety net for IN-PLACE updates (activation hooks do
                        // not re-run): a legacy live chatbot must stay live.
                        SSC_Setup::initialize_state();
                        update_option( self::DB_VERSION_OPTION, self::DB_VERSION, false );
                } finally {
                        delete_transient( 'ssc_chatbot_upgrade_lock' );
                }
        }

        /**
         * Very old installs: rename nafas_* tables/options into the ssc_ namespace.
         */
        public static function migrate_legacy_namespace() {
                global $wpdb;
                $done = get_option( 'ssc_chatbot_ns_migrated', false );
                if ( $done ) {
                        return;
                }

                $tables = array(
                        $wpdb->prefix . 'nafas_chatbot_submissions' => self::table_name(),
                        $wpdb->prefix . 'nafas_chatbot_chatlog'     => self::chatlog_table_name(),
                        $wpdb->prefix . 'nafas_chatbot_qa'          => self::qa_table_name(),
                        $wpdb->prefix . 'nafas_chatbot_kb'          => self::kb_table_name(),
                        $wpdb->prefix . 'nafas_chatbot_stats'       => self::stats_table_name(),
                );
                foreach ( $tables as $old => $new ) {
                        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- one-time rename guarded by flag.
                        $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $old ) );
                        if ( $exists === $old ) {
                                // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- one-time migration.
                                $wpdb->query( "RENAME TABLE {$old} TO {$new}" );
                        }
                }

                $options = array(
                        'nafas_chatbot_settings'       => SSC_Settings::OPTION_KEY,
                        'nafas_chatbot_db_version'     => self::DB_VERSION_OPTION,
                        'nafas_chatbot_qa_migrated'    => 'ssc_chatbot_qa_migrated',
                        'nafas_chatbot_stats_migrated' => 'ssc_chatbot_stats_migrated',
                        'nafas_chatbot_chat_stats'     => 'ssc_chatbot_chat_stats',
                        'nafas_chatbot_csat'           => 'ssc_chatbot_csat',
                );
                foreach ( $options as $old_opt => $new_opt ) {
                        $old_value = get_option( $old_opt, null );
                        if ( null !== $old_value && false === get_option( $new_opt, false ) ) {
                                update_option( $new_opt, $old_value, false );
                        }
                        delete_option( $old_opt );
                }
                wp_clear_scheduled_hook( 'nafas_chatbot_daily_cleanup' );
                update_option( 'ssc_chatbot_ns_migrated', 1, false );
        }

        /**
         * Upgrade 4.x settings shape to 5.0 (once).
         */
        public static function migrate_settings_v5() {
                $saved = get_option( SSC_Settings::OPTION_KEY, array() );
                if ( ! is_array( $saved ) || empty( $saved ) ) {
                        return;
                }
                $is_legacy = isset( $saved['company_id'] ) || isset( $saved['show_company'] ) || isset( $saved['ai_system_prompt'] ) || isset( $saved['product_knowledge'] );
                if ( ! $is_legacy ) {
                        return;
                }
                $migrated = SSC_Settings::migrate_v4( $saved );
                // Secrets must be stored encrypted in the migrated shape too.
                // encrypt() returns false when unavailable — leave the existing
                // stored value rather than writing a broken/empty key.
                foreach ( SSC_Settings::secret_fields() as $secret_key ) {
                        if ( isset( $migrated[ $secret_key ] ) && '' !== (string) $migrated[ $secret_key ] ) {
                                $decrypted = SSC_Settings::decrypt( (string) $migrated[ $secret_key ] );
                                if ( '' !== $decrypted && 0 !== strpos( $decrypted, 'enc::' ) ) {
                                        $reencrypted = SSC_Settings::encrypt( $decrypted );
                                        if ( false !== $reencrypted && '' !== $reencrypted ) {
                                                $migrated[ $secret_key ] = $reencrypted;
                                        }
                                }
                        }
                }
                // Preserve every unknown legacy key (never destroy data).
                foreach ( $saved as $k => $v ) {
                        if ( ! array_key_exists( $k, $migrated ) ) {
                                $migrated[ $k ] = $v;
                        }
                }
                update_option( SSC_Settings::OPTION_KEY, $migrated, false );
        }

        /**
         * Canonicalize submission type values ('pharma_adr', 'consult', ...).
         */
        public static function migrate_submission_types() {
                global $wpdb;
                $table = self::table_name();
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- one-time canonicalization.
                $wpdb->query(
                        $wpdb->prepare(
                                "UPDATE {$table} SET type = %s WHERE type = %s",
                                'pharma_adr',
                                'گزارش عوارض دارویی'
                        )
                );
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- one-time canonicalization.
                $wpdb->query(
                        $wpdb->prepare(
                                "UPDATE {$table} SET type = %s WHERE type = %s",
                                'consult',
                                'درخواست مشاوره'
                        )
                );
        }

        /**
         * Allowed submission types (server-enforced whitelist).
         *
         * The pharma module registers 'pharma_adr' itself via the filter below —
         * it is deliberately absent here so the type is unusable while the
         * module is inactive (server-side module enforcement).
         *
         * @return string[] type => label.
         */
        public static function submission_types() {
                $types = array(
                        'consult'    => __( 'Consultation request', 'smart-support-chatbot' ),
                );
                /**
                 * Modules may register additional submission types.
                 *
                 * @param array $types type => label.
                 */
                return apply_filters( 'ssc_submission_types', $types );
        }

        /**
         * Human label for a stored type (legacy values included).
         *
         * @param string $type Stored type.
         * @return string
         */
        public static function type_label( $type ) {
                $types = self::submission_types();
                if ( isset( $types[ $type ] ) ) {
                        return $types[ $type ];
                }
                // Legacy display strings from 4.x.
                $legacy = array(
                        'گزارش عوارض دارویی' => __( 'Adverse drug reaction report', 'smart-support-chatbot' ),
                        'درخواست مشاوره'     => __( 'Consultation request', 'smart-support-chatbot' ),
                );
                if ( isset( $legacy[ $type ] ) ) {
                        return $legacy[ $type ];
                }
                return ( '' === $type ) ? __( 'General', 'smart-support-chatbot' ) : $type;
        }

        /* ------------------------------------------------------------------ *
         * Submissions.
         * ------------------------------------------------------------------ */

        /**
         * Insert a submission row (type whitelisted server-side).
         *
         * @param array $data Column values.
         * @return int Inserted id (0 on failure).
         */
        public static function insert_submission( $data ) {
                global $wpdb;
                $allowed = self::submission_types();
                $type    = isset( $data['type'] ) ? (string) $data['type'] : '';
                if ( ! isset( $allowed[ $type ] ) ) {
                        return 0;
                }
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- custom table insert.
                $wpdb->insert(
                        self::table_name(),
                        array(
                                'type'              => $type,
                                'name'              => isset( $data['name'] ) ? (string) $data['name'] : '',
                                'phone'             => isset( $data['phone'] ) ? (string) $data['phone'] : '',
                                'product'           => isset( $data['product'] ) ? (string) $data['product'] : '',
                                'description'       => isset( $data['description'] ) ? (string) $data['description'] : '',
                                'severity'          => isset( $data['severity'] ) ? (string) $data['severity'] : '',
                                'outcome'           => isset( $data['outcome'] ) ? (string) $data['outcome'] : '',
                                'batch_number'      => isset( $data['batch_number'] ) ? (string) $data['batch_number'] : '',
                                'concomitant_drugs' => isset( $data['concomitant_drugs'] ) ? (string) $data['concomitant_drugs'] : '',
                                'reporter_type'     => isset( $data['reporter_type'] ) ? (string) $data['reporter_type'] : '',
                                'extra_fields'      => isset( $data['extra_fields'] ) ? (string) $data['extra_fields'] : '',
                                'status'            => 'new',
                                'ip'                => isset( $data['ip'] ) ? (string) $data['ip'] : '',
                                'created_at'        => current_time( 'mysql' ),
                        ),
                        array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
                );
                return (int) $wpdb->insert_id;
        }

        /**
         * Query submissions with filters + pagination.
         *
         * @param array $args Filters (type, status, search, date_from, date_to, per_page, page).
         * @return array items/total/total_pages/page.
         */
        public static function get_submissions( $args = array() ) {
                global $wpdb;
                $table = self::table_name();
                $args  = wp_parse_args(
                        $args,
                        array(
                                'type'      => '',
                                'exclude_type' => '',
                                'status'    => '',
                                'search'    => '',
                                'date_from' => '',
                                'date_to'   => '',
                                'per_page'  => 20,
                                'page'      => 1,
                        )
                );

                $where  = array( '1=1' );
                $params = array();
                if ( '' !== $args['exclude_type'] ) {
                        $where[] = 'type <> %s';
                        $params[] = $args['exclude_type'];
                }
                if ( '' !== $args['type'] ) {
                        $where[]  = 'type = %s';
                        $params[] = $args['type'];
                }
                if ( '' !== $args['status'] ) {
                        $where[]  = 'status = %s';
                        $params[] = $args['status'];
                }
                if ( '' !== $args['search'] ) {
                        $where[]  = '(name LIKE %s OR description LIKE %s)';
                        $like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
                        $params[] = $like;
                        $params[] = $like;
                }
                if ( '' !== $args['date_from'] ) {
                        $where[]  = 'created_at >= %s';
                        $params[] = $args['date_from'] . ' 00:00:00';
                }
                if ( '' !== $args['date_to'] ) {
                        $where[]  = 'created_at <= %s';
                        $params[] = $args['date_to'] . ' 23:59:59';
                }
                $where_sql = implode( ' AND ', $where );

                $per_page = max( 1, min( 200, (int) $args['per_page'] ) );
                $page     = max( 1, (int) $args['page'] );
                $offset   = ( $page - 1 ) * $per_page;

                // phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- parameterized.
                $count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
                if ( $params ) {
                        $count_sql = $wpdb->prepare( $count_sql, $params );
                }
                $total = (int) $wpdb->get_var( $count_sql );

                $list_sql = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d";
                $rows     = $wpdb->get_results( $wpdb->prepare( $list_sql, array_merge( $params, array( $per_page, $offset ) ) ), ARRAY_A );
                // phpcs:enable

                return array(
                        'items'       => is_array( $rows ) ? $rows : array(),
                        'total'       => $total,
                        'total_pages' => (int) ceil( $total / $per_page ),
                        'page'        => $page,
                );
        }

        /**
         * Status counts grouped by type and status.
         *
         * @return array
         */
        public static function counts() {
                global $wpdb;
                $table = self::table_name();
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- aggregate on custom table.
                $rows = $wpdb->get_results( "SELECT type, status, COUNT(*) AS n FROM {$table} GROUP BY type, status", ARRAY_A );
                $out  = array();
                if ( is_array( $rows ) ) {
                        foreach ( $rows as $r ) {
                                $out[ $r['type'] ][ $r['status'] ] = (int) $r['n'];
                        }
                }
                return $out;
        }

        /**
         * Update a submission status (whitelisted) + audit entry.
         *
         * @param int    $id      Submission id.
         * @param string $status  New status.
         * @param string $note    Optional audit note.
         * @return bool
         */
        public static function update_status( $id, $status, $note = '' ) {
                global $wpdb;
                if ( ! in_array( $status, array( 'new', 'in_progress', 'follow_up', 'done', 'archived' ), true ) ) {
                        return false;
                }
                $table = self::table_name();
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- custom table update.
                $old = $wpdb->get_var( $wpdb->prepare( "SELECT status FROM {$table} WHERE id = %d", $id ) );
                if ( null === $old ) { return false; }
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- custom table update.
                $ok  = $wpdb->update( $table, array( 'status' => $status ), array( 'id' => (int) $id ), array( '%s' ), array( '%d' ) );
                if ( false !== $ok ) {
                        $user = wp_get_current_user();
                        self::audit( $id, 'status', (string) $old, $status, $user ? $user->user_login : '', $note );
                }
                return false !== $ok;
        }

        /**
         * Delete a submission (admin action, audited).
         *
         * @param int $id Submission id.
         * @return bool
         */
        public static function delete_submission( $id ) {
                global $wpdb;
                $table = self::table_name();
                $user  = wp_get_current_user();
                self::audit( (int) $id, 'delete', '', '', $user ? $user->user_login : '', __( 'Submission deleted', 'smart-support-chatbot' ) );
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- custom table delete.
                return false !== $wpdb->delete( $table, array( 'id' => (int) $id ), array( '%d' ) );
        }

        /**
         * Append an audit trail row (append-only).
         *
         * @param int    $submission_id Related submission.
         * @param string $action        Action name.
         * @param string $from          Previous value.
         * @param string $to            New value.
         * @param string $actor         Actor identity.
         * @param string $note          Free note.
         */
        public static function audit( $submission_id, $action, $from = '', $to = '', $actor = '', $note = '' ) {
                global $wpdb;
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- append-only audit insert.
                $wpdb->insert(
                        self::audit_table_name(),
                        array(
                                'submission_id' => (int) $submission_id,
                                'action'        => sanitize_key( $action ),
                                'from_status'   => sanitize_key( $from ),
                                'to_status'     => sanitize_key( $to ),
                                'actor'         => sanitize_text_field( $actor ),
                                'note'          => sanitize_text_field( $note ),
                                'created_at'    => current_time( 'mysql' ),
                        ),
                        array( '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
                );
        }

        /**
         * Audit rows for one submission.
         *
         * @param int $submission_id Submission id.
         * @return array
         */
        public static function get_audit( $submission_id ) {
                global $wpdb;
                $table = self::audit_table_name();
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- audit read.
                $rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE submission_id = %d ORDER BY id ASC", (int) $submission_id ), ARRAY_A );
                return is_array( $rows ) ? $rows : array();
        }

        /* ------------------------------------------------------------------ *
         * Chatlog.
         * ------------------------------------------------------------------ */

        /**
         * Log one exchange (only when the history module is active).
         *
         * @param string $question User message.
         * @param string $answer   Assistant answer.
         * @param string $source   ai|bank|cache|filter|unanswered.
         * @param string $product  Product scope.
         * @param string $ip       Client ip.
         * @return int Row id (0 on failure).
         */
        public static function log_chat( $question, $answer, $source, $product = 'general', $ip = '' ) {
                global $wpdb;
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- custom table insert.
                $wpdb->insert(
                        self::chatlog_table_name(),
                        array(
                                'product'    => sanitize_text_field( $product ),
                                'question'   => sanitize_textarea_field( $question ),
                                'answer'     => sanitize_textarea_field( $answer ),
                                'source'     => sanitize_key( $source ),
                                'ip'         => $ip,
                                'created_at' => current_time( 'mysql' ),
                        ),
                        array( '%s', '%s', '%s', '%s', '%s', '%s' )
                );
                return (int) $wpdb->insert_id;
        }

        /**
         * Attach a rating to a logged exchange (validated by token).
         *
         * @param int    $id     Row id.
         * @param int    $rating 1 or -1.
         * @return bool
         */
        public static function set_chatlog_rating( $id, $rating ) {
                global $wpdb;
                if ( ! in_array( (int) $rating, array( 1, -1 ), true ) ) {
                        return false;
                }
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- custom table update.
                $result = $wpdb->update( self::chatlog_table_name(), array( 'rating' => (int) $rating ), array( 'id' => (int) $id ), array( '%d' ), array( '%d' ) );
                return false !== $result;
        }

        /**
         * Chatlog rows with pagination.
         *
         * @param array $args source, product, page, per_page.
         * @return array
         */
        public static function get_chatlog( $args = array() ) {
                global $wpdb;
                $table = self::chatlog_table_name();
                $args  = wp_parse_args(
                        $args,
                        array(
                                'source'   => '',
                                'rating'   => '',
                                'per_page' => 30,
                                'page'     => 1,
                        )
                );
                $where  = array( '1=1' );
                $params = array();
                if ( '' !== $args['source'] ) {
                        $where[]  = 'source = %s';
                        $params[] = $args['source'];
                }
                if ( '' !== $args['rating'] ) {
                        $where[]  = 'rating = %d';
                        $params[] = (int) $args['rating'];
                }
                $where_sql = implode( ' AND ', $where );
                $per_page  = max( 1, min( 200, (int) $args['per_page'] ) );
                $offset    = ( max( 1, (int) $args['page'] ) - 1 ) * $per_page;

                // phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- parameterized.
                $sql = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY id DESC LIMIT %d OFFSET %d";
                $rows = $wpdb->get_results( $wpdb->prepare( $sql, array_merge( $params, array( $per_page, $offset ) ) ), ARRAY_A );
                $count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
                if ( $params ) {
                        $count_sql = $wpdb->prepare( $count_sql, $params );
                }
                $total = (int) $wpdb->get_var( $count_sql );
                // phpcs:enable
                return array(
                        'items'       => is_array( $rows ) ? $rows : array(),
                        'total'       => $total,
                        'total_pages' => (int) ceil( $total / $per_page ),
                        'page'        => (int) $args['page'],
                );
        }

        /**
         * Delete a chatlog row.
         *
         * @param int $id Row id.
         * @return bool
         */
        public static function delete_chatlog( $id ) {
                global $wpdb;
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- custom table delete.
                return false !== $wpdb->delete( self::chatlog_table_name(), array( 'id' => (int) $id ), array( '%d' ) );
        }

        /**
         * Unanswered questions radar (questions with no usable answer).
         *
         * @param int $days Look-back window.
         * @param int $limit Max rows.
         * @return array
         */
        public static function unanswered_questions( $days = 14, $limit = 50 ) {
                global $wpdb;
                $table = self::chatlog_table_name();
                $from  = gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS * max( 1, $days ) );
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- aggregated radar query.
                $rows = $wpdb->get_results(
                        $wpdb->prepare(
                                "SELECT question, COUNT(*) AS n, MAX(created_at) AS last_at
                                 FROM {$table}
                                 WHERE source = 'unanswered' AND created_at >= %s
                                 GROUP BY question ORDER BY n DESC LIMIT %d",
                                $from,
                                (int) $limit
                        ),
                        ARRAY_A
                );
                return is_array( $rows ) ? $rows : array();
        }

        /* ------------------------------------------------------------------ *
         * QA bank.
         * ------------------------------------------------------------------ */

        /**
         * QA candidates for a product scope. Deterministic order (recent first)
         * so rows beyond the candidate cap are stable, never silently unreachable.
         *
         * @param string $product_id Scope.
         * @return array
         */
        public static function qa_candidates( $product_id ) {
                global $wpdb;
                $table = self::qa_table_name();
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- candidate window.
                $rows = $wpdb->get_results(
                        $wpdb->prepare(
                                "SELECT id, question, keywords, answer FROM {$table}
                                 WHERE product_id IN (%s, 'general') ORDER BY id DESC LIMIT 800",
                                sanitize_text_field( $product_id )
                        ),
                        ARRAY_A
                );
                return is_array( $rows ) ? $rows : array();
        }

        /**
         * Insert a QA row.
         *
         * @param array $row question, keywords, answer, product_id.
         * @return int Row id.
         */
        public static function qa_insert( $row ) {
                global $wpdb;
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- custom table insert.
                $wpdb->insert(
                        self::qa_table_name(),
                        array(
                                'product_id' => isset( $row['product_id'] ) ? sanitize_text_field( $row['product_id'] ) : 'general',
                                'question'   => sanitize_textarea_field( $row['question'] ),
                                'keywords'   => isset( $row['keywords'] ) ? sanitize_text_field( $row['keywords'] ) : '',
                                'answer'     => sanitize_textarea_field( $row['answer'] ),
                                'created_at' => current_time( 'mysql' ),
                        ),
                        array( '%s', '%s', '%s', '%s', '%s' )
                );
                return (int) $wpdb->insert_id;
        }

        /**
         * Bump usage counter for a matched QA row.
         *
         * @param int $id Row id.
         */
        public static function qa_touch( $id ) {
                global $wpdb;
                $table = self::qa_table_name();
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- counter bump.
                $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET usage_count = usage_count + 1 WHERE id = %d", (int) $id ) );
        }

        /**
         * Delete QA rows.
         *
         * @param int[] $ids Row ids.
         */
        public static function qa_delete( $ids ) {
                global $wpdb;
                $table = self::qa_table_name();
                foreach ( array_map( 'intval', (array) $ids ) as $id ) {
                        if ( $id > 0 ) {
                                // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- custom table delete.
                                $wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );
                        }
                }
        }

        /**
         * Count QA rows.
         *
         * @return int
         */
        public static function qa_count() {
                global $wpdb;
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- count.
                return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::qa_table_name() );
        }

        /**
         * Import QA rows in bulk (replace = truncate first).
         *
         * @param array $rows   List of row arrays.
         * @param bool  $replace Replace mode.
         * @return int Inserted count.
         */
        public static function qa_import( $rows, $replace = false ) {
                global $wpdb;
                if ( $replace ) {
                        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- explicit bulk replace requested by admin.
                        $wpdb->query( 'TRUNCATE TABLE ' . self::qa_table_name() );
                }
                $n = 0;
                foreach ( (array) $rows as $row ) {
                        if ( ! empty( $row['question'] ) && ! empty( $row['answer'] ) ) {
                                self::qa_insert( $row );
                                ++$n;
                        }
                }
                return $n;
        }

        /* ------------------------------------------------------------------ *
         * Knowledge base chunks.
         * ------------------------------------------------------------------ */

        /**
         * Insert a KB document as chunks.
         *
         * @param string $doc_id   Stable document id.
         * @param string $title    Source title.
         * @param string $text     Full text.
         * @param string $product  Product scope.
         * @return int Chunks inserted.
         */
        public static function kb_insert_document( $doc_id, $title, $text, $product = 'general' ) {
                global $wpdb;
                $chunks = SSC_Knowledge::chunk_text( $text );
                $n      = 0;
                foreach ( $chunks as $chunk ) {
                        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- custom table insert.
                        $inserted = $wpdb->insert(
                                self::kb_table_name(),
                                array(
                                        'doc_id'       => sanitize_key( $doc_id ),
                                        'product_id'   => sanitize_text_field( $product ),
                                        'source_title' => sanitize_text_field( $title ),
                                        'chunk'        => $chunk,
                                        'search_text'  => SSC_Knowledge::normalize( $chunk ),
                                        'created_at'   => current_time( 'mysql' ),
                                ),
                                array( '%s', '%s', '%s', '%s', '%s', '%s' )
                        );
                        if ( false !== $inserted ) { ++$n; }
                }
                return $n;
        }

        /**
         * KB candidates (deterministic order).
         *
         * @param string $product_id Scope.
         * @return array
         */
        public static function kb_candidates( $product_id ) {
                global $wpdb;
                $table = self::kb_table_name();
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- candidate window.
                $rows = $wpdb->get_results(
                        $wpdb->prepare(
                                "SELECT id, source_title, chunk FROM {$table}
                                 WHERE product_id IN (%s, 'general') ORDER BY id ASC LIMIT 800",
                                sanitize_text_field( $product_id )
                        ),
                        ARRAY_A
                );
                return is_array( $rows ) ? $rows : array();
        }

        /**
         * Delete a whole KB document.
         *
         * @param string $doc_id Document id.
         * @return bool
         */
        public static function kb_delete_document( $doc_id ) {
                global $wpdb;
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- document removal.
                return false !== $wpdb->delete( self::kb_table_name(), array( 'doc_id' => sanitize_key( $doc_id ) ), array( '%s' ) );
        }

        /**
         * All KB documents (id, title, chunks, created).
         *
         * @return array
         */
        public static function kb_documents() {
                global $wpdb;
                $table = self::kb_table_name();
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- document listing.
                $rows = $wpdb->get_results(
                        "SELECT doc_id, source_title, COUNT(*) AS chunks, MIN(created_at) AS created_at
                         FROM {$table} GROUP BY doc_id, source_title ORDER BY doc_id ASC",
                        ARRAY_A
                );
                return is_array( $rows ) ? $rows : array();
        }

        /**
         * Count KB chunks.
         *
         * @return int
         */
        public static function kb_count() {
                global $wpdb;
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- count.
                return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::kb_table_name() );
        }

        /**
         * Remove every KB chunk.
         */
        public static function kb_clear() {
                global $wpdb;
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- explicit clear requested by admin.
                $wpdb->query( 'TRUNCATE TABLE ' . self::kb_table_name() );
        }

        /* ------------------------------------------------------------------ *
         * Stats & rate limiting.
         * ------------------------------------------------------------------ */

        /**
         * Atomic counter increment (INSERT ... ON DUPLICATE KEY UPDATE).
         *
         * Overcount note: the subsequent read is a separate statement, so two
         * concurrent hits may both see limit+1. This fails CLOSED (extra
         * strictness), which is the safe direction for abuse protection.
         *
         * @param string $metric Metric key.
         * @return int Current count.
         */
        public static function stat_hit( $metric ) {
                global $wpdb;
                $table = self::stats_table_name();
                $today = current_time( 'Y-m-d' );
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- atomic upsert counter.
                $wpdb->query(
                        $wpdb->prepare(
                                "INSERT INTO {$table} (stat_date, metric, cnt) VALUES (%s, %s, 1)
                                 ON DUPLICATE KEY UPDATE cnt = cnt + 1",
                                $today,
                                $metric
                        )
                );
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- read-back.
                return (int) $wpdb->get_var( $wpdb->prepare( "SELECT cnt FROM {$table} WHERE stat_date = %s AND metric = %s", $today, $metric ) );
        }

        /**
         * Add to a counter without reading (CSAT sums etc.).
         *
         * @param string $metric Metric key.
         * @param int    $value  Value to add.
         */
        public static function stat_add( $metric, $value ) {
                global $wpdb;
                $table = self::stats_table_name();
                $today = current_time( 'Y-m-d' );
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- atomic upsert.
                $wpdb->query(
                        $wpdb->prepare(
                                "INSERT INTO {$table} (stat_date, metric, cnt) VALUES (%s, %s, %d)
                                 ON DUPLICATE KEY UPDATE cnt = cnt + %d",
                                $today,
                                $metric,
                                (int) $value,
                                (int) $value
                        )
                );
        }

        /**
         * Rate limit check-and-hit for a bucket.
         *
         * @param string $bucket Bucket (chat|submit|suggest|csat|feedback).
         * @param string $mode   ip|session|both|off.
         * @param int    $limit_ip Per-day IP limit.
         * @param int    $limit_session Per-day session limit.
         * @param string $ip     Client ip.
         * @param string $cid    Client session id.
         * @return bool True when allowed (and counted).
         */
        public static function rate_limit( $bucket, $mode, $limit_ip, $limit_session, $ip, $cid ) {
                if ( 'off' === $mode ) {
                        return true;
                }
                $blocked = false;

                if ( in_array( $mode, array( 'ip', 'both' ), true ) && $limit_ip > 0 ) {
                        $count = self::stat_hit( 'rl:' . $bucket . ':ip:' . md5( $ip ) );
                        if ( $count > $limit_ip ) {
                                $blocked = true;
                        }
                }
                if ( ! $blocked && in_array( $mode, array( 'session', 'both' ), true ) && $limit_session > 0 ) {
                        // Omitting cid must not disable session quotas.
                        $cid = '' !== $cid ? $cid : 'missing:' . $ip;
                        $count = self::stat_hit( 'rl:' . $bucket . ':sess:' . md5( $cid ) );
                        if ( $count > $limit_session ) {
                                $blocked = true;
                        }
                        // Backstop: sessions can be regenerated; an IP ceiling guards it.
                        $cap = max( 10, $limit_session * 10 );
                        if ( self::stat_hit( 'rl:' . $bucket . ':ipcap:' . md5( $ip ) ) > $cap ) {
                                $blocked = true;
                        }
                }
                return ! $blocked;
        }

        /**
         * Daily chat metric + product views.
         *
         * @param string $product Product scope.
         */
        public static function record_chat( $product = 'general' ) {
                self::stat_hit( 'chat' );
                if ( 'general' !== $product ) {
                        self::stat_hit( 'product:' . sanitize_text_field( $product ) );
                }
        }

        /**
         * CSAT vote.
         *
         * @param int $score 1..5.
         */
        public static function record_csat( $score ) {
                $score = max( 1, min( 5, (int) $score ) );
                self::stat_hit( 'csat_count' );
                self::stat_add( 'csat_sum', $score );
                self::stat_hit( 'csat:' . $score );
        }

        /**
         * Stats time series for analytics (per metric, per day).
         *
         * @param int    $days   Window.
         * @param string $metric Exact metric or prefix pattern like 'product:%'.
         * @return array rows (stat_date, cnt).
         */
        public static function stats_series( $days, $metric ) {
                global $wpdb;
                $table = self::stats_table_name();
                $from  = gmdate( 'Y-m-d', time() - DAY_IN_SECONDS * max( 1, $days ) );
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- aggregated read.
                $rows = $wpdb->get_results(
                        $wpdb->prepare(
                                "SELECT stat_date, SUM(cnt) AS cnt FROM {$table}
                                 WHERE metric LIKE %s AND stat_date >= %s GROUP BY stat_date ORDER BY stat_date ASC",
                                $metric,
                                $from
                        ),
                        ARRAY_A
                );
                return is_array( $rows ) ? $rows : array();
        }

        /**
         * All-time sums for a metric pattern.
         *
         * @param string $metric Metric or pattern.
         * @return int
         */
        public static function stats_sum( $metric ) {
                global $wpdb;
                $table = self::stats_table_name();
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- aggregate.
                return (int) $wpdb->get_var( $wpdb->prepare( "SELECT SUM(cnt) FROM {$table} WHERE metric LIKE %s", $metric ) );
        }

        /**
         * Distinct metric keys matching a pattern (top products).
         *
         * @param string $pattern LIKE pattern.
         * @param int    $limit   Max rows.
         * @return array
         */
        public static function stats_top( $pattern, $limit = 10 ) {
                global $wpdb;
                $table = self::stats_table_name();
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- aggregate.
                $rows = $wpdb->get_results(
                        $wpdb->prepare(
                                "SELECT metric, SUM(cnt) AS cnt FROM {$table} WHERE metric LIKE %s GROUP BY metric ORDER BY cnt DESC LIMIT %d",
                                $pattern,
                                (int) $limit
                        ),
                        ARRAY_A
                );
                return is_array( $rows ) ? $rows : array();
        }

        /**
         * CSAT aggregate.
         *
         * @return array count, avg.
         */
        public static function csat_summary() {
                $count = self::stats_sum( 'csat_count' );
                $sum   = self::stats_sum( 'csat_sum' );
                return array(
                        'count' => $count,
                        'avg'   => $count > 0 ? round( $sum / $count, 2 ) : 0,
                );
        }

        /**
         * Purge stale data (daily cron).
         *
         * @param int $chatlog_days   Chatlog retention (0 = keep forever).
         * @param int $submission_days Submission retention (0 = keep forever).
         */
        public static function purge_old( $chatlog_days, $submission_days ) {
                global $wpdb;
                if ( $chatlog_days > 0 ) {
                        $from = gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS * $chatlog_days );
                        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- retention purge.
                        $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::chatlog_table_name() . ' WHERE created_at < %s', $from ) );
                }
                if ( $submission_days > 0 ) {
                        $from = gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS * $submission_days );
                        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- retention purge (does not touch archived cases).
                        $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::table_name() . " WHERE created_at < %s AND type != 'pharma_adr'", $from ) );
                }
                // Rate-limit rows are per-day; anything older than 2 days is dead weight.
                $stale = gmdate( 'Y-m-d', time() - 2 * DAY_IN_SECONDS );
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- stale counter purge.
                $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::stats_table_name() . " WHERE metric LIKE 'rl:%%' AND stat_date < %s", $stale ) );
        }

        /**
         * 4.x option-based stats migration (once).
         */
        public static function migrate_stats_from_options() {
                if ( get_option( 'ssc_chatbot_stats_migrated', false ) ) {
                        return;
                }
                $chat_stats = get_option( 'ssc_chatbot_chat_stats', array() );
                if ( is_array( $chat_stats ) && ! empty( $chat_stats ) ) {
                        $day = gmdate( 'Y-m-d' );
                        foreach ( $chat_stats as $metric => $count ) {
                                $count = (int) $count;
                                if ( $count > 0 ) {
                                        self::stat_add( (string) $metric, $count );
                                }
                        }
                        unset( $day );
                }
                update_option( 'ssc_chatbot_stats_migrated', 1, false );
        }

        /**
         * 4.x option-based QA bank migration (once).
         */
        public static function migrate_qa_from_options() {
                if ( get_option( 'ssc_chatbot_qa_migrated', false ) ) {
                        return;
                }
                $settings = get_option( SSC_Settings::OPTION_KEY, array() );
                $bank     = isset( $settings['qa_bank'] ) && is_array( $settings['qa_bank'] ) ? $settings['qa_bank'] : array();
                foreach ( $bank as $row ) {
                        if ( ! empty( $row['question'] ) && ! empty( $row['answer'] ) ) {
                                self::qa_insert(
                                        array(
                                                'product_id' => isset( $row['product'] ) ? $row['product'] : 'general',
                                                'question'   => $row['question'],
                                                'keywords'   => isset( $row['keywords'] ) ? $row['keywords'] : '',
                                                'answer'     => $row['answer'],
                                        )
                                );
                        }
                }
                update_option( 'ssc_chatbot_qa_migrated', 1, false );
        }
}
