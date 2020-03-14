<?php

namespace WordCamp\SpeakerFeedback\Admin;

use WordCamp\SpeakerFeedback\Feedback_List_Table;
use const WordCamp\SpeakerFeedback\SUPPORTED_POST_TYPES;
use const WordCamp\SpeakerFeedback\Comment\COMMENT_TYPE;
use function WordCamp\SpeakerFeedback\{ get_assets_path, get_includes_path, get_views_path, get_assets_url };
use function WordCamp\SpeakerFeedback\Comment\get_feedback;

defined( 'WPINC' ) || die();

foreach ( SUPPORTED_POST_TYPES as $supported_post_type ) {
	add_filter( "manage_{$supported_post_type}_posts_columns", __NAMESPACE__ . '\add_post_listtable_columns' );
	add_action( "manage_{$supported_post_type}_posts_custom_column", __NAMESPACE__ . '\render_post_listtable_columns', 10, 2 );
}

add_action( 'admin_menu', __NAMESPACE__ . '\add_subpages' );

/**
 * Add a Speaker Feedback column for post list tables that support speaker feedback.
 *
 * @param array $columns
 *
 * @return array
 */
function add_post_listtable_columns( $columns ) {
	$columns = array_slice( $columns, 0, -1, true )
		+ array( 'count_' . COMMENT_TYPE => __( 'Speaker Feedback', 'wordcamporg' ) )
		+ array_slice( $columns, -1, 1, true );

	return $columns;
}

/**
 * Render the cell contents for the Speaker Feedback column on list tables.
 *
 * @param string $column_name
 * @param int    $post_id
 *
 * @return void
 */
function render_post_listtable_columns( $column_name, $post_id ) {
	global $wp_list_table;

	switch ( $column_name ) {
		case 'count_' . COMMENT_TYPE:
			// The `column-comments` class is added here since it can't be injected into the usual `td` element.
			// This gives us the same comment bubble styles for free.
			?>
			<div class="column-comments post-com-count-wrapper">
			<?php feedback_bubble( $post_id ); ?>
			</div>
			<?php
			break;
	}
}

/**
 * Output a graphical representation of the approved/pending feedback comments for a particular post.
 *
 * This is based on the `comment_bubble` method in `WP_List_Table`.
 *
 * @param int $post_id
 *
 * @return void
 */
function feedback_bubble( $post_id ) {
	$feedback = get_feedback( array( $post_id ), array( 'approve', 'hold' ) );

	$counted_feedback = array_reduce(
		$feedback,
		function( $carry, $item ) {
			if ( in_array( $item->comment_approved, array( 1, '1', 'approve' ), true ) ) {
				$carry['approve'] ++;
			} else {
				$carry['hold'] ++;
			}

			return $carry;
		},
		array(
			'approve' => 0,
			'hold'    => 0,
		)
	);

	$counted_feedback_label = array_map( 'number_format_i18n', $counted_feedback );

	$approved_only_phrase = sprintf(
		/* translators: %s: Number of comments. */
		_n( '%s comment', '%s comments', $counted_feedback['approve'], 'wordcamporg' ),
		$counted_feedback_label['approve']
	);

	$approved_phrase = sprintf(
		/* translators: %s: Number of comments. */
		_n( '%s approved comment', '%s approved comments', $counted_feedback['approve'], 'wordcamporg' ),
		$counted_feedback_label['approve']
	);

	$pending_phrase = sprintf(
		/* translators: %s: Number of comments. */
		_n( '%s pending comment', '%s pending comments', $counted_feedback['hold'], 'wordcamporg' ),
		$counted_feedback_label['hold']
	);

	// No comments at all.
	if ( ! $counted_feedback['approve'] && ! $counted_feedback['hold'] ) {
		printf(
			'<span aria-hidden="true">&#8212;</span><span class="screen-reader-text">%s</span>',
			esc_html__( 'No comments', 'wordcamporg' )
		);
		// Approved comments have different display depending on some conditions.
	} elseif ( $counted_feedback['approve'] ) {
		printf(
			'<a href="%s" class="post-com-count post-com-count-approved"><span class="comment-count-approved" aria-hidden="true">%s</span><span class="screen-reader-text">%s</span></a>',
			esc_url(
				add_query_arg(
					array(
						'p'              => $post_id,
						'comment_status' => 'approved',
					),
					get_subpage_url( get_post_type( $post_id ) )
				)
			),
			esc_html( $counted_feedback_label['approve'] ),
			$counted_feedback['hold'] ? esc_html( $approved_phrase ) : esc_html( $approved_only_phrase )
		);
	} else {
		printf(
			'<span class="post-com-count post-com-count-no-comments"><span class="comment-count comment-count-no-comments" aria-hidden="true">%s</span><span class="screen-reader-text">%s</span></span>',
			esc_html( $counted_feedback_label['approve'] ),
			$counted_feedback['hold'] ? esc_html__( 'No approved comments', 'wordcamporg' ) : esc_html__( 'No comments', 'wordcamporg' )
		);
	}

	if ( $counted_feedback['hold'] ) {
		printf(
			'<a href="%s" class="post-com-count post-com-count-pending"><span class="comment-count-pending" aria-hidden="true">%s</span><span class="screen-reader-text">%s</span></a>',
			esc_url(
				add_query_arg(
					array(
						'p'              => $post_id,
						'comment_status' => 'moderated',
					),
					get_subpage_url( get_post_type( $post_id ) )
				)
			),
			esc_html( $counted_feedback_label['hold'] ),
			esc_html( $pending_phrase )
		);
	} else {
		printf(
			'<span class="post-com-count post-com-count-pending post-com-count-no-pending"><span class="comment-count comment-count-no-pending" aria-hidden="true">%s</span><span class="screen-reader-text">%s</span></span>',
			esc_html( $counted_feedback_label['hold'] ),
			$counted_feedback['approve'] ? esc_html__( 'No pending comments', 'wordcamporg' ) : esc_html__( 'No comments', 'wordcamporg' )
		);
	}
}

