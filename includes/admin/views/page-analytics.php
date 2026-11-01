<?php
/**
 * Analytics view (analytics module). Insights, not decoration: four summary
 * tiles (two with rings), the daily volume, and two short lists.
 *
 * @package Vazho
 * @var array $data Insights.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$ssc_unanswered_total = 0;
foreach ( $data['unanswered'] as $ssc_row ) {
	$ssc_unanswered_total += (int) $ssc_row['n'];
}
$ssc_history  = SSC_Modules::is_active( 'history' );
$ssc_answered = ( $ssc_history && $data['total14'] > 0 ) ? max( 0, min( 100, (int) round( 100 * ( $data['total14'] - $ssc_unanswered_total ) / $data['total14'] ) ) ) : -1;
$ssc_faq_url  = SSC_Modules::is_active( 'faq' ) ? admin_url( 'admin.php?page=ssc-faq' ) : admin_url( 'admin.php?page=ssc-knowledge' );
?>
<div class="ssc-page ssc-analytics">
	<header class="ssc-page__head">
		<div>
			<h1><?php esc_html_e( 'Analytics', 'vazho' ); ?></h1>
			<p class="ssc-page__sub"><?php esc_html_e( 'Last 14 days. Only what helps you improve the assistant.', 'vazho' ); ?></p>
		</div>
	</header>

	<section class="ssc-hstats">
		<?php
		SSC_Admin_UI::stat( 'chat', 'cyan', number_format_i18n( $data['total14'] ), __( 'Conversations (14 days)', 'vazho' ) );
		if ( $data['csat'] && $data['csat']['count'] > 0 ) {
			SSC_Admin_UI::stat( 'star', 'amber', number_format_i18n( $data['csat']['avg'], 2 ) . ' / 5', sprintf( /* translators: %d: number of ratings. */ _n( '%d rating', '%d ratings', $data['csat']['count'], 'vazho' ), $data['csat']['count'] ), '', (int) round( 20 * (float) $data['csat']['avg'] ) );
		} elseif ( $data['csat'] ) {
			SSC_Admin_UI::stat( 'star', 'amber', '—', __( 'No ratings yet', 'vazho' ) );
		}
		if ( $ssc_answered >= 0 ) {
			SSC_Admin_UI::stat( 'check', 'violet', $ssc_answered . '%', __( 'Answered', 'vazho' ), '', $ssc_answered );
		}
		SSC_Admin_UI::stat( 'search', count( $data['unanswered'] ) > 0 ? 'rose' : 'green', number_format_i18n( count( $data['unanswered'] ) ), __( 'Unanswered questions (14 days)', 'vazho' ) );
		?>
	</section>

	<section class="ssc-card ssc-barscard" data-ssc-hue="cyan">
		<?php SSC_Admin_UI::card_title( 'chat', 'cyan', __( 'Conversation volume', 'vazho' ) ); ?>
		<div class="ssc-bars" role="img" aria-label="<?php esc_attr_e( 'Daily conversations over the last 14 days', 'vazho' ); ?>">
			<?php
			$max = max( 1, max( $data['series14'] ) );
			foreach ( $data['series14'] as $day => $count ) :
				$h = (int) round( ( $count / $max ) * 100 );
				?>
				<div class="ssc-bars__col">
					<span class="ssc-bars__bar" style="height:<?php echo esc_attr( max( 2, $h ) ); ?>%"></span>
					<span class="ssc-bars__label"><?php echo esc_html( mysql2date( 'D', $day . ' 00:00:00' ) ); ?></span>
					<span class="ssc-bars__val"><?php echo esc_html( number_format_i18n( $count ) ); ?></span>
				</div>
			<?php endforeach; ?>
		</div>
	</section>

	<div class="ssc-grid ssc-grid--2">
		<section class="ssc-card">
			<?php SSC_Admin_UI::card_title( 'box', 'violet', __( 'Most-discussed products', 'vazho' ) ); ?>
			<?php if ( $data['top_products'] ) : ?>
				<?php $ssc_top = max( 1, (int) $data['top_products'][0]['count'] ); ?>
				<ul class="ssc-mini" data-ssc-hue="violet">
					<?php foreach ( $data['top_products'] as $row ) : ?>
						<li>
							<span class="ssc-mini__name"><?php echo esc_html( $row['name'] ); ?></span>
							<span class="ssc-pill"><?php echo esc_html( number_format_i18n( $row['count'] ) ); ?></span>
							<span class="ssc-mini__bar"><i style="width:<?php echo esc_attr( max( 3, (int) round( 100 * $row['count'] / $ssc_top ) ) ); ?>%"></i></span>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php else : ?>
				<p class="ssc-card__sub"><?php esc_html_e( 'No product conversations recorded yet.', 'vazho' ); ?></p>
			<?php endif; ?>
		</section>

		<section class="ssc-card">
			<?php SSC_Admin_UI::card_title( 'search', 'rose', __( 'Unanswered questions', 'vazho' ), __( 'Knowledge gaps — answer these in the FAQ bank or knowledge page.', 'vazho' ) ); ?>
			<?php if ( $data['unanswered'] ) : ?>
				<ul class="ssc-mini" data-ssc-hue="rose">
					<?php foreach ( $data['unanswered'] as $row ) : ?>
						<li>
							<span class="ssc-mini__name"><?php echo esc_html( mb_substr( (string) $row['question'], 0, 100 ) ); ?></span>
							<span class="ssc-pill">×<?php echo esc_html( number_format_i18n( (int) $row['n'] ) ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
				<p class="ssc-card__actions"><a class="ssc-btn ssc-btn--ghost" href="<?php echo esc_url( $ssc_faq_url ); ?>"><?php esc_html_e( 'Answer them', 'vazho' ); ?></a></p>
			<?php else : ?>
				<p class="ssc-card__sub"><?php esc_html_e( 'Every question got an answer. 👏', 'vazho' ); ?></p>
			<?php endif; ?>
		</section>
	</div>
</div>

