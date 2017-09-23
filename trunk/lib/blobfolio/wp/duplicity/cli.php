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
	 * There are two senses in which an attachment is a "duplicate":
	 * 1) The wp_posts entry has been duplicated;
	 * 2) The same file has been independently uploaded more than once;
	 *
	 * ## OPTIONS
	 *
	 * [--include-posts]
	 * : Search wp_posts for duplicated entries.
	 * Default: True
	 *
	 * [--include-files]
	 * : Search the file system for identical files.
	 * Default: True.
	 *
	 * @param array $args Not used.
	 * @param array $assoc_args Flags.
	 * @return bool True/false.
	 *
	 * @subcommand list
	 */
	public function _list($args, $assoc_args=array()) {
		$include_posts = !!Utils\get_flag_value($assoc_args, 'include-posts', true);
		$include_files = !!Utils\get_flag_value($assoc_args, 'include-files', true);

		$translated = array(
			'File'=>__('File', 'duplicity'),
			'Files'=>__('Files', 'duplicity'),
			'MD5'=>'MD5',
			'Post IDs'=>__('Post IDs', 'duplicity'),
		);

		// Search for duplicated posts.
		if ($include_posts) {
			WP_CLI::log('');

			$posts = attachment::get_duplicate_posts();
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
				WP_CLI::warning(
					__('Duplicated attachment posts:', 'duplicity') . " $total"
				);
			}
			else {
				WP_CLI::success(
					__('No file attachment posts have been duplicated.', 'duplicity')
				);
			}
		}

		// Search for duplicated files.
		if ($include_files) {
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
		}

		WP_CLI::log('');
		return true;
	}

}
