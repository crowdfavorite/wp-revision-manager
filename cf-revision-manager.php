<?php
/*
Plugin Name: CF Revision Manager
Plugin URI: http://crowdfavorite.com
Description: Revision management functionality so that plugins can add metadata to revisions as well as restore that metadata from revisions.
Version: 2.0.0
Author: Crowd Favorite
Author URI: https://crowdfavorite.com/
License: GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Domain Path: /languages
Text Domain: cfrm
 */

// Exit if already accessed
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register js and css file.
 *
 * @since 2.0.0
 */
function cfrm_enqueue_js() {
	wp_register_script( 'cfrm-script', plugin_dir_url( __FILE__ ) . 'cfrm-script.js', array( 'jquery' ) );
	wp_register_style( 'cfrm-style', plugin_dir_url( __FILE__ ) . 'cfrm-style.css' );
}
add_action( 'admin_enqueue_scripts', 'cfrm_enqueue_js' );

/**
 * Register revision manger admin menu.
 *
 * @since  2.0.0
 *
 * @return void
 */
function cfrm_admin_menu() {
	 add_options_page(
        __( 'Revision Manager', 'cfrm' ),
        __( 'Revision Manager', 'cfrm' ),
        'manage_options',
        basename( __FILE__ ),
        'cfrm_admin_form'
    );
}
add_action( 'admin_menu', 'cfrm_admin_menu' );

/**
 * Revision Manger admin form callback function.
 *
 * @since  2.0.0
 *
 * @return void
 */
function cfrm_admin_form() {
	//include_once plugin_dir_path( __FILE__ ) . 'includes/admin-form.php';
	wp_enqueue_style( 'cfrm-style' );
	wp_enqueue_script( 'cfrm-script' );
	$required_keys = cfrm_set_required_keys();
	$keys = array_diff( cfrm_get_distinct_meta_keys(), cfrm_excluded_keys(), $required_keys );
?>
<div class="wrap">
	<h2><?php _e( 'CF Revision Manager', 'cfrm' ); ?></h2>
<?php
		if ( ! count( $keys ) ) {
			echo '<p>' . __( 'No custom fields found.', 'cfrm' ) . '</p>';
		} else {
			echo '<form id="cfr_revision_manager_form" name="cfr_revision_manager_form" action="' . admin_url( 'options-general.php' ) . '" method="post">
				<p>' . __( 'A plugin or theme has specified that the following custom fields need to included in revisions.', 'cfrm' ) . '</p>';
			if ( count( $required_keys ) ) {
				echo '<div>
				<ul id="cfr_revision_manager_keys_required">';
				foreach ( $required_keys as $key ) {
					$checked = $key;
					$disabled = $key;
					$id = 'cf_revision_manager_key_' . esc_attr( $key );
					echo '<li>
						<input type="checkbox" name="revision_manager_keys[]" id="' . $id . '" value="' . esc_attr( $key ) . '" ' . checked( $key, $checked, false ) . ' ' . disabled( $key, $disabled, false ) . ' />
						<label for="' . $id . '">' . esc_html( $key ) . '</label>
					</li>';
				}
				echo '</ul>
				</div>';
			}
			echo '<p class="clearfix">' . __( 'Below is a list of selectable custom fields for this site. Choose the ones you would like to have included in your revisions.', 'cfrm' ).'</p>
				<div>
				<ul id="cfr_revision_manager_keys">';
			foreach ( $keys as $key ) {
				$checked = ( in_array( $key, cfrm_get_selected_keys() ) ? $key : '' );
				$disabled = '';
				$id = 'cf_revision_manager_key_' . esc_attr( $key );
				echo '<li>
					<input type="checkbox" name="revision_manager_keys[]" id="' . $id . '" value="' . esc_attr( $key ) . '" ' . checked( $key, $checked, false ) . ' ' . disabled( $key, $disabled, false ) . ' />
					<label for="' . $id . '">' . esc_html( $key ) . '</label>
				</li>';
			}
			echo '</ul>
				</div>
				<p class="submit">
				<input type="submit" name="submit_button" class="button-primary" value="' . __( 'Save', 'cfrm' ) . '" />
				</p>
				<input type="hidden" name="cf_action" value="cfr_save_keys" class="hidden" style="display: none;" />
				' . wp_nonce_field( 'cfr_save_keys', '_wpnonce', true, false ) . wp_referer_field( false ) . '
			</form>';
		}
?>
</div>
<?php
}

/**
 * Fetch all meta key from postmeta.
 *
 * @since  2.0.0
 *
 * @return array returns meta key.
 */
