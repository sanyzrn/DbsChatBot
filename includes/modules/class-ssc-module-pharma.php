<?php
/**
 * Pharmaceutical industry extension (Layer C): structured adverse drug
 * reaction (ADR) reporting, case management, audit trail, follow-up
 * workflow, role-based access and dedicated privacy controls.
 *
 * Alignment notes:
 * - Seriousness criteria follow the ICH E2A definitions (death,
 *   life-threatening, hospitalization or prolongation, persistent
 *   disability, congenital anomaly, other medically important events).
 * - Clinical SEVERITY (mild/moderate/severe) is kept strictly separate from
 *   regulatory SERIOUSNESS: a case is SERIOUS when ANY seriousness
 *   criterion is met OR the outcome belongs to the serious outcome set,
 *   regardless of how "mild" the reported severity looks. Potentially
 *   serious cases can never be silently classified as routine.
 * - No regulatory compliance is claimed: implementers must verify their own
 *   jurisdiction's reporting obligations (e.g., national PV center rules).
 * - The AI never invents pharmaceutical facts: when this module is active a
 *   strict medical-answer rule set is injected into every prompt.
 *
 * @package SmartSupportChatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pharma module.
 */
class SSC_Module_Pharma extends SSC_Module {

	/**
	 * Module id.
	 *
	 * @return string
	 */
	public function id() {
		return 'pharma';
	}

	/**
	 * Title.
	 *
	 * @return string
	 */
	public function title() {
		return __( 'Pharmaceutical Extension', 'nexachat-ai' );
	}

	/**
	 * Description.
	 *
	 * @return string
	 */
	public function description() {
		return __( 'Structured adverse drug reaction (ADR) intake with ICH E2A-style seriousness criteria, case management, audit trail and follow-up workflow for pharmacovigilance teams.', 'nexachat-ai' );
	}

	/**
	 * Benefit.
	 *
	 * @return string
	 */
	public function benefit() {
		return __( 'Capture complete, reviewable safety reports instead of loose chat messages.', 'nexachat-ai' );
	}

	/**
	 * Category (industry extension).
	 *
	 * @return string
	 */
	public function category() {
		return 'industry';
	}

	/**
	 * Icon.
	 *
	 * @return string
	 */
	public function icon() {
		return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.5 20.5 3.5 13.5a4.95 4.95 0 1 1 7-7l.5.5.5-.5a4.95 4.95 0 1 1 7 7l-7 7Z"/><path d="M12 8v8"/><path d="M8 12h8"/></svg>';
	}

	/**
	 * Industry extension marker.
	 *
	 * @return bool
	 */
	public function is_industry() {
		return true;
	}

	/**
	 * Needs its own setup (onboarding flow) after activation.
	 *
	 * @return bool
	 */
	public function needs_config() {
		return true;
	}

	/**
	 * Configured once PV essentials are answered.
	 *
	 * @return bool
	 */
	public function is_configured() {
		$state = get_option( 'ssc_pharma_setup', array() );
		return ! empty( $state['done'] );
	}

	/**
	 * Capability protecting case records (role-based access).
	 *
	 * @return string
	 */
	public static function cap() {
		// Administrators implicitly hold manage_options; PV officers can be
		// granted this dedicated capability by role plugins.
		return apply_filters( 'ssc_pharma_capability', 'ssc_pharma_cases' );
	}

	/**
	 * Can the current user access pharma case records?
	 *
	 * @return bool
	 */
	public static function user_can() {
		return current_user_can( 'manage_options' ) || current_user_can( self::cap() );
	}

	/**
	 * Grant the dedicated capability to administrators at runtime.
	 */
	public function register() {
		add_filter( 'ssc_submission_types', array( $this, 'register_type' ) );
		// AI safety rules whenever the pharma context is active.
		add_filter( 'ssc_pre_reply', array( $this, 'route_adr_intent' ), 10, 4 );
		add_filter( 'ssc_prompt_extra', array( __CLASS__, 'prompt_rules' ) );
	}

	/**
	 * Register the pharma submission type (server-side whitelist).
	 *
	 * @param array $types Types.
	 * @return array
	 */
	public function register_type( $types ) {
		$types['pharma_adr'] = __( 'Adverse drug reaction report', 'nexachat-ai' );
		return $types;
	}

	/**
	 * Detect explicit ADR reporting intent in chat and route to the form
	 * instead of letting the AI improvise medical advice.
	 *
	 * @param string|null     $pre     Prefiltered reply.
	 * @param string          $message Message.
	 * @param string          $product Product scope.
	 * @param SSC_Chat_Engine $engine  Chat engine (flag attachment).
	 * @return string|null
	 */
	public function route_adr_intent( $pre, $message, $product, $engine = null ) {
		if ( null !== $pre ) {
			return $pre;
		}
		$normalized = ' ' . SSC_Knowledge::normalize( $message ) . ' ';
		$triggers   = array( ' عارضه ', ' عوارض ', ' side effect ', ' side effects ', ' adr ', ' adverse ', ' واکنش دارویی ' );
		$wants_adr  = false;
		foreach ( $triggers as $t ) {
			if ( false !== stripos( $normalized, $t ) ) {
				$wants_adr = true;
				break;
			}
		}
		$offer = __( 'If you want to report a side effect of a medicine, I can register a structured safety report that our team will review. Would you like to start the report?', 'nexachat-ai' );

		/*
		 * Emergency guidance comes FIRST and on its own: it does not wait for
		 * the word "side effect", for the report form, for the AI connection
		 * or for a model answer. This interception runs before the model and
		 * the knowledge base, so the pharmacovigilance prompt rule that tells
		 * the model to urge immediate medical attention never applies here.
		 * The notice is emitted in both languages because the reply otherwise
		 * follows the SITE locale, which may not be the language the visitor
		 * just wrote in.
		 */
		if ( self::looks_like_emergency( $normalized, (string) $message ) ) {
			if ( $engine ) {
				$engine->flags['emergency'] = true;
			}
			if ( $wants_adr || self::mentions_medicine( $normalized ) ) {
				if ( $engine ) {
					$engine->flags['adr_offer'] = true;
				}
				return self::emergency_notice() . "\n\n" . $offer;
			}
			return self::emergency_notice();
		}
		if ( $wants_adr ) {
			if ( $engine ) {
				$engine->flags['adr_offer'] = true;
			}
			return $offer;
		}
		return null;
	}

	/**
	 * Does a message talk about taking a medicine? (An emergency that follows a
	 * medicine is worth a safety report once the person is safe.)
	 *
	 * @param string $normalized Normalized, space-padded message.
	 * @return bool
	 */
	protected static function mentions_medicine( $normalized ) {
		return (bool) preg_match( '/(?:^|\s)(?:medicine|medication|drug|pill|pills|tablet|tablets|capsule|capsules|inhaler|spray|dose|دارو|داروی|قرص|کپسول|اسپری|دوز|مصرف|خوردم|زدم)(?:\s|$)/u', $normalized );
	}

	/**
	 * Emergency-first notice, shown in both supported languages.
	 *
	 * @return string
	 */
	protected static function emergency_notice() {
		$english = __( 'This may be a medical emergency. Stop and seek immediate medical help now — call your local emergency number or go to the nearest emergency department. Do not wait for a reply here.', 'nexachat-ai' );
		$persian = 'این وضعیت می‌تواند اورژانس پزشکی باشد. همین حالا کمک پزشکی فوری بگیرید — با اورژانس تماس بگیرید یا به نزدیک‌ترین بخش اورژانس بروید. منتظر پاسخ در این گفتگو نمانید.';

		/**
		 * Emergency guidance shown before any side-effect form offer.
		 *
		 * Sites outside Iran should filter this to their own emergency number
		 * and languages.
		 *
		 * @param string $notice Default bilingual notice.
		 */
		return (string) apply_filters( 'ssc_emergency_notice', '⚠️ ' . $english . "\n\n⚠️ " . $persian );
	}

