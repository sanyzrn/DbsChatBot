<?php
/**
 * نمای صفحه پایگاه دانش هیبریدی.
 *
 * @package SmartSupportChatbot
 * @var array $s            تنظیمات.
 * @var array $products_map نگاشت محصولات.
 * @var array $docs         فهرست اسناد.
 * @var int   $kb_count     تعداد کل تکه‌ها.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$docs     = isset( $docs ) ? (array) $docs : array();
$base_url = admin_url( 'admin.php?page=smart-support-chatbot-kb' );
?>
<div class="wrap ssc-admin" dir="rtl">
	<h1 class="ssc-admin__title">
		<span class="dashicons dashicons-book-alt"></span>
		<?php esc_html_e( 'پایگاه دانش', 'smart-support-chatbot' ); ?>
		<span class="ssc-admin__ver"><?php echo esc_html( number_format_i18n( $kb_count ) ); ?> <?php esc_html_e( 'تکه', 'smart-support-chatbot' ); ?></span>
	</h1>

	<p class="description">
		<?php esc_html_e( 'اسناد و بروشورهای خود را اینجا وارد کنید. هر سند به‌صورت خودکار به تکه‌های کوچک تقسیم می‌شود و هنگام پاسخ‌گویی، مرتبط‌ترین تکه‌ها به‌صورت خودکار به هوش مصنوعی داده می‌شوند تا پاسخ دقیق و مستند تولید کند (بازیابی هیبریدی، کاملاً آفلاین).', 'smart-support-chatbot' ); ?>
	</p>
	<p class="description" style="color:#b45309">
		<?php esc_html_e( 'نکته: پایگاه دانش زمانی اثر دارد که یک «موتور هوش مصنوعی» در تنظیمات فعال باشد. برای پاسخ‌های کاملاً آفلاین بدون AI، از «بانک پاسخ‌ها» استفاده کنید.', 'smart-support-chatbot' ); ?>
	</p>

	<form method="post" action="" enctype="multipart/form-data" class="ssc-settings-form">
		<?php wp_nonce_field( 'ssc_chatbot_kb' ); ?>

		<h3 class="ssc-section"><?php esc_html_e( 'تنظیمات', 'smart-support-chatbot' ); ?></h3>
		<table class="form-table">
			<tr>
				<th><?php esc_html_e( 'فعال‌سازی پایگاه دانش', 'smart-support-chatbot' ); ?></th>
				<td>
					<label class="ssc-switch"><input type="checkbox" name="kb_enabled" value="yes" <?php checked( $s['kb_enabled'], 'yes' ); ?>><span class="ssc-switch__slider"></span></label>
					<p class="description"><?php esc_html_e( 'اگر فعال باشد، تکه‌های مرتبط هنگام هر پاسخ به پرامپت هوش مصنوعی تزریق می‌شوند.', 'smart-support-chatbot' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="kb_max_chunks"><?php esc_html_e( 'حداکثر تکه در هر پاسخ', 'smart-support-chatbot' ); ?></label></th>
				<td>
					<input type="number" id="kb_max_chunks" name="kb_max_chunks" value="<?php echo esc_attr( $s['kb_max_chunks'] ); ?>" min="1" max="8" class="small-text">
					<p class="description"><?php esc_html_e( 'تعداد بیشتر = دانش بیشتر اما مصرف توکن بالاتر. پیش‌فرض: ۳', 'smart-support-chatbot' ); ?></p>
				</td>
			</tr>
		</table>

		<h3 class="ssc-section"><?php esc_html_e( 'افزودن سند جدید', 'smart-support-chatbot' ); ?></h3>
		<table class="form-table">
			<tr>
				<th><label for="kb_product"><?php esc_html_e( 'محصول مرتبط', 'smart-support-chatbot' ); ?></label></th>
				<td>
					<select name="kb_product" id="kb_product">
						<option value="general"><?php esc_html_e( 'عمومی / شرکت (همهٔ گفتگوها)', 'smart-support-chatbot' ); ?></option>
						<?php foreach ( $products_map as $pid => $pname ) : ?>
							<option value="<?php echo esc_attr( $pid ); ?>"><?php echo esc_html( $pname ); ?></option>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php esc_html_e( 'سند «عمومی» در همهٔ گفتگوها در دسترس است؛ سند محصول فقط در گفتگوی همان محصول.', 'smart-support-chatbot' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="kb_title"><?php esc_html_e( 'عنوان سند', 'smart-support-chatbot' ); ?></label></th>
				<td><input type="text" id="kb_title" name="kb_title" value="" class="regular-text" placeholder="<?php esc_attr_e( 'مثلاً: بروشور کپسولایزر', 'smart-support-chatbot' ); ?>"></td>
			</tr>
			<tr>
				<th><label for="kb_content"><?php esc_html_e( 'متن سند', 'smart-support-chatbot' ); ?></label></th>
				<td>
					<textarea id="kb_content" name="kb_content" rows="8" class="large-text" placeholder="<?php esc_attr_e( 'متن بروشور یا دانش محصول را اینجا بچسبانید...', 'smart-support-chatbot' ); ?>"></textarea>
					<p class="description"><?php esc_html_e( 'متن با خط خالی بین پاراگراف‌ها بهتر تکه‌بندی می‌شود.', 'smart-support-chatbot' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="kb_file"><?php esc_html_e( 'یا بارگذاری فایل متنی', 'smart-support-chatbot' ); ?></label></th>
				<td>
					<input type="file" id="kb_file" name="kb_file" accept=".txt,text/plain">
					<p class="description"><?php esc_html_e( 'فایل متنی (.txt). برای PDF، ابتدا متن آن را کپی و در کادر بالا بچسبانید.', 'smart-support-chatbot' ); ?></p>
				</td>
			</tr>
		</table>

		<p class="submit">
			<button type="submit" name="ssc_chatbot_save_kb" class="button button-primary button-hero"><?php esc_html_e( 'ذخیره و افزودن سند', 'smart-support-chatbot' ); ?></button>
		</p>
	</form>

	<h3 class="ssc-section"><?php esc_html_e( 'اسناد موجود', 'smart-support-chatbot' ); ?> <span class="ssc-count">(<?php echo esc_html( number_format_i18n( count( $docs ) ) ); ?>)</span></h3>

	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'عنوان سند', 'smart-support-chatbot' ); ?></th>
				<th style="width:160px"><?php esc_html_e( 'محصول', 'smart-support-chatbot' ); ?></th>
				<th style="width:90px"><?php esc_html_e( 'تکه‌ها', 'smart-support-chatbot' ); ?></th>
				<th style="width:160px"><?php esc_html_e( 'تاریخ', 'smart-support-chatbot' ); ?></th>
				<th style="width:80px"><?php esc_html_e( 'عملیات', 'smart-support-chatbot' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $docs ) ) : ?>
				<tr><td colspan="5" class="ssc-empty"><?php esc_html_e( 'هنوز سندی اضافه نشده است.', 'smart-support-chatbot' ); ?></td></tr>
			<?php else : ?>
				<?php foreach ( $docs as $doc ) : ?>
					<?php
					$pid    = isset( $doc['product_id'] ) ? $doc['product_id'] : 'general';
					$pname  = ( 'general' === $pid ) ? __( 'عمومی / شرکت', 'smart-support-chatbot' ) : ( isset( $products_map[ $pid ] ) ? $products_map[ $pid ] : $pid );
					$delurl = wp_nonce_url(
						add_query_arg(
							array(
								'ssc_action' => 'delkb',
								'doc' => $doc['doc_id'],
							),
							$base_url
						),
						'ssc_kb_action'
					);
					?>
					<tr>
						<td><strong><?php echo esc_html( $doc['source_title'] ); ?></strong></td>
						<td><?php echo esc_html( $pname ); ?></td>
						<td><span class="ssc-badge ssc-badge--purple"><?php echo esc_html( number_format_i18n( $doc['chunks'] ) ); ?></span></td>
						<td><?php echo esc_html( $doc['created_at'] ); ?></td>
						<td>
							<a href="<?php echo esc_url( $delurl ); ?>" class="ssc-del-link" title="<?php esc_attr_e( 'حذف سند', 'smart-support-chatbot' ); ?>" onclick="return confirm('<?php esc_attr_e( 'این سند و همهٔ تکه‌هایش حذف شوند؟', 'smart-support-chatbot' ); ?>');"><span class="dashicons dashicons-trash"></span></a>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>

	<?php if ( ! empty( $docs ) ) : ?>
		<form method="post" style="margin-top:14px" onsubmit="return confirm('<?php esc_attr_e( 'کل پایگاه دانش پاک شود؟', 'smart-support-chatbot' ); ?>');">
			<?php wp_nonce_field( 'ssc_kb_clear' ); ?>
			<button type="submit" name="ssc_chatbot_clear_kb" class="button button-secondary"><?php esc_html_e( 'پاک‌سازی کل پایگاه دانش', 'smart-support-chatbot' ); ?></button>
		</form>
	<?php endif; ?>
</div>
