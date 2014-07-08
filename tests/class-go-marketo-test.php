<?php
/**
 * GO_Marketo unit tests
 */

require_once dirname( __DIR__ ) . '/go-marketo.php';

class GO_Marketo_Test extends WP_UnitTestCase
{
	/**
	 * set up our test environment
	 */
	public function setUp()
	{
		parent::setUp();
		remove_filter( 'go_config', array( go_config(), 'go_config_filter' ), 10, 2 );
		add_filter( 'go_config', array( $this, 'go_config_filter' ), 10, 2 );
	}//END setUp

	/**
	 * make sure we can get an instance of our plugin
	 */
	public function test_singleton()
	{
		$this->assertTrue( function_exists( 'go_marketo' ) );
		$this->assertTrue( is_object( go_marketo() ) );
	}//END test_singleton

	/**
	 * return custom config data for our tests
	 */
	public function go_config_filter( $config, $which )
	{
		if ( 'go-marketo' == $which )
		{
			$config = array(
			);
		}//END if

		return $config;
	}//END go_config_filter
}// END class