# matrix-register-bot

This bot provides a two-step-registration for matrix.

This is done in several steps:
- potential new user registers on a bot-provided side
- bot sends a message to predefined room with a registration notification.
- users in that room now can approve or decline the registration.
- When approved
  - the bot creates credentials
  - sends them to the user
  - stores them encrypted in own database
  - provides that credentials to [matrix-synapse-rest-auth](https://github.com/kamax-io/matrix-synapse-rest-auth#integrate) which has to be configured to query login.php

2nd step: Implement the other apis to integrade [mxisd](https://github.com/kamax-io/mxisd/blob/master/docs/backends/rest.md)

## How to install

- Copy `config.sample.php` to `config.php` and configure the bot as you can find there
- Configure your webserver to publish the folder `public` and configure.
  The folder `internal` contains files that can be accessed by mxisd or matrix-synapse-rest-auth
- To integrate with matrix-synapse-rest-auth:
  - `/_matrix-internal/identity/v1/check_credentials` should map to `internal/login.php`
- To integrate with mxisd: Have a look at [the docs](https://github.com/kamax-io/mxisd/blob/master/docs/backends/rest.md) and apply as follows:


| Key                            | file which handles that       | Description                                          |
|--------------------------------|-------------------------------|------------------------------------------------------|
| rest.endpoints.auth            | internal/login.php            | Validate credentials and get user profile            |
| rest.endpoints.directory       | internal/directory_search.php | Search for users by arbitrary input                  |
| rest.endpoints.identity.single | internal/identity_single.php  | Endpoint to query a single 3PID                      |
| rest.endpoints.identity.bulk   | internal/identity_bulk.php    | Endpoint to query a list of 3PID                     |
