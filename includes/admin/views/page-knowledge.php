<?php
/**
 * Business Knowledge view.
 *
 * @package SmartSupportChatbot
 * @var array $business  Business profile.
 * @var array $items     Knowledge items.
 * @var array $products  Product catalog.
 * @var array $kb_docs   KB documents.
 * @var int   $kb_count  KB chunk count.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$saved = isset( $_GET['saved'] ) ? (int) $_GET['saved'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
$kb_state = isset( $_GET['kb'] ) ? sanitize_key( wp_unslash( $_GET['kb'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
?>
<div class="ssc-page">
	<header class="ssc-page__head">
		<div>
			<h1><?php esc_html_e( 'Business Knowledge', 'smart-support-chatbot' ); ?></h1>
			<p class="ssc-page__sub"><?php esc_html_e( 'The facts your assistant relies on. Everything you write here reaches the AI on every answer.', 'smart-support-chatbot' ); ?></p>
		</div>
	</header>

	<?php if ( $saved ) : ?>
		<div class="ssc-notice ssc-notice--success" role="status"><?php esc_html_e( 'Knowledge saved.', 'smart-support-chatbot' ); ?></div>
	<?php endif; ?>
	<?php if ( 'added' === $kb_state ) : ?>
		<div class="ssc-notice ssc-notice--success" role="status"><?php echo esc_html( sprintf( __( 'Document imported (%d chunks).', 'smart-support-chatbot' ), isset( $_GET['chunks'] ) ? (int) $_GET['chunks'] : 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only. ?></div>
	<?php elseif ( 'toobig' === $kb_state ) : ?>
		<div class="ssc-notice ssc-notice--error" role="alert"><?php esc_html_e( 'The file is larger than 2 MB and was NOT imported.', 'smart-support-chatbot' ); ?></div>
	<?php elseif ( 'badtype' === $kb_state ) : ?>
		<div class="ssc-notice ssc-notice--error" role="alert"><?php esc_html_e( 'Only .txt, .md, .csv and .json files can be imported.', 'smart-support-chatbot' ); ?></div>
	<?php elseif ( 'failed' === $kb_state ) : ?>
		<div class="ssc-notice ssc-notice--error" role="alert"><?php esc_html_e( 'The page could not be imported (check the URL or try later).', 'smart-support-chatbot' ); ?></div>
	<?php elseif ( 'deleted' === $kb_state ) : ?>
		<div class="ssc-notice ssc-notice--info" role="status"><?php esc_html_e( 'Document removed.', 'smart-support-chatbot' ); ?></div>
	<?php endif; ?>

	<form method="post" class="ssc-form" enctype="multipart/form-data">
		<?php wp_nonce_field( 'ssc_knowledge' ); ?>
		<input type="hidden" name="ssc_knowledge_save" value="1" />

		<section class="ssc-card">
			<h2><?php esc_html_e( 'Business profile', 'smart-support-chatbot' ); ?></h2>
			<p class="ssc-card__sub"><?php esc_html_e( 'Always included in the assistant\'s instructions.', 'smart-support-chatbot' ); ?></p>
			<div class="ssc-grid ssc-grid--2">
				<?php
				$fields = array(
					'org_name'   => __( 'Official organization name', 'smart-support-chatbot' ),
					'brand_name' => __( 'Brand / trading name', 'smart-support-chatbot' ),
					'category'   => __( 'Business category', 'smart-support-chatbot' ),
					'industry'   => __( 'Industry', 'smart-support-chatbot' ),
					'url'        => __( 'Website', 'smart-support-chatbot' ),
					'location'   => __( 'Location', 'smart-support-chatbot' ),
					'phone'      => __( 'Contact phone', 'smart-support-chatbot' ),
					'email'      => __( 'Contact email', 'smart-support-chatbot' ),
					'support_phone' => __( 'Support phone', 'smart-support-chatbot' ),
					'support_email' => __( 'Support email', 'smart-support-chatbot' ),
					'hours'      => __( 'Working hours', 'smart-support-chatbot' ),
					'assistant_name' => __( 'Assistant name', 'smart-support-chatbot' ),
					'assistant_role' => __( 'Assistant role', 'smart-support-chatbot' ),
					'language'   => __( 'Primary answer language', 'smart-support-chatbot' ),
				);
				foreach ( $fields as $key => $label ) :
					$type = in_array( $key, array( 'url', 'email', 'support_email' ), true ) ? ( 'url' === $key ? 'url' : 'email' ) : 'text';
					?>
					<div class="ssc-field">
						<label for="b_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label>
						<input id="b_<?php echo esc_attr( $key ); ?>" name="business[<?php echo esc_attr( $key ); ?>]" type="<?php echo esc_attr( $type ); ?>" dir="<?php echo in_array( $key, array( 'url', 'email', 'support_email', 'phone', 'support_phone', 'language' ), true ) ? 'ltr' : 'auto'; ?>" value="<?php echo esc_attr( isset( $business[ $key ] ) ? $business[ $key ] : '' ); ?>" />
					</div>
				<?php endforeach; ?>
			</div>
			<div class="ssc-field">
				<label for="b_tone"><?php esc_html_e( 'Communication tone', 'smart-support-chatbot' ); ?></label>
				<select id="b_tone" name="business[tone]">
					<?php foreach ( array( 'professional' => __( 'Professional', 'smart-support-chatbot' ), 'friendly' => __( 'Friendly', 'smart-support-chatbot' ), 'formal' => __( 'Formal', 'smart-support-chatbot' ), 'casual' => __( 'Casual', 'smart-support-chatbot' ) ) as $t_id => $t_label ) : ?>
						<option value="<?php echo esc_attr( $t_id ); ?>" <?php selected( $business['tone'], $t_id ); ?>><?php echo esc_html( $t_label ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<?php foreach ( array( 'description' => __( 'What does your organization do?', 'smart-support-chatbot' ), 'products' => __( 'Products & services overview', 'smart-support-chatbot' ), 'differentiators' => __( 'Differentiators', 'smart-support-chatbot' ) ) as $key => $label ) : ?>
				<div class="ssc-field">
					<label for="b_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label>
					<textarea id="b_<?php echo esc_attr( $key ); ?>" name="business[<?php echo esc_attr( $key ); ?>]" rows="3"><?php echo esc_textarea( isset( $business[ $key ] ) ? $business[ $key ] : '' ); ?></textarea>
				</div>
			<?php endforeach; ?>
		</section>

		<section class="ssc-card">
			<h2><?php esc_html_e( 'Knowledge entries', 'smart-support-chatbot' ); ?></h2>
			<p class="ssc-card__sub"><?php esc_html_e( 'Short, factual reference blocks the assistant quotes verbatim. (Policies, prices, hours, FAQs…)', 'smart-support-chatbot' ); ?></p>
			<div id="ssc-ki-list" class="ssc-ki-list">
				<?php $rows = $items ? $items : array( array( 'type' => 'general', 'title' => '', 'content' => '' ) ); ?>
				<?php foreach ( $rows as $i => $item ) : ?>
					<div class="ssc-ki">
						<input type="hidden" name="ki[<?php echo esc_attr( (string) $i ); ?>][id]" value="<?php echo esc_attr( isset( $item['id'] ) ? $item['id'] : '' ); ?>" />
						<div class="ssc-ki__row">
							<select name="ki[<?php echo esc_attr( (string) $i ); ?>][type]" aria-label="<?php esc_attr_e( 'Type', 'smart-support-chatbot' ); ?>">
								<?php foreach ( array( 'general' => __( 'General', 'smart-support-chatbot' ), 'product' => __( 'Products', 'smart-support-chatbot' ), 'service' => __( 'Services', 'smart-support-chatbot' ), 'faq' => __( 'FAQ', 'smart-support-chatbot' ), 'policy' => __( 'Policies', 'smart-support-chatbot' ), 'support' => __( 'Support', 'smart-support-chatbot' ), 'custom' => __( 'Custom', 'smart-support-chatbot' ) ) as $t_id => $t_label ) : ?>
									<option value="<?php echo esc_attr( $t_id ); ?>" <?php selected( isset( $item['type'] ) ? $item['type'] : 'general', $t_id ); ?>><?php echo esc_html( $t_label ); ?></option>
								<?php endforeach; ?>
							</select>
							<input type="text" name="ki[<?php echo esc_attr( (string) $i ); ?>][title]" value="<?php echo esc_attr( isset( $item['title'] ) ? $item['title'] : '' ); ?>" placeholder="<?php esc_attr_e( 'Title', 'smart-support-chatbot' ); ?>" />
							<button type="button" class="ssc-ki__remove" aria-label="<?php esc_attr_e( 'Remove', 'smart-support-chatbot' ); ?>">×</button>
						</div>
						<textarea name="ki[<?php echo esc_attr( (string) $i ); ?>][content]" rows="3" placeholder="<?php esc_attr_e( 'Facts…', 'smart-support-chatbot' ); ?>"><?php echo esc_textarea( isset( $item['content'] ) ? $item['content'] : '' ); ?></textarea>
					</div>
				<?php endforeach; ?>
			</div>
			<button type="button" class="ssc-btn ssc-btn--ghost ssc-ki__add" data-target="#ssc-ki-list"><?php esc_html_e( '+ Add entry', 'smart-support-chatbot' ); ?></button>
		</section>

		<section class="ssc-card">
			<h2><?php esc_html_e( 'Products & services', 'smart-support-chatbot' ); ?></h2>
			<p class="ssc-card__sub"><?php esc_html_e( 'When a visitor asks about a product, its summary and attributes are injected into the answer context.', 'smart-support-chatbot' ); ?></p>
			<div id="ssc-product-list" class="ssc-products">
				<?php if ( $products ) : ?>
					<?php foreach ( $products as $i => $p ) : ?>
						<div class="ssc-product">
							<div class="ssc-ki__row">
								<input type="hidden" name="products[<?php echo esc_attr( (string) $i ); ?>][id]" value="<?php echo esc_attr( isset( $p['id'] ) ? $p['id'] : '' ); ?>" />
								<input type="text" name="products[<?php echo esc_attr( (string) $i ); ?>][name]" value="<?php echo esc_attr( isset( $p['name'] ) ? $p['name'] : '' ); ?>" placeholder="<?php esc_attr_e( 'Product / service name', 'smart-support-chatbot' ); ?>" />
								<input type="url" name="products[<?php echo esc_attr( (string) $i ); ?>][brochure]" dir="ltr" value="<?php echo esc_attr( isset( $p['brochure'] ) ? $p['brochure'] : '' ); ?>" placeholder="<?php esc_attr_e( 'Brochure link (optional)', 'smart-support-chatbot' ); ?>" />
								<input type="url" name="products[<?php echo esc_attr( (string) $i ); ?>][image]" dir="ltr" value="<?php echo esc_attr( isset( $p['image'] ) ? $p['image'] : '' ); ?>" placeholder="<?php esc_attr_e( 'Image URL (optional)', 'smart-support-chatbot' ); ?>" />
								<button type="button" class="ssc-ki__remove" aria-label="<?php esc_attr_e( 'Remove product', 'smart-support-chatbot' ); ?>">×</button>
							</div>
							<textarea name="products[<?php echo esc_attr( (string) $i ); ?>][summary]" rows="2" placeholder="<?php esc_attr_e( 'What is it, key facts, terms…', 'smart-support-chatbot' ); ?>"><?php echo esc_textarea( isset( $p['summary'] ) ? $p['summary'] : '' ); ?></textarea>
							<div class="ssc-product__attrs">
								<?php $attrs = ! empty( $p['attributes'] ) && is_array( $p['attributes'] ) ? $p['attributes'] : array( '' => '' ); ?>
								<?php foreach ( $attrs as $ak => $av ) : ?>
									<div class="ssc-product__attrrow">
										<input type="text" name="product_attributes[<?php echo esc_attr( (string) $i ); ?>][]" value="<?php echo esc_attr( $ak ); ?>" placeholder="<?php esc_attr_e( 'Attribute (e.g. warranty)', 'smart-support-chatbot' ); ?>" />
										<input type="text" name="product_attributes[<?php echo esc_attr( (string) $i ); ?>][]" value="<?php echo esc_attr( $av ); ?>" placeholder="<?php esc_attr_e( 'Value', 'smart-support-chatbot' ); ?>" />
									</div>
								<?php endforeach; ?>
							</div>
						</div>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>
			<button type="button" class="ssc-btn ssc-btn--ghost ssc-product__add" data-target="#ssc-product-list"><?php esc_html_e( '+ Add product', 'smart-support-chatbot' ); ?></button>
		</section>

		<div class="ssc-form__actions">
			<button type="submit" class="ssc-btn ssc-btn--primary"><?php esc_html_e( 'Save knowledge', 'smart-support-chatbot' ); ?></button>
		</div>
	</form>

	<section class="ssc-card ssc-mt">
		<h2><?php esc_html_e( 'Long documents (knowledge base)', 'smart-support-chatbot' ); ?></h2>
		<p class="ssc-card__sub"><?php echo esc_html( sprintf( __( '%d chunks from %d documents. Relevant pieces are retrieved automatically per question.', 'smart-support-chatbot' ), $kb_count, count( $kb_docs ) ) ); ?></p>

		<?php if ( $kb_docs ) : ?>
			<table class="ssc-table">
				<thead>
					<tr><th><?php esc_html_e( 'Document', 'smart-support-chatbot' ); ?></th><th><?php esc_html_e( 'Chunks', 'smart-support-chatbot' ); ?></th><th><?php esc_html_e( 'Added', 'smart-support-chatbot' ); ?></th><th></th></tr>
				</thead>
				<tbody>
					<?php foreach ( $kb_docs as $doc ) : ?>
						<tr>
							<td><?php echo esc_html( $doc['source_title'] ? $doc['source_title'] : $doc['doc_id'] ); ?></td>
							<td><?php echo esc_html( number_format_i18n( (int) $doc['chunks'] ) ); ?></td>
							<td><?php echo esc_html( mysql2date( get_option( 'date_format' ), $doc['created_at'] ) ); ?></td>
							<td><a class="ssc-link--danger" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'ssc_kb_action' => 'delete', 'doc' => $doc['doc_id'] ) ), 'ssc_kb_' . $doc['doc_id'] ) ); ?>"><?php esc_html_e( 'Remove', 'smart-support-chatbot' ); ?></a></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

		<div class="ssc-kb-import">
			<form method="post" class="ssc-kb-import__form">
				<?php wp_nonce_field( 'ssc_kb' ); ?>
				<input type="hidden" name="ssc_kb_import_url" value="1" />
				<input type="url" name="kb_url" dir="ltr" placeholder="https://example.com/about" aria-label="<?php esc_attr_e( 'Page URL to import', 'smart-support-chatbot' ); ?>" />
				<button type="submit" class="ssc-btn ssc-btn--secondary"><?php esc_html_e( 'Import page', 'smart-support-chatbot' ); ?></button>
			</form>
			<form method="post" class="ssc-kb-import__form" enctype="multipart/form-data">
				<?php wp_nonce_field( 'ssc_kb' ); ?>
				<input type="hidden" name="ssc_kb_import_file" value="1" />
				<input type="file" name="kb_file" accept=".txt,.md,.csv,.json" aria-label="<?php esc_attr_e( 'Document file to import', 'smart-support-chatbot' ); ?>" />
				<button type="submit" class="ssc-btn ssc-btn--secondary"><?php esc_html_e( 'Upload file', 'smart-support-chatbot' ); ?></button>
			</form>
		</div>
	</section>
</div>
