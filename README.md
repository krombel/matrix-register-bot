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

This bot takes care for user accounts. So it stores the credentials itself and provides ways to access them via matrix-synapse-rest-auth or mxisd.
## How to install

- Copy `config.sample.php` to `config.php` and configure the bot as you can find there
- Configure your webserver to publish the folder `public`.
  The folder `internal` contains files that can be accessed by mxisd or matrix-synapse-rest-auth or else via a reverse proxy
- To integrate with [matrix-synapse-rest-auth](https://github.com/kamax-io/matrix-synapse-rest-auth):
  - `/_matrix-internal/identity/v1/check_credentials` should map to `internal/login.php`
- To integrate with [mxisd](https://github.com/kamax-io/mxisd): Have a look at [the docs](https://github.com/kamax-io/mxisd/blob/master/docs/backends/rest.md) and apply as follows:


| Key                            | file which handles that       | Description                                          |
|--------------------------------|-------------------------------|------------------------------------------------------|
| rest.endpoints.auth            | internal/login.php            | Validate credentials and get user profile            |
| rest.endpoints.directory       | internal/directory_search.php | Search for users by arbitrary input                  |
| rest.endpoints.identity.single | internal/identity_single.php  | Endpoint to query a single 3PID                      |
| rest.endpoints.identity.bulk   | internal/identity_bulk.php    | Endpoint to query a list of 3PID                     |


## Implement usage of additional features:
### Use the ChangePasswortInterceptor:

You need a reverse proxy which maps `/_matrix/client/r0/account/password` to `internal/intercept_change_password.php`.
Here is an example for nginx:

```
        location /_matrix/client/r0/account/password {
                proxy_pass http://localhost/mxbot/internal/intercept_change_password.php;
                proxy_set_header X-Forwarded-For $remote_addr;
        }
```