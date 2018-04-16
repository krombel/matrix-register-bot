<?php
$config = [
    "homeserver" => "example.com",
    "access_token" => "To be used for sending the registration notification",

    // Which e-mail-adresse shall the bot use to send e-mails?
    "register_email" => 'register_bot@example.com',
    // Where should the bot post registration requests to?
    "register_room" => '$registerRoomID:example.com',

    // Where is the public part of the bot located? make sure you have a / at the end
    "webroot" => "https://myregisterdomain.net/",

    // optional: Do you have a place where howTo's are located? If not leave this value out
    "howToURL" => "https://my-url-for-storing-howTos.net",

    // set the mode of operation. Basically this defines where the data is stored:
    // - synapse (using the register endpoint - so no further auth config necessary
    // - local (recommended; using a table in the database to store credentials;
    //   synapse has to be configured to use that)
    "operationMode" => "local",

    // This setting is only required for operationMode = synapse
    "registration_shared_secret" => "SOME_SECRET_KEY_FROM_HOMESERVER_CONFIG",

    // When you want to collect the password on registration set this to true
    // only evaluated when operationMode = local
    "getPasswordOnRegistration" => false,

    // default language: one of [ en-gb | de-de ]
    "defaultLanguage" => "en-gb",

    // to define where the data should be stored:
    "databaseURI" => "sqlite:" . dirname(__FILE__) . "/db_file.sqlite",
    // credentials for sqlite not used
    "databaseUser" => "dbUser123",
    "databasePass" => "secretPassword",
        ]
?>
