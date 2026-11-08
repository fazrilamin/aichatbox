<?php
/**
 * Front end: loads the widget and hands it its settings through a data attribute on #aisa-root.
 * [aisa_search] adds an inline search bar that answers with AI.
 *
 * @package ai-site-assistant
 */

defined( 'ABSPATH' ) || exit;

/**
 * Accept a CSS colour, gradient or var() reference; reject anything that could break out of the rule.
 *
 * @param string $value Raw value.
 * @return string
 */
function aisa_css_value( $value ) {
	$value = trim( (string) $value );
	return preg_match( '/^[#\w\s(),.%-]+$/', $value ) ? $value : '';
}

/**
 * Register the widget assets and enqueue them unless the widget is off.
 */
function aisa_enqueue_widget() {
	wp_register_style( 'aisa-widget', AISA_URL . 'assets/css/widget.css', array(), AISA_VERSION );
	wp_register_script(
		'aisa-widget',
		AISA_URL . 'assets/js/widget.js',
		array(),
		AISA_VERSION,
		array(
			'strategy'  => 'defer',
			'in_footer' => true,
		)
	);
	$accent = aisa_css_value( aisa_setting( 'accent' ) );
	if ( '' !== $accent ) {
		wp_add_inline_style( 'aisa-widget', '#aisa-widget,.aisa-launcher,.aisa-inline{--aisa-accent:' . $accent . '}' );
	}
	if ( 'none' !== aisa_setting( 'widget_mode' ) ) {
		wp_enqueue_style( 'aisa-widget' );
		wp_enqueue_script( 'aisa-widget' );
	}
}
add_action( 'wp_enqueue_scripts', 'aisa_enqueue_widget' );

/**
 * Settings and texts for the widget script.
 *
 * @return array
 */
function aisa_widget_config() {
	$s        = aisa_setting();
	$starters = array_slice( array_values( array_filter( array_map( 'trim', explode( "\n", (string) $s['starters'] ) ) ) ), 0, 6 );
	$cta      = trim( (string) $s['cta_url'] );
	if ( '' !== $cta && '/' === $cta[0] ) {
		$cta = home_url( $cta );
	}
	return array(
		'rest'     => esc_url_raw( rest_url( 'aisa/v1/' ) ),
		'mode'     => $s['widget_mode'],
		'selector' => $s['attach_selector'],
		'position' => $s['position'],
		'margin'   => (int) $s['margin'],
		'scheme'   => $s['scheme'],
		'stream'   => (bool) $s['stream'],
		'search'   => home_url( '/' ),
		'starters' => $starters,
		'text'     => array(
			'button'      => $s['button_label'],
			'title'       => $s['title'],
			'placeholder' => $s['placeholder'],
			'welcome'     => $s['welcome'],
			'disclaimer'  => $s['disclaimer'],
			'fallback'    => $s['fallback'],
			'ctaLabel'    => $s['cta_label'],
			'ctaUrl'      => esc_url_raw( $cta ),
			'send'        => __( 'Send', 'ai-site-assistant' ),
			'close'       => __( 'Close', 'ai-site-assistant' ),
			'newChat'     => __( 'New chat', 'ai-site-assistant' ),
			'sources'     => __( 'Sources', 'ai-site-assistant' ),
			'faq'         => __( 'From our FAQ', 'ai-site-assistant' ),
			'thinking'    => __( 'Thinking…', 'ai-site-assistant' ),
			'error'       => __( 'Something went wrong. Please try again.', 'ai-site-assistant' ),
			'helpful'     => __( 'Helpful', 'ai-site-assistant' ),
			'notHelpful'  => __( 'Not helpful', 'ai-site-assistant' ),
			'thanks'      => __( 'Thanks for the feedback.', 'ai-site-assistant' ),
			'allResults'  => __( 'All search results for “%s”', 'ai-site-assistant' ),
			'you'         => __( 'You', 'ai-site-assistant' ),
		),
	);
}

/**
 * Print the widget root (config only; the script builds the UI).
 */
function aisa_print_root() {
	if ( ! wp_script_is( 'aisa-widget', 'enqueued' ) ) {
		return;
	}
	printf( '<div id="aisa-root" hidden data-config="%s"></div>' . "\n", esc_attr( wp_json_encode( aisa_widget_config() ) ) );
}
add_action( 'wp_footer', 'aisa_print_root', 5 );

/**
 * [aisa_search placeholder="…" button="…"]: an inline search bar that asks the AI.
 * It is a normal search form, so it still works (as site search) without JavaScript.
 *
 * @param array $atts Attributes.
 * @return string
 */
function aisa_search_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'placeholder' => aisa_setting( 'placeholder' ),
			'button'      => __( 'Ask', 'ai-site-assistant' ),
		),
		$atts,
		'aisa_search'
	);
	wp_enqueue_style( 'aisa-widget' );
	wp_enqueue_script( 'aisa-widget' );
	$id = wp_unique_id( 'aisa-q-' );
	return sprintf(
		'<form class="aisa-inline" role="search" method="get" action="%1$s"><label class="screen-reader-text" for="%2$s">%3$s</label><input type="search" id="%2$s" name="s" class="aisa-inline__input" placeholder="%4$s" autocomplete="off" maxlength="500"><button type="submit" class="aisa-inline__btn">%5$s</button></form>',
		esc_url( home_url( '/' ) ),
		esc_attr( $id ),
		esc_html( aisa_setting( 'title' ) ),
		esc_attr( $atts['placeholder'] ),
		esc_html( $atts['button'] )
	);
}
add_shortcode( 'aisa_search', 'aisa_search_shortcode' );
