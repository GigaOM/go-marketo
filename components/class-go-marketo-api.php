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
			go_marketo()->config( 'endpoint' ) . '/identity/oauth/token?grant_type=client_credentials&client_id=' . $this->client_id . '&client_secret=' . $this->client_secret
		);

		if ( is_wp_error( $response ) || 200 != $response['response']['code'] )
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
		$url = go_marketo()->config( 'endpoint' ) . '/rest/v1/lead/' . absint( $id ) . '.json';

		$results = $this->marketo_rest_http( $url );

		if ( is_wp_error( $results ) )
		{
			return $results;
		}

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
	 * @return mixed Array of lead objects, or WP_Error if we encountered an error
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

		$url = go_marketo()->config( 'endpoint' ) . '/rest/v1/leads.json?filterType=' . urlencode( $filter_type ) . '&filterValues=' . $filter_values;

		if ( ! empty( $fields ) )
		{
			$fields = implode( ',', array_map( 'urlencode', $fields ) );
			$url .= '&fields=' . sanitize_text_field( $fields );
		}

		return $this->marketo_rest_http( $url );
	}//END get_leads

	/**
	 * update a single lead. if the lead does not exist by email or
	 * by wpid, then it will be created in Marketo.
	 *
	 * @param array $lead list of attributes for this lead. it must contain
	 *  an 'email' field, and optionally an Marketo 'id' field. the rest of
	 *  the fields are data on the lead that we want to sync to Marketo.
	 * @return mixed the Marketo id of the updated or created lead if the
	 *  update was successful, or WP_Error if we got an error.
	 */
	public function update_lead( $lead )
	{
		if ( empty( $lead['email'] ) && empty( $lead['id'] ) )
		{
			return new WP_Error( 'missing_field', 'Missing both "email" and "id" fields' );
		}

		// build our post param
		$post_data = new stdClass;
		$post_data->action = 'createOrUpdate';
		$post_data->lookupField = empty( $lead['id'] ) ? 'email' : 'id';
		$post_data->input = array( $lead );

		$response = $this->marketo_rest_http(
			go_marketo()->config( 'endpoint' ) . '/rest/v1/leads.json',
			'POST',
			$post_data
		);

		if ( is_wp_error( $response ) )
		{
			return $response;
		}

		if ( ! empty( $response[0]->errors ) )
		{
			return new WP_Error( $response[0]->errors[0]->code, $response[0]->errors[0]->message, $response );
		}

		// something went wrong
		if ( ! isset( $response[0]->id ) )
		{
			return new WP_Error( $response[0]->status, 'createOrUpdate failed', $response );
		}

		return $response[0]->id;
	}//END update_lead

	/**
	 * Add a lead to a list
	 *
	 * @param string $list_id id of the list to add the lead to
	 * @param string $lead_id id of the lead to add to the list
	 * @return mixed the lead id if the add was successful, or WP_Error if not.
	 */
	public function add_lead_to_list( $list_id, $lead_id )
	{
		$url = go_marketo()->config( 'endpoint' ) . '/rest/v1/lists/' . $list_id . '/leads.json';

		$input = new stdClass();
		$input->input = array();
		$input->input[0] = new stdClass();
		$input->input[0]->id = $lead_id;

		$response = $this->marketo_rest_http( $url, 'POST', $input );

		if ( is_wp_error( $response ) )
		{
			return $response;
		}

		if ( 1 != count( $response ) || ! isset( $response[0]->id ) || 'added' != $response[0]->status )
		{
			return new WP_Error( 'list_add_error', 'Error adding lead ' . $lead_id . ' to list ' . $list_id, $response );
		}

		return $response[0]->id;
	}//END add_lead_to_list

	/**
	 * make an HTTP GET request to Marketo's RESET API. Mainly we add the
	 * authorization token to the HTTP header.
	 *
	 * @param string $url the url to request, query vars included.
	 * @param string $method (optional) GET or POST. default is GET
	 * @param object $body (optional) used for POST and will be converted
	 *  to JSON
	 * @return array results from the HTTP GET request. or WP_Error if we
	 *  encountered an HTTP error
	 */
	private function marketo_rest_http( $url, $method = 'GET', $body = NULL )
	{
		// add the auth header
		$args = array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $this->get_auth_token(),
			)
		);

		if ( 'POST' == $method )
		{
			$args['method'] = 'POST';
			if ( ! empty( $body ) )
			{
				$args['body'] = json_encode( $body );
				$args['headers']['Content-type'] = 'application/json';
			}
			$response = wp_remote_post( $url, $args );
		}//END if
		else
		{
			$response = wp_remote_get( $url, $args );
		}

		if ( is_wp_error( $response ) )
		{
			return $response;
		}

		if ( 200 != $response['response']['code'] )
		{
			return new WP_Erorr( 'http_error', 'Marketo API returned HTTP ' . $response['response']['code'], $response );
		}

		return json_decode( $response['body'] )->result;
	}//END marketo_rest_http
}//END class