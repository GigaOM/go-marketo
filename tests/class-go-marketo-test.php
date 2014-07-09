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
		// the id is for will.luo+test2@gigaom.com
		// https://app-sjo.marketo.com/leadDatabase/loadLeadDetail?leadId=26059509
		$lead = go_marketo()->api()->get_lead_by_id( 26059509 );

		$this->assertFalse( empty( $lead ) );
		$this->assertTrue( 0 < count( $lead ) );
		$this->assertEquals( 26059509, $lead['id'] );
		$this->assertEquals( 'will.luo+test2@gigaom.com', $lead['email'] );
	}//END test_get_lead_by_id

	/**
	 * test GO_Marketo_API's get_leads() function
	 */
	public function test_get_leads()
	{
		$leads = go_marketo()->api()->get_leads( 'Email', array( 'will.luo+test2@gigaom.com' ), array( 'firstName', 'lastName', 'email', 'wpid' ) );

		$this->assertEquals( 1, count( $leads ) );
		$this->assertEquals( 'will.luo+test2@gigaom.com', $leads[0]->email );
		$this->assertEquals( 26059509, $leads[0]->id );
		$this->assertEquals( 137141, $leads[0]->wpid );

		$leads = go_marketo()->api()->get_leads( 'wpid', 137141, array( 'firstName', 'lastName', 'email', 'wpid' ) );

		$this->assertEquals( 1, count( $leads ) );
		$this->assertEquals( 'will.luo+test2@gigaom.com', $leads[0]->email );
		$this->assertEquals( 26059509, $leads[0]->id );
		$this->assertEquals( 137141, $leads[0]->wpid );
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
			array( 'will.luo+test2@gigaom.com' ),
			array( 'firstName', 'lastName', 'email', 'wpid', 'marketoEventEmails', 'marketoResearchEmails', 'wpname' ) );
		$this->assertEquals( 1, count( $leads ) );
		$this->assertEquals( 'will.luo+test2@gigaom.com', $leads[0]->email );
		$the_lead = $leads[0];

		
		$result = go_marketo()->api()->update_lead(
			array(
				'id' => $the_lead->id,
				'email' => 'will.luo+test2@gigaom.com',
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
}// END class