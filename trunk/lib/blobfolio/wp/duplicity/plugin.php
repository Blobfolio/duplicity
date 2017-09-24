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
use \WP_CLI;
use \WP_CLI\Utils;

class plugin {

	/**
	 * Install
	 *
	 * Install the helper plugin to the WPMU_PLUGIN_DIR.
	 *
	 * @return bool True/false.
	 */
	public static function install() {
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
}
