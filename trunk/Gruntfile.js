/*global module:false*/
module.exports = function(grunt) {

	// Project configuration.
	grunt.initConfig({

		// Metadata.
		pkg: grunt.file.readJSON('package.json'),

		// Garbage collection.
		clean: {
			composer: [
				'lib/vendor/**/*.markdown',
				'lib/vendor/**/*.md',
				'lib/vendor/**/.*.yml',
				'lib/vendor/**/.git',
				'lib/vendor/**/.gitattributes',
				'lib/vendor/**/.gitignore',
				'lib/vendor/**/build.xml',
				'lib/vendor/**/composer.json',
				'lib/vendor/**/composer.lock',
				'lib/vendor/**/examples',
				'lib/vendor/**/phpunit.*',
				'lib/vendor/**/test',
				'lib/vendor/**/Test',
				'lib/vendor/**/Tests',
				'lib/vendor/**/tests',
				'lib/vendor/autoload.php',
				'lib/vendor/bin',
				'lib/vendor/blobfolio/blob-common/lib/blobfolio/common/image.php',
				'lib/vendor/blobfolio/blob-common/lib/blobfolio/common/mime.php',
				'lib/vendor/blobfolio/blob-mimes',
				'lib/vendor/blobfolio/blob-phone',
				'lib/vendor/composer',
			]
		},
		// PHP.
		blobphp: {
			check: {
				src: process.cwd(),
				options: {
					colors: true,
					warnings: true
				}
			},
			fix: {
				src: process.cwd(),
				options: {
					fix: true
				},
			}
		},
		// Watch.
		watch: {
			php: {
				files: [
					'**/*.php',
					'lib/**/*.php'
				],
				tasks: ['php'],
				options: {
					spawn: false
				},
			},
		},
		// Notify.
		notify: {
			cleanup: {
				options: {
					title: "Composer garbage cleaned",
					message: "grunt-clean has successfully run"
				}
			},
		}
	});

	// These plugins provide necessary tasks.
	grunt.loadNpmTasks('grunt-contrib-watch');
	grunt.loadNpmTasks('grunt-contrib-clean');
	grunt.loadNpmTasks('grunt-notify');
	grunt.loadNpmTasks('grunt-blobfolio');

	grunt.registerTask('php', ['blobphp:check']);

	grunt.event.on('watch', function(action, filepath, target) {
		grunt.log.writeln(target + ': ' + filepath + ' has ' + action);
	});
};
