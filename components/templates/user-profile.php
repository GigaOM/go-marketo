<div class="wrap go-marketo" id="go-marketo-on-demand-sync">
	<h3>Marketo Synchronization</h3>
	<div class="results">
		<?php echo $user_status; ?>
	</div>
	<button class="go-marketo-sync button-secondary sync" id="go-marketo-user-sync-btn" value="go_marketo_user_sync">Sync to Marketo</button>
	<input type="hidden" class="user" id="go-marketo-user-sync-user" name="go_marketo_user_sync_user" value="<?php echo absint( $user->ID ); ?>" />
	<input type="hidden" class="nonce" id="go-marketo-user-sync-user-nonce" name="go_marketo_nonce" value="<?php echo $go_marketo_nonce; ?>" />
	<span class="feedback"></span>
</div>
