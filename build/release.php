<?php
/**
 * Package!
 *
 * We want to get rid of source files and whatnot, and since they're
 * kinda all over the place, it is better to let a robot handle it.
 *
 * Dirty, dirty work.
 *
 * @package duplicity
 * @author  Blobfolio, LLC <hello@blobfolio.com>
 */

define('BUILD_DIR', dirname(__FILE__) . '/');
define('SKEL_DIR', BUILD_DIR . 'skel/');
define('PLUGIN_BASE', dirname(BUILD_DIR) . '/trunk/');
define('DEB_BASE', dirname(BUILD_DIR) . '/wp-cli-duplicity/');
define('DEB_SOURCE', DEB_BASE . 'opt/duplicity/');
define('RELEASE_ZIP', dirname(BUILD_DIR) . '/release/duplicity.zip');
define('RELEASE_JSON', dirname(BUILD_DIR) . '/release/duplicity.json');
define('MUSTY_JSON', '{
  "version": "%VERSION%",
  "download_link": "https:\/\/raw.githubusercontent.com\/Blobfolio\/duplicity\/master\/release\/duplicity.zip"
}
');



// Find the version.
$tmp = @file_get_contents(PLUGIN_BASE . 'index.php');
preg_match('/@version\s+([\d\.\-]+)/', $tmp, $matches);
if (is_array($matches) && count($matches)) {
	define('RELEASE_VERSION', $matches[1]);
}
else {
	echo "\nCould not determine version.";
	exit(1);
}



echo "\n";
echo "+ Copying the source.\n";

// Delete the release base if it already exists.
if (file_exists(DEB_BASE)) {
	shell_exec('rm -rf ' . escapeshellarg(DEB_BASE));
}

// Copy the debian stuff.
shell_exec('cp -aR ' . escapeshellarg(SKEL_DIR) . ' ' . escapeshellarg(DEB_BASE));
$tmp = @file_get_contents(DEB_BASE . 'DEBIAN/control');
$tmp = str_replace('%VERSION%', RELEASE_VERSION, $tmp);
@file_put_contents(DEB_BASE . 'DEBIAN/control', $tmp);

// Copy the trunk.
mkdir(DEB_BASE . 'opt', 0755, true);
shell_exec('cp -aR ' . escapeshellarg(PLUGIN_BASE) . ' ' . escapeshellarg(DEB_SOURCE));



echo "+ Cleaning the source.\n";
unlink(DEB_SOURCE . 'Gruntfile.js');
unlink(DEB_SOURCE . 'package.json');
shell_exec('rm -rf ' . escapeshellarg(DEB_SOURCE . 'node_modules/'));
shell_exec('find ' . escapeshellarg(DEB_BASE) . ' -name ".gitignore" -type f -delete');



// We can compress the Blobfolio vendor files.
echo "+ Compressing files.\n";
$dir = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator(DEB_SOURCE . 'lib/vendor/blobfolio/', RecursiveDirectoryIterator::SKIP_DOTS)
);
foreach ($dir as $file) {
	if (preg_match('/\.php$/i', $file)) {
		@file_put_contents($file, php_strip_whitespace($file));
	}
}



echo "+ Fixing permissions.\n";
shell_exec('find ' . escapeshellarg(DEB_BASE) . ' -type d -print0 | xargs -0 chmod 755');
shell_exec('find ' . escapeshellarg(DEB_BASE) . ' -type f -print0 | xargs -0 chmod 644');



echo "+ Packaging Zip.\n";

// The JSON is easy.
@file_put_contents(RELEASE_JSON, str_replace('%VERSION%', RELEASE_VERSION, MUSTY_JSON));

// The zip is a little more annoying.
if (@file_exists(RELEASE_ZIP)) {
	@unlink(RELEASE_ZIP);
}

$files = array();
$dir = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator(DEB_SOURCE, RecursiveDirectoryIterator::SKIP_DOTS)
);
foreach ($dir as $file) {
	$files[] = (string) $file;
}

$zip = new ZipArchive();
if ($zip->open(RELEASE_ZIP, ZipArchive::CREATE) === true) {
	foreach($files as $file) {
		$zip->addfile(
			$file,
			preg_replace('/^' . preg_quote(DEB_SOURCE, '/') . '/u', '', $file)
		);
	}
	$zip->close();
}

echo "\nDone!.\n";
exit(0);
