<?php
/**
 * نمای صفحه تاریخچه گفتگو.
 *
 * @package SmartSupportChatbot
 * @var array  $result       نتیجه واکشی.
 * @var array  $products_map نگاشت محصولات.
 * @var string $source       فیلتر منبع.
 * @var string $search       جستجو.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$base_url = admin_url( 'admin.php?page=smart-support-chatbot-chatlog' );

$source_labels = array(
	'ai'         => __( 'هوش مصنوعی', 'smart-support-chatbot' ),
	'bank'       => __( 'بانک', 'smart-support-chatbot' ),
	'unanswered' => __( 'بی‌پاسخ', 'smart-support-chatbot' ),
);
?>
<div class="wrap ssc-admin" dir="rtl">
	<h1 class="ssc-admin__title">
		<span class="dashicons dashicons-format-chat"></span>
		<?php esc_html_e( 'تاریخچه گفتگو', 'smart-support-chatbot' ); ?>
	</h1>

	<?php if ( isset( $_GET['added'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'به بانک پاسخ‌ها اضافه شد. می‌توانید در صفحه «بانک پاسخ‌ها» ویرایشش کنید.', 'smart-support-chatbot' ); ?></p></div>
	<?php endif; ?>

	<p class="description" style="max-width:820px;font-size:13px">
		<?php esc_html_e( 'سوال‌ها و پاسخ‌های ثبت‌شده. موارد مفید را با دکمه «افزودن به بانک» به بانک پاسخ‌ها منتقل کنید تا حتی بدون هوش مصنوعی هم در دسترس باشند.', 'smart-support-chatbot' ); ?>
	</p>

	<form method="get" class="ssc-filters">
		<input type="hidden" name="page" value="smart-support-chatbot-chatlog">
		<select name="source">
			<option value=""><?php esc_html_e( 'همه منابع', 'smart-support-chatbot' ); ?></option>
			<option value="ai" <?php selected( $source, 'ai' ); ?>><?php esc_html_e( 'هوش مصنوعی', 'smart-support-chatbot' ); ?></option>
			<option value="bank" <?php selected( $source, 'bank' ); ?>><?php esc_html_e( 'بانک', 'smart-support-chatbot' ); ?></option>
			<option value="unanswered" <?php selected( $source, 'unanswered' ); ?>><?php esc_html_e( 'بی‌پاسخ (نیازمند بانک)', 'smart-support-chatbot' ); ?></option>
		</select>
		<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'جستجو در سوال یا پاسخ...', 'smart-support-chatbot' ); ?>">
		<button type="submit" class="button"><?php esc_html_e( 'فیلتر', 'smart-support-chatbot' ); ?></button>
	</form>

	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th style="width:90px"><?php esc_html_e( 'محصول', 'smart-support-chatbot' ); ?></th>
				<th style="width:25%"><?php esc_html_e( 'سوال', 'smart-support-chatbot' ); ?></th>
				<th><?php esc_html_e( 'پاسخ', 'smart-support-chatbot' ); ?></th>
				<th style="width:90px"><?php esc_html_e( 'منبع', 'smart-support-chatbot' ); ?></th>
				<th style="width:130px"><?php esc_html_e( 'تاریخ', 'smart-support-chatbot' ); ?></th>
				<th style="width:130px"><?php esc_html_e( 'عملیات', 'smart-support-chatbot' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $result['items'] ) ) : ?>
				<tr><td colspan="6" class="ssc-empty"><?php esc_html_e( 'هنوز گفتگویی ثبت نشده است.', 'smart-support-chatbot' ); ?></td></tr>
			<?php else : ?>
				<?php foreach ( $result['items'] as $row ) : ?>
					<?php
					$pname    = isset( $products_map[ $row->product ] ) ? $products_map[ $row->product ] : $row->product;
					$tobank   = wp_nonce_url( add_query_arg( array( 'ssc_action' => 'tobank', 'cid' => $row->id ), $base_url ), 'ssc_chatlog_action' );
					$dellog   = wp_nonce_url( add_query_arg( array( 'ssc_action' => 'dellog', 'cid' => $row->id ), $base_url ), 'ssc_chatlog_action' );
					?>
					<tr>
						<td><?php echo esc_html( $pname ? $pname : '—' ); ?></td>
						<td><?php echo esc_html( $row->question ); ?></td>
						<td class="ssc-desc"><?php echo esc_html( wp_trim_words( $row->answer, 30 ) ); ?></td>
						<td><span class="ssc-badge <?php echo 'ai' === $row->source ? 'ssc-badge--purple' : 'ssc-badge--red'; ?>"><?php echo esc_html( isset( $source_labels[ $row->source ] ) ? $source_labels[ $row->source ] : $row->source ); ?></span></td>
						<td><?php echo esc_html( $row->created_at ); ?></td>
						<td>
							<?php if ( $row->in_bank ) : ?>
								<span class="ssc-inbank">✓ <?php esc_html_e( 'در بانک', 'smart-support-chatbot' ); ?></span>
							<?php else : ?>
								<a href="<?php echo esc_url( $tobank ); ?>" class="button button-small"><?php esc_html_e( '➕ افزودن به بانک', 'smart-support-chatbot' ); ?></a>
							<?php endif; ?>
							<a href="<?php echo esc_url( $dellog ); ?>" class="ssc-del-link" title="<?php esc_attr_e( 'حذف', 'smart-support-chatbot' ); ?>" onclick="return confirm('<?php esc_attr_e( 'حذف این مورد؟', 'smart-support-chatbot' ); ?>');"><span class="dashicons dashicons-trash"></span></a>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>

	<?php if ( ! empty( $result['items'] ) ) : ?>
		<form method="post" style="margin-top:14px" onsubmit="return confirm('<?php esc_attr_e( 'کل تاریخچه گفتگو پاک شود؟', 'smart-support-chatbot' ); ?>');">
			<?php wp_nonce_field( 'ssc_chatlog_clear' ); ?>
			<button type="submit" name="ssc_chatbot_clear_log" class="button button-secondary"><?php esc_html_e( 'پاک‌سازی کل تاریخچه', 'smart-support-chatbot' ); ?></button>
		</form>
	<?php endif; ?>

	<?php if ( $result['total_pages'] > 1 ) : ?>
		<div class="ssc-pagination tablenav">
			<div class="tablenav-pages">
				<?php
				echo wp_kses_post(
					paginate_links(
						array(
							'base'      => add_query_arg( 'paged', '%#%' ),
							'format'    => '',
							'current'   => $result['page'],
							'total'     => $result['total_pages'],
							'prev_text' => '‹',
							'next_text' => '›',
						)
					)
				);
				?>
			</div>
		</div>
	<?php endif; ?>
</div>