/**
 * Register an admin page for each post type that supports speaker feedback.
 *
 * @return void
 */
function add_subpages() {
	foreach ( SUPPORTED_POST_TYPES as $supported_post_type ) {
		$parent_slug = add_query_arg( 'post_type', $supported_post_type, 'edit.php' );

		add_submenu_page(
			$parent_slug,
			__( 'Speaker Feedback', 'wordcamporg' ),
			__( 'Feedback', 'wordcamporg' ),
			'moderate_' . COMMENT_TYPE,
			COMMENT_TYPE,
			__NAMESPACE__ . '\render_subpage'
		);

		$page_hook = get_plugin_page_hook( COMMENT_TYPE, $parent_slug );

		add_action(
			"load-$page_hook",
			function() {
				// This is a hack to ensure that the list table columns are registered properly. It has to happen
				// before the subpage's render function is called.
				get_feedback_list_table();
			}
		);
	}
}

/**
 * Generate a full URL for a feedback list table page.
 *
 * @param string $post_type
 *
 * @return string
 */
function get_subpage_url( $post_type ) {
	if ( ! in_array( $post_type, SUPPORTED_POST_TYPES, true ) ) {
		return '';
	}

	return add_query_arg(
		array(
			'post_type' => $post_type,
			'page'      => COMMENT_TYPE,
		),
		esc_url( admin_url( 'edit.php' ) )
	);
}

/**
 * Render the admin page for displaying a feedback comments list table.
 *
 * @return void
 */
function render_subpage() {
	$post_id        = filter_input( INPUT_GET, 'p', FILTER_VALIDATE_INT );
	$search         = wp_unslash( filter_input( INPUT_GET, 's' ) );
	$paged          = filter_input( INPUT_GET, 'paged', FILTER_VALIDATE_INT );
	$comment_status = wp_unslash( filter_input( INPUT_GET, 'comment_status' ) );
	$list_table     = get_feedback_list_table();

	wp_enqueue_style(
		'speaker-feedback',
		get_assets_url() . 'build/style.css',
		array(),
		filemtime( get_assets_path() . 'build/style.css' )
	);
	wp_enqueue_script( 'admin-comments' );
	enqueue_comment_hotkeys_js();

	toggle_list_table_filters();
	require_once get_views_path() . 'edit-feedback.php';
	toggle_list_table_filters();
}

/**
 * Load necessary files and instantiate the list table class.
 *
 * @return Feedback_List_Table
 */
function get_feedback_list_table() {
	require_once ABSPATH . 'wp-admin/includes/class-wp-comments-list-table.php';
	require_once get_includes_path() . 'class-feedback-list-table.php';

	return new Feedback_List_Table();
}

/**
 * Add or remove a bunch of filters to customize our list table.
 *
 * @return string The current state of the filters. 'on' or 'off'.
 */
function toggle_list_table_filters() {
	static $current_state = 'off';

	$screen_id = get_current_screen()->id;

	switch ( $current_state ) {
		case 'off':
			add_filter( 'comments_list_table_query_args', __NAMESPACE__ . '\filter_list_table_query_args' );
			add_filter( "views_{$screen_id}", __NAMESPACE__ . '\filter_list_table_views' );
			add_filter( 'comment_row_actions', __NAMESPACE__ . '\filter_list_table_row_actions' );

			$current_state = 'on';
			break;

		case 'on':
			remove_filter( 'comments_list_table_query_args', __NAMESPACE__ . '\filter_list_table_query_args' );
			remove_filter( "views_{$screen_id}", __NAMESPACE__ . '\filter_list_table_views' );
			remove_filter( 'comment_row_actions', __NAMESPACE__ . '\filter_list_table_row_actions' );

			$current_state = 'off';
			break;
	}

	return $current_state;
}

/**
 * Tweak the args for the comment query in the list table.
 *
 * - Ensure the list table query for feedback comments always has the correct comment type specified.
 * - Enable ordering by the `rating` meta value.
 *
 * @param array $args
 *
 * @return array
 */
function filter_list_table_query_args( $args ) {
	$args['type'] = COMMENT_TYPE;

	if ( 'rating' === $args['orderby'] ) {
		$args['orderby']  = 'meta_value_num';
		$args['meta_key'] = 'rating';
	}

	return $args;
}

/**
 * Modify the list of available views for the feedback comments list table.
 *
 * - Remove unnecessary views.
 * - Replace the default view URLs with ones that link back to our feedback list table page.
 *
 * @param array $views
 *
 * @return mixed
 */
function filter_list_table_views( $views ) {
	global $typenow;

	// Feedback from an admin of the event site would probably be rare, so this one is unnecessary.
	unset( $views['mine'] );

	foreach ( $views as $status => $view ) {
		// Note that the HTML here is wrapping href attributes with single quotes.
		preg_match( '#href=\'([^\']+)\'#', $view, $orig_url );
		$parsed_url = wp_parse_url( $orig_url[1] );
		wp_parse_str( $parsed_url['query'], $query_args );

		$new_url = add_query_arg( $query_args, get_subpage_url( $typenow ) );

		$views[ $status ] = str_replace(
			$orig_url[1],
			$new_url,
			$view
		);
	}

	return $views;
}

/**
 * Modify the list of available row actions for each feedback comment.
 *
 * - Remove irrelevant actions.
 *
 * @param array $actions
 *
 * @return mixed
 */
function filter_list_table_row_actions( $actions ) {
	unset( $actions['reply'], $actions['quickedit'], $actions['edit'] );

	return $actions;
}
