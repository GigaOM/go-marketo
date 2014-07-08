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
}//END class