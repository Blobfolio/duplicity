<?php
/**
 * Rebuild Dependencies
 *
 * We want to try and sandbox the dependencies as much as
 * possible to prevent collisions in the broader WP
 * environment.
 *
 * The main way this is achieved is by grouping external
 * classes under the plugin's namespace.
 *
 * Dirty, dirty work.
 *
 * @package duplicity
 * @author  Blobfolio, LLC <hello@blobfolio.com>
 */

define('BUILD_DIR', dirname(__FILE__) . '/');
define('PLUGIN_BASE', dirname(BUILD_DIR) . '/trunk/');
define('VENDOR_BASE', PLUGIN_BASE . 'lib/vendor/');
define('COMPOSER', BUILD_DIR . 'composer.phar');
define('PHPAB', BUILD_DIR . 'phpab.phar');
define('PREFIX', 'blobfolio\\wp\\duplicity\\vendor\\');

$from_builddir = 'cd ' . escapeshellarg(BUILD_DIR) . ' && ';
$from_plugindir = 'cd ' . escapeshellarg(PLUGIN_BASE) . ' && ';
$from_vendordir = 'cd ' . escapeshellarg(VENDOR_BASE) . ' && ';

echo "\n";



// Download the packages we'll need, just in case they aren't
// already on the system or accessible via PHP's user.
echo "+ Grabbing Composer and Autloader.\n";

shell_exec($from_builddir . 'wget -q -O composer.phar https://getcomposer.org/composer.phar');
shell_exec($from_builddir . 'wget -q -O phpab.phar https://github.com/theseer/Autoload/releases/download/1.23.0/phpab-1.23.0.phar');
shell_exec($from_builddir . 'chmod 644 composer.phar phpab.phar');
shell_exec($from_builddir . 'chmod +x composer.phar phpab.phar');



// Rebuild the Composer dependencies and autoloader.
echo "\n";
echo "+ Rebuilding dependencies.\n";

shell_exec('rm -rf ' . escapeshellarg(VENDOR_BASE));
shell_exec(
	$from_plugindir .
	'cp -a ' . escapeshellarg(BUILD_DIR . 'composer.json') . ' ./ && ' .
	escapeshellcmd(COMPOSER) . ' install --no-dev -q'
);
shell_exec($from_plugindir . 'grunt clean');
shell_exec($from_plugindir . escapeshellcmd(PHPAB) . ' -e "./node_modules/*" -e "./tests/*" -n --tolerant -o ./lib/autoload.php .');



// Parse the autoloader to find all of our classes.
echo "\n";
echo "+ Parse autoloader.\n";

$autoloader = file_get_contents(PLUGIN_BASE . 'lib/autoload.php');
if (
	(false !== ($start = strpos($autoloader, '$classes = array'))) &&
	(false !== ($end = strpos($autoloader, ');', $start)))
) {
	$classes = substr($autoloader, $start, ($end - $start + 2));
	eval($classes);
}

if (!isset($classes) || !is_array($classes)) {
	echo "ERROR!\n";
	exit(1);
}

// Remove anything that isn't in vendor.
$r_classes = array();
$r_namespaces = array();
$files = array();
foreach ($classes as $k=>$v) {
	if (0 === strpos($v, '/vendor/')) {
		$files[] = PLUGIN_BASE . 'lib/' . ltrim($v, '/');

		$r_class = str_replace('\\vendor\\blobfolio', '\\vendor', PREFIX . $k);
		$r_classes[$k] = $r_class;

		$ns = explode('\\', $k);
		if (count($ns) > 1) {
			array_pop($ns);
			$ns = implode('\\', $ns);
			$r_namespace = str_replace('\\vendor\\blobfolio', '\\vendor', PREFIX . $ns);
			$r_namespaces[$ns] = $r_namespace;
		}
	}
}



// Find all PHP files in the vendor directories.
echo "\n";
echo "+ Patching files.\n";
foreach ($files as $file) {
	echo "  + " . str_replace(VENDOR_BASE, '', $file) . "\n";
	$content = file_get_contents($file);
	$replacements = 0;

	// Replace the namespace.
	$ns = false;
	$content = preg_replace_callback(
		'/^\s*namespace\s+(\\\\)?([a-z0-9\\\\]+)\s*;/im',
		function ($matches) use($r_namespaces) {
			global $ns;
			global $replacements;
			if (3 === count($matches) && array_key_exists($matches[2], $r_namespaces)) {
				$ns = true;
				$replacements++;
				return "namespace {$r_namespaces[$matches[2]]};";
			}

			return $matches[0];
		},
		$content
	);

	// Replace use statements.
	$content = preg_replace_callback(
		'/^\s*use\s+(\\\\)?([a-z0-9\\\\]+)(\s.*)?;/imU',
		function ($matches) use($r_classes, $r_namespaces) {
			global $replacements;

			if (3 <= count($matches)) {
				$matches = array_pad($matches, 4, '');
				$matches[2] = ltrim($matches[2], '\\');
				if (array_key_exists($matches[2], $r_classes)) {
					$replacements++;
					return "use \\{$r_classes[$matches[2]]}{$matches[3]};";
				}
				elseif (array_key_exists($matches[2], $r_namespaces)) {
					$replacements++;
					return "use \\{$r_namespaces[$matches[2]]}{$matches[3]};";
				}
			}

			return $matches[0];
		},
		$content
	);

	// Manual overrides.
	$manual = array(
		"\\blobfolio\\common"=>"\\blobfolio\\wp\\duplicity\\vendor\\common",
		"\\blobfolio\\domain"=>"\\blobfolio\\wp\\duplicity\\vendor\\domain",
		"use \\blobfolio\\phone\\phone;"=>''
	);
	$content = str_replace(
		array_keys($manual),
		array_values($manual),
		$content
	);

	// Update it!
	echo "    - $replacements replacement" . ($replacements !== 1 ? 's' : '') . " made.\n";
	echo "    - Updating file.\n";
	@file_put_contents($file, $content);
}



// Rebuild autoloader.
echo "\n";
echo "+ Regenerate autoloader.\n";
shell_exec($from_plugindir . escapeshellcmd(PHPAB) . ' -e "./node_modules/*" -e "./tests/*" -n --tolerant -o ./lib/autoload.php .');



echo "\n";
echo "+ Clean up.\n";
shell_exec($from_plugindir . " rm composer.json composer.lock");



echo "\n----\nDone\n";
exit(0);
?>