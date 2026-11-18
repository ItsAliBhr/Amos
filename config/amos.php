<?php

return [
    // Installed when storage/app/installed.lock exists, or explicitly forced via env (tests / SSH installs).
    'installed' => env('AMOS_INSTALLED'),
    'lock_file' => storage_path('app/installed.lock'),
    'force_https' => (bool) env('FORCE_HTTPS', false),
    'login_max_attempts' => (int) env('LOGIN_MAX_ATTEMPTS', 5),
    'login_decay_seconds' => (int) env('LOGIN_DECAY_SECONDS', 300),
    'version' => '1.0.0',
];