function cfrm_get_distinct_meta_keys() {
	global $wpdb;
	return $wpdb->get_col("
		SELECT DISTINCT `meta_key`
		FROM $wpdb->postmeta
		ORDER BY `meta_key`
	");
}

/**
 * Assign registered key in global variable.
 *
 * @since  2.0.0
 *
 * @return array returns registered key.
 */
function cfrm_set_required_keys() {
	global $CFR_KEYS_REQUIRED;
	return $CFR_KEYS_REQUIRED;
}

/**
 * Add filter to exclude meta keys.
 *
 * @since  2.0.0
 *
 * @return array returns meta key.
 */
function cfrm_excluded_keys() {
	return apply_filters(
		'cf_revision_manager_excluded_keys',
		array(
			'_edit_last',
			'_edit_lock',
		)
	);
}

/**
 * Return selected meta keys.
 *
 * @since  2.0.0
 *
 * @return array returns selected meta key.
 */
function cfrm_get_selected_keys() {
	$selected = get_option( 'cf_revision_manager_meta_keys' );
	if( empty( $selected ) ) {
		$selected = array();
	}
	return $selected;
}

/**
 * Register custom meta.
 *
 * @since  2.0.0
 *
 * @return void
 */
function cfrm_register_meta() {
	if ( function_exists( 'cfr_register_metadata' ) ) {
		//cfr_register_metadata( 'foo', 'abcd' );
		global $CFR_KEYS_REQUIRED;
		$CFR_KEYS_REQUIRED = cfr_get_registered_keys();
		$keys = cfrm_get_selected_keys();
		if ( count( $keys ) ) {
			foreach ( $keys as $key ) {
				cfr_register_metadata( $key );
			}
		}
	}
}
add_action( 'init', 'cfrm_register_meta', 999 );

/**
 * Register custom post meta.
 *
 * @since  2.0.0
 *
 * @return array returns registered meta key.
 */
function cfr_register_metadata( $postmeta_key, $display_func = '' ) {
	return cfr_process_metadata( $postmeta_key, $display_func );
}

/**
 * Process custom post meta.
 *
 * @since  2.0.0
 *
 * @return bool
 */
function cfr_process_metadata( $postmeta_key, $display_func = '' ) {
	$postmeta_keys = array();
	if ( ! in_array( $postmeta_key, $postmeta_keys, true ) ) {
		$postmeta_keys[] = compact( 'postmeta_key', 'display_func' );
		update_option( 'cfrm_set_postmeta_keys', array_unique( $postmeta_keys ) );
	}
	return true;
}

/**
 * Get registered post meta keys.
 *
 * @since  2.0.0
 *
 * @return array returns unique postmeta key.
 */
function cfr_get_registered_keys() {
	$keys = array();
	$registered_keys = cfr_get_postmeta_keys();

	if ( count( $registered_keys ) ) {
		foreach ( $registered_keys as $key ) {
			extract( $key );
			$keys[] = $postmeta_key;
		}
	}
	return array_unique($keys);
}

/**
 * Get post meta keys.
 *
 * @since  2.0.0
 *
 * @return array returns postmeta keys.
 */
function cfr_get_postmeta_keys() {
	return get_option( 'cfrm_set_postmeta_keys', true );
}

/**
 * Get registered post meta keys.
 *
 * @since  2.0.0
 *
 * @return array returns unique postmeta key.
 */
function cfr_request_handler() {
	if ( isset( $_POST['cf_action'] ) ) {
		switch ( $_POST['cf_action'] ) {
			case 'cfr_save_keys':
				if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'cfr_save_keys' ) ) {
					wp_die( 'Oops, please try again.' );
				}
				$keys = ( isset( $_POST['revision_manager_keys'] ) && is_array( $_POST['revision_manager_keys'] ) ) ? $_POST['revision_manager_keys'] : array();
				cfr_save_meta_keys_settings( $keys );
				wp_redirect( admin_url( 'options-general.php?page='.basename( __FILE__ ) ).'&cf_admin_notice=cfr-1' );
				break;
		}
	}
}
add_action( 'admin_init', 'cfr_request_handler' );

/**
 * Save meta keys settings.
 *
 * @since  2.0.0
 *
 * @return void
 */
function cfr_save_meta_keys_settings( $keys ) {
	update_option( 'cf_revision_manager_meta_keys', ( array ) $keys );
}

/**
 * Display admin notices.
 *
 * @since  2.0.0
 *
 * @return void
 */
function cfrm_admin_notices() {
	$notice = '';
	$class = 'updated';
	if ( isset( $_GET['cf_admin_notice'] ) ) {
		switch ( $_GET['cf_admin_notice'] ) {
			case 'cfr-1':
				$notice = 'Selected meta keys to be versioned have been updated.';
				break;
		}
	}
	if ( ! empty( $notice ) ) {
		echo '<div id="message" class="'.$class.'"><p>'.$notice.'</p></div>
';
	}
}
add_action( 'admin_notices', 'cfrm_admin_notices' );

