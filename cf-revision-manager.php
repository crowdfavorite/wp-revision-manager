<?php
/*
Plugin Name: CF Revision Manager
Plugin URI: http://crowdfavorite.com
Description: Revision management functionality so that plugins can add metadata to revisions as well as restore that metadata from revisions
Version: 2.0.0
Author: Crowd Favorite
Author URI: http://crowdfavorite.com
*/

define( 'CF_REVISIONS_DEBUG', true );

/**
 *
 *
 * @since 1.0.0
 */
function cfrm_register_metadata( $postmeta_key, $display_func = '' ) {
	return cfrm_register( $postmeta_key, $display_func );
}

/**
 *
 *
 * @since 2.0.0
 */
function cfrm_register( $postmeta_key, $display_func = '' ) {
	if ( ! in_array( $postmeta_key, $this->postmeta_keys, true ) ) {
		$postmeta_keys[] = compact( 'postmeta_key', 'display_func' );
	}
	return true;
}

/**
 *
 *
 * @since 2.0.0
 */
function cfrm_save_post_revision( $post_id, $post ) {
	if ( $post->post_type != 'revision' ) {
		return false;
	}

	$cfrm_post_meta_keys = cfrm_get_post_metas( $post->post_parent );

	foreach ( $cfrm_post_meta_keys as $postmeta_key ) {

		if ( $postmeta_values = get_post_meta( $post->post_parent, $postmeta_key ) && ! in_array( $postmeta_key, cfrm_get_excluded_revisions() ) ) {
			foreach ( $postmeta_values as $postmeta_value ) {
				add_metadata( 'post', $post_id, $postmeta_key, $postmeta_value );
			}
			cfrm_log('Added postmeta for: '.$postmeta_key.' to revision: '.$post_id.' from post: '.$post->post_parent);
		}
	}
}
add_action('save_post', 'cfrm_save_post_revision', 10, 2);

/**
 *
 *
 * @since 2.0.0
 */
function cfrm_restore_post_revision( $post_id, $revision_id ) {
	$cfrm_post_meta_keys = cfrm_get_post_metas( $post_id );

	if ( ! $cfrm_post_meta_keys ) {
		return false;
	}

	foreach ( $cfrm_post_meta_keys as $postmeta_key ) {

		delete_metadata( 'post', $post_id, $postmeta_key );
		// get_metadata does not unslash
		if ( $postmeta_values = get_metadata( 'post', $revision_id, $postmeta_key ) ) {
			foreach ( $postmeta_values as $postmeta_value ) {
				cfrm_log( 'Setting postmeta: '.$postmeta_key.' for post: '.$post_id );
				add_metadata( 'post', $post_id, $postmeta_key, $postmeta_value, true );
			}
			cfrm_log( 'Restored post_id: '.$post_id.' metadata from: '.$postmeta_key );
		}
	}
}
add_action( 'wp_restore_post_revision', 'cfrm_restore_post_revision', 10, 2 );

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

function cfrm_post_revision_field($field_id, $field) {
	if ( $field != 'postmeta' || ! $this->have_keys() ) {
		return;
	}

	remove_filter( '_wp_post_revision_field_postmeta', 'htmlspecialchars', 10, 2 );

	$html = '<ul style="white-space: normal; margin-left: 1.5em; list-style: disc outside;">';
	foreach ( $this->postmeta_keys as $postmeta_type ) {
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

function cfrm_wp_revisions_manager() {
	add_submenu_page( 'options-general.php', 'Revisions Manager Settings', 'Revision Manager', 'administrator', 'wp_revisions_manager', 'cfrm_revisions_manager_list' );

}
add_action('admin_menu', 'cfrm_wp_revisions_manager');

function cfrm_revisions_manager_list() {
	cfrm_revision_process_submission();
	$cfrm_exclude_meta_arr = cfrm_get_excluded_revisions();
	$meta_keys = cfrm_get_meta_keys();
	$html = '<div class="wrap">';
	$html .= '<h1>WP Revision Manager Settings</h1>';
	$html .= '<p>Please select those meta_keys for which you dont want revision to be stored.</p>';
	$html .= '<form action="" method="post">';
	$html .= '<ul style="display:inline-block;width:100%;">';
	foreach ( $meta_keys as $meta_key ) {
		$checked = '';
		if ( in_array( $meta_key, $cfrm_exclude_meta_arr ) ) {
			$checked = ' checked="checked"';
		}
		$html .= '<li style="width:30%;float:left;">';
		$html .= '<input type="checkbox" name="cfrm_exclude_meta[]" value="' . $meta_key . '" id="' . $meta_key. '"' . $checked . '><label for="' . $meta_key . '">' . $meta_key . '</label>';
		$html .= '</li>';
	}
	$html .= '</ul>';
	$html .= '<p class="submit"><input type="submit" name="cfrm_exclude_submit" id="submit" class="button button-primary" value="Save Changes"></p>';
	$html .= '</form>';
	$html .= '</div>';
	echo $html;
}

function cfrm_revision_process_submission() {
	if ( isset( $_POST['cfrm_exclude_submit'] ) ) {
		if ( isset( $_POST['cfrm_exclude_meta'] ) ) {
			$exclude_metas = $_POST['cfrm_exclude_meta'];
			update_option( 'cfrm_exclude_meta', $exclude_metas );
		}
	}
}

function cfrm_get_excluded_revisions() {
	return get_option( 'cfrm_exclude_meta' );
}

function cfrm_get_meta_keys() {
	$postmeta_keys = array();
	$posts = get_posts( array( 'post_type' => get_post_types() ) );
	foreach ( $posts as $post ) {
		$meta_keys = cfrm_get_post_metas( $post->ID );
		$postmeta_keys = array_unique( array_merge( $postmeta_keys,$meta_keys ) );
	}
	return array_values( $postmeta_keys );
}

function cfrm_get_post_metas( $post_id ) {
	$metas = get_post_meta( $post_id );
	return array_keys( $metas );
}

function cfrm_has_post_metas( $post_id ) {
	return (bool) count( cfrm_get_post_metas( $post_id ) );
}

function cfrm_log( $message ) {
	if ( CF_REVISIONS_DEBUG ) {
		error_log( $message );
	}
}
