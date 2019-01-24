# matrix-register-bot
![state: alpha](https://img.shields.io/badge/state-alpha-yellowgreen.svg)
[![#matrix-register-bot:msg-net.de](https://img.shields.io/badge/matrix-%23matrix--register--bot%3Amsg--net.de-brightgreen.svg)](https://matrix.to/#/#matrix-register-bot:msg-net.de)

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

There are two operation modes available:
- `operationMode=synapse`
    - No adjustments on your running environment are required. This bot uses the the [Shared-Secret Registration of synapse](https://github.com/matrix-org/synapse/blob/master/docs/admin_api/register_api.rst) to register the users.
- `operationMode=local`:
    - Bot handles user management. Therefore it stores the user-data and uses [matrix-synapse-rest-auth](https://github.com/kamax-io/matrix-synapse-rest-auth#integrate) to authenticate the users.
    - This way it is possible to set the display name of a user on first login (first- and lastname instead of username)
    - The email address of the user can be used to implement third party lookup (requires [mxisd](https://github.com/kamax-io/mxisd/blob/master/docs/stores/rest.md))
    - search for users you have not seen yet but are available on the server

## Requirements

- Working PHP environment with
  - database connection provider \[one of sqlite, mysql, postgres\]
  - curl extension
  - mail capability to interact with the users (verification, approval (+ initial password), notifications)
      - either via sendmail or with credentials
- [composer](https://getcomposer.org) installed
- [matrix-synapse-rest-auth](https://github.com/kamax-io/matrix-synapse-rest-auth) when using `operationMode=local`

## How to install

```
git clone https://github.com/krombel/matrix-register-bot
cd matrix-register-bot
composer install
cp config.sample.php config.php
editor config.php
```
- Configure your webserver to have the folder `public` accessible via web.


When running `operationMode=local`:
- Configure your webserver to provide the folder `internal` internally. This is only meant to be accessible by mxisd and matrix-synapse-rest-auth 
- To integrate with [matrix-synapse-rest-auth](https://github.com/kamax-io/matrix-synapse-rest-auth):
  - `/_matrix-internal/identity/v1/check_credentials` should map to `internal/login.php`
- To integrate with [mxisd](https://github.com/kamax-io/mxisd): Have a look at [the docs of mxisd](https://github.com/kamax-io/mxisd/blob/master/docs/stores/rest.md) and apply as follows:


| Key                            | file which handles that       | Description                                          |
|--------------------------------|-------------------------------|------------------------------------------------------|
| rest.endpoints.auth            | internal/login.php            | Validate credentials and get user profile            |
| rest.endpoints.directory       | internal/directory_search.php | Search for users by arbitrary input                  |
| rest.endpoints.identity.single | internal/identity_single.php  | Endpoint to query a single 3PID                      |
| rest.endpoints.identity.bulk   | internal/identity_bulk.php    | Endpoint to query a list of 3PID                     |


## Further notes:

### Security: Passwords from registration form are stored in clear text
Currently the passwords which are typed in while capturing the register request are stored in clear text.
The bot needs to access them to trigger a register request with correct credentials.
It is currently strongly recommended to set `"getPasswordOnRegistration" => false` in your config!
This leads to autocreating passwords which will then be send to the users directly without storing it.

### Use the ChangePasswortInterceptor (if `operationMode=local`)

To allow users to change their pasword you need a reverse proxy which maps `/_matrix/client/r0/account/password` to `internal/intercept_change_password.php`.
Here is an example for nginx:
```
        location /_matrix/client/r0/account/password {
                proxy_pass http://localhost/mxbot/internal/intercept_change_password.php;
                proxy_set_header X-Forwarded-For $remote_addr;
        }
```
### The bot postpones some actions
There is a cron.php which implements retries and database cleanups (e.g. to remove a username claim)
For this run cron.php regularly with your system of choice.
A suggested interval is once per day