/**
 * Save the revision data.
 *
 * @param int $post_id
 * @param object $post
 *
 * @return void
 */
function cfrm_save_post_revision( $post_id, $post ) {
	$cfrm_postmeta_keys = cfr_get_postmeta_keys();
	if ( $post->post_type != 'revision' || ! cfrm_have_keys() ) {
		return false;
	}

	foreach ( $cfrm_postmeta_keys as $postmeta_type ) {
		$postmeta_key = $postmeta_type['postmeta_key'];

		if ( $postmeta_value = get_post_meta( $post->post_parent, $postmeta_key, true ) ) {
			add_metadata( 'post', $post_id, $postmeta_key, $postmeta_value );
			cfrm_log_msg( 'Added postmeta for: '.$postmeta_key.' to revision: '.$post_id.' from post: '.$post->post_parent );
		}
	}
}
add_action( 'save_post', 'cfrm_save_post_revision', 10, 2 );

/**
 * This is a paranoid check. There will be no object to register the
 * actions and filters if nobody adds any postmeta to be handled
 *
 * @return bool
 */
function cfrm_have_keys() {
	$cfrm_postmeta_keys = cfr_get_postmeta_keys();
	return (bool) count( $cfrm_postmeta_keys );
}

/**
 * Revert the revision data
 *
 * @param int $post_id
 * @param int $revision_id
 * @return void
 */
function cfrm_restore_post_revision( $post_id, $revision_id ) {
	$cfrm_postmeta_keys = cfr_get_postmeta_keys();
	if ( ! cfrm_have_keys() ) {
		return false;
	}

	foreach ( $cfrm_postmeta_keys as $postmeta_type ) {
		$postmeta_key = $postmeta_type['postmeta_key'];

		if ( $postmeta_value = get_metadata( 'post', $revision_id, $postmeta_key, true ) ) {
			if ( get_metadata( 'post', $post_id, $postmeta_key, true ) ) {
				cfrm_log_msg( 'Updating postmeta: '.$postmeta_key.' for post: '.$post_id.' from revision: '.$revision_id );
				update_metadata( 'post', $post_id, $postmeta_key, $postmeta_value );
			}
			else {
				cfrm_log_msg( 'Adding postmeta: '.$postmeta_key.' for post: '.$post_id );
				add_metadata( 'post', $post_id, $postmeta_key, $postmeta_value, true );
			}
			cfrm_log_msg( 'Restored post_id: '.$post_id.' metadata from: '.$postmeta_key );
		}
	}
}
add_action( 'wp_restore_post_revision', 'cfrm_restore_post_revision', 10, 2 );

/**
 * Display log msg.
 */
function cfrm_log_msg( $message ) {
	error_log( $message );
}

/**
 * Add filter
 */
function cfrm_add_filter() {
	global $pagenow;
	if ( $pagenow == 'revision.php' ) {
		add_filter( '_wp_post_revision_fields', 'post_revision_fields', 10, 1 );
		add_filter( '_wp_post_revision_field_postmeta', 'post_revision_field', 1, 2);
	}
}
add_action( 'admin_init', 'cfrm_add_filter' );

/**
 * Add post revision fields filter
 *
 * @param  string $fields
 *
 * @return array returns fields
 */
function post_revision_fields( $fields ) {
	$fields['postmeta'] = 'Post Meta';
	return $fields;
}
/**
 * Add post revision field filter
 *
 * @param  int   $field_id
 * @param  string $field
 *
 * @return string returns string
 */
function post_revision_field( $field_id, $field ) {
	$cfrm_postmeta_keys = cfr_get_postmeta_keys();
	if ( $field != 'postmeta' || ! cfrm_have_keys() ) {
		return;
	}

	remove_filter( '_wp_post_revision_field_postmeta', 'htmlspecialchars', 10, 2 );

	$html = '<ul style="white-space: normal; margin-left: 1.5em; list-style: disc outside;background-color:red;">';
	foreach ( $cfrm_postmeta_keys as $postmeta_type ) {
		$postmeta_key = $postmeta_type['postmeta_key'];
		$postmeta = maybe_unserialize( get_metadata( 'post', intval( $_GET['revision'] ), $postmeta_key, true ) );
		if ( ! empty( $postmeta ) ) {

			if ( ! empty( $postmeta_type['display_func']) && function_exists( $postmeta_type['display_func'] ) ) {
				$postmeta_html = $postmeta_type['display_func']( $postmeta );
			}
			else {
				$postmeta_rendered = ( is_array( $postmeta ) || is_object( $postmeta ) ? print_r( $postmeta, true ) : $postmeta );
				$postmeta_html = apply_filters( '_wp_post_revision_field_postmeta_display', htmlspecialchars( $postmeta_rendered ), $postmeta_key, $postmeta );
			}
		}
		else {
			$postmeta_html = '*empty postmeta value*';
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
