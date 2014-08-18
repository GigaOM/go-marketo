<?php
/**
 * GO_Marketo unit tests
 */

require_once dirname( __DIR__ ) . '/go-marketo.php';

/**
 * Our tests depend on the real Marketo authentication info from the system
 * config, and the lead will.luo+test2@gigaom.com with a Marketo id of
 * 85902141.
 */
class GO_Marketo_Test extends WP_UnitTestCase
{
	private $lead_email = 'will.luo+test2@gigaom.com';
	private $lead_id = 85902141;

	/**
	 * set up our test environment
	 */
	public function setUp()
	{
		parent::setUp();
		$this->clear_caches();
	}//END setUp

	/**
	 * clean up our environment
	 */
	public function tearDown()
	{
		parent::tearDown();
		$this->clear_caches();
	}//END tearDown

	/**
	 */
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

	/**
	 * test GO_Marketo_API's get_auth_token() function
	 */
	public function test_get_auth_token()
	{
		$token = go_marketo()->api()->get_auth_token();
		$this->assertFalse( empty( $token ) );
	}//END test_get_auth_token

	/**
	 * test GO_Marketo_API's get_lead_by_id() function
	 */
	public function test_get_lead_by_id()
	{
		$lead = go_marketo()->api()->get_lead_by_id( $this->lead_id );

		$this->assertFalse( is_wp_error( $lead ) );
		$this->assertFalse( empty( $lead ) );
		$this->assertTrue( 0 < count( $lead ) );
		$this->assertEquals( $this->lead_id, $lead['id'] );
		$this->assertEquals( $this->lead_email, $lead['email'] );
	}//END test_get_lead_by_id

	/**
	 * test GO_Marketo_API's get_leads() function
	 */
	public function test_get_leads()
	{
		$leads = go_marketo()->api()->get_leads( 'Email', array( $this->lead_email ), array( 'firstName', 'lastName', 'email', 'wpid' ) );

		$this->assertFalse( is_wp_error( $leads ) );
		$this->assertEquals( 1, count( $leads ) );
		$this->assertEquals( $this->lead_email, $leads[0]->email );
		$this->assertEquals( $this->lead_id, $leads[0]->id );
	}//END test_get_leads

	/**
	 * test GO_Marketo_API's update_lead() function
	 */
	public function test_update_lead()
	{
		// test the error condition
		$result = go_marketo()->api()->update_lead( array() );
		$this->assertTrue( is_wp_error( $result ) );
		$this->assertEquals( 'missing_field', $result->get_error_code() );

		// try to update/create an actual lead
		$leads = go_marketo()->api()->get_leads(
			'Email',
			array( $this->lead_email ),
			array( 'firstName', 'lastName', 'email', 'wpid', 'marketoEventEmails', 'marketoResearchEmails', 'wpname' ) );
		$this->assertFalse( is_wp_error( $leads ) );
		$this->assertEquals( 1, count( $leads ) );
		$this->assertEquals( $this->lead_email, $leads[0]->email );
		$the_lead = $leads[0];

		$result = go_marketo()->api()->update_lead(
			array(
				'id' => $the_lead->id,
				'email' => $this->lead_email,
				'wpname' => 'WL 2014',
			)
		);
		$this->assertFalse( is_wp_error( $result ) );
		$this->assertEquals( $the_lead->id, $result );

		$leads = go_marketo()->api()->get_leads(
			'ID',
			array( $the_lead->id ),
			array( 'firstName', 'lastName', 'email', 'wpid', 'marketoEventEmails', 'marketoResearchEmails', 'wpname' )
		);
		$this->assertEquals( 1, count( $leads ) );
		$this->assertEquals( 'WL 2014', $leads[0]->wpname );

		// empty wpname for later tests
		$result = go_marketo()->api()->update_lead(
			array(
				'id' => $the_lead->id,
				'wpname' => '',
			)
		);
		$this->assertFalse( is_wp_error( $result ) );
		$this->assertEquals( $the_lead->id, $result );
	}//END test_update_lead

	public function test_add_lead_to_list()
	{
		$the_list = go_marketo()->config( 'list' );
		$this->assertFalse( empty( $the_list ) );

		$leads = go_marketo()->api()->get_leads(
			'Email',
			array( $this->lead_email ),
			array( 'email', 'wpid', 'go_newsletters' )
		);

		$this->assertFalse( is_wp_error( $leads ) );
		$this->assertEquals( 1, count( $leads ) );

		$response = go_marketo()->api()->add_lead_to_list( $the_list['id'], $leads[0]->id );

		$this->assertTrue( is_numeric( $response ) );
	}//END test_add_lead_to_list

	public function test_post_lead_update_action()
	{
		$this->clear_caches();
		add_action( 'go_marketo_post_lead_update', array( $this, 'go_marketo_post_lead_update' ), 10, 2 );

		$wp_user_id = wp_insert_user(
			array(
				'user_login' => 'willluotest2',
				'user_email' => 'will.luo+test2@gigaom.com',
				'role' => 'guest',
			)
		);

		$user = get_user_by( 'id', $wp_user_id );

		go_marketo()->sync_user( $user, 'add' );
	}//END test_post_lead_update_action

	public function go_marketo_post_lead_update( $user, $marketo_id )
	{
		$this->assertEquals( $this->lead_email, $user->user_email );
		$this->assertEquals( $this->lead_id, $marketo_id );
	}//END go_marketo_post_lead_update

	/**
	 * clear out caches so we don't leave any test artifacts behind
	 */
	public function clear_caches()
	{
		$this->flush_cache();

		// clear cache if enabled
		$save_handler = ini_get( 'session.save_handler' );
		$save_path = ini_get( 'session.save_path' );

		try
		{
			if ( ! $save_path )
			{
				$save_path = 'tcp://127.0.0.1:11211';
			}

			$memcache = new Memcache;

			$save_path = str_replace( 'tcp://', '', $save_path );
			$save_path = explode( ':', $save_path );

			$memcache->connect( $save_path[0], $save_path[1] );
			$memcache->flush();
		}
		catch( Exception $e )
		{
			var_dump( $e );
		}//END catch
	}//END clear_caches
}// END class