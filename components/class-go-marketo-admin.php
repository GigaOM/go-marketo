<?php
/**
 * class-go-marketo-admin.php
 *
 * admin dashboard and ajax related functions
 *
 * @author Gigaom <support@gigaom.com>
 */
class GO_Marketo_Admin
{
	public $webhooking = FALSE; // are we handling a webhook?

	private $core = NULL;

	/**
	 * constructor
	 *
	 * @param GO_Marketo the GO_Marketo singleton object
	 */
	public function __construct( $core )
	{
		$this->core = $core;
		add_action( 'admin_init', array( $this, 'admin_init' ) );
	}//END __construct

	/**
	 * admin_init
	 *
	 * Register all necessary aspects for the visual aspects of the plugin
	 * to appear throughout the WP Dashboard specific to MailChimp
	 * administration.
	 */
	public function admin_init()
	{
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );

		add_action( 'show_user_profile', array( $this, 'show_user_profile' ) );
		add_action( 'edit_user_profile', array( $this, 'show_user_profile' ) );

		add_action( 'wp_ajax_go_marketo_user_sync', array( $this, 'user_sync_ajax' ) );

		add_action( 'wp_ajax_go-marketo-webhook', array( $this, 'webhook_ajax' ) );
		add_action( 'wp_ajax_nopriv_go-marketo-webhook', array( $this, 'webhook_ajax' ) );
	}//END admin_init

	/**
	 * register our custom plugin js file
	 *
	 * @param string $hook The page hook that this should be enqueued for
	 */
	public function admin_enqueue_scripts( $hook )
	{
		if ( 'user-edit.php' == $hook )
		{
			wp_enqueue_script( 'go_marketo_admin_js', plugins_url( '/js/go-marketo-admin.js', __FILE__ ), array( 'jquery' ), $this->core->version, TRUE );
		}
	}//END admin_enqueue_scripts

	/**
	 * Function to display the user's Marketo sync status on the user's
	 * profile admin section
	 *
	 * @param object $user The WP_User being viewed
	 */
	public function show_user_profile( $user )
	{
		// get the output of display_user_profile_status_section() and
		// pass it to user-profile.php template. we generate the HTML
		// in display_user_profile_status_section() so it can also be
		// used by the admin ajax call to update the result of a sync
		ob_start();
		$this->display_user_profile_status_section( $user );
		$user_status = ob_get_clean();

		$go_marketo_nonce = wp_create_nonce( 'go-marketo' );

		include_once __DIR__ . '/templates/user-profile.php';
	}//END show_user_profile

	/**
	 * Display the user's Marketo subscription status information
	 *
	 * @param object $user The WP user being viewed
	 */
	public function display_user_profile_status_section( $user )
	{
		$user_meta = get_user_meta( $user->ID, $this->core->meta_key(), TRUE );
		if ( empty( $user_meta ) )
		{
			?>
			<p class="description">User is not synced to Marketo yet</p>
			<?php
			return;
		}//END if

		?>
		<p>Last synchronized on: <span class="description"><?php echo date( 'Y-m-d H:i:s', $user_meta[ 'timestamp' ] ); ?></span></p>
		<p><a href="https://app-sjo.marketo.com/leadDatabase/loadLeadDetail?leadId=<?php echo esc_attr( $user_meta['marketo_id'] ); ?>" target="_blank">Marketo lead <?php echo esc_html( $user_meta['marketo_id'] ); ?></a></p>
		<?php
	}//END display_user_profile_status_section

	/**
	 * ajax callback for the Sync button on the user profile page to
	 * avoid saving the user in the WP database
	 */
	public function user_sync_ajax()
	{
		// only allowed for people who can edit users
		if ( ! current_user_can( 'edit_users' ) )
		{
			do_action( 'go_slog', 'go-marketo', 'called by non-admin user', array( 'user' => get_current_user_id() ) );
			wp_die();
		}

		if ( ! isset( $_POST['go_marketo_nonce'] ) || ! wp_verify_nonce( $_POST['go_marketo_nonce'], 'go-marketo' ) )
		{
			do_action( 'go_slog', 'go-marketo', 'invalid nonce', array( 'user' => get_current_user_id() ) );
			wp_die();
		}

		if ( ! $user = get_userdata( wp_filter_nohtml_kses( $_POST['go_marketo_user_sync_user'] ) ) )
		{
			do_action( 'go_slog', 'go-marketo', 'invalid user id', array( 'user' => $_POST['go_marketo_user_sync_user'] ) );
			wp_die();
		}

		// set go-syncuser's debug flag on temporarily (not saved to options)
		go_syncuser()->set_debug( TRUE );

		$this->core->go_syncuser_user( $user->ID, 'update' );

		$this->display_user_profile_status_section( $user );

		wp_die();
	}//END user_sync_ajax

	/**
	 * Function used to catch hooks being fired from Marketo
	 *
	 * https://accounts.gigaom.com/wp-admin/admin-ajax.php?action=go-marketo-webhook&marketowhs=lalala
	 */
	public function webhook_ajax()
	{
		if ( go_syncuser()->debug() )
		{
			do_action( 'go_slog', 'go-mailchimp', 'webhook_ajax()' );
		}

		if ( empty( $_POST['marketowhs'] ) || $this->core->config( 'webhook_secret' ) != $_POST['marketowhs'] )
		{
			do_action( 'go_slog', 'go-mailchimp', 'invalid webhook secret' );
			wp_die();
		}

		$this->webhooking = TRUE;

		// we only expect one event type currently ("unsubscribe")
		if ( ! empty( $_POST[ 'event' ] ) && 'unsubscribe' == $_POST[ 'event' ] )
		{
			if ( ! empty( $_POST['wpid'] ) )
			{
				$user = get_user_by( 'id', absint( $_POST['wpid'] ) );
			}
			elseif ( ! empty( $_POST['email'] ) )
			{
				$user = get_user_by( 'email', sanitize_email( $_POST[ 'email' ] ) );
			}

			// update the do_not_email user profile
			if ( ! empty( $user ) && ! $this->core->do_not_email( $user->ID ) )
			{
				do_action( 'go_user_profile_do_not_email', $user->ID, TRUE );

				if ( go_syncuser()->debug() )
				{
					do_action( 'go_slog', 'go-mailchimp', 'webhook_ajax(): set do_not_email flag to TRUE', array( 'user_id' => $user->ID ) );
				}
			}
			elseif ( go_syncuser()->debug() )
			{
				do_action( 'go_slog', 'go-mailchimp', 'webhook_ajax(): missing user wpid or email' );
			}
		}//END if
		elseif ( go_syncuser()->debug() )
		{
			do_action( 'go_slog', 'go-mailchimp', 'webhook_ajax(): missing or unknown "event" param', array( 'event' => $_POST['event'] ) );
		}

		$this->webhooking = FALSE;

		wp_die();
	}//END webhook_ajax
}//END class
