<?php
/**
 * نمای صفحه مدیریت درخواست‌ها.
 *
 * @package SmartSupportChatbot
 * @var array $result نتیجه واکشی.
 * @var array $counts شمارش‌ها.
 * @var string $type   فیلتر نوع.
 * @var string $status فیلتر وضعیت.
 * @var string $search جستجو.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$status_labels = array(
	'new'         => __( 'جدید', 'smart-support-chatbot' ),
	'in_progress' => __( 'در حال پیگیری', 'smart-support-chatbot' ),
	'done'        => __( 'انجام شده', 'smart-support-chatbot' ),
	'archived'    => __( 'بایگانی', 'smart-support-chatbot' ),
);

$base_url = admin_url( 'admin.php?page=smart-support-chatbot-submissions' );
?>
<div class="wrap ssc-admin" dir="rtl">
	<h1 class="ssc-admin__title">
		<span class="dashicons dashicons-list-view"></span>
		<?php esc_html_e( 'درخواست‌های دریافتی', 'smart-support-chatbot' ); ?>
	</h1>
	<p class="description"><?php esc_html_e( 'مدیریت درخواست‌های ثبت‌شده از طریق چت‌بات (گزارش عوارض دارویی و درخواست مشاوره).', 'smart-support-chatbot' ); ?></p>

	<div class="ssc-stats">
		<div class="ssc-stat-card">
			<span class="ssc-stat-card__num"><?php echo esc_html( number_format_i18n( $counts['total'] ) ); ?></span>
			<span class="ssc-stat-card__label"><?php esc_html_e( 'کل درخواست‌ها', 'smart-support-chatbot' ); ?></span>
		</div>
		<div class="ssc-stat-card ssc-stat-card--red">
			<span class="ssc-stat-card__num"><?php echo esc_html( number_format_i18n( isset( $counts['گزارش عوارض دارویی'] ) ? $counts['گزارش عوارض دارویی'] : 0 ) ); ?></span>
			<span class="ssc-stat-card__label"><?php esc_html_e( 'گزارش عوارض', 'smart-support-chatbot' ); ?></span>
		</div>
		<div class="ssc-stat-card ssc-stat-card--purple">
			<span class="ssc-stat-card__num"><?php echo esc_html( number_format_i18n( isset( $counts['درخواست مشاوره'] ) ? $counts['درخواست مشاوره'] : 0 ) ); ?></span>
			<span class="ssc-stat-card__label"><?php esc_html_e( 'درخواست مشاوره', 'smart-support-chatbot' ); ?></span>
		</div>
	</div>

	<form method="get" class="ssc-filters">
		<input type="hidden" name="page" value="smart-support-chatbot-submissions">
		<select name="type">
			<option value=""><?php esc_html_e( 'همه انواع', 'smart-support-chatbot' ); ?></option>
			<option value="گزارش عوارض دارویی" <?php selected( $type, 'گزارش عوارض دارویی' ); ?>><?php esc_html_e( 'گزارش عوارض', 'smart-support-chatbot' ); ?></option>
			<option value="درخواست مشاوره" <?php selected( $type, 'درخواست مشاوره' ); ?>><?php esc_html_e( 'درخواست مشاوره', 'smart-support-chatbot' ); ?></option>
		</select>
		<select name="status">
			<option value=""><?php esc_html_e( 'همه وضعیت‌ها', 'smart-support-chatbot' ); ?></option>
			<?php foreach ( $status_labels as $sk => $sl ) : ?>
				<option value="<?php echo esc_attr( $sk ); ?>" <?php selected( $status, $sk ); ?>><?php echo esc_html( $sl ); ?></option>
			<?php endforeach; ?>
		</select>
		<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'جستجوی نام، تلفن یا متن...', 'smart-support-chatbot' ); ?>">
		<label class="ssc-date-label"><?php esc_html_e( 'از', 'smart-support-chatbot' ); ?> <input type="date" name="date_from" value="<?php echo esc_attr( isset( $date_from ) ? $date_from : '' ); ?>"></label>
		<label class="ssc-date-label"><?php esc_html_e( 'تا', 'smart-support-chatbot' ); ?> <input type="date" name="date_to" value="<?php echo esc_attr( isset( $date_to ) ? $date_to : '' ); ?>"></label>
		<button type="submit" class="button"><?php esc_html_e( 'فیلتر', 'smart-support-chatbot' ); ?></button>

		<?php
		$export_args = array_filter(
			array(
				'type'      => $type,
				'status'    => $status,
				's'         => $search,
				'date_from' => isset( $date_from ) ? $date_from : '',
				'date_to'   => isset( $date_to ) ? $date_to : '',
			)
		);
		$export_url = wp_nonce_url( add_query_arg( array_merge( array( 'action' => 'ssc_chatbot_export' ), $export_args ), admin_url( 'admin-post.php' ) ), 'ssc_export' );
		?>
		<a href="<?php echo esc_url( $export_url ); ?>" class="button button-secondary ssc-export-btn">
			<span class="dashicons dashicons-download"></span> <?php echo $export_args ? esc_html__( 'خروجی CSV (فیلترشده)', 'smart-support-chatbot' ) : esc_html__( 'خروجی CSV', 'smart-support-chatbot' ); ?>
		</a>
	</form>

	<table class="wp-list-table widefat fixed striped ssc-submissions">
		<thead>
			<tr>
				<th style="width:40px">#</th>
				<th style="width:140px"><?php esc_html_e( 'نوع', 'smart-support-chatbot' ); ?></th>
				<th style="width:130px"><?php esc_html_e( 'نام', 'smart-support-chatbot' ); ?></th>
				<th style="width:120px"><?php esc_html_e( 'تلفن', 'smart-support-chatbot' ); ?></th>
				<th style="width:110px"><?php esc_html_e( 'محصول', 'smart-support-chatbot' ); ?></th>
				<th><?php esc_html_e( 'توضیحات', 'smart-support-chatbot' ); ?></th>
				<th style="width:120px"><?php esc_html_e( 'وضعیت', 'smart-support-chatbot' ); ?></th>
				<th style="width:140px"><?php esc_html_e( 'تاریخ', 'smart-support-chatbot' ); ?></th>
				<th style="width:90px"><?php esc_html_e( 'عملیات', 'smart-support-chatbot' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $result['items'] ) ) : ?>
				<tr><td colspan="9" class="ssc-empty"><?php esc_html_e( 'هیچ درخواستی یافت نشد.', 'smart-support-chatbot' ); ?></td></tr>
			<?php else : ?>
				<?php foreach ( $result['items'] as $row ) : ?>
					<?php
					$is_adr      = ( false !== mb_strpos( $row->type, 'عوارض' ) );
					$status_url  = wp_nonce_url( add_query_arg( array( 'ssc_action' => 'status', 'sid' => $row->id ), $base_url ), 'ssc_sub_action' );
					$delete_url  = wp_nonce_url( add_query_arg( array( 'ssc_action' => 'delete', 'sid' => $row->id ), $base_url ), 'ssc_sub_action' );

					$adr_fields = array(
						'reporter_type'     => __( 'نوع گزارش‌دهنده', 'smart-support-chatbot' ),
						'severity'          => __( 'شدت عارضه', 'smart-support-chatbot' ),
						'outcome'           => __( 'پیامد', 'smart-support-chatbot' ),
						'batch_number'      => __( 'شماره سری ساخت (Batch)', 'smart-support-chatbot' ),
						'concomitant_drugs' => __( 'داروهای مصرفی همزمان', 'smart-support-chatbot' ),
					);
					$has_extra = false;
					foreach ( $adr_fields as $fk => $fl ) {
						if ( ! empty( $row->$fk ) ) {
							$has_extra = true;
							break;
						}
					}
					?>
					<tr>
						<td><?php echo esc_html( $row->id ); ?></td>
						<td>
							<span class="ssc-badge <?php echo $is_adr ? 'ssc-badge--red' : 'ssc-badge--purple'; ?>">
								<?php echo esc_html( $row->type ); ?>
							</span>
						</td>
						<td><strong><?php echo esc_html( $row->name ); ?></strong></td>
						<td dir="ltr"><a href="tel:<?php echo esc_attr( $row->phone ); ?>"><?php echo esc_html( $row->phone ); ?></a></td>
						<td><?php echo $row->product ? esc_html( $row->product ) : '—'; ?></td>
						<td class="ssc-desc"><?php echo esc_html( wp_trim_words( $row->description, 20 ) ); ?></td>
						<td>
							<select class="ssc-status-select" data-url="<?php echo esc_url( $status_url ); ?>">
								<?php foreach ( $status_labels as $sk => $sl ) : ?>
									<option value="<?php echo esc_attr( $sk ); ?>" <?php selected( $row->status, $sk ); ?>><?php echo esc_html( $sl ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
						<td><?php echo esc_html( $row->created_at ); ?></td>
						<td class="ssc-row-actions">
							<button type="button" class="ssc-detail-toggle" aria-label="<?php esc_attr_e( 'مشاهده جزئیات', 'smart-support-chatbot' ); ?>" title="<?php esc_attr_e( 'مشاهده جزئیات', 'smart-support-chatbot' ); ?>">
								<span class="dashicons dashicons-visibility"></span>
							</button>
							<a href="<?php echo esc_url( $delete_url ); ?>" class="ssc-del-link" onclick="return confirm('<?php esc_attr_e( 'آیا از حذف این درخواست اطمینان دارید؟', 'smart-support-chatbot' ); ?>');">
								<span class="dashicons dashicons-trash"></span>
							</a>
						</td>
					</tr>
					<tr class="ssc-detail-row" hidden>
						<td colspan="9">
							<div class="ssc-detail">
								<div class="ssc-detail__block">
									<h4><?php esc_html_e( 'شرح کامل', 'smart-support-chatbot' ); ?></h4>
									<p><?php echo nl2br( esc_html( $row->description ) ); ?></p>
								</div>
								<?php if ( $has_extra ) : ?>
									<div class="ssc-detail__block">
										<h4><?php esc_html_e( 'اطلاعات استاندارد عارضه (ADR)', 'smart-support-chatbot' ); ?></h4>
										<dl class="ssc-detail__grid">
											<?php foreach ( $adr_fields as $fk => $fl ) : ?>
												<?php if ( ! empty( $row->$fk ) ) : ?>
													<dt><?php echo esc_html( $fl ); ?></dt>
													<dd><?php echo esc_html( $row->$fk ); ?></dd>
												<?php endif; ?>
											<?php endforeach; ?>
										</dl>
									</div>
								<?php endif; ?>
							</div>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>

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
