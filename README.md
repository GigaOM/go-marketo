Gigaom Marketo Synchronization
==============================

* Requires: [go-syncuser](https://github.com/GigaOM/go-syncuser)
* Requires: [go-config](https://github.com/GigaOM/go-config)

* Ticket: https://github.com/GigaOM/legacy-pro/issues/3638

What's going on?
----------------

This plugin is triggered by the "go_syncuser_user" action callback fired by `go-syncuser`, and its config file uses the mapping functions in GO_Sync_User_Map to collect the user data to be sync'ed to Marketo.


Hacking notes
-------------

Struggles & annoyances
----------------------
