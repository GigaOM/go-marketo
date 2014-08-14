<?php
/**
 * class-go-marketo.php
 *
 * @author Gigaom <support@gigaom.com>
 */
class GO_Marketo
{
	public $version = '0.1';

	private $meta_key_base = 'go_marketo';
	private $config = NULL;
	private $api = NULL;
	private $admin = NULL;

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

		if ( is_admin() )
		{
			$this->admin();
		}
	}//END init

	public function admin()
	{
		if ( ! $this->admin )
		{
			require_once __DIR__ . '/class-go-marketo-admin.php';
			$this->admin = new GO_Marketo_Admin( $this );
		}

		return $this->admin;
	}//END admin

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
	 * check if the user is flagged for not being emailed
	 *
	 * @param $user_id ID of the user to check
	 */
	public function do_not_email( $user_id )
	{
		return apply_filters( 'go_user_profile_do_not_email_check', FALSE, $user_id );
	}//END do_not_email

	/**
	 * hooked to the go_user_profile_do_not_email_updated action.
	 * we set the unsubscribed flag according to $do_not_email's value
	 * and then call subscribe() or unsubscribe().
	 *
	 * @param $user_id int the user id
	 * @param $do_not_email bool value of the do_not_email user profile
	 */
	public function do_not_email_updated( $user_id, $do_not_email )
	{
		// ignore this call if we're inside a Marketo webhook else
		// we'll get into a loopy loop
		if ( $this->admin->webhooking )
		{
			return;
		}

		// we can only act if we have a valid $user_id
		if ( 0 >= $user_id )
		{
			return;
		}

		$this->go_syncuser_user( $user_id, ( $do_not_email ? 'unsubscribe' : 'subscribe' ) );
	}//END do_not_email_updated

	/**
	 * this callback gets invoked when events configured in go-syncuser
	 * are fired.
	 *
	 * @param int $user_id ID of the user who triggered an event
	 * @param string $action the type action triggered. we're only
	 *  processing 'add', 'update', 'delete' and 'unsubscribe'.
	 */
	public function go_syncuser_user( $user_id, $action )
	{
		$user = get_user_by( 'id', $user_id );

		if ( empty( $user ) )
		{
			return; // invalid user id
		}

		$this->sync_user( $user, $action );
	}//END go_syncuser_user

	/**
	 * retrieve the marketo id from the user meta
	 *
	 * @param WP_User $user a WP_User object
	 * @return the user's marketo id if it exists in our db, or NULL if not
	 */
	public function get_marketo_id( $user )
	{
		$meta = get_user_meta( $user->ID, $this->meta_key(), TRUE );
		if ( ! empty( $meta['marketo_id'] ) )
		{
			return $meta['marketo_id'];
		}

		return NULL;
	}//END get_marketo_id

	/**
	 * this callback gets invoked when events configured in go-syncuser
	 * are fired.
	 *
	 * @param WP_User $user the user who's going to be sync'ed to Marketo
	 * @param string $action the type action triggered. we're only
	 *  processing 'add', 'update', 'delete', 'subscribe', and 'unsubscribe'.
	 */
	public function sync_user( $user, $action )
	{
		$lead_info = array(
			'wpid' => $user->ID,
			'email' => $user->user_email,
		);

		// check if we have a Marketo id for the user
		$meta = get_user_meta( $user->ID, $this->meta_key(), TRUE );

		if ( ! empty( $meta['marketo_id'] ) )
		{
			$lead_info['id'] = $meta['marketo_id'];
		}

		if ( 'delete' == $action || 'unsubscribe' == $action )
		{
			// unsubscribe the user from Marketo if this is a deletion
			$lead_info['unsubscribed'] = TRUE;
			$lead_info['unsubscribedReason'] = 'user unsubscribed';
		}
		elseif ( 'add' == $action || 'subscribe' == $action )
		{
			// make sure the unsubscribed flag is FALSE if this is an 'add'
			// or 'subscribe'
			$lead_info['unsubscribed'] = FALSE;
			$lead_info['unsubscribedReason'] = '';
		}

		// collect all other lead info
		$lead_info = array_merge( $this->get_sync_fields( $user ), $lead_info );

		$response = $this->api()->update_lead( $lead_info );

		if ( ! is_wp_error( $response ) ) // save the Marketo id
		{
			// do not trigger a sync while we're updating user meta as a
			// result of a sync
			go_syncuser()->suspend_triggers( TRUE );
			update_user_meta( $user->ID, $this->meta_key(), array( 'marketo_id' => $response, 'timestamp' => time() ) );
			go_syncuser()->suspend_triggers( FALSE );

			// and add the lead to The List
			$the_list = $this->config( 'list' );

			if ( ! empty( $the_list ) )
			{
				$this->api()->add_lead_to_list( $the_list['id'], $response );
			}
		}//END if
	}//END sync_user

	/**
	 * Collect data fields associated with $user to be sync'ed to Marketo
	 *
	 * @param WP_User $user the user who's going to be sync'ed to Marketo
	 */
	public function get_sync_fields( $user )
	{
		$results = array();

		$field_map = $this->config( 'field_map' );

		if ( empty( $field_map ) )
		{
			return $results;
		}

		foreach ( $field_map as $field_name => $field_config )
		{
			$results[ $field_name ] = go_syncuser_map()->map_field( $user, $field_config );
		}//END foreach

		return $results;
	}//END get_sync_fields
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