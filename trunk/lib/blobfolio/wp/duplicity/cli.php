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

		$results['bytes_saved'] = utility::nice_bytes($results['bytes_saved']);

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
	 * Orphans
	 *
	 * List orphaned files in the uploads directory.
	 *
	 * ## OPTIONS
	 *
	 * [--all]
	 * : This option will include all upload subdirectories in its
	 * search. You almost certainly do not want to do this.
	 *
	 * [--clean]
	 * : Remove orphaned files. (Be careful!)
	 *
	 * @param array $args Not used.
	 * @param array $assoc_args Flags.
	 * @return bool True/false.
	 */
	public function orphans($args, $assoc_args=array()) {
		$all = !!Utils\get_flag_value($assoc_args, 'all', false);
		$clean = !!Utils\get_flag_value($assoc_args, 'clean', false);

		if ($all) {
			WP_CLI::confirm(
				__('Really search for orphans in all uploads subdirectories? Plugins might store their own files there; this could be dangerous!', 'duplicity')
			);
		}

		$orphans = utility::get_orphans($all);
		if (!count($orphans)) {
			WP_CLI::success(
				__('No orphaned file attachments were found!', 'duplicity')
			);
			return true;
		}

		$translated = array(
			'File'=>__('File', 'duplicity'),
		);

		$headers = array_values($translated);
		$data = array();
		foreach ($orphans as $v) {
			$data[] = array(
				$translated['File']=>$v,
			);
		}

		Utils\format_items('table', $data, $headers);
		WP_CLI::warning(
			__('Orphaned attachments:', 'duplicity') . ' ' . count($orphans)
		);

		if (!$clean) {
			return true;
		}

		WP_CLI::confirm(
			__('Really remove these files? Please back up before proceeding!', 'duplicity')
		);

		// Count up the bytes saved.
		$total_bytes = 0;
		$total_deleted = 0;
		$upload_dir = utility::get_upload_dir();

		// Loop it.
		$progress = Utils\make_progress_bar('Removing orphaned attachments.', count($orphans));
		foreach ($orphans as $v) {
			$progress->tick();

			$path = $upload_dir . $v;
			if (!@is_file($path)) {
				continue;
			}

			$total_bytes += @filesize($path);
			@unlink($path);
			if (!@file_exists($path)) {
				$total_deleted++;
			}
		}
		$progress->finish();

		if ($total_deleted > 0) {
			WP_CLI::success(
				__('Files removed:', 'duplicity') . " $total_deleted (" . utility::nice_bytes($total_bytes) . ')'
			);
		}

		if (count($orphans) !== $total_deleted) {
			WP_CLI::warning(
				__('Not all files could be removed.', 'duplicity')
			);
		}

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
