<?php
/*
Plugin Name: CF Revision Manager
Plugin URI: http://crowdfavorite.com
Description: Revision management functionality so that plugins can add metadata to revisions as well as restore that metadata from revisions
Version: 2.0.0
Author: Crowd Favorite
Author URI: http://crowdfavorite.com
*/

/**
 * Adding admin menu for Revision meta settings.
 *
 * @since 2.0.0
 */
function cfrm_wp_revisions_manager() {
	add_submenu_page(
		'options-general.php',
		'Revisions Manager Settings',
		'Revision Manager',
		'administrator',
		'wp_revisions_manager',
		'cfrm_revisions_manager_list'
		);

}
add_action('admin_menu', 'cfrm_wp_revisions_manager');

/**
 * Callback function for revisions settings page.
 *
 * @since 2.0.0
 */
function cfrm_revisions_manager_list() {
	//Handles Form Submission.
	cfrm_revision_process_submission();
	//Display list of all metas.
	cfrm_revision_display_metas();
}

/**
 * Processes form revision manager form submission.
 *
 * @since 2.0.0
 */
function cfrm_revision_process_submission() {
	if ( isset( $_POST['cfrm_exclude_submit'] ) ) {
		if ( isset( $_POST['cfrm_exclude_meta'] ) ) {
			$exclude_metas = $_POST['cfrm_exclude_meta'];
			update_option( 'cfrm_exclude_meta', $exclude_metas );
		}
	}
}

/**
 * Outputs html for the form meta list.
 *
 * @since 2.0.0
 */
function cfrm_revision_display_metas() {
	$meta_keys = cfrm_get_meta_keys();

	$html = cfrm_get_page_header();
	$html .= cfrm_get_page_meta_list( $meta_keys );
	$html .= cfrm_get_page_footer();

	echo $html;
}

/**
 * Returns a list of all metas associated with any post type.
 *
 * @since  2.0.0
 *
 * @return Array An array of all meta keys.
 */
function cfrm_get_meta_keys() {
	global $wpdb;
	return $wpdb->get_col( "SELECT DISTINCT(meta_key) FROM $wpdb->postmeta" );
}

/**
 * Get List of meta keys associated with a particular post.
 *
 * @since  2.0.0
 *
 * @param  $post_id Integer Post ID.
 * @return          Array   An array of all metakeys associated with the post.
 */
function cfrm_get_post_metas( $post_id ) {
	$metas = get_post_meta( $post_id );
	return array_keys( $metas );
}

/**
 * Outputs the header section of the settings page.
 *
 * @since  2.0.0
 *
 * @return String An html output of the header.
 */
function cfrm_get_page_header() {
	$html = '<div class="wrap">';
	$html .= '<h1>WP Revision Manager Settings</h1>';
	$html .= '<p>Please select those meta_keys for which you dont want revision to be stored.</p>';
	$html .= '<form action="" method="post">';
	$html .= '<ul style="display:inline-block;width:100%;">';
	return $html;
}

/**
 * Outputs the list of metas for the settings page.
 *
 * @since  2.0.0
 *
 * @param  $meta_meys Array  An array of all meta keys on site.
 * @return            String An html output of the header.
 */
function cfrm_get_page_meta_list( $meta_keys ) {
	//Fetches Excluded Revisions
	$cfrm_exclude_meta_arr = cfrm_get_excluded_revisions();

	$html = '';
	foreach ( $meta_keys as $meta_key ) {
		$checked = '';
		if ( in_array( $meta_key, $cfrm_exclude_meta_arr ) ) {
			$checked = ' checked="checked"';
		}
		$html .= '<li style="width:30%;float:left;">';
		$html .= sprintf(
			'<input type="checkbox" name="cfrm_exclude_meta[]" value="%1$s" id="%1$s"%2$s />
			<label for="%1$s">%1$s</label>',
			$meta_key,
			$checked
			);
		$html .= '</li>';
	}
	return $html;
}

/**
 * Outputs the footer section of the settings page.
 *
 * @since  2.0.0
 *
 * @return String An html output of the footer.
 */
function cfrm_get_page_footer() {
	$html = '</ul>';
	$html .= '<p class="submit">
			<input type="submit" name="cfrm_exclude_submit" id="submit" class="button button-primary" value="Save Changes">
			</p>';
	$html .= '</form>';
	$html .= '</div>';
	return $html;
}

/**
 * Get list of metas to be excluded from revisions.
 *
 * @since  2.0.0
 *
 * @return Array An array of meta keys to be excluded from revisions.
 */
function cfrm_get_excluded_revisions() {
	$excludes_saved = get_option( 'cfrm_exclude_meta' );
	if ( ! empty( $excludes_saved ) ) {
		return $excludes_saved;
	}
	return array();
}

/**
 * Store meta's for revision on post save.
 *
 * @since 2.0.0
 *
 * @param $post_id Integer Post ID.
 * @param $post    Object  Post Details.
 */
