<?php
/**
 * Duplicity: Utilities
 *
 * Remove some clutter from the main CLI class.
 *
 * @package duplicity
 * @author  Blobfolio, LLC <hello@blobfolio.com>
 */

namespace blobfolio\wp\duplicity;

use \blobfolio\wp\duplicity\vendor\common;
use \WP_CLI;
use \WP_CLI\Utils;

class utility {

	const META_KEY = '_duplicity_id';

	protected static $attachments;
	protected static $checksums;
	protected static $database;
	protected static $upload_dir;
	protected static $upload_url;

	/**
	 * Load Paths
	 *
	 * @return bool True.
	 */
	protected static function load_paths() {
		if (is_null(static::$upload_dir)) {
			$tmp = wp_upload_dir();
			static::$upload_dir = trailingslashit($tmp['basedir']);
			static::$upload_url = trailingslashit($tmp['baseurl']);
		}

		return true;
	}

	/**
	 * Get Upload Dir
	 *
	 * @return string Directory.
	 */
	public static function get_upload_dir() {
		static::load_paths();
		return static::$upload_dir;
	}

	/**
	 * Load Attachments
	 *
	 * Find all WordPress file attachments and store them in an array.
	 * The keys will be post IDs, the values file paths.
	 *
	 * @param bool $refresh Refresh.
	 * @return bool True.
	 */
	protected static function load_attachments($refresh=false) {
		if ($refresh || !is_array(static::$attachments)) {
			global $wpdb;
			static::$attachments = array();

			// Pull attachments from the database.
			$dbResult = $wpdb->get_results("
				SELECT
					p.ID AS `post_id`,
					m.meta_value AS `file_path`
				FROM
					`{$wpdb->posts}` AS p,
					`{$wpdb->postmeta}` AS m
				WHERE
					p.post_type='attachment' AND
					m.post_id=p.ID AND
					m.meta_key='_wp_attached_file'
				ORDER BY p.ID ASC
			", ARRAY_A);
			if (is_array($dbResult) && count($dbResult)) {
				foreach ($dbResult as $Row) {
					$Row['post_id'] = (int) $Row['post_id'];
					static::$attachments[$Row['post_id']] = $Row['file_path'];
				}
			}
		}

		return true;
	}

	/**
	 * Get Attachments
	 *
	 * @param bool $refresh Refresh.
	 * @return bool True.
	 */
	public static function get_attachments($refresh=false) {
		static::load_attachments($refresh);
		return static::$attachments;
	}

	/**
	 * Get Orphans
	 *
	 * @param bool $all Look everywhere.
	 * @return array Orphans.
	 */
	public static function get_orphans($all=false) {
		static::load_paths();
		$official = static::get_attachments(true);
		$orphans = array();

		// Start by getting the file paths we expect to find.
		if (count($official)) {
			$official = array_flip($official);
			foreach ($official as $k=>$v) {
				$official[$k] = 1;
			}
			ksort($official);
		}
		else {
			$official = array();
		}

		$orphans = array();

		$dir = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator(
				static::$upload_dir,
				\RecursiveDirectoryIterator::SKIP_DOTS |
				\RecursiveDirectoryIterator::CURRENT_AS_PATHNAME |
				\RecursiveDirectoryIterator::UNIX_PATHS
			)
		);
		$base_length = strlen(static::$upload_dir);
		foreach ($dir as $file) {
			// Early and easy skips.
			if (
				(0 !== strpos($file, static::$upload_dir)) ||
				preg_match('/\.(html?|php|js|css)(\.(gz|br))?$/i', $file)
			) {
				continue;
			}

			// Find the relative dir.
			$relative = substr($file, $base_length);

			// We can skip this if it is official.
			if (isset($official[$relative])) {
				continue;
			}

			$filepath = trailingslashit(dirname($relative));
			$filebase = basename($relative);
			$filename = pathinfo($relative, PATHINFO_FILENAME);
			$fileext = pathinfo($relative, PATHINFO_EXTENSION);

			// Make sure the subdirs are basic WP material.
			if (
				!$all &&
				('/' !== $filepath) &&
				!preg_match('#^\d{4}/\d{2}/$#', $filepath)
			) {
				continue;
			}

			// WebP is handled a bit differently. It might take two
			// passes to clear them entirely.
			if (strtolower($fileext) === 'webp') {
				if (false === static::webp_sister($relative)) {
					$orphans[] = $relative;
				}
				continue;
			}

			// Regular thumbnail?
			if (preg_match('/(\-\d+x\d+)$/', $filename, $match)) {
				if ($match[0]) {
					$filename_original = substr($filename, 0, 0 - strlen($match[0]));
					if (isset($official["{$filepath}{$filename_original}.{$fileext}"])) {
						continue;
					}
				}
			}

			// PDF thumbnail?
			if (preg_match('/(\-pdf(\-\d+x\d+)?)$/', $filename, $match)) {
				if ($match[0]) {
					$filename_original = substr($filename, 0, 0 - strlen($match[0]));
					if (isset($official["{$filepath}{$filename_original}.pdf"])) {
						continue;
					}
				}
			}

			// Post Thumbnail Editor garbage.
			if (preg_match('/\-\d+x\d+\-\d{10,}$/', $filename, $match)) {
				if ($match[0]) {
					// For reasons that aren't clear, sometimes this
					// pattern is tossed onto the full source basename,
					// while other times it is built more like a
					// traditional thumbnail.
					$filebase_original = substr($filename, 0, 0 - strlen($match[0]));
					if (
						isset($official["{$filepath}{$filebase_original}"]) ||
						isset($official["{$filepath}{$filebase_original}.{$fileext}"])
					) {
						continue;
					}
				}
			}

			// Probably an orphan.
			$orphans[] = $relative;
		}

		sort($orphans);
		return $orphans;
	}

