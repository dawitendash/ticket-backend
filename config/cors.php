<?php

// config/cors.php

return [ 

    'paths' => ['api/*', 'v1/*', 'login', 'register'], 

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'http://localhost:3000', 
        'https://ticket.egletechnologies.com'
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true, // Keep this true if you're using cookies/sessions

];