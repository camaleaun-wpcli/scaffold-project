<?php

namespace Camaleaun;

use WP_CLI;
use WP_CLI\Utils;
use WordPress;

class Scaffold_Command extends \WP_CLI_Command {

	private $has_wp_temp_core = false;

	private $uniqid;

	private $wp_temp_path;

	/**
	 * Generates project files.
	 *
	 * ## OPTIONS
	 *
	 * <slug>
	 * : The slug for the new project.
	 *
	 * [<path>]
	 * : Specify the path in which to create WordPress project. Defaults to
	 * current directory.
	 *
	 * [--name=<name>]
	 * : The name for the new project.
	 *
	 * [--type=<type>]
	 * : Type of the project do create. Defaults to 'theme'.
	 * ---
	 * default: theme
	 * options:
	 *   - theme
	 * ---
	 *
	 * [<download-url>]
	 * : Download directly from a provided URL instead of fetching the URL from the wordpress.org servers.
	 *
	 * [--path-core=<path>]
	 * : Specify the path in which to install WordPress. Defaults to current
	 * 'slug' as subdirectory of '.wp-cli/sites' path in home directory.
	 *
	 * [--locale=<locale>]
	 * : Select which language you want to download.
	 *
	 * [--version=<version>]
	 * : Select which version you want to download. Accepts a version number, 'latest' or 'nightly'.
	 *
	 * [--skip-content]
	 * : Download WP without the default themes and plugins.
	 *
	 * [--force-core-download]
	 * : Overwrites existing core files, if present.
	 *
	 * --dbname=<dbname>
	 * : Set the database name.
	 *
	 * --dbuser=<dbuser>
	 * : Set the database user.
	 *
	 * [--dbpass=<dbpass>]
	 * : Set the database user password.
	 *
	 * [--dbhost=<dbhost>]
	 * : Set the database host.
	 * ---
	 * default: localhost
	 * ---
	 *
	 * [--dbprefix=<dbprefix>]
	 * : Set the database table prefix.
	 * ---
	 * default: wp_
	 * ---
	 *
	 * [--dbcharset=<dbcharset>]
	 * : Set the database charset.
	 * ---
	 * default: utf8
	 * ---
	 *
	 * [--dbcollate=<dbcollate>]
	 * : Set the database collation.
	 * ---
	 * default:
	 * ---
	 *
	 * [--locale=<locale>]
	 * : Set the WPLANG constant. Defaults to $wp_local_package variable.
	 *
	 * [--extra-php]
	 * : If set, the command copies additional PHP code into wp-config.php from STDIN.
	 *
	 * [--skip-salts]
	 * : If set, keys and salts won't be generated, but should instead be passed via `--extra-php`.
	 *
	 * [--skip-check]
	 * : If set, the database connection is not checked.
	 *
	 * --url=<url>
	 * : The address of the new site.
	 *
	 * --title=<site-title>
	 * : The title of the new site.
	 *
	 * --admin_user=<username>
	 * : The name of the admin user.
	 *
	 * [--admin_password=<password>]
	 * : The password for the admin user. Defaults to randomly generated string.
	 *
	 * --admin_email=<email>
	 * : The email address for the admin user.
	 *
	 * [--skip-email]
	 * : Don't send an email notification to the new admin user.
	 *
	 * [--force]
	 * : Overwrite files that already exist.
	 *
	 * ## EXAMPLES
	 *
	 *     # Generate a theme with slug "sample
	 *     $ wp scaffold project sample
	 *     Success: Created project 'sample'.
	 *
	 * @when  before_wp_load
	 *
	 * @alias init
	 */
	public function __invoke( $args, $assoc_args ) {
		list( $slug ) = $args;

		// $this->eval_in_temp_wp( "echo sanitize_title( '$slug' );" );
		// die;
		// $this->load_temp_wordpress();
		// die;

		// $slug = $this->sanitize_title( $slug );
		// WP_CLI::log( $slug );
		// die;

		if ( ! preg_match( '/^[a-z_]\w+$/i', str_replace( '-', '_', $slug ) ) ) {
			WP_CLI::error( 'Invalid project slug specified. Project slugs can only contain letters, numbers, underscores and hyphens, and can only start with a letter or underscore.' );
		}

		// var_dump( $slug );
		$project_dir = \WP_CLI\Utils\get_flag_value( $args, 1, $slug );
		if ( $project_dir === $slug ) {
			$project_dir = realpath( dirname( $project_dir ) ) . "/$slug";
		} else {
			$project_dir = realpath( $project_dir );
		}

		$force = \WP_CLI\Utils\get_flag_value( $assoc_args, 'force', false );

		$error_msg = $this->check_dir( $project_dir, $assoc_args  );
		if ( ! empty( $error_msg ) ) {
			WP_CLI::error( $error_msg );
		}

		$path_core = \WP_CLI\Utils\get_flag_value( $assoc_args, 'path-core' );
		$cmd = 'core download --path=%s --force';
		WP_CLI::runcommand( \WP_CLI\Utils\esc_cmd( $cmd, $path_core ) );

		file_put_contents( "$project_dir/wp-cli.local.yml", "path: $path_core\n" );

		$dbname = \WP_CLI\Utils\get_flag_value( $assoc_args, 'dbname' );
		$dbuser = \WP_CLI\Utils\get_flag_value( $assoc_args, 'dbuser' );
		$cmd = 'config create --dbname=%s --dbuser=%s --path=%s --force';
		WP_CLI::runcommand( \WP_CLI\Utils\esc_cmd( $cmd, $dbname, $dbuser, $path_core ) );

		$cmd = 'db create --path=%s';
		WP_CLI::runcommand( \WP_CLI\Utils\esc_cmd( $cmd, $path_core ) );

		$url         = \WP_CLI\Utils\get_flag_value( $assoc_args, 'url' );
		$title       = \WP_CLI\Utils\get_flag_value( $assoc_args, 'title' );
		$admin_user  = \WP_CLI\Utils\get_flag_value( $assoc_args, 'admin_user' );
		$admin_email = \WP_CLI\Utils\get_flag_value( $assoc_args, 'admin_email' );
		$cmd = 'core install --url=%s --title=%s --admin_user=%s --admin_email=%s --skip-email --path=%s';
		WP_CLI::runcommand( \WP_CLI\Utils\esc_cmd( $cmd, $url, $title, $admin_user, $admin_email, $path_core ) );

		$cmd = 'scaffold underscores %s --path=%s --activate --force';
		WP_CLI::runcommand( \WP_CLI\Utils\esc_cmd( $cmd, $slug, $path_core ) );

		$this->mv_theme( "$path_core/wp-content/themes/$slug", $project_dir );

		/*$project_args       = $args;
		$project_assoc_args = $assoc_args;

		$args       = array(
			'slug' => $slug,
		);
		$assoc_args = array(
			// 'path'  => $project_dir,
			'force' => $force,
		);

		unset( $assoc_args['theme'] );

		WP_CLI::run_command(
			array_merge(
				array(
					'scaffold',
					'underscores',
				),
				array_values( $args),
			),
			$assoc_args
		);*/

		WP_CLI::success( "Created project '$slug'." );
	}

