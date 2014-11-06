<?php

if(!defined('CMS_FOLDER')) define('CMS_FOLDER', "../cms");
if(!defined('SUBDOM_FOLDER')) define('SUBDOM_FOLDER', false);
if(!defined('ADMIN_BACKUP')) define('ADMIN_BACKUP', 'adm.bak');
if(!defined('ADMIN_FOLDER')) define('ADMIN_FOLDER', 'adm');
if(!defined('USER_FOLDER')) define('USER_FOLDER', 'usr');
if(!defined('USER_BACKUP')) define('USER_BACKUP', 'usr.bak');
if(!defined('FILES_FOLDER')) define('FILES_FOLDER', 'files');
if(!defined('TEMP_FOLDER')) define('TEMP_FOLDER', 'temp');
if(!defined('THEMES_FOLDER')) define('THEMES_FOLDER', 'themes');
if(!defined('CMSRES_FOLDER')) define('CMSRES_FOLDER', false);
if(!defined('RES_FOLDER')) define('RES_FOLDER', false);
if(!defined('LOG_FOLDER')) define('LOG_FOLDER', 'log');
if(!defined('VER_FOLDER')) define('VER_FOLDER', 'ver');
if(!defined('CACHE_FOLDER')) define('CACHE_FOLDER', 'cache');
if(!defined('PLUGIN_FOLDER')) define('PLUGIN_FOLDER', 'plugins');

define('VARIABLE_PATTERN', '(?:[a-z]+-)?[a-z_]+');
define('FILEPATH_PATTERN', "(?:[a-zA-Z0-9_-]+\/)*[a-zA-Z0-9._-]+\.[a-z0-9]{2,4}");
define('CORE_FOLDER', 'core');
define('FILE_HASH_ALGO', 'crc32b');
define('CMS_VERSION', '0.2.0');

?>