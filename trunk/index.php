<?php
/**
 * An experimental WP-CLI plugin for detecting and deleting duplicate WordPress file attachments.
 *
 * @package duplicity
 * @version 0.1.0-1
 *
 * @wordpress-plugin
 * Plugin Name: Duplicity Attachment Linter
 * Version: 0.1.0-1
 * Plugin URI: https://github.com/Blobfolio/duplicity
 * Info URI: https://raw.githubusercontent.com/Blobfolio/duplicity/master/release/duplicity.json
 * Description: An experimental WP-CLI plugin for detecting and deleting duplicate WordPress file attachments.
 * Author: Blobfolio, LLC
 * Author URI: https://blobfolio.com/
 * Text Domain: duplicity
 * Domain Path: /languages/
 * License: WTFPL
 * License URI: http://www.wtfpl.net/
 */

// This is for WP-CLI only.
if (!defined('WP_CLI') || !WP_CLI) {
	return;
}

// Where are we?
define('DUPLICITY_ROOT', dirname(__FILE__) . '/');
define('DUPLICITY_INDEX', DUPLICITY_ROOT . 'index.php');

// The bootstrap.
require(dirname(__FILE__) . '/lib/autoload.php');

use \blobfolio\wp\duplicity\vendor\common;

// Add the main command.
WP_CLI::add_command(
	'duplicity',
	'\\blobfolio\\wp\\duplicity\\cli',
	array(
		'before_invoke'=>function() {
			if (is_multisite()) {
				WP_CLI::error(
					__('This plugin is not multisite compatible.', 'duplicity')
				);
			}

			// Some helpful requirements.
			require_once(ABSPATH . 'wp-admin/includes/file.php');

			// Make sure CHMOD is set.
			if (!defined('FS_CHMOD_DIR')) {
				define('FS_CHMOD_DIR', ( fileperms( ABSPATH ) & 0777 | 0755 ) );
			}
			if (!defined('FS_CHMOD_FILE')) {
				define('FS_CHMOD_FILE', ( fileperms( ABSPATH . 'index.php' ) & 0777 | 0644 ) );
			}
		},
	)
);
