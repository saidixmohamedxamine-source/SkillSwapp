<?php
/**
 * Configuration File
 */

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'ismo_skillswap_v2');

// Site Configuration
define('SITE_NAME', 'ISMO-SkillSwap');
define('SITE_SUBTITLE', 'Student Skill Sharing');
define('SITE_URL', 'http://localhost/PRJ/');

// Environment
define('ENVIRONMENT', 'development'); // development or production

// Error Reporting
if (ENVIRONMENT === 'development') {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}

// Session Configuration
/*ini_set('session.use_only_cookies', 1);
ini_set('session.use_strict_mode', 1);*/