	/**
	 * Generates project files.
	 *
	 * ## OPTIONS
	 *
	 * [<slug>]
	 * : The slug for the new project.
	 *
	 * [<path>]
	 * : Specify the path in which to create WordPress project. Defaults to
	 * current directory.
	 *
	 * [--name=<name>]
	 * : The name for the new project.
	 *
	 * [--type=<type>]
	 * : Type of the project do create. Defaults to 'theme'.
	 * ---
	 * default: theme
	 * options:
	 *   - theme
	 * ---
	 *
	 * [<download-url>]
	 * : Download directly from a provided URL instead of fetching the URL from the wordpress.org servers.
	 *
	 * [--path-core=<path>]
	 * : Specify the path in which to install WordPress. Defaults to current
	 * 'slug' as subdirectory of '.wp-cli/sites' path in home directory.
	 *
	 * [--locale=<locale>]
	 * : Select which language you want to download.
	 *
	 * [--version=<version>]
	 * : Select which version you want to download. Accepts a version number, 'latest' or 'nightly'.
	 *
	 * [--skip-content]
	 * : Download WP without the default themes and plugins.
	 *
	 * [--force-core-download]
	 * : Overwrites existing core files, if present.
	 *
	 * [--dbname=<dbname>]
	 * : Set the database name.
	 *
	 * [--dbuser=<dbuser>]
	 * : Set the database user.
	 *
	 * [--dbpass=<dbpass>]
	 * : Set the database user password.
	 *
	 * [--dbhost=<dbhost>]
	 * : Set the database host.
	 * ---
	 * default: localhost
	 * ---
	 *
	 * [--dbprefix=<dbprefix>]
	 * : Set the database table prefix.
	 * ---
	 * default: wp_
	 * ---
	 *
	 * [--dbcharset=<dbcharset>]
	 * : Set the database charset.
	 * ---
	 * default: utf8
	 * ---
	 *
	 * [--dbcollate=<dbcollate>]
	 * : Set the database collation.
	 * ---
	 * default:
	 * ---
	 *
	 * [--locale=<locale>]
	 * : Set the WPLANG constant. Defaults to $wp_local_package variable.
	 *
	 * [--extra-php]
	 * : If set, the command copies additional PHP code into wp-config.php from STDIN.
	 *
	 * [--skip-salts]
	 * : If set, keys and salts won't be generated, but should instead be passed via `--extra-php`.
	 *
	 * [--skip-check]
	 * : If set, the database connection is not checked.
	 *
	 * [--url=<url>]
	 * : The address of the new site.
	 *
	 * [--title=<site-title>]
	 * : The title of the new site.
	 *
	 * [--admin_user=<username>]
	 * : The name of the admin user.
	 *
	 * [--admin_password=<password>]
	 * : The password for the admin user. Defaults to randomly generated string.
	 *
	 * [--admin_email=<email>]
	 * : The email address for the admin user.
	 *
	 * [--skip-email]
	 * : Don't send an email notification to the new admin user.
	 *
	 * [--force]
	 * : Overwrite files that already exist.
	 *
	 * ## EXAMPLES
	 *
	 *     # Generate a project with prompts
	 *     $ wp init
	 *     Success: Created project 'sample'.
	 *
	 * @when  before_wp_load
	 *
	 * @alias scaffold project
	 */
	public function init( $args, $assoc_args ) {
		if ( \WP_CLI::get_config( 'url' ) ) {
			$assoc_args['url']  = \WP_CLI::get_config( 'url' );
		}
		$slug        = \WP_CLI\Utils\get_flag_value( $args, 0, false );
		$name        = \WP_CLI\Utils\get_flag_value( $assoc_args, 'name', false );
		$path_core   = \WP_CLI\Utils\get_flag_value( $assoc_args, 'path-core', false );
		$dbname      = \WP_CLI\Utils\get_flag_value( $assoc_args, 'dbname', false );
		$dbuser      = \WP_CLI\Utils\get_flag_value( $assoc_args, 'dbuser', false );
		$url         = \WP_CLI\Utils\get_flag_value( $assoc_args, 'url', false );
		$title       = \WP_CLI\Utils\get_flag_value( $assoc_args, 'title', false );
		$admin_user  = \WP_CLI\Utils\get_flag_value( $assoc_args, 'admin_user', false );
		$admin_email = \WP_CLI\Utils\get_flag_value( $assoc_args, 'admin_email', false );

		if ( false === $slug && false === $name && false === $path_core && false === $dbname && false === $url && false === $title && false === $admin_user && false === $admin_email ) {
			WP_CLI::log( "This utility will walk you through creating a WordPress project.\nIt only covers the most common items, and tries to guess sensible defaults.\n" );
			WP_CLI::log( "See `wp help init` for definitive documentation on these fields\nand exactly what they do.\n" );
			WP_CLI::log( 'Press ^C at any time to quit.' );
		}

		$default_slug = false;
		if ( empty( $this->scandir( \getcwd(), true ) ) ) {
			$default_slug = \WP_CLI\Utils\basename( \getcwd() );
		}
		if ( false === $slug ) {
			do {
				$slug = \cli\prompt(
					'project slug',
					$default = $default_slug,
					$marker  = ': '
				);
			} while ( false );
		}

		$args = array(
			'slug' => $slug,
		);

		if ( false === $name ) {
			do {
				$name = \cli\prompt(
					'project name',
					$default = ucfirst( $slug ),
					$marker  = ': '
				);
			} while ( false );
		}
		$assoc_args['name'] = $name;

		$home            = Utils\get_home_dir();
		$wpcli_sites_dir = "$home/.wp-cli/sites";
		$this->check_dir( $wpcli_sites_dir, true );

		$path_core_default = $wpcli_sites_dir . "/$slug";
		if ( false === $path_core ) {
			do {
				$path_core = \cli\prompt(
					'path to core',
					$default = $path_core_default,
					$marker  = ': '
				);
			} while ( false );
		}
		$assoc_args['path_core'] = $path_core;

		if ( false === $dbname ) {
			do {
				$dbname = \cli\prompt(
					'dbname',
					$default = $slug,
					$marker  = ': '
				);
			} while ( false );
		}
		$assoc_args['dbname'] = $dbname;

		if ( false === $dbuser ) {
			do {
				$dbuser = \cli\prompt(
					'dbuser',
					$default = 'root',
					$marker  = ': '
				);
			} while ( false );
		}
		$assoc_args['dbuser'] = $dbuser;

		if ( false === $url ) {
			do {
				$url = \cli\prompt(
					'url',
					$default = 'localhost',
					$marker  = ': '
				);
			} while ( false );
		}
		$url               = strtolower( $url );
		$assoc_args['url'] = $url;

		if ( false === $title ) {
			do {
				$title = \cli\prompt(
					'title',
					$default = $name,
					$marker  = ': '
				);
			} while ( false );
		}
		$assoc_args['title'] = $title;

		if ( false === $admin_user ) {
			do {
				$admin_user = \cli\prompt(
					'admin_user',
					$default = false,
					$marker  = ': '
				);
			} while ( false );
		}
		$admin_user               = strtolower( $admin_user );
		$assoc_args['admin_user'] = $admin_user;

		$admin_email_default = false;
		if ( preg_match( '/[a-z0-9.-]+\.[a-z]{2,}$/', $url ) ) {
			$admin_email_default = "$admin_user@$url";
		}
		if ( false === $admin_email ) {
			do {
				$admin_email = \cli\prompt(
					'admin_email',
					$default = $admin_email_default,
					$marker  = ': '
				);
			} while ( false );
		}
		$assoc_args['admin_email'] = $admin_email;

		$cmd = 'scaffold project %s --path-core=%s --dbname=%s --dbuser=%s --url=%s --title=%s --admin_user=%s --admin_email=%s'; // --name=%s
		WP_CLI::runcommand( \WP_CLI\Utils\esc_cmd( $cmd, $slug, $path_core, $dbname, $dbuser, $url, $title, $admin_user, $admin_email ) ); // , $name
	}

	/**
	 * Check project dir
	 *
	 * @param string $dir   The directory to check.
	 * @param array  $force Ignore not empty.
	 *
	 * @return null|string Returns null on success, error message on error.
	 */
	private function check_dir( $dir, $force ) {
		if ( file_exists( $dir ) && ! is_dir( $dir ) ) {
			return 'Provided directory is not a directory.';
		} elseif ( ! is_dir( $dir ) ) {
			if ( ! is_writable( dirname( $dir ) ) ) {
				return "Insufficient permission to create directory '$dir'.";
			}

			WP_CLI::log( "Creating directory '$dir'." );
			if ( ! @mkdir( $dir, 0777, true /*recursive*/ ) ) {
				$error = error_get_last();
				return "Failed to create directory '$dir': {$error['message']}.";
			}
		} elseif ( '...' !== implode( scandir( $dir ) ) && ! $force ) {
			// WP_CLI::log( $dir );
			if ( ! $force ) {
				WP_CLI::warning( "'$dir' is not empty. Use --force for ignore this without ask." );
				do {
					$answer = \cli\prompt(
						'Use this not empty directory?',
						$default = 'Y/n',
						$marker  = ': '
					);
				} while ( ! in_array( strtolower( $answer[0] ), array( 'y', 'n' ), true ) );
				if ( 'n' === strtolower( $answer ) ) {
					return "Aborted project creation because '$dir' is not empty.";
				}
			}
		}

		if ( ! is_writable( $dir ) ) {
			return "'$dir' is not writable by current user.";
		}

		// Success.
		return null;
	}

	/**
	 * List files and directories inside the specified path
	 *
	 * @param  string     $directory        The directory that will be scanned.
	 * @param  boolean    $ignore_only_dots Ignore marks to current '.' and parent '..' directories.
	 * @return array|bool
	 */
	private function scandir( $directory, $ignore_only_dots = false ) {
		$contents = scandir( $directory );
		if ( true === $ignore_only_dots ) {
			$contents = array_values(
				array_diff(
					$contents,
					array( '.', '..' )
				)
			);
		}
		return $contents;
	}

	/**
	 * Removes directory
	 *
	 * @param  string  $dirname   Path to the directory.
	 * @param  boolean $recursive Remove directories and their contents recursively.
	 * @return bool
	 */
	private function rmdir( $dirname, $recursive = false ) {
		$files = $this->scandir( $dirname, true );
		foreach ( $files as $file ) {
			if ( is_dir( "$dirname/$file" ) ) {
				$this->rmdir( "$dirname/$file", true );
			} else {
				unlink( "$dirname/$file" );
			}
		}
		return rmdir( $dirname );
	}

	private function mv_theme( $source, $target ) {
		passthru( "mv $source $target" );
		// $files  = $this->scandir( $source, true );
		// foreach ( $files as $file ) {
		// 	passthru( "mv $source/$file $target" );
		// }
		// if ( \file_exists( $source ) && ! is_dir( $source ) ) {
		// 	unlink( $source );
		// } elseif ( \file_exists( $source ) && is_dir( $source ) ) {
		// 	$this->rmdir( $source, true );
		// }
		passthru( "ln -s $target $source" );

		// TODO: Use PHP.
		// $source = rtrim( $source, '/\\*' );
		// foreach ( $files as $file ) {
		// 	if ( is_dir( "$source/$file" ) ) {
		// 		$this->check_dir( "$target/$file", true );
		// 		$this->mv( "$source/$file", "$target/$file" );
		// 	} else {
		// 		unlink( "$dirname/$file" );
		// 	}
		// }
		// return $this->rmdir( $dirname );
	}

	/**
	 * Test ABSPATH
	 *
	 * @when  before_wp_load
	 */
	public function test_abspath( $args, $assoc_args ) {
		// echo 'ABS'
		WP_CLI::log( 'ABSPATH' );
		WP_CLI::log( ABSPATH );
	}

	/**
	 * Sanitizes a title.
	 *
	 * @param  string $title The string to be sanitized.
	 *
	 * @return string        The sanitized string.
	 */
	private function sanitize_title( $title ) {
		return strtolower(
			preg_replace( '/\s+/', '-', $this->remove_accents( $title ) )
		);
		/*if ( false === $this->has_wp_temp_core ) {
			$this->download_temp_wordpress();
		}
		if ( ! \function_exists( 'apply_filters' ) ) {
			require $this->wp_temp_path . '/wp-includes/plugin.php';
		}
		if ( ! \function_exists( 'sanitize_title' ) ) {
			require $this->wp_temp_path . '/wp-includes/formatting.php';
			// require $this->wp_temp_path . '/wp-load.php';
		}
		if ( ! \function_exists( 'mbstring_binary_safe_encoding' ) ) {
			require $this->wp_temp_path . '/wp-includes/functions.php';
		}
		if ( ! \function_exists( 'get_locale' ) ) {
			require $this->wp_temp_path . '/wp-includes/l10n.php';
		}
		if ( ! \function_exists( 'is_multisite' ) ) {
			require $this->wp_temp_path . '/wp-includes/load.php';
		}
		if ( ! \function_exists( 'wp_get_cache' ) ) {
			global $wp_object_cache;
			require $this->wp_temp_path . '/wp-includes/class-wp-object-cache.php';
			$wp_object_cache = new \WP_Object_Cache();
			require $this->wp_temp_path . '/wp-includes/cache.php';
		}
		return \sanitize_title( $title );*/
	}

