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
* Fetch global variables declared for use anywhere in the plugin
*
* @since 2.0.0
*
* @return mixed Returns the global value depending on the key being provided
*/
function cfrm_get_global( $key ) {
	$cfrm                   = array();
	$cfrm['plugin_file']    = __FILE__;
	$cfrm['basename']       = plugin_basename( __FILE__ );
	$cfrm['directory_path'] = plugin_dir_path( __FILE__ );
	$cfrm['directory_url']  = plugin_dir_url( __FILE__ );
	return array_key_exists( $key, $cfrm ) ? $cfrm[$key] : '';
}

/**
 * Register js and css file.
 *
 * @since 2.0.0
 */
function cfrm_enqueue_js() {
	wp_register_script( 'cfrm-script', cfrm_get_global( 'directory_url' ) . 'cfrm-script.js', array( 'jquery' ) );
	wp_register_style( 'cfrm-style', cfrm_get_global( 'directory_url' ) . 'cfrm-style.css' );
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
	//include_once cfase_get_global( 'directory_path' ) . 'includes/admin-form.php';
	wp_enqueue_style( 'cfrm-style' );
	wp_enqueue_script( 'cfrm-script' );
	global $CFR_KEYS_REQUIRED;
	$required_keys = $CFR_KEYS_REQUIRED;
	$keys = array_diff( cfrm_meta_keys(), excluded_keys(), $required_keys );
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
				$checked = ( in_array( $key, selected_keys() ) ? $key : '' );
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
				<input type="submit" name="submit_button" class="button-primary" value="' . __( 'Save' ) . '" />
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
function cfrm_meta_keys() {
	global $wpdb;
	return $wpdb->get_col("
		SELECT DISTINCT `meta_key`
		FROM $wpdb->postmeta
		ORDER BY `meta_key`
	");
}

/*function required_keys() {
	global $CFR_KEYS_REQUIRED;
	return $CFR_KEYS_REQUIRED;
}*/

/**
 * Add filter to exclude meta keys.
 *
 * @since  2.0.0
 *
 * @return array returns meta key.
 */
function excluded_keys() {
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
 * @return void
 */
function selected_keys() {
	$selected = get_option('cf_revision_manager_meta_keys');
	if (empty($selected)) {
		$selected = array();
	}
	return $selected;
}

function cfrm_register_meta() {
	if ( function_exists( 'cfr_register_metadata' ) ) {
		cfr_register_metadata( 'foo' );
		global $CFR_KEYS_REQUIRED;
		$CFR_KEYS_REQUIRED = registered_keys();
		$keys = selected_keys();
		if ( count( $keys ) ) {
			foreach ( $keys as $key ) {
				cfr_register_metadata( $key );
			}
		}
	}
}
add_action( 'init', 'cfrm_register_meta', 999 );

function cfr_register_metadata( $postmeta_key, $display_func = '' ) {
	return register( $postmeta_key, $display_func );
}

function register( $postmeta_key, $display_func = '' ) {
	$postmeta_keys = array();
	global $CFRM_POSTMETA_KEYS;
	if ( ! in_array( $postmeta_key, $postmeta_keys, true ) ) {
		$postmeta_keys[] = compact( 'postmeta_key', 'display_func' );
	}
	$CFRM_POSTMETA_KEYS = $postmeta_keys;
	return true;
}

function registered_keys() {
	$keys = array();
	global $CFRM_POSTMETA_KEYS;

	if ( count( $CFRM_POSTMETA_KEYS ) ) {
		foreach ( $CFRM_POSTMETA_KEYS as $key ) {
			extract( $key );
			$keys[] = $postmeta_key;
		}
	}
	return array_unique($keys);
}

function request_handler() {
	if ( isset( $_POST['cf_action'] ) ) {
		switch ( $_POST['cf_action'] ) {
			case 'cfr_save_keys':
				if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'cfr_save_keys' ) ) {
					wp_die( 'Oops, please try again.' );
				}
				$keys = ( isset( $_POST['revision_manager_keys'] ) && is_array( $_POST['revision_manager_keys'] ) ) ? $_POST['revision_manager_keys'] : array();
				save_settings( $keys );
				wp_redirect( admin_url( 'options-general.php?page='.basename( __FILE__ ) ).'&cf_admin_notice=cfr-1' );
				break;
		}
	}
}
add_action( 'admin_init', 'request_handler' );

function save_settings( $keys ) {
	update_option( 'cf_revision_manager_meta_keys', ( array ) $keys );
}

/**
 * Display admin notices.
 *
 * @since  2.0.0
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
 * Save the revision data
 *
 * @param int $post_id
 * @param object $post
 * @return void
 */
function cfrm_save_post_revision( $post_id, $post ) {
	global $CFRM_POSTMETA_KEYS;
	if ( $post->post_type != 'revision' || ! have_keys() ) {
		return false;
	}

	foreach ( $CFRM_POSTMETA_KEYS as $postmeta_type ) {
		$postmeta_key = $postmeta_type['postmeta_key'];

		if ( $postmeta_value = get_post_meta( $post->post_parent, $postmeta_key, true ) ) {
			add_metadata( 'post', $post_id, $postmeta_key, $postmeta_value );
			log_msg( 'Added postmeta for: '.$postmeta_key.' to revision: '.$post_id.' from post: '.$post->post_parent );
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
function have_keys() {
	global $CFRM_POSTMETA_KEYS;
	return (bool) count( $CFRM_POSTMETA_KEYS );
}

/**
 * Revert the revision data
 *
 * @param int $post_id
 * @param int $revision_id
 * @return void
 */
function restore_post_revision( $post_id, $revision_id ) {
	global $CFRM_POSTMETA_KEYS;
	if ( ! have_keys() ) {
		return false;
	}

	foreach ( $CFRM_POSTMETA_KEYS as $postmeta_type ) {
		$postmeta_key = $postmeta_type['postmeta_key'];

		if ( $postmeta_value = get_metadata( 'post', $revision_id, $postmeta_key, true ) ) {
			if ( get_metadata( 'post', $post_id, $postmeta_key, true ) ) {
				log_msg( 'Updating postmeta: '.$postmeta_key.' for post: '.$post_id.' from revision: '.$revision_id );
				update_metadata( 'post', $post_id, $postmeta_key, $postmeta_value );
			}
			else {
				log_msg( 'Adding postmeta: '.$postmeta_key.' for post: '.$post_id );
				add_metadata( 'post', $post_id, $postmeta_key, $postmeta_value, true );
			}
			log_msg( 'Restored post_id: '.$post_id.' metadata from: '.$postmeta_key );
		}
	}
}
add_action( 'wp_restore_post_revision', 'restore_post_revision', 10, 2 );

function log_msg( $message ) {
	//if (CF_REVISIONS_DEBUG) {
		error_log( $message );
	//}
}

function cfrm_add_filter() {
	global $pagenow;
	if ( $pagenow == 'revision.php' ) {
		add_filter( '_wp_post_revision_fields', 'post_revision_fields', 10, 1 );
		add_filter( '_wp_post_revision_field_postmeta', 'post_revision_field', 1, 2);
	}
}
add_action( 'admin_init', 'cfrm_add_filter' );

/*function post_revision_fields( $fields ) {
	$fields['postmeta'] = 'Post Meta';
	return $fields;
}*/

/*function post_revision_field( $field_id, $field ) {

	global $CFRM_POSTMETA_KEYS;
	if ( $field != 'postmeta' || ! have_keys() ) {
		return;
	}

	//remove_filter( '_wp_post_revision_field_postmeta', 'htmlspecialchars', 10, 2 );

	//$html = '<ul style="white-space: normal; margin-left: 1.5em; list-style: disc outside;background-color:red;">';
	foreach ( $CFRM_POSTMETA_KEYS as $postmeta_type ) {

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
			echo "test";
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
}*/

