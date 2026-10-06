<?php

/*
 | Front controller for running MailBatch directly from the project folder URL,
 | e.g. http://localhost/mailbatch  (XAMPP/WAMP/shared hosting) - no `php artisan serve`, no virtual host.
 | Static files are served from /public by the .htaccess next to this file.
 */

require __DIR__.'/public/index.php';
