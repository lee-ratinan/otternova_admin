<?php

/*
 | --------------------------------------------------------------------
 | App Namespace
 | --------------------------------------------------------------------
 |
 | This defines the default Namespace that is used throughout
 | CodeIgniter to refer to the Application directory. Change
 | this constant to change the namespace that all application
 | classes should use.
 |
 | NOTE: changing this will require manually modifying the
 | existing namespaces of App\* namespaced-classes.
 */
defined('APP_NAMESPACE') || define('APP_NAMESPACE', 'App');

/*
 | --------------------------------------------------------------------------
 | Composer Path
 | --------------------------------------------------------------------------
 |
 | The path that Composer's autoload file is expected to live. By default,
 | the vendor folder is in the Root directory, but you can customize that here.
 */
defined('COMPOSER_PATH') || define('COMPOSER_PATH', ROOTPATH . 'vendor/autoload.php');

/*
 |--------------------------------------------------------------------------
 | Timing Constants
 |--------------------------------------------------------------------------
 |
 | Provide simple ways to work with the myriad of PHP functions that
 | require information to be in seconds.
 */
defined('SECOND') || define('SECOND', 1);
defined('MINUTE') || define('MINUTE', 60);
defined('HOUR')   || define('HOUR', 3600);
defined('DAY')    || define('DAY', 86400);
defined('WEEK')   || define('WEEK', 604800);
defined('MONTH')  || define('MONTH', 2_592_000);
defined('YEAR')   || define('YEAR', 31_536_000);
defined('DECADE') || define('DECADE', 315_360_000);

/*
 | --------------------------------------------------------------------------
 | Exit Status Codes
 | --------------------------------------------------------------------------
 |
 | Used to indicate the conditions under which the script is exit()ing.
 | While there is no universal standard for error codes, there are some
 | broad conventions.  Three such conventions are mentioned below, for
 | those who wish to make use of them.  The CodeIgniter defaults were
 | chosen for the least overlap with these conventions, while still
 | leaving room for others to be defined in future versions and user
 | applications.
 |
 | The three main conventions used for determining exit status codes
 | are as follows:
 |
 |    Standard C/C++ Library (stdlibc):
 |       http://www.gnu.org/software/libc/manual/html_node/Exit-Status.html
 |       (This link also contains other GNU-specific conventions)
 |    BSD sysexits.h:
 |       http://www.gsp.com/cgi-bin/man.cgi?section=3&topic=sysexits
 |    Bash scripting:
 |       http://tldp.org/LDP/abs/html/exitcodes.html
 |
 */
defined('EXIT_SUCCESS')        || define('EXIT_SUCCESS', 0);        // no errors
defined('EXIT_ERROR')          || define('EXIT_ERROR', 1);          // generic error
defined('EXIT_CONFIG')         || define('EXIT_CONFIG', 3);         // configuration error
defined('EXIT_UNKNOWN_FILE')   || define('EXIT_UNKNOWN_FILE', 4);   // file not found
defined('EXIT_UNKNOWN_CLASS')  || define('EXIT_UNKNOWN_CLASS', 5);  // unknown class
defined('EXIT_UNKNOWN_METHOD') || define('EXIT_UNKNOWN_METHOD', 6); // unknown class member
defined('EXIT_USER_INPUT')     || define('EXIT_USER_INPUT', 7);     // invalid user input
defined('EXIT_DATABASE')       || define('EXIT_DATABASE', 8);       // database error
defined('EXIT__AUTO_MIN')      || define('EXIT__AUTO_MIN', 9);      // lowest automatically-assigned error code
defined('EXIT__AUTO_MAX')      || define('EXIT__AUTO_MAX', 125);    // highest automatically-assigned error code

/**
 | Default values
 */
define('SITE_TITLE', 'OtterNova Admin');
define('META_DESCRIPTION', 'Admin portal for OtterNova staff');
define('META_KEYWORDS', 'admin portal, backoffice, OtterNova');
define('LOCALE', 'en-US');
define('EXECUTIVE_DEPARTMENT', ['EXE']);
// ... whose dashboard is the Finance one (the API lets EXE and FIN read it)
define('FINANCE_DEPARTMENT', ['FIN']);
// ... whose dashboard is the Engineering one (the API lets EXE and ENG read it)
define('ENGINEERING_DEPARTMENT', ['ENG']);
// ... whose dashboard is the Client Experience one (the API lets EXE and CXD read it)
define('CLIENT_EXPERIENCE_DEPARTMENT', ['CXD']);
// otternova_department.department_code values that may use the ledger (the API checks the same list)
define('LEDGER_DEPARTMENTS', ['EXE', 'FIN']);
// ... and that may manage the plans, prices, payment methods and discounts (the API checks the same list)
define('PLAN_DEPARTMENTS', ['EXE', 'FIN']);
// ... and that may read the activity log, which holds every table's changes (the API checks the same list)
define('LOG_DEPARTMENTS', ['EXE', 'ENG']);
// ... and that may read the apps' error logs (writable/logs), which hold stack traces and request details
define('ERROR_LOG_DEPARTMENTS', ['EXE', 'ENG']);
// ... and that may read the email log, to answer whether a customer got an email (the API checks the same list)
define('EMAIL_LOG_DEPARTMENTS', ['EXE', 'CXD', 'ENG']);
// ... and that may read the Terms of Service and Privacy Policy versions and who accepted them (the API checks the
// same list); only the Executive Office (EXECUTIVE_DEPARTMENT) may publish or cancel a version
define('LEGAL_DEPARTMENTS', ['EXE', 'CXD']);
// ... and that may view the org chart, the staff's reporting lines (the API checks the same list)
define('ORG_CHART_DEPARTMENTS', ['EXE', 'HRD']);
// ... and that may review the businesses, their products and services and their variants: approve, flag, block and
// unblock them (the API checks the same list); only the Executive Office (MODERATION_REMOVE_DEPARTMENTS) removes for good
define('MODERATION_DEPARTMENTS', ['EXE', 'CXD']);
define('MODERATION_REMOVE_DEPARTMENTS', ['EXE']);