	/**
	 * Terms that mean an emergency whenever they appear (unless negated):
	 * the person cannot breathe, is unconscious, is thinking of suicide…
	 * Normalized spelling: Persian letters folded, half-spaces and
	 * punctuation turned into spaces, apostrophes dropped.
	 *
	 * @return string[]
	 */
	protected static function emergency_always_terms() {
		return array(
			// English.
			'unconscious',
			'not breathing',
			'cant breathe',
			'can t breathe',
			'can not breathe',
			'cannot breathe',
			'stopped breathing',
			'stop breathing',
			'no pulse',
			'choking',
			'passed out',
			'suicide',
			'suicidal',
			'kill myself',
			'want to die',
			'end my life',
			'overdosed',
			'took too many',
			'i am dying',
			// Persian.
			'بیهوش',
			'نفس نمی',
			'نفسم نمی',
			'نفسم بند',
			'تنفس ندارد',
			'تنفس نداره',
			'قطع تنفس',
			'ایست قلبی',
			'ایست تنفسی',
			'نبض ندارد',
			'نبض نداره',
			'خودکشی',
			'خودم را بکشم',
			'می خواهم بمیرم',
			'میخواهم بمیرم',
			'می خوام بمیرم',
			'میخوام بمیرم',
			'اوردوز کرد',
			'از هوش رفت',
			'از حال رفت',
			'غش کرد',
			'خفه می',
			'در حال مرگ',
		);
	}

	/**
	 * Symptom phrases: an emergency when they describe a person who has them
	 * (not negated, and not a general question about the medicine).
	 *
	 * @return string[]
	 */
	protected static function emergency_symptom_terms() {
		return array(
			// English.
			'difficulty breathing',
			'trouble breathing',
			'shortness of breath',
			'short of breath',
			'hard to breathe',
			'wheezing',
			'chest pain',
			'chest tightness',
			'tight chest',
			'swelling of the throat',
			'throat swelling',
			'swollen throat',
			'swollen tongue',
			'swollen lips',
			'swollen face',
			'blue lips',
			'severe bleeding',
			'bleeding heavily',
			'seizure',
			'convulsion',
			'collapsed',
			'fainted',
			'anaphyla',
			'heart attack',
			'stroke',
			'severe allergic',
			'life threatening',
			'overdose',
			'poisoning',
			'coma',
			// Persian.
			'تنگی نفس',
			'نفس تنگی',
			'نفستنگی',
			'مشکل تنفسی',
			'مشکل در تنفس',
			'سختی نفس',
			'نفس کشیدن سخت',
			'خس خس',
			'درد قفسه',
			'درد سینه',
			'فشار روی سینه',
			'تشنج',
			'غش',
			'خونریزی شدید',
			'تورم گلو',
			'ورم گلو',
			'تورم زبان',
			'ورم زبان',
			'ورم لب',
			'تورم لب',
			'ورم صورت',
			'تورم صورت',
			'کبودی لب',
			'لب کبود',
			'حساسیت شدید',
			'انافیلاکسی',
			'حمله قلبی',
			'سکته',
			'مسمومیت',
			'اوردوز',
			'کما',
			'تهدید کننده حیات',
		);
	}

