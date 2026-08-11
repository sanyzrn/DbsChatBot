<?php
/**
 * پنل مدیریت وردپرس.
 *
 * @package SmartSupportChatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * کلاس Admin.
 */
class SSC_Chatbot_Admin {

	/**
	 * راه‌اندازی.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'handle_actions' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_ssc_chatbot_export', array( $this, 'export_csv' ) );
	}

	/**
	 * ثبت منوی مدیریت.
	 */
	public function register_menu() {
		$counts    = SSC_Chatbot_DB::counts();
		$new_count = 0;
		// شمارش درخواست‌های جدید برای نشان (badge).
		global $wpdb;
		$table = SSC_Chatbot_DB::table_name();
		// phpcs:ignore WordPress.DB
		$new_count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status = %s", 'new' ) );

		$badge = $new_count > 0 ? ' <span class="awaiting-mod">' . esc_html( number_format_i18n( $new_count ) ) . '</span>' : '';

		add_menu_page(
			esc_html__( 'دستیار هوشمند', 'smart-support-chatbot' ),
			esc_html__( 'دستیار هوشمند', 'smart-support-chatbot' ) . $badge,
			'manage_options',
			'smart-support-chatbot',
			array( $this, 'render_dashboard_page' ),
			'dashicons-format-chat',
			58
		);

		add_submenu_page(
			'smart-support-chatbot',
			esc_html__( 'داشبورد', 'smart-support-chatbot' ),
			esc_html__( 'داشبورد', 'smart-support-chatbot' ),
			'manage_options',
			'smart-support-chatbot',
			array( $this, 'render_dashboard_page' )
		);

		add_submenu_page(
			'smart-support-chatbot',
			esc_html__( 'درخواست‌ها', 'smart-support-chatbot' ),
			esc_html__( 'درخواست‌ها', 'smart-support-chatbot' ) . $badge,
			'manage_options',
			'smart-support-chatbot-submissions',
			array( $this, 'render_submissions_page' )
		);

		add_submenu_page(
			'smart-support-chatbot',
			esc_html__( 'بانک پاسخ‌ها', 'smart-support-chatbot' ),
			esc_html__( 'بانک پاسخ‌ها', 'smart-support-chatbot' ),
			'manage_options',
			'smart-support-chatbot-qa',
			array( $this, 'render_qa_page' )
		);

		add_submenu_page(
			'smart-support-chatbot',
			esc_html__( 'پایگاه دانش', 'smart-support-chatbot' ),
			esc_html__( 'پایگاه دانش', 'smart-support-chatbot' ),
			'manage_options',
			'smart-support-chatbot-kb',
			array( $this, 'render_kb_page' )
		);

		add_submenu_page(
			'smart-support-chatbot',
			esc_html__( 'تاریخچه گفتگو', 'smart-support-chatbot' ),
			esc_html__( 'تاریخچه گفتگو', 'smart-support-chatbot' ),
			'manage_options',
			'smart-support-chatbot-chatlog',
			array( $this, 'render_chatlog_page' )
		);

		add_submenu_page(
			'smart-support-chatbot',
			esc_html__( 'تنظیمات', 'smart-support-chatbot' ),
			esc_html__( 'تنظیمات', 'smart-support-chatbot' ),
			'manage_options',
			'smart-support-chatbot-settings',
			array( $this, 'render_settings_page' )
		);

		add_submenu_page(
			'smart-support-chatbot',
			esc_html__( 'درباره و توسعه‌دهندگان', 'smart-support-chatbot' ),
			'💎 ' . esc_html__( 'درباره', 'smart-support-chatbot' ),
			'manage_options',
			'smart-support-chatbot-about',
			array( $this, 'render_about_page' )
		);
	}

	/**
	 * رندر صفحه «درباره و توسعه‌دهندگان».
	 */
	public function render_about_page() {
		$insights = array(
			'qa'   => SSC_Chatbot_DB::qa_count(),
			'kb'   => SSC_Chatbot_DB::kb_count(),
			'chat' => isset( SSC_Chatbot_DB::get_chat_stats()['total'] ) ? (int) SSC_Chatbot_DB::get_chat_stats()['total'] : 0,
		);
		require SSC_CHATBOT_DIR . 'includes/views/about-page.php';
	}

	/**
	 * رندر صفحه بانک پاسخ‌ها.
	 */
	public function render_qa_page() {
		$s            = SSC_Chatbot_Settings::all();
		$products_map = SSC_Chatbot_Settings::products_map();
		$sample_url   = SSC_CHATBOT_URL . 'sample-qa.csv';
		$bank         = SSC_Chatbot_DB::qa_get_all(); // ردیف‌ها با کلید product_id.
		require SSC_CHATBOT_DIR . 'includes/views/qa-bank-page.php';
	}

	/**
	 * رندر صفحه پایگاه دانش.
	 */
	public function render_kb_page() {
		$s            = SSC_Chatbot_Settings::all();
		$products_map = SSC_Chatbot_Settings::products_map();
		$docs         = SSC_Chatbot_DB::kb_get_documents();
		$kb_count     = SSC_Chatbot_DB::kb_count();
		require SSC_CHATBOT_DIR . 'includes/views/kb-page.php';
	}

