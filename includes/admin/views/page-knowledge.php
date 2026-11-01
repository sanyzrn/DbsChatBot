<?php
/**
 * Business Knowledge view: a progress hero, a row of section tiles that act
 * as tabs, and one section at a time (no giant form).
 *
 * @package Vazho
 * @var array  $business  Business profile.
 * @var array  $items     Knowledge items.
 * @var array  $products  Product catalog.
 * @var array  $kb_docs   KB documents (every document, website pages included).
 * @var int    $kb_count  KB chunk count.
 * @var array  $site_docs Documents mirrored from the website.
 * @var array  $own_docs  Uploaded and imported documents.
 * @var array  $score     SSC_Knowledge_Score::compute() result.
 * @var string $kb_state  Result of the last action.
 * @var string $initial   Section to open first.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$saved    = isset( $_GET['saved'] ) ? (int) $_GET['saved'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
$kb_state = isset( $_GET['kb'] ) ? sanitize_key( wp_unslash( $_GET['kb'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
$ssc_bad  = isset( $_GET['malformed'] ) ? sanitize_text_field( wp_unslash( $_GET['malformed'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.

// Every entry carries an id (older ones get a stable one) and the form remembers what it was built from, so a stale tab cannot silently overwrite newer changes.
$items        = SSC_Merge::with_ids( 'ki', $items );
$products     = SSC_Merge::with_ids( 'products', $products );
$ssc_base     = SSC_Merge::snapshot( SSC_Settings::sanitize_business( $business ), $items, $products );
$ssc_conflict = isset( $_GET['conflict'] ) ? get_transient( SSC_Admin_Knowledge::conflict_key() ) : false; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.

$ssc_site_on  = SSC_Modules::is_active( 'sitesync' );
$ssc_suggest  = get_transient( 'ssc_suggest_' . get_current_user_id() );
if ( is_array( $ssc_suggest ) ) {
	$initial = 'ai';
}

/*
 * Section definitions: what each is called, one line on what it is for, and
 * the short line shown under its name in the tile.
 */
$ssc_meta = array(
	'identity'  => array(
		'icon'  => 'building',
		'title' => __( 'Your business', 'vazho' ),
		'tile'  => __( 'Name and what you do', 'vazho' ),
		'intro' => __( 'Who you are. The assistant introduces your business with these words.', 'vazho' ),
	),
	'contact'   => array(
		'icon'  => 'phone',
		'title' => __( 'Contact & hours', 'vazho' ),
		'tile'  => __( 'Phone, email, opening hours', 'vazho' ),
		'intro' => __( 'How people can reach you. The assistant shares these details when someone asks.', 'vazho' ),
	),
	'persona'   => array(
		'icon'  => 'user',
		'title' => __( 'Assistant persona', 'vazho' ),
		'tile'  => __( 'Name, role and manner', 'vazho' ),
		'intro' => __( 'Who is talking: the assistant’s name, role and way of speaking.', 'vazho' ),
	),
	'facts'     => array(
		'icon'  => 'book',
		'title' => __( 'Knowledge entries', 'vazho' ),
		'tile'  => __( 'Policies, prices, answers', 'vazho' ),
		'intro' => __( 'Short, reliable facts the assistant can quote: hours, policies, prices, answers to common questions.', 'vazho' ),
	),
	'products'  => array(
		'icon'  => 'box',
		'title' => __( 'Products & services', 'vazho' ),
		'tile'  => __( 'What you offer', 'vazho' ),
		'intro' => __( 'What you sell or offer. Add specifications so the answers are precise.', 'vazho' ),
	),
	'documents' => array(
		'icon'  => 'file',
		'title' => __( 'Documents', 'vazho' ),
		'tile'  => __( 'Brochures, catalogues, pages', 'vazho' ),
		'intro' => __( 'Upload brochures, catalogues or price lists. The assistant finds the relevant parts for each question.', 'vazho' ),
	),
	'website'   => array(
		'icon'  => 'globe',
		'title' => __( 'Your website', 'vazho' ),
		'tile'  => __( 'Learn from your own pages', 'vazho' ),
		'intro' => __( 'Let the assistant learn from your pages, posts and products and keep up with changes automatically.', 'vazho' ),
	),
	'ai'        => array(
		'icon'  => 'sparkles',
		'title' => __( 'AI helper', 'vazho' ),
		'tile'  => __( 'Let AI draft it for you', 'vazho' ),
		'intro' => __( 'Not sure what to write? The AI reads what you have and drafts questions and a persona for you to review.', 'vazho' ),
	),
);

/* Wording of the progress line, in one place so the script can reuse it. */
$ssc_fmt = array(
	'fields'      => __( '%1$d of %2$d filled', 'vazho' ),
	'none'        => __( 'Not started', 'vazho' ),
	'entries_one' => __( '1 entry', 'vazho' ),
	'entries'     => __( '%d entries', 'vazho' ),
	'products_one' => __( '1 product', 'vazho' ),
	'products'    => __( '%d products', 'vazho' ),
	'docs_one'    => __( '1 document', 'vazho' ),
	'docs'        => __( '%d documents', 'vazho' ),
	'pages_one'   => __( '1 page', 'vazho' ),
	'pages'       => __( '%d pages', 'vazho' ),
	'empty'       => __( 'None yet', 'vazho' ),
	'off'         => __( 'Module is off', 'vazho' ),
	'ready_yes'   => __( 'The minimum to go live is done: your business name and something to answer from.', 'vazho' ),
	'ready_no'    => __( 'To go live you only need two things: your business name and one fact, product or document.', 'vazho' ),
);
$ssc_levels = array(
	'start' => array(
		'label' => __( 'Just starting', 'vazho' ),
		'line'  => __( 'Let’s begin with the basics. It takes about two minutes.', 'vazho' ),
	),
	'basic' => array(
		'label' => __( 'Getting there', 'vazho' ),
		'line'  => __( 'A good start. A few more facts and the answers will get much better.', 'vazho' ),
	),
	'good'  => array(
		'label' => __( 'Good', 'vazho' ),
		'line'  => __( 'Looking good. A few more details will make the answers precise.', 'vazho' ),
	),
	'great' => array(
		'label' => __( 'Excellent', 'vazho' ),
		'line'  => __( 'Your assistant is well prepared. Keep it up to date as things change.', 'vazho' ),
	),
);
$ssc_step_text = array(
	'org_name'     => __( 'Add your organization’s name', 'vazho' ),
	'description'  => __( 'Describe in a few sentences what you do', 'vazho' ),
	'first_fact'   => __( 'Add your first fact, such as your return policy', 'vazho' ),
	'contact'      => __( 'Add a phone number or an email', 'vazho' ),
	'hours'        => __( 'Add your working hours', 'vazho' ),
	'more_facts'   => __( 'Add a few more facts', 'vazho' ),
	'persona_name' => __( 'Give your assistant a name', 'vazho' ),
	'products'     => __( 'Add your products or services', 'vazho' ),
	'documents'    => __( 'Upload a brochure or a price list', 'vazho' ),
	'website'      => __( 'Let the assistant learn from your website', 'vazho' ),
	'website_sync' => __( 'Sync your website pages now', 'vazho' ),
);

/**
 * Progress line of a section.
 */
$ssc_status = function ( $id ) use ( $score, $ssc_fmt ) {
	$row = $score['panels'][ $id ];
	if ( $row['locked'] ) {
		return $ssc_fmt['off'];
	}
	if ( null !== $row['count'] ) {
		$map = array(
			'facts'     => array( 'entries_one', 'entries' ),
			'products'  => array( 'products_one', 'products' ),
			'documents' => array( 'docs_one', 'docs' ),
			'website'   => array( 'pages_one', 'pages' ),
		);
		if ( 0 === $row['count'] ) {
			return $ssc_fmt['empty'];
		}
		return 1 === $row['count'] ? $ssc_fmt[ $map[ $id ][0] ] : sprintf( $ssc_fmt[ $map[ $id ][1] ], $row['count'] );
	}
	return 0 === $row['filled'] ? $ssc_fmt['none'] : sprintf( $ssc_fmt['fields'], $row['filled'], $row['total'] );
};

/**
 * Ring modifier for a section.
 */
$ssc_ring_class = function ( $id ) use ( $score ) {
	$row = $score['panels'][ $id ];
	if ( $row['ratio'] >= 1 ) {
		return 'ssc-ring--sm is-ok';
	}
	return 'ssc-ring--sm' . ( $row['ratio'] <= 0 ? ' is-low' : '' );
};

/**
 * One profile field.
 */
