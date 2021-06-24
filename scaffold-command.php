<?php

if ( ! class_exists( 'WP_CLI' ) ) {
	return;
}

$camaleaun_scaffold_autoloader = dirname( __FILE__ ) . '/vendor/autoload.php';
if ( file_exists( $camaleaun_scaffold_autoloader ) ) {
	require_once $camaleaun_scaffold_autoloader;
}
WP_CLI::add_command( 'scaffold project', 'Camaleaun\Scaffold_Command' );
WP_CLI::add_command( 'init-project', array( 'Camaleaun\Scaffold_Command', 'init' ) );
// WP_CLI::add_command( 'test-abspath', array( 'Camaleaun\Scaffold_Command', 'test_abspath' ) );
