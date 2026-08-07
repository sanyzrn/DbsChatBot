<?php
/**
 * نمای داشبورد آماری.
 *
 * @package SmartSupportChatbot
 * @var array $counts       شمارش درخواست‌ها بر اساس نوع.
 * @var array $chat_stats   آمار گفتگوها.
 * @var array $product_subs شمارش درخواست‌ها بر اساس محصول.
 * @var array $recent       آخرین درخواست‌ها.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$total_chats   = isset( $chat_stats['total'] ) ? (int) $chat_stats['total'] : 0;
$adr_count     = isset( $counts['گزارش عوارض دارویی'] ) ? (int) $counts['گزارش عوارض دارویی'] : 0;
$consult_count = isset( $counts['درخواست مشاوره'] ) ? (int) $counts['درخواست مشاوره'] : 0;
$total_subs    = isset( $counts['total'] ) ? (int) $counts['total'] : 0;

// محبوب‌ترین محصولات: ترکیب تعامل گفتگو و درخواست‌ها بر اساس نام محصول.
$popular = array();
foreach ( (array) ( isset( $chat_stats['by_product'] ) ? $chat_stats['by_product'] : array() ) as $name => $c ) {
	$popular[ $name ] = ( isset( $popular[ $name ] ) ? $popular[ $name ] : 0 ) + (int) $c;
}
foreach ( (array) $product_subs as $name => $c ) {
	$popular[ $name ] = ( isset( $popular[ $name ] ) ? $popular[ $name ] : 0 ) + (int) $c;
}
arsort( $popular );
$popular     = array_slice( $popular, 0, 6, true );
$popular_max = $popular ? max( $popular ) : 0;

// روند گفتگوها در ۱۴ روز اخیر.
$daily      = isset( $chat_stats['daily'] ) && is_array( $chat_stats['daily'] ) ? $chat_stats['daily'] : array();
$trend      = array();
$today_dt   = new DateTimeImmutable( 'now', wp_timezone() ); // تاریخ محلی، هم‌راستا با کلیدهای روزانهٔ record_chat.
for ( $i = 13; $i >= 0; $i-- ) {
	$d           = $today_dt->modify( "-{$i} days" )->format( 'Y-m-d' );
	$trend[ $d ] = isset( $daily[ $d ] ) ? (int) $daily[ $d ] : 0;
}
$trend_max = $trend ? max( $trend ) : 0;

$status_labels = array(
	'new'         => __( 'جدید', 'smart-support-chatbot' ),
	'in_progress' => __( 'در حال پیگیری', 'smart-support-chatbot' ),
	'done'        => __( 'انجام شده', 'smart-support-chatbot' ),
	'archived'    => __( 'بایگانی', 'smart-support-chatbot' ),
);
?>
<div class="wrap ssc-admin" dir="rtl">
	<h1 class="ssc-admin__title">
		<span class="dashicons dashicons-chart-area"></span>
		<?php esc_html_e( 'داشبورد دستیار هوشمند', 'smart-support-chatbot' ); ?>
		<span class="ssc-admin__ver">v<?php echo esc_html( SSC_CHATBOT_VERSION ); ?></span>
	</h1>
	<p class="description"><?php esc_html_e( 'نمای کلی عملکرد دستیار هوشمند: گفتگوها، درخواست‌ها، بازخوردها و رضایت کاربران.', 'smart-support-chatbot' ); ?></p>

	<!-- کارت‌های آماری -->
	<div class="ssc-kpi-grid">
		<div class="ssc-kpi ssc-kpi--blue">
			<span class="ssc-kpi__icon dashicons dashicons-format-chat"></span>
			<span class="ssc-kpi__num"><?php echo esc_html( number_format_i18n( $total_chats ) ); ?></span>
			<span class="ssc-kpi__label"><?php esc_html_e( 'تعداد گفتگوها', 'smart-support-chatbot' ); ?></span>
		</div>
		<div class="ssc-kpi ssc-kpi--red">
			<span class="ssc-kpi__icon dashicons dashicons-warning"></span>
			<span class="ssc-kpi__num"><?php echo esc_html( number_format_i18n( $adr_count ) ); ?></span>
			<span class="ssc-kpi__label"><?php esc_html_e( 'گزارش عوارض دارویی', 'smart-support-chatbot' ); ?></span>
		</div>
		<div class="ssc-kpi ssc-kpi--purple">
			<span class="ssc-kpi__icon dashicons dashicons-sos"></span>
			<span class="ssc-kpi__num"><?php echo esc_html( number_format_i18n( $consult_count ) ); ?></span>
			<span class="ssc-kpi__label"><?php esc_html_e( 'درخواست مشاوره', 'smart-support-chatbot' ); ?></span>
		</div>
		<div class="ssc-kpi ssc-kpi--green">
			<span class="ssc-kpi__icon dashicons dashicons-list-view"></span>
			<span class="ssc-kpi__num"><?php echo esc_html( number_format_i18n( $total_subs ) ); ?></span>
			<span class="ssc-kpi__label"><?php esc_html_e( 'کل درخواست‌های ثبت‌شده', 'smart-support-chatbot' ); ?></span>
		</div>
	</div>

	<?php $ins = isset( $insights ) ? $insights : array(); ?>
	<div class="ssc-insights">
		<a class="ssc-insight ssc-insight--amber" href="<?php echo esc_url( admin_url( 'admin.php?page=smart-support-chatbot-chatlog&source=unanswered' ) ); ?>">
			<span class="ssc-insight__num"><?php echo esc_html( number_format_i18n( isset( $ins['unanswered'] ) ? $ins['unanswered'] : 0 ) ); ?></span>
			<span class="ssc-insight__label">🔍 <?php esc_html_e( 'سوالات بی‌پاسخ', 'smart-support-chatbot' ); ?></span>
		</a>
		<a class="ssc-insight" href="<?php echo esc_url( admin_url( 'admin.php?page=smart-support-chatbot-qa' ) ); ?>">
			<span class="ssc-insight__num"><?php echo esc_html( number_format_i18n( isset( $ins['qa_count'] ) ? $ins['qa_count'] : 0 ) ); ?></span>
			<span class="ssc-insight__label">🗂️ <?php esc_html_e( 'پاسخ در بانک', 'smart-support-chatbot' ); ?></span>
		</a>
		<div class="ssc-insight ssc-insight--green">
			<span class="ssc-insight__num">👍 <?php echo esc_html( number_format_i18n( isset( $ins['feedback']['up'] ) ? $ins['feedback']['up'] : 0 ) ); ?> &nbsp; 👎 <?php echo esc_html( number_format_i18n( isset( $ins['feedback']['down'] ) ? $ins['feedback']['down'] : 0 ) ); ?></span>
			<span class="ssc-insight__label">💬 <?php esc_html_e( 'بازخورد پاسخ‌ها', 'smart-support-chatbot' ); ?></span>
		</div>
		<?php $csat = isset( $ins['csat'] ) ? $ins['csat'] : array( 'avg' => 0, 'count' => 0 ); ?>
		<div class="ssc-insight">
			<span class="ssc-insight__num">⭐ <?php echo esc_html( $csat['count'] > 0 ? number_format_i18n( $csat['avg'], 1 ) : '—' ); ?></span>
			<span class="ssc-insight__label"><?php esc_html_e( 'رضایت گفتگو', 'smart-support-chatbot' ); ?><?php echo $csat['count'] > 0 ? ' (' . esc_html( number_format_i18n( $csat['count'] ) ) . ' ' . esc_html__( 'رأی', 'smart-support-chatbot' ) . ')' : ''; ?></span>
		</div>
		<a class="ssc-insight ssc-insight--red" href="<?php echo esc_url( admin_url( 'admin.php?page=smart-support-chatbot-submissions&type=' . rawurlencode( 'گزارش عوارض دارویی' ) ) ); ?>">
			<span class="ssc-insight__num"><?php echo esc_html( number_format_i18n( isset( $ins['serious'] ) ? $ins['serious'] : 0 ) ); ?></span>
			<span class="ssc-insight__label">🚨 <?php esc_html_e( 'عوارض جدی', 'smart-support-chatbot' ); ?></span>
		</a>
	</div>

	<div class="ssc-dash-cols">
		<!-- محبوب‌ترین محصولات -->
		<div class="ssc-card">
			<h3 class="ssc-card__title"><span class="dashicons dashicons-star-filled"></span> <?php esc_html_e( 'محبوب‌ترین محصولات', 'smart-support-chatbot' ); ?></h3>
			<?php if ( empty( $popular ) ) : ?>
				<p class="ssc-card__empty"><?php esc_html_e( 'هنوز داده‌ای ثبت نشده است.', 'smart-support-chatbot' ); ?></p>
			<?php else : ?>
				<ul class="ssc-bars">
					<?php foreach ( $popular as $name => $c ) : ?>
						<?php $pct = $popular_max > 0 ? round( ( $c / $popular_max ) * 100 ) : 0; ?>
						<li class="ssc-bar">
							<div class="ssc-bar__head">
								<span class="ssc-bar__name"><?php echo esc_html( $name ); ?></span>
								<span class="ssc-bar__val"><?php echo esc_html( number_format_i18n( $c ) ); ?></span>
							</div>
							<div class="ssc-bar__track"><span class="ssc-bar__fill" style="width: <?php echo esc_attr( $pct ); ?>%"></span></div>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>

		<!-- روند گفتگوها -->
		<div class="ssc-card">
			<h3 class="ssc-card__title"><span class="dashicons dashicons-chart-bar"></span> <?php esc_html_e( 'روند گفتگوها (۱۴ روز اخیر)', 'smart-support-chatbot' ); ?></h3>
			<?php if ( 0 === $trend_max ) : ?>
				<p class="ssc-card__empty"><?php esc_html_e( 'در ۱۴ روز اخیر گفتگویی ثبت نشده است.', 'smart-support-chatbot' ); ?></p>
			<?php else : ?>
				<div class="ssc-trend" role="img" aria-label="<?php esc_attr_e( 'نمودار روند گفتگوها', 'smart-support-chatbot' ); ?>">
					<?php foreach ( $trend as $d => $v ) : ?>
						<?php $h = $trend_max > 0 ? max( 4, round( ( $v / $trend_max ) * 100 ) ) : 4; ?>
						<div class="ssc-trend__col" title="<?php echo esc_attr( $d . ' — ' . $v ); ?>">
							<div class="ssc-trend__bar" style="height: <?php echo esc_attr( $h ); ?>%"></div>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
	</div>

	<!-- آخرین درخواست‌ها -->
	<div class="ssc-card">
		<h3 class="ssc-card__title">
			<span class="dashicons dashicons-clock"></span> <?php esc_html_e( 'آخرین درخواست‌ها', 'smart-support-chatbot' ); ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=smart-support-chatbot-submissions' ) ); ?>" class="ssc-card__link"><?php esc_html_e( 'مشاهده همه ←', 'smart-support-chatbot' ); ?></a>
		</h3>
		<?php if ( empty( $recent ) ) : ?>
			<p class="ssc-card__empty"><?php esc_html_e( 'هنوز درخواستی ثبت نشده است.', 'smart-support-chatbot' ); ?></p>
		<?php else : ?>
			<table class="wp-list-table widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'نوع', 'smart-support-chatbot' ); ?></th>
						<th><?php esc_html_e( 'نام', 'smart-support-chatbot' ); ?></th>
						<th><?php esc_html_e( 'تلفن', 'smart-support-chatbot' ); ?></th>
						<th><?php esc_html_e( 'محصول', 'smart-support-chatbot' ); ?></th>
						<th><?php esc_html_e( 'وضعیت', 'smart-support-chatbot' ); ?></th>
						<th><?php esc_html_e( 'تاریخ', 'smart-support-chatbot' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $recent as $row ) : ?>
						<?php $is_adr = ( false !== mb_strpos( $row->type, 'عوارض' ) ); ?>
						<tr>
							<td><span class="ssc-badge <?php echo $is_adr ? 'ssc-badge--red' : 'ssc-badge--purple'; ?>"><?php echo esc_html( $row->type ); ?></span></td>
							<td><strong><?php echo esc_html( $row->name ); ?></strong></td>
							<td dir="ltr"><?php echo esc_html( $row->phone ); ?></td>
							<td><?php echo $row->product ? esc_html( $row->product ) : '—'; ?></td>
							<td><?php echo esc_html( isset( $status_labels[ $row->status ] ) ? $status_labels[ $row->status ] : $row->status ); ?></td>
							<td><?php echo esc_html( $row->created_at ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
</div>
