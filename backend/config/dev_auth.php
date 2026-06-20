<?php

return [
    'enabled' => filter_var(env('DEV_AUTH_BYPASS_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
    'persona_email_domain' => env('DEV_AUTH_PERSONA_EMAIL_DOMAIN', 'dev.horizon.test'),
];