	/**
	 * Download a temp WordPress
	 */
	private function download_temp_wordpress() {
		$this->uniqid       = uniqid( 'wp_' );
		$this->wp_temp_path = Utils\get_temp_dir() . $this->uniqid;

		$cmd = 'core download --path=%s --force --quiet';
		WP_CLI::runcommand( sprintf( $cmd, $this->wp_temp_path ) );

		define( 'WPINC', ltrim( $this->wp_temp_path, '/' ) . '/wp-includes' );

		$this->has_wp_temp_core = true;

		register_shutdown_function(
			function () {
				if ( file_exists( $this->wp_temp_path ) ) {
					$this->rmdir( $this->wp_temp_path, true );
				}
			}
		);
	}

	/**
	 * Load a temp WordPress
	 */
	private function load_temp_wordpress() {
		$this->download_temp_wordpress();

		$cmd  = 'config create --dbname=%s --path=%s --skip-check --force';
		$cmd .= ' --quiet';
		WP_CLI::runcommand(
			sprintf( $cmd, $this->uniqid, $this->wp_temp_path )
		);

		$cmd = 'db create --path=%s --quiet';
		WP_CLI::runcommand( sprintf( $cmd, $this->wp_temp_path ) );

		$cmd  = 'core install --url=localhost --title=%1$s --admin_user=%1$s';
		$cmd .= ' --admin_email=loc@lho.st --path=%2$s --quiet';
		WP_CLI::runcommand(
			sprintf( $cmd, $this->uniqid, $this->wp_temp_path )
		);

		register_shutdown_function(
			function () use ( $temp ) {
				if ( file_exists( $temp ) ) {
					$cmd  = 'db drop --yes --path=%2$s';
					WP_CLI::runcommand( sprintf( $cmd, $this->wp_temp_path ) );
				}
			}
		);
	}

	/**
	 * Executes arbitrary PHP code.
	 */
	private function eval_in_temp_wp( $php_code ) {
		$uniqid = 'wp_temp';
		// $temp = Utils\get_temp_dir() . uniqid( 'wp_' );
		$temp = Utils\get_temp_dir() . $uniqid;

		// WP_CLI::debug( "Creating a temporary WordPress installation in '$temp'." );
		// if ( ! @mkdir( $temp, 0777, true recursive ) ) {
		// 	$error = error_get_last();
		// 	WP_CLI::error( "Failed to create a temp directory: {$error['message']}." );
		// }

		$cmd = 'core download --path=%s --force --quiet';
		// WP_CLI::log( Utils\esc_cmd( $cmd, $temp ) );
		// WP_CLI::launch( Utils\esc_cmd( $cmd, $temp ) );
		WP_CLI::runcommand( sprintf( $cmd, $temp ) );

		$cmd  = 'config create --dbname=%s --path=%s --skip-check --force';
		$cmd .= ' --quiet';
		// WP_CLI::log( Utils\esc_cmd( $cmd, $uniqid, $temp ) );
		// WP_CLI::launch( Utils\esc_cmd( $cmd, $uniqid, $temp ) );
		WP_CLI::runcommand( sprintf( $cmd, $uniqid, $temp ) );

		$cmd = 'db create --path=%s --quiet';
		// WP_CLI::log( Utils\esc_cmd( $cmd, $temp ) );
		// WP_CLI::runcommand( Utils\esc_cmd( $cmd, $temp ) );
		WP_CLI::runcommand( sprintf( $cmd, $temp ) );

		$cmd  = 'core install --url=localhost --title=%s --admin_user=%s';
		$cmd .= ' --admin_email=loc@lho.st --path=%s --quiet';
		// WP_CLI::log( Utils\esc_cmd( $cmd, $uniqid, $uniqid, $temp ) );
		// WP_CLI::launch( Utils\esc_cmd( $cmd, $uniqid, $uniqid, $temp ) );
		WP_CLI::runcommand( sprintf( $cmd, $uniqid, $uniqid, $temp ) );
		// WP_CLI::log( sprintf( $cmd, $uniqid , $uniqid, $temp ) );

		$cmd = 'eval %s --path=%s';
		WP_CLI::log( Utils\esc_cmd( $cmd, $php_code, $temp ) );
		// WP_CLI::launch( Utils\esc_cmd( $cmd, $php_code, $temp ) );
		WP_CLI::runcommand( Utils\esc_cmd( $cmd, $php_code, $temp ) );
		// WP_CLI::log( sprintf( $cmd, $php_code, $temp ) );

		// WP_CLI::run_command();
		// WP_CLI::runcommand( 'test-abspath --path=/tmp/wp_temp' );
		// WP_CLI::launch( '' );
	    // $cmd = "tar xz --strip-components=1 --directory=%s -f $tarball";
	    // WP_CLI::launch( Utils\esc_cmd( $cmd, $dest ) );


		// $uniqid = 'wp_temp';
		// $temp = Utils\get_temp_dir() . uniqid( 'wp_' );
		// $temp = Utils\get_temp_dir() . $uniqid;
		die;
	}

	/**
	 * Load a temp WordPress
	 */
	public function xload_temp_wordpress() {
		$uniqid = 'wp_temp';
		// $temp = Utils\get_temp_dir() . uniqid( 'wp_' );
		$temp = Utils\get_temp_dir() . $uniqid;
		// WP_CLI::debug( "Creating a temporary WordPress installation in '$temp'." );
		//if ( ! is_dir( $temp ) && ! @mkdir( $temp, 0777, true /*recursive*/ ) ) {
		// 	$error = error_get_last();
		// 	WP_CLI::error( "Failed to create a temp directory: {$error['message']}." );
		// }
		// register_shutdown_function(
		// 	function () use ( $temp ) {
		// 		if ( file_exists( $temp ) ) {
		// 			$this->rmdir( $temp, true );
		// 		}
		// 	}
		// );
		// var_dump( defined( 'ABSPATH' ) ? ABSPATH : null );
		// die;
		WP_CLI::runcommand( "core download --path=$temp --force --quiet" );
		WP_CLI::runcommand( "config create --dbname=$uniqid --path=$temp --force --quiet" );
		// WP_CLI::get_runner()->load_wordpress();
		// eval( WP_CLI::get_runner()->get_wp_config_code( "$temp/wp-config.php" ) ); // phpcs:ignore Squiz.PHP.Eval.Discouraged
		// eval( $this->get_wp_config_code( "$temp/wp-config.php" ) ); // phpcs:ignore Squiz.PHP.Eval.Discouraged
		// WP_CLI::log( DB_NAME );
		// WP_CLI::line( $temp . '/wp-load.php' );
		// require $temp . '/wp-load.php';
		// WP_CLI::line( 'WordPress downloaded' );

		static $wp_cli_is_loaded;
		// Globals not explicitly globalized in WordPress
		global $site_id, $wpdb, $public, $current_site, $current_blog, $path, $shortcode_tags;

		if ( ! empty( $wp_cli_is_loaded ) ) {
			return;
		}

		$wp_cli_is_loaded = true;

		WP_CLI::debug( 'Begin temp WordPress load', 'bootstrap' );
		// WP_CLI::do_hook( 'before_wp_load' );

		// $this->check_wp_version();

		$wp_config_path = "$temp/wp-config.php";

		WP_CLI::debug( 'wp-config.php path: ' . $wp_config_path, 'bootstrap' );

		// Load wp-config.php code, in the global scope
		$wp_cli_original_defined_vars = get_defined_vars();

		eval( $this->get_wp_config_code( $wp_config_path ) ); // phpcs:ignore Squiz.PHP.Eval.Discouraged

		foreach ( get_defined_vars() as $key => $var ) {
			if ( array_key_exists( $key, $wp_cli_original_defined_vars ) || 'wp_cli_original_defined_vars' === $key ) {
				continue;
			}

			// phpcs:ignore PHPCompatibility.Variables.ForbiddenGlobalVariableVariable.NonBareVariableFound
			global ${$key};
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
			${$key} = $var;
		}

		// $this->maybe_update_url_from_domain_constant();
		// WP_CLI::do_hook( 'after_wp_config_load' );
		// $this->do_early_invoke( 'after_wp_config_load' );

		// Prevent error notice from wp_guess_url() when core isn't installed
		// if ( $this->cmd_starts_with( array( 'core', 'is-installed' ) )
		// 	&& ! defined( 'COOKIEHASH' ) ) {
		define( 'COOKIEHASH', md5( 'wp-cli' ) );
		// }

		// Load WP-CLI utilities
		require WP_CLI_ROOT . '/php/utils-wp.php';

		// Set up WordPress bootstrap actions and filters
		$this->setup_bootstrap_hooks();

		// Load Core, mu-plugins, plugins, themes etc.
		if ( Utils\wp_version_compare( '4.6-alpha-37575', '>=' ) ) {
			require ABSPATH . 'wp-settings.php';
		} else {
			require WP_CLI_ROOT . '/php/wp-settings-cli.php';
		}

		// Fix memory limit. See https://core.trac.wordpress.org/ticket/14889
		// phpcs:ignore WordPress.PHP.IniSet.memory_limit_Blacklisted -- This is perfectly fine for CLI usage.
		ini_set( 'memory_limit', -1 );

		// Load all the admin APIs, for convenience
		require ABSPATH . 'wp-admin/includes/admin.php';

		add_filter(
			'filesystem_method',
			static function () {
				return 'direct';
			},
			99
		);

		WP_CLI::debug( 'Loaded temp WordPress', 'bootstrap' );
		// WP_CLI::do_hook( 'after_wp_load' );
	}