	/**
	 * Look for WebP Companion
	 *
	 * WebP images tend to be copies of traditional media. This will
	 * check to see whether or not a non-webp file exists.
	 *
	 * @param string $webp WebP path (relative).
	 * @return string|bool Sister path or false.
	 */
	protected static function webp_sister($webp) {
		if (
			!$webp ||
			!is_string($webp) ||
			!preg_match('/\.webp$/i', $webp) ||
			!file_exists(static::$upload_dir . $webp)
		) {
			return false;
		}

		$exts = array(
			'gif',
			'jpg',
			'jpeg',
			'png',
		);

		$webp = substr($webp, 0, -4);
		foreach ($exts as $ext) {
			if (file_exists(static::$upload_dir . "{$webp}{$ext}")) {
				return "{$webp}{$ext}";
			}
		}

		return false;
	}

	/**
	 * Load Checksums
	 *
	 * Build an array of file checksums. The keys will be MD5 hashes,
	 * values an array of post IDs.
	 *
	 * @param bool $refresh Refresh.
	 * @return bool True.
	 */
	protected static function load_checksums($refresh=false) {
		if ($refresh || !is_array(static::$checksums)) {
			static::load_attachments($refresh);
			static::load_paths();
			static::$checksums = array();

			foreach (static::$attachments as $k=>$v) {
				$md5 = @md5_file(static::$upload_dir . $v);
				if ($md5 && isset($md5[31])) {
					if (!isset(static::$checksums[$md5])) {
						static::$checksums[$md5] = array();
					}
					static::$checksums[$md5][] = $v;
				}
			}

			foreach (static::$checksums as $k=>$v) {
				static::$checksums[$k] = array_values(array_unique($v));
			}
		}

		return true;
	}

	/**
	 * Get Checksums
	 *
	 * @param bool $refresh Refresh.
	 * @return bool True.
	 */
	public static function get_checksums($refresh=false) {
		static::load_checksums($refresh);
		return static::$checksums;
	}

	/**
	 * Get Linted Attachments
	 *
	 * Return a list of attachments that have already undergone linting.
	 *
	 * @param bool $refresh Refresh.
	 * @return array Post IDs.
	 */
	public static function get_linted_attachments($refresh=false) {
		$out = array();

		// Make a copy of our attachments list because this process is
		// destructive.
		$attachments = static::get_attachments($refresh);
		if (!count($attachments)) {
			return $out;
		}

		// Are there any duplicates? Let's start by counting values.
		$counted = array_count_values($attachments);
		arsort($counted);

		foreach ($counted as $k=>$v) {
			// We're done looking.
			if ($v < 2) {
				break;
			}

			$out[$k] = array();
			while (false !== ($post_id = array_search($k, $attachments, true))) {
				$out[$k][] = $post_id;
				unset($attachments[$post_id]);
			}

			sort($out[$k]);
		}

		ksort($out);
		return $out;
	}

