# matrix-register-bot

This bot provides a two-step-registration for matrix ([synapse](https://github.com/matrix-org/synapse)).

This is done in several steps:
- potential new user registers on a bot-provided site
- user has to verify its mail address
- bot sends a message to predefined room with a registration notification.
- users in that room now can approve or decline the registration.
- When approved
  - the bot creates short time credentials
  - sends them to the user
  - stores them encrypted in own databas or uses that as initial password for registration

To configure synapse so that the users can login that were created via this bot you can either 
- set `operationMode=synapse` so the bot uses the register api to push the new users to synapse or 
- integrate it via [matrix-synapse-rest-auth](https://github.com/kamax-io/matrix-synapse-rest-auth#integrate) by configuring your system to point at `internal/login.php`.

When using `operationMode=local` you can have the following benefits (some require [mxisd](https://github.com/kamax-io/mxisd/blob/master/docs/backends/rest.md))
- Automatically set the display name based on first and last name on first login
- Use the 3PID lookup for other users (only email)
- Search for users that you have not seen yet

## How to install

- Copy `config.sample.php` to `config.php` and configure the bot as you can find there
- Configure your webserver to have the folder `public` accessible via web.
  The folder `internal` contains files that only provide API access. They can be accessed by mxisd or matrix-synapse-rest-auth 
- To integrate with [matrix-synapse-rest-auth](https://github.com/kamax-io/matrix-synapse-rest-auth):
  - `/_matrix-internal/identity/v1/check_credentials` should map to `internal/login.php`
- To integrate with [mxisd](https://github.com/kamax-io/mxisd): Have a look at [the docs of mxisd](https://github.com/kamax-io/mxisd/blob/master/docs/backends/rest.md) and apply as follows:


| Key                            | file which handles that       | Description                                          |
|--------------------------------|-------------------------------|------------------------------------------------------|
| rest.endpoints.auth            | internal/login.php            | Validate credentials and get user profile            |
| rest.endpoints.directory       | internal/directory_search.php | Search for users by arbitrary input                  |
| rest.endpoints.identity.single | internal/identity_single.php  | Endpoint to query a single 3PID                      |
| rest.endpoints.identity.bulk   | internal/identity_bulk.php    | Endpoint to query a list of 3PID                     |


## Further notes:

### This bot sends mails
To allow the bot to verify the email address of the user and to interact with them e.g. in case of approval this bot needs a running mailserver configuration.
This bot relies on php to be properly configured.

### Use the ChangePasswortInterceptor (if `operationMode=local`)

To allow users to change their pasword you need a reverse proxy which maps `/_matrix/client/r0/account/password` to `internal/intercept_change_password.php`.
Here is an example for nginx:
```
        location /_matrix/client/r0/account/password {
                proxy_pass http://localhost/mxbot/internal/intercept_change_password.php;
                proxy_set_header X-Forwarded-For $remote_addr;
        }
```
### Chat
For further questions, comments, feedback and more come and talk in [#matrix-register-bot:msg-net.de](https://matrix.to/#/#matrix-register-bot:msg-net.de)