function cfrm_save_post_revision( $post_id, $post ) {
	if ( 'revision' != $post->post_type ) {
		return false;
	}

	$cfrm_post_meta_keys = cfrm_get_post_metas( $post->post_parent );

	foreach ( $cfrm_post_meta_keys as $postmeta_key ) {

		$postmeta_values = get_post_meta( $post->post_parent, $postmeta_key );
		$excluded_keys = cfrm_get_excluded_revisions();
		if ( ! empty( $postmeta_values ) && ! in_array( $postmeta_key, $excluded_keys ) ) {
			cfrm_update_post_meta( $post_id, $postmeta_key, $postmeta_values );
		}
	}
}
add_action( 'save_post', 'cfrm_save_post_revision', 10, 2 );

/**
 * Revert Meta values on revision restore.
 *
 * @since 2.0.0
 *
 * @param $post_id     Integer Post ID.
 * @param $revision_id Integer Post Revision ID.
 */
function cfrm_restore_post_revision( $post_id, $revision_id ) {
	$cfrm_post_meta_keys = cfrm_get_post_metas( $post_id );

	if ( ! $cfrm_post_meta_keys ) {
		return false;
	}

	foreach ( $cfrm_post_meta_keys as $postmeta_key ) {
		$postmeta_values = get_post_meta( $revision_id, $postmeta_key );
		delete_post_meta( $post_id, $postmeta_key );
		if ( ! empty( $postmeta_values ) ) {
			cfrm_update_post_meta( $post_id, $postmeta_key, $postmeta_values );
		}
	}
}
add_action( 'wp_restore_post_revision', 'cfrm_restore_post_revision', 10, 2 );

/**
 * Update Post Meta for all post meta values.
 *
 * @since 2.0.0
 *
 * @param $post_id         Integer Post ID.
 * @param $postmeta_key    String  Post Meta Key against which value is retieved and stored.
 * @param $postmeta_values Array   All Post Meta Values to iterate and store.
 */
function cfrm_update_post_meta( $post_id, $postmeta_key, $postmeta_values ) {
	foreach ( $postmeta_values as $postmeta_value ) {
		update_post_meta( $post_id, $postmeta_key, $postmeta_value );
	}
}

function cfrm_admin_revisions() {
	global $pagenow;
	if ( $pagenow == 'revision.php' ) {
		add_filter( '_wp_post_revision_fields', 'cfrm_post_revision_fields', 10, 1 );
		add_filter( '_wp_post_revision_field_postmeta', 'cfrm_post_revision_field', 1, 2 );
	}
}
add_action( 'admin_init', 'cfrm_admin_revisions' );

function cfrm_post_revision_fields( $fields ) {
	$fields['postmeta'] = __('Post Meta');
	return $fields;
}

function cfrm_post_revision_field( $field_id, $field ) {
	if ( 'postmeta' != $field ) {
		return;
	}

	remove_filter( '_wp_post_revision_field_postmeta', 'htmlspecialchars', 10, 2 );

	$html = '<ul style="white-space: normal; margin-left: 1.5em; list-style: disc outside;">';

	$revision_id = intval( $_GET['revision'] );

	$postmeta_keys = cfrm_get_post_metas( $revision_id );

	foreach ( $postmeta_keys as $postmeta_type ) {
		$postmeta_html = '';
		$postmeta_key = $postmeta_type['postmeta_key'];
		$postmeta_values = get_metadata('post', intval($_GET['revision']), $postmeta_key);
		if (is_array($postmeta_values)) {
			foreach ($postmeta_values as $postmeta_value) {
				$postmeta_html .= '<div>';
				$postmeta_value = maybe_unserialize($postmeta_value);
				if (!empty($postmeta_value)) {
					if (!empty($postmeta_type['display_func']) && function_exists($postmeta_type['display_func'])) {
						$postmeta_html .= $postmeta_type['display_func']($postmeta_value);
					}
					else {
						$postmeta_rendered = (is_array($postmeta_value) || is_object($postmeta_value) ? print_r($postmeta_value, true) : $postmeta_value);
						$postmeta_html .= apply_filters('_wp_post_revision_field_postmeta_display', htmlspecialchars($postmeta_rendered), $postmeta_key, $postmeta_value);
					}
				}
				$postmeta_html .= '</div>';
			}
		}
		else {
			$postmeta_html .= '*empty postmeta value*';
		}

		$html .= '
			<li>
				<h3><a href="#postmeta-'.$postmeta_key.'" onclick="jQuery(\'#postmeta-'.$postmeta_key.'\').slideToggle(); return false;">'.$postmeta_key.'</a></h3>
				<div id="postmeta-'.$postmeta_key.'" style="display: none;">'.$postmeta_html.'</div>
			</li>
			';
	}
	$html .= '</ul>';

	return $html;
}
