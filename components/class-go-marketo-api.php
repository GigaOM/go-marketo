<?php
/**
 * class-go-marketo-api.php
 *
 * @author Gigaom <support@gigaom.com>
 */
class GO_Marketo_API
{
	private $client_id     = NULL;
	private $client_secret = NULL;
	private $auth_token    = NULL;

	/**
	 * construct an API object
	 *
	 * @param string $client_id Marketo client id.
	 * @param string $client_secret Marketo client secret.
	 */
	public function __construct( $client_id, $client_secret )
	{
		$this->client_id = $client_id;
		$this->client_secret = $client_secret;
	}//END __construct

	/**
	 * Retrieve our authentication token. First we'll look in our cache.
	 * If it's not found there then try to retrieve it with our client id
	 * and secret.
	 *
	 * @return string the authentication token if we have it. NULL if not.
	 */
	public function get_auth_token()
	{
		// first check the cache
		if ( ! empty( $this->auth_token ) )
		{
			return $this->auth_token;
		}

		$this->auth_token = wp_cache_get( 'auth_token', 'go-marketo' );

		if ( FALSE !== $this->auth_token )
		{
			return $this->auth_token;
		}

		$response = wp_remote_get(
			go_marketo()->config( 'identity_url' ) . '/oauth/token?grant_type=client_credentials&client_id=' . $this->client_id . '&client_secret=' . $this->client_secret
		);

		if ( 200 != $response['response']['code'] )
		{
			return NULL;
		}

		$body = json_decode( $response['body'] );

		$this->auth_token = $body->access_token;

		$expires_in = isset( $body->expires_in ) ? $body->expires_in : go_marketo()->config( 'auth_token_ttl' );

		wp_cache_set( 'auth_token', $this->auth_token, 'go-marketo', $expires_in );

		return $this->auth_token;
	}//END get_auth_token

	/**
	 * retrieve a single lead using the Marketo id
	 *
	 * @param int $id Marketo id of a lead
	 * @return array attributes of the lead if found, NULL if not
	 */
	public function get_lead_by_id( $id )
	{
		$url = go_marketo()->config( 'endpoint_url' ) . '/v1/lead/' . absint( $id ) . '.json';

		$results = $this->marketo_rest_get( $url );

		if ( 0 < count( $results ) )
		{
			return (array) $results[0]; // should just get one lead per Marketo id
		}

		return NULL;
	}//END get_lead_by_id

	/**
	 * retrieve a list of leads filtered by attribute $type and $value
	 *
	 * @param string $filter_type what filter type to use. this is named
	 *  after the lead attribute fields. e.g. 'id', 'email', 'facebookId',
	 *  etc.
	 * @param mixed $filter_values can be comma-separated string or an array,
	 *  of the values of $type to filter for.
	 * @param array $fields what fields to return for each lead
	 */
	public function get_leads( $filter_type, $filter_values, $fields = array() )
	{
		if ( is_array( $filter_values ) )
		{
			$filter_values = implode( ',', array_map( 'urlencode', $filter_values ) );
		}
		else
		{
			$filter_values = urlencode( $filter_values );
		}

		$url = go_marketo()->config( 'endpoint_url' ) . '/v1/leads.json?filterType=' . urlencode( $filter_type ) . '&filterValues=' . $filter_values;

		if ( ! empty( $fields ) )
		{
			$fields = implode( ',', array_map( 'urlencode', $fields ) );
			$url .= '&fields=' . sanitize_text_field( $fields );
		}

		return $this->marketo_rest_get( $url );
	}//END get_leads

	/**
	 * make an HTTP GET request to Marketo's RESET API. Mainly we add the
	 * authorization token to the HTTP header.
	 *
	 * @param $url string the url to request, query vars included.
	 * @return array results from the HTTP GET request. NULL if we encountered
	 *  an HTTP error
	 */
	private function marketo_rest_get( $url )
	{
		$response = wp_remote_get(
			$url,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->get_auth_token(),
				),
			)
		);

		if ( 200 != $response['response']['code'] )
		{
			return NULL;
		}

		return json_decode( $response['body'] )->result;
	}//END marketo_rest_get
}//END class