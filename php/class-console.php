<?php

use \Psy\Configuration;

class Code_Snippets_Console {

	function __construct() {
		add_action( 'wp_ajax_nopriv_evaluatewpd', array( $this, 'evaluate_wpd_console' ) );
		add_action( 'wp_ajax_evaluatewpd', array( $this, 'evaluate_wpd_console' ) );
		add_action( 'wp_ajax_nopriv_getsnippetcontent', array( $this, 'get_snippet_content' ) );
		add_action( 'wp_ajax_getsnippetcontent', array( $this, 'get_snippet_content' ) );
	}

	function get_snippet_content() {
		$id      = $_POST['id'];
		$snippet = get_snippet_template( $id );
		wp_send_json( array('id' => $id, 'code' => $snippet->code ) );
	}

	function register_wpd_endpoints() {
		register_rest_route( 'wpd', '/evaluate', array(
			'methods'  => WP_REST_Server::READABLE,
			'callback' => array( $this, 'evaluate_wpd_console' ),
		) );
	}


	public function evaluate_wpd_console() {
		$timer = microtime( true );
		$input = base64_decode( $_POST['input'] );


		$config = new Configuration( array(
			'configDir' => WP_CONTENT_DIR,
		) );

		$output = new Code_Snippets_ShellOutput( Code_Snippets_ShellOutput::VERBOSITY_NORMAL, true );

		$config->setOutput( $output );
		$config->setColorMode( Configuration::COLOR_MODE_DISABLED );

		$psysh = new Code_Snippets_Shell( $config );
		$psysh->setOutput( $output );
		$psysh->addCode( $input );

		try {
			extract( $psysh->getScopeVariablesDiff( get_defined_vars() ) );
			ob_start( array( $psysh, 'writeStdout' ), 1 );
			set_error_handler( array( $psysh, 'handleError' ) );

			$_ = eval( $psysh->onExecute( $psysh->flushCode() ?: \Psy\ExecutionClosure::NOOP_INPUT ) );

			restore_error_handler();

			$psysh->setScopeVariables( get_defined_vars() );
			$psysh->writeReturnValue( $_ );

			ob_end_flush();

			if ( $output->exception ) {
				throw $output->exception;
			}

			$execution_time = microtime( true ) - $timer;

			$data = array(
				'output'         => $output->outputMessage,
				'execution_time' => number_format( $execution_time, 3, '.', '' ),
			);
			wp_send_json( $data );
			wp_die();
		} catch ( Throwable $e ) {
			ob_end_flush();
			wp_send_json_error( array(
				'message' => $e->getMessage(),
				'input'   => $input,
				'status'  => 422,
				'trace'   => $e->getTraceAsString(),
			) );
			wp_die();
		}
	}
}
