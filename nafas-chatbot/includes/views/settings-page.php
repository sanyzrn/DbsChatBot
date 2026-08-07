<?php
/**
 * نمای صفحه تنظیمات.
 *
 * @package NafasChatbot
 * @var array $s تنظیمات.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * placeholder فیلد حساس: اگر مقداری ذخیره شده، به‌جای نمایش کلید، وضعیت «ذخیره‌شده» را نشان می‌دهد.
 *
 * @param string $key کلید.
 * @return string
 */
$secret_ph = function ( $key ) {
	return Nafas_Chatbot_Settings::has_secret( $key )
		? '•••••••••• (ذخیره‌شده — برای تغییر، مقدار جدید وارد کنید)'
		: 'وارد کنید...';
};
?>
<div class="wrap nafas-admin" dir="rtl">
	<h1 class="nafas-admin__title">
		<span class="dashicons dashicons-format-chat"></span>
		<?php esc_html_e( 'دستیار هوشمند گفتگو', 'nafas-chatbot' ); ?>
		<span class="nafas-admin__ver">v<?php echo esc_html( NAFAS_CHATBOT_VERSION ); ?></span>
	</h1>

	<form method="post" action="" class="nafas-settings-form">
		<?php wp_nonce_field( 'nafas_chatbot_settings' ); ?>

		<div class="nafas-tabs">
			<nav class="nafas-tabs__nav">
				<a href="#tab-general" class="nafas-tab is-active"><?php esc_html_e( 'عمومی', 'nafas-chatbot' ); ?></a>
				<a href="#tab-menu" class="nafas-tab"><?php esc_html_e( 'منو و متن‌ها', 'nafas-chatbot' ); ?></a>
				<a href="#tab-products" class="nafas-tab"><?php esc_html_e( 'محصولات', 'nafas-chatbot' ); ?></a>
				<a href="#tab-appearance" class="nafas-tab"><?php esc_html_e( 'ظاهر', 'nafas-chatbot' ); ?></a>
				<a href="#tab-ai" class="nafas-tab"><?php esc_html_e( 'هوش مصنوعی', 'nafas-chatbot' ); ?></a>
				<a href="#tab-advanced" class="nafas-tab"><?php esc_html_e( 'تجربه کاربری', 'nafas-chatbot' ); ?></a>
				<a href="#tab-notify" class="nafas-tab"><?php esc_html_e( 'اعلان‌ها', 'nafas-chatbot' ); ?></a>
			</nav>

			<!-- عمومی -->
			<div id="tab-general" class="nafas-tab-panel is-active">
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'فعال‌سازی دکمه شناور', 'nafas-chatbot' ); ?></th>
						<td>
							<label class="nafas-switch">
								<input type="checkbox" name="enabled" value="yes" <?php checked( $s['enabled'], 'yes' ); ?>>
								<span class="nafas-switch__slider"></span>
							</label>
							<p class="description"><?php esc_html_e( 'نمایش خودکار دکمه چت‌بات در تمام صفحات سایت.', 'nafas-chatbot' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="business_mode"><?php esc_html_e( 'حالت کسب‌وکار', 'nafas-chatbot' ); ?></label></th>
						<td>
							<select name="business_mode" id="business_mode">
								<option value="general" <?php selected( $s['business_mode'], 'general' ); ?>><?php esc_html_e( 'عمومی (هر کسب‌وکاری)', 'nafas-chatbot' ); ?></option>
								<option value="pharma" <?php selected( $s['business_mode'], 'pharma' ); ?>><?php esc_html_e( 'داروسازی (فرم استاندارد عوارض دارویی)', 'nafas-chatbot' ); ?></option>
							</select>
							<p class="description"><?php esc_html_e( 'در حالت «داروسازی»، فیلدهای استاندارد گزارش عوارض دارویی (شدت، پیامد، شماره سری ساخت و…) در فرم نمایش داده می‌شوند. در حالت «عمومی» فرم ساده و بدون فیلدهای دارویی است.', 'nafas-chatbot' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="company_name"><?php esc_html_e( 'نام شرکت / کسب‌وکار', 'nafas-chatbot' ); ?></label></th>
						<td>
							<input type="text" id="company_name" name="company_name" value="<?php echo esc_attr( $s['company_name'] ); ?>" class="regular-text" placeholder="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
							<p class="description"><?php esc_html_e( 'در صورت خالی بودن، از نام سایت استفاده می‌شود.', 'nafas-chatbot' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="company_id"><?php esc_html_e( 'شناسه شرکت', 'nafas-chatbot' ); ?></label></th>
						<td>
							<input type="text" id="company_id" name="company_id" value="<?php echo esc_attr( $s['company_id'] ); ?>" class="regular-text" dir="ltr">
							<p class="description"><?php esc_html_e( 'یک شناسه انگلیسی یکتا برای حالت گفتگو درباره شرکت.', 'nafas-chatbot' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="header_title"><?php esc_html_e( 'عنوان هدر پیش‌فرض', 'nafas-chatbot' ); ?></label></th>
						<td><input type="text" id="header_title" name="header_title" value="<?php echo esc_attr( $s['header_title'] ); ?>" class="regular-text"></td>
					</tr>
					<tr>
						<th><label for="support_phone"><?php esc_html_e( 'شماره تماس پشتیبانی', 'nafas-chatbot' ); ?></label></th>
						<td>
							<input type="text" id="support_phone" name="support_phone" value="<?php echo esc_attr( $s['support_phone'] ); ?>" class="regular-text" dir="ltr" placeholder="021...">
							<p class="description"><?php esc_html_e( 'در صورت تکمیل، دکمهٔ «تماس با ما» زیر پاسخ‌های چت‌بات نمایش داده می‌شود.', 'nafas-chatbot' ); ?></p>
						</td>
					</tr>
					<tr><th colspan="2"><h3 class="nafas-section"><?php esc_html_e( 'محدودیت استفاده', 'nafas-chatbot' ); ?></h3></th></tr>
					<tr>
						<th><label for="rate_limit_mode"><?php esc_html_e( 'روش محدودسازی', 'nafas-chatbot' ); ?></label></th>
						<td>
							<select name="rate_limit_mode" id="rate_limit_mode">
								<option value="ip" <?php selected( $s['rate_limit_mode'], 'ip' ); ?>><?php esc_html_e( 'بر اساس IP (مناسب سایت عمومی)', 'nafas-chatbot' ); ?></option>
								<option value="session" <?php selected( $s['rate_limit_mode'], 'session' ); ?>><?php esc_html_e( 'بر اساس نشست/مرورگر (مناسب IP مشترک شرکت)', 'nafas-chatbot' ); ?></option>
								<option value="both" <?php selected( $s['rate_limit_mode'], 'both' ); ?>><?php esc_html_e( 'ترکیبی: هم نشست هم IP (پیشنهادی)', 'nafas-chatbot' ); ?></option>
								<option value="off" <?php selected( $s['rate_limit_mode'], 'off' ); ?>><?php esc_html_e( 'نامحدود (خاموش)', 'nafas-chatbot' ); ?></option>
							</select>
							<p class="description">
								<?php esc_html_e( 'حالت «نشست» به هر مرورگر سهمیهٔ جداگانه می‌دهد؛ برای شرکت‌هایی که همه پشت یک IP مشترک هستند مناسب است. حالت «ترکیبی» سهمیهٔ منصفانهٔ نشست را با یک سقف IP برای جلوگیری از سوءاستفاده ترکیب می‌کند.', 'nafas-chatbot' ); ?>
							</p>
						</td>
					</tr>
					<tr class="nafas-rl-ip">
						<th><label for="ai_rate_limit"><?php esc_html_e( 'محدودیت روزانه هر IP', 'nafas-chatbot' ); ?></label></th>
						<td>
							<input type="number" id="ai_rate_limit" name="ai_rate_limit" value="<?php echo esc_attr( $s['ai_rate_limit'] ); ?>" min="0" class="small-text">
							<p class="description"><?php esc_html_e( 'حداکثر درخواست در روز برای هر IP. صفر = بدون سقف IP.', 'nafas-chatbot' ); ?></p>
						</td>
					</tr>
					<tr class="nafas-rl-session">
						<th><label for="session_rate_limit"><?php esc_html_e( 'محدودیت روزانه هر نشست', 'nafas-chatbot' ); ?></label></th>
						<td>
							<input type="number" id="session_rate_limit" name="session_rate_limit" value="<?php echo esc_attr( $s['session_rate_limit'] ); ?>" min="0" class="small-text">
							<p class="description"><?php esc_html_e( 'حداکثر درخواست در روز برای هر مرورگر. صفر = بدون سقف نشست.', 'nafas-chatbot' ); ?></p>
						</td>
					</tr>
				</table>
			</div>

			<!-- منو -->
			<div id="tab-menu" class="nafas-tab-panel">
				<table class="form-table">
					<tr>
						<th><label for="welcome_title"><?php esc_html_e( 'عنوان خوش‌آمد', 'nafas-chatbot' ); ?></label></th>
						<td><input type="text" id="welcome_title" name="welcome_title" value="<?php echo esc_attr( $s['welcome_title'] ); ?>" class="regular-text"></td>
					</tr>
					<tr>
						<th><label for="welcome_text"><?php esc_html_e( 'متن خوش‌آمد', 'nafas-chatbot' ); ?></label></th>
						<td><textarea id="welcome_text" name="welcome_text" rows="2" class="large-text"><?php echo esc_textarea( $s['welcome_text'] ); ?></textarea>
						<p class="description"><?php esc_html_e( 'می‌توانید از تگ <br> استفاده کنید.', 'nafas-chatbot' ); ?></p></td>
					</tr>
					<tr><th colspan="2"><h3 class="nafas-section"><?php esc_html_e( 'گزینه‌های منو', 'nafas-chatbot' ); ?></h3></th></tr>

					<tr>
						<th><?php esc_html_e( 'سوال درباره شرکت', 'nafas-chatbot' ); ?></th>
						<td>
							<label class="nafas-switch"><input type="checkbox" name="show_company" value="yes" <?php checked( $s['show_company'], 'yes' ); ?>><span class="nafas-switch__slider"></span></label>
							<input type="text" name="company_btn_title" value="<?php echo esc_attr( $s['company_btn_title'] ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'عنوان', 'nafas-chatbot' ); ?>">
							<input type="text" name="company_btn_desc" value="<?php echo esc_attr( $s['company_btn_desc'] ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'توضیح کوتاه', 'nafas-chatbot' ); ?>">
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'سوال درباره محصولات', 'nafas-chatbot' ); ?></th>
						<td>
							<label class="nafas-switch"><input type="checkbox" name="show_products" value="yes" <?php checked( $s['show_products'], 'yes' ); ?>><span class="nafas-switch__slider"></span></label>
							<input type="text" name="products_btn_title" value="<?php echo esc_attr( $s['products_btn_title'] ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'عنوان', 'nafas-chatbot' ); ?>">
							<input type="text" name="products_btn_desc" value="<?php echo esc_attr( $s['products_btn_desc'] ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'توضیح کوتاه', 'nafas-chatbot' ); ?>">
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'ثبت عوارض', 'nafas-chatbot' ); ?></th>
						<td>
							<label class="nafas-switch"><input type="checkbox" name="show_adr" value="yes" <?php checked( $s['show_adr'], 'yes' ); ?>><span class="nafas-switch__slider"></span></label>
							<input type="text" name="adr_btn_title" value="<?php echo esc_attr( $s['adr_btn_title'] ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'عنوان', 'nafas-chatbot' ); ?>">
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'درخواست مشاوره', 'nafas-chatbot' ); ?></th>
						<td>
							<label class="nafas-switch"><input type="checkbox" name="show_consult" value="yes" <?php checked( $s['show_consult'], 'yes' ); ?>><span class="nafas-switch__slider"></span></label>
							<input type="text" name="consult_btn_title" value="<?php echo esc_attr( $s['consult_btn_title'] ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'عنوان', 'nafas-chatbot' ); ?>">
						</td>
					</tr>
					<tr>
						<th><label for="disclaimer"><?php esc_html_e( 'متن سلب مسئولیت', 'nafas-chatbot' ); ?></label></th>
						<td><input type="text" id="disclaimer" name="disclaimer" value="<?php echo esc_attr( $s['disclaimer'] ); ?>" class="large-text"></td>
					</tr>
				</table>
			</div>

			<!-- محصولات -->
			<div id="tab-products" class="nafas-tab-panel">
				<h3 class="nafas-section"><?php esc_html_e( 'مدیریت محصولات', 'nafas-chatbot' ); ?></h3>
				<p class="description"><?php esc_html_e( 'محصولاتی که در منوی چت‌بات و فرم گزارش عوارض نمایش داده می‌شوند. می‌توانید برای هر محصول یک «پایگاه دانش» وارد کنید تا هوش مصنوعی بر اساس آن پاسخ دهد.', 'nafas-chatbot' ); ?></p>

				<table class="nafas-products-table widefat" id="nafas-products">
					<thead>
						<tr>
							<th style="width:130px"><?php esc_html_e( 'شناسه (انگلیسی)', 'nafas-chatbot' ); ?></th>
							<th style="width:150px"><?php esc_html_e( 'نام نمایشی', 'nafas-chatbot' ); ?></th>
							<th><?php esc_html_e( 'پایگاه دانش (اختیاری)', 'nafas-chatbot' ); ?></th>
							<th style="width:180px"><?php esc_html_e( 'تصویر محصول (URL)', 'nafas-chatbot' ); ?></th>
							<th style="width:180px"><?php esc_html_e( 'لینک بروشور', 'nafas-chatbot' ); ?></th>
							<th style="width:40px"></th>
						</tr>
					</thead>
					<tbody>
						<?php
						$products  = (array) $s['products'];
						$knowledge = (array) $s['product_knowledge'];
						foreach ( $products as $p ) :
							$pid   = isset( $p['id'] ) ? $p['id'] : '';
							$pname = isset( $p['name'] ) ? $p['name'] : '';
							$pbr   = isset( $p['brochure'] ) ? $p['brochure'] : '';
							$pimg  = isset( $p['image'] ) ? $p['image'] : '';
							$pk    = isset( $knowledge[ $pid ] ) ? $knowledge[ $pid ] : '';
							?>
							<tr class="nafas-product-row">
								<td><input type="text" name="product_id[]" value="<?php echo esc_attr( $pid ); ?>" dir="ltr" class="widefat"></td>
								<td><input type="text" name="product_name[]" value="<?php echo esc_attr( $pname ); ?>" class="widefat"></td>
								<td><textarea name="product_knowledge[]" rows="2" class="widefat"><?php echo esc_textarea( $pk ); ?></textarea></td>
								<td><input type="url" name="product_image[]" value="<?php echo esc_attr( $pimg ); ?>" dir="ltr" class="widefat" placeholder="https://..."></td>
								<td><input type="url" name="product_brochure[]" value="<?php echo esc_attr( $pbr ); ?>" dir="ltr" class="widefat" placeholder="https://..."></td>
								<td><button type="button" class="button nafas-remove-product">&times;</button></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<p><button type="button" class="button button-secondary" id="nafas-add-product"><?php esc_html_e( '+ افزودن محصول', 'nafas-chatbot' ); ?></button></p>

				<h3 class="nafas-section" style="margin-top:32px"><?php esc_html_e( 'پاسخ‌های پیشنهادی (Quick Replies)', 'nafas-chatbot' ); ?></h3>
				<p class="description"><?php esc_html_e( 'دکمه‌های پیشنهادی که هنگام گفتگو درباره یک محصول نمایش داده می‌شوند. کاربر با یک کلیک، سوال آماده را می‌پرسد. دکمه «بروشور» در صورت تنظیم لینک، به‌صورت خودکار به محصول اضافه می‌شود.', 'nafas-chatbot' ); ?></p>

				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'فعال‌سازی', 'nafas-chatbot' ); ?></th>
						<td><label class="nafas-switch"><input type="checkbox" name="quick_replies_enabled" value="yes" <?php checked( $s['quick_replies_enabled'], 'yes' ); ?>><span class="nafas-switch__slider"></span></label></td>
					</tr>
				</table>

				<table class="nafas-products-table widefat" id="nafas-quick-replies">
					<thead>
						<tr>
							<th style="width:200px"><?php esc_html_e( 'برچسب دکمه', 'nafas-chatbot' ); ?></th>
							<th><?php esc_html_e( 'سوالی که ارسال می‌شود', 'nafas-chatbot' ); ?></th>
							<th style="width:40px"></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( (array) $s['quick_replies'] as $qr ) : ?>
							<tr class="nafas-quick-row">
								<td><input type="text" name="quick_reply_label[]" value="<?php echo esc_attr( isset( $qr['label'] ) ? $qr['label'] : '' ); ?>" class="widefat"></td>
								<td><input type="text" name="quick_reply_question[]" value="<?php echo esc_attr( isset( $qr['question'] ) ? $qr['question'] : '' ); ?>" class="widefat"></td>
								<td><button type="button" class="button nafas-remove-quick">&times;</button></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<p><button type="button" class="button button-secondary" id="nafas-add-quick"><?php esc_html_e( '+ افزودن پاسخ پیشنهادی', 'nafas-chatbot' ); ?></button></p>
			</div>

			<!-- ظاهر -->
			<div id="tab-appearance" class="nafas-tab-panel">
				<table class="form-table">
					<tr>
						<th><label for="position"><?php esc_html_e( 'موقعیت دکمه', 'nafas-chatbot' ); ?></label></th>
						<td>
							<select name="position" id="position">
								<option value="right" <?php selected( $s['position'], 'right' ); ?>><?php esc_html_e( 'پایین راست', 'nafas-chatbot' ); ?></option>
								<option value="left" <?php selected( $s['position'], 'left' ); ?>><?php esc_html_e( 'پایین چپ', 'nafas-chatbot' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th><label for="primary_color"><?php esc_html_e( 'رنگ اصلی', 'nafas-chatbot' ); ?></label></th>
						<td><input type="text" id="primary_color" name="primary_color" value="<?php echo esc_attr( $s['primary_color'] ); ?>" class="nafas-color-picker" data-default-color="#b61615"></td>
					</tr>
					<tr>
						<th><label for="primary_hover"><?php esc_html_e( 'رنگ اصلی (هاور)', 'nafas-chatbot' ); ?></label></th>
						<td><input type="text" id="primary_hover" name="primary_hover" value="<?php echo esc_attr( $s['primary_hover'] ); ?>" class="nafas-color-picker" data-default-color="#991211"></td>
						</tr>
						<tr><th colspan="2"><h3 class="nafas-section" style="margin:8px 0 0"><?php esc_html_e( 'آیکون شناور', 'nafas-chatbot' ); ?></h3></th></tr>
						<tr>
							<th><label for="button_size"><?php esc_html_e( 'اندازه دکمه (پیکسل)', 'nafas-chatbot' ); ?></label></th>
							<td>
								<input type="number" id="button_size" name="button_size" value="<?php echo esc_attr( $s['button_size'] ); ?>" min="40" max="120" class="small-text">
								<p class="description"><?php esc_html_e( 'قطر دکمه گرد شناور. پیش‌فرض: ۶۰', 'nafas-chatbot' ); ?></p>
							</td>
						</tr>
						<tr>
							<th><label for="icon_size"><?php esc_html_e( 'اندازه آیکون (پیکسل)', 'nafas-chatbot' ); ?></label></th>
							<td>
								<input type="number" id="icon_size" name="icon_size" value="<?php echo esc_attr( $s['icon_size'] ); ?>" min="16" max="80" class="small-text">
								<p class="description"><?php esc_html_e( 'اندازه آیکون داخل دکمه؛ فاصله (پدینگ) از تفاوت اندازه دکمه و آیکون به‌صورت خودکار محاسبه می‌شود. پیش‌فرض: ۲۸', 'nafas-chatbot' ); ?></p>
							</td>
						</tr>
						<tr>
							<th><label for="button_radius"><?php esc_html_e( 'گردی گوشه‌های دکمه (٪)', 'nafas-chatbot' ); ?></label></th>
							<td>
								<input type="number" id="button_radius" name="button_radius" value="<?php echo esc_attr( $s['button_radius'] ); ?>" min="0" max="50" class="small-text">
								<p class="description"><?php esc_html_e( '۵۰ = دایره کامل (پیش‌فرض)، ۰ = مربع، مقادیر میانی = گوشه‌گرد.', 'nafas-chatbot' ); ?></p>
							</td>
						</tr>
						<tr>
							<th><label for="button_icon_url"><?php esc_html_e( 'تصویر آیکون سفارشی', 'nafas-chatbot' ); ?></label></th>
							<td>
								<input type="url" id="button_icon_url" name="button_icon_url" value="<?php echo esc_attr( $s['button_icon_url'] ); ?>" class="large-text" dir="ltr" placeholder="https://...">
								<p class="description"><?php esc_html_e( 'در صورت وارد کردن لینک تصویر، به‌جای آیکون پیش‌فرض نمایش داده می‌شود.', 'nafas-chatbot' ); ?></p>
							</td>
					</tr>
					<tr>
						<th><label for="theme_mode"><?php esc_html_e( 'حالت تم', 'nafas-chatbot' ); ?></label></th>
						<td>
							<select name="theme_mode" id="theme_mode">
								<option value="auto" <?php selected( $s['theme_mode'], 'auto' ); ?>><?php esc_html_e( 'خودکار (تبعیت از پوسته سایت)', 'nafas-chatbot' ); ?></option>
								<option value="light" <?php selected( $s['theme_mode'], 'light' ); ?>><?php esc_html_e( 'روشن', 'nafas-chatbot' ); ?></option>
								<option value="dark" <?php selected( $s['theme_mode'], 'dark' ); ?>><?php esc_html_e( 'تیره', 'nafas-chatbot' ); ?></option>
							</select>
						</td>
					</tr>

					<tr><th colspan="2"><h3 class="nafas-section" style="margin:8px 0 0"><?php esc_html_e( 'فونت و استایل پنجره', 'nafas-chatbot' ); ?></h3></th></tr>
					<tr>
						<th><label for="font_family"><?php esc_html_e( 'فونت', 'nafas-chatbot' ); ?></label></th>
						<td>
							<select name="font_family" id="font_family">
								<option value="vazirmatn" <?php selected( $s['font_family'], 'vazirmatn' ); ?>><?php esc_html_e( 'وزیرمتن (فارسی)', 'nafas-chatbot' ); ?></option>
								<option value="system" <?php selected( $s['font_family'], 'system' ); ?>><?php esc_html_e( 'فونت سیستمی (بدون بارگذاری خارجی)', 'nafas-chatbot' ); ?></option>
								<option value="inter" <?php selected( $s['font_family'], 'inter' ); ?>>Inter (Google)</option>
								<option value="roboto" <?php selected( $s['font_family'], 'roboto' ); ?>>Roboto (Google)</option>
								<option value="custom" <?php selected( $s['font_family'], 'custom' ); ?>><?php esc_html_e( 'سفارشی (آدرس فونت)', 'nafas-chatbot' ); ?></option>
							</select>
							<p class="description"><?php esc_html_e( 'برای بازار فارسی «وزیرمتن» و برای سایت‌های چندزبانه «سیستمی» یا Google پیشنهاد می‌شود.', 'nafas-chatbot' ); ?></p>
						</td>
					</tr>
					<tr class="nafas-font-custom">
						<th><label for="font_name"><?php esc_html_e( 'نام خانوادهٔ فونت', 'nafas-chatbot' ); ?></label></th>
						<td>
							<input type="text" id="font_name" name="font_name" value="<?php echo esc_attr( $s['font_name'] ); ?>" class="regular-text" dir="ltr" placeholder="e.g. IRANSans">
							<p class="description"><?php esc_html_e( 'نام font-family که در فایل فونت سفارشی تعریف شده است.', 'nafas-chatbot' ); ?></p>
						</td>
					</tr>
					<tr class="nafas-font-custom">
						<th><label for="font_url"><?php esc_html_e( 'آدرس شیوه‌نامهٔ فونت', 'nafas-chatbot' ); ?></label></th>
						<td><input type="url" id="font_url" name="font_url" value="<?php echo esc_attr( $s['font_url'] ); ?>" class="large-text" dir="ltr" placeholder="https://...font.css"></td>
					</tr>
					<tr>
						<th><label for="font_size"><?php esc_html_e( 'اندازهٔ متن پیام (px)', 'nafas-chatbot' ); ?></label></th>
						<td><input type="number" id="font_size" name="font_size" value="<?php echo esc_attr( $s['font_size'] ); ?>" min="10" max="24" class="small-text"></td>
					</tr>
					<tr>
						<th><label for="window_width"><?php esc_html_e( 'عرض پنجرهٔ چت (px)', 'nafas-chatbot' ); ?></label></th>
						<td><input type="number" id="window_width" name="window_width" value="<?php echo esc_attr( $s['window_width'] ); ?>" min="300" max="520" class="small-text"></td>
					</tr>
					<tr>
						<th><label for="window_radius"><?php esc_html_e( 'گردی گوشه‌های پنجره (px)', 'nafas-chatbot' ); ?></label></th>
						<td><input type="number" id="window_radius" name="window_radius" value="<?php echo esc_attr( $s['window_radius'] ); ?>" min="0" max="40" class="small-text"></td>
					</tr>
					<tr>
						<th><label for="bubble_radius"><?php esc_html_e( 'گردی گوشه‌های حباب پیام (px)', 'nafas-chatbot' ); ?></label></th>
						<td><input type="number" id="bubble_radius" name="bubble_radius" value="<?php echo esc_attr( $s['bubble_radius'] ); ?>" min="0" max="30" class="small-text"></td>
					</tr>
					<tr>
						<th><label for="user_bubble_color"><?php esc_html_e( 'رنگ حباب کاربر', 'nafas-chatbot' ); ?></label></th>
						<td>
							<input type="text" id="user_bubble_color" name="user_bubble_color" value="<?php echo esc_attr( $s['user_bubble_color'] ); ?>" class="nafas-color-picker">
							<p class="description"><?php esc_html_e( 'خالی = استفاده از رنگ اصلی.', 'nafas-chatbot' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="bot_bubble_color"><?php esc_html_e( 'رنگ حباب ربات', 'nafas-chatbot' ); ?></label></th>
						<td>
							<input type="text" id="bot_bubble_color" name="bot_bubble_color" value="<?php echo esc_attr( $s['bot_bubble_color'] ); ?>" class="nafas-color-picker">
							<p class="description"><?php esc_html_e( 'خالی = پس‌زمینهٔ پیش‌فرض کارت.', 'nafas-chatbot' ); ?></p>
						</td>
					</tr>
				</table>

				<div class="nafas-preview">
					<h3 class="nafas-section"><?php esc_html_e( 'پیش‌نمایش زنده', 'nafas-chatbot' ); ?></h3>
					<p class="description"><?php esc_html_e( 'رنگ‌ها، فونت، اندازه و گردی گوشه‌ها بلافاصله اینجا نمایش داده می‌شوند. برای اعمال روی سایت، «ذخیره تنظیمات» را بزنید.', 'nafas-chatbot' ); ?></p>
					<div id="nfx-preview" class="nfx-preview" data-theme="light">
						<div class="nfx-preview__win">
							<div class="nfx-preview__head">
								<span class="nfx-preview__ava">🤖</span>
								<div class="nfx-preview__titles">
									<strong class="nfx-preview__title"><?php esc_html_e( 'دستیار هوشمند', 'nafas-chatbot' ); ?></strong>
									<small class="nfx-preview__status"><?php esc_html_e( 'آنلاین', 'nafas-chatbot' ); ?></small>
								</div>
							</div>
							<div class="nfx-preview__body">
								<div class="nfx-preview__msg nfx-preview__msg--bot nfx-preview__welcome"><?php esc_html_e( 'سلام! 👋 چطور می‌تونم کمکتون کنم؟', 'nafas-chatbot' ); ?></div>
								<div class="nfx-preview__msg nfx-preview__msg--user"><?php esc_html_e( 'سلام، یک سوال داشتم.', 'nafas-chatbot' ); ?></div>
								<div class="nfx-preview__msg nfx-preview__msg--bot"><?php esc_html_e( 'بله، در خدمتم. بفرمایید.', 'nafas-chatbot' ); ?></div>
							</div>
							<div class="nfx-preview__foot"><span class="nfx-preview__disc"><?php esc_html_e( 'هوش مصنوعی ممکن است اشتباه کند.', 'nafas-chatbot' ); ?></span></div>
						</div>
						<div class="nfx-preview__btn">💬</div>
					</div>
				</div>
			</div>

			<!-- هوش مصنوعی -->
			<div id="tab-ai" class="nafas-tab-panel">
				<table class="form-table">
					<tr>
						<th><label for="ai_provider"><?php esc_html_e( 'موتور پاسخ‌گویی', 'nafas-chatbot' ); ?></label></th>
						<td>
							<select name="ai_provider" id="ai_provider">
								<option value="fallback" <?php selected( $s['ai_provider'], 'fallback' ); ?>><?php esc_html_e( 'پیام ثابت (بدون AI)', 'nafas-chatbot' ); ?></option>
								<option value="gemini" <?php selected( $s['ai_provider'], 'gemini' ); ?>><?php esc_html_e( 'Google Gemini', 'nafas-chatbot' ); ?></option>
								<option value="openai" <?php selected( $s['ai_provider'], 'openai' ); ?>><?php esc_html_e( 'OpenAI (ChatGPT)', 'nafas-chatbot' ); ?></option>
								<option value="claude" <?php selected( $s['ai_provider'], 'claude' ); ?>><?php esc_html_e( 'Anthropic Claude', 'nafas-chatbot' ); ?></option>
								<option value="custom" <?php selected( $s['ai_provider'], 'custom' ); ?>><?php esc_html_e( 'سفارشی (سازگار با OpenAI)', 'nafas-chatbot' ); ?></option>
								<option value="webhook" <?php selected( $s['ai_provider'], 'webhook' ); ?>><?php esc_html_e( 'Webhook سفارشی', 'nafas-chatbot' ); ?></option>
							</select>
							<p class="description"><?php esc_html_e( 'موتور هوش مصنوعی برای پاسخ‌گویی به سوالات. حالت «سفارشی» امکان اتصال به هر سرویس سازگار با OpenAI را با وارد کردن آدرس می‌دهد.', 'nafas-chatbot' ); ?></p>
						</td>
					</tr>

					<!-- Gemini -->
					<tr class="nafas-ai-gemini">
						<th><label for="gemini_api_key"><?php esc_html_e( 'کلید API جمینای', 'nafas-chatbot' ); ?></label></th>
						<td>
							<input type="password" id="gemini_api_key" name="gemini_api_key" value="" placeholder="<?php echo esc_attr( $secret_ph( 'gemini_api_key' ) ); ?>" class="regular-text" dir="ltr" autocomplete="off">
							<p class="description"><?php esc_html_e( 'کلید را از Google AI Studio دریافت کنید.', 'nafas-chatbot' ); ?></p>
						</td>
					</tr>
					<tr class="nafas-ai-gemini">
						<th><label for="gemini_model"><?php esc_html_e( 'مدل جمینای', 'nafas-chatbot' ); ?></label></th>
						<td><input type="text" id="gemini_model" name="gemini_model" value="<?php echo esc_attr( $s['gemini_model'] ); ?>" class="regular-text" dir="ltr" placeholder="gemini-2.0-flash"></td>
					</tr>

					<!-- OpenAI -->
					<tr class="nafas-ai-openai">
						<th><label for="openai_api_key"><?php esc_html_e( 'کلید API اوپن‌ای‌آی', 'nafas-chatbot' ); ?></label></th>
						<td>
							<input type="password" id="openai_api_key" name="openai_api_key" value="" placeholder="<?php echo esc_attr( $secret_ph( 'openai_api_key' ) ); ?>" class="regular-text" dir="ltr" autocomplete="off">
							<p class="description"><?php esc_html_e( 'کلید را از platform.openai.com دریافت کنید.', 'nafas-chatbot' ); ?></p>
						</td>
					</tr>
					<tr class="nafas-ai-openai">
						<th><label for="openai_model"><?php esc_html_e( 'مدل OpenAI', 'nafas-chatbot' ); ?></label></th>
						<td><input type="text" id="openai_model" name="openai_model" value="<?php echo esc_attr( $s['openai_model'] ); ?>" class="regular-text" dir="ltr" placeholder="gpt-4o-mini"></td>
					</tr>

					<!-- Claude -->
					<tr class="nafas-ai-claude">
						<th><label for="claude_api_key"><?php esc_html_e( 'کلید API کلود', 'nafas-chatbot' ); ?></label></th>
						<td>
							<input type="password" id="claude_api_key" name="claude_api_key" value="" placeholder="<?php echo esc_attr( $secret_ph( 'claude_api_key' ) ); ?>" class="regular-text" dir="ltr" autocomplete="off">
							<p class="description"><?php esc_html_e( 'کلید را از console.anthropic.com دریافت کنید.', 'nafas-chatbot' ); ?></p>
						</td>
					</tr>
					<tr class="nafas-ai-claude">
						<th><label for="claude_model"><?php esc_html_e( 'مدل کلود', 'nafas-chatbot' ); ?></label></th>
						<td>
							<input type="text" id="claude_model" name="claude_model" value="<?php echo esc_attr( $s['claude_model'] ); ?>" class="regular-text" dir="ltr" placeholder="claude-opus-4-8">
							<p class="description"><?php esc_html_e( 'مثال‌ها: claude-opus-4-8 (قوی‌ترین) · claude-haiku-4-5 (سریع و مقرون‌به‌صرفه).', 'nafas-chatbot' ); ?></p>
						</td>
					</tr>

					<!-- Custom -->
					<tr class="nafas-ai-custom">
						<th><label for="custom_endpoint"><?php esc_html_e( 'آدرس Endpoint سفارشی', 'nafas-chatbot' ); ?></label></th>
						<td>
							<input type="url" id="custom_endpoint" name="custom_endpoint" value="<?php echo esc_attr( $s['custom_endpoint'] ); ?>" class="large-text" dir="ltr" placeholder="https://api.example.com/v1/chat/completions">
							<p class="description"><?php esc_html_e( 'آدرس کامل endpoint سازگار با OpenAI (مثلاً OpenRouter، Groq، DeepSeek، Together، Ollama و ...).', 'nafas-chatbot' ); ?></p>
						</td>
					</tr>
					<tr class="nafas-ai-custom">
						<th><label for="custom_api_key"><?php esc_html_e( 'کلید API سفارشی', 'nafas-chatbot' ); ?></label></th>
						<td><input type="password" id="custom_api_key" name="custom_api_key" value="" placeholder="<?php echo esc_attr( $secret_ph( 'custom_api_key' ) ); ?>" class="regular-text" dir="ltr" autocomplete="off"></td>
					</tr>
					<tr class="nafas-ai-custom">
						<th><label for="custom_model"><?php esc_html_e( 'نام مدل سفارشی', 'nafas-chatbot' ); ?></label></th>
						<td><input type="text" id="custom_model" name="custom_model" value="<?php echo esc_attr( $s['custom_model'] ); ?>" class="regular-text" dir="ltr" placeholder="مثلاً: llama-3.3-70b"></td>
					</tr>

					<!-- Webhook -->
					<tr class="nafas-ai-webhook">
						<th><label for="ai_webhook_url"><?php esc_html_e( 'آدرس Webhook', 'nafas-chatbot' ); ?></label></th>
						<td>
							<input type="url" id="ai_webhook_url" name="ai_webhook_url" value="<?php echo esc_attr( $s['ai_webhook_url'] ); ?>" class="large-text" dir="ltr">
							<p class="description"><?php esc_html_e( 'یک درخواست POST با فیلدهای message، product و history ارسال و پاسخ JSON با کلید reply انتظار می‌رود.', 'nafas-chatbot' ); ?></p>
						</td>
					</tr>
					<tr class="nafas-ai-webhook">
						<th><label for="ai_webhook_secret"><?php esc_html_e( 'کلید امضای Webhook (اختیاری)', 'nafas-chatbot' ); ?></label></th>
						<td>
							<input type="password" id="ai_webhook_secret" name="ai_webhook_secret" value="" placeholder="<?php echo esc_attr( $secret_ph( 'ai_webhook_secret' ) ); ?>" class="regular-text" dir="ltr" autocomplete="off">
							<p class="description"><?php esc_html_e( 'در صورت تنظیم، درخواست با هدر امضای HMAC-SHA256 ارسال و امضای پاسخ بررسی می‌شود.', 'nafas-chatbot' ); ?></p>
						</td>
					</tr>

					<!-- مشترک -->
					<tr class="nafas-ai-shared">
						<th><label for="ai_system_prompt"><?php esc_html_e( 'دستورالعمل سیستمی', 'nafas-chatbot' ); ?></label></th>
						<td>
							<textarea id="ai_system_prompt" name="ai_system_prompt" rows="4" class="large-text"><?php echo esc_textarea( $s['ai_system_prompt'] ); ?></textarea>
							<p class="description"><?php esc_html_e( 'شخصیت و قوانین رفتاری دستیار. برای همه موتورها (به‌جز Webhook) اعمال می‌شود.', 'nafas-chatbot' ); ?></p>
						</td>
					</tr>
					<tr class="nafas-ai-shared">
						<th><label for="ai_history_limit"><?php esc_html_e( 'حافظه مکالمه', 'nafas-chatbot' ); ?></label></th>
						<td>
							<input type="number" id="ai_history_limit" name="ai_history_limit" value="<?php echo esc_attr( $s['ai_history_limit'] ); ?>" min="0" max="20" class="small-text">
							<p class="description"><?php esc_html_e( 'تعداد پیام‌های اخیر گفتگو که برای حفظ زمینه به مدل ارسال می‌شود (۰ = بدون حافظه). مقدار بیشتر = پاسخ‌های دقیق‌تر اما مصرف توکن بالاتر.', 'nafas-chatbot' ); ?></p>
						</td>
					</tr>
					<tr class="nafas-ai-shared">
						<th><label for="ai_temperature"><?php esc_html_e( 'میزان خلاقیت پاسخ', 'nafas-chatbot' ); ?></label></th>
						<td>
							<select name="ai_temperature" id="ai_temperature">
								<option value="0.1" <?php selected( (string) $s['ai_temperature'], '0.1' ); ?>><?php esc_html_e( 'دقیق و محافظه‌کار (مناسب اطلاعات دارویی)', 'nafas-chatbot' ); ?></option>
								<option value="0.4" <?php selected( (string) $s['ai_temperature'], '0.4' ); ?>><?php esc_html_e( 'متعادل (پیش‌فرض)', 'nafas-chatbot' ); ?></option>
								<option value="0.7" <?php selected( (string) $s['ai_temperature'], '0.7' ); ?>><?php esc_html_e( 'خلاق', 'nafas-chatbot' ); ?></option>
								<option value="1" <?php selected( (string) $s['ai_temperature'], '1' ); ?>><?php esc_html_e( 'بسیار خلاق', 'nafas-chatbot' ); ?></option>
							</select>
							<p class="description"><?php esc_html_e( 'مقدار کمتر = پاسخ‌های دقیق‌تر و قابل‌اتکاتر؛ مقدار بیشتر = خلاقانه‌تر اما با احتمال خطای بیشتر. (روی مدل‌های Claude اعمال نمی‌شود.)', 'nafas-chatbot' ); ?></p>
						</td>
					</tr>
					<tr class="nafas-ai-shared">
						<th><label for="ai_max_tokens"><?php esc_html_e( 'حداکثر طول پاسخ', 'nafas-chatbot' ); ?></label></th>
						<td>
							<input type="number" id="ai_max_tokens" name="ai_max_tokens" value="<?php echo esc_attr( $s['ai_max_tokens'] ); ?>" min="100" max="4000" step="50" class="small-text">
							<p class="description"><?php esc_html_e( 'حداکثر تعداد توکن خروجی (طول پاسخ). پیش‌فرض: ۸۰۰', 'nafas-chatbot' ); ?></p>
						</td>
					</tr>
					<tr class="nafas-ai-shared">
						<th><?php esc_html_e( 'پاسخ فقط بر اساس پایگاه دانش', 'nafas-chatbot' ); ?></th>
						<td>
							<label class="nafas-switch"><input type="checkbox" name="ai_strict_knowledge" value="yes" <?php checked( $s['ai_strict_knowledge'], 'yes' ); ?>><span class="nafas-switch__slider"></span></label>
							<p class="description"><?php esc_html_e( 'در صورت فعال بودن، دستیار فقط از «پایگاه دانش» هر محصول پاسخ می‌دهد و اگر اطلاعاتی نبود، کاربر را به تماس/مشاوره ارجاع می‌دهد (از دانش عمومی استفاده نمی‌کند).', 'nafas-chatbot' ); ?></p>
						</td>
					</tr>
					<tr class="nafas-ai-shared">
						<th><?php esc_html_e( 'کش پاسخ هوش مصنوعی', 'nafas-chatbot' ); ?></th>
						<td>
							<label class="nafas-switch"><input type="checkbox" name="ai_cache_enabled" value="yes" <?php checked( $s['ai_cache_enabled'], 'yes' ); ?>><span class="nafas-switch__slider"></span></label>
							<p class="description"><?php esc_html_e( 'پاسخ سوال‌های تکراری (بدون تاریخچه) برای ۶ ساعت کش می‌شود تا فوری و بدون هزینه مجدد پاسخ داده شود.', 'nafas-chatbot' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="ai_fallback_msg"><?php esc_html_e( 'پیام پیش‌فرض/جایگزین', 'nafas-chatbot' ); ?></label></th>
						<td>
							<textarea id="ai_fallback_msg" name="ai_fallback_msg" rows="3" class="large-text"><?php echo esc_textarea( $s['ai_fallback_msg'] ); ?></textarea>
							<p class="description"><?php esc_html_e( 'وقتی موتور AI غیرفعال است یا پاسخی دریافت نشود، این پیام نمایش داده می‌شود.', 'nafas-chatbot' ); ?></p>
						</td>
					</tr>
					<tr class="nafas-ai-shared">
						<th><?php esc_html_e( 'تست اتصال', 'nafas-chatbot' ); ?></th>
						<td>
							<button type="button" class="button button-secondary" id="nafas-test-ai">
								<span class="dashicons dashicons-admin-plugins" style="margin-top:4px"></span>
								<?php esc_html_e( 'تست اتصال به موتور AI', 'nafas-chatbot' ); ?>
							</button>
							<span id="nafas-test-ai-result" class="nafas-test-result"></span>
							<p class="description"><?php esc_html_e( 'ابتدا تنظیمات را ذخیره کنید، سپس این دکمه را بزنید تا صحت کلید، نام مدل و دسترسی سرور بررسی شود. پیام خطای واقعی سرویس نمایش داده می‌شود.', 'nafas-chatbot' ); ?></p>
						</td>
					</tr>
				</table>
			</div>

			<!-- تجربه کاربری / پیشرفته -->
			<div id="tab-advanced" class="nafas-tab-panel">
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'بازخورد پاسخ (👍/👎)', 'nafas-chatbot' ); ?></th>
						<td><label class="nafas-switch"><input type="checkbox" name="feedback_enabled" value="yes" <?php checked( $s['feedback_enabled'], 'yes' ); ?>><span class="nafas-switch__slider"></span></label>
						<p class="description"><?php esc_html_e( 'نمایش دکمه‌های مفید بود/نبود زیر پاسخ‌ها و ثبت در داشبورد.', 'nafas-chatbot' ); ?></p></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'افکت تایپ تدریجی پاسخ', 'nafas-chatbot' ); ?></th>
						<td><label class="nafas-switch"><input type="checkbox" name="typewriter_enabled" value="yes" <?php checked( $s['typewriter_enabled'], 'yes' ); ?>><span class="nafas-switch__slider"></span></label></td>
					</tr>

					<tr><th colspan="2"><h3 class="nafas-section"><?php esc_html_e( 'هوشمندسازی گفتگو (نسخه ۲.۵)', 'nafas-chatbot' ); ?></h3></th></tr>
					<tr>
						<th><?php esc_html_e( 'چیپس‌های پیگیری هوشمند', 'nafas-chatbot' ); ?></th>
						<td><label class="nafas-switch"><input type="checkbox" name="suggestions_enabled" value="yes" <?php checked( $s['suggestions_enabled'], 'yes' ); ?>><span class="nafas-switch__slider"></span></label>
						<p class="description"><?php esc_html_e( 'پس از هر پاسخ، چند سوال مرتبط از بانک به‌صورت پیشنهاد نمایش داده می‌شود.', 'nafas-chatbot' ); ?></p></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'تکمیل خودکار هنگام تایپ', 'nafas-chatbot' ); ?></th>
						<td><label class="nafas-switch"><input type="checkbox" name="autocomplete_enabled" value="yes" <?php checked( $s['autocomplete_enabled'], 'yes' ); ?>><span class="nafas-switch__slider"></span></label>
						<p class="description"><?php esc_html_e( 'هنگام نوشتن، سوال‌های مشابه از بانک به‌صورت لیست پیشنهاد می‌شوند (سریع و بدون مصرف هوش مصنوعی).', 'nafas-chatbot' ); ?></p></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'حالت صوتی (میکروفون + خواندن پاسخ)', 'nafas-chatbot' ); ?></th>
						<td><label class="nafas-switch"><input type="checkbox" name="voice_enabled" value="yes" <?php checked( $s['voice_enabled'], 'yes' ); ?>><span class="nafas-switch__slider"></span></label>
						<p class="description"><?php esc_html_e( 'دکمه میکروفون برای گفتن سوال و دکمه بلندگو برای شنیدن پاسخ — کاملاً سمت مرورگر کاربر (Web Speech API) و رایگان. در مرورگرهای ناسازگار به‌صورت خودکار پنهان می‌شود.', 'nafas-chatbot' ); ?></p></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'نظرسنجی رضایت پایان گفتگو (CSAT)', 'nafas-chatbot' ); ?></th>
						<td><label class="nafas-switch"><input type="checkbox" name="csat_enabled" value="yes" <?php checked( $s['csat_enabled'], 'yes' ); ?>><span class="nafas-switch__slider"></span></label>
						<p class="description"><?php esc_html_e( 'هنگام بازگشت به منوی اصلی پس از یک گفتگوی واقعی، از کاربر امتیاز ۱ تا ۵ ستاره پرسیده می‌شود (نمایش میانگین در داشبورد).', 'nafas-chatbot' ); ?></p></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'واگذاری به کارشناس انسانی', 'nafas-chatbot' ); ?></th>
						<td><label class="nafas-switch"><input type="checkbox" name="handoff_enabled" value="yes" <?php checked( $s['handoff_enabled'], 'yes' ); ?>><span class="nafas-switch__slider"></span></label>
						<p class="description"><?php esc_html_e( 'وقتی نه هوش مصنوعی و نه بانک پاسخی نداشتند، گزینهٔ «گفتگو با کارشناس» (ثبت درخواست مشاوره) به کاربر پیشنهاد می‌شود.', 'nafas-chatbot' ); ?></p></td>
					</tr>
					<tr>
						<th><label for="handoff_text"><?php esc_html_e( 'متن پیشنهاد واگذاری', 'nafas-chatbot' ); ?></label></th>
						<td><textarea id="handoff_text" name="handoff_text" rows="2" class="large-text"><?php echo esc_textarea( $s['handoff_text'] ); ?></textarea></td>
					</tr>

					<tr><th colspan="2"><h3 class="nafas-section"><?php esc_html_e( 'حریم خصوصی', 'nafas-chatbot' ); ?></h3></th></tr>
					<tr>
						<th><?php esc_html_e( 'الزام موافقت در فرم‌ها', 'nafas-chatbot' ); ?></th>
						<td><label class="nafas-switch"><input type="checkbox" name="consent_enabled" value="yes" <?php checked( $s['consent_enabled'], 'yes' ); ?>><span class="nafas-switch__slider"></span></label>
						<p class="description"><?php esc_html_e( 'نمایش یک تیک موافقت با حریم خصوصی در فرم‌های عوارض/مشاوره (ثبت بدون تأیید آن ممکن نیست).', 'nafas-chatbot' ); ?></p></td>
					</tr>
					<tr>
						<th><label for="consent_text"><?php esc_html_e( 'متن موافقت', 'nafas-chatbot' ); ?></label></th>
						<td><textarea id="consent_text" name="consent_text" rows="2" class="large-text"><?php echo esc_textarea( $s['consent_text'] ); ?></textarea></td>
					</tr>
					<tr>
						<th><label for="consent_link"><?php esc_html_e( 'لینک سیاست حریم خصوصی', 'nafas-chatbot' ); ?></label></th>
						<td><input type="url" id="consent_link" name="consent_link" value="<?php echo esc_attr( $s['consent_link'] ); ?>" class="large-text" dir="ltr" placeholder="https://..."></td>
					</tr>
					<tr>
						<th><label for="submissions_retention_days"><?php esc_html_e( 'نگهداری درخواست‌ها (روز)', 'nafas-chatbot' ); ?></label></th>
						<td>
							<input type="number" id="submissions_retention_days" name="submissions_retention_days" value="<?php echo esc_attr( $s['submissions_retention_days'] ); ?>" min="0" max="3650" class="small-text">
							<p class="description"><?php esc_html_e( 'درخواست‌های (عوارض/مشاوره) قدیمی‌تر از این تعداد روز به‌صورت خودکار حذف می‌شوند. ۰ = نگهداری همیشگی. (برای کمینه‌سازی دادهٔ شخصی.)', 'nafas-chatbot' ); ?></p>
						</td>
					</tr>

					<tr><th colspan="2"><h3 class="nafas-section"><?php esc_html_e( 'پیام دعوت هوشمند', 'nafas-chatbot' ); ?></h3></th></tr>
					<tr>
						<th><?php esc_html_e( 'فعال‌سازی', 'nafas-chatbot' ); ?></th>
						<td><label class="nafas-switch"><input type="checkbox" name="proactive_enabled" value="yes" <?php checked( $s['proactive_enabled'], 'yes' ); ?>><span class="nafas-switch__slider"></span></label>
						<p class="description"><?php esc_html_e( 'نمایش یک حباب دعوت کنار دکمه پس از چند ثانیه (یا هنگام قصد خروج).', 'nafas-chatbot' ); ?></p></td>
					</tr>
					<tr>
						<th><label for="proactive_delay"><?php esc_html_e( 'تاخیر نمایش (ثانیه)', 'nafas-chatbot' ); ?></label></th>
						<td><input type="number" id="proactive_delay" name="proactive_delay" value="<?php echo esc_attr( $s['proactive_delay'] ); ?>" min="2" max="120" class="small-text"></td>
					</tr>
					<tr>
						<th><label for="proactive_text"><?php esc_html_e( 'متن دعوت', 'nafas-chatbot' ); ?></label></th>
						<td><input type="text" id="proactive_text" name="proactive_text" value="<?php echo esc_attr( $s['proactive_text'] ); ?>" class="regular-text"></td>
					</tr>

					<tr><th colspan="2"><h3 class="nafas-section"><?php esc_html_e( 'ساعات کاری / وضعیت آنلاین', 'nafas-chatbot' ); ?></h3></th></tr>
					<tr>
						<th><?php esc_html_e( 'محدود به ساعات کاری', 'nafas-chatbot' ); ?></th>
						<td><label class="nafas-switch"><input type="checkbox" name="office_enabled" value="yes" <?php checked( $s['office_enabled'], 'yes' ); ?>><span class="nafas-switch__slider"></span></label>
						<p class="description"><?php esc_html_e( 'اگر فعال باشد، خارج از ساعات کاری وضعیت هدر «خارج از ساعت کاری» نمایش داده می‌شود.', 'nafas-chatbot' ); ?></p></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'ساعت کاری (از - تا)', 'nafas-chatbot' ); ?></th>
						<td>
							<?php esc_html_e( 'از', 'nafas-chatbot' ); ?>
							<input type="number" name="office_start" value="<?php echo esc_attr( $s['office_start'] ); ?>" min="0" max="23" class="small-text">
							<?php esc_html_e( 'تا', 'nafas-chatbot' ); ?>
							<input type="number" name="office_end" value="<?php echo esc_attr( $s['office_end'] ); ?>" min="1" max="24" class="small-text">
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'روزهای کاری', 'nafas-chatbot' ); ?></th>
						<td>
							<?php
							$days_labels = array( 6 => 'شنبه', 0 => 'یکشنبه', 1 => 'دوشنبه', 2 => 'سه‌شنبه', 3 => 'چهارشنبه', 4 => 'پنجشنبه', 5 => 'جمعه' );
							$office_days = array_map( 'intval', (array) $s['office_days'] );
							foreach ( $days_labels as $dnum => $dlabel ) :
								?>
								<label style="margin-left:12px;display:inline-block"><input type="checkbox" name="office_days[]" value="<?php echo esc_attr( $dnum ); ?>" <?php checked( in_array( $dnum, $office_days, true ) ); ?>> <?php echo esc_html( $dlabel ); ?></label>
							<?php endforeach; ?>
						</td>
					</tr>
					<tr>
						<th><label for="online_text"><?php esc_html_e( 'متن وضعیت آنلاین', 'nafas-chatbot' ); ?></label></th>
						<td><input type="text" id="online_text" name="online_text" value="<?php echo esc_attr( $s['online_text'] ); ?>" class="regular-text"></td>
					</tr>
					<tr>
						<th><label for="offline_text"><?php esc_html_e( 'متن خارج از ساعت کاری', 'nafas-chatbot' ); ?></label></th>
						<td><input type="text" id="offline_text" name="offline_text" value="<?php echo esc_attr( $s['offline_text'] ); ?>" class="regular-text"></td>
					</tr>
				</table>
			</div>

			<!-- اعلان‌ها -->
			<div id="tab-notify" class="nafas-tab-panel">
				<h3 class="nafas-section"><?php esc_html_e( 'اعلان پیام‌رسان (بله / تلگرام)', 'nafas-chatbot' ); ?></h3>
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'فعال‌سازی', 'nafas-chatbot' ); ?></th>
						<td><label class="nafas-switch"><input type="checkbox" name="notify_enabled" value="yes" <?php checked( $s['notify_enabled'], 'yes' ); ?>><span class="nafas-switch__slider"></span></label></td>
					</tr>
					<tr>
						<th><label for="notify_platform"><?php esc_html_e( 'پلتفرم', 'nafas-chatbot' ); ?></label></th>
						<td>
							<select name="notify_platform" id="notify_platform">
								<option value="bale" <?php selected( $s['notify_platform'], 'bale' ); ?>><?php esc_html_e( 'بله (Bale)', 'nafas-chatbot' ); ?></option>
								<option value="telegram" <?php selected( $s['notify_platform'], 'telegram' ); ?>><?php esc_html_e( 'تلگرام', 'nafas-chatbot' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th><label for="notify_token"><?php esc_html_e( 'توکن بات', 'nafas-chatbot' ); ?></label></th>
						<td><input type="password" id="notify_token" name="notify_token" value="" placeholder="<?php echo esc_attr( $secret_ph( 'notify_token' ) ); ?>" class="regular-text" dir="ltr" autocomplete="off"></td>
					</tr>
					<tr>
						<th><label for="notify_chat_id"><?php esc_html_e( 'شناسه چت (Chat ID)', 'nafas-chatbot' ); ?></label></th>
						<td><input type="text" id="notify_chat_id" name="notify_chat_id" value="<?php echo esc_attr( $s['notify_chat_id'] ); ?>" class="regular-text" dir="ltr"></td>
					</tr>
				</table>

				<h3 class="nafas-section"><?php esc_html_e( 'اعلان ایمیلی', 'nafas-chatbot' ); ?></h3>
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'فعال‌سازی', 'nafas-chatbot' ); ?></th>
						<td><label class="nafas-switch"><input type="checkbox" name="email_enabled" value="yes" <?php checked( $s['email_enabled'], 'yes' ); ?>><span class="nafas-switch__slider"></span></label></td>
					</tr>
					<tr>
						<th><label for="email_to"><?php esc_html_e( 'ایمیل دریافت‌کننده', 'nafas-chatbot' ); ?></label></th>
						<td>
							<input type="email" id="email_to" name="email_to" value="<?php echo esc_attr( $s['email_to'] ); ?>" class="regular-text" dir="ltr">
							<p class="description"><?php esc_html_e( 'در صورت خالی بودن، به ایمیل مدیر سایت ارسال می‌شود.', 'nafas-chatbot' ); ?></p>
						</td>
					</tr>
				</table>
			</div>
		</div>

		<p class="submit">
			<button type="submit" name="nafas_chatbot_save_settings" class="button button-primary button-hero">
				<?php esc_html_e( 'ذخیره تنظیمات', 'nafas-chatbot' ); ?>
			</button>
		</p>
	</form>
</div>