	/**
	 * Get Duplicate Files
	 *
	 * Find identical files on the file system.
	 *
	 * @param bool $refresh Refresh.
	 * @return array Files.
	 */
	public static function get_duplicate_files($refresh=false) {
		$out = array();

		$checksums = static::get_checksums($refresh);
		if (!count($checksums)) {
			return $out;
		}

		foreach ($checksums as $k=>$v) {
			if (count($v) < 2) {
				continue;
			}

			$out[$k] = $v;
		}

		ksort($out);
		return $out;
	}

	/**
	 * Get Sister Files
	 *
	 * Find all files associated with a particular attachment.
	 *
	 * @param string $source File source (relative).
	 * @return array Files.
	 */
	protected static function get_sister_files($source='') {
		static::load_paths();
		common\ref\cast::to_string($source, true);
		$out = array();

		if (!$source) {
			return $out;
		}
		$subdir = trailingslashit(dirname($source));
		$dir = static::$upload_dir . $subdir;
		if (!@is_dir($dir)) {
			return $out;
		}

		$source = basename($source);
		$filename = pathinfo($source, PATHINFO_FILENAME);
		$ext = pathinfo($source, PATHINFO_EXTENSION);
		if (!$filename || !$ext) {
			return $out;
		}

		$pattern = preg_quote($filename, '/') . '(\-\d+x\d+)?\.' . preg_quote($ext, '/');

		if ($handle = opendir($dir)) {
			while (false !== ($file = readdir($handle))) {
				if (('.' === $file) || ('..' === $file)) {
					continue;
				}

				if (preg_match("/^$pattern$/", $file) && @is_file($dir . $file)) {
					$out[] = $subdir . $file;
				}
			}

			closedir($handle);
		}

		return $out;
	}