	/**
	 * ذخیره تنظیمات پایگاه دانش + افزودن سند (متن یا فایل txt).
	 *
	 * @return string پیام نتیجه.
	 */
	protected function save_kb() {
		$in                   = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification -- بررسی شده.
		$new                  = array();
		$new['kb_enabled']    = ( isset( $in['kb_enabled'] ) && ( '1' === (string) $in['kb_enabled'] || 'yes' === $in['kb_enabled'] || 'on' === $in['kb_enabled'] ) ) ? 'yes' : 'no';
		$new['kb_max_chunks'] = isset( $in['kb_max_chunks'] ) ? max( 1, min( 8, (int) $in['kb_max_chunks'] ) ) : 3;
		SSC_Chatbot_Settings::update( $new );

		// محتوای سند: متن واردشده + فایل txt آپلودی.
		$product = isset( $in['kb_product'] ) ? sanitize_text_field( $in['kb_product'] ) : 'general';
		$title   = isset( $in['kb_title'] ) ? sanitize_text_field( $in['kb_title'] ) : '';
		$content = isset( $in['kb_content'] ) ? sanitize_textarea_field( $in['kb_content'] ) : '';

		if ( ! empty( $_FILES['kb_file']['tmp_name'] ) && is_uploaded_file( $_FILES['kb_file']['tmp_name'] ) ) { // phpcs:ignore
			$max_bytes = (int) apply_filters( 'ssc_chatbot_max_upload_bytes', 2 * 1024 * 1024 );
			if ( (int) $_FILES['kb_file']['size'] > $max_bytes ) { // phpcs:ignore WordPress.Security
				return __( 'فایل بیش از حد بزرگ است (حداکثر ۲ مگابایت).', 'smart-support-chatbot' );
			}
			$raw      = file_get_contents( $_FILES['kb_file']['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions, WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce توسط فراخوان (check_admin_referer در register_menu) بررسی شده؛ tmp_name توسط is_uploaded_file اعتبارسنجی شده است.
			$raw      = sanitize_textarea_field( (string) $raw );
			$content .= ( '' !== $content ? "\n\n" : '' ) . $raw;
			if ( '' === trim( $title ) && isset( $_FILES['kb_file']['name'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce توسط فراخوان بررسی شده.
				$title = sanitize_file_name( $_FILES['kb_file']['name'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- نام فایل با sanitize_file_name پاکسازی می‌شود.
			}
		}

		if ( '' === trim( $content ) ) {
			return __( 'تنظیمات پایگاه دانش ذخیره شد.', 'smart-support-chatbot' );
		}

		$n = SSC_Chatbot_DB::kb_insert_document( $product, $title, $content );
		/* translators: %d: تعداد تکه‌ها. */
		return sprintf( __( 'سند افزوده شد و به %d تکه تقسیم شد.', 'smart-support-chatbot' ), $n );
	}

	/**
	 * رندر صفحه تاریخچه گفتگو.
	 */
	public function render_chatlog_page() {
		// فیلترهای فقط-خواندنی صفحه لیست (بدون تغییر وضعیت)؛ مطابق الگوی WP_List_Table نیازی به nonce نیست.
		$source       = isset( $_GET['source'] ) ? sanitize_text_field( wp_unslash( $_GET['source'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$search       = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$paged        = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$result       = SSC_Chatbot_DB::get_chatlog(
			array(
				'source'   => $source,
				'search'   => $search,
				'page'     => $paged,
				'per_page' => 20,
			)
		);
		$products_map = SSC_Chatbot_Settings::products_map();
		require SSC_CHATBOT_DIR . 'includes/views/chatlog-page.php';
	}

	/**
	 * رندر صفحه داشبورد.
	 */
	public function render_dashboard_page() {
		$counts       = SSC_Chatbot_DB::counts();
		$chat_stats   = SSC_Chatbot_DB::get_chat_stats();
		$product_subs = SSC_Chatbot_DB::product_submission_counts();
		$recent       = SSC_Chatbot_DB::get_recent( 6 );
		$adr_opts     = SSC_Chatbot_Settings::adr_options();
		$serious_list = apply_filters( 'ssc_chatbot_serious_severities', array( 'شدید', 'تهدیدکننده حیات', 'منجر به بستری شد', 'فوت' ) );
		$insights     = array(
			'unanswered' => SSC_Chatbot_DB::count_chatlog_source( 'unanswered' ),
			'feedback'   => SSC_Chatbot_DB::feedback_counts(),
			'serious'    => SSC_Chatbot_DB::serious_adr_count( $serious_list ),
			'qa_count'   => SSC_Chatbot_DB::qa_count(),
			'csat'       => SSC_Chatbot_DB::get_csat_stats(),
		);
		require SSC_CHATBOT_DIR . 'includes/views/dashboard-page.php';
	}

	/**
	 * بارگذاری استایل ادمین.
	 *
	 * @param string $hook هوک صفحه.
	 */
	public function enqueue_assets( $hook ) {
		if ( false === strpos( $hook, 'smart-support-chatbot' ) ) {
			return;
		}
		wp_enqueue_style(
			'smart-support-chatbot-admin',
			SSC_CHATBOT_URL . 'assets/css/admin.css',
			array(),
			SSC_CHATBOT_VERSION
		);
		wp_enqueue_script(
			'smart-support-chatbot-admin',
			SSC_CHATBOT_URL . 'assets/js/admin.js',
			array( 'jquery', 'wp-color-picker' ),
			SSC_CHATBOT_VERSION,
			true
		);
		wp_enqueue_style( 'wp-color-picker' );

		wp_localize_script(
			'smart-support-chatbot-admin',
			'SSCAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'ssc_chatbot_admin' ),
			)
		);
	}

	/**
	 * پردازش اکشن‌های فرم (ذخیره تنظیمات، تغییر وضعیت، حذف).
	 */
	public function handle_actions() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// ذخیره تنظیمات.
		if ( isset( $_POST['ssc_chatbot_save_settings'] ) ) {
			check_admin_referer( 'ssc_chatbot_settings' );
			$this->save_settings();
			add_action(
				'admin_notices',
				function () {
					echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'تنظیمات با موفقیت ذخیره شد.', 'smart-support-chatbot' ) . '</p></div>';
				}
			);
		}

		// تغییر وضعیت درخواست.
		if ( isset( $_GET['ssc_action'], $_GET['sid'] ) && 'status' === $_GET['ssc_action'] ) {
			check_admin_referer( 'ssc_sub_action' );
			$sid    = (int) $_GET['sid'];
			$status = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : 'new';
			SSC_Chatbot_DB::update_status( $sid, $status );
			wp_safe_redirect( remove_query_arg( array( 'ssc_action', 'sid', 'status', '_wpnonce' ) ) );
			exit;
		}

		// حذف درخواست.
		if ( isset( $_GET['ssc_action'], $_GET['sid'] ) && 'delete' === $_GET['ssc_action'] ) {
			check_admin_referer( 'ssc_sub_action' );
			SSC_Chatbot_DB::delete( (int) $_GET['sid'] );
			wp_safe_redirect( remove_query_arg( array( 'ssc_action', 'sid', '_wpnonce' ) ) );
			exit;
		}

		// ذخیره بانک پاسخ‌ها (شامل ایمپورت فایل).
		if ( isset( $_POST['ssc_chatbot_save_qa'] ) ) {
			check_admin_referer( 'ssc_chatbot_qa' );
			$msg = $this->save_qa_bank();
			add_action(
				'admin_notices',
				function () use ( $msg ) {
					echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
				}
			);
		}

		// ذخیره پایگاه دانش (تنظیمات + افزودن سند).
		if ( isset( $_POST['ssc_chatbot_save_kb'] ) ) {
			check_admin_referer( 'ssc_chatbot_kb' );
			$msg = $this->save_kb();
			add_action(
				'admin_notices',
				function () use ( $msg ) {
					echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
				}
			);
		}

		// حذف یک سند از پایگاه دانش.
		if ( isset( $_GET['ssc_action'], $_GET['doc'] ) && 'delkb' === $_GET['ssc_action'] ) {
			check_admin_referer( 'ssc_kb_action' );
			SSC_Chatbot_DB::kb_delete_document( sanitize_text_field( wp_unslash( $_GET['doc'] ) ) );
			wp_safe_redirect( remove_query_arg( array( 'ssc_action', 'doc', '_wpnonce' ) ) );
			exit;
		}

		// پاک‌سازی کامل پایگاه دانش.
		if ( isset( $_POST['ssc_chatbot_clear_kb'] ) ) {
			check_admin_referer( 'ssc_kb_clear' );
			SSC_Chatbot_DB::kb_clear();
			wp_safe_redirect( admin_url( 'admin.php?page=smart-support-chatbot-kb' ) );
			exit;
		}

		// افزودن یک گفتگو به بانک.
		if ( isset( $_GET['ssc_action'], $_GET['cid'] ) && 'tobank' === $_GET['ssc_action'] ) {
			check_admin_referer( 'ssc_chatlog_action' );
			$entry = SSC_Chatbot_DB::get_chatlog_entry( (int) $_GET['cid'] );
			if ( $entry ) {
				// برای سوال‌های بی‌پاسخ، پاسخ خالی می‌گذاریم تا مدیر در صفحه بانک تکمیل کند.
				$ans = ( 'unanswered' === $entry->source ) ? '' : $entry->answer;
				SSC_Chatbot_DB::qa_insert(
					array(
						'product_id' => $entry->product ? $entry->product : 'general',
						'question'   => $entry->question,
						'keywords'   => '',
						'answer'     => $ans,
					)
				);
				SSC_Chatbot_DB::mark_chatlog_in_bank( (int) $_GET['cid'] );
			}
			wp_safe_redirect( add_query_arg( 'added', '1', remove_query_arg( array( 'ssc_action', 'cid', '_wpnonce' ) ) ) );
			exit;
		}

		// حذف یک ردیف تاریخچه.
		if ( isset( $_GET['ssc_action'], $_GET['cid'] ) && 'dellog' === $_GET['ssc_action'] ) {
			check_admin_referer( 'ssc_chatlog_action' );
			SSC_Chatbot_DB::delete_chatlog_entry( (int) $_GET['cid'] );
			wp_safe_redirect( remove_query_arg( array( 'ssc_action', 'cid', '_wpnonce' ) ) );
			exit;
		}

		// پاک‌سازی کل تاریخچه.
		if ( isset( $_POST['ssc_chatbot_clear_log'] ) ) {
			check_admin_referer( 'ssc_chatlog_clear' );
			SSC_Chatbot_DB::clear_chatlog();
			wp_safe_redirect( admin_url( 'admin.php?page=smart-support-chatbot-chatlog' ) );
			exit;
		}
	}

	/**
	 * ذخیره بانک پاسخ‌ها از فرم + ایمپورت فایل.
	 *
	 * @return string پیام نتیجه.
	 */
	protected function save_qa_bank() {
		$in  = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification -- بررسی شده.
		$new = array();

		// حالت و فعال‌سازی لاگ.
		$mode                          = isset( $in['qa_mode'] ) ? sanitize_text_field( $in['qa_mode'] ) : 'ai_first';
		$new['qa_mode']                = in_array( $mode, array( 'ai_first', 'bank_first', 'bank_only' ), true ) ? $mode : 'ai_first';
		$new['chatlog_enabled']        = ( isset( $in['chatlog_enabled'] ) && ( '1' === (string) $in['chatlog_enabled'] || 'yes' === $in['chatlog_enabled'] || 'on' === $in['chatlog_enabled'] ) ) ? 'yes' : 'no';
		$new['chatlog_retention_days'] = isset( $in['chatlog_retention_days'] ) ? max( 0, min( 3650, (int) $in['chatlog_retention_days'] ) ) : 90;

		// ردیف‌های دستی.
		$bank = array();
		if ( isset( $in['qa_question'] ) && is_array( $in['qa_question'] ) ) {
			$row_ids  = isset( $in['qa_id'] ) ? $in['qa_id'] : array();
			$products = isset( $in['qa_product'] ) ? $in['qa_product'] : array();
			$keywords = isset( $in['qa_keywords'] ) ? $in['qa_keywords'] : array();
			$answers  = isset( $in['qa_answer'] ) ? $in['qa_answer'] : array();
			foreach ( $in['qa_question'] as $i => $q ) {
				$q = sanitize_textarea_field( $q );
				$a = isset( $answers[ $i ] ) ? sanitize_textarea_field( $answers[ $i ] ) : '';
				if ( '' === trim( $q ) || '' === trim( $a ) ) {
					continue;
				}
				$bank[] = array(
					'id'       => isset( $row_ids[ $i ] ) ? (int) $row_ids[ $i ] : 0,
					'product'  => isset( $products[ $i ] ) ? sanitize_text_field( $products[ $i ] ) : 'general',
					'question' => $q,
					'keywords' => isset( $keywords[ $i ] ) ? sanitize_text_field( $keywords[ $i ] ) : '',
					'answer'   => $a,
				);
			}
		}

		// تشخیص بریده‌شدن POST توسط max_input_vars.
		// هر ردیف ۵ فیلد آرایه‌ای دارد؛ با عبور از سقف، PHP بی‌صدا ورودی را می‌بُرد.
		// در این حالت ذخیره متوقف می‌شود تا ردیف‌های ارسال‌نشده آسیب نبینند.
		$rendered = isset( $in['qa_rendered'] ) ? (int) $in['qa_rendered'] : 0;
		$received = isset( $in['qa_id'] ) && is_array( $in['qa_id'] ) ? count( $in['qa_id'] ) : 0;
		if ( $rendered > 0 && $received > 0 && $received < $rendered ) {
			return sprintf(
				/* translators: 1: تعداد دریافت‌شده، 2: تعداد ارسالی، 3: مقدار max_input_vars */
				__( '⚠️ ذخیره انجام نشد: فقط %1$d ردیف از %2$d ردیف به سرور رسید. مقدار max_input_vars در php.ini (فعلاً %3$s) برای این تعداد ردیف کافی نیست. آن را افزایش دهید یا بانک را در چند مرحله ذخیره کنید. هیچ داده‌ای تغییر نکرد.', 'smart-support-chatbot' ),
				$received,
				$rendered,
				ini_get( 'max_input_vars' )
			);
		}

		// ایمپورت فایل (CSV یا JSON).
		$imported  = 0;
		$replace   = false;
		$max_bytes = (int) apply_filters( 'ssc_chatbot_max_upload_bytes', 2 * 1024 * 1024 );
		// nonce توسط فراخوان (check_admin_referer در register_menu) بررسی شده؛ tmp_name توسط is_uploaded_file اعتبارسنجی و نام فایل با sanitize_file_name پاکسازی می‌شود.
		if ( ! empty( $_FILES['qa_import']['tmp_name'] ) && is_uploaded_file( $_FILES['qa_import']['tmp_name'] ) && (int) $_FILES['qa_import']['size'] <= $max_bytes ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.InputNotValidated
			$content  = file_get_contents( $_FILES['qa_import']['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions, WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$fname    = isset( $_FILES['qa_import']['name'] ) ? sanitize_file_name( $_FILES['qa_import']['name'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$rows     = $this->parse_qa_import( $content, $fname );
			$imported = count( $rows );
			$replace  = ( isset( $in['import_mode'] ) && 'replace' === $in['import_mode'] );
			if ( $replace ) {
				$bank = $rows;
			} else {
				$bank = array_merge( $bank, $rows );
			}
		}

		// ذخیره تنظیمات (qa_mode، chatlog_*).
		SSC_Chatbot_Settings::update( $new );

		// نگاشت کلید product → product_id.
		$table_rows = array();
		foreach ( $bank as $r ) {
			$table_rows[] = array(
				'id'         => isset( $r['id'] ) ? (int) $r['id'] : 0,
				'product_id' => isset( $r['product'] ) ? $r['product'] : 'general',
				'question'   => isset( $r['question'] ) ? $r['question'] : '',
				'keywords'   => isset( $r['keywords'] ) ? $r['keywords'] : '',
				'answer'     => isset( $r['answer'] ) ? $r['answer'] : '',
			);
		}

		if ( $replace ) {
			// فقط ایمپورت با حالت «جایگزینی» اجازهٔ پاک‌کردن کل بانک را دارد.
			SSC_Chatbot_DB::qa_replace_all( $table_rows );
		} else {
			// ذخیرهٔ غیرمخرب: به‌روزرسانی/درج + حذف صرفاً ردیف‌های علامت‌خوردهٔ کاربر.
			$deleted_ids = array();
			if ( ! empty( $in['qa_deleted'] ) ) {
				$deleted_ids = array_filter( array_map( 'intval', explode( ',', (string) $in['qa_deleted'] ) ) );
			}
			SSC_Chatbot_DB::qa_save_bulk( $table_rows, $deleted_ids );
		}

		$msg = sprintf(
			/* translators: %d: تعداد ردیف‌های بانک. */
			__( 'بانک پاسخ‌ها ذخیره شد (%d ردیف).', 'smart-support-chatbot' ),
			SSC_Chatbot_DB::qa_count()
		);
		if ( $imported ) {
			$msg .= ' ' . sprintf(
				/* translators: %d: تعداد ردیف‌های واردشده از فایل. */
				__( '%d ردیف از فایل وارد شد.', 'smart-support-chatbot' ),
				$imported
			);
		}
		return $msg;
	}

	/**
	 * تجزیه فایل ایمپورت CSV یا JSON به آرایه ردیف‌های بانک.
	 *
	 * @param string $content محتوای فایل.
	 * @param string $fname   نام فایل.
	 * @return array
	 */
	protected function parse_qa_import( $content, $fname ) {
		$rows    = array();
		$content = trim( (string) $content );
		if ( '' === $content ) {
			return $rows;
		}

		$is_json = ( '[' === substr( $content, 0, 1 ) || '{' === substr( $content, 0, 1 ) || ( $fname && false !== stripos( $fname, '.json' ) ) );

		if ( $is_json ) {
			$data = json_decode( $content, true );
			if ( is_array( $data ) ) {
				foreach ( $data as $item ) {
					if ( ! is_array( $item ) || empty( $item['question'] ) || empty( $item['answer'] ) ) {
						continue;
					}
					$rows[] = array(
						'product'  => isset( $item['product'] ) ? sanitize_text_field( $item['product'] ) : 'general',
						'question' => sanitize_textarea_field( $item['question'] ),
						'keywords' => isset( $item['keywords'] ) ? sanitize_text_field( is_array( $item['keywords'] ) ? implode( '|', $item['keywords'] ) : $item['keywords'] ) : '',
						'answer'   => sanitize_textarea_field( $item['answer'] ),
					);
				}
			}
			return $rows;
		}

		// CSV: ستون‌ها product,question,keywords,answer (با سرستون).
		// نکته: تجزیه روی استریم انجام می‌شود نه خط‌به‌خط، چون پاسخ‌های چندخطیِ داخل
		// نقل‌قول ("...\n...") با تقسیم بر اساس خط می‌شکنند و ردیف خراب می‌شود.
		$stream = fopen( 'php://temp', 'r+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( false === $stream ) {
			return $rows;
		}
		fwrite( $stream, $content ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		rewind( $stream );

		$first = true;
		// phpcs:ignore WordPress.WP.AlternativeFunctions, Generic.CodeAnalysis.AssignmentInCondition -- الگوی متداول خواندن ردیف‌به‌ردیف CSV.
		while ( false !== ( $cols = fgetcsv( $stream ) ) ) {
			// ردیف خالی.
			if ( null === $cols || ( 1 === count( $cols ) && ( null === $cols[0] || '' === trim( (string) $cols[0] ) ) ) ) {
				continue;
			}
			if ( $first ) {
				$first = false;
				// رد کردن سرستون در صورت وجود.
				$joined = mb_strtolower( implode( ',', array_map( 'strval', $cols ) ) );
				if ( false !== strpos( $joined, 'question' ) || false !== strpos( $joined, 'سوال' ) ) {
					continue;
				}
			}
			if ( count( $cols ) < 4 ) {
				continue;
			}
			if ( '' === trim( (string) $cols[1] ) || '' === trim( (string) $cols[3] ) ) {
				continue;
			}
			$rows[] = array(
				'product'  => sanitize_text_field( $cols[0] ),
				'question' => sanitize_textarea_field( $cols[1] ),
				'keywords' => sanitize_text_field( $cols[2] ),
				'answer'   => sanitize_textarea_field( $cols[3] ),
			);
		}
		fclose( $stream ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		return $rows;
	}

	/**
	 * ساخت شناسهٔ پایدار محصول.
	 *
	 * تابع sanitize_key() هر کاراکتر غیرلاتین را حذف می‌کند، بنابراین شناسهٔ فارسی
	 * («کپسول») بی‌صدا به رشتهٔ خالی تبدیل و کل محصول دور ریخته می‌شد.
	 * اینجا ابتدا شناسهٔ واردشده و سپس نام محصول به slug یونیکد تبدیل می‌شود
	 * (sanitize_title حروف فارسی را حفظ می‌کند) و در نهایت یکتاسازی انجام می‌گیرد.
	 *
	 * @param string $raw_id ورودی خام شناسه.
	 * @param string $name   نام نمایشی محصول (به‌عنوان منبع جایگزین slug).
	 * @param array  $taken  شناسه‌های استفاده‌شده تا این لحظه.
	 * @return string شناسهٔ معتبر یا رشتهٔ خالی اگر هیچ منبعی موجود نباشد.
	 */
	protected static function make_product_id( $raw_id, $name, $taken ) {
		$raw_id = trim( (string) $raw_id );
		$name   = trim( (string) $name );

		// ۱) شناسهٔ لاتینِ معتبر همان‌طور که هست حفظ می‌شود (سازگاری با نصب‌های قبلی).
		$latin = sanitize_key( $raw_id );
		$id    = '' !== $latin ? $latin : '';

		// ۲) در غیر این صورت از خود شناسه و سپس از نام، slug یونیکد می‌سازیم.
		if ( '' === $id && '' !== $raw_id ) {
			$id = sanitize_title( $raw_id );
		}
		if ( '' === $id && '' !== $name ) {
			$id = sanitize_title( $name );
		}
		if ( '' === $id ) {
			return '';
		}

		// ۳) یکتاسازی تا دو محصول شناسهٔ یکسان نگیرند.
		$base = $id;
		$n    = 2;
		while ( isset( $taken[ $id ] ) ) {
			$id = $base . '-' . $n;
			++$n;
		}
		return $id;
	}

	/**
	 * ذخیره تنظیمات از فرم.
	 */
	protected function save_settings() {
		$in = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification -- بررسی شده در handle_actions.

		$fields_text     = array(
			'company_name',
			'company_id',
			'header_title',
			'company_btn_title',
			'company_btn_desc',
			'products_btn_title',
			'products_btn_desc',
			'adr_btn_title',
			'consult_btn_title',
			'position',
			'theme_mode',
			'ai_provider',
			'gemini_model',
			'openai_model',
			'claude_model',
			'custom_model',
			'notify_platform',
			'proactive_text',
			'online_text',
			'offline_text',
			'support_phone',
		);
		$fields_textarea = array( 'welcome_text', 'disclaimer', 'ai_system_prompt', 'ai_fallback_msg', 'welcome_title', 'handoff_text', 'consent_text' );
		$fields_raw      = array(
			'ai_webhook_url',
			'notify_chat_id',
			'email_to',
		);
		$fields_color    = array( 'primary_color', 'primary_hover' );
		$fields_toggle   = array(
			'enabled',
			'show_company',
			'show_products',
			'show_adr',
			'show_consult',
			'notify_enabled',
			'email_enabled',
			'ai_strict_knowledge',
			'ai_cache_enabled',
			'feedback_enabled',
			'typewriter_enabled',
			'proactive_enabled',
			'office_enabled',
			'suggestions_enabled',
			'autocomplete_enabled',
			'voice_enabled',
			'csat_enabled',
			'handoff_enabled',
			'consent_enabled',
		);

		$new = array();

		foreach ( $fields_text as $f ) {
			$new[ $f ] = isset( $in[ $f ] ) ? sanitize_text_field( $in[ $f ] ) : '';
		}
		foreach ( $fields_textarea as $f ) {
			$new[ $f ] = isset( $in[ $f ] ) ? wp_kses_post( $in[ $f ] ) : '';
		}
		foreach ( $fields_raw as $f ) {
			$new[ $f ] = isset( $in[ $f ] ) ? sanitize_text_field( $in[ $f ] ) : '';
		}
		foreach ( $fields_color as $f ) {
			$new[ $f ] = isset( $in[ $f ] ) ? sanitize_hex_color( $in[ $f ] ) : '';
		}
		foreach ( $fields_toggle as $f ) {
			$new[ $f ] = ( isset( $in[ $f ] ) && ( '1' === (string) $in[ $f ] || 'yes' === $in[ $f ] || 'on' === $in[ $f ] ) ) ? 'yes' : 'no';
		}

		$biz_mode                          = isset( $in['business_mode'] ) ? sanitize_text_field( $in['business_mode'] ) : 'general';
		$new['business_mode']              = in_array( $biz_mode, array( 'general', 'pharma' ), true ) ? $biz_mode : 'general';
		$new['email_to']                   = isset( $in['email_to'] ) ? sanitize_email( $in['email_to'] ) : '';
		$new['ai_rate_limit']              = isset( $in['ai_rate_limit'] ) ? max( 0, (int) $in['ai_rate_limit'] ) : 100;
		$rl_mode                           = isset( $in['rate_limit_mode'] ) ? sanitize_text_field( $in['rate_limit_mode'] ) : 'ip';
		$new['rate_limit_mode']            = in_array( $rl_mode, array( 'ip', 'session', 'both', 'off' ), true ) ? $rl_mode : 'ip';
		$new['session_rate_limit']         = isset( $in['session_rate_limit'] ) ? max( 0, (int) $in['session_rate_limit'] ) : 50;
		$new['submissions_retention_days'] = isset( $in['submissions_retention_days'] ) ? max( 0, min( 3650, (int) $in['submissions_retention_days'] ) ) : 0;
		$new['ai_history_limit']           = isset( $in['ai_history_limit'] ) ? max( 0, min( 20, (int) $in['ai_history_limit'] ) ) : 8;
		$new['ai_temperature']             = isset( $in['ai_temperature'] ) ? (string) max( 0, min( 1, (float) $in['ai_temperature'] ) ) : '0.4';
		$new['ai_max_tokens']              = isset( $in['ai_max_tokens'] ) ? max( 100, min( 4000, (int) $in['ai_max_tokens'] ) ) : 800;
		$new['ai_webhook_url']             = isset( $in['ai_webhook_url'] ) ? esc_url_raw( $in['ai_webhook_url'] ) : '';
		$new['custom_endpoint']            = isset( $in['custom_endpoint'] ) ? esc_url_raw( $in['custom_endpoint'] ) : '';
		$new['consent_link']               = isset( $in['consent_link'] ) ? esc_url_raw( $in['consent_link'] ) : '';

		// فیلدهای حساس: فقط در صورت ورود مقدار جدید، رمزنگاری و ذخیره می‌شوند (وگرنه مقدار قبلی حفظ می‌شود).
		foreach ( SSC_Chatbot_Settings::secret_fields() as $sf ) {
			if ( isset( $in[ $sf ] ) && '' !== trim( $in[ $sf ] ) ) {
				$new[ $sf ] = SSC_Chatbot_Settings::encrypt( sanitize_text_field( $in[ $sf ] ) );
			}
		}

		// آیکون شناور.
		$new['button_size']   = isset( $in['button_size'] ) ? max( 40, min( 120, (int) $in['button_size'] ) ) : 60;
		$new['icon_size']     = isset( $in['icon_size'] ) ? max( 16, min( 80, (int) $in['icon_size'] ) ) : 28;
		$new['button_radius'] = isset( $in['button_radius'] ) ? max( 0, min( 50, (int) $in['button_radius'] ) ) : 50;

		// فونت و استایل پنجرهٔ چت.
		$font_family              = isset( $in['font_family'] ) ? sanitize_text_field( $in['font_family'] ) : 'vazirmatn';
		$new['font_family']       = in_array( $font_family, array( 'vazirmatn', 'system', 'inter', 'roboto', 'custom' ), true ) ? $font_family : 'vazirmatn';
		$new['font_name']         = isset( $in['font_name'] ) ? sanitize_text_field( $in['font_name'] ) : '';
		$new['font_url']          = isset( $in['font_url'] ) ? esc_url_raw( $in['font_url'] ) : '';
		$new['font_size']         = isset( $in['font_size'] ) ? max( 10, min( 24, (int) $in['font_size'] ) ) : 14;
		$new['window_width']      = isset( $in['window_width'] ) ? max( 300, min( 520, (int) $in['window_width'] ) ) : 384;
		$new['window_radius']     = isset( $in['window_radius'] ) ? max( 0, min( 40, (int) $in['window_radius'] ) ) : 24;
		$new['bubble_radius']     = isset( $in['bubble_radius'] ) ? max( 0, min( 30, (int) $in['bubble_radius'] ) ) : 16;
		$new['user_bubble_color'] = isset( $in['user_bubble_color'] ) ? (string) sanitize_hex_color( $in['user_bubble_color'] ) : '';
		$new['bot_bubble_color']  = isset( $in['bot_bubble_color'] ) ? (string) sanitize_hex_color( $in['bot_bubble_color'] ) : '';

		// تجربه کاربری / ساعات کاری.
		$new['proactive_delay'] = isset( $in['proactive_delay'] ) ? max( 2, min( 120, (int) $in['proactive_delay'] ) ) : 12;
		$new['office_start']    = isset( $in['office_start'] ) ? max( 0, min( 23, (int) $in['office_start'] ) ) : 8;
		$new['office_end']      = isset( $in['office_end'] ) ? max( 1, min( 24, (int) $in['office_end'] ) ) : 16;
		$new['office_days']     = ( isset( $in['office_days'] ) && is_array( $in['office_days'] ) )
			? array_values( array_map( 'intval', $in['office_days'] ) )
			: array();
		$new['button_icon_url'] = isset( $in['button_icon_url'] ) ? esc_url_raw( $in['button_icon_url'] ) : '';

		// محصولات.
		$products = array();
		if ( isset( $in['product_id'] ) && is_array( $in['product_id'] ) ) {
			$ids           = $in['product_id'];
			$names         = isset( $in['product_name'] ) ? $in['product_name'] : array();
			$know          = isset( $in['product_knowledge'] ) ? $in['product_knowledge'] : array();
			$brochures     = isset( $in['product_brochure'] ) ? $in['product_brochure'] : array();
			$images        = isset( $in['product_image'] ) ? $in['product_image'] : array();
			$knowledge_map = array();
			$seen_ids      = array();
			foreach ( $ids as $i => $pid ) {
				$pname = isset( $names[ $i ] ) ? sanitize_text_field( $names[ $i ] ) : '';
				$pid   = self::make_product_id( $pid, $pname, $seen_ids );
				if ( '' === $pid ) {
					continue;
				}
				$seen_ids[ $pid ] = true;
				if ( '' === $pname ) {
					$pname = $pid;
				}
				$brochure   = isset( $brochures[ $i ] ) ? esc_url_raw( trim( $brochures[ $i ] ) ) : '';
				$image      = isset( $images[ $i ] ) ? esc_url_raw( trim( $images[ $i ] ) ) : '';
				$products[] = array(
					'id' => $pid,
					'name' => $pname,
					'brochure' => $brochure,
					'image' => $image,
				);
				if ( isset( $know[ $i ] ) && '' !== trim( $know[ $i ] ) ) {
					$knowledge_map[ $pid ] = sanitize_textarea_field( $know[ $i ] );
				}
			}
			$new['product_knowledge'] = $knowledge_map;
		}
		if ( ! empty( $products ) ) {
			$new['products'] = $products;
		}

		// پاسخ‌های پیشنهادی.
		$new['quick_replies_enabled'] = ( isset( $in['quick_replies_enabled'] ) && ( '1' === (string) $in['quick_replies_enabled'] || 'yes' === $in['quick_replies_enabled'] || 'on' === $in['quick_replies_enabled'] ) ) ? 'yes' : 'no';
		$quick                        = array();
		if ( isset( $in['quick_reply_label'] ) && is_array( $in['quick_reply_label'] ) ) {
			$labels    = $in['quick_reply_label'];
			$questions = isset( $in['quick_reply_question'] ) ? $in['quick_reply_question'] : array();
			foreach ( $labels as $i => $label ) {
				$label = sanitize_text_field( $label );
				$q     = isset( $questions[ $i ] ) ? sanitize_text_field( $questions[ $i ] ) : '';
				if ( '' === $label || '' === $q ) {
					continue;
				}
				$quick[] = array(
					'label' => $label,
					'question' => $q,
				);
			}
			$new['quick_replies'] = $quick;
		}

		SSC_Chatbot_Settings::update( $new );
	}

	/**
	 * رندر صفحه تنظیمات.
	 */
	public function render_settings_page() {
		$s = SSC_Chatbot_Settings::all();
		require SSC_CHATBOT_DIR . 'includes/views/settings-page.php';
	}

	/**
	 * رندر صفحه درخواست‌ها.
	 */
	public function render_submissions_page() {
		// فیلترهای فقط-خواندنی صفحه لیست (بدون تغییر وضعیت)؛ مطابق الگوی WP_List_Table نیازی به nonce نیست.
		$type      = isset( $_GET['type'] ) ? sanitize_text_field( wp_unslash( $_GET['type'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$status    = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$search    = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$date_from = isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$date_to   = isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$paged     = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$result = SSC_Chatbot_DB::get_submissions(
			array(
				'type'      => $type,
				'status'    => $status,
				'search'    => $search,
				'date_from' => $date_from,
				'date_to'   => $date_to,
				'page'      => $paged,
				'per_page'  => 20,
			)
		);
		$counts = SSC_Chatbot_DB::counts();

		require SSC_CHATBOT_DIR . 'includes/views/submissions-page.php';
	}

	/**
	 * خنثی‌سازی تزریق فرمول در CSV: هر سلولی که با =، +، -، @، Tab یا CR شروع شود،
	 * با یک آپاستروف ابتدایی بی‌اثر می‌شود (طبق راهنمای OWASP) تا در Excel/LibreOffice اجرا نشود.
	 *
	 * @param mixed $v مقدار سلول.
	 * @return string
	 */
	private static function csv_safe( $v ) {
		$v = (string) $v;
		return preg_match( '/^[=+\-@\t\r]/', $v ) ? "'" . $v : $v;
	}

	/**
	 * خروجی CSV درخواست‌ها.
	 */
	public function export_csv() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'دسترسی غیرمجاز', 'smart-support-chatbot' ) );
		}
		check_admin_referer( 'ssc_export' );

		// خروجی بر اساس فیلترهای فعلی صفحه (نوع/وضعیت/جستجو/بازهٔ تاریخ).
		$filters    = array(
			'type'      => isset( $_GET['type'] ) ? sanitize_text_field( wp_unslash( $_GET['type'] ) ) : '',
			'status'    => isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '',
			'search'    => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '',
			'date_from' => isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '',
			'date_to'   => isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '',
		);
		$has_filter = (bool) array_filter( $filters );
		$rows       = $has_filter ? SSC_Chatbot_DB::get_filtered_for_export( $filters ) : SSC_Chatbot_DB::get_all_for_export();

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=ssc-submissions-' . gmdate( 'Y-m-d' ) . '.csv' );

		$out = fopen( 'php://output', 'w' );
		// BOM برای پشتیبانی فارسی در اکسل.
		fprintf( $out, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) );
		fputcsv(
			$out,
			array(
				'شناسه',
				'نوع',
				'نام',
				'تلفن',
				'محصول',
				'شرح',
				'نوع گزارش‌دهنده',
				'شدت',
				'پیامد',
				'شماره سری ساخت',
				'داروهای همزمان',
				'وضعیت',
				'IP',
				'تاریخ',
			)
		);
		foreach ( $rows as $r ) {
			$cells = array(
				$r['id'],
				$r['type'],
				$r['name'],
				$r['phone'],
				$r['product'],
				$r['description'],
				isset( $r['reporter_type'] ) ? $r['reporter_type'] : '',
				isset( $r['severity'] ) ? $r['severity'] : '',
				isset( $r['outcome'] ) ? $r['outcome'] : '',
				isset( $r['batch_number'] ) ? $r['batch_number'] : '',
				isset( $r['concomitant_drugs'] ) ? $r['concomitant_drugs'] : '',
				$r['status'],
				$r['ip'],
				$r['created_at'],
			);
			fputcsv( $out, array_map( array( __CLASS__, 'csv_safe' ), $cells ) );
		}
		fclose( $out ); // phpcs:ignore
		exit;
	}
}
