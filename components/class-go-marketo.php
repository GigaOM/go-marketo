<?php
/**
 * class-go-marketo.php
 *
 * @author Gigaom <support@gigaom.com>
 */
class GO_Marketo
{
	private $meta_key_base = 'go_marketo';
	private $config = NULL;
	private $api = NULL;

	/**
	 * constructor method for the GO_Marketo class
	 */
	public function __construct()
	{
		// most hooks registered on init
		add_action( 'init', array( $this, 'init' ) );

		// listen for triggered user action from go-syncuser
		add_action( 'go_syncuser_user', array( $this, 'go_syncuser_user' ), 10, 2 );

		// listen for the action when the user's do_not_email user profile
		// is updated (unsubscribed or re-subscribed)
		add_action( 'go_user_profile_do_not_email_updated', array( $this, 'do_not_email_updated' ), 10, 2 );
	}//END __construct

	/**
	 * This function is triggered at 'init' to initialize all actions and
	 * hooks for the plugin to function. It makes callbacks to both internal
	 * functions as well as to the api class.
	 */
	public function init()
	{
		if ( ! $this->config() )
		{
			$this->log( 'Trying to register hooks without a valid config file.', __FUNCTION__ );
		}

		// Hooking admin_menu here because it's too late to do it in admin_init
		if ( is_admin() )
		{
			add_action( 'admin_init', array( $this, 'admin_init' ) );
		}
	}//END init

	/**
	 * admin_init
	 *
	 * Register all necessary aspects for the visual aspects of the plugin
	 * to appear throughout the WP Dashboard specific to MailChimp
	 * administration.
	 */
	public function admin_init()
	{
	}//END admin_init

	/**
	 * @return GO_Marketo_API a GO_Marketo_API object
	 */
	public function api()
	{
		if ( $this->api )
		{
			return $this->api;
		}

		require_once __DIR__ . '/class-go-marketo-api.php';
		$this->api = new GO_Marketo_API( $this->config( 'client_id' ), $this->config( 'client_secret' ) );

		return $this->api;
	}//END api

	/**
	 * @param string $suffix (optional) what to append to the plugin's
	 *  main meta key. an underscore (_) will be appended between the plugin's
	 *  base meta key and $suffix
	 * @return the meta key
	 */
	public function meta_key( $suffix = NULL )
	{
		if ( empty( $suffix ) )
		{
			return $this->meta_key_base;
		}

		return $this->meta_key_base . '_' . $suffix;
	}//END meta_key

	/**
	 * returns our current configuration, or a value in the configuration.
	 *
	 * @param string $key (optional) key to a configuration value
	 * @return mixed Returns the config array, or a config value if
	 *  $key is not NULL
	 */
	public function config( $key = NULL )
	{
		if ( empty( $this->config ) )
		{
			$this->config = apply_filters(
				'go_config',
				NULL,
				'go-marketo'
			);
		}//END if

		if ( ! empty( $key ) )
		{
			return isset( $this->config[ $key ] ) ? $this->config[ $key ] : NULL ;
		}

		return $this->config;
	}//END config

	/**
	 * hooked to the go_user_profile_do_not_email_updated action. 
	 * when $do_not_email is FALSE we make sure to subscribe the user
	 * and invoke a sync
	 *
	 * @param $user_id int the user id
	 * @param $do_not_email bool value of the do_not_email user profile
	 */
	public function do_not_email_updated( $user_id, $do_not_email )
	{
		// we can only act if $do_not_email is FALSE and we have a valid $user_id
		if ( $do_not_email || 0 >= $user_id )
		{
			return;
		}

		$this->api()->subscribe( $user_id );
	}//END do_not_email_updated

	/**
	 * this callback gets invoked when events configured in go-syncuser
	 * are fired.
	 *
	 * @param int $user_id ID of the user who triggered an event
	 * @param string $action the type action triggered. we're only
	 *  processing 'update' and 'delete'.
	 */
	public function go_syncuser_user( $user_id, $action )
	{
	}//END go_syncuser_user
}//END class

/**
 * singleton
 */
function go_marketo()
{
	global $go_marketo;

	if ( ! isset( $go_marketo ) )
	{
		$go_marketo = new GO_Marketo();
	}//END if

	return $go_marketo;
}//END go_marketo