	/**
	 * Returns wp-config.php code, skipping the loading of wp-settings.php
	 *
	 * @param string $wp_config_file_path. Config file path. If left empty, it tries to
	 * locate the wp-config.php file automatically.
	 *
	 * @return string
	 */
	public function get_wp_config_code( $wp_config_path ) {
		$wp_config_code = explode( "\n", file_get_contents( $wp_config_path ) );

		$found_wp_settings = false;

		$lines_to_run = array();

		foreach ( $wp_config_code as $line ) {
			if ( preg_match( '/^\s*require.+wp-settings\.php/', $line ) ) {
				WP_CLI::log( 'found_wp_settings' );
				$found_wp_settings = true;
				continue;
			}
			WP_CLI::log( $line );

			$lines_to_run[] = $line;
		}

		if ( ! $found_wp_settings ) {
			WP_CLI::error( 'Strange wp-config.php file: wp-settings.php is not loaded directly.' );
		}

		$source = implode( "\n", $lines_to_run );
		$source = Utils\replace_path_consts( $source, $wp_config_path );
		return preg_replace( '|^\s*\<\?php\s*|', '', $source );
	}

	/**
	 * Set up hooks meant to run during the WordPress bootstrap process
	 */
	private function setup_bootstrap_hooks() {
		WP_CLI::add_wp_hook(
			'wp_die_handler',
			static function () {
				return '\WP_CLI\Utils\wp_die_handler';
			}
		);

		// Prevent code from performing a redirect
		WP_CLI::add_wp_hook( 'wp_redirect', 'WP_CLI\\Utils\\wp_redirect_handler' );

		WP_CLI::add_wp_hook(
			'nocache_headers',
			static function ( $headers ) {
				// WordPress might be calling nocache_headers() because of a dead db
				global $wpdb;
				if ( ! empty( $wpdb->error ) ) {
					Utils\wp_die_handler( $wpdb->error );
				}
				// Otherwise, WP might be calling nocache_headers() because WP isn't installed
				Utils\wp_not_installed();
				return $headers;
			}
		);

		WP_CLI::add_wp_hook(
			'setup_theme',
			static function () {
				// Polyfill is_customize_preview(), as it is needed by TwentyTwenty to
				// check for starter content.
				if ( ! function_exists( 'is_customize_preview' ) ) {
					function is_customize_preview() {
						return false;
					}
				}
			},
			0
		);

		// ALTERNATE_WP_CRON might trigger a redirect, which we can't handle
		if ( defined( 'ALTERNATE_WP_CRON' ) && ALTERNATE_WP_CRON ) {
			WP_CLI::add_wp_hook(
				'muplugins_loaded',
				static function () {
					remove_action( 'init', 'wp_cron' );
				}
			);
		}

		// Always permit operations against sites, regardless of status
		WP_CLI::add_wp_hook( 'ms_site_check', '__return_true' );

		// Always permit operations against WordPress, regardless of maintenance mode
		WP_CLI::add_wp_hook(
			'enable_maintenance_mode',
			static function () {
				return false;
			}
		);

		// Use our own debug mode handling instead of WP core
		WP_CLI::add_wp_hook(
			'enable_wp_debug_mode_checks',
			static function ( $ret ) {
				Utils\wp_debug_mode();
				return false;
			}
		);

		// Never load advanced-cache.php drop-in when WP-CLI is operating
		WP_CLI::add_wp_hook(
			'enable_loading_advanced_cache_dropin',
			static function () {
				return false;
			}
		);

		// The APC cache is not available on the command-line, so bail, to prevent cache poisoning
		WP_CLI::add_wp_hook(
			'muplugins_loaded',
			static function () {
				if ( $GLOBALS['_wp_using_ext_object_cache'] && class_exists( 'APC_Object_Cache' ) ) {
					WP_CLI::warning( 'Running WP-CLI while the APC object cache is activated can result in cache corruption.' );
					WP_CLI::confirm( 'Given the consequences, do you wish to continue?' );
				}
			},
			0
		);

		// Handle --user parameter
		if ( ! defined( 'WP_INSTALLING' ) ) {
			$config = \WP_CLI::get_config();
			WP_CLI::add_wp_hook(
				'init',
				static function () use ( $config ) {
					if ( isset( $config['user'] ) ) {
						$fetcher = new \WP_CLI\Fetchers\User();
						$user    = $fetcher->get_check( $config['user'] );
						wp_set_current_user( $user->ID );
					} else {
						add_action( 'init', 'kses_remove_filters', 11 );
					}
				},
				0
			);
		}

		// Avoid uncaught exception when using wp_mail() without defined $_SERVER['SERVER_NAME']
		WP_CLI::add_wp_hook(
			'wp_mail_from',
			static function ( $from_email ) {
				if ( 'wordpress@' === $from_email ) {
					$sitename = strtolower( Utils\parse_url( site_url(), PHP_URL_HOST ) );
					if ( substr( $sitename, 0, 4 ) === 'www.' ) {
						$sitename = substr( $sitename, 4 );
					}
					$from_email = 'wordpress@' . $sitename;
				}
				return $from_email;
			}
		);

		// Don't apply set_url_scheme in get_site_url()
		WP_CLI::add_wp_hook(
			'site_url',
			static function ( $url, $path, $scheme, $blog_id ) {
				if ( empty( $blog_id ) || ! is_multisite() ) {
					$url = get_option( 'siteurl' );
				} else {
					switch_to_blog( $blog_id );
					$url = get_option( 'siteurl' );
					restore_current_blog();
				}
				if ( $path && is_string( $path ) ) {
					$url .= '/' . ltrim( $path, '/' );
				}
				return $url;
			},
			0,
			4
		);

		// Set up hook for plugins and themes to conditionally add WP-CLI commands.
		WP_CLI::add_wp_hook(
			'init',
			static function () {
				do_action( 'cli_init' );
			}
		);
	}

	/**
	 * Checks to see if a string is utf8 encoded.
	 *
	 * NOTE: This function checks for 5-Byte sequences, UTF8
	 *       has Bytes Sequences with a maximum length of 4.
	 *
	 * @author bmorel at ssi dot fr (modified)
	 * @since 1.2.1
	 *
	 * @param string $str The string to be checked
	 * @return bool True if $str fits a UTF-8 model, false otherwise.
	 */
	private function seems_utf8( $str ) {
		$this->mbstring_binary_safe_encoding();
		$length = strlen( $str );
		$this->reset_mbstring_encoding();
		for ( $i = 0; $i < $length; $i++ ) {
			$c = ord( $str[ $i ] );
			if ( $c < 0x80 ) {
				$n = 0; // 0bbbbbbb
			} elseif ( ( $c & 0xE0 ) == 0xC0 ) {
				$n = 1; // 110bbbbb
			} elseif ( ( $c & 0xF0 ) == 0xE0 ) {
				$n = 2; // 1110bbbb
			} elseif ( ( $c & 0xF8 ) == 0xF0 ) {
				$n = 3; // 11110bbb
			} elseif ( ( $c & 0xFC ) == 0xF8 ) {
				$n = 4; // 111110bb
			} elseif ( ( $c & 0xFE ) == 0xFC ) {
				$n = 5; // 1111110b
			} else {
				return false; // Does not match any model.
			}
			for ( $j = 0; $j < $n; $j++ ) { // n bytes matching 10bbbbbb follow ?
				if ( ( ++$i == $length ) || ( ( ord( $str[ $i ] ) & 0xC0 ) != 0x80 ) ) {
					return false;
				}
			}
		}
		return true;
	}

