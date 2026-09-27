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
			<h1><?php esc_html_e( 'Business Knowledge', 'nexachat-ai' ); ?></h1>
			<p class="ssc-page__sub"><?php esc_html_e( 'The facts your assistant relies on. Everything you write here reaches the AI on every answer.', 'nexachat-ai' ); ?></p>
		</div>
	</header>

	<?php if ( $saved ) : ?>
		<div class="ssc-notice ssc-notice--success" role="status"><?php esc_html_e( 'Knowledge saved.', 'nexachat-ai' ); ?></div>
	<?php endif; ?>
	<?php if ( 'added' === $kb_state ) : ?>
		<div class="ssc-notice ssc-notice--success" role="status"><?php echo esc_html( sprintf( __( 'Document imported (%d chunks).', 'nexachat-ai' ), isset( $_GET['chunks'] ) ? (int) $_GET['chunks'] : 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only. ?></div>
	<?php elseif ( 'toobig' === $kb_state ) : ?>
		<div class="ssc-notice ssc-notice--error" role="alert"><?php esc_html_e( 'The file is larger than 2 MB and was NOT imported.', 'nexachat-ai' ); ?></div>
	<?php elseif ( 'badtype' === $kb_state ) : ?>
		<div class="ssc-notice ssc-notice--error" role="alert"><?php esc_html_e( 'Only .txt, .md, .csv and .json files can be imported.', 'nexachat-ai' ); ?></div>
	<?php elseif ( 'failed' === $kb_state ) : ?>
		<div class="ssc-notice ssc-notice--error" role="alert"><?php esc_html_e( 'The page could not be imported (check the URL or try later).', 'nexachat-ai' ); ?></div>
	<?php elseif ( 'deleted' === $kb_state ) : ?>
		<div class="ssc-notice ssc-notice--info" role="status"><?php esc_html_e( 'Document removed.', 'nexachat-ai' ); ?></div>
	<?php elseif ( 'semantic' === $kb_state ) : ?>
		<div class="ssc-notice ssc-notice--success" role="status"><?php esc_html_e( 'Search settings saved.', 'nexachat-ai' ); ?></div>
	<?php elseif ( 'semantic_error' === $kb_state ) : ?>
		<div class="ssc-notice ssc-notice--error" role="alert"><?php echo esc_html( __( 'Indexing failed:', 'nexachat-ai' ) . ' ' . (string) get_transient( 'ssc_kb_embed_error' ) ); ?></div>
	<?php endif; ?>

	<form method="post" class="ssc-form" enctype="multipart/form-data">
		<?php wp_nonce_field( 'ssc_knowledge' ); ?>
		<input type="hidden" name="ssc_knowledge_save" value="1" />

		<section class="ssc-card">
			<h2><?php esc_html_e( 'Business profile', 'nexachat-ai' ); ?></h2>
			<p class="ssc-card__sub"><?php esc_html_e( 'Always included in the assistant\'s instructions.', 'nexachat-ai' ); ?></p>
			<div class="ssc-grid ssc-grid--2">
				<?php
				$fields = array(
					'org_name'   => __( 'Official organization name', 'nexachat-ai' ),
					'brand_name' => __( 'Brand / trading name', 'nexachat-ai' ),
					'category'   => __( 'Business category', 'nexachat-ai' ),
					'industry'   => __( 'Industry', 'nexachat-ai' ),
					'url'        => __( 'Website', 'nexachat-ai' ),
					'location'   => __( 'Location', 'nexachat-ai' ),
					'phone'      => __( 'Contact phone', 'nexachat-ai' ),
					'email'      => __( 'Contact email', 'nexachat-ai' ),
					'support_phone' => __( 'Support phone', 'nexachat-ai' ),
					'support_email' => __( 'Support email', 'nexachat-ai' ),
					'hours'      => __( 'Working hours', 'nexachat-ai' ),
					'assistant_name' => __( 'Assistant name', 'nexachat-ai' ),
					'assistant_role' => __( 'Assistant role', 'nexachat-ai' ),
					'language'   => __( 'Primary answer language', 'nexachat-ai' ),
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
				<label for="b_tone"><?php esc_html_e( 'Communication tone', 'nexachat-ai' ); ?></label>
				<select id="b_tone" name="business[tone]">
					<?php foreach ( array( 'professional' => __( 'Professional', 'nexachat-ai' ), 'friendly' => __( 'Friendly', 'nexachat-ai' ), 'formal' => __( 'Formal', 'nexachat-ai' ), 'casual' => __( 'Casual', 'nexachat-ai' ) ) as $t_id => $t_label ) : ?>
						<option value="<?php echo esc_attr( $t_id ); ?>" <?php selected( $business['tone'], $t_id ); ?>><?php echo esc_html( $t_label ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<?php foreach ( array( 'description' => __( 'What does your organization do?', 'nexachat-ai' ), 'products' => __( 'Products & services overview', 'nexachat-ai' ), 'differentiators' => __( 'Differentiators', 'nexachat-ai' ) ) as $key => $label ) : ?>
				<div class="ssc-field">
					<label for="b_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label>
					<textarea id="b_<?php echo esc_attr( $key ); ?>" name="business[<?php echo esc_attr( $key ); ?>]" rows="3"><?php echo esc_textarea( isset( $business[ $key ] ) ? $business[ $key ] : '' ); ?></textarea>
				</div>
			<?php endforeach; ?>
		</section>

		<section class="ssc-card">
			<h2><?php esc_html_e( 'Knowledge entries', 'nexachat-ai' ); ?></h2>
			<p class="ssc-card__sub"><?php esc_html_e( 'Short, factual reference blocks the assistant quotes verbatim. (Policies, prices, hours, FAQs…)', 'nexachat-ai' ); ?></p>
			<div id="ssc-ki-list" class="ssc-ki-list">
				<?php $rows = $items ? $items : array( array( 'type' => 'general', 'title' => '', 'content' => '' ) ); ?>
				<?php foreach ( $rows as $i => $item ) : ?>
					<div class="ssc-ki">
						<input type="hidden" name="ki[<?php echo esc_attr( (string) $i ); ?>][id]" value="<?php echo esc_attr( isset( $item['id'] ) ? $item['id'] : '' ); ?>" />
						<div class="ssc-ki__row">
							<select name="ki[<?php echo esc_attr( (string) $i ); ?>][type]" aria-label="<?php esc_attr_e( 'Type', 'nexachat-ai' ); ?>">
								<?php foreach ( array( 'general' => __( 'General', 'nexachat-ai' ), 'product' => __( 'Products', 'nexachat-ai' ), 'service' => __( 'Services', 'nexachat-ai' ), 'faq' => __( 'FAQ', 'nexachat-ai' ), 'policy' => __( 'Policies', 'nexachat-ai' ), 'support' => __( 'Support', 'nexachat-ai' ), 'custom' => __( 'Custom', 'nexachat-ai' ) ) as $t_id => $t_label ) : ?>
									<option value="<?php echo esc_attr( $t_id ); ?>" <?php selected( isset( $item['type'] ) ? $item['type'] : 'general', $t_id ); ?>><?php echo esc_html( $t_label ); ?></option>
								<?php endforeach; ?>
							</select>
							<input type="text" name="ki[<?php echo esc_attr( (string) $i ); ?>][title]" value="<?php echo esc_attr( isset( $item['title'] ) ? $item['title'] : '' ); ?>" placeholder="<?php esc_attr_e( 'Title', 'nexachat-ai' ); ?>" />
							<button type="button" class="ssc-ki__remove" aria-label="<?php esc_attr_e( 'Remove', 'nexachat-ai' ); ?>">×</button>
						</div>
						<textarea name="ki[<?php echo esc_attr( (string) $i ); ?>][content]" rows="3" placeholder="<?php esc_attr_e( 'Facts…', 'nexachat-ai' ); ?>"><?php echo esc_textarea( isset( $item['content'] ) ? $item['content'] : '' ); ?></textarea>
					</div>
				<?php endforeach; ?>
			</div>
			<button type="button" class="ssc-btn ssc-btn--ghost ssc-ki__add" data-target="#ssc-ki-list"><?php esc_html_e( '+ Add entry', 'nexachat-ai' ); ?></button>
		</section>

		<section class="ssc-card">
			<h2><?php esc_html_e( 'Products & services', 'nexachat-ai' ); ?></h2>
			<p class="ssc-card__sub"><?php esc_html_e( 'When a visitor asks about a product, its summary and attributes are injected into the answer context.', 'nexachat-ai' ); ?></p>
			<?php
			/*
			 * One product row. Also rendered blank into a <template>, so "Add product"
			 * works when the list is still empty (there is no row to copy yet).
			 */
			$ssc_product_row = function ( $i, $p ) {
				?>
			<div class="ssc-product">
				<div class="ssc-ki__row">
					<input type="hidden" name="products[<?php echo esc_attr( (string) $i ); ?>][id]" value="<?php echo esc_attr( isset( $p['id'] ) ? $p['id'] : '' ); ?>" />
					<input type="text" name="products[<?php echo esc_attr( (string) $i ); ?>][name]" value="<?php echo esc_attr( isset( $p['name'] ) ? $p['name'] : '' ); ?>" placeholder="<?php esc_attr_e( 'Product / service name', 'nexachat-ai' ); ?>" />
					<input type="url" name="products[<?php echo esc_attr( (string) $i ); ?>][brochure]" dir="ltr" value="<?php echo esc_attr( isset( $p['brochure'] ) ? $p['brochure'] : '' ); ?>" placeholder="<?php esc_attr_e( 'Brochure link (optional)', 'nexachat-ai' ); ?>" />
					<input type="url" name="products[<?php echo esc_attr( (string) $i ); ?>][image]" dir="ltr" value="<?php echo esc_attr( isset( $p['image'] ) ? $p['image'] : '' ); ?>" placeholder="<?php esc_attr_e( 'Image URL (optional)', 'nexachat-ai' ); ?>" />
					<button type="button" class="ssc-ki__remove" aria-label="<?php esc_attr_e( 'Remove product', 'nexachat-ai' ); ?>">×</button>
				</div>
				<textarea name="products[<?php echo esc_attr( (string) $i ); ?>][summary]" rows="2" placeholder="<?php esc_attr_e( 'What is it, key facts, terms…', 'nexachat-ai' ); ?>"><?php echo esc_textarea( isset( $p['summary'] ) ? $p['summary'] : '' ); ?></textarea>
				<div class="ssc-product__attrs">
					<?php $attrs = ! empty( $p['attributes'] ) && is_array( $p['attributes'] ) ? $p['attributes'] : array( '' => '' ); ?>
					<?php foreach ( $attrs as $ak => $av ) : ?>
						<div class="ssc-product__attrrow">
							<input type="text" name="product_attributes[<?php echo esc_attr( (string) $i ); ?>][]" value="<?php echo esc_attr( $ak ); ?>" placeholder="<?php esc_attr_e( 'Attribute (e.g. warranty)', 'nexachat-ai' ); ?>" />
							<input type="text" name="product_attributes[<?php echo esc_attr( (string) $i ); ?>][]" value="<?php echo esc_attr( $av ); ?>" placeholder="<?php esc_attr_e( 'Value', 'nexachat-ai' ); ?>" />
						</div>
					<?php endforeach; ?>
				</div>
				<button type="button" class="ssc-btn ssc-btn--ghost ssc-btn--sm ssc-product__attradd"><?php esc_html_e( '+ Add attribute', 'nexachat-ai' ); ?></button>
			</div>
				<?php
			};
			?>
			<div id="ssc-product-list" class="ssc-products">
				<?php
				foreach ( $products as $i => $p ) {
					$ssc_product_row( $i, $p );
				}
				?>
			</div>
			<template id="ssc-product-template"><?php $ssc_product_row( 0, array() ); ?></template>
			<button type="button" class="ssc-btn ssc-btn--ghost ssc-product__add" data-target="#ssc-product-list" data-template="#ssc-product-template"><?php esc_html_e( '+ Add product', 'nexachat-ai' ); ?></button>
		</section>

		<div class="ssc-form__actions">
			<button type="submit" class="ssc-btn ssc-btn--primary"><?php esc_html_e( 'Save knowledge', 'nexachat-ai' ); ?></button>
		</div>
	</form>

	<section class="ssc-card ssc-mt">
		<h2><?php esc_html_e( 'Long documents (knowledge base)', 'nexachat-ai' ); ?></h2>
		<p class="ssc-card__sub"><?php echo esc_html( sprintf( __( '%d chunks from %d documents. Relevant pieces are retrieved automatically per question.', 'nexachat-ai' ), $kb_count, count( $kb_docs ) ) ); ?></p>

		<?php if ( $kb_docs ) : ?>
			<div class="ssc-table-scroll" tabindex="0" role="region" aria-label="<?php esc_attr_e( 'Scrollable table', 'nexachat-ai' ); ?>"><table class="ssc-table">
				<thead>
					<tr><th><?php esc_html_e( 'Document', 'nexachat-ai' ); ?></th><th><?php esc_html_e( 'Chunks', 'nexachat-ai' ); ?></th><th><?php esc_html_e( 'Added', 'nexachat-ai' ); ?></th><th></th></tr>
				</thead>
				<tbody>
					<?php foreach ( $kb_docs as $doc ) : ?>
						<tr>
							<td><?php echo esc_html( $doc['source_title'] ? $doc['source_title'] : $doc['doc_id'] ); ?></td>
							<td><?php echo esc_html( number_format_i18n( (int) $doc['chunks'] ) ); ?></td>
							<td><?php echo esc_html( SSC_Date::display( $doc['created_at'], false ) ); ?></td>
							<td><a class="ssc-link--danger" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'ssc_kb_action' => 'delete', 'doc' => $doc['doc_id'] ) ), 'ssc_kb_' . $doc['doc_id'] ) ); ?>"><?php esc_html_e( 'Remove', 'nexachat-ai' ); ?></a></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table></div>
		<?php endif; ?>

		<form method="post" class="ssc-kb-semantic">
			<?php wp_nonce_field( 'ssc_kb' ); ?>
			<input type="hidden" name="ssc_kb_semantic_save" value="1" />
			<h3><?php esc_html_e( 'Semantic search', 'nexachat-ai' ); ?></h3>
			<?php $ssc_embed_model = SSC_Embeddings::model(); ?>
			<?php if ( '' === $ssc_embed_model ) : ?>
				<p class="ssc-field__hint"><?php esc_html_e( 'Available with OpenAI, Gemini or an OpenAI-compatible custom endpoint. The connected provider has no embeddings API, so keyword search is used.', 'nexachat-ai' ); ?></p>
			<?php else : ?>
				<label class="ssc-check"><input type="checkbox" name="kb_semantic" value="yes" <?php checked( 'yes', SSC_Settings::get( 'kb_semantic', 'no' ) ); ?> /> <span><?php esc_html_e( 'Find answers by meaning, not only matching words (uses the provider\'s embeddings API: one small request per question)', 'nexachat-ai' ); ?></span></label>
				<?php if ( 'yes' === SSC_Settings::get( 'kb_semantic', 'no' ) ) : ?>
					<?php $ssc_pending = SSC_Schema::kb_pending_count( $ssc_embed_model ); ?>
					<p class="ssc-field__hint">
						<?php
						echo esc_html(
							0 === $ssc_pending
								? __( 'Index is up to date.', 'nexachat-ai' )
								/* translators: %d: number of chunks still waiting for indexing. */
								: sprintf( __( '%d chunks are waiting to be indexed (continues in the background).', 'nexachat-ai' ), $ssc_pending )
						);
						?>
					</p>
				<?php endif; ?>
			<?php endif; ?>
			<label class="ssc-check"><input type="checkbox" name="show_sources" value="yes" <?php checked( 'yes', SSC_Settings::get( 'show_sources', 'yes' ) ); ?> /> <span><?php esc_html_e( 'Show the source documents under AI answers', 'nexachat-ai' ); ?></span></label>
			<button type="submit" class="ssc-btn ssc-btn--secondary"><?php esc_html_e( 'Save and index now', 'nexachat-ai' ); ?></button>
		</form>

		<div class="ssc-kb-import">
			<form method="post" class="ssc-kb-import__form">
				<?php wp_nonce_field( 'ssc_kb' ); ?>
				<input type="hidden" name="ssc_kb_import_url" value="1" />
				<input type="url" name="kb_url" dir="ltr" placeholder="https://example.com/about" aria-label="<?php esc_attr_e( 'Page URL to import', 'nexachat-ai' ); ?>" />
				<button type="submit" class="ssc-btn ssc-btn--secondary"><?php esc_html_e( 'Import page', 'nexachat-ai' ); ?></button>
			</form>
			<form method="post" class="ssc-kb-import__form" enctype="multipart/form-data">
				<?php wp_nonce_field( 'ssc_kb' ); ?>
				<input type="hidden" name="ssc_kb_import_file" value="1" />
				<input type="file" name="kb_file" accept=".txt,.md,.csv,.json" aria-label="<?php esc_attr_e( 'Document file to import', 'nexachat-ai' ); ?>" />
				<button type="submit" class="ssc-btn ssc-btn--secondary"><?php esc_html_e( 'Upload file', 'nexachat-ai' ); ?></button>
			</form>
		</div>
	</section>
</div>
