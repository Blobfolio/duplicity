<?php
/**
 * Duplicity: CLI Commands
 *
 * @package duplicity
 * @author  Blobfolio, LLC <hello@blobfolio.com>
 */

namespace blobfolio\wp\duplicity;

use \blobfolio\wp\duplicity\vendor\common;
use \WP_CLI;
use \WP_CLI\Utils;

/**
 * Duplicity
 *
 * Find and remove duplicate attachments.
 *
 * ## EXAMPLES
 *
 *     wp duplicity --help
 */
class cli extends \WP_CLI_Command {

	/**
	 * List Duplicate Attachments
	 *
	 * The same file might be uploaded to WordPress more than once.
	 * This will scan the file system to identify duplicates.
	 *
	 * ## OPTIONS
	 *
	 * [--linted]
	 * : Show entries that have already been deduplicated.
	 *
	 * @param array $args Not used.
	 * @param array $assoc_args Flags.
	 * @return bool True/false.
	 *
	 * @subcommand list
	 */
	public function _list($args, $assoc_args=array()) {
		$include_posts = !!Utils\get_flag_value($assoc_args, 'linted', false);

		// Install the MU plugin script.
		plugin::install();

		$translated = array(
			'File'=>__('File', 'duplicity'),
			'Files'=>__('Files', 'duplicity'),
			'MD5'=>'MD5',
			'Post IDs'=>__('Post IDs', 'duplicity'),
		);

		// Search for duplicated posts.
		if ($include_posts) {
			WP_CLI::log('');

			$posts = attachment::get_linted_attachments();
			if (count($posts)) {
				$headers = array(
					$translated['File'],
					$translated['Post IDs'],
				);

				$data = array();
				$total = 0;
				foreach ($posts as $k=>$v) {
					$data[] = array(
						$translated['File']=>$k,
						$translated['Post IDs']=>implode(', ', $v),
					);

					$total += (count($v) - 1);
				}

				Utils\format_items('table', $data, $headers);
				WP_CLI::success(
					__('Linted attachments:', 'duplicity') . " $total"
				);
			}
		}

		// Search for duplicated files.
		WP_CLI::log('');

		$files = attachment::get_duplicate_files();
		if (count($files)) {
			$headers = array(
				$translated['MD5'],
				$translated['Files'],
			);

			$data = array();
			$total = 0;
			foreach ($files as $k=>$v) {
				$data[] = array(
					$translated['MD5']=>$k,
					$translated['Files']=>implode('; ', $v),
				);

				$total += (count($v) - 1);
			}

			Utils\format_items('table', $data, $headers);
			WP_CLI::warning(
				__('Duplicated file uploads:', 'duplicity') . " $total"
			);
		}
		else {
			WP_CLI::success(
				__('No duplicate files have been uploaded.', 'duplicity')
			);
		}

		WP_CLI::log('');
		return true;
	}

	/**
	 * Deduplicate
	 *
	 * Remove and relink duplicate file attachments.
	 *
	 * ## OPTIONS
	 *
	 * @return bool True/false.
	 */
	public function deduplicate() {
		$results = attachment::deduplicate_files();

		// Install the MU plugin script.
		plugin::install();

		if (!$results['posts']) {
			WP_CLI::success(
				__('No attachments needed deduplicating.', 'duplicity')
			);
			return true;
		}

		WP_CLI::success(
			__('Affected posts:', 'duplicity') . ' ' . count($results['posts'])
		);
		WP_CLI::success(
			__('Files removed:', 'duplicity') . ' ' . count($results['deleted'])
		);

		attachment::regenerate_postmeta();

		return true;
	}

	/**
	 * Rebuild Postmeta
	 *
	 * To help lighten database queries, deduplicated attachments each
	 * get a postmeta entry indicating the main source.
	 *
	 * This postmeta is updated automatically when other operations are
	 * performed, however this function can be used on its own if data
	 * falls out of sync for whatever reason.
	 *
	 * @return bool True.
	 *
	 * @subcommand regenerate-postmeta
	 */
	public function regenerate_postmeta() {
		attachment::regenerate_postmeta();
		WP_CLI::success(
			__('Attachment postmeta has been updated.', 'duplicity')
		);

		// Install the MU plugin script.
		plugin::install();

		return true;
	}

	/**
	 * Install
	 *
	 * This will add a small helper plugin to the WPMU_PLUGIN_DIR. The
	 * main purpose of this script is improving how deduplicated posts
	 * and files are handled during delete actions, etc.
	 *
	 * @return bool True.
	 */
	public function install() {
		$result = plugin::install();

		if (!$result) {
			WP_CLI::error(
				__('The companion plugin could not be installed.', 'duplicity')
			);
		}

		WP_CLI::success(
			__('The companion plugin has been installed.', 'duplicity')
		);

		return $result;
	}
}
