<?php

	if(!defined('INSTALL_REQUIREMENTS_PASSED') || !INSTALL_REQUIREMENTS_PASSED){
		die('<h1>Symphony Fatal Error</h1><p>This file cannot be accessed directly</p>');
	}

	$clean_path = $_SERVER["HTTP_HOST"] . dirname($_SERVER["PHP_SELF"]);
	$clean_path = rtrim($clean_path, '/\\');
	$clean_path = preg_replace('/\/{2,}/i', '/', $clean_path);

	define('_INSTALL_DOMAIN_', $clean_path);
	define('_INSTALL_URL_', 'http://' . $clean_path);

	## If its not an update, we need to set a couple of important constants.
	define('__IN_SYMPHONY__', true);
	define('DOCROOT', './');

	$rewrite_base = trim(dirname($_SERVER['PHP_SELF']), '/\\');

	if(strlen($rewrite_base) > 0){
		$rewrite_base .= '/';
	}

	define('REWRITE_BASE', $rewrite_base);

	## Include some parts of the Symphony engine
	require_once(CORE . '/class.log.php');
	require_once(CORE . '/class.datetimeobj.php');
	require_once(TOOLKIT . '/class.mysql.php');
	require_once(TOOLKIT . '/class.xmlelement.php');
	require_once(TOOLKIT . '/class.widget.php');

	define('CRLF', "\r\n");

	define('BAD_BROWSER', 0);
	define('MISSING_MYSQL', 3);
	define('MISSING_ZLIB', 5);
	define('MISSING_XSL', 6);
	define('MISSING_XML', 7);
	define('MISSING_PHP', 8);
	define('MISSING_MOD_REWRITE', 9);

	$header = '<?xml version="1.0" encoding="utf-8"?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en">
	<head>
		<title><!-- TITLE --></title>
		<link rel="stylesheet" type="text/css" href="'.kINSTALL_ASSET_LOCATION.'/main.css" />
		<script type="text/javascript" src="'.kINSTALL_ASSET_LOCATION.'/main.js"></script>
	</head>' . CRLF;

	define('kHEADER', $header);

	$footer = '
