<?php

declare(strict_types=1);

require_once __DIR__ . '/db/Database.php';
require_once __DIR__ . '/db/UserRepository.php';

require_once __DIR__ . '/MT5/HttpClient.php';
require_once __DIR__ . '/MT5/Session.php';
require_once __DIR__ . '/MT5/API/AUTH/Hash.php';
require_once __DIR__ . '/MT5/API/AUTH/Authentication.php';
require_once __DIR__ . '/MT5/API/USER/Add.php';
require_once __DIR__ . '/MT5/API/USER/CheckPassword.php';
