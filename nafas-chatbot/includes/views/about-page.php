<?php
/**
 * نمای صفحه «درباره و توسعه‌دهندگان».
 *
 * @package NafasChatbot
 * @var array $insights آمار سریع (qa, kb, chat).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$ins = isset( $insights ) ? $insights : array( 'qa' => 0, 'kb' => 0, 'chat' => 0 );
?>
<div class="wrap nafas-admin nafas-about" dir="rtl">

	<!-- قهرمان -->
	<div class="nafas-credit nafas-credit--hero">
		<span class="nafas-credit__orb nafas-credit__orb--a"></span>
		<span class="nafas-credit__orb nafas-credit__orb--b"></span>
		<div class="nafas-about__hero">
			<span class="nafas-credit__spark dashicons dashicons-superhero-alt"></span>
			<h1 class="nafas-about__title">
				<?php
				/* translators: %1$s و %2$s نام توسعه‌دهندگان. */
				printf( esc_html__( 'توسعه با همکاری %1$s و %2$s', 'nafas-chatbot' ), '<b dir="ltr">Claude</b>', '<b>' . esc_html__( 'سعید', 'nafas-chatbot' ) . '</b>' ); // phpcs:ignore WordPress.Security.EscapeOutput
				?>
			</h1>
			<p class="nafas-about__tagline"><?php esc_html_e( 'دستیار هوشمند گفتگو — ساخته‌شده با وسواس، برای شرایط واقعی ایران ❤️', 'nafas-chatbot' ); ?></p>
			<div class="nafas-credit__contacts">
				<a class="nafas-credit__chip" href="tel:09301221816" dir="ltr"><span class="dashicons dashicons-phone"></span> 0930 122 1816</a>
				<a class="nafas-credit__chip" href="mailto:dbsgraphic.ir@gmail.com" dir="ltr"><span class="dashicons dashicons-email-alt"></span> dbsgraphic.ir@gmail.com</a>
				<a class="nafas-credit__chip" href="https://dbsgraphic.ir" target="_blank" rel="noopener noreferrer" dir="ltr"><span class="dashicons dashicons-admin-site-alt3"></span> dbsgraphic.ir</a>
			</div>
		</div>
	</div>

	<!-- آمار زنده -->
	<div class="nafas-about__stats">
		<div class="nafas-about__stat"><span><?php echo esc_html( number_format_i18n( $ins['chat'] ) ); ?></span><small><?php esc_html_e( 'گفتگوی پاسخ‌داده‌شده', 'nafas-chatbot' ); ?></small></div>
		<div class="nafas-about__stat"><span><?php echo esc_html( number_format_i18n( $ins['qa'] ) ); ?></span><small><?php esc_html_e( 'پاسخ در بانک', 'nafas-chatbot' ); ?></small></div>
		<div class="nafas-about__stat"><span><?php echo esc_html( number_format_i18n( $ins['kb'] ) ); ?></span><small><?php esc_html_e( 'تکهٔ دانش', 'nafas-chatbot' ); ?></small></div>
		<div class="nafas-about__stat"><span dir="ltr">v<?php echo esc_html( NAFAS_CHATBOT_VERSION ); ?></span><small><?php esc_html_e( 'نسخهٔ فعلی', 'nafas-chatbot' ); ?></small></div>
	</div>

	<div class="nafas-dash-cols">
		<!-- این دستیار چه می‌کند -->
		<div class="nafas-card">
			<h3 class="nafas-card__title"><span class="dashicons dashicons-superhero"></span> <?php esc_html_e( 'این دستیار چه کارهایی بلد است؟', 'nafas-chatbot' ); ?></h3>
			<ul class="nafas-about__list">
				<li>🧠 <b><?php esc_html_e( 'سه مغز در یک بدن:', 'nafas-chatbot' ); ?></b> <?php esc_html_e( 'هوش مصنوعی + پایگاه دانش هیبریدی + بانک پاسخ آفلاین؛ اگر یکی نبود، بعدی جواب می‌دهد.', 'nafas-chatbot' ); ?></li>
				<li>📚 <b><?php esc_html_e( 'پایگاه دانش (RAG آفلاین):', 'nafas-chatbot' ); ?></b> <?php esc_html_e( 'بروشورها را می‌خورد، تکه‌تکه می‌کند و فقط تکهٔ مرتبط را به مدل می‌دهد.', 'nafas-chatbot' ); ?></li>
				<li>🎙️ <b><?php esc_html_e( 'حالت صوتی:', 'nafas-chatbot' ); ?></b> <?php esc_html_e( 'پرسیدن با میکروفون و شنیدن پاسخ — کاملاً سمت مرورگر و رایگان.', 'nafas-chatbot' ); ?></li>
				<li>🚨 <b><?php esc_html_e( 'حالت داروسازی (اختیاری):', 'nafas-chatbot' ); ?></b> <?php esc_html_e( 'فرم استاندارد عوارض دارویی + هشدار فوری عوارض جدی — قابل فعال‌سازی برای کسب‌وکارهای دارویی.', 'nafas-chatbot' ); ?></li>
				<li>💡 <b><?php esc_html_e( 'هوشمندی گفتگو:', 'nafas-chatbot' ); ?></b> <?php esc_html_e( 'پیشنهاد سوال مرتبط، تکمیل خودکار، کارت محصول، نظرسنجی رضایت و واگذاری به کارشناس.', 'nafas-chatbot' ); ?></li>
				<li>📊 <b><?php esc_html_e( 'داشبورد و رادار:', 'nafas-chatbot' ); ?></b> <?php esc_html_e( 'سوالات بی‌پاسخ، بازخوردها و آمار — برای بهبود مستمر.', 'nafas-chatbot' ); ?></li>
			</ul>
		</div>

		<!-- پشت صحنه -->
		<div class="nafas-card">
			<h3 class="nafas-card__title"><span class="dashicons dashicons-admin-tools"></span> <?php esc_html_e( 'پشت صحنه (سختی‌هایی که کشیدیم 😅)', 'nafas-chatbot' ); ?></h3>
			<ul class="nafas-about__list">
				<li>🚧 <b><?php esc_html_e( 'تحریم و اینترنت کُند:', 'nafas-chatbot' ); ?></b> <?php esc_html_e( 'به‌جای سرویس‌های خارجی، همه‌چیز را آفلاین و مقاوم ساختیم؛ مهلت پاسخ ۶۰ ثانیه ماند تا قطع نشود.', 'nafas-chatbot' ); ?></li>
				<li>🧩 <b><?php esc_html_e( 'بدون embeddings:', 'nafas-chatbot' ); ?></b> <?php esc_html_e( 'موتور تطبیق فارسی با نرمال‌سازی ی/ک، مترادف‌ها و FULLTEXT ساختیم تا «معنا» را بدون سرویس برداری بفهمد.', 'nafas-chatbot' ); ?></li>
				<li>🔤 <b><?php esc_html_e( 'فارسیِ راست‌چین:', 'nafas-chatbot' ); ?></b> <?php esc_html_e( 'از نیم‌فاصله تا جهت‌دهی متن و ایموجی، همه با وسواس تنظیم شد.', 'nafas-chatbot' ); ?></li>
				<li>🔐 <b><?php esc_html_e( 'امنیت:', 'nafas-chatbot' ); ?></b> <?php esc_html_e( 'رمزنگاری کلیدها، ضداسپم آفلاین (Honeypot)، nonce و محدودیت استفاده بر اساس IP/نشست.', 'nafas-chatbot' ); ?></li>
				<li>🎨 <b><?php esc_html_e( 'هزار بار بازطراحی:', 'nafas-chatbot' ); ?></b> <?php esc_html_e( 'از رنگ دکمه تا نقطهٔ سبز آنلاین و کارت محصول — تا «خوشگل و مینیمال» شد.', 'nafas-chatbot' ); ?></li>
			</ul>
		</div>
	</div>

	<!-- ساخته‌شده با -->
	<div class="nafas-card">
		<h3 class="nafas-card__title"><span class="dashicons dashicons-editor-code"></span> <?php esc_html_e( 'ساخته‌شده با', 'nafas-chatbot' ); ?></h3>
		<div class="nafas-about__tags">
			<span>WordPress</span><span>Elementor</span><span>Vanilla JS</span><span>PHP</span>
			<span>MySQL FULLTEXT</span><span>Web Speech API</span><span>Gemini / OpenAI / Claude</span><span>RTL / فارسی</span>
		</div>
		<p class="description" style="margin-top:14px">
			<?php esc_html_e( 'یک دستیار هوشمند گفتگو و پشتیبانی برای هر کسب‌وکار وردپرسی. برای سفارش پروژهٔ مشابه، طراحی، یا پشتیبانی با راه‌های بالا در تماس باشید.', 'nafas-chatbot' ); ?>
		</p>
	</div>
</div>
