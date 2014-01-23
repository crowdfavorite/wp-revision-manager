<?php
/*
Plugin Name: CF Revision Manager
Plugin URI: http://crowdfavorite.com
Description: Revision management functionality so that plugins can add metadata to revisions as well as restore that metadata from revisions
Version: 1.0.1
Author: Crowd Favorite
Author URI: http://crowdfavorite.com
*/
define(CF_REVISIONS_DEBUG, true);
if (!class_exists('cf_revisions')) {

	define('CF_REVISIONS_DEBUG', false);

	function cfr_register_metadata($postmeta_key, $display_func = '') {
		static $cfr;
		if (empty($cfr)) {
			$cfr = cf_revisions::get_instance();
		}
		return $cfr->register($postmeta_key, $display_func);
	}

	class cf_revisions {
		private static $_instance;
		protected $postmeta_keys = array();
		protected $prefix = 'cfrm';

		public function __construct() {
			# save & restore
			add_action('save_post', array($this, 'save_post_revision'), 10, 2);
			add_action('wp_restore_post_revision', array($this, 'restore_post_revision'), 10, 2);
			add_action('wp_save_post_revision_check_for_changes', array($this, 'has_changes'), 10, 2);

			if (is_admin()) {
				# revision display
				global $pagenow;
				if ($pagenow == 'revision.php') {
					add_filter('_wp_post_revision_fields', array($this, 'post_revision_fields'), 10, 1);
				}
			}
		}

		public function register($postmeta_key, $display = 'deprecated') {
			if (!in_array($postmeta_key, $this->postmeta_keys, true)) {
				$this->postmeta_keys[] = $postmeta_key;
				add_filter('_wp_post_revision_field_'.$this->prefix.$postmeta_key, array($this, 'post_revision_field'), 1, 4);
			}
			return true;
		}

		/**
		 * This is a paranoid check. There will be no object to register the
		 * actions and filters if nobody adds any postmeta to be handled
		 *
		 * @return bool
		 */
		public function have_keys() {
			return (bool) count($this->postmeta_keys);
		}

		/**
		 * Save the revision data
		 *
		 * @param int $post_id
		 * @param object $post
		 * @return void
		 */
		public function save_post_revision($post_id, $post) {
			if ($post->post_type != 'revision' || !$this->have_keys()) {
				return false;
			}

			foreach ($this->postmeta_keys as $postmeta_type) {
				$postmeta_key = $postmeta_type;

				if ($postmeta_values = get_post_meta($post->post_parent, $postmeta_key)) {
					foreach ($postmeta_values as $postmeta_value) {
						add_metadata('post', $post_id, $postmeta_key, $postmeta_value);
					}
					$this->log('Added postmeta for: '.$postmeta_key.' to revision: '.$post_id.' from post: '.$post->post_parent);
				}
			}
		}

		/**
		 * Revert the revision data
		 *
		 * @param int $post_id
		 * @param int $revision_id
		 * @return void
		 */
		public function restore_post_revision($post_id, $revision_id) {
			if (!$this->have_keys()) {
				return false;
			}

			foreach ($this->postmeta_keys as $postmeta_type) {
				$postmeta_key = $postmeta_type;
				delete_metadata('post', $post_id, $postmeta_key);
				if ($postmeta_values = get_metadata('post', $revision_id, $postmeta_key)) {
					foreach ($postmeta_values as $postmeta_value) {
						$this->log('Setting postmeta: '.$postmeta_key.' for post: '.$post_id);
						add_metadata('post', $post_id, $postmeta_key, $postmeta_value, true);
					}
					$this->log('Restored post_id: '.$post_id.' metadata from: '.$postmeta_key);
				}
			}
		}

		public function post_revision_fields($fields) {
			$name_base = apply_filters('cfrm_compare_header', __('Post Meta: '));
			foreach ($this->postmeta_keys as $key) {
				$fields[$this->prefix.$key] = apply_filters('cfrm_compare_header_'.$key, $name_base.$key, $key);
			}
			return $fields;
		}

		public function post_revision_field($field_id, $field, $comparison_post, $type) {

			// remove prefix
			if (substr($field, 0, strlen($this->prefix)) == $this->prefix) {

				$key = substr($field, strlen($this->prefix));
			}
			else {
				return '';
			}

			if (in_array($key, $this->postmeta_keys)) {
				$html = print_r(get_post_meta($comparison_post->ID, $key, true),1). "\n";
			}
			return $html;
		}

		public function has_changes() {
			$names = apply_filters('cfr_html_names', $this->postmeta_keys);
			// Array of post_meta_key => html_name

			if ($this->have_keys()) {
				foreach ($names as $meta_key => $html_name) {

				}
			}
		}

		/**
		 * Singleton
		 *
		 * @return object
		 */
		public function get_instance() {
			if (!(self::$_instance instanceof cf_revisions)) {
				self::$_instance = new cf_revisions;
			}
			return self::$_instance;
		}

		protected function log($message) {
			if (CF_REVISIONS_DEBUG) {
				error_log($message);
			}
		}
	}

	if (defined('CF_REVISIONS_DEBUG') && CF_REVISIONS_DEBUG) {
		include('tests.php');
	}
}
