<?php
/**
 * UX handling for Duplicity CLI deduplicated attachments.
 *
 * @package duplicity
 * @version 0.1.0-1
 *
 * @wordpress-plugin
 * Plugin Name: Duplicity
 * Version: 0.1.0-1
 * Plugin URI: https://github.com/Blobfolio/duplicity
 * Description: UX handling for Duplicity CLI deduplicated attachments.
 * Author: Blobfolio, LLC
 * Author URI: https://blobfolio.com/
 * Text Domain: duplicity
 * Domain Path: /languages/
 * License: WTFPL
 * License URI: http://www.wtfpl.net/
 *
 * This is a companion script to Duplicity CLI. It is meant to be added
 * to a site's WPMU_PLUGIN_DIR.
 */

if (!defined('ABSPATH')) {
	exit;
}

// Don't load this twice.
if (defined('DUPLICITY_MU_INDEX')) {
	return;
}

define('DUPLICITY_MU_ROOT', dirname(__FILE__) . '/');
define('DUPLICITY_MU_INDEX', __FILE__);



/**
 * Is Deduplicated?
 *
 * @param int $attachment_id Attachment ID.
 * @return bool True/false.
 */
function is_duplicity_attachment($attachment_id=0) {
	static $attachments;
	if (is_null($attachments)) {
		$attachments = array();
		global $wpdb;

		$dbResult = $wpdb->get_results("
			SELECT `post_id`
			FROM `{$wpdb->postmeta}`
			WHERE `meta_key`='_duplicity_id'
			ORDER BY `post_id` ASC
		", ARRAY_A);
		if (is_array($dbResult) && count($dbResult)) {
			foreach ($dbResult as $Row) {
				$attachments[] = (int) $Row['post_id'];
			}
		}
	}

	$attachment_id = (int) $attachment_id;
	return in_array($attachment_id, $attachments, true);
}

/**
 * List View Notice
 *
 * Add a notice to the media library's list view when the media is part
 * of a deduplicated group.
 *
 * @param array $actions Actions.
 * @param object $post Post.
 * @return array Actions.
 */
function duplicity_media_row_actions($actions, $post) {
	if (is_duplicity_attachment($post->ID)) {
		$actions[] = 'Deduplicated Attachment';

		// Replace media will just cause trouble.
		foreach ($actions as $k=>$v) {
			if (
				(false !== strpos($v, 'enable-media-replace')) ||
				(false !== strpos($v, 'action=delete'))
			) {
				unset($actions[$k]);
			}
		}
	}

	return $actions;
}
add_filter('media_row_actions', 'duplicity_media_row_actions', 20, 2);

/**
 * Edit Media Notice
 *
 * Add a notice to the edit media page.
 *
 * @param array $fields Fields.
 * @param object $post Post.
 * @return array Actions.
 */
function duplicity_attachment_fields_to_edit($fields, $post) {
	if (is_duplicity_attachment($post->ID)) {
		$fields['duplicity'] = array(
			'label'=>'Deduplicated',
			'input'=>'html',
			'html'=>'<p style="margin: 0;">The same attachment was uploaded multiple times.</p>' . duplicity_script(),
		);

		// Replace media will just cause trouble.
		if (isset($fields['enable-media-replace'])) {
			unset($fields['enable-media-replace']);
		}
	}

	return $fields;
}
add_action('attachment_fields_to_edit', 'duplicity_attachment_fields_to_edit', 20, 2);

/**
 * Remove Delete Links
 *
 * WordPress does not provide adequate filters for intervening in the
 * media delete process, so instead we'll just hide any such links.
 *
 * @return string Javascript.
 */
function duplicity_script() {
	ob_start();
	?>
	<script>
	(function(){
		var dp = function() {
			var item;

			item = document.getElementById('delete-action');
			if (item) {
				console.warn('Attachment is part of deduplication group; removed "Delete" option.');
				item.parentNode.removeChild(item);
			}

			item = document.querySelector('.delete-attachment');
			if (item) {
				console.warn('Attachment is part of deduplication group; removed "Delete" option.');
				item.parentNode.removeChild(item);
			}
		};

		// Run it now?
		if(document.readyState === 'complete') {
			dp();
		}
		// Or after scripts have finished loading?
		else {
			window.addEventListener('load', dp, false);
		}
	})();
	</script>
	<?php
	return ob_get_clean();
}