	/**
	 * Converts all accent characters to ASCII characters.
	 *
	 * If there are no accent characters, then the string given is just returned.
	 *
	 * **Accent characters converted:**
	 *
	 * Currency signs:
	 *
	 * |   Code   | Glyph | Replacement |     Description     |
	 * | -------- | ----- | ----------- | ------------------- |
	 * | U+00A3   | ??     | (empty)     | British Pound sign  |
	 * | U+20AC   | ???     | E           | Euro sign           |
	 *
	 * Decompositions for Latin-1 Supplement:
	 *
	 * |  Code   | Glyph | Replacement |               Description              |
	 * | ------- | ----- | ----------- | -------------------------------------- |
	 * | U+00AA  | ??     | a           | Feminine ordinal indicator             |
	 * | U+00BA  | ??     | o           | Masculine ordinal indicator            |
	 * | U+00C0  | ??     | A           | Latin capital letter A with grave      |
	 * | U+00C1  | ??     | A           | Latin capital letter A with acute      |
	 * | U+00C2  | ??     | A           | Latin capital letter A with circumflex |
	 * | U+00C3  | ??     | A           | Latin capital letter A with tilde      |
	 * | U+00C4  | ??     | A           | Latin capital letter A with diaeresis  |
	 * | U+00C5  | ??     | A           | Latin capital letter A with ring above |
	 * | U+00C6  | ??     | AE          | Latin capital letter AE                |
	 * | U+00C7  | ??     | C           | Latin capital letter C with cedilla    |
	 * | U+00C8  | ??     | E           | Latin capital letter E with grave      |
	 * | U+00C9  | ??     | E           | Latin capital letter E with acute      |
	 * | U+00CA  | ??     | E           | Latin capital letter E with circumflex |
	 * | U+00CB  | ??     | E           | Latin capital letter E with diaeresis  |
	 * | U+00CC  | ??     | I           | Latin capital letter I with grave      |
	 * | U+00CD  | ??     | I           | Latin capital letter I with acute      |
	 * | U+00CE  | ??     | I           | Latin capital letter I with circumflex |
	 * | U+00CF  | ??     | I           | Latin capital letter I with diaeresis  |
	 * | U+00D0  | ??     | D           | Latin capital letter Eth               |
	 * | U+00D1  | ??     | N           | Latin capital letter N with tilde      |
	 * | U+00D2  | ??     | O           | Latin capital letter O with grave      |
	 * | U+00D3  | ??     | O           | Latin capital letter O with acute      |
	 * | U+00D4  | ??     | O           | Latin capital letter O with circumflex |
	 * | U+00D5  | ??     | O           | Latin capital letter O with tilde      |
	 * | U+00D6  | ??     | O           | Latin capital letter O with diaeresis  |
	 * | U+00D8  | ??     | O           | Latin capital letter O with stroke     |
	 * | U+00D9  | ??     | U           | Latin capital letter U with grave      |
	 * | U+00DA  | ??     | U           | Latin capital letter U with acute      |
	 * | U+00DB  | ??     | U           | Latin capital letter U with circumflex |
	 * | U+00DC  | ??     | U           | Latin capital letter U with diaeresis  |
	 * | U+00DD  | ??     | Y           | Latin capital letter Y with acute      |
	 * | U+00DE  | ??     | TH          | Latin capital letter Thorn             |
	 * | U+00DF  | ??     | s           | Latin small letter sharp s             |
	 * | U+00E0  | ??     | a           | Latin small letter a with grave        |
	 * | U+00E1  | ??     | a           | Latin small letter a with acute        |
	 * | U+00E2  | ??     | a           | Latin small letter a with circumflex   |
	 * | U+00E3  | ??     | a           | Latin small letter a with tilde        |
	 * | U+00E4  | ??     | a           | Latin small letter a with diaeresis    |
	 * | U+00E5  | ??     | a           | Latin small letter a with ring above   |
	 * | U+00E6  | ??     | ae          | Latin small letter ae                  |
	 * | U+00E7  | ??     | c           | Latin small letter c with cedilla      |
	 * | U+00E8  | ??     | e           | Latin small letter e with grave        |
	 * | U+00E9  | ??     | e           | Latin small letter e with acute        |
	 * | U+00EA  | ??     | e           | Latin small letter e with circumflex   |
	 * | U+00EB  | ??     | e           | Latin small letter e with diaeresis    |
	 * | U+00EC  | ??     | i           | Latin small letter i with grave        |
	 * | U+00ED  | ??     | i           | Latin small letter i with acute        |
	 * | U+00EE  | ??     | i           | Latin small letter i with circumflex   |
	 * | U+00EF  | ??     | i           | Latin small letter i with diaeresis    |
	 * | U+00F0  | ??     | d           | Latin small letter Eth                 |
	 * | U+00F1  | ??     | n           | Latin small letter n with tilde        |
	 * | U+00F2  | ??     | o           | Latin small letter o with grave        |
	 * | U+00F3  | ??     | o           | Latin small letter o with acute        |
	 * | U+00F4  | ??     | o           | Latin small letter o with circumflex   |
	 * | U+00F5  | ??     | o           | Latin small letter o with tilde        |
	 * | U+00F6  | ??     | o           | Latin small letter o with diaeresis    |
	 * | U+00F8  | ??     | o           | Latin small letter o with stroke       |
	 * | U+00F9  | ??     | u           | Latin small letter u with grave        |
	 * | U+00FA  | ??     | u           | Latin small letter u with acute        |
	 * | U+00FB  | ??     | u           | Latin small letter u with circumflex   |
	 * | U+00FC  | ??     | u           | Latin small letter u with diaeresis    |
	 * | U+00FD  | ??     | y           | Latin small letter y with acute        |
	 * | U+00FE  | ??     | th          | Latin small letter Thorn               |
	 * | U+00FF  | ??     | y           | Latin small letter y with diaeresis    |
	 *
	 * Decompositions for Latin Extended-A:
	 *
	 * |  Code   | Glyph | Replacement |                    Description                    |
	 * | ------- | ----- | ----------- | ------------------------------------------------- |
	 * | U+0100  | ??     | A           | Latin capital letter A with macron                |
	 * | U+0101  | ??     | a           | Latin small letter a with macron                  |
	 * | U+0102  | ??     | A           | Latin capital letter A with breve                 |
	 * | U+0103  | ??     | a           | Latin small letter a with breve                   |
	 * | U+0104  | ??     | A           | Latin capital letter A with ogonek                |
	 * | U+0105  | ??     | a           | Latin small letter a with ogonek                  |
	 * | U+01006 | ??     | C           | Latin capital letter C with acute                 |
	 * | U+0107  | ??     | c           | Latin small letter c with acute                   |
	 * | U+0108  | ??     | C           | Latin capital letter C with circumflex            |
	 * | U+0109  | ??     | c           | Latin small letter c with circumflex              |
	 * | U+010A  | ??     | C           | Latin capital letter C with dot above             |
	 * | U+010B  | ??     | c           | Latin small letter c with dot above               |
	 * | U+010C  | ??     | C           | Latin capital letter C with caron                 |
	 * | U+010D  | ??     | c           | Latin small letter c with caron                   |
	 * | U+010E  | ??     | D           | Latin capital letter D with caron                 |
	 * | U+010F  | ??     | d           | Latin small letter d with caron                   |
	 * | U+0110  | ??     | D           | Latin capital letter D with stroke                |
	 * | U+0111  | ??     | d           | Latin small letter d with stroke                  |
	 * | U+0112  | ??     | E           | Latin capital letter E with macron                |
	 * | U+0113  | ??     | e           | Latin small letter e with macron                  |
	 * | U+0114  | ??     | E           | Latin capital letter E with breve                 |
	 * | U+0115  | ??     | e           | Latin small letter e with breve                   |
	 * | U+0116  | ??     | E           | Latin capital letter E with dot above             |
	 * | U+0117  | ??     | e           | Latin small letter e with dot above               |
	 * | U+0118  | ??     | E           | Latin capital letter E with ogonek                |
	 * | U+0119  | ??     | e           | Latin small letter e with ogonek                  |
	 * | U+011A  | ??     | E           | Latin capital letter E with caron                 |
	 * | U+011B  | ??     | e           | Latin small letter e with caron                   |
	 * | U+011C  | ??     | G           | Latin capital letter G with circumflex            |
	 * | U+011D  | ??     | g           | Latin small letter g with circumflex              |
	 * | U+011E  | ??     | G           | Latin capital letter G with breve                 |
	 * | U+011F  | ??     | g           | Latin small letter g with breve                   |
	 * | U+0120  | ??     | G           | Latin capital letter G with dot above             |
	 * | U+0121  | ??     | g           | Latin small letter g with dot above               |
	 * | U+0122  | ??     | G           | Latin capital letter G with cedilla               |
	 * | U+0123  | ??     | g           | Latin small letter g with cedilla                 |
	 * | U+0124  | ??     | H           | Latin capital letter H with circumflex            |
	 * | U+0125  | ??     | h           | Latin small letter h with circumflex              |
	 * | U+0126  | ??     | H           | Latin capital letter H with stroke                |
	 * | U+0127  | ??     | h           | Latin small letter h with stroke                  |
	 * | U+0128  | ??     | I           | Latin capital letter I with tilde                 |
	 * | U+0129  | ??     | i           | Latin small letter i with tilde                   |
	 * | U+012A  | ??     | I           | Latin capital letter I with macron                |
	 * | U+012B  | ??     | i           | Latin small letter i with macron                  |
	 * | U+012C  | ??     | I           | Latin capital letter I with breve                 |
	 * | U+012D  | ??     | i           | Latin small letter i with breve                   |
	 * | U+012E  | ??     | I           | Latin capital letter I with ogonek                |
	 * | U+012F  | ??     | i           | Latin small letter i with ogonek                  |
	 * | U+0130  | ??     | I           | Latin capital letter I with dot above             |
	 * | U+0131  | ??     | i           | Latin small letter dotless i                      |
	 * | U+0132  | ??     | IJ          | Latin capital ligature IJ                         |
	 * | U+0133  | ??     | ij          | Latin small ligature ij                           |
	 * | U+0134  | ??     | J           | Latin capital letter J with circumflex            |
	 * | U+0135  | ??     | j           | Latin small letter j with circumflex              |
	 * | U+0136  | ??     | K           | Latin capital letter K with cedilla               |
	 * | U+0137  | ??     | k           | Latin small letter k with cedilla                 |
	 * | U+0138  | ??     | k           | Latin small letter Kra                            |
	 * | U+0139  | ??     | L           | Latin capital letter L with acute                 |
	 * | U+013A  | ??     | l           | Latin small letter l with acute                   |
	 * | U+013B  | ??     | L           | Latin capital letter L with cedilla               |
	 * | U+013C  | ??     | l           | Latin small letter l with cedilla                 |
	 * | U+013D  | ??     | L           | Latin capital letter L with caron                 |
	 * | U+013E  | ??     | l           | Latin small letter l with caron                   |
	 * | U+013F  | ??     | L           | Latin capital letter L with middle dot            |
	 * | U+0140  | ??     | l           | Latin small letter l with middle dot              |
	 * | U+0141  | ??     | L           | Latin capital letter L with stroke                |
	 * | U+0142  | ??     | l           | Latin small letter l with stroke                  |
	 * | U+0143  | ??     | N           | Latin capital letter N with acute                 |
	 * | U+0144  | ??     | n           | Latin small letter N with acute                   |
	 * | U+0145  | ??     | N           | Latin capital letter N with cedilla               |
	 * | U+0146  | ??     | n           | Latin small letter n with cedilla                 |
	 * | U+0147  | ??     | N           | Latin capital letter N with caron                 |
	 * | U+0148  | ??     | n           | Latin small letter n with caron                   |
	 * | U+0149  | ??     | n           | Latin small letter n preceded by apostrophe       |
	 * | U+014A  | ??     | N           | Latin capital letter Eng                          |
	 * | U+014B  | ??     | n           | Latin small letter Eng                            |
	 * | U+014C  | ??     | O           | Latin capital letter O with macron                |
	 * | U+014D  | ??     | o           | Latin small letter o with macron                  |
	 * | U+014E  | ??     | O           | Latin capital letter O with breve                 |
	 * | U+014F  | ??     | o           | Latin small letter o with breve                   |
	 * | U+0150  | ??     | O           | Latin capital letter O with double acute          |
	 * | U+0151  | ??     | o           | Latin small letter o with double acute            |
	 * | U+0152  | ??     | OE          | Latin capital ligature OE                         |
	 * | U+0153  | ??     | oe          | Latin small ligature oe                           |
	 * | U+0154  | ??     | R           | Latin capital letter R with acute                 |
	 * | U+0155  | ??     | r           | Latin small letter r with acute                   |
	 * | U+0156  | ??     | R           | Latin capital letter R with cedilla               |
	 * | U+0157  | ??     | r           | Latin small letter r with cedilla                 |
	 * | U+0158  | ??     | R           | Latin capital letter R with caron                 |
	 * | U+0159  | ??     | r           | Latin small letter r with caron                   |
	 * | U+015A  | ??     | S           | Latin capital letter S with acute                 |
	 * | U+015B  | ??     | s           | Latin small letter s with acute                   |
	 * | U+015C  | ??     | S           | Latin capital letter S with circumflex            |
	 * | U+015D  | ??     | s           | Latin small letter s with circumflex              |
	 * | U+015E  | ??     | S           | Latin capital letter S with cedilla               |
	 * | U+015F  | ??     | s           | Latin small letter s with cedilla                 |
	 * | U+0160  | ??     | S           | Latin capital letter S with caron                 |
	 * | U+0161  | ??     | s           | Latin small letter s with caron                   |
	 * | U+0162  | ??     | T           | Latin capital letter T with cedilla               |
	 * | U+0163  | ??     | t           | Latin small letter t with cedilla                 |
	 * | U+0164  | ??     | T           | Latin capital letter T with caron                 |
	 * | U+0165  | ??     | t           | Latin small letter t with caron                   |
	 * | U+0166  | ??     | T           | Latin capital letter T with stroke                |
	 * | U+0167  | ??     | t           | Latin small letter t with stroke                  |
	 * | U+0168  | ??     | U           | Latin capital letter U with tilde                 |
	 * | U+0169  | ??     | u           | Latin small letter u with tilde                   |
	 * | U+016A  | ??     | U           | Latin capital letter U with macron                |
	 * | U+016B  | ??     | u           | Latin small letter u with macron                  |
	 * | U+016C  | ??     | U           | Latin capital letter U with breve                 |
	 * | U+016D  | ??     | u           | Latin small letter u with breve                   |
	 * | U+016E  | ??     | U           | Latin capital letter U with ring above            |
	 * | U+016F  | ??     | u           | Latin small letter u with ring above              |
	 * | U+0170  | ??     | U           | Latin capital letter U with double acute          |
	 * | U+0171  | ??     | u           | Latin small letter u with double acute            |
	 * | U+0172  | ??     | U           | Latin capital letter U with ogonek                |
	 * | U+0173  | ??     | u           | Latin small letter u with ogonek                  |
	 * | U+0174  | ??     | W           | Latin capital letter W with circumflex            |
	 * | U+0175  | ??     | w           | Latin small letter w with circumflex              |
	 * | U+0176  | ??     | Y           | Latin capital letter Y with circumflex            |
	 * | U+0177  | ??     | y           | Latin small letter y with circumflex              |
	 * | U+0178  | ??     | Y           | Latin capital letter Y with diaeresis             |
	 * | U+0179  | ??     | Z           | Latin capital letter Z with acute                 |
	 * | U+017A  | ??     | z           | Latin small letter z with acute                   |
	 * | U+017B  | ??     | Z           | Latin capital letter Z with dot above             |
	 * | U+017C  | ??     | z           | Latin small letter z with dot above               |
	 * | U+017D  | ??     | Z           | Latin capital letter Z with caron                 |
	 * | U+017E  | ??     | z           | Latin small letter z with caron                   |
	 * | U+017F  | ??     | s           | Latin small letter long s                         |
	 * | U+01A0  | ??     | O           | Latin capital letter O with horn                  |
	 * | U+01A1  | ??     | o           | Latin small letter o with horn                    |
	 * | U+01AF  | ??     | U           | Latin capital letter U with horn                  |
	 * | U+01B0  | ??     | u           | Latin small letter u with horn                    |
	 * | U+01CD  | ??     | A           | Latin capital letter A with caron                 |
	 * | U+01CE  | ??     | a           | Latin small letter a with caron                   |
	 * | U+01CF  | ??     | I           | Latin capital letter I with caron                 |
	 * | U+01D0  | ??     | i           | Latin small letter i with caron                   |
	 * | U+01D1  | ??     | O           | Latin capital letter O with caron                 |
	 * | U+01D2  | ??     | o           | Latin small letter o with caron                   |
	 * | U+01D3  | ??     | U           | Latin capital letter U with caron                 |
	 * | U+01D4  | ??     | u           | Latin small letter u with caron                   |
	 * | U+01D5  | ??     | U           | Latin capital letter U with diaeresis and macron  |
	 * | U+01D6  | ??     | u           | Latin small letter u with diaeresis and macron    |
	 * | U+01D7  | ??     | U           | Latin capital letter U with diaeresis and acute   |
	 * | U+01D8  | ??     | u           | Latin small letter u with diaeresis and acute     |
	 * | U+01D9  | ??     | U           | Latin capital letter U with diaeresis and caron   |
	 * | U+01DA  | ??     | u           | Latin small letter u with diaeresis and caron     |
	 * | U+01DB  | ??     | U           | Latin capital letter U with diaeresis and grave   |
	 * | U+01DC  | ??     | u           | Latin small letter u with diaeresis and grave     |
	 *
	 * Decompositions for Latin Extended-B:
	 *
	 * |   Code   | Glyph | Replacement |                Description                |
	 * | -------- | ----- | ----------- | ----------------------------------------- |
	 * | U+0218   | ??     | S           | Latin capital letter S with comma below   |
	 * | U+0219   | ??     | s           | Latin small letter s with comma below     |
	 * | U+021A   | ??     | T           | Latin capital letter T with comma below   |
	 * | U+021B   | ??     | t           | Latin small letter t with comma below     |
	 *
	 * Vowels with diacritic (Chinese, Hanyu Pinyin):
	 *
	 * |   Code   | Glyph | Replacement |                      Description                      |
	 * | -------- | ----- | ----------- | ----------------------------------------------------- |
	 * | U+0251   | ??     | a           | Latin small letter alpha                              |
	 * | U+1EA0   | ???     | A           | Latin capital letter A with dot below                 |
	 * | U+1EA1   | ???     | a           | Latin small letter a with dot below                   |
	 * | U+1EA2   | ???     | A           | Latin capital letter A with hook above                |
	 * | U+1EA3   | ???     | a           | Latin small letter a with hook above                  |
	 * | U+1EA4   | ???     | A           | Latin capital letter A with circumflex and acute      |
	 * | U+1EA5   | ???     | a           | Latin small letter a with circumflex and acute        |
	 * | U+1EA6   | ???     | A           | Latin capital letter A with circumflex and grave      |
	 * | U+1EA7   | ???     | a           | Latin small letter a with circumflex and grave        |
	 * | U+1EA8   | ???     | A           | Latin capital letter A with circumflex and hook above |
	 * | U+1EA9   | ???     | a           | Latin small letter a with circumflex and hook above   |
	 * | U+1EAA   | ???     | A           | Latin capital letter A with circumflex and tilde      |
	 * | U+1EAB   | ???     | a           | Latin small letter a with circumflex and tilde        |
	 * | U+1EA6   | ???     | A           | Latin capital letter A with circumflex and dot below  |
	 * | U+1EAD   | ???     | a           | Latin small letter a with circumflex and dot below    |
	 * | U+1EAE   | ???     | A           | Latin capital letter A with breve and acute           |
	 * | U+1EAF   | ???     | a           | Latin small letter a with breve and acute             |
	 * | U+1EB0   | ???     | A           | Latin capital letter A with breve and grave           |
	 * | U+1EB1   | ???     | a           | Latin small letter a with breve and grave             |
	 * | U+1EB2   | ???     | A           | Latin capital letter A with breve and hook above      |
	 * | U+1EB3   | ???     | a           | Latin small letter a with breve and hook above        |
	 * | U+1EB4   | ???     | A           | Latin capital letter A with breve and tilde           |
	 * | U+1EB5   | ???     | a           | Latin small letter a with breve and tilde             |
	 * | U+1EB6   | ???     | A           | Latin capital letter A with breve and dot below       |
	 * | U+1EB7   | ???     | a           | Latin small letter a with breve and dot below         |
	 * | U+1EB8   | ???     | E           | Latin capital letter E with dot below                 |
	 * | U+1EB9   | ???     | e           | Latin small letter e with dot below                   |
	 * | U+1EBA   | ???     | E           | Latin capital letter E with hook above                |
	 * | U+1EBB   | ???     | e           | Latin small letter e with hook above                  |
	 * | U+1EBC   | ???     | E           | Latin capital letter E with tilde                     |
	 * | U+1EBD   | ???     | e           | Latin small letter e with tilde                       |
	 * | U+1EBE   | ???     | E           | Latin capital letter E with circumflex and acute      |
	 * | U+1EBF   | ???     | e           | Latin small letter e with circumflex and acute        |
	 * | U+1EC0   | ???     | E           | Latin capital letter E with circumflex and grave      |
	 * | U+1EC1   | ???     | e           | Latin small letter e with circumflex and grave        |
	 * | U+1EC2   | ???     | E           | Latin capital letter E with circumflex and hook above |
	 * | U+1EC3   | ???     | e           | Latin small letter e with circumflex and hook above   |
	 * | U+1EC4   | ???     | E           | Latin capital letter E with circumflex and tilde      |
	 * | U+1EC5   | ???     | e           | Latin small letter e with circumflex and tilde        |
	 * | U+1EC6   | ???     | E           | Latin capital letter E with circumflex and dot below  |
	 * | U+1EC7   | ???     | e           | Latin small letter e with circumflex and dot below    |
	 * | U+1EC8   | ???     | I           | Latin capital letter I with hook above                |
	 * | U+1EC9   | ???     | i           | Latin small letter i with hook above                  |
	 * | U+1ECA   | ???     | I           | Latin capital letter I with dot below                 |
	 * | U+1ECB   | ???     | i           | Latin small letter i with dot below                   |
	 * | U+1ECC   | ???     | O           | Latin capital letter O with dot below                 |
	 * | U+1ECD   | ???     | o           | Latin small letter o with dot below                   |
	 * | U+1ECE   | ???     | O           | Latin capital letter O with hook above                |
	 * | U+1ECF   | ???     | o           | Latin small letter o with hook above                  |
	 * | U+1ED0   | ???     | O           | Latin capital letter O with circumflex and acute      |
	 * | U+1ED1   | ???     | o           | Latin small letter o with circumflex and acute        |
	 * | U+1ED2   | ???     | O           | Latin capital letter O with circumflex and grave      |
	 * | U+1ED3   | ???     | o           | Latin small letter o with circumflex and grave        |
	 * | U+1ED4   | ???     | O           | Latin capital letter O with circumflex and hook above |
	 * | U+1ED5   | ???     | o           | Latin small letter o with circumflex and hook above   |
	 * | U+1ED6   | ???     | O           | Latin capital letter O with circumflex and tilde      |
	 * | U+1ED7   | ???     | o           | Latin small letter o with circumflex and tilde        |
	 * | U+1ED8   | ???     | O           | Latin capital letter O with circumflex and dot below  |
	 * | U+1ED9   | ???     | o           | Latin small letter o with circumflex and dot below    |
	 * | U+1EDA   | ???     | O           | Latin capital letter O with horn and acute            |
	 * | U+1EDB   | ???     | o           | Latin small letter o with horn and acute              |
	 * | U+1EDC   | ???     | O           | Latin capital letter O with horn and grave            |
	 * | U+1EDD   | ???     | o           | Latin small letter o with horn and grave              |
	 * | U+1EDE   | ???     | O           | Latin capital letter O with horn and hook above       |
	 * | U+1EDF   | ???     | o           | Latin small letter o with horn and hook above         |
	 * | U+1EE0   | ???     | O           | Latin capital letter O with horn and tilde            |
	 * | U+1EE1   | ???     | o           | Latin small letter o with horn and tilde              |
	 * | U+1EE2   | ???     | O           | Latin capital letter O with horn and dot below        |
	 * | U+1EE3   | ???     | o           | Latin small letter o with horn and dot below          |
	 * | U+1EE4   | ???     | U           | Latin capital letter U with dot below                 |
	 * | U+1EE5   | ???     | u           | Latin small letter u with dot below                   |
	 * | U+1EE6   | ???     | U           | Latin capital letter U with hook above                |
	 * | U+1EE7   | ???     | u           | Latin small letter u with hook above                  |
	 * | U+1EE8   | ???     | U           | Latin capital letter U with horn and acute            |
	 * | U+1EE9   | ???     | u           | Latin small letter u with horn and acute              |
	 * | U+1EEA   | ???     | U           | Latin capital letter U with horn and grave            |
	 * | U+1EEB   | ???     | u           | Latin small letter u with horn and grave              |
	 * | U+1EEC   | ???     | U           | Latin capital letter U with horn and hook above       |
	 * | U+1EED   | ???     | u           | Latin small letter u with horn and hook above         |
	 * | U+1EEE   | ???     | U           | Latin capital letter U with horn and tilde            |
	 * | U+1EEF   | ???     | u           | Latin small letter u with horn and tilde              |
	 * | U+1EF0   | ???     | U           | Latin capital letter U with horn and dot below        |
	 * | U+1EF1   | ???     | u           | Latin small letter u with horn and dot below          |
	 * | U+1EF2   | ???     | Y           | Latin capital letter Y with grave                     |
	 * | U+1EF3   | ???     | y           | Latin small letter y with grave                       |
	 * | U+1EF4   | ???     | Y           | Latin capital letter Y with dot below                 |
	 * | U+1EF5   | ???     | y           | Latin small letter y with dot below                   |
	 * | U+1EF6   | ???     | Y           | Latin capital letter Y with hook above                |
	 * | U+1EF7   | ???     | y           | Latin small letter y with hook above                  |
	 * | U+1EF8   | ???     | Y           | Latin capital letter Y with tilde                     |
	 * | U+1EF9   | ???     | y           | Latin small letter y with tilde                       |
	 *
	 * German (`de_DE`), German formal (`de_DE_formal`), German (Switzerland) formal (`de_CH`),
	 * and German (Switzerland) informal (`de_CH_informal`) locales:
	 *
	 * |   Code   | Glyph | Replacement |               Description               |
	 * | -------- | ----- | ----------- | --------------------------------------- |
	 * | U+00C4   | ??     | Ae          | Latin capital letter A with diaeresis   |
	 * | U+00E4   | ??     | ae          | Latin small letter a with diaeresis     |
	 * | U+00D6   | ??     | Oe          | Latin capital letter O with diaeresis   |
	 * | U+00F6   | ??     | oe          | Latin small letter o with diaeresis     |
	 * | U+00DC   | ??     | Ue          | Latin capital letter U with diaeresis   |
	 * | U+00FC   | ??     | ue          | Latin small letter u with diaeresis     |
	 * | U+00DF   | ??     | ss          | Latin small letter sharp s              |
	 *
	 * Danish (`da_DK`) locale:
	 *
	 * |   Code   | Glyph | Replacement |               Description               |
	 * | -------- | ----- | ----------- | --------------------------------------- |
	 * | U+00C6   | ??     | Ae          | Latin capital letter AE                 |
	 * | U+00E6   | ??     | ae          | Latin small letter ae                   |
	 * | U+00D8   | ??     | Oe          | Latin capital letter O with stroke      |
	 * | U+00F8   | ??     | oe          | Latin small letter o with stroke        |
	 * | U+00C5   | ??     | Aa          | Latin capital letter A with ring above  |
	 * | U+00E5   | ??     | aa          | Latin small letter a with ring above    |
	 *
	 * Catalan (`ca`) locale:
	 *
	 * |   Code   | Glyph | Replacement |               Description               |
	 * | -------- | ----- | ----------- | --------------------------------------- |
	 * | U+00B7   | l??l   | ll          | Flown dot (between two Ls)              |
	 *
	 * Serbian (`sr_RS`) and Bosnian (`bs_BA`) locales:
	 *
	 * |   Code   | Glyph | Replacement |               Description               |
	 * | -------- | ----- | ----------- | --------------------------------------- |
	 * | U+0110   | ??     | DJ          | Latin capital letter D with stroke      |
	 * | U+0111   | ??     | dj          | Latin small letter d with stroke        |
	 *
	 * @since 1.2.1
	 * @since 4.6.0 Added locale support for `de_CH`, `de_CH_informal`, and `ca`.
	 * @since 4.7.0 Added locale support for `sr_RS`.
	 * @since 4.8.0 Added locale support for `bs_BA`.
	 *
	 * @param string $string Text that might have accent characters
	 * @return string Filtered string with replaced "nice" characters.
	 */
	private function remove_accents( $string ) {
		if ( ! preg_match( '/[\x80-\xff]/', $string ) ) {
			return $string;
		}

		if ( $this->seems_utf8( $string ) ) {
			$chars = array(
				// Decompositions for Latin-1 Supplement.
				'??' => 'a',
				'??' => 'o',
				'??' => 'A',
				'??' => 'A',
				'??' => 'A',
				'??' => 'A',
				'??' => 'A',
				'??' => 'A',
				'??' => 'AE',
				'??' => 'C',
				'??' => 'E',
				'??' => 'E',
				'??' => 'E',
				'??' => 'E',
				'??' => 'I',
				'??' => 'I',
				'??' => 'I',
				'??' => 'I',
				'??' => 'D',
				'??' => 'N',
				'??' => 'O',
				'??' => 'O',
				'??' => 'O',
				'??' => 'O',
				'??' => 'O',
				'??' => 'U',
				'??' => 'U',
				'??' => 'U',
				'??' => 'U',
				'??' => 'Y',
				'??' => 'TH',
				'??' => 's',
				'??' => 'a',
				'??' => 'a',
				'??' => 'a',
				'??' => 'a',
				'??' => 'a',
				'??' => 'a',
				'??' => 'ae',
				'??' => 'c',
				'??' => 'e',
				'??' => 'e',
				'??' => 'e',
				'??' => 'e',
				'??' => 'i',
				'??' => 'i',
				'??' => 'i',
				'??' => 'i',
				'??' => 'd',
				'??' => 'n',
				'??' => 'o',
				'??' => 'o',
				'??' => 'o',
				'??' => 'o',
				'??' => 'o',
				'??' => 'o',
				'??' => 'u',
				'??' => 'u',
				'??' => 'u',
				'??' => 'u',
				'??' => 'y',
				'??' => 'th',
				'??' => 'y',
				'??' => 'O',
				// Decompositions for Latin Extended-A.
				'??' => 'A',
				'??' => 'a',
				'??' => 'A',
				'??' => 'a',
				'??' => 'A',
				'??' => 'a',
				'??' => 'C',
				'??' => 'c',
				'??' => 'C',
				'??' => 'c',
				'??' => 'C',
				'??' => 'c',
				'??' => 'C',
				'??' => 'c',
				'??' => 'D',
				'??' => 'd',
				'??' => 'D',
				'??' => 'd',
				'??' => 'E',
				'??' => 'e',
				'??' => 'E',
				'??' => 'e',
				'??' => 'E',
				'??' => 'e',
				'??' => 'E',
				'??' => 'e',
				'??' => 'E',
				'??' => 'e',
				'??' => 'G',
				'??' => 'g',
				'??' => 'G',
				'??' => 'g',
				'??' => 'G',
				'??' => 'g',
				'??' => 'G',
				'??' => 'g',
				'??' => 'H',
				'??' => 'h',
				'??' => 'H',
				'??' => 'h',
				'??' => 'I',
				'??' => 'i',
				'??' => 'I',
				'??' => 'i',
				'??' => 'I',
				'??' => 'i',
				'??' => 'I',
				'??' => 'i',
				'??' => 'I',
				'??' => 'i',
				'??' => 'IJ',
				'??' => 'ij',
				'??' => 'J',
				'??' => 'j',
				'??' => 'K',
				'??' => 'k',
				'??' => 'k',
				'??' => 'L',
				'??' => 'l',
				'??' => 'L',
				'??' => 'l',
				'??' => 'L',
				'??' => 'l',
				'??' => 'L',
				'??' => 'l',
				'??' => 'L',
				'??' => 'l',
				'??' => 'N',
				'??' => 'n',
				'??' => 'N',
				'??' => 'n',
				'??' => 'N',
				'??' => 'n',
				'??' => 'n',
				'??' => 'N',
				'??' => 'n',
				'??' => 'O',
				'??' => 'o',
				'??' => 'O',
				'??' => 'o',
				'??' => 'O',
				'??' => 'o',
				'??' => 'OE',
				'??' => 'oe',
				'??' => 'R',
				'??' => 'r',
				'??' => 'R',
				'??' => 'r',
				'??' => 'R',
				'??' => 'r',
				'??' => 'S',
				'??' => 's',
				'??' => 'S',
				'??' => 's',
				'??' => 'S',
				'??' => 's',
				'??' => 'S',
				'??' => 's',
				'??' => 'T',
				'??' => 't',
				'??' => 'T',
				'??' => 't',
				'??' => 'T',
				'??' => 't',
				'??' => 'U',
				'??' => 'u',
				'??' => 'U',
				'??' => 'u',
				'??' => 'U',
				'??' => 'u',
				'??' => 'U',
				'??' => 'u',
				'??' => 'U',
				'??' => 'u',
				'??' => 'U',
				'??' => 'u',
				'??' => 'W',
				'??' => 'w',
				'??' => 'Y',
				'??' => 'y',
				'??' => 'Y',
				'??' => 'Z',
				'??' => 'z',
				'??' => 'Z',
				'??' => 'z',
				'??' => 'Z',
				'??' => 'z',
				'??' => 's',
				// Decompositions for Latin Extended-B.
				'??' => 'S',
				'??' => 's',
				'??' => 'T',
				'??' => 't',
				// Euro sign.
				'???' => 'E',
				// GBP (Pound) sign.
				'??' => '',
				// Vowels with diacritic (Vietnamese).
				// Unmarked.
				'??' => 'O',
				'??' => 'o',
				'??' => 'U',
				'??' => 'u',
				// Grave accent.
				'???' => 'A',
				'???' => 'a',
				'???' => 'A',
				'???' => 'a',
				'???' => 'E',
				'???' => 'e',
				'???' => 'O',
				'???' => 'o',
				'???' => 'O',
				'???' => 'o',
				'???' => 'U',
				'???' => 'u',
				'???' => 'Y',
				'???' => 'y',
				// Hook.
				'???' => 'A',
				'???' => 'a',
				'???' => 'A',
				'???' => 'a',
				'???' => 'A',
				'???' => 'a',
				'???' => 'E',
				'???' => 'e',
				'???' => 'E',
				'???' => 'e',
				'???' => 'I',
				'???' => 'i',
				'???' => 'O',
				'???' => 'o',
				'???' => 'O',
				'???' => 'o',
				'???' => 'O',
				'???' => 'o',
				'???' => 'U',
				'???' => 'u',
				'???' => 'U',
				'???' => 'u',
				'???' => 'Y',
				'???' => 'y',
				// Tilde.
				'???' => 'A',
				'???' => 'a',
				'???' => 'A',
				'???' => 'a',
				'???' => 'E',
				'???' => 'e',
				'???' => 'E',
				'???' => 'e',
				'???' => 'O',
				'???' => 'o',
				'???' => 'O',
				'???' => 'o',
				'???' => 'U',
				'???' => 'u',
				'???' => 'Y',
				'???' => 'y',
				// Acute accent.
				'???' => 'A',
				'???' => 'a',
				'???' => 'A',
				'???' => 'a',
				'???' => 'E',
				'???' => 'e',
				'???' => 'O',
				'???' => 'o',
				'???' => 'O',
				'???' => 'o',
				'???' => 'U',
				'???' => 'u',
				// Dot below.
				'???' => 'A',
				'???' => 'a',
				'???' => 'A',
				'???' => 'a',
				'???' => 'A',
				'???' => 'a',
				'???' => 'E',
				'???' => 'e',
				'???' => 'E',
				'???' => 'e',
				'???' => 'I',
				'???' => 'i',
				'???' => 'O',
				'???' => 'o',
				'???' => 'O',
				'???' => 'o',
				'???' => 'O',
				'???' => 'o',
				'???' => 'U',
				'???' => 'u',
				'???' => 'U',
				'???' => 'u',
				'???' => 'Y',
				'???' => 'y',
				// Vowels with diacritic (Chinese, Hanyu Pinyin).
				'??' => 'a',
				// Macron.
				'??' => 'U',
				'??' => 'u',
				// Acute accent.
				'??' => 'U',
				'??' => 'u',
				// Caron.
				'??' => 'A',
				'??' => 'a',
				'??' => 'I',
				'??' => 'i',
				'??' => 'O',
				'??' => 'o',
				'??' => 'U',
				'??' => 'u',
				'??' => 'U',
				'??' => 'u',
				// Grave accent.
				'??' => 'U',
				'??' => 'u',
			);

			// Used for locale-specific rules.
			// TODO: get locale
			// $locale = get_locale();
			$locale = 'en_US';

			if ( 'de_DE' == $locale || 'de_DE_formal' == $locale || 'de_CH' == $locale || 'de_CH_informal' == $locale ) {
				$chars['??'] = 'Ae';
				$chars['??'] = 'ae';
				$chars['??'] = 'Oe';
				$chars['??'] = 'oe';
				$chars['??'] = 'Ue';
				$chars['??'] = 'ue';
				$chars['??'] = 'ss';
			} elseif ( 'da_DK' === $locale ) {
				$chars['??'] = 'Ae';
				$chars['??'] = 'ae';
				$chars['??'] = 'Oe';
				$chars['??'] = 'oe';
				$chars['??'] = 'Aa';
				$chars['??'] = 'aa';
			} elseif ( 'ca' === $locale ) {
				$chars['l??l'] = 'll';
			} elseif ( 'sr_RS' === $locale || 'bs_BA' === $locale ) {
				$chars['??'] = 'DJ';
				$chars['??'] = 'dj';
			}

			$string = strtr( $string, $chars );
		} else {
			$chars = array();
			// Assume ISO-8859-1 if not UTF-8.
			$chars['in'] = "\x80\x83\x8a\x8e\x9a\x9e"
				. "\x9f\xa2\xa5\xb5\xc0\xc1\xc2"
				. "\xc3\xc4\xc5\xc7\xc8\xc9\xca"
				. "\xcb\xcc\xcd\xce\xcf\xd1\xd2"
				. "\xd3\xd4\xd5\xd6\xd8\xd9\xda"
				. "\xdb\xdc\xdd\xe0\xe1\xe2\xe3"
				. "\xe4\xe5\xe7\xe8\xe9\xea\xeb"
				. "\xec\xed\xee\xef\xf1\xf2\xf3"
				. "\xf4\xf5\xf6\xf8\xf9\xfa\xfb"
				. "\xfc\xfd\xff";

			$chars['out'] = 'EfSZszYcYuAAAAAACEEEEIIIINOOOOOOUUUUYaaaaaaceeeeiiiinoooooouuuuyy';

			$string              = strtr( $string, $chars['in'], $chars['out'] );
			$double_chars        = array();
			$double_chars['in']  = array( "\x8c", "\x9c", "\xc6", "\xd0", "\xde", "\xdf", "\xe6", "\xf0", "\xfe" );
			$double_chars['out'] = array( 'OE', 'oe', 'AE', 'DH', 'TH', 'ss', 'ae', 'dh', 'th' );
			$string              = str_replace( $double_chars['in'], $double_chars['out'], $string );
		}

		return $string;
	}

