<?php
/**
 * Analytics view (analytics module). Insights, not decoration.
 *
 * @package SmartSupportChatbot
 * @var array $data Insights.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="ssc-page">
	<header class="ssc-page__head">
		<div>
			<h1><?php esc_html_e( 'Analytics', 'nexachat-ai' ); ?></h1>
			<p class="ssc-page__sub"><?php esc_html_e( 'Last 14 days. Only what helps you improve the assistant.', 'nexachat-ai' ); ?></p>
		</div>
	</header>

	<section class="ssc-tiles">
		<div class="ssc-tile">
			<strong class="ssc-tile__num"><?php echo esc_html( number_format_i18n( $data['total14'] ) ); ?></strong>
			<span><?php esc_html_e( 'Conversations (14 days)', 'nexachat-ai' ); ?></span>
		</div>
		<?php if ( $data['csat'] ) : ?>
			<div class="ssc-tile">
				<strong class="ssc-tile__num"><?php echo esc_html( number_format_i18n( $data['csat']['avg'], 2 ) ); ?> / 5</strong>
				<span><?php echo esc_html( sprintf( _n( '%d rating', '%d ratings', $data['csat']['count'], 'nexachat-ai' ), $data['csat']['count'] ) ); ?></span>
			</div>
		<?php endif; ?>
		<div class="ssc-tile">
			<strong class="ssc-tile__num"><?php echo esc_html( number_format_i18n( count( $data['unanswered'] ) ) ); ?></strong>
			<span><?php esc_html_e( 'Unanswered questions (14 days)', 'nexachat-ai' ); ?></span>
		</div>
	</section>

	<section class="ssc-card">
		<h2><?php esc_html_e( 'Conversation volume', 'nexachat-ai' ); ?></h2>
		<div class="ssc-bars" role="img" aria-label="<?php esc_attr_e( 'Daily conversations over the last 14 days', 'nexachat-ai' ); ?>">
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
			<h2><?php esc_html_e( 'Most-discussed products', 'nexachat-ai' ); ?></h2>
			<?php if ( $data['top_products'] ) : ?>
				<div class="ssc-table-scroll" tabindex="0" role="region" aria-label="<?php esc_attr_e( 'Scrollable table', 'nexachat-ai' ); ?>"><table class="ssc-table">
					<tbody>
						<?php foreach ( $data['top_products'] as $row ) : ?>
							<tr><td><?php echo esc_html( $row['name'] ); ?></td><td><?php echo esc_html( number_format_i18n( $row['count'] ) ); ?></td></tr>
						<?php endforeach; ?>
					</tbody>
				</table></div>
			<?php else : ?>
				<p class="ssc-card__sub"><?php esc_html_e( 'No product conversations recorded yet.', 'nexachat-ai' ); ?></p>
			<?php endif; ?>
		</section>

		<section class="ssc-card">
			<h2><?php esc_html_e( 'Unanswered questions', 'nexachat-ai' ); ?></h2>
			<p class="ssc-card__sub"><?php esc_html_e( 'Knowledge gaps — answer these in the FAQ bank or knowledge page.', 'nexachat-ai' ); ?></p>
			<?php if ( $data['unanswered'] ) : ?>
				<div class="ssc-table-scroll" tabindex="0" role="region" aria-label="<?php esc_attr_e( 'Scrollable table', 'nexachat-ai' ); ?>"><table class="ssc-table">
					<tbody>
						<?php foreach ( $data['unanswered'] as $row ) : ?>
							<tr><td class="ssc-td-truncate"><?php echo esc_html( mb_substr( (string) $row['question'], 0, 100 ) ); ?></td><td>×<?php echo esc_html( number_format_i18n( (int) $row['n'] ) ); ?></td></tr>
						<?php endforeach; ?>
					</tbody>
				</table></div>
			<?php else : ?>
				<p class="ssc-card__sub"><?php esc_html_e( 'Every question got an answer. 👏', 'nexachat-ai' ); ?></p>
			<?php endif; ?>
		</section>
	</div>
</div>
