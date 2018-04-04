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
    // When you want to collect the password on registration set this to true
    "getPasswordOnRegistration" => false,
    // to define where the data should be stored:
    "databaseURI" => "sqlite:" . dirname(__FILE__) . "/db_file.sqlite",
    // credentials for sqlite not used
    "databaseUser" => "dbUser123",
    "databasePass" => "secretPassword",
        ]
?>