	/**
	 * Set the mbstring internal encoding to a binary safe encoding when func_overload
	 * is enabled.
	 *
	 * When mbstring.func_overload is in use for multi-byte encodings, the results from
	 * strlen() and similar functions respect the utf8 characters, causing binary data
	 * to return incorrect lengths.
	 *
	 * This function overrides the mbstring encoding to a binary-safe encoding, and
	 * resets it to the users expected encoding afterwards through the
	 * `reset_mbstring_encoding` function.
	 *
	 * It is safe to recursively call this function, however each
	 * `mbstring_binary_safe_encoding()` call must be followed up with an equal number
	 * of `reset_mbstring_encoding()` calls.
	 *
	 * @since 3.7.0
	 *
	 * @see reset_mbstring_encoding()
	 *
	 * @staticvar array $encodings
	 * @staticvar bool  $overloaded
	 *
	 * @param bool $reset Optional. Whether to reset the encoding back to a previously-set encoding.
	 *                    Default false.
	 */
	private function mbstring_binary_safe_encoding( $reset = false ) {
		static $encodings  = array();
		static $overloaded = null;

		if ( is_null( $overloaded ) ) {
			$overloaded = function_exists( 'mb_internal_encoding' ) && ( ini_get( 'mbstring.func_overload' ) & 2 );
		}

		if ( false === $overloaded ) {
			return;
		}

		if ( ! $reset ) {
			$encoding = mb_internal_encoding();
			array_push( $encodings, $encoding );
			mb_internal_encoding( 'ISO-8859-1' );
		}

		if ( $reset && $encodings ) {
			$encoding = array_pop( $encodings );
			mb_internal_encoding( $encoding );
		}
	}

	/**
	 * Reset the mbstring internal encoding to a users previously set encoding.
	 *
	 * @see mbstring_binary_safe_encoding()
	 *
	 * @since 3.7.0
	 */
	private function reset_mbstring_encoding() {
		$this->mbstring_binary_safe_encoding( true );
	}
}
