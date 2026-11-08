<?php
/**
 * Snippets: private question → answer entries for things the website doesn't say
 * (policies, FAQs, internal facts). They are indexed like pages but have no public URL.
 *
 * @package ai-site-assistant
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the snippet post type under the AI Assistant menu.
 */
function aisa_register_snippets() {
	register_post_type(
		'aisa_snippet',
		array(
			'labels'              => array(
				'name'          => __( 'Snippets', 'ai-site-assistant' ),
				'singular_name' => __( 'Snippet', 'ai-site-assistant' ),
				'add_new_item'  => __( 'Add snippet', 'ai-site-assistant' ),
				'edit_item'     => __( 'Edit snippet', 'ai-site-assistant' ),
				'new_item'      => __( 'New snippet', 'ai-site-assistant' ),
				'search_items'  => __( 'Search snippets', 'ai-site-assistant' ),
				'not_found'     => __( 'No snippets yet. Add answers to questions your website doesn\'t cover.', 'ai-site-assistant' ),
				'all_items'     => __( 'Snippets', 'ai-site-assistant' ),
			),
			'public'              => false,
			'publicly_queryable'  => false,
			'exclude_from_search' => true,
			'show_ui'             => true,
			'show_in_menu'        => 'aisa',
			'show_in_rest'        => false,
			'rewrite'             => false,
			'supports'            => array( 'title', 'editor', 'revisions' ),
		)
	);
}
add_action( 'init', 'aisa_register_snippets' );

/**
 * Title placeholder: the title is the question.
 *
 * @param string  $text Placeholder.
 * @param WP_Post $post Post.
 * @return string
 */
function aisa_snippet_title_placeholder( $text, $post ) {
	return 'aisa_snippet' === $post->post_type ? __( 'Question, e.g. What is your refund policy?', 'ai-site-assistant' ) : $text;
}
add_filter( 'enter_title_here', 'aisa_snippet_title_placeholder', 10, 2 );