	/**
	 * Trouble breathing, said in words a fixed list cannot foresee: the idea of
	 * breathing together with the idea of "cannot", "impossible" or "hard", in
	 * either order and with other words between ("نفس کشیدن برام غیرممکن شده",
	 * "نفس کشیدن برایم خیلی سخت شده", "هوا بهم نمی‌رسه"). A sentence that denies
	 * it ("نفس کشیدن سخت نیست", "without trouble breathing") does not count.
	 * Being unable to breathe counts for anyone; being merely hard needs a
	 * described person, and in a question that person must be the writer or
	 * someone close ("after exercise" is not enough), so a teaching question
	 * stays quiet.
	 *
	 * @param string $text     Normalized, space-padded message.
	 * @param bool   $person   Whether the message describes somebody.
	 * @param bool   $question Whether it is a general question.
	 * @return bool
	 */
	protected static function breathing_trouble( $text, $person, $question = false ) {
		$breath = '(?:نفس(?:م|ش|ت|مان|شان)?|تنفس)(?:\s+(?:کشیدن|کشیدنم|کشیدنش|کشی))?';
		$cannot = '(?:غیر\s?ممکن|ناممکن|نمی\s?(?:تونم|توانم|تونه|تواند|تونی|توانی|تونیم|توانیم|تونن|توانند|شه|شود|شد|اد|اید|یاد))';
		$hard   = '(?:سخت|دشوار|سنگین|گرفته|گرفت|تنگ|بند|مشکل|اذیت)\S*';
		$gap    = '(?:\s+\S+)';
		$unable = array(
			'/(?:^|\s)' . $breath . $gap . '{0,5}?\s+' . $cannot . '(?:\s|$)/u',
			'/(?:^|\s)' . $cannot . $gap . '{0,4}?\s+' . $breath . '(?:\s|$)/u',
			'/(?:^|\s)هوا\s+(?:\S+\s+)?نمی\s?(?:رسه|رسد)(?:\s|$)/u',
			'/(?:^|\s)هوا\s+کم\s+(?:میارم|می\s?ارم|می\s?اورم|اوردم|اورد|اوردی)(?:\s|$)/u',
			'/(?:^|\s)(?:impossible|unable|struggling)\s+to\s+breathe(?:\s|$)/u',
			'/(?:^|\s)breathing\s+(?:is\s+|has\s+become\s+|became\s+)?impossible(?:\s|$)/u',
			'/(?:^|\s)(?:can\s?t|cannot|can\s+not|couldn\s?t|could\s+not)\s+get\s+(?:any\s+|enough\s+)?air(?:\s|$)/u',
			'/(?:^|\s)(?:gasping|suffocating)(?:\s|$)/u',
		);
		$hard_p = array(
			'/(?:^|\s)' . $breath . $gap . '{0,3}?\s+' . $hard . '(?:\s|$)/u',
			'/(?:^|\s)' . $hard . $gap . '{0,3}?\s+' . $breath . '(?:\s|$)/u',
			'/(?:^|\s)breathing\s+(?:is\s+|has\s+become\s+|became\s+)?(?:very\s+|so\s+|really\s+|too\s+)?(?:hard|difficult)(?:\s|$)/u',
		);
		$own    = (bool) preg_match( '/(?:^|\s)(?:من|ما|برام|برایم|برامون|براش|برایش|بهم|بهش|نفسم|تنفسم|مادرم|پدرم|بچم|بچه ام|پسرم|دخترم|همسرم|بچه|نوزاد|بیمار|مریض|i|im|my|me|we|our|he|she|his|her|him|they|their|son|daughter|baby|child|kid|mother|father|patient)(?:\s|$)/u', $text );
		$list   = $person && ( ! $question || $own ) ? array_merge( $unable, $hard_p ) : $unable;
		foreach ( $list as $pattern ) {
			if ( ! preg_match_all( $pattern, $text, $found, PREG_OFFSET_CAPTURE ) ) {
				continue;
			}
			foreach ( $found[0] as $hit ) {
				$lead  = strlen( $hit[0] ) - strlen( ltrim( $hit[0] ) );
				$start = mb_strlen( substr( $text, 0, $hit[1] + $lead ) );
				$len   = mb_strlen( trim( $hit[0] ) );
				if ( ! self::is_negated_at( $text, $start, $len ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Broad words that only count when somebody is described (my father died,
	 * we called the emergency room), never in a general question.
	 *
	 * @return string[]
	 */
	protected static function emergency_context_terms() {
		return array( 'emergency', 'died', 'death', 'dead', 'اورژانس', 'مرگ', 'فوت' );
	}

	/**
	 * Does the text describe a person (me, my father, the patient…) rather
	 * than ask in general?
	 *
	 * @param string $normalized Normalized, space-padded message.
	 * @return bool
	 */
	protected static function describes_a_person( $normalized ) {
		if ( preg_match( '/(?:^|\s)(?:i|im|i m|my|me|mine|we|our|he|she|his|her|him|they|their|patient|son|daughter|baby|child|kid|toddler|mother|father|mom|dad|wife|husband|brother|sister|friend|grandmother|grandfather|someone|somebody|after taking|after i|after he|after she)(?:\s|$)/u', $normalized ) ) {
			return true;
		}
		if ( preg_match( '/(?:^|\s)(?:من|ما|دارم|داریم|داره|دارد|داشت|داشتم|شده|شد|شدم|کرد|کردم|کرده|میکنم|میکند|میکنه|می کنم|می کند|بچه|بیمار|مریض|بعد از)(?:\s|$)/u', $normalized ) ) {
			return true;
		}
		// A Persian word ending in the first-person possessive (قلبم، مادرم، نفسم).
		return (bool) preg_match( '/(?:^|\s)\S{2,}م(?:\s|$)/u', $normalized );
	}

	/**
	 * A general question about a medicine or a condition (not about a person).
	 *
	 * @param string $normalized Normalized, space-padded message.
	 * @param string $raw        The message as written (its question mark).
	 * @return bool
	 */
	protected static function is_general_question( $normalized, $raw ) {
		if ( '' !== $raw && preg_match( '/[?؟]\s*$/u', trim( $raw ) ) ) {
			return true;
		}
		return (bool) preg_match( '/^\s*(?:what|how|why|can|could|does|do|is|are|will|should|which|when|who|tell me|explain|ایا|چه|چرا|چگونه|چطور)(?:\s|$)|(?:^|\s)(?:چیست|چیه|چی هست)(?:\s|$)/u', $normalized );
	}

	/**
	 * Is the match at $pos negated ("no chest pain", "تنگی نفس ندارم")?
	 *
	 * @param string $text Normalized, space-padded message.
	 * @param int    $pos  Where the term starts (characters).
	 * @param int    $len  Term length (characters).
	 * @return bool
	 */
	protected static function is_negated_at( $text, $pos, $len ) {
		$before = mb_substr( $text, max( 0, $pos - 36 ), min( $pos, 36 ) );
		$after  = mb_substr( $text, $pos + $len, 36 );
		$tails  = array(
			'/(?:^|\s)(?:no|not|never|without|denies|denied|nor|zero)\s+(?:any\s+|a\s+|the\s+|signs?\s+of\s+|history\s+of\s+)?$/u',
			'/(?:^|\s)(?:do|does|did|dont|doesnt|didnt|don t|doesn t|didn t)(?:\s+not)?\s+(?:have|had|get|feel)\s+(?:any\s+)?$/u',
			'/(?:^|\s)(?:have|has|had)\s+no\s+$/u',
			'/(?:^|\s)(?:بدون|هیچ|بی)\s+(?:گونه\s+)?$/u',
		);
		foreach ( $tails as $re ) {
			if ( preg_match( $re, $before ) ) {
				return true;
			}
		}
		$heads = array(
			'/^\s*(?:\S+\s+)?(?:ندارم|ندارد|نداره|ندارند|ندارید|نداریم|نداشت|نداشتم|نداشته|نیست|نبود|نشد|نشده|وجود ندارد)(?:\s|$)/u',
			'/^\s*(?:is\s+|was\s+|are\s+)?(?:absent|ruled out|resolved)(?:\s|$)/u',
		);
		foreach ( $heads as $re ) {
			if ( preg_match( $re, $after ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Is any of the terms in the text, outside a negation?
	 *
	 * @param string   $text  Normalized, space-padded message.
	 * @param string[] $terms Terms (normalized spelling).
	 * @return bool
	 */
	protected static function has_term( $text, $terms ) {
		foreach ( $terms as $term ) {
			$term = trim( (string) $term );
			if ( '' === $term ) {
				continue;
			}
			$len    = mb_strlen( $term );
			$offset = 0;
			while ( false !== ( $pos = mb_stripos( $text, $term, $offset ) ) ) { // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition -- a search loop.
				$offset = $pos + $len;
				// A term starts a word (not "کمال" for "کما"), and a short one is a whole word (not "فوتبال" for "فوت").
				if ( $pos > 0 && ' ' !== mb_substr( $text, $pos - 1, 1 ) ) {
					continue;
				}
				if ( $len <= 4 && ' ' !== mb_substr( $text, $pos + $len, 1 ) ) {
					continue;
				}
				if ( ! self::is_negated_at( $text, $pos, $len ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Screen for a message that may describe a life-threatening situation.
	 *
	 * Deliberately broad: a false positive costs one extra safety sentence,
	 * a false negative costs a visitor their emergency guidance. This is
	 * harm reduction, NOT clinical triage, and it does not replace the
	 * medical judgement of the pharmacovigilance team. It reads Persian and
	 * Arabic spellings, half-spaces and common phrasings ("نمی‌توانم نفس
	 * بکشم", "تنگی نفس دارم", "I cannot breathe") and it stays quiet for
	 * negated sentences ("no chest pain", "تنگی نفس ندارم") and for general
	 * questions that do not describe a person ("Can this cause chest pain?").
	 *
	 * @param string $normalized Normalized, space-padded message.
	 * @param string $raw        The message as written (optional; its question mark helps).
	 * @return bool
	 */
	public static function looks_like_emergency( $normalized, $raw = '' ) {
		$text = ' ' . trim( SSC_Knowledge::normalize( $normalized ) ) . ' ';

		/**
		 * Terms that mark a message as a possible emergency wherever they appear.
		 *
		 * @param string[] $terms      Default term list.
		 * @param string   $normalized Normalized message.
		 */
		$always = (array) apply_filters( 'ssc_emergency_terms', self::emergency_always_terms(), $text );
		if ( self::has_term( $text, $always ) ) {
			return true;
		}
		// "I am not able to breathe" style phrases, in Persian and English.
		if ( preg_match( '/نمی\s?(?:تونم|توانم|تونه|تواند|تونی|توانی|توانیم|تونیم|توانند)\s+(?:درست\s+|راحت\s+)?نفس|نفس\S*\s+(?:بالا\s+)?نمی\s?(?:اد|اید|یاد)|(?:unable|not able)\s+to\s+breathe/u', $text ) ) {
			return true;
		}

		// The rest need a person (or at least not a general question).
		$person   = self::describes_a_person( $text );
		$question = self::is_general_question( $text, (string) $raw );
		if ( $question && ! $person ) {
			return false;
		}

		/**
		 * Symptom phrases that mark an emergency when they describe somebody.
		 *
		 * @param string[] $terms Default term list.
		 * @param string   $text  Normalized message.
		 */
		$symptoms = (array) apply_filters( 'ssc_emergency_symptom_terms', self::emergency_symptom_terms(), $text );
		if ( self::has_term( $text, $symptoms ) ) {
			return true;
		}
		// Breathing named together with "cannot", "impossible" or "hard", however it is worded.
		if ( self::breathing_trouble( $text, $person, $question ) ) {
			return true;
		}
		// Broad words need a described person and no question.
		return $person && ! $question && self::has_term( $text, self::emergency_context_terms() );
	}

	/**
	 * Prompt rules injected while the module is active (hooked by register()).
	 *
	 * @param string $extra Existing extra instructions.
	 * @return string
	 */
	public static function prompt_rules( $extra ) {
		$rules  = "\nPHARMACOVIGILANCE RULES (non-negotiable):\n";
		$rules .= "- NEVER invent or guess pharmaceutical facts: dosages, indications, contraindications, interactions, or product quality claims.\n";
		$rules .= "- NEVER provide personalized treatment or medication advice. Refer the user to a healthcare professional.\n";
		$rules .= "- When a user describes a possible side effect, do NOT assess its cause. Encourage them to file the structured ADR report and, when the description may be serious, urge immediate medical attention.\n";
		if ( 'general_education' === SSC_Settings::get( 'pharma_answer_mode', 'approved_only' ) ) {
			$rules .= "- General educational health information is allowed. Clearly distinguish it from approved company/product information; do not imply endorsement or invent sources.\n";
			$rules .= "- Product-specific medical claims must come ONLY from the supplied approved references. If missing, say you do not have that information and offer human support.\n";
		} else {
			$rules .= "- Only use approved information supplied in the ORGANIZATION PROFILE and REFERENCE KNOWLEDGE; otherwise say you do not have that information and offer human support. Do not supplement it with general medical knowledge.\n";
		}
		return $extra . $rules;
	}

	/*
	 * --------------------------------------------------------------
	 * ADR form definition.
	 * --------------------------------------------------------------
	 */

	/**
	 * Structured ADR form schema (labels translatable; keys stable).
	 *
	 * @return array
	 */
	public static function form_schema() {
		return array(
			'reporter' => array(
				'label'  => __( 'Reporter', 'nexachat-ai' ),
				'fields' => array(
					'reporter_type' => array(
						'label'   => __( 'Reporter role', 'nexachat-ai' ),
						'type'    => 'select',
						'options' => array( 'patient', 'physician', 'pharmacist', 'nurse', 'other_health_professional' ),
					),
					'name'          => array(
						'label'    => __( 'Reporter name', 'nexachat-ai' ),
						'type'     => 'text',
						'required' => true,
					),
					'phone'         => array(
						'label'    => __( 'Contact phone', 'nexachat-ai' ),
						'type'     => 'tel',
						'required' => true,
					),
					'patient_age'   => array(
						'label' => __( 'Patient age (years)', 'nexachat-ai' ),
						'type'  => 'number',
					),
					'patient_sex'   => array(
						'label'   => __( 'Patient sex', 'nexachat-ai' ),
						'type'    => 'select',
						'options' => array( 'female', 'male', 'other', 'unknown' ),
					),
				),
			),
			'product'  => array(
				'label'  => __( 'Suspected product', 'nexachat-ai' ),
				'fields' => array(
					'product'      => array(
						'label'    => __( 'Product name', 'nexachat-ai' ),
						'type'     => 'product',
						'required' => true,
					),
					'batch_number' => array(
						'label' => __( 'Batch / lot number', 'nexachat-ai' ),
						'type'  => 'text',
					),
					'dose'         => array(
						'label' => __( 'Dose and frequency used', 'nexachat-ai' ),
						'type'  => 'text',
					),
					'route'        => array(
						'label'   => __( 'Route of administration', 'nexachat-ai' ),
						'type'    => 'select',
						'options' => array( 'oral', 'topical', 'intravenous', 'intramuscular', 'subcutaneous', 'inhalation', 'ophthalmic', 'other', 'unknown' ),
					),
				),
			),
			'reaction' => array(
				'label'  => __( 'Suspected reaction', 'nexachat-ai' ),
				'fields' => array(
					'description'       => array(
						'label'    => __( 'What happened? Describe the reaction and dates.', 'nexachat-ai' ),
						'type'     => 'textarea',
						'required' => true,
					),
					'severity'          => array(
						'label'   => __( 'Clinical severity (how it felt)', 'nexachat-ai' ),
						'type'    => 'select',
						'options' => array( 'mild', 'moderate', 'severe' ),
					),
					'seriousness'       => array(
						'label'   => __( 'Regulatory seriousness criteria (any that apply)', 'nexachat-ai' ),
						'type'    => 'checkboxes',
						'options' => array(
							'death'               => __( 'Resulted in death', 'nexachat-ai' ),
							'life_threatening'    => __( 'Life-threatening', 'nexachat-ai' ),
							'hospitalization'     => __( 'Required or prolonged hospitalization', 'nexachat-ai' ),
							'disability'          => __( 'Persistent or significant disability', 'nexachat-ai' ),
							'congenital_anomaly'  => __( 'Congenital anomaly or birth defect', 'nexachat-ai' ),
							'medically_important' => __( 'Other medically important event', 'nexachat-ai' ),
						),
					),
					'outcome'           => array(
						'label'   => __( 'Outcome so far', 'nexachat-ai' ),
						'type'    => 'select',
						'options' => array( 'recovered', 'recovering', 'not_recovered', 'sequelae', 'hospitalized', 'death', 'unknown' ),
					),
					'concomitant_drugs' => array(
						'label' => __( 'Other medicines taken at the same time', 'nexachat-ai' ),
						'type'  => 'textarea',
					),
				),
			),
		);
	}

	/*
	 * --------------------------------------------------------------
	 * Form configuration (presets, per-field switches, custom questions).
	 * --------------------------------------------------------------
	 */

	/**
	 * Fields that can never be switched off: without an identifiable
	 * reporter, a way to contact them, a suspect product and a described
	 * reaction the report is not a valid safety case.
	 */
	const LOCKED_FIELDS = array( 'name', 'phone', 'product', 'description' );

	/** Types allowed for admin-defined questions. */
	const CUSTOM_TYPES = array( 'text', 'textarea', 'number', 'select', 'checkboxes' );

	/** Most admin-defined questions a form may carry. */
	const MAX_CUSTOM = 15;

	/**
	 * Built-in fields per preset ('full' = every field, 'custom' = per-field switches).
	 *
	 * @return array
	 */
	public static function presets() {
		return array(
			'short'    => array( 'name', 'phone', 'product', 'description', 'seriousness' ),
			'standard' => array( 'reporter_type', 'name', 'phone', 'patient_age', 'patient_sex', 'product', 'description', 'severity', 'seriousness', 'outcome' ),
		);
	}

	/**
	 * Preset names for the admin screen.
	 *
	 * @return array
	 */
	public static function preset_labels() {
		return array(
			'short'    => __( 'Short — essentials only (5 questions)', 'nexachat-ai' ),
			'standard' => __( 'Standard — essentials plus patient and outcome', 'nexachat-ai' ),
			'full'     => __( 'Full — every question (most complete reports)', 'nexachat-ai' ),
			'custom'   => __( 'Custom — choose each question', 'nexachat-ai' ),
		);
	}

	/**
	 * Type names for admin-defined questions.
	 *
	 * @return array
	 */
	public static function custom_type_labels() {
		return array(
			'text'       => __( 'Short text', 'nexachat-ai' ),
			'textarea'   => __( 'Long text', 'nexachat-ai' ),
			'number'     => __( 'Number', 'nexachat-ai' ),
			'select'     => __( 'Dropdown (one choice)', 'nexachat-ai' ),
			'checkboxes' => __( 'Checkboxes (several choices)', 'nexachat-ai' ),
		);
	}

	/**
	 * Saved form configuration, normalized.
	 *
	 * @return array
	 */
	public static function form_config() {
		$raw = SSC_Settings::get( 'adr_form', array() );
		return self::sanitize_form_config( is_array( $raw ) ? $raw : array() );
	}

	/**
	 * Normalize a form configuration (admin POST or stored value).
	 *
	 * Defaults keep the historical behaviour: the full form.
	 *
	 * @param array $raw Raw configuration.
	 * @return array
	 */
	public static function sanitize_form_config( $raw ) {
		$raw    = is_array( $raw ) ? $raw : array();
		$preset = isset( $raw['preset'] ) ? sanitize_key( $raw['preset'] ) : 'full';
		$config = array(
			'preset'            => in_array( $preset, array( 'short', 'standard', 'full', 'custom' ), true ) ? $preset : 'full',
			'collapse_optional' => ( isset( $raw['collapse_optional'] ) && in_array( $raw['collapse_optional'], array( 'no', '0', 0, false ), true ) ) ? 'no' : 'yes',
			'intro'             => isset( $raw['intro'] ) ? mb_substr( sanitize_textarea_field( (string) $raw['intro'] ), 0, 400 ) : '',
			'fields'            => array(),
			'custom'            => array(),
		);

		$known = array();
		foreach ( self::form_schema() as $section ) {
			foreach ( $section['fields'] as $key => $field ) {
				$known[ $key ] = $field;
			}
		}
		$fields = isset( $raw['fields'] ) && is_array( $raw['fields'] ) ? $raw['fields'] : array();
		foreach ( $fields as $key => $f ) {
			if ( ! isset( $known[ $key ] ) || ! is_array( $f ) ) {
				continue;
			}
			$locked                   = in_array( $key, self::LOCKED_FIELDS, true );
			$config['fields'][ $key ] = array(
				'on'    => $locked || ! empty( $f['on'] ),
				'req'   => $locked || ! empty( $f['req'] ),
				'label' => isset( $f['label'] ) ? mb_substr( sanitize_text_field( (string) $f['label'] ), 0, 160 ) : '',
			);
		}

		$custom = isset( $raw['custom'] ) && is_array( $raw['custom'] ) ? array_values( $raw['custom'] ) : array();
		$taken  = array_fill_keys( array_merge( array_keys( $known ), array( 'consent', 'type', 'ssc_hp', 'extra' ) ), true );
		foreach ( $custom as $q ) {
			if ( count( $config['custom'] ) >= self::MAX_CUSTOM ) {
				break;
			}
			if ( ! is_array( $q ) ) {
				continue;
			}
			$label = isset( $q['label'] ) ? mb_substr( sanitize_text_field( (string) $q['label'] ), 0, 160 ) : '';
			if ( '' === $label ) {
				continue;
			}
			$type    = isset( $q['type'] ) && in_array( $q['type'], self::CUSTOM_TYPES, true ) ? $q['type'] : 'text';
			$options = array();
			if ( in_array( $type, array( 'select', 'checkboxes' ), true ) ) {
				$list    = isset( $q['options'] ) ? ( is_array( $q['options'] ) ? $q['options'] : preg_split( '/[\r\n,،]+/u', (string) $q['options'] ) ) : array();
				$options = array_slice( array_values( array_unique( array_filter( array_map( 'sanitize_text_field', (array) $list ), 'strlen' ) ) ), 0, 30 );
				if ( ! $options ) {
					$type = 'text'; // A choice question without choices degrades to text.
				}
			}
			// Stable key: the posted one (hidden input), else derived from the label.
			$key = isset( $q['key'] ) ? sanitize_key( (string) $q['key'] ) : '';
			if ( 0 !== strpos( $key, 'q_' ) ) {
				$key = 'q_' . substr( md5( $label ), 0, 8 );
			}
			$base = $key;
			for ( $n = 2; isset( $taken[ $key ] ); ++$n ) {
				$key = $base . '_' . $n;
			}
			$taken[ $key ]      = true;
			$config['custom'][] = array(
				'key'      => $key,
				'label'    => $label,
				'type'     => $type,
				'options'  => $options,
				'required' => ! empty( $q['required'] ),
			);
		}
		return $config;
	}

	/**
	 * The fields the form actually shows, in order, after presets, switches,
	 * label overrides and admin-defined questions are applied.
	 *
	 * Each entry: key, label, type, required, options (as in the schema),
	 * custom (bool).
	 *
	 * @return array
	 */
	public static function effective_fields() {
		$config  = self::form_config();
		$presets = self::presets();
		$out     = array();
		foreach ( self::form_schema() as $section ) {
			foreach ( $section['fields'] as $key => $field ) {
				$locked   = in_array( $key, self::LOCKED_FIELDS, true );
				$override = isset( $config['fields'][ $key ] ) ? $config['fields'][ $key ] : null;
				if ( 'custom' === $config['preset'] ) {
					// Never-configured fields start switched on (the full form).
					$on       = $locked || null === $override || ! empty( $override['on'] );
					$required = $locked || ( null === $override ? ! empty( $field['required'] ) : ! empty( $override['req'] ) );
				} else {
					$on       = $locked || 'full' === $config['preset'] || in_array( $key, $presets[ $config['preset'] ], true );
					$required = $locked || ! empty( $field['required'] );
				}
				if ( ! $on ) {
					continue;
				}
				$field['key']      = $key;
				$field['required'] = $required;
				$field['custom']   = false;
				if ( $override && '' !== $override['label'] ) {
					$field['label'] = $override['label'];
				}
				$out[] = $field;
			}
		}
		foreach ( $config['custom'] as $q ) {
			$q['custom'] = true;
			$out[]       = $q;
		}
		return $out;
	}

	/**
	 * Public (widget-facing) ADR form: labels translated, values validated.
	 *
	 * @return array
	 */
	public static function adr_options_public() {
		$out = array();
		foreach ( self::effective_fields() as $field ) {
			$key   = $field['key'];
			$entry = array(
				'key'      => $key,
				'label'    => $field['label'],
				'type'     => $field['type'],
				'required' => ! empty( $field['required'] ),
			);
			if ( ! empty( $field['options'] ) ) {
				$entry['options'] = array();
				foreach ( $field['options'] as $value => $label ) {
					if ( $field['custom'] ) {
						// Admin-defined choices: the text is both value and label.
						$entry['options'][] = array(
							'value' => $label,
							'label' => $label,
						);
					} elseif ( 'checkboxes' === $field['type'] ) {
						$entry['options'][] = array(
							'value' => $value,
							'label' => $label,
						);
					} else {
						$entry['options'][] = array(
							'value' => $label,
							'label' => self::option_label( $key, $label ),
						);
					}
				}
			}
			$out[] = $entry;
		}
		return $out;
	}

	/**
	 * Answers to admin-defined questions stored on a case, as
	 * array( array( question text => answer ) ), using the question text saved
	 * with the report. Wrapped in one group so views can skip it when empty.
	 *
	 * @param array $extra Decoded extra_fields.
	 * @return array
	 */
	public static function custom_answers( $extra ) {
		$out       = array();
		$questions = isset( $extra['_questions'] ) && is_array( $extra['_questions'] ) ? $extra['_questions'] : array();
		foreach ( $questions as $key => $label ) {
			if ( ! isset( $extra[ $key ] ) ) {
				continue;
			}
			$value = is_array( $extra[ $key ] ) ? implode( '، ', array_map( 'strval', $extra[ $key ] ) ) : (string) $extra[ $key ];
			if ( '' !== trim( $value ) ) {
				$out[ (string) $label ] = $value;
			}
		}
		return array( $out );
	}

	/**
	 * Admin-defined answers as one CSV-friendly cell ("question: answer | …").
	 *
	 * @param array $extra Decoded extra_fields.
	 * @return string
	 */
	public static function custom_answers_text( $extra ) {
		$parts   = array();
		$answers = self::custom_answers( $extra );
		foreach ( $answers[0] as $question => $answer ) {
			$parts[] = $question . ': ' . $answer;
		}
		return implode( ' | ', $parts );
	}

	/**
	 * Form presentation options for the widget.
	 *
	 * @return array
	 */
	public static function adr_form_public() {
		$config = self::form_config();
		return array(
			'collapse' => 'yes' === $config['collapse_optional'],
			'intro'    => $config['intro'],
		);
	}

	/**
	 * Translate stored option values into labels.
	 *
	 * @param string $field Field key.
	 * @param string $value Stored value.
	 * @return string
	 */
	public static function option_label( $field, $value ) {
		$labels = array(
			'reporter_type' => array(
				'patient'                   => __( 'Patient / consumer', 'nexachat-ai' ),
				'physician'                 => __( 'Physician', 'nexachat-ai' ),
				'pharmacist'                => __( 'Pharmacist', 'nexachat-ai' ),
				'nurse'                     => __( 'Nurse', 'nexachat-ai' ),
				'other_health_professional' => __( 'Other health professional', 'nexachat-ai' ),
			),
			'patient_sex'   => array(
				'female'  => __( 'Female', 'nexachat-ai' ),
				'male'    => __( 'Male', 'nexachat-ai' ),
				'other'   => __( 'Other', 'nexachat-ai' ),
				'unknown' => __( 'Unknown', 'nexachat-ai' ),
			),
			'route'         => array(
				'oral'          => __( 'Oral', 'nexachat-ai' ),
				'topical'       => __( 'Topical', 'nexachat-ai' ),
				'intravenous'   => __( 'Intravenous', 'nexachat-ai' ),
				'intramuscular' => __( 'Intramuscular', 'nexachat-ai' ),
				'subcutaneous'  => __( 'Subcutaneous', 'nexachat-ai' ),
				'inhalation'    => __( 'Inhalation', 'nexachat-ai' ),
				'ophthalmic'    => __( 'Ophthalmic', 'nexachat-ai' ),
				'other'         => __( 'Other', 'nexachat-ai' ),
				'unknown'       => __( 'Unknown', 'nexachat-ai' ),
			),
			'severity'      => array(
				'mild'     => __( 'Mild', 'nexachat-ai' ),
				'moderate' => __( 'Moderate', 'nexachat-ai' ),
				'severe'   => __( 'Severe', 'nexachat-ai' ),
			),
			'outcome'       => array(
				'recovered'     => __( 'Recovered', 'nexachat-ai' ),
				'recovering'    => __( 'Recovering', 'nexachat-ai' ),
				'not_recovered' => __( 'Not recovered', 'nexachat-ai' ),
				'sequelae'      => __( 'Sequelae / permanent impairment', 'nexachat-ai' ),
				'hospitalized'  => __( 'Hospitalized', 'nexachat-ai' ),
				'death'         => __( 'Death', 'nexachat-ai' ),
				'unknown'       => __( 'Unknown', 'nexachat-ai' ),
			),
		);
		if ( isset( $labels[ $field ] ) && isset( $labels[ $field ][ $value ] ) ) {
			return $labels[ $field ][ $value ];
		}
		return $value;
	}

	/**
	 * SERIOUSNESS evaluation - deliberately independent from severity.
	 *
	 * A case is serious when ANY ICH E2A criterion is flagged OR the outcome
	 * is in the serious outcome set. A "mild" severity never downgrades an
	 * otherwise serious case.
	 *
	 * @param array $row Submission row (or handled payload).
	 * @return bool
	 */
	public static function is_serious_row( $row ) {
		$criteria = self::seriousness_criteria( $row );
		if ( ! empty( $criteria ) ) {
			return true;
		}
		$serious_outcomes = array( 'hospitalized', 'death', 'sequelae' );
		return isset( $row['outcome'] ) && in_array( (string) $row['outcome'], $serious_outcomes, true );
	}

	/**
	 * Extract flagged seriousness criteria from a row/payload.
	 *
	 * @param array $row Row.
	 * @return string[]
	 */
	public static function seriousness_criteria( $row ) {
		$extra   = isset( $row['extra_fields'] ) ? json_decode( (string) $row['extra_fields'], true ) : array();
		$extra   = is_array( $extra ) ? $extra : array();
		$flags   = isset( $extra['seriousness'] ) && is_array( $extra['seriousness'] ) ? $extra['seriousness'] : array();
		$allowed = array( 'death', 'life_threatening', 'hospitalization', 'disability', 'congenital_anomaly', 'medically_important' );
		return array_values( array_intersect( array_map( 'strval', (array) $flags ), $allowed ) );
	}

	/*
	 * --------------------------------------------------------------
	 * Submission handling (called via leads module dispatch).
	 * --------------------------------------------------------------
	 */

	/**
	 * Handle an ADR submission.
	 *
	 * @param array  $params Raw input.
	 * @param string $ip     Client ip.
	 * @return array|WP_Error
	 */
	public function handle_submission( $params, $ip = '' ) {
		$params = is_array( $params ) ? $params : array();
		if ( isset( $params['extra'] ) && ! is_array( $params['extra'] ) ) {
			$params['extra'] = is_string( $params['extra'] ) ? json_decode( $params['extra'], true ) : array();
		}
		if ( ! SSC_Modules::is_active( 'pharma' ) ) {
			return new WP_Error( 'ssc_module_off', __( 'ADR reporting is disabled on this site.', 'nexachat-ai' ), array( 'status' => 404 ) );
		}

		if ( ! empty( $params['ssc_hp'] ) ) {
			return array(
				'ok'   => true,
				'fake' => true,
			); // Honeypot.
		}

		$name  = isset( $params['name'] ) ? SSC_Input::text( $params['name'], 81 ) : '';
		$phone = isset( $params['phone'] ) ? SSC_Input::phone( $params['phone'] ) : '';
		$desc  = isset( $params['description'] ) ? SSC_Input::text( $params['description'], 10001, true ) : '';

		$errors = array();
		if ( mb_strlen( $name ) < 2 || mb_strlen( $name ) > 80 ) {
			$errors[] = __( 'Please enter the reporter name (2-80 characters).', 'nexachat-ai' );
		}
		$pattern = (string) apply_filters( 'ssc_phone_pattern', '/^\+?\d[\d\s\-]{6,18}\d$/' );
		if ( ! preg_match( $pattern, $phone ) ) {
			$errors[] = __( 'Please enter a valid contact phone.', 'nexachat-ai' );
		}
		if ( mb_strlen( $desc ) < 10 || mb_strlen( $desc ) > 10000 ) {
			$errors[] = __( 'Please describe the reaction (10-10000 characters).', 'nexachat-ai' );
		}

		// Consent is REQUIRED for ADR reports (sensitive health data), independent
		// of the leads-module consent toggle. Stored with the case for audit.
		$consent_meta = array();
		$consent      = SSC_Input::consent( $params['consent'] ?? false );
		if ( ! $consent ) {
			$errors[] = __( 'Your consent is required to submit a safety report.', 'nexachat-ai' );
		} else {
			$consent_meta = array(
				'_consent'        => 1,
				'_consent_scope'  => 'pharma_adr',
				'_consent_hash'   => hash( 'sha256', SSC_Input::consent_text( true ) ),
				'_consent_policy' => (string) SSC_Settings::get( 'consent_link', '' ),
				'_consent_at'     => current_time( 'mysql' ),
			);
		}

		// Structured fields validated against the CONFIGURED form (whitelist):
		// switched-off questions are ignored even when posted.
		$extra     = array();
		$questions = array();
		foreach ( self::effective_fields() as $field ) {
			$key = $field['key'];
			if ( 'name' === $key || 'phone' === $key || 'description' === $key ) {
				continue; // Top-level columns.
			}
			$raw = isset( $params[ $key ] ) ? $params[ $key ] : ( isset( $params['extra'][ $key ] ) ? $params['extra'][ $key ] : '' );
			if ( $field['custom'] ) {
				// Keep the question text with the answer: later edits of the
				// question must not change what an old report means.
				$questions[ $key ] = $field['label'];
			}
			/* translators: %s: field label. */
			$required_message = sprintf( __( 'The field "%s" is required.', 'nexachat-ai' ), $field['label'] );
			switch ( $field['type'] ) {
				case 'checkboxes':
					$allowed = $field['custom'] ? $field['options'] : array_keys( $field['options'] );
					$raw     = array_values( array_intersect( SSC_Input::list_value( $raw ), $allowed ) );
					if ( ! empty( $field['required'] ) && empty( $raw ) ) {
						$errors[] = $required_message;
					}
					$extra[ $key ] = $field['custom'] ? array_map( 'sanitize_text_field', $raw ) : array_map( 'sanitize_key', $raw );
					break;
				case 'select':
					if ( $field['custom'] ) {
						$raw = SSC_Input::text( $raw, 200 );
						$raw = in_array( $raw, $field['options'], true ) ? $raw : '';
					} else {
						$raw = sanitize_key( SSC_Input::text( $raw, 100 ) );
						if ( ! in_array( $raw, array_map( 'sanitize_key', $field['options'] ), true ) ) {
							$raw = '';
						}
					}
					if ( ! empty( $field['required'] ) && '' === $raw ) {
						$errors[] = $required_message;
					}
					if ( '' !== $raw ) {
						$extra[ $key ] = $raw;
					}
					break;
				case 'product':
						// Product must exist in the catalog (product identification).
						$raw   = SSC_Input::text( $raw, 100 );
						$valid = 'general';
					foreach ( (array) SSC_Settings::get( 'products', array() ) as $p ) {
						if ( isset( $p['id'] ) && $p['id'] === $raw ) {
							$valid = $raw;
							break;
						}
					}
					if ( 'general' === $valid ) {
						$errors[] = __( 'Unknown product. Please choose from the list.', 'nexachat-ai' );
					}
						$extra['product_id'] = $valid;
					break;
				case 'number':
					// Typed on a Persian keyboard as ۲۵ or with ٫ as the decimal point: read as the number it is.
					// A list, an object or text that is not a number is refused, not treated as empty.
					$typed = is_scalar( $raw ) ? trim( (string) $raw ) : ( null === $raw ? '' : '*' );
					$raw   = '' === $typed ? '' : SSC_Input::ascii_number( $typed );
					if ( '' !== $typed && '' === $raw ) {
						if ( 'patient_age' === $key ) {
							$errors[] = __( 'Patient age must be between 0 and 130 years.', 'nexachat-ai' );
						} else {
							/* translators: %s: field label. */
							$errors[] = sprintf( __( 'The field "%s" must be a number.', 'nexachat-ai' ), $field['label'] );
						}
					} elseif ( 'patient_age' === $key && '' !== $raw && ( (float) $raw < 0 || (float) $raw > 130 ) ) {
						$errors[] = __( 'Patient age must be between 0 and 130 years.', 'nexachat-ai' );
					}
					if ( ! empty( $field['required'] ) && '' === $typed ) {
						$errors[] = $required_message;
					}
					$extra[ $key ] = '' !== $raw ? (float) $raw : '';
					break;
				case 'textarea':
					$extra[ $key ] = SSC_Input::text( $raw, 4000, true );
					if ( ! empty( $field['required'] ) && '' === trim( $extra[ $key ] ) ) {
						$errors[] = $required_message;
					}
					break;
				default:
					$extra[ $key ] = SSC_Input::text( $raw, $field['custom'] ? 300 : 100 );
					if ( ! empty( $field['required'] ) && '' === trim( $extra[ $key ] ) ) {
						$errors[] = $required_message;
					}
			}
		}
		if ( $questions ) {
			$extra['_questions'] = $questions;
		}

		if ( ! empty( $errors ) ) {
			return new WP_Error( 'ssc_validation', implode( ' ', $errors ), array( 'status' => 400 ) );
		}

		// Attach consent receipt to extra_fields.
		if ( ! empty( $consent_meta ) ) {
			$extra = array_merge( $extra, $consent_meta );
		}

		$id = SSC_Schema::insert_submission(
			array(
				'type'              => 'pharma_adr',
				'name'              => $name,
				'phone'             => $phone,
				'product'           => isset( $extra['product_id'] ) ? $extra['product_id'] : '',
				'description'       => $desc,
				'severity'          => isset( $extra['severity'] ) ? $extra['severity'] : '',
				'outcome'           => isset( $extra['outcome'] ) ? $extra['outcome'] : '',
				'batch_number'      => isset( $extra['batch_number'] ) ? $extra['batch_number'] : '',
				'concomitant_drugs' => isset( $extra['concomitant_drugs'] ) ? $extra['concomitant_drugs'] : '',
				'reporter_type'     => isset( $extra['reporter_type'] ) ? $extra['reporter_type'] : '',
				'extra_fields'      => wp_json_encode( $extra ),
				'ip'                => $ip,
			)
		);
		if ( ! $id ) {
			return new WP_Error( 'ssc_storage', __( 'The report could not be stored. Please try again.', 'nexachat-ai' ), array( 'status' => 500 ) );
		}

		// Audit trail entry (case creation).
		SSC_Schema::audit( $id, 'created', '', 'new', 'system', __( 'ADR report received', 'nexachat-ai' ) );

		// Notifications: immediate for serious cases, normal otherwise.
		if ( SSC_Modules::is_active( 'notifications' ) ) {
			$notifications = SSC_Modules::get( 'notifications' );
			$notifications->dispatch_for_submission( $id, 'pharma_adr' );
		}
		/**
		 * Fired when an ADR report is captured (PV integrations).
		 */
		do_action( 'ssc_adr_reported', $id );

		return array( 'ok' => true );
	}

	/*
	 * --------------------------------------------------------------
	 * Case management admin.
	 * --------------------------------------------------------------
	 */

	/**
	 * Admin hooks.
	 */
	public function register_admin() {
		add_action( 'admin_menu', array( $this, 'menu' ), 30 );
		add_action( 'admin_init', array( $this, 'handle_actions' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
	}

	/**
	 * ADR cases menu (capability-gated, own onboarding entry).
	 */
	public function menu() {
		if ( ! SSC_Modules::is_active( 'pharma' ) ) {
			return;
		}
		add_submenu_page(
			current_user_can( 'manage_options' ) ? 'ssc-dashboard' : 'tools.php',
			__( 'ADR Cases', 'nexachat-ai' ),
			__( 'ADR Cases', 'nexachat-ai' ),
			current_user_can( 'manage_options' ) ? 'manage_options' : self::cap(),
			'ssc-pharma',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Admin assets on the pharma page only.
	 *
	 * @param string $hook Hook.
	 */
	public function assets( $hook ) {
		if ( false === strpos( (string) $hook, 'ssc-pharma' ) ) {
			return;
		}
		wp_enqueue_style( 'ssc-admin', SSC_CHATBOT_URL . 'assets/css/admin.css', array(), SSC_CHATBOT_VERSION );
	}

	/**
	 * Case page actions (status workflow, follow-up note, export).
	 */
	public function handle_actions() {
		if ( ! self::user_can() ) {
			return;
		}

		// Onboarding completion.
		if ( current_user_can( 'manage_options' ) && isset( $_POST['ssc_pharma_setup_save'] ) && check_admin_referer( 'ssc_pharma_setup' ) ) {
			$pv_email = isset( $_POST['pv_contact'] ) ? sanitize_email( wp_unslash( $_POST['pv_contact'] ) ) : '';
			if ( ! is_email( $pv_email ) ) {
				wp_die( esc_html__( 'Enter a valid pharmacovigilance contact email.', 'nexachat-ai' ) );
			}
			update_option(
				'ssc_pharma_setup',
				array(
					'done'       => 1,
					'pv_contact' => $pv_email,
					'at'         => time(),
				),
				false
			);
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'       => 'ssc-pharma',
						'configured' => 1,
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		if ( isset( $_POST['case_id'] ) ) {
			global $wpdb;
			$case_type = $wpdb->get_var( $wpdb->prepare( 'SELECT type FROM ' . SSC_Schema::table_name() . ' WHERE id = %d', (int) $_POST['case_id'] ) );
			if ( 'pharma_adr' !== $case_type ) {
				return;
			}
		}

		// Case status change (follow-up workflow).
		if ( isset( $_POST['ssc_case_status'], $_POST['case_id'] ) && check_admin_referer( 'ssc_case_' . (int) $_POST['case_id'] ) ) {
			$id     = (int) $_POST['case_id'];
			$status = sanitize_key( wp_unslash( $_POST['ssc_case_status'] ) );
			$note   = isset( $_POST['case_note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['case_note'] ) ) : '';
			$map    = self::case_statuses();
			if ( isset( $map[ $status ] ) ) {
				SSC_Schema::update_status( $id, $status, $note );
			}
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'    => 'ssc-pharma',
						'updated' => 1,
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		// Per-record privacy actions (export single / anonymize / purge).
		if ( isset( $_POST['ssc_case_privacy'], $_POST['case_id'] ) && check_admin_referer( 'ssc_case_privacy_' . (int) $_POST['case_id'] ) ) {
			$id     = (int) $_POST['case_id'];
			$action = sanitize_key( wp_unslash( $_POST['ssc_case_privacy'] ) );
			$actor  = wp_get_current_user()->user_login ? wp_get_current_user()->user_login : 'admin';

			if ( 'export' === $action ) {
				$this->export_single( $id );
				exit;
			}
			if ( 'anonymize' === $action ) {
				$this->anonymize_case( $id, $actor );
				wp_safe_redirect(
					add_query_arg(
						array(
							'page'    => 'ssc-pharma',
							'view'    => $id,
							'privacy' => 'anon',
						),
						admin_url( 'admin.php' )
					)
				);
				exit;
			}
			if ( 'purge' === $action ) {
				$this->purge_case( $id, $actor );
				wp_safe_redirect(
					add_query_arg(
						array(
							'page'   => 'ssc-pharma',
							'purged' => 1,
						),
						admin_url( 'admin.php' )
					)
				);
				exit;
			}
		}

		// Export CSV (dedicated, capability-gated).
		if ( isset( $_POST['ssc_pharma_export'] ) && check_admin_referer( 'ssc_pharma_export' ) ) {
			$this->export_csv();
		}
	}

	/**
	 * Export a single ADR case as JSON (compliance / data-subject request).
	 *
	 * @param int $id Case id.
	 */
	protected function export_single( $id ) {
		global $wpdb;
		$table = SSC_Schema::table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- single-case export.
		$case = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d AND type = 'pharma_adr'", $id ), ARRAY_A );
		if ( ! $case ) {
			wp_die( esc_html__( 'Case not found.', 'nexachat-ai' ) );
		}
		$audit   = SSC_Schema::get_audit( $id );
		$payload = array(
			'exported_at' => gmdate( 'c' ),
			'case'        => $case,
			'audit'       => $audit,
		);
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=ssc-adr-case-' . $id . '.json' );
		echo wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
		exit;
	}

	/**
	 * Anonymize PII on a single case (keeps clinical fields for PV review).
	 *
	 * @param int    $id    Case id.
	 * @param string $actor Actor label for the audit trail.
	 */
	public function anonymize_case( $id, $actor = 'admin' ) {
		global $wpdb;
		$table = SSC_Schema::table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- targeted privacy action.
		$case = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d AND type = 'pharma_adr'", $id ), ARRAY_A );
		if ( ! $case ) {
			return;
		}
		$extra = json_decode( (string) $case['extra_fields'], true );
		$extra = is_array( $extra ) ? $extra : array();
		unset( $extra['patient_age'], $extra['patient_sex'] );
		$extra['_anonymized']    = 1;
		$extra['_anonymized_at'] = current_time( 'mysql' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- targeted privacy action.
		$wpdb->update(
			$table,
			array(
				'name'         => '[redacted]',
				'phone'        => '',
				'ip'           => '',
				'extra_fields' => wp_json_encode( $extra ),
			),
			array( 'id' => (int) $id ),
			array( '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);
		SSC_Schema::audit( $id, 'anonymized', $case['status'], $case['status'], $actor, __( 'Reporter PII removed (privacy request)', 'nexachat-ai' ) );
	}

	/**
	 * Permanently delete a single case. The audit trail is KEPT (append-only)
	 * so compliance reviewers can still see that a purge occurred.
	 *
	 * @param int    $id    Case id.
	 * @param string $actor Actor label for site-level log.
	 */
	public function purge_case( $id, $actor = 'admin' ) {
		SSC_Schema::delete_submission( $id );
		/**
		 * Fired after a single ADR case is purged (compliance hooks).
		 *
		 * @param int    $id    Case id.
		 * @param string $actor Actor.
		 */
		do_action( 'ssc_adr_case_purged', $id, $actor );
	}

	/**
	 * Case statuses (workflow states; audit-tracked).
	 *
	 * @return string[] status => label.
	 */
	public static function case_statuses() {
		return array(
			'new'         => __( 'New (awaiting triage)', 'nexachat-ai' ),
			'in_progress' => __( 'Under assessment', 'nexachat-ai' ),
			'follow_up'   => __( 'Follow-up requested', 'nexachat-ai' ),
			'done'        => __( 'Closed', 'nexachat-ai' ),
			'archived'    => __( 'Archived', 'nexachat-ai' ),
		);
	}

	/**
	 * Render the ADR cases page.
	 */
	public function render_page() {
		if ( ! self::user_can() ) {
			wp_die( esc_html__( 'You do not have permission to access case records.', 'nexachat-ai' ) );
		}
		$setup = get_option( 'ssc_pharma_setup', array() );
		if ( empty( $setup['done'] ) && current_user_can( 'manage_options' ) ) {
			// Dedicated onboarding before any case tooling is exposed.
			require SSC_CHATBOT_DIR . 'includes/admin/views/pharma-setup.php';
			return;
		}

		$view = isset( $_GET['view'] ) ? (int) $_GET['view'] : 0;
		if ( $view > 0 ) {
			global $wpdb;
			$table = SSC_Schema::table_name();
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- single case read.
			$case = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d AND type = 'pharma_adr'", $view ), ARRAY_A );
			if ( $case ) {
				$audit = SSC_Schema::get_audit( $view );
				require SSC_CHATBOT_DIR . 'includes/admin/views/pharma-case.php';
				return;
			}
		}

		$filters = array(
			'type'   => 'pharma_adr',
			'status' => isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '',
			'search' => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '',
			'page'   => isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1,
		);
		$result  = SSC_Schema::get_submissions( $filters );
		require SSC_CHATBOT_DIR . 'includes/admin/views/page-pharma.php';
	}

	/**
	 * ADR cases CSV export.
	 */
	protected function export_csv() {
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=ssc-adr-cases-' . gmdate( 'Ymd-Hi' ) . '.csv' );
		$out = fopen( 'php://output', 'w' );
		fwrite( $out, "\xEF\xBB\xBF" );
		SSC_Input::write_csv( $out, array( 'case_id', 'created_at', 'reporter_type', 'reporter_name', 'reporter_phone', 'patient_age', 'patient_sex', 'product', 'batch_number', 'dose', 'route', 'reaction', 'severity', 'seriousness_criteria', 'outcome', 'concomitant_drugs', 'status', 'serious', 'additional_answers' ) );

		$page = 1;
		do {
			$result = SSC_Schema::get_submissions(
				array(
					'type'     => 'pharma_adr',
					'per_page' => 500,
					'page'     => $page,
				)
			);
			foreach ( $result['items'] as $row ) {
				$extra = json_decode( (string) $row['extra_fields'], true );
				$extra = is_array( $extra ) ? $extra : array();
				SSC_Input::write_csv(
					$out,
					// write_csv() already runs every cell through csv_cell().
					array(
						$row['id'],
						$row['created_at'],
						isset( $extra['reporter_type'] ) ? $extra['reporter_type'] : $row['reporter_type'],
						$row['name'],
						$row['phone'],
						isset( $extra['patient_age'] ) ? $extra['patient_age'] : '',
						isset( $extra['patient_sex'] ) ? $extra['patient_sex'] : '',
						$row['product'],
						$row['batch_number'],
						isset( $extra['dose'] ) ? $extra['dose'] : '',
						isset( $extra['route'] ) ? $extra['route'] : '',
						$row['description'],
						$row['severity'],
						implode( '|', self::seriousness_criteria( $row ) ),
						$row['outcome'],
						$row['concomitant_drugs'],
						$row['status'],
						self::is_serious_row( $row ) ? 'yes' : 'no',
						self::custom_answers_text( $extra ),
					)
				);
			}
			++$page;
		} while ( $page <= $result['total_pages'] );
		fclose( $out );
		exit;
	}
}