</html>';

	define('kFOOTER', $footer);

	$warnings = array(
		'no-symphony-dir' => __('No <code>/symphony</code> directory was found at this location. Please upload the contents of Symphony\'s install package here.'),
		'no-write-permission-workspace' => __('Symphony does not have write permission to the existing <code>/workspace</code> directory. Please modify permission settings on this directory and its contents to allow this, such as with a recursive <code>chmod -R</code> command.'),
		'no-write-permission-manifest' => __('Symphony does not have write permission to the <code>/manifest</code> directory. Please modify permission settings on this directory and its contents to allow this, such as with a recursive <code>chmod -R</code> command.'),
		'no-write-permission-root' => __('Symphony does not have write permission to the root directory. Please modify permission settings on this directory. This is necessary only if you are not including a workspace, and can be reverted once installation is complete.'),
		'no-write-permission-htaccess' => __('Symphony does not have write permission to the temporary <code>htaccess</code> file. Please modify permission settings on this file so it can be written to, and renamed.'),
		'no-write-permission-symphony' => __('Symphony does not have write permission to the <code>/symphony</code> directory. Please modify permission settings on this directory. This is necessary only during installation, and can be reverted once installation is complete.'),
		'no-database-connection' => __('Symphony was unable to connect to the specified database. You may need to modify host or port settings.'),
		'database-incorrect-version' => __('Symphony requires <code>MySQL 4.1</code> or greater to work. This requirement must be met before installation can proceed.'),
		'database-table-clash' => __('The table prefix <code><!-- TABLE-PREFIX --></code> is already in use. Please choose a different prefix to use with Symphony.'),
		'user-password-mismatch' => __('The password and confirmation did not match. Please retype your password.'),
		'user-invalid-email' => __('This is not a valid email address. You must provide an email address since you will need it if you forget your password.'),
		'user-no-username' => __('You must enter a Username. This will be your Symphony login information.'),
		'user-no-password' => __('You must enter a Password. This will be your Symphony login information.'),
		'user-no-name' => __('You must enter your name.')
	);

	$notices = array(
		'existing-workspace' => __('An existing <code>/workspace</code> directory was found at this location. Symphony will use this workspace.')
	);

	$languages = array();
	$current = $_REQUEST['lang'];
	foreach(Lang::getAvailableLanguages(false) as $code => $lang) {
		$class = '';
		if($current == $code || ($current == NULL && $code == 'en')) $class = ' class="selected"';
		$languages[] = '<li' . $class . '><a href="?lang=' . $code . '">' . $lang . '</a></li>';
	}
	$languages = implode('', $languages);


	function installResult(&$Page, &$install_log, $start){

		if(!defined('_INSTALL_ERRORS_')){

			$install_log->writeToLog("============================================", true);
			$install_log->writeToLog("INSTALLATION COMPLETED: Execution Time - ".max(1, time() - $start)." sec (" . date("d.m.y H:i:s") . ")", true);
			$install_log->writeToLog("============================================" . CRLF . CRLF . CRLF, true);

		}else{

			$install_log->pushToLog(_INSTALL_ERRORS_, E_ERROR, true);
			$install_log->writeToLog("============================================", true);
			$install_log->writeToLog("INSTALLATION ABORTED: Execution Time - ".max(1, time() - $start)." sec (" . date("d.m.y H:i:s") . ")", true);
			$install_log->writeToLog("============================================" . CRLF . CRLF . CRLF, true);

			$Page->setPage('failure');
		}

	}

	function writeConfig($dest, $conf, $mode){

		$string	 = "<?php\n";

		$string .= "\n\t\$settings = array(";
		foreach($conf['settings'] as $group => $data){
			$string .= "\r\n\r\n\r\n\t\t###### ".strtoupper($group)." ######";
			$string .= "\r\n\t\t'$group' => array(";
			foreach($data as $key => $value){
				$string .= "\r\n\t\t\t'$key' => ".(strlen($value) > 0 ? "'".addslashes($value)."'" : 'NULL').",";
			}
			$string .= "\r\n\t\t),";
			$string .= "\r\n\t\t########";
		}
		$string .= "\r\n\t);\n\n";

		return General::writeFile($dest . '/config.php', $string, $mode);

	}

	function fireSql(&$db, $data, &$error, $use_server_encoding = false){

		if($use_server_encoding) {
			$data = str_replace('DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci', NULL, $data);
			$data = str_replace('COLLATE utf8_unicode_ci', NULL, $data);
		}

		## Silently attempt to change the storage engine. This prevents INNOdb errors.
		$db->query('SET storage_engine=MYISAM', $e);

		$queries = preg_split('/;[\\r\\n]+/', $data, -1, PREG_SPLIT_NO_EMPTY);

		if (is_array($queries) && !empty($queries)) foreach($queries as $sql) {
			if (strlen(trim($sql)) > 0) $result = $db->query($sql);
			if (!$result){
				$err = $db->getLastError();
				$error = $err['num'] . ': ' . $err['msg'];
				return false;
			}
		}

		return true;

	}


	function checkRequirement($item, $type, $expected){

		switch($type){

			case 'func':

				$test = function_exists($item);
				if($test != $expected) return false;
				break;

			case 'setting':
				$test = ini_get($item);
				if(strtolower($test) != strtolower($expected)) return false;
				break;

			case 'ext':
				foreach(explode(':', $item) as $ext){
					$test = extension_loaded($ext);
					if($test == $expected) return true;
				}

				return false;
				break;

			 case 'version':
				if(version_compare($item, $expected, '>=') != 1) return false;
				break;

			 case 'permission':
				if(!is_writable($item)) return false;
				break;

			 case 'remote':
				$result = curler($item);
				if(strpos(strtolower($result), 'error') !== false) return false;
				break;

		}

		return true;

	}

	if(!function_exists('timezone_identifiers_list')){
		function timezone_identifiers_list(){
			return array(
				'Africa/Abidjan', 'Africa/Accra', 'Africa/Addis_Ababa', 'Africa/Algiers', 'Africa/Asmera',
				'Africa/Bamako', 'Africa/Bangui', 'Africa/Banjul', 'Africa/Bissau', 'Africa/Blantyre',
				'Africa/Brazzaville', 'Africa/Bujumbura', 'Africa/Cairo', 'Africa/Casablanca', 'Africa/Ceuta',
				'Africa/Conakry', 'Africa/Dakar', 'Africa/Dar_es_Salaam', 'Africa/Djibouti', 'Africa/Douala',
				'Africa/El_Aaiun', 'Africa/Freetown', 'Africa/Gaborone', 'Africa/Harare', 'Africa/Johannesburg',
				'Africa/Kampala', 'Africa/Khartoum', 'Africa/Kigali', 'Africa/Kinshasa', 'Africa/Lagos',
				'Africa/Libreville', 'Africa/Lome', 'Africa/Luanda', 'Africa/Lubumbashi', 'Africa/Lusaka',
				'Africa/Malabo', 'Africa/Maputo', 'Africa/Maseru', 'Africa/Mbabane', 'Africa/Mogadishu',
				'Africa/Monrovia', 'Africa/Nairobi', 'Africa/Ndjamena', 'Africa/Niamey', 'Africa/Nouakchott',
				'Africa/Ouagadougou', 'Africa/Porto-Novo', 'Africa/Sao_Tome', 'Africa/Timbuktu', 'Africa/Tripoli',
				'Africa/Tunis', 'Africa/Windhoek', 'America/Adak', 'America/Anchorage', 'America/Anguilla',
				'America/Antigua', 'America/Araguaina', 'America/Argentina/Buenos_Aires', 'America/Argentina/Catamarca',
				'America/Argentina/ComodRivadavia', 'America/Argentina/Cordoba', 'America/Argentina/Jujuy',
				'America/Argentina/La_Rioja', 'America/Argentina/Mendoza', 'America/Argentina/Rio_Gallegos',
				'America/Argentina/San_Juan', 'America/Argentina/Tucuman', 'America/Argentina/Ushuaia', 'America/Aruba',
				'America/Asuncion', 'America/Atikokan', 'America/Atka', 'America/Bahia', 'America/Barbados', 'America/Belem',
				'America/Belize', 'America/Blanc-Sablon', 'America/Boa_Vista', 'America/Bogota', 'America/Boise',
				'America/Buenos_Aires', 'America/Cambridge_Bay', 'America/Campo_Grande', 'America/Cancun', 'America/Caracas',
				'America/Catamarca', 'America/Cayenne', 'America/Cayman', 'America/Chicago', 'America/Chihuahua',
				'America/Coral_Harbour', 'America/Cordoba', 'America/Costa_Rica', 'America/Cuiaba', 'America/Curacao',
				'America/Danmarkshavn', 'America/Dawson', 'America/Dawson_Creek', 'America/Denver', 'America/Detroit',
				'America/Dominica', 'America/Edmonton', 'America/Eirunepe', 'America/El_Salvador', 'America/Ensenada',
				'America/Fort_Wayne', 'America/Fortaleza', 'America/Glace_Bay', 'America/Godthab', 'America/Goose_Bay',
				'America/Grand_Turk', 'America/Grenada', 'America/Guadeloupe', 'America/Guatemala', 'America/Guayaquil',
				'America/Guyana', 'America/Halifax', 'America/Havana', 'America/Hermosillo', 'America/Indiana/Indianapolis',
				'America/Indiana/Knox', 'America/Indiana/Marengo', 'America/Indiana/Petersburg', 'America/Indiana/Vevay',
				'America/Indiana/Vincennes', 'America/Indianapolis', 'America/Inuvik', 'America/Iqaluit', 'America/Jamaica',
				'America/Jujuy', 'America/Juneau', 'America/Kentucky/Louisville', 'America/Kentucky/Monticello',
				'America/Knox_IN', 'America/La_Paz', 'America/Lima', 'America/Los_Angeles', 'America/Louisville',
				'America/Maceio', 'America/Managua', 'America/Manaus', 'America/Martinique', 'America/Mazatlan',
				'America/Mendoza', 'America/Menominee', 'America/Merida', 'America/Mexico_City', 'America/Miquelon',
				'America/Moncton', 'America/Monterrey', 'America/Montevideo', 'America/Montreal', 'America/Montserrat',
				'America/Nassau', 'America/New_York', 'America/Nipigon', 'America/Nome', 'America/Noronha',
				'America/North_Dakota/Center', 'America/North_Dakota/New_Salem', 'America/Panama', 'America/Pangnirtung',
				'America/Paramaribo', 'America/Phoenix', 'America/Port-au-Prince', 'America/Port_of_Spain', 'America/Porto_Acre',
				'America/Porto_Velho', 'America/Puerto_Rico', 'America/Rainy_River', 'America/Rankin_Inlet', 'America/Recife',
				'America/Regina', 'America/Rio_Branco', 'America/Rosario', 'America/Santiago', 'America/Santo_Domingo',
				'America/Sao_Paulo', 'America/Scoresbysund', 'America/Shiprock', 'America/St_Johns', 'America/St_Kitts',
				'America/St_Lucia', 'America/St_Thomas', 'America/St_Vincent', 'America/Swift_Current', 'America/Tegucigalpa',
				'America/Thule', 'America/Thunder_Bay', 'America/Tijuana', 'America/Toronto', 'America/Tortola',
				'America/Vancouver', 'America/Virgin', 'America/Whitehorse', 'America/Winnipeg', 'America/Yakutat',
				'America/Yellowknife', 'Antarctica/Casey', 'Antarctica/Davis', 'Antarctica/DumontDUrville', 'Antarctica/Mawson',
				'Antarctica/McMurdo', 'Antarctica/Palmer', 'Antarctica/Rothera', 'Antarctica/South_Pole', 'Antarctica/Syowa',
				'Antarctica/Vostok', 'Arctic/Longyearbyen', 'Asia/Aden', 'Asia/Almaty', 'Asia/Amman', 'Asia/Anadyr', 'Asia/Aqtau',
				'Asia/Aqtobe', 'Asia/Ashgabat', 'Asia/Ashkhabad', 'Asia/Baghdad', 'Asia/Bahrain', 'Asia/Baku', 'Asia/Bangkok',
				'Asia/Beirut', 'Asia/Bishkek', 'Asia/Brunei', 'Asia/Calcutta', 'Asia/Choibalsan', 'Asia/Chongqing',
				'Asia/Chungking', 'Asia/Colombo', 'Asia/Dacca', 'Asia/Damascus', 'Asia/Dhaka', 'Asia/Dili', 'Asia/Dubai',
				'Asia/Dushanbe', 'Asia/Gaza', 'Asia/Harbin', 'Asia/Hong_Kong', 'Asia/Hovd', 'Asia/Irkutsk', 'Asia/Istanbul',
				'Asia/Jakarta', 'Asia/Jayapura', 'Asia/Jerusalem', 'Asia/Kabul', 'Asia/Kamchatka', 'Asia/Karachi', 'Asia/Kashgar',
				'Asia/Katmandu', 'Asia/Krasnoyarsk', 'Asia/Kuala_Lumpur', 'Asia/Kuching', 'Asia/Kuwait', 'Asia/Macao',
				'Asia/Macau', 'Asia/Magadan', 'Asia/Makassar', 'Asia/Manila', 'Asia/Muscat', 'Asia/Nicosia', 'Asia/Novosibirsk',
				'Asia/Omsk', 'Asia/Oral', 'Asia/Phnom_Penh', 'Asia/Pontianak', 'Asia/Pyongyang', 'Asia/Qatar', 'Asia/Qyzylorda',
				'Asia/Rangoon', 'Asia/Riyadh', 'Asia/Saigon', 'Asia/Sakhalin', 'Asia/Samarkand', 'Asia/Seoul', 'Asia/Shanghai',
				'Asia/Singapore', 'Asia/Taipei', 'Asia/Tashkent', 'Asia/Tbilisi', 'Asia/Tehran', 'Asia/Tel_Aviv', 'Asia/Thimbu',
				'Asia/Thimphu', 'Asia/Tokyo', 'Asia/Ujung_Pandang', 'Asia/Ulaanbaatar', 'Asia/Ulan_Bator', 'Asia/Urumqi',
				'Asia/Vientiane', 'Asia/Vladivostok', 'Asia/Yakutsk', 'Asia/Yekaterinburg', 'Asia/Yerevan', 'Atlantic/Azores',
				'Atlantic/Bermuda', 'Atlantic/Canary', 'Atlantic/Cape_Verde', 'Atlantic/Faeroe', 'Atlantic/Jan_Mayen',
				'Atlantic/Madeira', 'Atlantic/Reykjavik', 'Atlantic/South_Georgia', 'Atlantic/St_Helena', 'Atlantic/Stanley',
				'Australia/ACT', 'Australia/Adelaide', 'Australia/Brisbane', 'Australia/Broken_Hill', 'Australia/Canberra',
				'Australia/Currie', 'Australia/Darwin', 'Australia/Hobart', 'Australia/LHI', 'Australia/Lindeman',
				'Australia/Lord_Howe', 'Australia/Melbourne', 'Australia/North', 'Australia/NSW', 'Australia/Perth',
				'Australia/Queensland', 'Australia/South', 'Australia/Sydney', 'Australia/Tasmania', 'Australia/Victoria',
				'Australia/West', 'Australia/Yancowinna', 'Brazil/Acre', 'Brazil/DeNoronha', 'Brazil/East', 'Brazil/West',
				'Canada/Atlantic', 'Canada/Central', 'Canada/East-Saskatchewan', 'Canada/Eastern', 'Canada/Mountain',
				'Canada/Newfoundland', 'Canada/Pacific', 'Canada/Saskatchewan', 'Canada/Yukon', 'CET', 'Chile/Continental',
				'Chile/EasterIsland', 'CST6CDT', 'Cuba', 'EET', 'Egypt', 'Eire', 'EST', 'EST5EDT', 'Etc/GMT', 'Etc/GMT+0',
				'Etc/GMT+1', 'Etc/GMT+10', 'Etc/GMT+11', 'Etc/GMT+12', 'Etc/GMT+2', 'Etc/GMT+3', 'Etc/GMT+4', 'Etc/GMT+5',
				'Etc/GMT+6', 'Etc/GMT+7', 'Etc/GMT+8', 'Etc/GMT+9', 'Etc/GMT-0', 'Etc/GMT-1', 'Etc/GMT-10', 'Etc/GMT-11',
				'Etc/GMT-12', 'Etc/GMT-13', 'Etc/GMT-14', 'Etc/GMT-2', 'Etc/GMT-3', 'Etc/GMT-4', 'Etc/GMT-5', 'Etc/GMT-6',
				'Etc/GMT-7', 'Etc/GMT-8', 'Etc/GMT-9', 'Etc/GMT0', 'Etc/Greenwich', 'Etc/UCT', 'Etc/Universal', 'Etc/UTC',
				'Etc/Zulu', 'Europe/Amsterdam', 'Europe/Andorra', 'Europe/Athens', 'Europe/Belfast', 'Europe/Belgrade',
				'Europe/Berlin', 'Europe/Bratislava', 'Europe/Brussels', 'Europe/Bucharest', 'Europe/Budapest', 'Europe/Chisinau',
				'Europe/Copenhagen', 'Europe/Dublin', 'Europe/Gibraltar', 'Europe/Guernsey', 'Europe/Helsinki',
				'Europe/Isle_of_Man', 'Europe/Istanbul', 'Europe/Jersey', 'Europe/Kaliningrad', 'Europe/Kiev', 'Europe/Lisbon',
				'Europe/Ljubljana', 'Europe/London', 'Europe/Luxembourg', 'Europe/Madrid', 'Europe/Malta', 'Europe/Mariehamn',
				'Europe/Minsk', 'Europe/Monaco', 'Europe/Moscow', 'Europe/Nicosia', 'Europe/Oslo', 'Europe/Paris',
				'Europe/Podgorica', 'Europe/Prague', 'Europe/Riga', 'Europe/Rome', 'Europe/Samara', 'Europe/San_Marino',
				'Europe/Sarajevo', 'Europe/Simferopol', 'Europe/Skopje', 'Europe/Sofia', 'Europe/Stockholm', 'Europe/Tallinn',
				'Europe/Tirane', 'Europe/Tiraspol', 'Europe/Uzhgorod', 'Europe/Vaduz', 'Europe/Vatican', 'Europe/Vienna',
				'Europe/Vilnius', 'Europe/Volgograd', 'Europe/Warsaw', 'Europe/Zagreb', 'Europe/Zaporozhye', 'Europe/Zurich',
				'Factory', 'GB', 'GB-Eire', 'GMT', 'GMT+0', 'GMT-0', 'GMT0', 'Greenwich', 'Hongkong', 'HST', 'Iceland',
				'Indian/Antananarivo', 'Indian/Chagos', 'Indian/Christmas', 'Indian/Cocos', 'Indian/Comoro', 'Indian/Kerguelen',
				'Indian/Mahe', 'Indian/Maldives', 'Indian/Mauritius', 'Indian/Mayotte', 'Indian/Reunion', 'Iran', 'Israel',
				'Jamaica', 'Japan', 'Kwajalein', 'Libya', 'MET', 'Mexico/BajaNorte', 'Mexico/BajaSur', 'Mexico/General', 'MST',
				'MST7MDT', 'Navajo', 'NZ', 'NZ-CHAT', 'Pacific/Apia', 'Pacific/Auckland', 'Pacific/Chatham', 'Pacific/Easter',
				'Pacific/Efate', 'Pacific/Enderbury', 'Pacific/Fakaofo', 'Pacific/Fiji', 'Pacific/Funafuti', 'Pacific/Galapagos',
				'Pacific/Gambier', 'Pacific/Guadalcanal', 'Pacific/Guam', 'Pacific/Honolulu', 'Pacific/Johnston',
				'Pacific/Kiritimati', 'Pacific/Kosrae', 'Pacific/Kwajalein', 'Pacific/Majuro', 'Pacific/Marquesas',
				'Pacific/Midway', 'Pacific/Nauru', 'Pacific/Niue', 'Pacific/Norfolk', 'Pacific/Noumea', 'Pacific/Pago_Pago',
				'Pacific/Palau', 'Pacific/Pitcairn', 'Pacific/Ponape', 'Pacific/Port_Moresby', 'Pacific/Rarotonga',
				'Pacific/Saipan', 'Pacific/Samoa', 'Pacific/Tahiti', 'Pacific/Tarawa', 'Pacific/Tongatapu', 'Pacific/Truk',
				'Pacific/Wake', 'Pacific/Wallis', 'Pacific/Yap', 'Poland', 'Portugal', 'PRC', 'PST8PDT', 'ROC', 'ROK',
				'Singapore', 'Turkey', 'UCT', 'Universal', 'US/Alaska', 'US/Aleutian', 'US/Arizona', 'US/Central',
				'US/East-Indiana', 'US/Eastern', 'US/Hawaii', 'US/Indiana-Starke', 'US/Michigan', 'US/Mountain', 'US/Pacific',
				'US/Pacific-New', 'US/Samoa', 'UTC', 'W-SU', 'WET', 'Zulu'
			);
		}
	}

	Class SymphonyLog extends Log{

		function SymphonyLog($path){
			$this->setLogPath($path);

			if(@file_exists($this->getLogPath())){
				$this->open();

			}else{
				$this->open('OVERRIDE');
				$this->writeToLog('Symphony Installer Log', true);
				$this->writeToLog('Opened: '. DateTimeObj::get('c'), true);
				$this->writeToLog('Version: '. kVERSION, true);
				$this->writeToLog('Domain: '._INSTALL_URL_, true);
				$this->writeToLog('--------------------------------------------', true);
			}
		}
	}

	Class Action{

		function requirements(&$Page){

			$missing = array();

			if(!checkRequirement(phpversion(), 'version', '5.2')){
				$Page->log->pushToLog('Requirement - PHP Version is not correct. '.phpversion().' detected.' , E_ERROR, true);
				$missing[] = MISSING_PHP;
			}

			if(!checkRequirement('mysql_connect', 'func', true)){
				$Page->log->pushToLog('Requirement - MySQL extension not present' , E_ERROR, true);
				$missing[] = MISSING_MYSQL;
			}

			if(!checkRequirement('zlib', 'ext', true)){
				$Page->log->pushToLog('Requirement - ZLib extension not present' , E_ERROR, true);
				$missing[] = MISSING_ZLIB;
			}

			if(!checkRequirement('xml:libxml', 'ext', true)){
				$Page->log->pushToLog('Requirement - No XML extension present' , E_ERROR, true);
				$missing[] = MISSING_XML;
			}

			if(!checkRequirement('xsl:xslt', 'ext', true) && !checkRequirement('domxml_xslt_stylesheet', 'func', true)) {
				$Page->log->pushToLog('Requirement - No XSL extension present' , E_ERROR, true);
				$missing[] = MISSING_XSL;
			}

			$Page->missing = $missing;

			return;

		}

		function install(&$Page, $fields){

			global $warnings;

			$database_connection_error = false;

			try{
				$db = new MySQL;
				$db->connect($fields['database']['host'], $fields['database']['username'], $fields['database']['password'], $fields['database']['port']);

				$tables = $db->fetch(sprintf(
					"SHOW TABLES FROM `%s` LIKE '%s'",
					mysql_escape_string($fields['database']['name']),
					mysql_escape_string($fields['database']['prefix']) . '%'
				));

			}
			catch(DatabaseException $e){
				$database_connection_error = true;
			}

			## Invalid path
			if(!@is_dir(rtrim($fields['docroot'], '/') . '/symphony')){
				$Page->log->pushToLog("Configuration - Bad Document Root Specified: " . $fields['docroot'], E_NOTICE, true);
				define("kENVIRONMENT_WARNING", true);
				if(!defined("ERROR")) define("ERROR", 'no-symphony-dir');
			}

			## Cannot write to workspace
			elseif(is_dir(rtrim($fields['docroot'], '/') . '/workspace') && !is_writable(rtrim($fields['docroot'], '/') . '/workspace')){
				$Page->log->pushToLog("Configuration - Workspace folder not writable: " . $fields['docroot'] . '/workspace', E_NOTICE, true);
				define("kENVIRONMENT_WARNING", true);
				if(!defined("ERROR")) define("ERROR", 'no-write-permission-workspace');
			}

			## Cannot write to root folder.
			elseif(!is_writable(rtrim($fields['docroot'], '/'))){
				$Page->log->pushToLog("Configuration - Root folder not writable: " . $fields['docroot'], E_NOTICE, true);
				define("kENVIRONMENT_WARNING", true);
				if(!defined("ERROR")) define("ERROR", 'no-write-permission-root');
			}

			## Failed to establish database connection
			elseif($database_connection_error){
				$Page->log->pushToLog("Configuration - Could not establish database connection", E_NOTICE, true);
				define("kDATABASE_CONNECTION_WARNING", true);
				if(!defined("ERROR")) define("ERROR", 'no-database-connection');
			}

			## Incorrect MySQL version
			elseif(version_compare($db->fetchVar('version', 0, "SELECT VERSION() AS `version`;"), '5.0', '<')){
				$version = $db->fetchVar('version', 0, "SELECT VERSION() AS `version`;");
				$Page->log->pushToLog('Configuration - MySQL Version is not correct. '.$version.' detected.', E_NOTICE, true);
				define("kDATABASE_VERSION_WARNING", true);

				$warnings['database-incorrect-version'] = __('Symphony requires <code>MySQL 5.0</code> or greater to work, however version <code>%s</code> was detected. This requirement must be met before installation can proceed.', array($version));

				if(!defined("ERROR")) define("ERROR", 'database-incorrect-version');
			}

			## Failed to select database
			elseif(!$db->select($fields['database']['name'])){
				$Page->log->pushToLog("Configuration - Database '".$fields['database']['name']."' Not Found", E_NOTICE, true);
				define("kDATABASE_CONNECTION_WARNING", true);
				if(!defined("ERROR")) define("ERROR", 'no-database-connection');
			}

			## Failed to establish connection
			elseif(is_array($tables) && !empty($tables)){
				$Page->log->pushToLog("Configuration - Database table prefix clash with '".$fields['database']['name']."'", E_NOTICE, true);
				define("kDATABASE_PREFIX_WARNING", true);
				if(!defined("ERROR")) define("ERROR", 'database-table-clash');
			}

			## Username Not Entered
			elseif(trim($fields['user']['username']) == ''){
				$Page->log->pushToLog("Configuration - No username entered.", E_NOTICE, true);
				define("kUSER_USERNAME_WARNING", true);
				if(!defined("ERROR")) define("ERROR", 'user-no-username');
			}

			## Password Not Entered
			elseif(trim($fields['user']['password']) == ''){
				$Page->log->pushToLog("Configuration - No password entered.", E_NOTICE, true);
				define("kUSER_PASSWORD_WARNING", true);
				if(!defined("ERROR")) define("ERROR", 'user-no-password');
			}

			## Password mismatch
			elseif($fields['user']['password'] != $fields['user']['confirm-password']){
				$Page->log->pushToLog("Configuration - Passwords did not match.", E_NOTICE, true);
				define("kUSER_PASSWORD_WARNING", true);
				if(!defined("ERROR")) define("ERROR", 'user-password-mismatch');
			}

			## No Name entered
			elseif(trim($fields['user']['firstname']) == '' || trim($fields['user']['lastname']) == ''){
				$Page->log->pushToLog("Configuration - Did not enter First and Last names.", E_NOTICE, true);
				define("kUSER_NAME_WARNING", true);
				if(!defined("ERROR")) define("ERROR", 'user-no-name');
			}

			## Invalid Email
			elseif(!preg_match('/^\w(?:\.?[\w%+-]+)*@\w(?:[\w-]*\.)+?[a-z]{2,}$/i', $fields['user']['email'])){
				$Page->log->pushToLog("Configuration - Invalid email address supplied.", E_NOTICE, true);
				define("kUSER_EMAIL_WARNING", true);
				if(!defined("ERROR")) define("ERROR", 'user-invalid-email');
			}

			## Otherwise there are no error, proceed with installation
			else{

				$config = $fields;

				$kDOCROOT = rtrim($config['docroot'], '/');

				$database = array_map("trim", $fields['database']);

				if(!isset($database['host']) || $database['host'] == "") $database['host'] = "localhost";
				if(!isset($database['port']) || $database['port'] == "") $database['port'] = "3306";
				if(!isset($database['prefix']) || $database['prefix'] == "") $database['prefix'] = "sym_";

				$install_log = $Page->log;

				$start = time();

				$install_log->writeToLog(CRLF . '============================================', true);
				$install_log->writeToLog('INSTALLATION PROCESS STARTED (' . DateTimeObj::get('c') . ')', true);
				$install_log->writeToLog('============================================', true);

				$install_log->pushToLog("MYSQL: Establishing Connection...", E_NOTICE, true, false);
				$db = new MySQL;

				if(!$db->connect($database['host'], $database['username'], $database['password'], $database['port'])){
					define('_INSTALL_ERRORS_', "There was a problem while trying to establish a connection to the MySQL server. Please check your settings.");
					$install_log->pushToLog("Failed", E_NOTICE,true, true, true);
					installResult($Page, $install_log, $start);
				}else{
					$install_log->pushToLog("Done", E_NOTICE,true, true, true);
				}

				$install_log->pushToLog("MYSQL: Selecting Database '".$database['name']."'...", E_NOTICE, true, false);

				if(!$db->select($database['name'])){
					define('_INSTALL_ERRORS_', "Could not connect to specified database. Please check your settings.");
					$install_log->pushToLog("Failed", E_NOTICE,true, true, true);
					installResult($Page, $install_log, $start);
				}else{
					$install_log->pushToLog("Done", E_NOTICE,true, true, true);
				}

				$db->setPrefix($database['prefix']);

				$conf = getDynamicConfiguration();
				if($conf['database']['runtime_character_set_alter'] == '1'){
					$db->setCharacterEncoding($conf['database']['character_encoding']);
					$db->setCharacterSet($conf['database']['character_set']);
				}

				$install_log->pushToLog("MYSQL: Importing Table Schema...", E_NOTICE, true, false);
				$error = NULL;
				if(!fireSql($db, getTableSchema(), $error, ($config['database']['use-server-encoding'] != 'yes' ? true : false))){
					define('_INSTALL_ERRORS_', "There was an error while trying to import data to the database. MySQL returned: $error");
					$install_log->pushToLog("Failed", E_ERROR,true, true, true);
					installResult($Page, $install_log, $start);
				}else{
					$install_log->pushToLog("Done", E_NOTICE,true, true, true);
				}

				$author_sql = sprintf(
					"INSERT INTO  `tbl_authors` (
						`id` ,
						`username` ,
						`password` ,
						`first_name` ,
						`last_name` ,
						`email` ,
						`last_seen` ,
						`user_type` ,
						`primary` ,
						`default_area` ,
						`auth_token_active`
					)
					VALUES (
						1,
						'%s',
						%s('%s'),
						'%s',
						'%s',
						'%s',
						NULL ,
						'developer',
						'yes',
						NULL,
						'no'
					);",

					$db->cleanValue($config['user']['username']),
					'SHA1',
					$db->cleanValue($config['user']['password']),
					$db->cleanValue($config['user']['firstname']),
					$db->cleanValue($config['user']['lastname']),
					$db->cleanValue($config['user']['email'])
				);

				$install_log->pushToLog("MYSQL: Creating Default Author...", E_NOTICE, true, false);
				if(!$db->query($author_sql)){
					$error = $db->getLastError();
					define('_INSTALL_ERRORS_', "There was an error while trying create the default author. MySQL returned: " . $error['num'] . ': ' . $error['msg']);
					$install_log->pushToLog("Failed", E_ERROR, true, true, true);
					installResult($Page, $install_log, $start);

				}else{
					$install_log->pushToLog("Done", E_NOTICE, true, true, true);
				}


				$conf = array();

				if(@is_dir($fields['docroot'] . '/workspace')){
					foreach(getDynamicConfiguration() as $group => $settings){
						if(!is_array($conf['settings'][$group])) $conf['settings'][$group] = array();
						$conf['settings'][$group] = array_merge($conf['settings'][$group], $settings);
					}
				}

				else{

					$conf['settings']['admin']['max_upload_size'] = '5242880';
					$conf['settings']['symphony']['pagination_maximum_rows'] = '17';
					$conf['settings']['symphony']['allow_page_subscription'] = '1';
					$conf['settings']['symphony']['lang'] = Lang::get();
					$conf['settings']['symphony']['pages_table_nest_children'] = 'no';
					$conf['settings']['symphony']['strict_error_handling'] = 'yes';
					$conf['settings']['symphony']['session_gc_divisor'] = '10';
					$conf['settings']['log']['archive'] = '1';
					$conf['settings']['log']['maxsize'] = '102400';
					$conf['settings']['image']['cache'] = '1';
					$conf['settings']['image']['quality'] = '90';
					$conf['settings']['database']['character_set'] = 'utf8';
					$conf['settings']['database']['character_encoding'] = 'utf8';
					$conf['settings']['database']['runtime_character_set_alter'] = '1';
					$conf['settings']['database']['query_caching'] = 'on';
					$conf['settings']['public']['display_event_xml_in_source'] = 'no';
				}

				$conf['settings']['symphony']['version'] = kVERSION;
				$conf['settings']['symphony']['cookie_prefix'] = 'sym-';
				$conf['settings']['general']['useragent'] = 'Symphony/' . kVERSION;
				$conf['settings']['general']['sitename'] = (strlen(trim($config['general']['sitename'])) > 0 ? $config['general']['sitename'] : __('Website Name'));
				$conf['settings']['file']['write_mode'] = $config['permission']['file'];
				$conf['settings']['directory']['write_mode'] = $config['permission']['directory'];
				$conf['settings']['database']['host'] = $database['host'];
				$conf['settings']['database']['port'] = $database['port'];
				$conf['settings']['database']['user'] = $database['username'];
				$conf['settings']['database']['password'] = $database['password'];
				$conf['settings']['database']['db'] = $database['name'];
				$conf['settings']['database']['tbl_prefix'] = $database['prefix'];
				$conf['settings']['region']['time_format'] = $config['region']['time_format'];
				$conf['settings']['region']['date_format'] = $config['region']['date_format'];
				$conf['settings']['region']['datetime_separator'] = ' ';
				$conf['settings']['region']['timezone'] = $config['region']['timezone'];

				## Create Manifest Directory structure
				$install_log->pushToLog("WRITING: Creating 'manifest' folder (/manifest)", E_NOTICE, true, true);
				if(!General::realiseDirectory($kDOCROOT . '/manifest', $conf['settings']['directory']['write_mode'])){
					define('_INSTALL_ERRORS_', "Could not create 'manifest' directory. Check permission on the root folder.");
					$install_log->pushToLog("ERROR: Creation of 'manifest' folder failed.", E_ERROR, true, true);
					installResult($Page, $install_log, $start);
					return;
				}

				$install_log->pushToLog("WRITING: Creating 'logs' folder (/manifest/logs)", E_NOTICE, true, true);
				if(!General::realiseDirectory($kDOCROOT . '/manifest/logs', $conf['settings']['directory']['write_mode'])){
					define('_INSTALL_ERRORS_', "Could not create 'logs' directory. Check permission on /manifest.");
					$install_log->pushToLog("ERROR: Creation of 'logs' folder failed.", E_ERROR, true, true);
					installResult($Page, $install_log, $start);
					return;
				}

				$install_log->pushToLog("WRITING: Creating 'cache' folder (/manifest/cache)", E_NOTICE, true, true);
				if(!General::realiseDirectory($kDOCROOT . '/manifest/cache', $conf['settings']['directory']['write_mode'])){
					define('_INSTALL_ERRORS_', "Could not create 'cache' directory. Check permission on /manifest.");
					$install_log->pushToLog("ERROR: Creation of 'cache' folder failed.", E_ERROR, true, true);
					installResult($Page, $install_log, $start);
					return;
				}

				$install_log->pushToLog("WRITING: Creating 'tmp' folder (/manifest/tmp)", E_NOTICE, true, true);
				if(!General::realiseDirectory($kDOCROOT . '/manifest/tmp', $conf['settings']['directory']['write_mode'])){
					define('_INSTALL_ERRORS_', "Could not create 'tmp' directory. Check permission on /manifest.");
					$install_log->pushToLog("ERROR: Creation of 'tmp' folder failed.", E_ERROR, true, true);
					installResult($Page, $install_log, $start);
					return;
				}

				$install_log->pushToLog("WRITING: Configuration File", E_NOTICE, true, true);
				if(!writeConfig($kDOCROOT . '/manifest/', $conf, $conf['settings']['file']['write_mode'])){
					define('_INSTALL_ERRORS_', "Could not write config file. Check permission on /manifest.");
					$install_log->pushToLog("ERROR: Writing Configuration File Failed", E_ERROR, true, true);
					installResult($Page, $install_log, $start);
				}

				$htaccess = '



######################### GZIP FIX - http://my.opera.com/OmegaJunior/blog/google-chrome-and-gzip




<Files *.js.gz>
ForceType text/javascript
Header set Content-Encoding: gzip
</Files>
<Files *.css.gz>
ForceType text/css
Header set Content-Encoding: gzip
</Files>
<Files *.html.gz>
ForceType text/html
Header set Content-Encoding: gzip
</Files>




################# BOILERPLATE

# Apache configuration file
# httpd.apache.org/docs/2.2/mod/quickreference.html

# Note .htaccess files are an overhead, this logic should be in your Apache config if possible
# httpd.apache.org/docs/2.2/howto/htaccess.html

# Techniques in here adapted from all over, including:
#   Kroc Camen: camendesign.com/.htaccess
#   perishablepress.com/press/2006/01/10/stupid-htaccess-tricks/
#   Sample .htaccess file of CMS MODx: modxcms.com


###
### If you run a webserver other than Apache, consider:
### github.com/h5bp/server-configs
###



# ----------------------------------------------------------------------
# Better website experience for IE users
# ----------------------------------------------------------------------

# Force the latest IE version, in various cases when it may fall back to IE7 mode
#  github.com/rails/rails/commit/123eb25#commitcomment-118920
# Use ChromeFrame if its installed for a better experience for the poor IE folk

<IfModule mod_headers.c>
  Header set X-UA-Compatible "IE=Edge,chrome=1"
  # mod_headers cant match by content-type, but we dont want to send this header on *everything*...
  <FilesMatch "\.(js|css|gif|png|jpe?g|pdf|xml|oga|ogg|m4a|ogv|mp4|m4v|webm|svg|svgz|eot|ttf|otf|woff|ico|webp|appcache|manifest|htc|crx|oex|xpi|safariextz|vcf)$" >
    Header unset X-UA-Compatible
  </FilesMatch>
</IfModule>


# ----------------------------------------------------------------------
# Cross-domain AJAX requests
# ----------------------------------------------------------------------

# Serve cross-domain Ajax requests, disabled by default.
# enable-cors.org
# code.google.com/p/html5security/wiki/CrossOriginRequestSecurity

#  <IfModule mod_headers.c>
#    Header set Access-Control-Allow-Origin "*"
#  </IfModule>


# ----------------------------------------------------------------------
# CORS-enabled images (@crossorigin)
# ----------------------------------------------------------------------

# Send CORS headers if browsers request them; enabled by default for images.
# developer.mozilla.org/en/CORS_Enabled_Image
# blog.chromium.org/2011/07/using-cross-domain-images-in-webgl-and.html
# hacks.mozilla.org/2011/11/using-cors-to-load-webgl-textures-from-cross-domain-images/
# wiki.mozilla.org/Security/Reviews/crossoriginAttribute

<IfModule mod_setenvif.c>
  <IfModule mod_headers.c>
    # mod_headers, y u no match by Content-Type?!
    <FilesMatch "\.(gif|png|jpe?g|svg|svgz|ico|webp)$">
      SetEnvIf Origin ":" IS_CORS
      Header set Access-Control-Allow-Origin "*" env=IS_CORS
    </FilesMatch>
  </IfModule>
</IfModule>


# ----------------------------------------------------------------------
# Webfont access
# ----------------------------------------------------------------------

# Allow access from all domains for webfonts.
# Alternatively you could only whitelist your
# subdomains like "subdomain.example.com".

<IfModule mod_headers.c>
  <FilesMatch "\.(ttf|ttc|otf|eot|woff|font.css)$">
    Header set Access-Control-Allow-Origin "*"
  </FilesMatch>
</IfModule>



# ----------------------------------------------------------------------
# Proper MIME type for all files
# ----------------------------------------------------------------------


# JavaScript
#   Normalize to standard type (its sniffed in IE anyways)
#   tools.ietf.org/html/rfc4329#section-7.2
AddType application/javascript         js

# Audio
AddType audio/ogg                      oga ogg
AddType audio/mp4                      m4a

# Video
AddType video/ogg                      ogv
AddType video/mp4                      mp4 m4v
AddType video/webm                     webm

# SVG
#   Required for svg webfonts on iPad
#   twitter.com/FontSquirrel/status/14855840545
AddType     image/svg+xml              svg svgz
AddEncoding gzip                       svgz

# Webfonts
AddType application/vnd.ms-fontobject  eot
AddType application/x-font-ttf         ttf ttc
AddType font/opentype                  otf
AddType application/x-font-woff        woff

# Assorted types
AddType image/x-icon                        ico
AddType image/webp                          webp
AddType text/cache-manifest                 appcache manifest
AddType text/x-component                    htc
AddType application/x-chrome-extension      crx
AddType application/x-opera-extension       oex
AddType application/x-xpinstall             xpi
AddType application/octet-stream            safariextz
AddType application/x-web-app-manifest+json webapp
AddType text/x-vcard                        vcf



# ----------------------------------------------------------------------
# Allow concatenation from within specific js and css files
# ----------------------------------------------------------------------

# e.g. Inside of script.combined.js you could have
#   <!--#include file="libs/jquery-1.5.0.min.js" -->
#   <!--#include file="plugins/jquery.idletimer.js" -->
# and they would be included into this single file.

# This is not in use in the boilerplate as it stands. You may
# choose to name your files in this way for this advantage or
# concatenate and minify them manually.
# Disabled by default.

#<FilesMatch "\.combined\.js$">
#  Options +Includes
#  AddOutputFilterByType INCLUDES application/javascript application/json
#  SetOutputFilter INCLUDES
#</FilesMatch>
#<FilesMatch "\.combined\.css$">
#  Options +Includes
#  AddOutputFilterByType INCLUDES text/css
#  SetOutputFilter INCLUDES
#</FilesMatch>


# ----------------------------------------------------------------------
# Gzip compression
# ----------------------------------------------------------------------

<IfModule mod_deflate.c>

  # Force deflate for mangled headers developer.yahoo.com/blogs/ydn/posts/2010/12/pushing-beyond-gzipping/
  <IfModule mod_setenvif.c>
    <IfModule mod_headers.c>
      SetEnvIfNoCase ^(Accept-EncodXng|X-cept-Encoding|X{15}|~{15}|-{15})$ ^((gzip|deflate)\s*,?\s*)+|[X~-]{4,13}$ HAVE_Accept-Encoding
      RequestHeader append Accept-Encoding "gzip,deflate" env=HAVE_Accept-Encoding
    </IfModule>
  </IfModule>

  # HTML, TXT, CSS, JavaScript, JSON, XML, HTC:
  <IfModule filter_module>
    FilterDeclare   COMPRESS
    FilterProvider  COMPRESS  DEFLATE resp=Content-Type $text/html
    FilterProvider  COMPRESS  DEFLATE resp=Content-Type $text/css
    FilterProvider  COMPRESS  DEFLATE resp=Content-Type $text/plain
    FilterProvider  COMPRESS  DEFLATE resp=Content-Type $text/xml
    FilterProvider  COMPRESS  DEFLATE resp=Content-Type $text/x-component
    FilterProvider  COMPRESS  DEFLATE resp=Content-Type $application/javascript
    FilterProvider  COMPRESS  DEFLATE resp=Content-Type $application/json
    FilterProvider  COMPRESS  DEFLATE resp=Content-Type $application/xml
    FilterProvider  COMPRESS  DEFLATE resp=Content-Type $application/xhtml+xml
    FilterProvider  COMPRESS  DEFLATE resp=Content-Type $application/rss+xml
    FilterProvider  COMPRESS  DEFLATE resp=Content-Type $application/atom+xml
    FilterProvider  COMPRESS  DEFLATE resp=Content-Type $application/vnd.ms-fontobject
    FilterProvider  COMPRESS  DEFLATE resp=Content-Type $image/svg+xml
    FilterProvider  COMPRESS  DEFLATE resp=Content-Type $image/x-icon
    FilterProvider  COMPRESS  DEFLATE resp=Content-Type $application/x-font-ttf
    FilterProvider  COMPRESS  DEFLATE resp=Content-Type $font/opentype
    FilterChain     COMPRESS
    FilterProtocol  COMPRESS  DEFLATE change=yes;byteranges=no
  </IfModule>

  <IfModule !mod_filter.c>
    # Legacy versions of Apache
    AddOutputFilterByType DEFLATE text/html text/plain text/css application/json
    AddOutputFilterByType DEFLATE application/javascript
    AddOutputFilterByType DEFLATE text/xml application/xml text/x-component
    AddOutputFilterByType DEFLATE application/xhtml+xml application/rss+xml application/atom+xml
    AddOutputFilterByType DEFLATE image/x-icon image/svg+xml application/vnd.ms-fontobject application/x-font-ttf font/opentype
  </IfModule>

</IfModule>


# ----------------------------------------------------------------------
# Expires headers (for better cache control)
# ----------------------------------------------------------------------

# These are pretty far-future expires headers.
# They assume you control versioning with cachebusting query params like
#   <script src="application.js?20100608">
# Additionally, consider that outdated proxies may miscache
#   www.stevesouders.com/blog/2008/08/23/revving-filenames-dont-use-querystring/

# If you dont use filenames to version, lower the CSS  and JS to something like
#   "access plus 1 week" or so.

<IfModule mod_expires.c>
  ExpiresActive on

# Perhaps better to whitelist expires rules? Perhaps.
  ExpiresDefault                          "access plus 1 month"

# cache.appcache needs re-requests in FF 3.6 (thanks Remy ~Introducing HTML5)
  ExpiresByType text/cache-manifest       "access plus 0 seconds"

# Your document html
  ExpiresByType text/html                 "access plus 0 seconds"

# Data
  ExpiresByType text/xml                  "access plus 0 seconds"
  ExpiresByType application/xml           "access plus 0 seconds"
  ExpiresByType application/json          "access plus 0 seconds"

# Feed
  ExpiresByType application/rss+xml       "access plus 1 hour"
  ExpiresByType application/atom+xml      "access plus 1 hour"

# Favicon (cannot be renamed)
  ExpiresByType image/x-icon              "access plus 1 week"

# Media: images, video, audio
  ExpiresByType image/gif                 "access plus 1 month"
  ExpiresByType image/png                 "access plus 1 month"
  ExpiresByType image/jpg                 "access plus 1 month"
  ExpiresByType image/jpeg                "access plus 1 month"
  ExpiresByType video/ogg                 "access plus 1 month"
  ExpiresByType audio/ogg                 "access plus 1 month"
  ExpiresByType video/mp4                 "access plus 1 month"
  ExpiresByType video/webm                "access plus 1 month"

# HTC files  (css3pie)
  ExpiresByType text/x-component          "access plus 1 month"

# Webfonts
  ExpiresByType application/x-font-ttf    "access plus 1 month"
  ExpiresByType font/opentype             "access plus 1 month"
  ExpiresByType application/x-font-woff   "access plus 1 month"
  ExpiresByType image/svg+xml             "access plus 1 month"
  ExpiresByType application/vnd.ms-fontobject "access plus 1 month"

# CSS and JavaScript
  ExpiresByType text/css                  "access plus 1 year"
  ExpiresByType application/javascript    "access plus 1 year"

</IfModule>



# ----------------------------------------------------------------------
# ETag removal
# ----------------------------------------------------------------------

# FileETag None is not enough for every server.
<IfModule mod_headers.c>
  Header unset ETag
</IfModule>

# Since were sending far-future expires, we dont need ETags for
# static content.
#   developer.yahoo.com/performance/rules.html#etags
FileETag None



# ----------------------------------------------------------------------
# Stop screen flicker in IE on CSS rollovers
# ----------------------------------------------------------------------

# The following directives stop screen flicker in IE on CSS rollovers - in
# combination with the "ExpiresByType" rules for images (see above). If
# needed, un-comment the following rules.

# BrowserMatch "MSIE" brokenvary=1
# BrowserMatch "Mozilla/4.[0-9]{2}" brokenvary=1
# BrowserMatch "Opera" !brokenvary
# SetEnvIf brokenvary 1 force-no-vary



# ----------------------------------------------------------------------
# Cookie setting from iframes
# ----------------------------------------------------------------------

# Allow cookies to be set from iframes (for IE only)
# If needed, uncomment and specify a path or regex in the Location directive

# <IfModule mod_headers.c>
#   <Location />
#     Header set P3P "policyref=\"/w3c/p3p.xml\", CP=\"IDC DSP COR ADM DEVi TAIi PSA PSD IVAi IVDi CONi HIS OUR IND CNT\""
#   </Location>
# </IfModule>



# ----------------------------------------------------------------------
# Start rewrite engine
# ----------------------------------------------------------------------

# Turning on the rewrite engine is necessary for the following rules and features.
# FollowSymLinks must be enabled for this to work.

<IfModule mod_rewrite.c>
  Options +FollowSymlinks
  RewriteEngine On
</IfModule>



# ----------------------------------------------------------------------
# Suppress or force the "www." at the beginning of URLs
# ----------------------------------------------------------------------

# The same content should never be available under two different URLs - especially not with and
# without "www." at the beginning, since this can cause SEO problems (duplicate content).
# Thats why you should choose one of the alternatives and redirect the other one.

# By default option 1 (no "www.") is activated. Remember: Shorter URLs are sexier.
# no-www.org/faq.php?q=class_b

# If you rather want to use option 2, just comment out all option 1 lines
# and uncomment option 2.
# IMPORTANT: NEVER USE BOTH RULES AT THE SAME TIME!

# ----------------------------------------------------------------------

# Option 1:
# Rewrite "www.example.com -> example.com"

<IfModule mod_rewrite.c>
  RewriteCond %{HTTPS} !=on
  RewriteCond %{HTTP_HOST} ^www\.(.+)$ [NC]
  RewriteRule ^ http://%1%{REQUEST_URI} [R=301,L]
</IfModule>

# ----------------------------------------------------------------------

# Option 2:
# To rewrite "example.com -> www.example.com" uncomment the following lines.
# Be aware that the following rule might not be a good idea if you
# use "real" subdomains for certain parts of your website.

# <IfModule mod_rewrite.c>
#   RewriteCond %{HTTPS} !=on
#   RewriteCond %{HTTP_HOST} !^www\..+$ [NC]
#   RewriteRule ^ http://www.%{HTTP_HOST}%{REQUEST_URI} [R=301,L]
# </IfModule>



# ----------------------------------------------------------------------
# Built-in filename-based cache busting
# ----------------------------------------------------------------------

# If youre not using the build script to manage your filename version revving,
# you might want to consider enabling this, which will route requests for
# /css/style.20110203.css to /css/style.css

# To understand why this is important and a better idea than all.css?v1231,
# read: github.com/h5bp/html5-boilerplate/wiki/Version-Control-with-Cachebusting

# Uncomment to enable.
# <IfModule mod_rewrite.c>
#   RewriteCond %{REQUEST_FILENAME} !-f
#   RewriteCond %{REQUEST_FILENAME} !-d
#   RewriteRule ^(.+)\.(\d+)\.(js|css|png|jpg|gif)$ $1.$3 [L]
# </IfModule>



# ----------------------------------------------------------------------
# Prevent SSL cert warnings
# ----------------------------------------------------------------------

# Rewrite secure requests properly to prevent SSL cert warnings, e.g. prevent
# https://www.example.com when your cert only allows https://secure.example.com
# Uncomment the following lines to use this feature.

# <IfModule mod_rewrite.c>
#   RewriteCond %{SERVER_PORT} !^443
#   RewriteRule ^ https://example-domain-please-change-me.com%{REQUEST_URI} [R=301,L]
# </IfModule>



# ----------------------------------------------------------------------
# Prevent 404 errors for non-existing redirected folders
# ----------------------------------------------------------------------

# without -MultiViews, Apache will give a 404 for a rewrite if a folder of the same name does not exist
#   e.g. /blog/hello : webmasterworld.com/apache/3808792.htm

Options -MultiViews



# ----------------------------------------------------------------------
# Custom 404 page
# ----------------------------------------------------------------------

# You can add custom pages to handle 500 or 403 pretty easily, if you like.
ErrorDocument 404 /404.html



# ----------------------------------------------------------------------
# UTF-8 encoding
# ----------------------------------------------------------------------

# Use UTF-8 encoding for anything served text/plain or text/html
AddDefaultCharset utf-8

# Force UTF-8 for a number of file formats
AddCharset utf-8 .css .js .xml .json .rss .atom



# ----------------------------------------------------------------------
# A little more security
# ----------------------------------------------------------------------


# Do we want to advertise the exact version number of Apache were running?
# Probably not.
## This can only be enabled if used in httpd.conf - It will not work in .htaccess
# ServerTokens Prod


# "-Indexes" will have Apache block users from browsing folders without a default document
# Usually you should leave this activated, because you shouldnt allow everybody to surf through
# every folder on your server (which includes rather private places like CMS system folders).
<IfModule mod_autoindex.c>
  Options -Indexes
</IfModule>


# Block access to "hidden" directories whose names begin with a period. This
# includes directories used by version control systems such as Subversion or Git.
<IfModule mod_rewrite.c>
  RewriteCond %{SCRIPT_FILENAME} -d
  RewriteCond %{SCRIPT_FILENAME} -f
  RewriteRule "(^|/)\." - [F]
</IfModule>


# Block access to backup and source files
# This files may be left by some text/html editors and
# pose a great security danger, when someone can access them
<FilesMatch "(\.(bak|config|sql|fla|psd|ini|log|sh|inc|swp|dist)|~)$">
  Order allow,deny
  Deny from all
  Satisfy All
</FilesMatch>


# If your server is not already configured as such, the following directive
# should be uncommented in order to set PHPs register_globals option to OFF.
# This closes a major security hole that is abused by most XSS (cross-site
# scripting) attacks. For more information: http://php.net/register_globals
#
# IF REGISTER_GLOBALS DIRECTIVE CAUSES 500 INTERNAL SERVER ERRORS :
#
# Your server does not allow PHP directives to be set via .htaccess. In that
# case you must make this change in your php.ini file instead. If you are
# using a commercial web host, contact the administrators for assistance in
# doing this. Not all servers allow local php.ini files, and they should
# include all PHP configurations (not just this one), or you will effectively
# reset everything to PHP defaults. Consult www.php.net for more detailed
# information about setting PHP directives.

# php_flag register_globals Off

# Rename session cookie to something else, than PHPSESSID
# php_value session.name sid

# Do not show you are using PHP
# Note: Move this line to php.ini since it wont work in .htaccess
# php_flag expose_php Off

# Level of log detail - log all errors
# php_value error_reporting -1

# Write errors to log file
# php_flag log_errors On

# Do not display errors in browser (production - Off, development - On)
# php_flag display_errors Off

# Do not display startup errors (production - Off, development - On)
# php_flag display_startup_errors Off

# Format errors in plain text
# Note: Leave this setting On for xdebugs var_dump() output
# php_flag html_errors Off

# Show multiple occurrence of error
# php_flag ignore_repeated_errors Off

# Show same errors from different sources
# php_flag ignore_repeated_source Off

# Size limit for error messages
# php_value log_errors_max_len 1024

# Dont precede error with string (doesnt accept empty string, use whitespace if you need)
# php_value error_prepend_string " "

# Dont prepend to error (doesnt accept empty string, use whitespace if you need)
# php_value error_append_string " "

php_value memory_limit 512M

# Increase cookie security
<IfModule php5_module>
  php_value session.cookie_httponly true
</IfModule>


##########################################################################

### Symphony 2.2.x ###
Options +FollowSymlinks -Indexes

<IfModule mod_rewrite.c>

	RewriteEngine on
	RewriteBase /'.REWRITE_BASE.'

	### SECURITY - Protect crucial files
	RewriteRule ^manifest/(.*)$ - [F]
	RewriteRule ^workspace/utilities/(.*).xsl$ - [F]
	RewriteRule ^workspace/pages/(.*).xsl$ - [F]
	RewriteRule ^(.*).sql$ - [F]
	RewriteRule (^|/)\. - [F]

	### DO NOT APPLY RULES WHEN REQUESTING "favicon.ico"
	RewriteCond %{REQUEST_FILENAME} favicon.ico [NC]
	RewriteRule .* - [S=14]

	### IMAGE RULES
	RewriteRule ^image\/(.+\.(jpg|gif|jpeg|png|bmp))$ extensions/jit_image_manipulation/lib/image.php?param=$1 [L,NC]

	### CHECK FOR TRAILING SLASH - Will ignore files
	RewriteCond %{REQUEST_FILENAME} !-f
	RewriteCond %{REQUEST_URI} !/$
	RewriteCond %{REQUEST_URI} !(.*)/$
	RewriteRule ^(.*)$ $1/ [L,R=301]

	### URL Correction
	RewriteRule ^(symphony/)?index.php(/.*/?) $1$2 [NC]

	### ADMIN REWRITE
	RewriteRule ^symphony\/?$ index.php?mode=administration&%{QUERY_STRING} [NC,L]

	RewriteCond %{REQUEST_FILENAME} !-d
	RewriteCond %{REQUEST_FILENAME} !-f
	RewriteRule ^symphony(\/(.*\/?))?$ index.php?symphony-page=$1&mode=administration&%{QUERY_STRING}	[NC,L]

	### FRONTEND REWRITE - Will ignore files and folders
	RewriteCond %{REQUEST_FILENAME} !-d
	RewriteCond %{REQUEST_FILENAME} !-f
	RewriteRule ^(.*\/?)$ index.php?symphony-page=$1&%{QUERY_STRING}	[L]

</IfModule>
######
';

				$install_log->pushToLog("CONFIGURING: Frontend", E_NOTICE, true, true);
				if(!General::writeFile($kDOCROOT . "/.htaccess", $htaccess, $conf['settings']['file']['write_mode'], 'a')){
					define('_INSTALL_ERRORS_', "Could not write .htaccess file. Check permission on " . $kDOCROOT);
					$install_log->pushToLog("ERROR: Writing .htaccess File Failed", E_ERROR, true, true);
					installResult($Page, $install_log, $start);
				}

				if(@!is_dir($fields['docroot'] . '/workspace')){

					### Create the workspace folder structure
					$install_log->pushToLog("WRITING: Creating 'workspace' folder (/workspace)", E_NOTICE, true, true);
					if(!General::realiseDirectory($kDOCROOT . '/workspace', $conf['settings']['directory']['write_mode'])){
						define('_INSTALL_ERRORS_', "Could not create 'workspace' directory. Check permission on the root folder.");
						$install_log->pushToLog("ERROR: Creation of 'workspace' folder failed.", E_ERROR, true, true);
						installResult($Page, $install_log, $start);
						return;
					}

					$install_log->pushToLog("WRITING: Creating 'data-sources' folder (/workspace/data-sources)", E_NOTICE, true, true);
					if(!General::realiseDirectory($kDOCROOT . '/workspace/data-sources', $conf['settings']['directory']['write_mode'])){
						define('_INSTALL_ERRORS_', "Could not create 'workspace/data-sources' directory. Check permission on the root folder.");
						$install_log->pushToLog("ERROR: Creation of 'workspace/data-sources' folder failed.", E_ERROR, true, true);
						installResult($Page, $install_log, $start);
						return;
					}

					$install_log->pushToLog("WRITING: Creating 'events' folder (/workspace/events)", E_NOTICE, true, true);
					if(!General::realiseDirectory($kDOCROOT . '/workspace/events', $conf['settings']['directory']['write_mode'])){
						define('_INSTALL_ERRORS_', "Could not create 'workspace/events' directory. Check permission on the root folder.");
						$install_log->pushToLog("ERROR: Creation of 'workspace/events' folder failed.", E_ERROR, true, true);
						installResult($Page, $install_log, $start);
						return;
					}

					$install_log->pushToLog("WRITING: Creating 'pages' folder (/workspace/pages)", E_NOTICE, true, true);
					if(!General::realiseDirectory($kDOCROOT . '/workspace/pages', $conf['settings']['directory']['write_mode'])){
						define('_INSTALL_ERRORS_', "Could not create 'workspace/pages' directory. Check permission on the root folder.");
						$install_log->pushToLog("ERROR: Creation of 'workspace/pages' folder failed.", E_ERROR, true, true);
						installResult($Page, $install_log, $start);
						return;
					}

					$install_log->pushToLog("WRITING: Creating 'utilities' folder (/workspace/utilities)", E_NOTICE, true, true);
					if(!General::realiseDirectory($kDOCROOT . '/workspace/utilities', $conf['settings']['directory']['write_mode'])){
						define('_INSTALL_ERRORS_', "Could not create 'workspace/utilities' directory. Check permission on the root folder.");
						$install_log->pushToLog("ERROR: Creation of 'workspace/utilities' folder failed.", E_ERROR, true, true);
						installResult($Page, $install_log, $start);
						return;
					}

				}

				else {

					$install_log->pushToLog("MYSQL: Importing Workspace Data...", E_NOTICE, true, false);
					$error = NULL;
					if(!fireSql($db, getWorkspaceData(), $error, ($config['database']['use-server-encoding'] != 'yes' ? true : false))){
						define('_INSTALL_ERRORS_', "There was an error while trying to import data to the database. MySQL returned: $error");
						$install_log->pushToLog("Failed", E_ERROR,true, true, true);
						installResult($Page, $install_log, $start);
					}else{
						$install_log->pushToLog("Done", E_NOTICE,true, true, true);
					}

				}

				if(@!is_dir($fields['docroot'] . '/extensions')){
					$install_log->pushToLog("WRITING: Creating 'extensions' folder (/extensions)", E_NOTICE, true, true);
					if(!General::realiseDirectory($kDOCROOT . '/extensions', $conf['settings']['directory']['write_mode'])){
						define('_INSTALL_ERRORS_', "Could not create 'extensions' directory. Check permission on the root folder.");
						$install_log->pushToLog("ERROR: Creation of 'extensions' folder failed.", E_ERROR, true, true);
						installResult($Page, $install_log, $start);
						return;
					}
				}

				$install_log->pushToLog("Installation Process Completed In ".max(1, time() - $start)." sec", E_NOTICE, true);

				installResult($Page, $install_log, $start);

				// Redirect to backend
				redirect('http://' . rtrim(str_replace('http://', '', _INSTALL_DOMAIN_), '/') . '/symphony/');

			}

		}

	}

	Class InstallPage{

		var $_header;
		var $_footer;
		var $_content;
		var $_vars;
		var $_result;
		var $log;
		var $missing;
		var $_page;

		function __construct(&$log){
			$this->_header = $this->_footer = $this->_content = NULL;
			$this->_result = NULL;
			$this->_vars = $this->missing = array();
			$this->log = $log;
		}

		function setPage($page){
			$this->_page = $page;
		}

		function getPage(){
			return $this->_page;
		}

		function setFooter($footer){
			$this->_footer = $footer;
		}

		function setHeader($header){
			$this->_header = $header;
		}

		function setContent($content){
			$this->_content = $content;
		}

		function setTemplateVar($name, $value){
			$this->_vars[$name] = $value;
		}

		function render(){
			$this->_result = $this->_header . $this->_content . $this->_footer;

			if(is_array($this->_vars) && !empty($this->_vars)){
				foreach($this->_vars as $name => $val){
					$this->_result = str_replace('<!-- ' . strtoupper($name) . ' -->', $val, $this->_result);
				}
			}

			return $this->_result;

		}

		function display(){
			return ($this->_result ? $this->_result : $this->render());
		}

	}

	$fields = array();

	if(isset($_POST['fields'])) $fields = $_POST['fields'];
	else{

		$fields['docroot'] = rtrim(getcwd_safe(), '/');
		$fields['database']['host'] = 'localhost';
		$fields['database']['port'] = '3306';
		$fields['database']['prefix'] = 'sym_';
		$fields['database']['use-server-encoding'] = 'no';
		$fields['permission']['file'] = '0644';
		$fields['permission']['directory'] = '0755';

		$conf = getDynamicConfiguration();
		$fields['general']['sitename'] = $conf['general']['sitename'];
		$fields['region']['date_format'] = $conf['region']['date_format'];
		$fields['region']['time_format'] = $conf['region']['time_format'];
		$fields['region']['datetime_separator'] = $conf['region']['datetime_separator'];

	}

	Class Display{

		function index(&$Page, &$Contents, $fields){

			global $warnings;
			global $notices;
			global $languages;

			$Form = new XMLElement('form');
			$Form->setAttribute('action', kINSTALL_FILENAME.($_GET['lang'] ? '?lang='.$_GET['lang'] : ''));
			$Form->setAttribute('method', 'post');

		// START ENVIRONMENT SETTINGS
			$Environment = new XMLElement('fieldset');
			$Environment->appendChild(new XMLElement('legend', __('Environment Settings')));
			$Environment->appendChild(new XMLElement('p', __('Symphony is ready to be installed at the following location.')));

			$class = NULL;
			if(defined('kENVIRONMENT_WARNING') && kENVIRONMENT_WARNING == true) $class = 'warning';

			$Environment->appendChild(Widget::label(__('Root Path'), Widget::input('fields[docroot]', $fields['docroot']), $class));

			if(defined('ERROR') && defined('kENVIRONMENT_WARNING')) $Environment->appendChild(new XMLElement('p', $warnings[ERROR], array('class' => 'warning')));

			$Form->appendChild($Environment);

		// START LOCALE SETTINGS
			$Environment = new XMLElement('fieldset');
			$Environment->appendChild(new XMLElement('legend', __('Website Preferences')));
			$Environment->appendChild(Widget::label(__('Name'), Widget::input('fields[general][sitename]', $fields['general']['sitename'])));

				$Fieldset = new XMLElement('fieldset');
			$Fieldset->appendChild(new XMLElement('legend', __('Date and Time')));
			$Fieldset->appendChild(new XMLElement('p', __('Customise how Date and Time values are displayed throughout the Administration interface.')));


			$options = array();
			$groups = array();

			$system_tz = (isset($fields['region']['timezone']) ? $fields['region']['timezone'] : date_default_timezone_get());

			foreach(timezone_identifiers_list() as $tz){

				if(preg_match('/\//', $tz)){
					$parts = preg_split('/\//', $tz, 2, PREG_SPLIT_NO_EMPTY);

					$groups[$parts[0]][] = $parts[1];
				}

				else $groups[$tz] = $tz;

			}

			foreach($groups as $key => $val){
				if(is_array($val)){
					$tmp = array('label' => $key, 'options' => array());
					foreach($val as $zone){
						$tmp['options'][] = array("$key/$zone", "$key/$zone" == $system_tz, str_replace('_', ' ', $zone));
					}
					$options[] = $tmp;
				}
				else $options[] = array($key, $key == $system_tz, str_replace('_', ' ', $key));
			}

			$Fieldset->appendChild(Widget::label(__('Region'), Widget::Select('fields[region][timezone]', $options)));

			$dateformat = $fields['region']['date_format'];
			$label = Widget::Label(__('Date Format'));
			$dateFormats = array(
				'Y/m/d',	// e. g. 2011/01/20
				'm/d/Y',	// e. g. 01/20/2011
				'm/d/y',	// e. g. 10/20/11
				'Y-m-d',	// e. g. 2011-01-20
				'm-d-Y',	// e. g. 01-20-2011
				'm-d-y',	// e. g. 01-20-11
				'd.m.Y',	// e. g. 20.01.2011
				'j.n.Y',	// e. g. 20.1.2011 - no leading zeros
				'd.m.y',	// e. g. 20.01.11
				'j.n.y',	// e. g. 20.1.11 - no leading zeros
				'd F Y',	// e. g. 20 January 2011
				'd M Y',	// e. g. 20 Jan 2011
				'j. F Y',	// e. g. 20. January 2011 - no leading zeros
				'j. M. Y',	// e. g. 20. Jan. 2011 - no leading zeros
			);

			$dateOptions = array();
			foreach($dateFormats as $dateOption) {
				$leadingZero = '';
				if(strpos($dateOption, 'j') !== false || strpos($dateOption, 'n') !== false) {
					$leadingZero = ' (' . __('no leading zeros') . ')';
				}
				$dateOptions[] = array($dateOption, $dateformat == $dateOption, DateTimeObj::format('now', $dateOption) . $leadingZero);
			}

			$label->appendChild(Widget::Select('fields[region][date_format]', $dateOptions));
			$Fieldset->appendChild($label);

			$timeformat = $fields['region']['time_format'];
			$label = Widget::Label(__('Time Format'));

			$timeformats = array(
				array('H:i:s', $timeformat == 'H:i:s', DateTimeObj::get('H:i:s')),
				array('H:i', $timeformat == 'H:i', DateTimeObj::get('H:i')),
				array('g:i:s a', $timeformat == 'g:i:s a', DateTimeObj::get('g:i:s a')),
				array('g:i a', $timeformat == 'g:i a', DateTimeObj::get('g:i a')),
			);
			$label->appendChild(Widget::Select('fields[region][time_format]', $timeformats));
			$Fieldset->appendChild($label);


			$Environment->appendChild($Fieldset);

			$Form->appendChild($Environment);

		 // START DATABASE SETTINGS

			$Database = new XMLElement('fieldset');
			$Database->appendChild(new XMLElement('legend', __('Database Connection')));
			$Database->appendChild(new XMLElement('p', __('Please provide Symphony with access to a database.')));

			$class = NULL;
			if(defined('kDATABASE_VERSION_WARNING') && kDATABASE_VERSION_WARNING == true) $class = ' warning';

			## fields[database][name]
			$label = Widget::label(__('Database'), Widget::input('fields[database][name]', $fields['database']['name']), $class);
			$Database->appendChild($label);

			if(defined('ERROR') && defined('kDATABASE_VERSION_WARNING'))
				$Database->appendChild(new XMLElement('p', $warnings[ERROR], array('class' => 'warning')));

			$class = NULL;
			if(defined('kDATABASE_CONNECTION_WARNING') && kDATABASE_CONNECTION_WARNING == true) $class = ' warning';

			$Div = new XMLElement('div');
			$Div->setAttribute('class', 'group' . $class);

			## fields[database][username]
			$Div->appendChild(Widget::label(__('Username'), Widget::input('fields[database][username]', $fields['database']['username'])));

			## fields[database][password]
			$Div->appendChild(Widget::label(__('Password'), Widget::input('fields[database][password]', $fields['database']['password'], 'password')));

			$Database->appendChild($Div);

			if(defined('ERROR') && defined('kDATABASE_CONNECTION_WARNING'))
				$Database->appendChild(new XMLElement('p', $warnings[ERROR], array('class' => 'warning')));

			$Fieldset = new XMLElement('fieldset');
			$Fieldset->appendChild(new XMLElement('legend', __('Advanced Configuration')));
			$Fieldset->appendChild(new XMLElement('p', __('Leave these fields unless you are sure they need to be changed.')));

			$Div = new XMLElement('div');
			$Div->setAttribute('class', 'group');

			## fields[database][host]
			$Div->appendChild(Widget::label(__('Host'), Widget::input('fields[database][host]', $fields['database']['host'])));

			## fields[database][port]
			$Div->appendChild(Widget::label(__('Port'), Widget::input('fields[database][port]', $fields['database']['port'])));

			$Fieldset->appendChild($Div);

			$class = NULL;
			if(defined('kDATABASE_PREFIX_WARNING') && kDATABASE_PREFIX_WARNING == true) $class = 'warning';

			## fields[database][prefix]
			$Fieldset->appendChild(Widget::label(__('Table Prefix'), Widget::input('fields[database][prefix]', $fields['database']['prefix']), $class));

			if(defined('ERROR') && defined('kDATABASE_PREFIX_WARNING'))
				$Fieldset->appendChild(new XMLElement('p', $warnings[ERROR], array('class' => 'warning')));

			$Page->setTemplateVar('TABLE-PREFIX', $fields['database']['prefix']);

			## Use UTF-8 at all times unless otherwise specified
			$Fieldset->appendChild(Widget::label(__('Always use <code>UTF-8</code> encoding'), Widget::input('fields[database][use-server-encoding]', 'no', 'checkbox', !isset($fields['database']['use-server-encoding']) ? array() : array('checked' => 'checked')), 'option'));

			$Fieldset->appendChild(new XMLElement('p', __("If unchecked, Symphony will use your database's default encoding instead of <code>UTF-8</code>.")));

			$Database->appendChild($Fieldset);

			$Form->appendChild($Database);

		// START PERMISSION SETTINGS
			$Permissions = new XMLElement('fieldset');
			$Permissions->appendChild(new XMLElement('legend', __('Permission Settings')));
			$Permissions->appendChild(new XMLElement('p', __('Symphony needs permission to read and write both files and directories.')));

			$Div = new XMLElement('div');
			$Div->setAttribute('class', 'group');

			$Div->appendChild(Widget::label(__('Files'), Widget::input('fields[permission][file]', $fields['permission']['file'])));
			$Div->appendChild(Widget::label(__('Directories'), Widget::input('fields[permission][directory]', $fields['permission']['directory'])));

			$Permissions->appendChild($Div);
			$Form->appendChild($Permissions);

		// START USER SETTINGS
			$User = new XMLElement('fieldset');
			$User->appendChild(new XMLElement('legend', __('User Information')));
			$User->appendChild(new XMLElement('p', __('Once installed, you will be able to login to the Symphony admin with these user details.')));

			$class = NULL;
			if(defined('kUSER_USERNAME_WARNING') && kUSER_PASSWORD_WARNING == true) $class = 'warning';

			## fields[user][username]
			$User->appendChild(Widget::label(__('Username'), Widget::input('fields[user][username]', $fields['user']['username']), $class));

			if(defined('ERROR') && defined('kUSER_USERNAME_WARNING'))
				$User->appendChild(new XMLElement('p', $warnings[ERROR], array('class' => 'warning')));

			$class = NULL;
			if(defined('kUSER_PASSWORD_WARNING') && kUSER_PASSWORD_WARNING == true) $class = ' warning';

			$Div = new XMLElement('div');
			$Div->setAttribute('class', 'group' . $class);

			## fields[user][password]
			$Div->appendChild(Widget::label(__('Password'), Widget::input('fields[user][password]', $fields['user']['password'], 'password')));

			## fields[user][confirm-password]
			$Div->appendChild(Widget::label(__('Confirm Password'), Widget::input('fields[user][confirm-password]', $fields['user']['confirm-password'], 'password')));

			$User->appendChild($Div);

			if(defined('ERROR') && defined('kUSER_PASSWORD_WARNING'))
				$User->appendChild(new XMLElement('p', $warnings[ERROR], array('class' => 'warning')));

			$Fieldset = new XMLElement('fieldset');
			$Fieldset->appendChild(new XMLElement('legend', __('Personal Information')));
			$Fieldset->appendChild(new XMLElement('p', __('Please add the following personal details for this user.')));

			$class = NULL;
			if(defined('kUSER_NAME_WARNING') && kUSER_EMAIL_WARNING == true) $class = ' warning';

			$Div = new XMLElement('div');
			$Div->setAttribute('class', 'group' . $class);

			$Div->appendChild(Widget::label(__('First Name'), Widget::input('fields[user][firstname]', $fields['user']['firstname'])));
			$Div->appendChild(Widget::label(__('Last Name'), Widget::input('fields[user][lastname]', $fields['user']['lastname'])));

			$Fieldset->appendChild($Div);

			if(defined('ERROR') && defined('kUSER_NAME_WARNING'))
				$Fieldset->appendChild(new XMLElement('p', $warnings[ERROR], array('class' => 'warning')));

			$class = NULL;
			if(defined('kUSER_EMAIL_WARNING') && kUSER_EMAIL_WARNING == true) $class = 'warning';

			## fields[user][email]
			$Fieldset->appendChild(Widget::label(__('Email Address'), Widget::input('fields[user][email]', $fields['user']['email']), $class));

			if(defined('ERROR') && defined('kUSER_EMAIL_WARNING'))
				$Fieldset->appendChild(new XMLElement('p', $warnings[ERROR], array('class' => 'warning')));

			$User->appendChild($Fieldset);

			$Form->appendChild($User);

		// START FORM SUBMIT AREA
			$Form->appendChild(new XMLElement('h2', __('Install Symphony')));
			$Form->appendChild(new XMLElement('p', __('Make sure that you delete <code>%s</code> file after Symphony has installed successfully.', array(kINSTALL_FILENAME))));

			$Submit = new XMLElement('div');
			$Submit->setAttribute('class', 'submit');

			### submit
			$Submit->appendChild(Widget::input('submit', __('Install Symphony'), 'submit'));

			### action[install]
			$Submit->appendChild(Widget::input('action[install]', 'true', 'hidden'));

			$Form->appendChild($Submit);
			$Contents->appendChild($Form);

			$Page->setTemplateVar('title', __('Install Symphony'));
			$Page->setTemplateVar('tagline', __('Version %s', array(kVERSION)));
			$Page->setTemplateVar('languages', $languages);
		}

		function requirements(&$Page, &$Contents){

			$Contents->appendChild(new XMLElement('h2', __('Outstanding Requirements')));
			$Contents->appendChild(new XMLElement('p', __('Symphony needs the following requirements satisfied before installation can proceed.')));

			$messages = array();

			if(in_array(MISSING_PHP, $Page->missing))
				$messages[] = array(__('<abbr title="PHP: Hypertext Pre-processor">PHP</abbr> 5.1 or above'),
									__('Symphony needs a recent version of <abbr title="PHP: Hypertext Pre-processor">PHP</abbr>.'));

			if(in_array(MISSING_MYSQL, $Page->missing))
				$messages[] = array(__('My<abbr title="Structured Query Language">SQL</abbr> 4.1 or above'),
								__('Symphony needs a recent version of My<abbr title="Structured Query Language">SQL</abbr>.'));

			if(in_array(MISSING_ZLIB, $Page->missing))
				$messages[] = array(__('ZLib Compression Library'),
									__('Data retrieved from the Symphony support server is decompressed with the ZLib compression library.'));

			if(in_array(MISSING_XSL, $Page->missing) || in_array(MISSING_XML, $Page->missing))
				$messages[] = array(__('<abbr title="eXtensible Stylesheet Language Transformation">XSLT</abbr> Processor'),
									__('Symphony needs an XSLT processor such as Lib<abbr title="eXtensible Stylesheet Language Transformation">XSLT</abbr> or Sablotron to build pages.'));

			$dl = new XMLElement('dl');
			foreach($messages as $m){
				$dl->appendChild(new XMLElement('dt', $m[0]));
				$dl->appendChild(new XMLElement('dd', $m[1]));
			}

			$Contents->appendChild($dl);

			$Page->setTemplateVar('title', __('Missing Requirements'));
			$Page->setTemplateVar('tagline', __('Version %s', array(kVERSION)));

			global $languages;
			$Page->setTemplateVar('languages', $languages);
		}

		function uptodate(&$Page, &$Contents){
			$Contents->appendChild(new XMLElement('h2', __('Update Symphony')));
			$Contents->appendChild(new XMLElement('p', __('You are already using the most recent version of Symphony. There is no need to run the installer, and can be safely deleted.')));

			$Page->setTemplateVar('title', __('Update Symphony'));
			$Page->setTemplateVar('tagline', __('Version %s', array(kVERSION)));

			global $languages;
			$Page->setTemplateVar('languages', $languages);
		}

		function incorrectVersion(&$Page, &$Contents){
			$Contents->appendChild(new XMLElement('h2', __('Update Symphony')));
			$Contents->appendChild(new XMLElement('p', __('You are not using the most recent version of Symphony. This update is only compatible with Symphony 2.')));

			$Page->setTemplateVar('title', __('Update Symphony'));
			$Page->setTemplateVar('tagline', __('Version %s', array(kVERSION)));

			global $languages;
			$Page->setTemplateVar('languages', $languages);
		}

		function failure(&$Page, &$Contents){

			$Contents->appendChild(new XMLElement('h2', __('Installation Failure')));
			$Contents->appendChild(new XMLElement('p', __('An error occurred during installation. You can view you log <a href="install-log.txt">here</a> for more details.')));

			$Page->setTemplateVar('title', __('Installation Failure'));
			$Page->setTemplateVar('tagline', __('Version %s', array(kVERSION)));

			global $languages;
			$Page->setTemplateVar('languages', $languages);
		}
	}

	$Log = new SymphonyLog('install-log.txt');

	$Page = new InstallPage($Log);

	$Page->setHeader(kHEADER);
	$Page->setFooter(kFOOTER);

	$Contents = new XMLElement('body');
	$Contents->appendChild(new XMLElement('h1', '<!-- TITLE --> <em><!-- TAGLINE --></em>'));
	$Contents->appendChild(new XMLElement('ul', '<!-- LANGUAGES --><li class="more"><a href="http://symphony-cms.com/download/extensions/translations/">' . __('Symphony is also available in other languages') . '</a></li>'));

	if(defined('__IS_UPDATE__') && __IS_UPDATE__ == true)
		$Page->setPage('update');

	elseif(defined('__ALREADY_UP_TO_DATE__') && __ALREADY_UP_TO_DATE__ == true)
		$Page->setPage('uptodate');

	else{
		$Page->setPage('index');
		Action::requirements($Page);
	}

	if(is_array($Page->missing) && !empty($Page->missing)) $Page->setPage('requirements');
	elseif(isset($_POST['action'])){

		$action = array_keys($_POST['action']);
		$action = $action[0];

		call_user_func_array(array('Action', $action), array(&$Page, $fields));
	}

	call_user_func_array(array('Display', $Page->getPage()), array(&$Page, &$Contents, $fields));

	$Page->setContent($Contents->generate(true, 2));
	$output = $Page->display();

	header('Content-Type: text/html; charset=UTF-8');
	header(sprintf('Content-Length: %d', strlen($output)));
	echo $output;