$ssc_field = function ( $key, $label, $args = array() ) use ( $business ) {
	$args  = array_merge(
		array(
			'type'        => 'text',
			'dir'         => 'auto',
			'placeholder' => '',
			'badge'       => '',
			'rows'        => 0,
			'wide'        => false,
			'hint'        => '',
			'fill'        => '',
		),
		$args
	);
	$value = isset( $business[ $key ] ) ? (string) $business[ $key ] : '';
	?>
	<div class="ssc-field ssc-hfield<?php echo $args['wide'] ? ' ssc-hfield--wide' : ''; ?>">
		<label for="b_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?><?php if ( '' !== $args['badge'] ) : ?> <span class="ssc-hbadge"><?php echo esc_html( $args['badge'] ); ?></span><?php endif; ?></label>
		<?php if ( $args['rows'] > 0 ) : ?>
			<textarea id="b_<?php echo esc_attr( $key ); ?>" name="business[<?php echo esc_attr( $key ); ?>]" rows="<?php echo esc_attr( (string) $args['rows'] ); ?>" dir="auto" placeholder="<?php echo esc_attr( $args['placeholder'] ); ?>" data-ssc-score="1"><?php echo esc_textarea( $value ); ?></textarea>
		<?php else : ?>
			<input id="b_<?php echo esc_attr( $key ); ?>" name="business[<?php echo esc_attr( $key ); ?>]" type="<?php echo esc_attr( $args['type'] ); ?>" dir="<?php echo esc_attr( $args['dir'] ); ?>" value="<?php echo esc_attr( $value ); ?>" placeholder="<?php echo esc_attr( $args['placeholder'] ); ?>" data-ssc-score="1" />
		<?php endif; ?>
		<?php if ( '' !== $args['hint'] ) : ?>
			<p class="ssc-field__hint"><?php echo esc_html( $args['hint'] ); ?></p>
		<?php endif; ?>
		<?php if ( '' !== $args['fill'] ) : ?>
			<button type="button" class="ssc-btn ssc-btn--ghost ssc-btn--sm" data-ssc-fill="b_<?php echo esc_attr( $key ); ?>" data-text="<?php echo esc_attr( $args['fill'] ); ?>"><?php esc_html_e( 'Use the chat schedule', 'vazho' ); ?></button>
		<?php endif; ?>
	</div>
	<?php
};

/**
 * Panel heading.
 */
