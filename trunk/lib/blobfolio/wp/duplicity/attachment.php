<?php
/**
 * Duplicity: Attachments
 *
 * Everything we need to know about file attachments.
 *
 * @package duplicity
 * @author  Blobfolio, LLC <hello@blobfolio.com>
 */

namespace blobfolio\wp\duplicity;

use \blobfolio\wp\duplicity\vendor\common;

class attachment {

	protected static $attachments;
	protected static $checksums;
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
			static::load_attachments();
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
	 * Get Duplicate Posts
	 *
	 * You'd be surprised, but some plugins duplicate post entries
	 * without duplicating files. This is stupid and dangerous since
	 * deleting one would delete all.
	 *
	 * @param bool $refresh Refresh.
	 * @return array Post IDs.
	 */
	public static function get_duplicate_posts($refresh=false) {
		$out = array();

		// Make a copy of our attachments list because this process is
		// destructive.
		$attachments = static::get_attachments($refresh);
		if (!count($attachments)) {
			return array();
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
			return array();
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
}
