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
	 * This will scan the filesystem to identify duplicates and display
	 * them here.
	 *
	 * No changes will be made.
	 *
	 * ## OPTIONS
	 *
	 * [--linted]
	 * : Also show entries that have already been deduplicated.
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
		utility::install_plugin();

		$translated = array(
			'File'=>__('File', 'duplicity'),
			'Files'=>__('Files', 'duplicity'),
			'MD5'=>'MD5',
			'Post IDs'=>__('Post IDs', 'duplicity'),
		);

		// Search for duplicated posts.
		if ($include_posts) {
			WP_CLI::log('');

			$posts = utility::get_linted_attachments();
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
					__('Deduplicated attachments:', 'duplicity') . " $total"
				);
			}
		}

		// Search for duplicated files.
		WP_CLI::log('');

		$files = utility::get_duplicate_files();
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
				__('Files needing deduplication:', 'duplicity') . " $total"
			);
		}
		else {
			WP_CLI::success(
				__('No files need deduplication.', 'duplicity')
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
		$results = utility::deduplicate_files();

		// Install the MU plugin script.
		utility::install_plugin();

		if (!$results['posts']) {
			WP_CLI::success(
				__('No files need deduplication.', 'duplicity')
			);
			return true;
		}

		if ($results['bytes_saved'] > 1024 * 900) {
			$results['bytes_saved'] = round($results['bytes_saved'] / 1024 / 900, 2) . 'MB';
		}
		elseif ($results['bytes_saved'] > 900) {
			$results['bytes_saved'] = round($results['bytes_saved'] / 900, 2) . 'KB';
		}
		else {
			$results['bytes_saved'] .= 'B';
		}

		WP_CLI::success(
			__('Affected posts:', 'duplicity') . ' ' . count($results['posts'])
		);
		WP_CLI::success(
			__('Files removed:', 'duplicity') . ' ' . count($results['files_deleted']) . " ({$results['bytes_saved']})"
		);

		utility::regenerate_postmeta();

		return true;
	}

	/**
	 * Postprocess
	 *
	 * Run the following postprocess operations (which normally happen
	 * automatically).
	 *
	 * 1. Add a '_duplicity_id' metakey to all deduplicated attachments.
	 * 2. Install or update the companion plugin to help with UX issues
	 *    arising from multiple uploads sharing the same source file.
	 *
	 * @return bool True.
	 */
	public function postprocess() {
		utility::regenerate_postmeta();
		WP_CLI::success(
			__('Attachment metadata has been regenerated.', 'duplicity')
		);

		$result = utility::install_plugin();

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