	/**
	 * Quick MySQL Search
	 *
	 * This checks to see whether a substring can be found in any
	 * prefixed table. This allows us to avoid expensive search/replace
	 * calls for items that aren't referenced anywhere.
	 *
	 * @param string $str String.
	 * @return bool True/false.
	 */
	protected static function mysql_search($str='') {
		common\ref\cast::to_string($str, true);
		common\ref\mb::trim($str);
		if (!$str) {
			return false;
		}

		global $wpdb;
		$str = esc_sql($wpdb->esc_like($str));

		// Do we need to build the schema?
		if (!is_array(static::$database)) {
			static::$database = array();

			// Pull tables, listing anything *post* first since that
			// will be the most likely place to find a result.
			$dbResult = $wpdb->get_results("
				SELECT `table_name`
				FROM information_schema.tables
				WHERE
					`table_type` = 'BASE TABLE' AND
					`table_schema` = '" . DB_NAME . "' AND
					`table_name` LIKE '{$wpdb->prefix}%'
				ORDER BY
					(`table_name` LIKE '%post%') DESC,
					`table_name` ASC
			", ARRAY_N);
			if (is_array($dbResult) && count($dbResult)) {
				foreach ($dbResult as $Row) {
					static::$database[$Row[0]] = array();

					// Now pull columns. We only care about (var)char
					// and text since we're searching for a string and
					// WP doesn't go binary or anything like that.
					$dbResult2 = $wpdb->get_results("
						SHOW COLUMNS FROM `{$Row[0]}`
					", ARRAY_N);
					if (is_array($dbResult2) && count($dbResult2)) {
						foreach ($dbResult2 as $Row2) {
							if (
								(false !== strpos($Row2[1], 'char(')) ||
								(false !== strpos($Row2[1], 'text('))
							) {
								static::$database[$Row[0]][] = $Row2[0];
							}
						}
					}

					if (!count(static::$database[$Row[0]])) {
						unset(static::$database[$Row[0]]);
					}
				}
			}
		}

		// We couldn't build the table. Who knows?
		if (!count(static::$database)) {
			return true;
		}

		// All right, let's run some queries!
		foreach (static::$database as $table=>$columns) {
			$conds = array();
			foreach ($columns as $v) {
				$conds[] = "`$v` LIKE '%$str%'";
			}
			$conds = implode(' OR ', $conds);

			$hits = (int) $wpdb->get_var("SELECT COUNT(*) FROM `$table` WHERE $conds");
			if ($hits) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Deduplicate Files
	 *
	 * This will repoint duplicate attachment posts to the original
	 * source file, remove its files from the system, and search-replace
	 * references to the file paths.
	 *
	 * ID-based references will remain, but since the post's attachment
	 * reference has been updated, should now resolve to the original
	 * image.
	 *
	 * @return array Post IDs.
	 */
	public static function deduplicate_files() {
		$out = array(
			'bytes_saved'=>0,
			'files_deleted'=>array(),
			'files_saved'=>array(),
			'posts'=>array(),
		);

		// First, check to see if there are any duplicate files.
		$files = static::get_duplicate_files();
		if (!count($files)) {
			return $out;
		}

		// Grab a list of what we've already linted.
		$linted = static::get_linted_attachments(true);
		require_once(ABSPATH . 'wp-admin/includes/image.php');
		global $wpdb;

		// Take a quick break to make sure they really want to proceed.
		WP_CLI::warning(
			__('Files with duplicates:', 'duplicity') . ' ' . count($files)
		);
		WP_CLI::confirm(
			__('Really proceed with deduplication? Please back up files and data before proceeding!', 'duplicity')
		);

		// Loop it.
		$progress = Utils\make_progress_bar('Deduplicating attachments.', count($files));
		foreach ($files as $k=>$v) {
			$progress->tick();

			$primary = '';
			$dupes = array();
			$update_posts = array();
			$update_files = array();
			$in_lint = 0;

			foreach ($v as $v2) {
				if (!$primary && isset($linted[$v2])) {
					$primary = $v2;
					$in_lint++;
				}
				else {
					$dupes[] = $v2;
					if (isset($linted[$v2])) {
						$in_lint++;
					}
				}
			}

			// If we don't have a primary yet, just pluck the first from
			// the list.
			if (!$primary) {
				$primary = array_shift($dupes);
			}

			// Go through our data and find all files (duplicate only)
			// and all affected post IDs (including primary).
			$attachments = static::get_attachments();
			foreach ($dupes as $v2) {
				if ($v2 !== $primary) {
					$update_files += static::get_sister_files($v2);
				}

				while (false !== ($post_id = array_search($v2, $attachments, true))) {
					$update_posts[] = $post_id;
					unset($attachments[$post_id]);
				}
			}
			while (false !== ($post_id = array_search($primary, $attachments, true))) {
				$update_posts[] = $post_id;
				unset($attachments[$post_id]);
			}
			$update_files = array_values(array_unique($update_files));
			$update_posts = array_values(array_unique($update_posts));

			// The main ID is the oldest. This may or may not correspond
			// to the file we've identified as primary, but that doesn't
			// really matter.
			$primary_id = $update_posts[0];

			// Step one: update file entries for post/postmeta.
			$cond = implode(',', $update_posts);
			$wpdb->query("
				UPDATE `{$wpdb->posts}`
				SET `guid`='" . esc_sql(static::$upload_url . $primary) . "'
				WHERE `ID` IN ($cond)
			");
			$wpdb->query("
				UPDATE `{$wpdb->postmeta}`
				SET `meta_value`='" . esc_sql($primary) . "'
				WHERE
					`meta_key`='_wp_attached_file' AND
					`post_id` IN ($cond)
			");

			// Step two: update attachment metadata.
			$attach_data = wp_generate_attachment_metadata($primary_id, static::$upload_dir . $primary);
			foreach ($update_posts as $post_id) {
				wp_update_attachment_metadata($post_id, $attach_data);
				$out['posts'][] = $post_id;
			}

			// Step three: search/replace content for file references
			// and delete files as we go.

			// Pull apart our primary file.
			$primary_subdir = trailingslashit(dirname($primary));
			$primary_filename = pathinfo($primary, PATHINFO_FILENAME);
			$primary_ext = pathinfo($primary, PATHINFO_EXTENSION);

			// Loop over the dupes.
			foreach ($update_files as $v2) {
				$old_filename = pathinfo($v2, PATHINFO_FILENAME);
				preg_match('/\-\d+x\d+$/', $old_filename, $match);
				$size = count($match) ? $match[0] : '';

				// Build our search/replace pair.
				$from = str_replace("'", '', $v2);
				$to = str_replace("'", '', $primary_subdir . $primary_filename . $size . '.' . $primary_ext);
				if (!@file_exists(static::$upload_dir . $to)) {
					$to = $primary;
				}

				// Let WP-CLI handle replacements its usual way. We're
				// using ::launch_self instead of ::runcommand because
				// that seems to be the only way to prevent it from
				// polluting STDOUT.
				if (static::mysql_search($v2)) {
					WP_CLI::launch_self(
						'search-replace',
						array($from, $to),
						array('all-tables-with-prefix'=>true),
						true,
						false,
						array('quiet'=>true)
					);
				}

				// Now we can remove the old file.
				if (@is_file(static::$upload_dir . $v2)) {
					$out['bytes_saved'] += @filesize(static::$upload_dir . $v2);
					@unlink(static::$upload_dir . $v2);

					$out['files_deleted'][] = $v2;
				}
			}

			// Note which files we're keeping.
			$saved = static::get_sister_files($primary);
			foreach ($saved as $v2) {
				$out['files_saved'][] = $v2;
			}

			// Reload the linted data because we've had to merge two or
			// more linted groups.
			if ($in_lint > 1) {
				$linted = static::get_linted_attachments(true);
			}
		}
		$progress->finish();

		return $out;
	}

	/**
	 * Rebuild Deduplication Attachment Meta
	 *
	 * To lighten subsequent search operations, each deduplicated
	 * attachment will receive a special postmeta entry.
	 *
	 * @return bool True.
	 */
	public static function regenerate_postmeta() {
		global $wpdb;

		// To start with, remove all existing entries.
		$wpdb->delete(
			$wpdb->postmeta,
			array('meta_key'=>static::META_KEY),
			'%s'
		);

		$inserts = array();
		$linted = static::get_linted_attachments(true);

		// Build new meta_values.
		foreach ($linted as $v) {
			$primary = $v[0];
			foreach ($v as $v2) {
				$inserts[] = "($v2,'" . static::META_KEY . "',$primary)";
			}
		}

		// Insert them in chunks for performance reasons.
		if (count($inserts)) {
			$inserts = array_chunk($inserts, 250);
			foreach ($inserts as $v) {
				$wpdb->query("
					INSERT INTO `{$wpdb->postmeta}` (`post_id`, `meta_key`, `meta_value`)
					VALUES " . implode(', ', $v)
				);
			}
		}

		return true;
	}

	/**
	 * Install Plugin
	 *
	 * Install the helper plugin to the WPMU_PLUGIN_DIR.
	 *
	 * @return bool True/false.
	 */
	public static function install_plugin() {
		if (!defined('WPMU_PLUGIN_DIR')) {
			return false;
		}

		if (!@file_exists(WPMU_PLUGIN_DIR)) {
			@mkdir(WPMU_PLUGIN_DIR, FS_CHMOD_DIR, true);
			if (!@file_exists(WPMU_PLUGIN_DIR)) {
				return false;
			}
		}

		$script = DUPLICITY_ROOT . 'duplicity-mu.php';
		if (!@file_exists($script)) {
			return false;
		}

		$local = trailingslashit(WPMU_PLUGIN_DIR) . 'duplicity-mu.php';
		@file_put_contents($local, @file_get_contents($script));
		if (!@file_exists($local)) {
			return false;
		}
		@chmod($local, FS_CHMOD_FILE);

		return true;
	}

	/**
	 * Nice Bytes
	 *
	 * Convert bytes to a more compact unit for friendlier display.
	 *
	 * @param int $bytes Bytes.
	 * @return string Size.
	 */
	public static function nice_bytes($bytes) {
		$bytes = (int) $bytes;
		if ($bytes < 0) {
			$bytes = 0;
		}

		if ($bytes > 1024 * 900) {
			return round($bytes / 1024 / 1024, 2) . 'MB';
		}
		elseif ($bytes > 900) {
			return round($bytes / 1024, 2) . 'KB';
		}

		return "{$bytes}B";
	}
}
