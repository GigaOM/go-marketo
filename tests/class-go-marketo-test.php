<?php
/**
 * GO_Marketo unit tests
 */

require_once dirname( __DIR__ ) . '/go-marketo.php';

/**
 * Our tests depend on the real Marketo authentication info from the system
 * config.
 */
class GO_Marketo_Test extends WP_UnitTestCase
{
	/**
	 * set up our test environment
	 */
	public function setUp()
	{
		parent::setUp();
		add_filter( 'go_config', array( $this, 'go_config_filter' ), 10, 2 );
	}//END setUp

	/**
	 * make sure we can get an instance of our plugin
	 */
	public function test_singleton()
	{
		$this->assertTrue( function_exists( 'go_marketo' ) );
		$this->assertTrue( is_object( go_marketo() ) );
		$config = go_marketo()->config();
		$this->assertFalse( empty( $config ) );
	}//END test_singleton

	public function test_get_auth_token()
	{
		$token = go_marketo()->api()->get_auth_token();
		$this->assertFalse( empty( $token ) );
	}//END test_get_auth_token

	/**
	 * customize the system config file for our tests
	 */
	public function go_config_filter( $config, $which )
	{
		if ( 'go-marketo' == $which )
		{
///			$config = array(
//			);
		}//END if

		return $config;
	}//END go_config_filter
}// END class