$ssc_head = function ( $id ) use ( $ssc_meta ) {
	?>
	<header class="ssc-hpanel__head">
		<span class="ssc-hpanel__ico"><?php echo SSC_Icons::get( $ssc_meta[ $id ]['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static inline SVG. ?></span>
		<div>
			<h2><?php echo esc_html( $ssc_meta[ $id ]['title'] ); ?></h2>
			<p><?php echo esc_html( $ssc_meta[ $id ]['intro'] ); ?></p>
		</div>
	</header>
	<?php
};

$ssc_types = array(
	'general' => __( 'General', 'vazho' ),
	'product' => __( 'Products', 'vazho' ),
	'service' => __( 'Services', 'vazho' ),
	'faq'     => __( 'FAQ', 'vazho' ),
	'policy'  => __( 'Policies', 'vazho' ),
	'support' => __( 'Support', 'vazho' ),
	'custom'  => __( 'Custom', 'vazho' ),
);

/**
 * What to tell the admin about an entry that repeats the profile.
 */
$ssc_overlap_text = function ( $found ) {
	$kind  = $found['kind'];
	$state = $found['state'];
	$texts = array(
		'hours'   => array(
			'repeats' => __( 'Your working hours are already in Contact & hours. Keep them there only, so the assistant never gives two different answers.', 'vazho' ),
			'belongs' => __( 'Working hours belong in Contact & hours, so the assistant and the chat schedule use one version.', 'vazho' ),
		),
		'phone'   => array(
			'repeats' => __( 'This phone number is already in Contact & hours. Keep it in one place.', 'vazho' ),
			'belongs' => __( 'Phone numbers belong in Contact & hours.', 'vazho' ),
			/* translators: %s: the phone number written in the entry. */
			'differs' => __( 'This mentions a phone number (%s) that is not the one in Contact & hours. If it is another department, say so in the text; otherwise correct the profile.', 'vazho' ),
		),
		'email'   => array(
			'repeats' => __( 'This email address is already in Contact & hours. Keep it in one place.', 'vazho' ),
			'belongs' => __( 'Email addresses belong in Contact & hours.', 'vazho' ),
			/* translators: %s: the email address written in the entry. */
			'differs' => __( 'This mentions an email address (%s) that is not the one in Contact & hours. If it is another department, say so in the text; otherwise correct the profile.', 'vazho' ),
		),
		'address' => array(
			'repeats' => __( 'Your addresses are already in Contact & hours (Places). Keep them there only.', 'vazho' ),
			'belongs' => __( 'Addresses belong in Contact & hours (Places), where each one can have a map link.', 'vazho' ),
		),
	);
	if ( ! isset( $texts[ $kind ][ $state ] ) ) {
		return '';
	}
	return 'differs' === $state ? sprintf( $texts[ $kind ][ $state ], $found['found'] ) : $texts[ $kind ][ $state ];
};

/**
 * One place (head office, factory…). Also rendered blank into a <template>.
 */
$ssc_place_row = function ( $i, $place ) {
	$types = SSC_Locations::types();
	$type  = isset( $place['type'], $types[ $place['type'] ] ) ? $place['type'] : 'hq';
	?>
	<div class="ssc-ki ssc-loc">
		<div class="ssc-ki__row ssc-loc__row">
			<select name="business[locations][<?php echo esc_attr( (string) $i ); ?>][type]" aria-label="<?php esc_attr_e( 'Kind of place', 'vazho' ); ?>">
				<?php foreach ( $types as $t_id => $t_label ) : ?>
					<option value="<?php echo esc_attr( $t_id ); ?>" <?php selected( $type, $t_id ); ?>><?php echo esc_html( $t_label ); ?></option>
				<?php endforeach; ?>
			</select>
			<input type="text" name="business[locations][<?php echo esc_attr( (string) $i ); ?>][name]" value="<?php echo esc_attr( isset( $place['name'] ) ? $place['name'] : '' ); ?>" placeholder="<?php esc_attr_e( 'Name (optional), e.g. Tehran office', 'vazho' ); ?>" maxlength="80" />
			<button type="button" class="ssc-ki__remove" aria-label="<?php esc_attr_e( 'Remove', 'vazho' ); ?>">×</button>
		</div>
		<textarea name="business[locations][<?php echo esc_attr( (string) $i ); ?>][address]" rows="2" maxlength="300" placeholder="<?php esc_attr_e( 'Full address', 'vazho' ); ?>"><?php echo esc_textarea( isset( $place['address'] ) ? $place['address'] : '' ); ?></textarea>
		<div class="ssc-grid ssc-grid--2">
			<input type="text" dir="ltr" inputmode="tel" name="business[locations][<?php echo esc_attr( (string) $i ); ?>][phone]" value="<?php echo esc_attr( isset( $place['phone'] ) ? $place['phone'] : '' ); ?>" placeholder="<?php esc_attr_e( 'Phone (optional)', 'vazho' ); ?>" maxlength="30" />
			<input type="url" dir="ltr" name="business[locations][<?php echo esc_attr( (string) $i ); ?>][map]" value="<?php echo esc_attr( isset( $place['map'] ) ? $place['map'] : '' ); ?>" placeholder="<?php esc_attr_e( 'Map link, e.g. https://maps.app.goo.gl/…', 'vazho' ); ?>" maxlength="600" />
		</div>
	</div>
	<?php
};

/**
 * One knowledge entry card. Also rendered blank into a <template>.
 */
$ssc_fact_row = function ( $i, $item, $open = false, $state = '' ) use ( $ssc_types, $business, $ssc_overlap_text ) {
	$type    = isset( $item['type'] ) && isset( $ssc_types[ $item['type'] ] ) ? $item['type'] : 'general';
	$title   = isset( $item['title'] ) ? (string) $item['title'] : '';
	$content = isset( $item['content'] ) ? (string) $item['content'] : '';
	$overlap = ( $title || $content ) ? SSC_Knowledge_Overlap::check( $item, $business ) : null;
	$note    = $overlap ? $ssc_overlap_text( $overlap ) : '';
	?>
	<details class="ssc-ki ssc-kcard<?php echo '' !== $note ? ' has-overlap' : ''; ?>" data-type="<?php echo esc_attr( $type ); ?>"<?php echo $open ? ' open' : ''; ?>>
		<summary class="ssc-kcard__sum">
			<span class="ssc-kcard__ico"><?php echo SSC_Icons::get( 'book' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static inline SVG. ?></span>
			<span class="ssc-kcard__title" data-ssc-kmirror="title" data-empty="<?php esc_attr_e( 'New entry', 'vazho' ); ?>"><?php echo esc_html( '' !== $title ? $title : __( 'New entry', 'vazho' ) ); ?></span>
			<span class="ssc-kcard__excerpt" data-ssc-kmirror="content"><?php echo esc_html( wp_html_excerpt( wp_strip_all_tags( $content ), 90, '…' ) ); ?></span>
			<span class="ssc-kcard__badge ssc-kcard__badge--warn" data-ssc-kstate data-cut="<?php esc_attr_e( 'Partly used', 'vazho' ); ?>" data-out="<?php esc_attr_e( 'Not used', 'vazho' ); ?>"<?php echo in_array( $state, array( 'cut', 'out' ), true ) ? '' : ' hidden'; ?>><?php echo esc_html( 'cut' === $state ? __( 'Partly used', 'vazho' ) : ( 'out' === $state ? __( 'Not used', 'vazho' ) : '' ) ); ?></span>
			<span class="ssc-kcard__chev"><?php echo SSC_Icons::get( 'chevron' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static inline SVG. ?></span>
		</summary>
		<div class="ssc-kcard__body">
			<?php if ( '' !== $note ) : ?>
				<p class="ssc-koverlap" role="note"><span class="ssc-koverlap__ico"><?php echo SSC_Icons::get( 'alert' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static inline SVG. ?></span><span><?php echo esc_html( $note ); ?> <a href="#contact" data-ssc-hubgo="contact"><?php esc_html_e( 'Open Contact & hours', 'vazho' ); ?></a></span></p>
			<?php endif; ?>
			<input type="hidden" name="ki[<?php echo esc_attr( (string) $i ); ?>][id]" value="<?php echo esc_attr( isset( $item['id'] ) ? $item['id'] : '' ); ?>" />
			<div class="ssc-ki__row">
				<select name="ki[<?php echo esc_attr( (string) $i ); ?>][type]" aria-label="<?php esc_attr_e( 'Type', 'vazho' ); ?>" data-ssc-ktype>
					<?php foreach ( $ssc_types as $t_id => $t_label ) : ?>
						<option value="<?php echo esc_attr( $t_id ); ?>" <?php selected( $type, $t_id ); ?>><?php echo esc_html( $t_label ); ?></option>
					<?php endforeach; ?>
				</select>
				<input type="text" name="ki[<?php echo esc_attr( (string) $i ); ?>][title]" value="<?php echo esc_attr( $title ); ?>" placeholder="<?php esc_attr_e( 'Title', 'vazho' ); ?>" data-ssc-ktitle />
				<button type="button" class="ssc-ki__remove" aria-label="<?php esc_attr_e( 'Remove', 'vazho' ); ?>">×</button>
			</div>
			<textarea name="ki[<?php echo esc_attr( (string) $i ); ?>][content]" rows="3" placeholder="<?php esc_attr_e( 'Facts…', 'vazho' ); ?>" data-ssc-kcontent><?php echo esc_textarea( $content ); ?></textarea>
		</div>
	</details>
	<?php
};

/**
 * One product card. Also rendered blank into a <template>.
 */
$ssc_product_row = function ( $i, $p, $open = false ) {
	$attrs = ! empty( $p['attributes'] ) && is_array( $p['attributes'] ) ? $p['attributes'] : array( '' => '' );
	$name  = isset( $p['name'] ) ? (string) $p['name'] : '';
	$specs = isset( $p['attributes'] ) && is_array( $p['attributes'] ) ? count( $p['attributes'] ) : 0;
	?>
	<details class="ssc-product ssc-kcard ssc-kcard--product"<?php echo $open ? ' open' : ''; ?>>
		<summary class="ssc-kcard__sum">
			<span class="ssc-kcard__ico"><?php echo SSC_Icons::get( 'box' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static inline SVG. ?></span>
			<span class="ssc-kcard__title" data-ssc-kmirror="name" data-empty="<?php esc_attr_e( 'New product or service', 'vazho' ); ?>"><?php echo esc_html( '' !== $name ? $name : __( 'New product or service', 'vazho' ) ); ?></span>
			<span class="ssc-kcard__badge" data-ssc-kspecs data-one="<?php esc_attr_e( '1 specification', 'vazho' ); ?>" data-many="<?php esc_attr_e( '%d specifications', 'vazho' ); ?>"<?php echo $specs > 0 ? '' : ' hidden'; ?>><?php echo esc_html( 1 === $specs ? __( '1 specification', 'vazho' ) : sprintf( __( '%d specifications', 'vazho' ), $specs ) ); ?></span>
			<span class="ssc-kcard__chev"><?php echo SSC_Icons::get( 'chevron' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static inline SVG. ?></span>
		</summary>
		<div class="ssc-kcard__body">
			<div class="ssc-ki__row">
				<input type="hidden" name="products[<?php echo esc_attr( (string) $i ); ?>][id]" value="<?php echo esc_attr( isset( $p['id'] ) ? $p['id'] : '' ); ?>" />
				<input type="text" name="products[<?php echo esc_attr( (string) $i ); ?>][name]" value="<?php echo esc_attr( $name ); ?>" placeholder="<?php esc_attr_e( 'Product / service name', 'vazho' ); ?>" data-ssc-kname />
				<button type="button" class="ssc-ki__remove" aria-label="<?php esc_attr_e( 'Remove product', 'vazho' ); ?>">×</button>
			</div>
			<textarea name="products[<?php echo esc_attr( (string) $i ); ?>][summary]" rows="2" placeholder="<?php esc_attr_e( 'What is it, key facts, terms…', 'vazho' ); ?>"><?php echo esc_textarea( isset( $p['summary'] ) ? $p['summary'] : '' ); ?></textarea>
			<p class="ssc-kgroup"><?php echo SSC_Icons::get( 'list' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static inline SVG. ?> <?php esc_html_e( 'Specifications', 'vazho' ); ?></p>
			<div class="ssc-product__attrs">
				<?php foreach ( $attrs as $ak => $av ) : ?>
					<div class="ssc-product__attrrow">
						<input type="text" name="product_attributes[<?php echo esc_attr( (string) $i ); ?>][]" value="<?php echo esc_attr( $ak ); ?>" placeholder="<?php esc_attr_e( 'Attribute (e.g. warranty)', 'vazho' ); ?>" />
						<input type="text" name="product_attributes[<?php echo esc_attr( (string) $i ); ?>][]" value="<?php echo esc_attr( $av ); ?>" placeholder="<?php esc_attr_e( 'Value', 'vazho' ); ?>" />
					</div>
				<?php endforeach; ?>
			</div>
			<button type="button" class="ssc-btn ssc-btn--ghost ssc-btn--sm ssc-product__attradd"><?php esc_html_e( '+ Add attribute', 'vazho' ); ?></button>
			<p class="ssc-kgroup"><?php echo SSC_Icons::get( 'link' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static inline SVG. ?> <?php esc_html_e( 'Links (optional)', 'vazho' ); ?></p>
			<div class="ssc-grid ssc-grid--2">
				<input type="url" name="products[<?php echo esc_attr( (string) $i ); ?>][brochure]" dir="ltr" value="<?php echo esc_attr( isset( $p['brochure'] ) ? $p['brochure'] : '' ); ?>" placeholder="<?php esc_attr_e( 'Brochure link (optional)', 'vazho' ); ?>" />
				<input type="url" name="products[<?php echo esc_attr( (string) $i ); ?>][image]" dir="ltr" value="<?php echo esc_attr( isset( $p['image'] ) ? $p['image'] : '' ); ?>" placeholder="<?php esc_attr_e( 'Image URL (optional)', 'vazho' ); ?>" />
			</div>
		</div>
	</details>
	<?php
};

// Hours, phone, email and addresses live in Contact & hours, so they are not offered as entries here.
$ssc_starters = array(
	array(
		'type'  => 'policy',
		'title' => __( 'Return & refund policy', 'vazho' ),
		'hint'  => __( 'For example: Unused items in their original packaging can be returned within 7 days.', 'vazho' ),
	),
	array(
		'type'  => 'service',
		'title' => __( 'Shipping & delivery', 'vazho' ),
		'hint'  => __( 'For example: Orders ship within 2 working days. Delivery takes 2 to 5 days across the country.', 'vazho' ),
	),
	array(
		'type'  => 'general',
		'title' => __( 'Prices & payment', 'vazho' ),
		'hint'  => __( 'For example: Prices are listed in Toman. You can pay online or on delivery.', 'vazho' ),
	),
	array(
		'type'  => 'support',
		'title' => __( 'Warranty & after-sales', 'vazho' ),
		'hint'  => __( 'For example: All products carry an 18-month warranty. Contact support with your invoice number.', 'vazho' ),
	),
	array(
		'type'  => 'faq',
		'title' => __( 'A common question', 'vazho' ),
		'hint'  => __( 'Write the question as the title and the answer here.', 'vazho' ),
	),
);
$ssc_tone_choices = array(
	'professional' => __( 'Professional', 'vazho' ),
	'friendly'     => __( 'Friendly', 'vazho' ),
	'formal'       => __( 'Formal', 'vazho' ),
	'casual'       => __( 'Casual', 'vazho' ),
);
$ssc_hues  = array(
	'identity'  => 'blue',
	'contact'   => 'green',
	'persona'   => 'violet',
	'facts'     => 'amber',
	'products'  => 'cyan',
	'documents' => 'rose',
	'website'   => 'teal',
	'ai'        => 'indigo',
);
$ssc_order = array_merge( array_keys( SSC_Knowledge_Score::panels() ), array( 'ai' ) );
?>
<div class="ssc-page ssc-kh" id="ssc-kh" data-ssc-hub="knowledge" data-initial="<?php echo esc_attr( $initial ); ?>" data-initial-forced="<?php echo isset( $_GET['panel'] ) || '' !== $kb_state || is_array( $ssc_suggest ) ? '1' : '0'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only. ?>" data-extra="<?php echo SSC_Schema::kb_count() > 0 || SSC_Schema::qa_count() > 0 ? '1' : '0'; ?>"
	data-fmt="<?php echo esc_attr( (string) wp_json_encode( $ssc_fmt ) ); ?>"
	data-levels="<?php echo esc_attr( (string) wp_json_encode( array_map( function ( $id ) use ( $ssc_levels ) { return array_merge( array( 'id' => $id, 'min' => SSC_Knowledge_Score::levels()[ $id ] ), $ssc_levels[ $id ] ); }, array_keys( SSC_Knowledge_Score::levels() ) ) ) ); ?>"
	data-weights="<?php echo esc_attr( (string) wp_json_encode( array_map( function ( $row ) { return $row['locked'] ? 0 : $row['weight']; }, $score['panels'] ) ) ); ?>">
	<header class="ssc-page__head">
		<div>
			<h1><?php esc_html_e( 'Business Knowledge', 'vazho' ); ?></h1>
			<p class="ssc-page__sub"><?php esc_html_e( 'Teach your assistant about your business, a little at a time. Start with the basics and add more whenever you like. Your details and entries are read on every answer (entries up to the limit shown in that section); documents and website pages are searched when a question needs them.', 'vazho' ); ?></p>
		</div>
	</header>

	<?php if ( '' !== $ssc_bad ) : ?>
		<?php
		$ssc_bad_parts = array_pad( explode( ':', $ssc_bad, 3 ), 3, '' );
		$ssc_bad_names = array(
			'business'           => __( 'Business profile', 'vazho' ),
			'ki'                 => __( 'Knowledge entries', 'vazho' ),
			'products'           => __( 'Products & services', 'vazho' ),
			'product_attributes' => __( 'Product specifications', 'vazho' ),
		);
		$ssc_bad_where = isset( $ssc_bad_names[ $ssc_bad_parts[0] ] ) ? $ssc_bad_names[ $ssc_bad_parts[0] ] : __( 'the form', 'vazho' );
		?>
		<div class="ssc-notice ssc-notice--error" role="alert">
			<?php
			echo esc_html(
				sprintf(
					/* translators: 1: section name, 2: row number (or "-"), 3: field name. */
					__( 'Nothing was saved: the form had an invalid value in %1$s (entry %2$s, field “%3$s”). Reload the page and try again.', 'vazho' ),
					$ssc_bad_where,
					ctype_digit( $ssc_bad_parts[1] ) ? (string) ( (int) $ssc_bad_parts[1] + 1 ) : '–',
					preg_replace( '/[^A-Za-z0-9_\-]/', '', $ssc_bad_parts[2] )
				)
			);
			?>
		</div>
	<?php endif; ?>
	<?php if ( is_array( $ssc_conflict ) && ! empty( $ssc_conflict['conflicts'] ) ) : ?>
		<?php
		$ssc_field_names = array(
			'org_name'        => __( 'Official organization name', 'vazho' ),
			'brand_name'      => __( 'Brand / trading name', 'vazho' ),
			'category'        => __( 'Business category', 'vazho' ),
			'industry'        => __( 'Industry', 'vazho' ),
			'description'     => __( 'What does your organization do?', 'vazho' ),
			'differentiators' => __( 'Differentiators', 'vazho' ),
			'products'        => __( 'Products & services overview', 'vazho' ),
			'phone'           => __( 'Contact phone', 'vazho' ),
			'email'           => __( 'Contact email', 'vazho' ),
			'support_phone'   => __( 'Support phone', 'vazho' ),
			'support_email'   => __( 'Support email', 'vazho' ),
			'url'             => __( 'Website', 'vazho' ),
			'hours'           => __( 'Working hours', 'vazho' ),
			'location'        => __( 'Places', 'vazho' ),
			'locations'       => __( 'Places', 'vazho' ),
			'assistant_name'  => __( 'Assistant name', 'vazho' ),
			'assistant_role'  => __( 'Assistant role', 'vazho' ),
			'language'        => __( 'Primary answer language', 'vazho' ),
			'tone'            => __( 'Communication tone', 'vazho' ),
		);
		$ssc_clash_names = array();
		foreach ( $ssc_conflict['conflicts'] as $ssc_c_section => $ssc_c_keys ) {
			foreach ( (array) $ssc_c_keys as $ssc_c_key ) {
				if ( 'business' === $ssc_c_section ) {
					$ssc_clash_names[ $ssc_field_names[ $ssc_c_key ] ?? $ssc_c_key ] = true;
					continue;
				}
				$ssc_label = '';
				foreach ( array( (array) $ssc_conflict['mine'][ $ssc_c_section ], (array) ( 'ki' === $ssc_c_section ? $items : $products ) ) as $ssc_pool ) {
					foreach ( SSC_Merge::with_ids( $ssc_c_section, $ssc_pool ) as $ssc_c_entry ) {
						if ( $ssc_c_entry['id'] === $ssc_c_key ) {
							$ssc_label = 'ki' === $ssc_c_section ? ( isset( $ssc_c_entry['title'] ) ? (string) $ssc_c_entry['title'] : '' ) : ( isset( $ssc_c_entry['name'] ) ? (string) $ssc_c_entry['name'] : '' );
							break 2;
						}
					}
				}
				$ssc_clash_names[ '' !== $ssc_label ? $ssc_label : ( 'ki' === $ssc_c_section ? __( 'A knowledge entry', 'vazho' ) : __( 'A product', 'vazho' ) ) ] = true;
			}
		}
		?>
		<div class="ssc-notice ssc-notice--error ssc-kconflict" role="alert">
			<p><strong><?php esc_html_e( 'Not saved yet: this information was changed after you opened this page (in another tab, or by someone else).', 'vazho' ); ?></strong></p>
			<ul>
				<?php foreach ( array_keys( $ssc_clash_names ) as $ssc_clash ) : ?>
					<li><?php echo esc_html( (string) $ssc_clash ); ?></li>
				<?php endforeach; ?>
			</ul>
			<p><?php esc_html_e( 'Everything else you changed is ready to save. Choose which version to keep for the items above:', 'vazho' ); ?></p>
			<form method="post" class="ssc-kconflict__actions">
				<?php wp_nonce_field( 'ssc_knowledge_resolve' ); ?>
				<button type="submit" name="ssc_knowledge_resolve" value="mine" class="ssc-btn ssc-btn--primary"><?php esc_html_e( 'Use my version', 'vazho' ); ?></button>
				<button type="submit" name="ssc_knowledge_resolve" value="latest" class="ssc-btn"><?php esc_html_e( 'Keep the latest version', 'vazho' ); ?></button>
				<button type="submit" name="ssc_knowledge_resolve" value="discard" class="ssc-btn ssc-btn--ghost"><?php esc_html_e( 'Discard my changes', 'vazho' ); ?></button>
			</form>
		</div>
	<?php endif; ?>
	<?php if ( $saved ) : ?>
		<div class="ssc-notice ssc-notice--success" role="status"><?php esc_html_e( 'Knowledge saved.', 'vazho' ); ?></div>
	<?php endif; ?>
	<?php if ( 'added' === $kb_state ) : ?>
		<div class="ssc-notice ssc-notice--success" role="status"><?php echo esc_html( sprintf( __( 'Document imported (%d chunks).', 'vazho' ), isset( $_GET['chunks'] ) ? (int) $_GET['chunks'] : 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only. ?></div>
	<?php elseif ( 'toobig' === $kb_state ) : ?>
		<div class="ssc-notice ssc-notice--error" role="alert"><?php esc_html_e( 'The file is too large and was NOT imported (text files up to 2 MB, PDF and Word up to 10 MB).', 'vazho' ); ?></div>
	<?php elseif ( 'badtype' === $kb_state ) : ?>
		<div class="ssc-notice ssc-notice--error" role="alert"><?php esc_html_e( 'Only PDF, Word (.docx), .txt, .md, .csv and .json files can be imported.', 'vazho' ); ?></div>
	<?php elseif ( 'sitesync_run' === $kb_state ) : ?>
		<div class="ssc-notice ssc-notice--success" role="status"><?php esc_html_e( 'Sync started. Pages are being added in the background; this page shows the progress.', 'vazho' ); ?></div>
	<?php elseif ( 'sitesync_save' === $kb_state ) : ?>
		<div class="ssc-notice ssc-notice--success" role="status"><?php esc_html_e( 'Website learning settings saved.', 'vazho' ); ?></div>
	<?php elseif ( 'sitesync_clear' === $kb_state ) : ?>
		<div class="ssc-notice ssc-notice--info" role="status"><?php esc_html_e( 'Website pages were removed from the knowledge base.', 'vazho' ); ?></div>
	<?php elseif ( 'suggest_ready' === $kb_state ) : ?>
		<div class="ssc-notice ssc-notice--success" role="status"><?php esc_html_e( 'Suggestions are ready below. Review them before adding.', 'vazho' ); ?></div>
	<?php elseif ( 'suggest_error' === $kb_state ) : ?>
		<div class="ssc-notice ssc-notice--error" role="alert"><?php echo esc_html( (string) get_transient( 'ssc_suggest_error_' . get_current_user_id() ) ); ?></div>
	<?php elseif ( 'suggest_applied' === $kb_state ) : ?>
		<div class="ssc-notice ssc-notice--success" role="status"><?php echo esc_html( sprintf( /* translators: %d: number of questions. */ _n( 'Done. %d question was added.', 'Done. %d questions were added.', isset( $_GET['faqs'] ) ? (int) $_GET['faqs'] : 0, 'vazho' ), isset( $_GET['faqs'] ) ? (int) $_GET['faqs'] : 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only. ?></div>
	<?php elseif ( 'docerror' === $kb_state ) : ?>
		<div class="ssc-notice ssc-notice--error" role="alert"><?php echo esc_html( (string) get_transient( 'ssc_kb_import_error_' . get_current_user_id() ) ); ?></div>
	<?php elseif ( 'failed' === $kb_state ) : ?>
		<div class="ssc-notice ssc-notice--error" role="alert"><?php esc_html_e( 'The page could not be imported (check the URL or try later).', 'vazho' ); ?></div>
	<?php elseif ( 'deleted' === $kb_state ) : ?>
		<div class="ssc-notice ssc-notice--info" role="status"><?php esc_html_e( 'Document removed.', 'vazho' ); ?></div>
	<?php elseif ( 'semantic' === $kb_state ) : ?>
		<div class="ssc-notice ssc-notice--success" role="status"><?php esc_html_e( 'Search settings saved.', 'vazho' ); ?></div>
	<?php elseif ( 'semantic_error' === $kb_state ) : ?>
		<div class="ssc-notice ssc-notice--error" role="alert"><?php echo esc_html( __( 'Indexing failed:', 'vazho' ) . ' ' . (string) get_transient( 'ssc_kb_embed_error' ) ); ?></div>
	<?php endif; ?>

	<section class="ssc-card ssc-hhero">
		<div class="ssc-hhero__ring" data-ssc-kring="overall">
			<?php echo SSC_Icons::ring( $score['percent'], 'ssc-ring--xl' . ( $score['percent'] >= 75 ? ' is-ok' : '' ), __( 'How much the assistant knows about your business', 'vazho' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside the helper. ?>
		</div>
		<div class="ssc-hhero__main">
			<p class="ssc-hhero__level"><span data-ssc-klevel><?php echo esc_html( $ssc_levels[ $score['level'] ]['label'] ); ?></span></p>
			<h2 data-ssc-kline><?php echo esc_html( $ssc_levels[ $score['level'] ]['line'] ); ?></h2>
			<p class="ssc-hhero__ready <?php echo $score['ready'] ? 'is-ready' : ''; ?>" data-ssc-kready>
				<?php echo SSC_Icons::get( 'check', 'ssc-hhero__yes' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static inline SVG. ?>
				<?php echo SSC_Icons::get( 'info', 'ssc-hhero__no' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static inline SVG. ?>
				<span><?php echo esc_html( $score['ready'] ? $ssc_fmt['ready_yes'] : $ssc_fmt['ready_no'] ); ?></span>
			</p>
		</div>
		<?php if ( $score['steps'] ) : ?>
			<div class="ssc-hhero__steps">
				<h3><?php esc_html_e( 'Best next steps', 'vazho' ); ?></h3>
				<ul>
					<?php foreach ( $score['steps'] as $ssc_step ) : ?>
						<li>
							<a href="#<?php echo esc_attr( $ssc_step['panel'] ); ?>" data-ssc-hubgo="<?php echo esc_attr( $ssc_step['panel'] ); ?>">
								<span class="ssc-hhero__stepico"><?php echo SSC_Icons::get( $ssc_meta[ $ssc_step['panel'] ]['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static inline SVG. ?></span>
								<span><?php echo esc_html( $ssc_step_text[ $ssc_step['id'] ] ); ?></span>
								<?php if ( $ssc_step['gain'] > 0 ) : ?>
									<em>+<?php echo esc_html( (string) $ssc_step['gain'] ); ?>%</em>
								<?php endif; ?>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>
	</section>

	<nav class="<?php echo esc_attr( SSC_Admin_UI::tiles_class( count( $ssc_order ) ) ); ?>" role="tablist" aria-label="<?php esc_attr_e( 'Knowledge sections', 'vazho' ); ?>" hidden>
		<?php foreach ( $ssc_order as $ssc_id ) : ?>
			<button type="button" role="tab" class="ssc-htile<?php echo 'website' === $ssc_id && ! $ssc_site_on ? ' is-locked' : ''; ?>" id="ssc-htab-<?php echo esc_attr( $ssc_id ); ?>" data-ssc-hubtab="<?php echo esc_attr( $ssc_id ); ?>" data-ssc-hue="<?php echo esc_attr( $ssc_hues[ $ssc_id ] ); ?>" aria-controls="ssc-hpanel-<?php echo esc_attr( $ssc_id ); ?>" aria-selected="false">
				<span class="ssc-htile__ico"><?php echo SSC_Icons::get( $ssc_meta[ $ssc_id ]['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static inline SVG. ?></span>
				<span class="ssc-htile__txt">
					<strong><?php echo esc_html( $ssc_meta[ $ssc_id ]['title'] ); ?></strong>
					<small<?php echo isset( $score['panels'][ $ssc_id ] ) ? ' data-ssc-kstatus="' . esc_attr( $ssc_id ) . '"' : ''; ?>><?php echo esc_html( isset( $score['panels'][ $ssc_id ] ) ? $ssc_status( $ssc_id ) : $ssc_meta[ $ssc_id ]['tile'] ); ?></small>
				</span>
				<?php if ( 'ai' === $ssc_id ) : ?>
					<span class="ssc-htile__badge"><?php echo SSC_Icons::get( 'sparkles' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static inline SVG. ?></span>
				<?php elseif ( $score['panels'][ $ssc_id ]['locked'] ) : ?>
					<span class="ssc-htile__badge"><?php echo SSC_Icons::get( 'lock' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static inline SVG. ?></span>
				<?php else : ?>
					<span class="ssc-htile__ring" data-ssc-kring="<?php echo esc_attr( $ssc_id ); ?>"><?php echo SSC_Icons::ring( (int) round( 100 * $score['panels'][ $ssc_id ]['ratio'] ), $ssc_ring_class( $ssc_id ), $ssc_meta[ $ssc_id ]['title'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside the helper. ?></span>
				<?php endif; ?>
			</button>
		<?php endforeach; ?>
	</nav>

	<div class="ssc-hpanels">
		<form method="post" class="ssc-form ssc-kform" id="ssc-kform">
			<?php wp_nonce_field( 'ssc_knowledge' ); ?>
			<input type="hidden" name="ssc_knowledge_save" value="1" />
			<input type="hidden" name="ssc_base" value="<?php echo esc_attr( (string) wp_json_encode( $ssc_base ) ); ?>" />
			<input type="hidden" name="ssc_panel" value="<?php echo esc_attr( $initial ); ?>" data-ssc-hubfield />

			<section class="ssc-card ssc-hpanel" id="ssc-hpanel-identity" role="tabpanel" aria-labelledby="ssc-htab-identity" data-ssc-hubpanel="identity" data-ssc-hue="blue" data-ssc-form="1">
				<?php $ssc_head( 'identity' ); ?>
				<div class="ssc-grid ssc-grid--2">
					<?php
					$ssc_field( 'org_name', __( 'Official organization name', 'vazho' ), array( 'badge' => __( 'Start here', 'vazho' ), 'placeholder' => __( 'e.g. Atlas Pharmacy', 'vazho' ) ) );
					$ssc_field( 'brand_name', __( 'Brand / trading name', 'vazho' ), array( 'placeholder' => __( 'The name customers know you by', 'vazho' ) ) );
					$ssc_field( 'category', __( 'Business category', 'vazho' ), array( 'placeholder' => __( 'e.g. Pharmacy, online shop, law firm', 'vazho' ) ) );
					$ssc_field( 'industry', __( 'Industry', 'vazho' ), array( 'placeholder' => __( 'e.g. Healthcare, retail', 'vazho' ) ) );
					$ssc_field( 'description', __( 'What does your organization do?', 'vazho' ), array( 'rows' => 3, 'wide' => true, 'badge' => __( 'Start here', 'vazho' ), 'placeholder' => __( 'In two or three sentences: what you do and for whom.', 'vazho' ) ) );
					$ssc_field( 'differentiators', __( 'Differentiators', 'vazho' ), array( 'rows' => 3, 'wide' => true, 'placeholder' => __( 'What makes you different? Warranty, speed, experience…', 'vazho' ) ) );
					?>
				</div>
			</section>

			<section class="ssc-card ssc-hpanel" id="ssc-hpanel-contact" role="tabpanel" aria-labelledby="ssc-htab-contact" data-ssc-hubpanel="contact" data-ssc-hue="green" data-ssc-form="1">
				<?php $ssc_head( 'contact' ); ?>
				<div class="ssc-grid ssc-grid--2">
					<?php
					$ssc_field( 'phone', __( 'Contact phone', 'vazho' ), array( 'type' => 'text', 'dir' => 'ltr' ) );
					$ssc_field( 'email', __( 'Contact email', 'vazho' ), array( 'type' => 'email', 'dir' => 'ltr' ) );
					$ssc_field( 'support_phone', __( 'Support phone', 'vazho' ), array( 'type' => 'text', 'dir' => 'ltr' ) );
					$ssc_field( 'support_email', __( 'Support email', 'vazho' ), array( 'type' => 'email', 'dir' => 'ltr' ) );
					$ssc_field( 'url', __( 'Website', 'vazho' ), array( 'type' => 'url', 'dir' => 'ltr', 'placeholder' => 'https://' ) );
					$ssc_schedule = SSC_Availability::describe_schedule();
					$ssc_field(
						'hours',
						__( 'Working hours', 'vazho' ),
						array(
							'wide'        => true,
							'placeholder' => __( 'e.g. Saturday to Wednesday, 9:00 to 17:00', 'vazho' ),
							'hint'        => '' !== $ssc_schedule
								/* translators: %s: the chat schedule, such as "Sat–Wed, 09:00–18:00". */
								? sprintf( __( 'This is what the assistant tells visitors. Leave it empty to use the chat schedule from Settings (%s). That schedule only decides when the chat shows as online.', 'vazho' ), $ssc_schedule )
								: __( 'Write your hours once, here: this is what the assistant tells visitors. To show the chat as online only during set hours, use Settings → Display & hours.', 'vazho' ),
							'fill'        => '' !== $ssc_schedule ? $ssc_schedule : '',
						)
					);
					?>
				</div>

				<?php
				$ssc_places = SSC_Locations::effective( $business );
				?>
				<div class="ssc-hplaces" id="places">
					<header class="ssc-hplaces__head">
						<span class="ssc-hplaces__ico"><?php echo SSC_Icons::get( 'pin' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static inline SVG. ?></span>
						<div>
							<h3><?php esc_html_e( 'Places', 'vazho' ); ?></h3>
							<p><?php esc_html_e( 'Head office, factory, branches… Give each one an address and, if you like, a map link (for example from Google Maps): the assistant shares it when someone asks where you are.', 'vazho' ); ?></p>
						</div>
					</header>
					<div id="ssc-loc-list" class="ssc-loc-list" data-allow-empty="1" data-count="<?php echo esc_attr( (string) count( $ssc_places ) ); ?>"><?php
					foreach ( $ssc_places as $ssc_pi => $ssc_place ) {
						$ssc_place_row( $ssc_pi, $ssc_place );
					}
					?></div>
					<template id="ssc-loc-template"><?php $ssc_place_row( 0, array() ); ?></template>
					<button type="button" class="ssc-btn ssc-btn--ghost ssc-ki__add" data-target="#ssc-loc-list" data-template="#ssc-loc-template"><?php esc_html_e( '+ Add a place', 'vazho' ); ?></button>
				</div>
			</section>

			<section class="ssc-card ssc-hpanel" id="ssc-hpanel-persona" role="tabpanel" aria-labelledby="ssc-htab-persona" data-ssc-hubpanel="persona" data-ssc-hue="violet" data-ssc-form="1">
				<?php $ssc_head( 'persona' ); ?>
				<div class="ssc-grid ssc-grid--2">
					<?php
					$ssc_field( 'assistant_name', __( 'Assistant name', 'vazho' ), array( 'placeholder' => __( 'e.g. Sara', 'vazho' ), 'hint' => __( 'Also shown in the chat header, unless you set a display name in Appearance.', 'vazho' ) ) );
					$ssc_field( 'assistant_role', __( 'Assistant role', 'vazho' ), array( 'placeholder' => __( 'e.g. Customer support assistant', 'vazho' ) ) );
					$ssc_field( 'language', __( 'Primary answer language', 'vazho' ), array( 'dir' => 'ltr', 'placeholder' => __( 'e.g. Persian', 'vazho' ) ) );
					?>
					<div class="ssc-field ssc-hfield">
						<label for="b_tone"><?php esc_html_e( 'Communication tone', 'vazho' ); ?></label>
						<select id="b_tone" name="business[tone]">
							<?php foreach ( $ssc_tone_choices as $t_id => $t_label ) : ?>
								<option value="<?php echo esc_attr( $t_id ); ?>" <?php selected( $business['tone'], $t_id ); ?>><?php echo esc_html( $t_label ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
				</div>
			</section>

			<section class="ssc-card ssc-hpanel" id="ssc-hpanel-facts" role="tabpanel" aria-labelledby="ssc-htab-facts" data-ssc-hubpanel="facts" data-ssc-hue="amber" data-ssc-form="1" data-ssc-target="<?php echo esc_attr( (string) SSC_Knowledge_Score::panels()['facts']['target'] ); ?>">
				<?php $ssc_head( 'facts' ); ?>
				<div class="ssc-kstarters">
					<span><?php esc_html_e( 'Start from an example:', 'vazho' ); ?></span>
					<?php foreach ( $ssc_starters as $ssc_starter ) : ?>
						<button type="button" class="ssc-chip" data-ssc-kstarter data-type="<?php echo esc_attr( $ssc_starter['type'] ); ?>" data-title="<?php echo esc_attr( $ssc_starter['title'] ); ?>" data-hint="<?php echo esc_attr( $ssc_starter['hint'] ); ?>">+ <?php echo esc_html( $ssc_starter['title'] ); ?></button>
					<?php endforeach; ?>
				</div>
				<?php
				$ssc_plan  = SSC_Knowledge::plan_knowledge( $items );
				$ssc_over  = $ssc_plan['cut'] + $ssc_plan['out'];
				$ssc_share = $ssc_plan['limit'] > 0 ? min( 100, (int) round( 100 * $ssc_plan['used'] / $ssc_plan['limit'] ) ) : 0;
				?>
				<div class="ssc-kbudget<?php echo $ssc_over > 0 ? ' is-over' : ''; ?>" data-ssc-kbudget data-limit="<?php echo esc_attr( (string) $ssc_plan['limit'] ); ?>" data-fmt="<?php echo esc_attr( __( '%1$s of %2$s characters used', 'vazho' ) ); ?>">
					<div class="ssc-kbudget__bar" aria-hidden="true"><span style="width:<?php echo esc_attr( (string) $ssc_share ); ?>%"></span></div>
					<p class="ssc-kbudget__text" data-ssc-kbudget-text><?php echo esc_html( sprintf( __( '%1$s of %2$s characters used', 'vazho' ), number_format_i18n( $ssc_plan['used'] ), number_format_i18n( $ssc_plan['limit'] ) ) ); ?></p>
					<p class="ssc-kbudget__hint"><?php esc_html_e( 'The assistant reads these entries in every answer, from the top down, up to this limit. Long texts belong in Documents: there only the parts that match the question are used.', 'vazho' ); ?></p>
					<p class="ssc-kbudget__warn" role="alert" data-ssc-kbudget-warn<?php echo $ssc_over > 0 ? '' : ' hidden'; ?>><span class="ssc-koverlap__ico"><?php echo SSC_Icons::get( 'alert' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static inline SVG. ?></span><span><?php esc_html_e( 'Some entries do not fit, so the assistant does not see them (or only their beginning). Shorten the entries, remove the least important ones, or move long texts to Documents.', 'vazho' ); ?></span></p>
				</div>
				<div id="ssc-ki-list" class="ssc-ki-list ssc-klist" data-allow-empty="1" data-count="<?php echo esc_attr( (string) count( $items ) ); ?>"><?php
				foreach ( $items as $ssc_i => $ssc_item ) {
					$ssc_fact_row( $ssc_i, $ssc_item, false, isset( $ssc_plan['rows'][ $ssc_i ]['state'] ) ? $ssc_plan['rows'][ $ssc_i ]['state'] : '' );
				}
				?></div>
				<div class="ssc-hempty">
					<span class="ssc-hempty__ico"><?php echo SSC_Icons::get( 'book' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static inline SVG. ?></span>
					<p><strong><?php esc_html_e( 'No entries yet', 'vazho' ); ?></strong><br /><?php esc_html_e( 'An entry is one short fact. Pick an example above and just replace the text, or write your own.', 'vazho' ); ?></p>
				</div>
				<template id="ssc-ki-template"><?php $ssc_fact_row( 0, array(), true ); ?></template>
				<button type="button" class="ssc-btn ssc-btn--ghost ssc-ki__add" data-target="#ssc-ki-list" data-template="#ssc-ki-template"><?php esc_html_e( '+ Add entry', 'vazho' ); ?></button>
			</section>

			<section class="ssc-card ssc-hpanel" id="ssc-hpanel-products" role="tabpanel" aria-labelledby="ssc-htab-products" data-ssc-hubpanel="products" data-ssc-hue="cyan" data-ssc-form="1" data-ssc-target="<?php echo esc_attr( (string) SSC_Knowledge_Score::panels()['products']['target'] ); ?>">
				<?php $ssc_head( 'products' ); ?>
				<?php $ssc_field( 'products', __( 'Products & services overview', 'vazho' ), array( 'rows' => 3, 'wide' => true, 'placeholder' => __( 'A short summary of everything you offer.', 'vazho' ) ) ); ?>
				<div id="ssc-product-list" class="ssc-products ssc-klist" data-allow-empty="1" data-count="<?php echo esc_attr( (string) count( $products ) ); ?>"><?php
				foreach ( $products as $ssc_i => $ssc_p ) {
					$ssc_product_row( $ssc_i, $ssc_p );
				}
				?></div>
				<div class="ssc-hempty">
					<span class="ssc-hempty__ico"><?php echo SSC_Icons::get( 'box' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static inline SVG. ?></span>
					<p><strong><?php esc_html_e( 'No products yet', 'vazho' ); ?></strong><br /><?php esc_html_e( 'When a visitor asks about a product, its summary and specifications are added to the answer. Add the ones people ask about most.', 'vazho' ); ?></p>
				</div>
				<template id="ssc-product-template"><?php $ssc_product_row( 0, array(), true ); ?></template>
				<button type="button" class="ssc-btn ssc-btn--ghost ssc-product__add" data-target="#ssc-product-list" data-template="#ssc-product-template"><?php esc_html_e( '+ Add product', 'vazho' ); ?></button>
			</section>

			<div class="ssc-hsave" data-ssc-hubsave>
				<span class="ssc-hsave__state" data-ssc-hubdirty hidden><span class="ssc-hsave__dot"></span> <?php esc_html_e( 'You have unsaved changes', 'vazho' ); ?></span>
				<button type="submit" class="ssc-btn ssc-btn--primary"><?php esc_html_e( 'Save knowledge', 'vazho' ); ?></button>
			</div>
		</form>

		<section class="ssc-card ssc-hpanel" id="ssc-hpanel-documents" role="tabpanel" aria-labelledby="ssc-htab-documents" data-ssc-hubpanel="documents" data-ssc-hue="rose">
			<?php $ssc_head( 'documents' ); ?>
			<p class="ssc-card__sub"><?php echo esc_html( sprintf( /* translators: 1: number of chunks, 2: number of documents. */ __( '%1$d chunks from %2$d documents. Relevant pieces are retrieved automatically per question.', 'vazho' ), $kb_count, count( $kb_docs ) ) ); ?></p>
			<?php if ( $site_docs ) : ?>
				<p class="ssc-field__hint"><?php echo esc_html( sprintf( /* translators: %d: number of pages. */ _n( '%d page from your website is kept up to date automatically (see "Learn from my website" below).', '%d pages from your website are kept up to date automatically (see "Learn from my website" below).', count( $site_docs ), 'vazho' ), count( $site_docs ) ) ); ?></p>
			<?php endif; ?>

			<div class="ssc-kimport">
				<form method="post" class="ssc-kimport__card" enctype="multipart/form-data">
					<?php wp_nonce_field( 'ssc_kb' ); ?>
					<input type="hidden" name="ssc_kb_import_file" value="1" />
					<span class="ssc-kimport__ico"><?php echo SSC_Icons::get( 'upload' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static inline SVG. ?></span>
					<strong><?php esc_html_e( 'Upload a file', 'vazho' ); ?></strong>
					<small><?php esc_html_e( 'PDF, Word (.docx), .txt, .md, .csv or .json', 'vazho' ); ?></small>
					<input type="file" name="kb_file" accept=".pdf,.docx,.txt,.md,.csv,.json" aria-label="<?php esc_attr_e( 'Document file to import', 'vazho' ); ?>" />
					<button type="submit" class="ssc-btn ssc-btn--secondary"><?php esc_html_e( 'Upload file', 'vazho' ); ?></button>
				</form>
				<form method="post" class="ssc-kimport__card">
					<?php wp_nonce_field( 'ssc_kb' ); ?>
					<input type="hidden" name="ssc_kb_import_url" value="1" />
					<span class="ssc-kimport__ico"><?php echo SSC_Icons::get( 'link' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static inline SVG. ?></span>
					<strong><?php esc_html_e( 'Import a web page', 'vazho' ); ?></strong>
					<small><?php esc_html_e( 'Paste the address of any public page', 'vazho' ); ?></small>
					<input type="url" name="kb_url" dir="ltr" placeholder="https://example.com/about" aria-label="<?php esc_attr_e( 'Page URL to import', 'vazho' ); ?>" />
					<button type="submit" class="ssc-btn ssc-btn--secondary"><?php esc_html_e( 'Import page', 'vazho' ); ?></button>
				</form>
			</div>

			<?php if ( $own_docs ) : ?>
				<ul class="ssc-kdocs">
					<?php foreach ( $own_docs as $ssc_doc ) : ?>
						<li>
							<span class="ssc-kdocs__ico"><?php echo SSC_Icons::get( 'file' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static inline SVG. ?></span>
							<span class="ssc-kdocs__name"><strong><?php echo esc_html( $ssc_doc['source_title'] ? $ssc_doc['source_title'] : $ssc_doc['doc_id'] ); ?></strong>
								<small><?php echo esc_html( sprintf( /* translators: 1: number of chunks, 2: date. */ __( '%1$s chunks · added %2$s', 'vazho' ), number_format_i18n( (int) $ssc_doc['chunks'] ), SSC_Date::display( $ssc_doc['created_at'], false ) ) ); ?></small></span>
							<a class="ssc-link--danger" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'ssc_kb_action' => 'delete', 'doc' => $ssc_doc['doc_id'] ) ), 'ssc_kb_' . $ssc_doc['doc_id'] ) ); ?>"><?php esc_html_e( 'Remove', 'vazho' ); ?></a>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php else : ?>
				<div class="ssc-hempty is-shown">
					<span class="ssc-hempty__ico"><?php echo SSC_Icons::get( 'file' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static inline SVG. ?></span>
					<p><strong><?php esc_html_e( 'No documents yet', 'vazho' ); ?></strong><br /><?php esc_html_e( 'A brochure, a catalogue or a price list is the quickest way to teach the assistant a lot at once.', 'vazho' ); ?></p>
				</div>
			<?php endif; ?>

			<details class="ssc-kmore">
				<summary><?php echo SSC_Icons::get( 'search' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static inline SVG. ?> <?php esc_html_e( 'Search options', 'vazho' ); ?></summary>
				<form method="post" class="ssc-kb-semantic">
					<?php wp_nonce_field( 'ssc_kb' ); ?>
					<input type="hidden" name="ssc_kb_semantic_save" value="1" />
					<h3><?php esc_html_e( 'Semantic search', 'vazho' ); ?></h3>
					<?php $ssc_embed_model = SSC_Embeddings::model(); ?>
					<?php if ( '' === $ssc_embed_model ) : ?>
						<p class="ssc-field__hint"><?php esc_html_e( 'Available with OpenAI, Gemini or an OpenAI-compatible custom endpoint. The connected provider has no embeddings API, so keyword search is used.', 'vazho' ); ?></p>
					<?php else : ?>
						<label class="ssc-check"><input type="checkbox" name="kb_semantic" value="yes" <?php checked( 'yes', SSC_Settings::get( 'kb_semantic', 'no' ) ); ?> /> <span><?php esc_html_e( 'Find answers by meaning, not only matching words (uses the provider\'s embeddings API: one small request per question)', 'vazho' ); ?></span></label>
						<?php if ( 'yes' === SSC_Settings::get( 'kb_semantic', 'no' ) ) : ?>
							<?php $ssc_pending = SSC_Schema::kb_pending_count( $ssc_embed_model ); ?>
							<p class="ssc-field__hint">
								<?php
								echo esc_html(
									0 === $ssc_pending
										? __( 'Index is up to date.', 'vazho' )
										/* translators: %d: number of chunks still waiting for indexing. */
										: sprintf( __( '%d chunks are waiting to be indexed (continues in the background).', 'vazho' ), $ssc_pending )
								);
								?>
							</p>
						<?php endif; ?>
					<?php endif; ?>
					<label class="ssc-check"><input type="checkbox" name="show_sources" value="yes" <?php checked( 'yes', SSC_Settings::get( 'show_sources', 'yes' ) ); ?> /> <span><?php esc_html_e( 'Show the source documents under AI answers', 'vazho' ); ?></span></label>
					<button type="submit" class="ssc-btn ssc-btn--secondary"><?php esc_html_e( 'Save and index now', 'vazho' ); ?></button>
				</form>
			</details>
		</section>

		<section class="ssc-card ssc-hpanel" id="ssc-hpanel-website" role="tabpanel" aria-labelledby="ssc-htab-website" data-ssc-hubpanel="website" data-ssc-hue="teal">
			<?php $ssc_head( 'website' ); ?>
			<?php if ( $ssc_site_on ) : ?>
				<?php
				$ssc_sync       = SSC_Module_Sitesync::state();
				$ssc_sync_types = SSC_Module_Sitesync::types();
				$ssc_type_opts  = get_post_types( array( 'public' => true ), 'objects' );
				unset( $ssc_type_opts['attachment'] );
				?>
				<p class="ssc-card__sub">
					<?php if ( $ssc_sync['running'] ) : ?>
						<strong><?php echo esc_html( sprintf( /* translators: 1: done, 2: total. */ __( 'Syncing… %1$d of %2$d', 'vazho' ), (int) $ssc_sync['done'], (int) $ssc_sync['total'] ) ); ?></strong>
					<?php else : ?>
						<?php echo esc_html( sprintf( /* translators: %d: number of pages. */ _n( '%d page in the knowledge base.', '%d pages in the knowledge base.', count( SSC_Module_Sitesync::documents() ), 'vazho' ), count( SSC_Module_Sitesync::documents() ) ) ); ?>
						<?php if ( $ssc_sync['finished'] ) : ?>
							<?php echo esc_html( sprintf( /* translators: %s: date. */ __( 'Last full sync: %s', 'vazho' ), SSC_Date::display( $ssc_sync['finished'] ) ) ); ?>
						<?php endif; ?>
						<?php if ( ! empty( $ssc_sync['failed'] ) ) : ?>
							<strong class="ssc-text-warn"><?php echo esc_html( sprintf( /* translators: %d: number of pages. */ _n( '%d page could not be updated; its previous version was kept.', '%d pages could not be updated; their previous versions were kept.', (int) $ssc_sync['failed'], 'vazho' ), (int) $ssc_sync['failed'] ) ); ?></strong>
						<?php endif; ?>
					<?php endif; ?>
				</p>
				<form method="post">
					<?php wp_nonce_field( 'ssc_kb' ); ?>
					<fieldset class="ssc-field">
						<legend class="ssc-field__label"><?php esc_html_e( 'Content to learn from', 'vazho' ); ?></legend>
						<?php foreach ( $ssc_type_opts as $ssc_type ) : ?>
							<label class="ssc-check ssc-check--tight"><input type="checkbox" name="sitesync_types[]" value="<?php echo esc_attr( $ssc_type->name ); ?>" <?php checked( in_array( $ssc_type->name, $ssc_sync_types, true ) ); ?> /> <span><?php echo esc_html( $ssc_type->labels->name ); ?></span></label>
						<?php endforeach; ?>
					</fieldset>
					<div class="ssc-field">
						<label for="sitesync_exclude"><?php esc_html_e( 'Never include these IDs (comma separated)', 'vazho' ); ?></label>
						<input id="sitesync_exclude" name="sitesync_exclude" type="text" dir="ltr" value="<?php echo esc_attr( implode( ', ', SSC_Module_Sitesync::excluded() ) ); ?>" placeholder="12, 57" />
					</div>
					<div class="ssc-form__actions ssc-form__actions--start">
						<button type="submit" name="ssc_sitesync_action" value="run" class="ssc-btn ssc-btn--primary"><?php esc_html_e( 'Save and sync now', 'vazho' ); ?></button>
						<button type="submit" name="ssc_sitesync_action" value="save" class="ssc-btn ssc-btn--secondary"><?php esc_html_e( 'Save', 'vazho' ); ?></button>
						<button type="submit" name="ssc_sitesync_action" value="clear" class="ssc-btn ssc-btn--ghost ssc-link--danger" onclick="return window.confirm(this.getAttribute('data-confirm'));" data-confirm="<?php esc_attr_e( 'Remove every page that came from the website from the knowledge base? Your own entries and uploaded documents stay.', 'vazho' ); ?>"><?php esc_html_e( 'Remove website pages', 'vazho' ); ?></button>
					</div>
				</form>
			<?php else : ?>
				<div class="ssc-klocked">
					<span class="ssc-hempty__ico"><?php echo SSC_Icons::get( 'lock' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static inline SVG. ?></span>
					<p><strong><?php esc_html_e( 'This is an optional module', 'vazho' ); ?></strong><br /><?php esc_html_e( 'Switch on “Learn from my website” and your published pages, posts and products become knowledge automatically, and stay up to date.', 'vazho' ); ?></p>
					<a class="ssc-btn ssc-btn--primary" href="<?php echo esc_url( admin_url( 'admin.php?page=ssc-modules' ) ); ?>"><?php esc_html_e( 'Open Modules', 'vazho' ); ?></a>
				</div>
			<?php endif; ?>
		</section>

		<section class="ssc-card ssc-hpanel" id="ssc-hpanel-ai" role="tabpanel" aria-labelledby="ssc-htab-ai" data-ssc-hubpanel="ai" data-ssc-hue="indigo">
			<?php $ssc_head( 'ai' ); ?>
			<?php if ( ! is_array( $ssc_suggest ) ) : ?>
				<p class="ssc-card__sub"><?php esc_html_e( 'The AI reads your business profile, knowledge and pages, then proposes the questions visitors are most likely to ask (answered only from your material) and a name, role and welcome text for the assistant. You review everything before it is added.', 'vazho' ); ?></p>
				<form method="post">
					<?php wp_nonce_field( 'ssc_kb' ); ?>
					<button type="submit" name="ssc_suggest" value="1" class="ssc-btn ssc-btn--primary" onclick="this.classList.add('is-busy');this.textContent=this.getAttribute('data-busy');" data-busy="<?php esc_attr_e( 'Reading your material… (up to a minute)', 'vazho' ); ?>"><?php echo SSC_Icons::get( 'sparkles' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static inline SVG. ?> <?php esc_html_e( 'Write suggestions', 'vazho' ); ?></button>
				</form>
			<?php else : ?>
				<p class="ssc-card__sub"><?php esc_html_e( 'Tick what you want to keep and edit freely. Nothing is saved until you press "Add selected".', 'vazho' ); ?></p>
				<form method="post" class="ssc-suggest">
					<?php wp_nonce_field( 'ssc_kb' ); ?>
					<?php if ( ! empty( $ssc_suggest['persona'] ) ) : ?>
						<h3><?php esc_html_e( 'Assistant persona', 'vazho' ); ?></h3>
						<?php
						$ssc_persona_labels = array(
							'assistant_name' => __( 'Assistant name', 'vazho' ),
							'assistant_role' => __( 'Assistant role', 'vazho' ),
							'tone'           => __( 'Communication tone', 'vazho' ),
							'welcome_title'  => __( 'Welcome title', 'vazho' ),
							'welcome_text'   => __( 'Welcome text', 'vazho' ),
						);
						foreach ( $ssc_suggest['persona'] as $ssc_key => $ssc_value ) :
							if ( ! isset( $ssc_persona_labels[ $ssc_key ] ) ) {
								continue;
							}
							?>
							<div class="ssc-suggest__row">
								<label class="ssc-check ssc-check--tight"><input type="checkbox" name="persona_pick[]" value="<?php echo esc_attr( $ssc_key ); ?>" checked /> <span><?php echo esc_html( $ssc_persona_labels[ $ssc_key ] ); ?></span></label>
								<input type="text" name="persona[<?php echo esc_attr( $ssc_key ); ?>]" value="<?php echo esc_attr( $ssc_value ); ?>" dir="auto" />
							</div>
						<?php endforeach; ?>
					<?php endif; ?>
					<?php if ( ! empty( $ssc_suggest['faqs'] ) ) : ?>
						<h3><?php echo esc_html( SSC_Modules::is_active( 'faq' ) ? __( 'Questions for the FAQ bank', 'vazho' ) : __( 'Questions to add as knowledge entries', 'vazho' ) ); ?></h3>
						<?php foreach ( $ssc_suggest['faqs'] as $ssc_i => $ssc_faq ) : ?>
							<div class="ssc-suggest__faq">
								<label class="ssc-check ssc-check--tight"><input type="checkbox" name="faq[<?php echo esc_attr( (string) $ssc_i ); ?>][pick]" value="1" checked aria-label="<?php esc_attr_e( 'Keep this question', 'vazho' ); ?>" /></label>
								<div>
									<input type="text" name="faq[<?php echo esc_attr( (string) $ssc_i ); ?>][q]" value="<?php echo esc_attr( $ssc_faq['q'] ); ?>" dir="auto" aria-label="<?php esc_attr_e( 'Question', 'vazho' ); ?>" />
									<textarea name="faq[<?php echo esc_attr( (string) $ssc_i ); ?>][a]" rows="2" dir="auto" aria-label="<?php esc_attr_e( 'Answer', 'vazho' ); ?>"><?php echo esc_textarea( $ssc_faq['a'] ); ?></textarea>
								</div>
							</div>
						<?php endforeach; ?>
					<?php endif; ?>
					<div class="ssc-form__actions ssc-form__actions--start">
						<button type="submit" name="ssc_suggest_apply" value="1" class="ssc-btn ssc-btn--primary"><?php esc_html_e( 'Add selected', 'vazho' ); ?></button>
						<button type="submit" name="ssc_suggest_discard" value="1" class="ssc-btn ssc-btn--ghost"><?php esc_html_e( 'Discard', 'vazho' ); ?></button>
					</div>
				</form>
			<?php endif; ?>
		</section>
	</div>
</div>

