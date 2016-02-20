<?php
/***********************************************************************
| Cerb(tm) developed by Webgroup Media, LLC.
|-----------------------------------------------------------------------
| All source code & content (c) Copyright 2002-2015, Webgroup Media LLC
|   unless specifically noted otherwise.
|
| This source code is released under the Devblocks Public License.
| The latest version of this license can be found here:
| http://cerbweb.com/license
|
| By using this software, you acknowledge having read this license
| and agree to be bound thereby.
| ______________________________________________________________________
|	http://www.cerbweb.com	    http://www.webgroupmedia.com/
***********************************************************************/
/*
 * IMPORTANT LICENSING NOTE from your friends on the Cerb Development Team
 *
 * Sure, it would be so easy to just cheat and edit this file to use the
 * software without paying for it.  But we trust you anyway.  In fact, we're
 * writing this software for you!
 *
 * Quality software backed by a dedicated team takes money to develop.  We
 * don't want to be out of the office bagging groceries when you call up
 * needing a helping hand.  We'd rather spend our free time coding your
 * feature requests than mowing the neighbors' lawns for rent money.
 *
 * We've never believed in hiding our source code out of paranoia over not
 * getting paid.  We want you to have the full source code and be able to
 * make the tweaks your organization requires to get more done -- despite
 * having less of everything than you might need (time, people, money,
 * energy).  We shouldn't be your bottleneck.
 *
 * We've been building our expertise with this project since January 2002.  We
 * promise spending a couple bucks [Euro, Yuan, Rupees, Galactic Credits] to
 * let us take over your shared e-mail headache is a worthwhile investment.
 * It will give you a sense of control over your inbox that you probably
 * haven't had since spammers found you in a game of 'E-mail Battleship'.
 * Miss. Miss. You sunk my inbox!
 *
 * A legitimate license entitles you to support from the developers,
 * and the warm fuzzy feeling of feeding a couple of obsessed developers
 * who want to help you get more done.
 *
 \* - Jeff Standen, Darren Sugita, Dan Hildebrandt
 *	 Webgroup Media LLC - Developers of Cerb
 */
define("APP_BUILD", 2016012301);
define("APP_VERSION", '7.1.3');

define("APP_MAIL_PATH", APP_STORAGE_PATH . '/mail/');

require_once(APP_PATH . "/api/Model.class.php");
require_once(APP_PATH . "/api/Extension.class.php");

// App Scope ClassLoading
$path = APP_PATH . '/api/app/';

DevblocksPlatform::registerClasses($path . 'Mail.php', array(
	'CerberusMail',
));

DevblocksPlatform::registerClasses($path . 'Parser.php', array(
	'CerberusParser',
	'CerberusParserMessage',
	'CerberusParserModel',
	'ParserFile',
	'ParserFileBuffer',
));

DevblocksPlatform::registerClasses($path . 'Update.php', array(
	'ChUpdateController',
));

DevblocksPlatform::registerClasses($path . 'Utils.php', array(
	'CerberusUtils',
));

/**
 * Application-level Facade
 */
class CerberusApplication extends DevblocksApplication {
	private static $_active_worker = null;

	/**
	 * @return CerberusVisit
	 */
	static function getVisit() {
		$session = DevblocksPlatform::getSessionService();
		return $session->getVisit();
	}

	static function setActiveWorker($worker) {
		self::$_active_worker = $worker;
	}

	/**
	 * @return Model_Worker
	 */
	static function getActiveWorker() {
		if(isset(self::$_active_worker))
			return self::$_active_worker;

		$visit = self::getVisit();
		return (null != $visit)
			? $visit->getWorker()
			: null
			;
	}

	static function getWorkersByAtMentionsText($text) {
		$workers = array();

		if(false !== ($at_mentions = DevblocksPlatform::parseAtMentionString($text))) {
			$workers = DAO_Worker::getByAtMentions($at_mentions);
		}

		return $workers;
	}
	
	// [TODO] Cache by worker? (esp responsibility + availability + workloads)
	static function getWorkerPickerData($population, $sample, $group_id=0, $bucket_id=0) {
		// Shared objects
		
		$online_workers = DAO_Worker::getAllOnline();
		$group_responsibilities = DAO_Group::getResponsibilities($group_id);
		$bucket_responsibilities = @$group_responsibilities[$bucket_id] ?: array();
		$workloads = DAO_Worker::getWorkloads();
		// [TODO] Do availability efficiently 
		
		// Workers
		
		$picker_workers = array(
			'sample' => array(),
			'population' => array(),
		);
		
		// Bulk load population statistics
		foreach($population as $worker) {
			$worker->__is_selected = isset($sample[$worker->id]);
			$worker->__is_online = isset($online_workers[$worker->id]);
			$worker->__availability = $worker->getAvailabilityAsBlocks();
			$worker->__workload = isset($workloads[$worker->id]) ? $workloads[$worker->id] : array();
			$worker->__responsibility = isset($bucket_responsibilities[$worker->id]) ? $bucket_responsibilities[$worker->id] : 0;
		}
		
		// Sort population by score
		uasort($population, function($a, $b) {
			if($a->__responsibility == $b->__responsibility)
				return 0;
			
			return ($a->__responsibility < $b->__responsibility) ? 1 : -1;
		});
		
		// Set sample
		foreach($sample as &$worker) {
			if(!isset($population[$worker->id]))
				continue;
			
			$picker_workers['sample'][$worker->id] = $worker;
			unset($population[$worker->id]);
		}
		
		// Set remaining population
		foreach($population as &$worker) {
			$picker_workers['population'][$worker->id] = $worker;
		}
		
		// Return a result object
		return array(
			'show_responsibilities' => !empty($group_id),
			'workers' => $picker_workers,
		);
	}
	
	static function getFileBundleDictionaryJson() {
		$file_bundles = DAO_FileBundle::getAll();
		$active_worker = CerberusApplication::getActiveWorker();

		$list = array();

		if(is_array($file_bundles))
		foreach($file_bundles as $file_bundle) { /* @var $file_bundle Model_FileBundle */
			// Filter by owner/readable
			if($active_worker && !$file_bundle->isReadableByActor($active_worker))
				continue;

			$list[] = array(
				'id' => $file_bundle->id,
				'name' => $file_bundle->name,
				'tag' => $file_bundle->tag,
			);
		}

		return json_encode($list);
	}

	static function getAtMentionsWorkerDictionaryJson() {
		$workers = DAO_Worker::getAllActive();

		$list = array();

		foreach($workers as $worker) {
			$list[] = array(
				'id' => $worker->id,
				'name' => $worker->getName(),
				'email' => $worker->getEmailString(),
				'title' => $worker->title,
				'at_mention' => $worker->at_mention_name,
				'_index' => $worker->getName() . ' ' . $worker->at_mention_name,
			);
		}

		return json_encode($list);
	}

	/**
	 *
	 * @param string $uri
	 * @return DevblocksExtensionManifest or NULL
	 */
	static function getPageManifestByUri($uri) {
		$pages = DevblocksPlatform::getExtensions('cerberusweb.page', false);
		foreach($pages as $manifest) { /* @var $manifest DevblocksExtensionManifest */
			if(0 == strcasecmp($uri,$manifest->params['uri'])) {
				return $manifest;
			}
		}
		return NULL;
	}

	static function processRequest(DevblocksHttpRequest $request, $is_ajax=false) {
		/**
		 * Override the 'update' URI since we can't count on the database
		 * being populated from XML beforehand when /update loads it.
		 */
		if(!$is_ajax && isset($request->path[0]) && 0 == strcasecmp($request->path[0],'update')) {
			if(null != ($update_controller = new ChUpdateController(null)))
				$update_controller->handleRequest($request);

		} else {
			// Hand it off to the platform
			DevblocksPlatform::processRequest($request, $is_ajax);
		}
	}

	static function checkRequirements() {
		$errors = array();

		// Privileges

		// Make sure the temporary directories of Devblocks are writeable.
		if(!is_writeable(APP_TEMP_PATH)) {
			$errors[] = APP_TEMP_PATH ." is not writeable by the webserver.  Please adjust permissions and reload this page.";
		}

		if(!file_exists(APP_TEMP_PATH . "/templates_c")) {
			@mkdir(APP_TEMP_PATH . "/templates_c");
		}

		if(!is_writeable(APP_TEMP_PATH . "/templates_c/")) {
			$errors[] = APP_TEMP_PATH . "/templates_c/" . " is not writeable by the webserver.  Please adjust permissions and reload this page.";
		}

		if(!file_exists(APP_TEMP_PATH . "/cache")) {
			@mkdir(APP_TEMP_PATH . "/cache");
		}

		if(!is_writeable(APP_TEMP_PATH . "/cache/")) {
			$errors[] = APP_TEMP_PATH . "/cache/" . " is not writeable by the webserver.  Please adjust permissions and reload this page.";
		}

		if(!is_writeable(APP_STORAGE_PATH)) {
			$errors[] = APP_STORAGE_PATH ." is not writeable by the webserver.  Please adjust permissions and reload this page.";
		}

		if(!is_writeable(APP_STORAGE_PATH . "/import/fail")) {
			$errors[] = APP_STORAGE_PATH . "/import/fail/" ." is not writeable by the webserver.  Please adjust permissions and reload this page.";
		}

		if(!is_writeable(APP_STORAGE_PATH . "/import/new")) {
			$errors[] = APP_STORAGE_PATH . "/import/new/" ." is not writeable by the webserver.  Please adjust permissions and reload this page.";
		}

		if(!is_writeable(APP_STORAGE_PATH . "/mail/new/")) {
			$errors[] = APP_STORAGE_PATH . "/mail/new/" ." is not writeable by the webserver.  Please adjust permissions and reload this page.";
		}

		if(!is_writeable(APP_STORAGE_PATH . "/mail/fail/")) {
			$errors[] = APP_STORAGE_PATH . "/mail/fail/" ." is not writeable by the webserver.  Please adjust permissions and reload this page.";
		}

		// Requirements

		// PHP Version
		if(version_compare(PHP_VERSION,"5.3") >=0) {
		} else {
			$errors[] = sprintf("Cerb %s requires PHP 5.3 or later. Your server PHP version is %s",
				APP_VERSION,
				PHP_VERSION
			);
		}

		// File Uploads
		$ini_file_uploads = ini_get("file_uploads");
		if($ini_file_uploads == 1 || strcasecmp($ini_file_uploads,"on")==0) {
		} else {
			$errors[] = 'file_uploads is disabled in your php.ini file. Please enable it.';
		}

		// Memory Limit
		$memory_limit = ini_get("memory_limit");
		if ($memory_limit == '') { // empty string means failure or not defined, assume no compiled memory limits
		} else {
			$ini_memory_limit = DevblocksPlatform::parseBytesString($memory_limit);
			if($ini_memory_limit < 16777216) {
				$errors[] = 'memory_limit must be 16M or larger (32M recommended) in your php.ini file.  Please increase it.';
			}
		}

		// Extension: MySQLi
		if(extension_loaded("mysqli")) {
		} else {
			$errors[] = "The 'MySQLi' PHP extension is required.  Please enable it.";
		}

		// Extension: Sessions
		if(extension_loaded("session")) {
		} else {
			$errors[] = "The 'Session' PHP extension is required.  Please enable it.";
		}

		// Extension: cURL
		if(extension_loaded("curl")) {
		} else {
			$errors[] = "The 'cURL' PHP extension is required.  Please enable it.";
		}

		// Extension: PCRE
		if(extension_loaded("pcre")) {
		} else {
			$errors[] = "The 'PCRE' PHP extension is required.  Please enable it.";
		}

		// Extension: GD
		if(extension_loaded("gd") && function_exists('imagettfbbox')) {
		} else {
			$errors[] = "The 'GD' PHP extension (with FreeType library support) is required.  Please enable them.";
		}

		// Extension: IMAP
		if(extension_loaded("imap")) {
		} else {
			$errors[] = "The 'IMAP' PHP extension is required.  Please enable it.";
		}

		// Extension: MailParse
		if(extension_loaded("mailparse")) {
		} else {
			$errors[] = "The 'MailParse' PHP extension is required.  Please enable it.";
		}

		// Extension: mbstring
		if(extension_loaded("mbstring")) {
		} else {
			$errors[] = "The 'mbstring' PHP extension is required.  Please enable it.";
		}

		// Extension: XML
		if(extension_loaded("xml")) {
		} else {
			$errors[] = "The 'XML' PHP extension is required.  Please enable it.";
		}

		// Extension: SimpleXML
		if(extension_loaded("simplexml")) {
		} else {
			$errors[] = "The 'SimpleXML' PHP extension is required.  Please enable it.";
		}

		// Extension: DOM
		if(extension_loaded("dom")) {
		} else {
			$errors[] = "The 'DOM' PHP extension is required.  Please enable it.";
		}

		// Extension: SPL
		if(extension_loaded("spl")) {
		} else {
			$errors[] = "The 'SPL' PHP extension is required.  Please enable it.";
		}

		// Extension: ctype
		if(extension_loaded("ctype")) {
		} else {
			$errors[] = "The 'ctype' PHP extension is required.  Please enable it.";
		}

		// Extension: JSON
		if(extension_loaded("json")) {
		} else {
			$errors[] = "The 'JSON' PHP extension is required.  Please enable it.";
		}

		return $errors;
	}

	static function update() {
		// Update the platform
		if(!DevblocksPlatform::update())
			throw new Exception("Couldn't update Devblocks.");

		// Read in plugin information from the filesystem to the database
		DevblocksPlatform::readPlugins();

		// Clean up missing plugins
		DAO_Platform::cleanupPluginTables();
		DAO_Platform::maint();

		// Download updated plugins from repository
		// [TODO] This causes problems on an intranet
		if(class_exists('DAO_PluginLibrary'))
			DAO_PluginLibrary::downloadUpdatedPluginsFromRepository();

		// Registry
		$plugins = DevblocksPlatform::getPluginRegistry();

		// Update the application core (version by version)
		if(!isset($plugins['cerberusweb.core']))
			throw new Exception("Couldn't read application manifest.");

		$plugin_patches = array();

		// Load patches
		foreach($plugins as $p) { /* @var $p DevblocksPluginManifest */
			if('devblocks.core'==$p->id)
				continue;

			// Don't patch disabled plugins
			if($p->enabled) {
				// Ensure that the plugin requirements match, or disable
				if(!$p->checkRequirements()) {
					$p->setEnabled(false);
					continue;
				}

				$plugin_patches[$p->id] = $p->getPatches();
			}
		}

		$core_patches = $plugin_patches['cerberusweb.core'];
		unset($plugin_patches['cerberusweb.core']);

		/*
		 * For each core release, patch plugins in dependency order
		 */
		foreach($core_patches as $patch) { /* @var $patch DevblocksPatch */
			if(!file_exists($patch->getFilename()))
				throw new Exception("Missing application patch: ".$patch->getFilename());

			$version = $patch->getVersion();

			if(!$patch->run())
				throw new Exception("Application patch failed to apply: ".$patch->getFilename());

			// Patch this version and then patch plugins up to this version
			foreach($plugin_patches as $plugin_id => $patches) {
				$pass = true;
				foreach($patches as $k => $plugin_patch) {
					// Recursive patch up to _version_
					if($pass && version_compare($plugin_patch->getVersion(), $version, "<=")) {
						if($plugin_patch->run()) {
							unset($plugin_patches[$plugin_id][$k]);
						} else {
							$plugins[$plugin_id]->setEnabled(false);
							$pass = false;
						}
					}
				}
			}
		}

		return TRUE;
	}

	/**
	 *
	 * @param integer $length
	 * @return string
	 * @test CerberusApplicationTest
	 */
	static function generatePassword($length=8) {
		$chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ123456789';
		$len = strlen($chars)-1;
		$password = '';

		for($x=0;$x<$length;$x++) {
			$chars = str_shuffle($chars);
			$password .= substr($chars,mt_rand(0,$len),1);
		}

		return $password;
	}

	/**
	 * @return a unique ticket mask as a string
	 */
	static function generateTicketMask($pattern = null) {
		if(empty($pattern))
			$pattern = DevblocksPlatform::getPluginSetting('cerberusweb.core', CerberusSettings::TICKET_MASK_FORMAT);
		if(empty($pattern))
			$pattern = CerberusSettingsDefaults::TICKET_MASK_FORMAT;

		$letters = "ABCDEFGHIJKLMNPQRSTUVWXYZ";
		$numbers = "123456789";

		do {
			$mask = "";
			$bytes = str_split($pattern, 1);
			$literal = false;

			if(is_array($bytes))
			foreach($bytes as $byte) {
				$append = '';

				switch(strtoupper($byte)) {
					case '{':
						$literal = true;
						$byte = '';
						break;
					case '}':
						$literal = false;
						$append = '';
						break;
					case 'L':
						$append .= substr($letters,mt_rand(0,strlen($letters)-1),1);
						break;
					case 'N':
						$append .= substr($numbers,mt_rand(0,strlen($numbers)-1),1);
						break;
					case 'C': // L or N
						if(mt_rand(0,100) >= 50) { // L
							$append .= substr($letters,mt_rand(0,strlen($letters)-1),1);
						} else { // N
							$append .= substr($numbers,mt_rand(0,strlen($numbers)-1),1);
						}
						break;
					case 'Y':
						$append .= date('Y');
						break;
					case 'M':
						$append .= date('m');
						break;
					case 'D':
						$append .= date('d');
						break;
					default:
						$append .= $byte;
						break;
				}

				if($literal) {
					$mask .= $byte;
				} else {
					$mask .= $append;
				}

				$mask = strtoupper(DevblocksPlatform::strAlphaNum($mask,'\-'));
			}
		} while(null != DAO_Ticket::getTicketIdByMask($mask));

		return $mask;
	}

	static function generateTicketMaskCardinality($pattern = null) {
		if(empty($pattern))
			$pattern = DevblocksPlatform::getPluginSetting('cerberusweb.core', CerberusSettings::TICKET_MASK_FORMAT);
		if(empty($pattern))
			$pattern = CerberusSettingsDefaults::TICKET_MASK_FORMAT;

		$combinations = 1;
		$bytes = str_split($pattern, 1);
		$literal = false;

		if(is_array($bytes))
		foreach($bytes as $byte) {
			$mul = 1;
			switch(strtoupper($byte)) {
				case '{':
					$literal = true;
					break;
				case '}':
					$literal = false;
					break;
				case 'L':
					$mul *= 25;
					break;
				case 'N':
					$mul *= 9;
					break;
				case 'C': // L or N
					$mul *= 34;
					break;
				case 'Y':
					$mul *= 1;
					break;
				case 'M':
					$mul *= 12;
					break;
				case 'D':
					$mul *= 30;
					break;
				default:
					break;
			}

			if(!$literal)
				$combinations = round($combinations*$mul,0);
		}

		return $combinations;
	}

	/**
	 * Generate an RFC-compliant Message-ID
	 *
	 * @return string
	 * @test CerberusApplicationTest
	 */
	static function generateMessageId() {
		$host = @$_SERVER['HTTP_HOST'];
		$server_name = @$_SERVER['SERVER_NAME'] ?: 'localhost';
		$message_id = sprintf('<%s.%s@%s>', base_convert(time(), 10, 36), base_convert(mt_rand(), 10, 36), $host ?: $server_name);
		return $message_id;
	}

	/**
	 * Looks up an e-mail address using a revolving cache.  This is helpful
	 * in situations where you may look up the same e-mail address multiple
	 * times (reports, audit log, views) and you don't want to waste code
	 * filtering out dupes.
	 *
	 * @param string $email_or_id The email address or ID to lookup
	 * @param bool $create Should the address be created if not found? (only with address lookups, not ID)
	 * @return Model_Address The address object or NULL
	 *
	 * @todo [JAS]: Move this to a global cache/hash registry
	 */
	static public function hashLookupAddress($email_or_id, $create=false) {
		static $hash_to_address = array();
		static $hash_hits = array();
		static $hash_size = 0;

		if(isset($hash_to_address[$email_or_id])) {
			$return = $hash_to_address[$email_or_id];

			@$hash_hits[$email_or_id] = intval($hash_hits[$email_or_id]) + 1;

			// [JAS]: if our hash grows past our limit, crop hits array + intersect keys
			if($hash_size > 100) {
				arsort($hash_hits);
				$hash_hits = array_slice($hash_hits,0,25,true);
				$hash_to_address = array_intersect_key($hash_to_address, $hash_hits);
				$hash_size = count($hash_to_address);
			}

			return $return;
		}

		// Find the address record by email or ID
		$address = is_numeric($email_or_id)
			? DAO_Address::get($email_or_id)
			: DAO_Address::lookupAddress($email_or_id, $create)
			;

		if(!empty($address)) {
			$hash_to_address[$email_or_id] = $address;
			$hash_size++;
		}

		return $address;
	}

	static public function hashLookupAddresses($emails_or_ids, $create=false) {
		$results = array();

		foreach($emails_or_ids as $email_or_id) {
			if(false == ($address = self::hashLookupAddress($email_or_id)))
				continue;

			$results[$address->id] = $address;
		}

		return $results;
	}

	/**
	 * Looks up an org using a revolving cache.  This is helpful
	 * in situations where you may look up the same org multiple
	 * times (reports, audit log, views) and you don't want to waste code
	 * filtering out dupes.
	 *
	 * @param string $name_or_id The org name or ID to lookup
	 * @param bool $create Should the org be created if not found? (only with namelookups, not ID)
	 * @return Model_ContactOrg The org record or NULL
	 *
	 * @todo [JAS]: Move this to a global cache/hash registry
	 */
	static public function hashLookupOrg($name_or_id, $create=false) {
		static $hash_to_org = array();
		static $hash_hits = array();
		static $hash_size = 0;

		if(isset($hash_to_org[$name_or_id])) {
			$return = $hash_to_org[$name_or_id];

			@$hash_hits[$name_or_id] = intval($hash_hits[$name_or_id]) + 1;

			// [JAS]: if our hash grows past our limit, crop hits array + intersect keys
			if($hash_size > 100) {
				arsort($hash_hits);
				$hash_hits = array_slice($hash_hits,0,25,true);
				$hash_to_org = array_intersect_key($hash_to_org, $hash_hits);
				$hash_size = count($hash_to_org);
			}

			return $return;
		}

		// Find the record by name or ID
		$org = is_numeric($name_or_id)
			? DAO_ContactOrg::get($name_or_id)
			: DAO_ContactOrg::lookup($name_or_id, $create)
			;

		if(!empty($org)) {
			$hash_to_org[$name_or_id] = $org;
			$hash_size++;
		}

		return $org;
	}

	static public function hashLookupOrgs($names_or_ids, $create=false) {
		$results = array();

		foreach($names_or_ids as $name_or_ids) {
			if(false == ($org = self::hashLookupOrg($name_or_ids)))
				continue;

			$results[$org->id] = $org;
		}

		return $results;
	}

	/**
	 * Looks up a ticket ID by the provided mask using a revolving cache.
	 * This is useful if you need to translate several ticket masks into
	 * IDs where there may be a lot of redundancy (batches in the e-mail
	 * parser, etc.)
	 *
	 * @param string $mask The ticket mask to look up
	 * @return integer The ticket id, or NULL if not found
	 *
	 * @todo [JAS]: Move this to a global cache/hash registry
	 */
	static public function hashLookupTicketIdByMask($mask) {
		static $hash_mask_to_id = array();
		static $hash_hits = array();
		static $hash_size = 0;

		if(isset($hash_mask_to_id[$mask])) {
			$return = $hash_mask_to_id[$mask];

			@$hash_hits[$mask] = intval($hash_hits[$mask]) + 1;
			$hash_size++;

			// [JAS]: if our hash grows past our limit, crop hits array + intersect keys
			if($hash_size > 200) {
				arsort($hash_hits);
				$hash_hits = array_slice($hash_hits,0,100,true);
				$hash_mask_to_id = array_intersect_key($hash_mask_to_id,$hash_hits);
				$hash_size = count($hash_mask_to_id);
			}

			return $return;
		}

		$ticket_id = DAO_Ticket::getTicketIdByMask($mask);
		if(!empty($ticket_id)) {
			$hash_mask_to_id[$mask] = $ticket_id;
		}
		return $ticket_id;
	}

	/**
	 * Save form-uploaded files as Cerb attachments, with dupe detection.
	 *
	 * @param array $files
	 * @return array
	 */
	static function saveHttpUploadedFiles($files) {
		$file_ids = array();

		// Sanitize
		if(!isset($files['name']) || !isset($files['tmp_name']))
			return false;

		// Convert a single file upload into an array
		if(!is_array($files['name'])) {
			$files['name'] = array($files['name']);
			$files['type'] = array($files['type']);
			$files['tmp_name'] = array($files['tmp_name']);
			$files['error'] = array($files['error']);
			$files['size'] = array($files['size']);
		}

		if (is_array($files) && !empty($files)) {
			reset($files);
			foreach($files['tmp_name'] as $idx => $file) {
				if(empty($file) || empty($files['name'][$idx]) || !file_exists($file))
					continue;

				// Dupe detection
				@$sha1_hash = sha1_file($file, false);

				if(false == ($file_id = DAO_Attachment::getBySha1Hash($sha1_hash, $files['name'][$idx]))) {
					$fields = array(
						DAO_Attachment::DISPLAY_NAME => $files['name'][$idx],
						DAO_Attachment::MIME_TYPE => $files['type'][$idx],
						DAO_Attachment::STORAGE_SHA1HASH => $sha1_hash,
					);
					$file_id = DAO_Attachment::create($fields);

					// Content
					if(null !== ($fp = fopen($file, 'rb'))) {
						Storage_Attachments::put($file_id, $fp);
						fclose($fp);
					}
				}

				// Save results
				if($file_id)
					$file_ids[] = intval($file_id);

				@unlink($file);
			}
		}

		return $file_ids;
	}
};

class CerbException extends DevblocksException {
};

interface IContextToken {
	static function getValue($context, $context_values);
};

class CerberusContexts {
	private static $_is_caching_loads = false;
	private static $_cache_loads = array();

	private static $_default_actor_stack = array();
	private static $_default_actor_context = null;
	private static $_default_actor_context_id = null;

	private static $_stack = array();

	const CONTEXT_APPLICATION = 'cerberusweb.contexts.app';
	const CONTEXT_ACTIVITY_LOG = 'cerberusweb.contexts.activity_log';
	const CONTEXT_ADDRESS = 'cerberusweb.contexts.address';
	const CONTEXT_ASSET = 'cerberusweb.contexts.asset';
	const CONTEXT_ATTACHMENT = 'cerberusweb.contexts.attachment';
	const CONTEXT_ATTACHMENT_LINK = 'cerberusweb.contexts.attachment_link';
	const CONTEXT_BUCKET = 'cerberusweb.contexts.bucket';
	const CONTEXT_CALENDAR = 'cerberusweb.contexts.calendar';
	const CONTEXT_CALENDAR_EVENT = 'cerberusweb.contexts.calendar_event';
	const CONTEXT_CALENDAR_EVENT_RECURRING = 'cerberusweb.contexts.calendar_event.recurring';
	const CONTEXT_CALL = 'cerberusweb.contexts.call';
	const CONTEXT_COMMENT = 'cerberusweb.contexts.comment';
	const CONTEXT_CONTACT = 'cerberusweb.contexts.contact';
	const CONTEXT_CONTEXT_AVATAR = 'cerberusweb.contexts.context.avatar';
	const CONTEXT_CUSTOM_FIELD = 'cerberusweb.contexts.custom_field';
	const CONTEXT_CUSTOM_FIELDSET = 'cerberusweb.contexts.custom_fieldset';
	const CONTEXT_DOMAIN = 'cerberusweb.contexts.datacenter.domain';
	const CONTEXT_DRAFT = 'cerberusweb.contexts.mail.draft';
	const CONTEXT_FEED = 'cerberusweb.contexts.feed';
	const CONTEXT_FEED_ITEM = 'cerberusweb.contexts.feed.item';
	const CONTEXT_FEEDBACK = 'cerberusweb.contexts.feedback';
	const CONTEXT_FILE_BUNDLE = 'cerberusweb.contexts.file_bundle';
	const CONTEXT_GROUP = 'cerberusweb.contexts.group';
	const CONTEXT_KB_ARTICLE = 'cerberusweb.contexts.kb_article';
	const CONTEXT_KB_CATEGORY = 'cerberusweb.contexts.kb_category';
	const CONTEXT_MAIL_TRANSPORT = 'cerberusweb.contexts.mail.transport';
	const CONTEXT_MAILBOX = 'cerberusweb.contexts.mailbox';
	const CONTEXT_MAIL_HTML_TEMPLATE = 'cerberusweb.contexts.mail.html_template';
	const CONTEXT_MAILING_LIST = 'cerberusweb.contexts.mailing_list';
	const CONTEXT_MAILING_LIST_BROADCAST = 'cerberusweb.contexts.mailing_list.broadcast';
	const CONTEXT_MAILING_LIST_MEMBER = 'cerberusweb.contexts.mailing_list.member';
	const CONTEXT_MESSAGE = 'cerberusweb.contexts.message';
	const CONTEXT_NOTIFICATION= 'cerberusweb.contexts.notification';
	const CONTEXT_OPPORTUNITY = 'cerberusweb.contexts.opportunity';
	const CONTEXT_ORG = 'cerberusweb.contexts.org';
	const CONTEXT_PORTAL = 'cerberusweb.contexts.portal';
	const CONTEXT_PROJECT = 'cerberusweb.contexts.project';
	const CONTEXT_PROJECT_ISSUE = 'cerberusweb.contexts.project.issue';
	const CONTEXT_RECOMMENDATION = 'cerberusweb.contexts.recommendation';
	const CONTEXT_ROLE = 'cerberusweb.contexts.role';
	const CONTEXT_SENSOR = 'cerberusweb.contexts.datacenter.sensor';
	const CONTEXT_SERVER = 'cerberusweb.contexts.datacenter.server';
	const CONTEXT_SKILL = 'cerberusweb.contexts.skill';
	const CONTEXT_SKILLSET = 'cerberusweb.contexts.skillset';
	const CONTEXT_SNIPPET = 'cerberusweb.contexts.snippet';
	const CONTEXT_TASK = 'cerberusweb.contexts.task';
	const CONTEXT_TICKET = 'cerberusweb.contexts.ticket';
	const CONTEXT_TIMETRACKING = 'cerberusweb.contexts.timetracking';
	const CONTEXT_VIRTUAL_ATTENDANT = 'cerberusweb.contexts.virtual.attendant';
	const CONTEXT_WORKER = 'cerberusweb.contexts.worker';
	const CONTEXT_WORKSPACE_PAGE = 'cerberusweb.contexts.workspace.page';
	const CONTEXT_WORKSPACE_TAB = 'cerberusweb.contexts.workspace.tab';
	const CONTEXT_WORKSPACE_WIDGET = 'cerberusweb.contexts.workspace.widget';

	public static function setCacheLoads($state) {
		self::$_is_caching_loads = ($state ? true : false);

		// Clear the cache when disabled
		if(!self::$_is_caching_loads) {
			self::$_cache_loads = array();
		}
	}

	public static function getStack() {
		return self::$_stack;
	}

	public static function pushStack($context) {
		self::$_stack[] = $context;
		return self::$_stack;
	}

	public static function popStack() {
		return array_pop(self::$_stack);
	}

	public static function getContext($context, $context_object, &$labels, &$values, $prefix=null, $nested=false, $skip_labels=false) {
		// Push the stack
		self::$_stack[] = $context;

		switch($context) {
			default:
				// Migrated

				if(null != ($ctx = Extension_DevblocksContext::get($context))) {
					// If blank, check the cache for a prebuilt context object
					if(is_null($context_object)) {
						$cache = DevblocksPlatform::getCacheService();

						$hash = md5(serialize(array($context, $prefix, $nested)));
						$cache_key = sprintf("cerb:ctx:%s", $hash);

						// Cache hit
						if(null !== ($data = $cache->load($cache_key, false, true))) {
							$loaded_labels = $data['labels'];
							$loaded_values = $data['values'];

						// Cache miss
						} else {
							$loaded_labels = array();
							$loaded_values = array();
							$ctx->getContext($context_object, $loaded_labels, $loaded_values, $prefix);

							$cache->save(array('labels' => $loaded_labels, 'values' => $loaded_values), $cache_key, array(), 0, true);
						}

						$labels = $loaded_labels;
						$values = $loaded_values;

					} else {

						// If instance caching is enabled
						if(self::$_is_caching_loads) {
							$hash_context_id = $context_object;

							// Hash uniformly (if we have a model, hash as its ID, so an ID only request uses cache)
							if(is_object($context_object) && isset($context_object->id)) {
								$hash_context_id = $context_object->id;
							}

							if(is_numeric($hash_context_id))
								$hash_context_id = intval($hash_context_id);

							$hash = md5(serialize(array($context, $hash_context_id, $prefix, $nested)));

							if(isset(self::$_cache_loads[$hash])) {
								$values = self::$_cache_loads[$hash];

							} else {
								$ctx->getContext($context_object, $labels, $values, $prefix);
								self::$_cache_loads[$hash] = $values;
							}

						} else {
							$ctx->getContext($context_object, $labels, $values, $prefix);

						}

					}
				}
				break;
		}

		if(!$nested) {
			// Globals
			CerberusContexts::merge(
				'global_',
				'(Global) ',
				array(
					'timestamp' => 'Current Date+Time',
				),
				array(
					'timestamp' => time(),
				),
				$labels,
				$values
			);

			// Current worker (Don't add to worker context)
			if($context != CerberusContexts::CONTEXT_WORKER) {
				$active_worker = CerberusApplication::getActiveWorker();
				$merge_token_labels = array();
				$merge_token_values = array();
				self::getContext(self::CONTEXT_WORKER, $active_worker, $merge_token_labels, $merge_token_values, '', true);

				CerberusContexts::merge(
					'worker_',
					'Current:Worker:',
					$merge_token_labels,
					$merge_token_values,
					$labels,
					$values
				);
			}

			// Plugin-provided tokens
			$token_extension_mfts = DevblocksPlatform::getExtensions('cerberusweb.snippet.token', false);
			foreach($token_extension_mfts as $mft) { /* @var $mft DevblocksExtensionManifest */
				@$token = $mft->params['token'];
				@$label = $mft->params['label'];
				@$contexts = $mft->params['contexts'][0];

				if(empty($token) || empty($label) || !is_array($contexts))
					continue;

				if(!isset($contexts[$context]))
					continue;

				if(null != ($ext = $mft->createInstance()) && $ext instanceof IContextToken) {
					/* @var $ext IContextToken */
					$value = $ext->getValue($context, $values);

					if(!empty($value)) {
						$labels['plugin_'.$token] = '(Plugin) '.$label;
						$values['plugin_'.$token] = $value;
					}
				}
			}
		}

		// Rename labels
		// [TODO] mb_*

		// [TODO] Phase out $labels

		if($skip_labels) {
			unset($values['_labels']);
			unset($values['_types']);

		} else {
			if(is_array($labels)) {
				foreach($labels as $idx => $label) {
					$labels[$idx] = trim(ucfirst(strtolower(strtr($label,':',' '))));
				}

				asort($labels);

				$values['_labels'] = $labels;
			}
		}

		// Pop the stack
		array_pop(self::$_stack);

		return null;
	}

	public static function scrubTokensWithRegexp(&$labels, &$values, $patterns=array()) {
		foreach($patterns as $pattern) {
			foreach(array_keys($labels) as $token) {
				if(preg_match($pattern, $token)) {
					unset($labels[$token]);
				}
			}
			foreach(array_keys($values) as $token) {
				if(false !== ($pos = strpos($token,'|')))
					$token = substr($token,0,$pos);

				if(preg_match($pattern, $token)) {
					unset($values[$token]);
				}
			}
		}

		return TRUE;
	}

	/**
	 *
	 * @param string $token_prefix
	 * @param array $label_replace
	 * @param array $src_labels
	 * @param array $dst_labels
	 * @param array $src_values
	 * @param array $dst_values
	 * @return void
	 */
	public static function merge($token_prefix, $label_prefix, $src_labels, $src_values, &$dst_labels, &$dst_values) {
		if(is_array($src_labels))
		foreach($src_labels as $token => $label) {
			$dst_labels[$token_prefix.$token] = $label_prefix.$label;
		}

		if(is_array($src_values))
		foreach($src_values as $token => $value) {
			if(in_array($token, array('_labels', '_types'))) {

				switch($token) {
					case '_labels':
						if(!isset($dst_values['_labels']))
							$dst_values['_labels'] = array();

						foreach($value as $key => $label) {
							$dst_values['_labels'][$token_prefix.$key] = $label_prefix.$label;
						}
						break;

					case '_types':
						if(!isset($dst_values['_types']))
							$dst_values['_types'] = array();

						foreach($value as $key => $type) {
							$dst_values['_types'][$token_prefix.$key] = $type;
						}
						break;
				}

			} else {
				$dst_values[$token_prefix.$token] = $value;
			}
		}

		return true;
	}

	public static function isSameObject($a, $b) {
		if(false == ($a = CerberusContexts::polymorphActor($a)))
			return false;

		if(false == ($b = CerberusContexts::polymorphActor($b)))
			return false;

		return ((get_class($a) == get_class($b)) && (intval($a->id) == intval($b->id)));
	}

	// Polymorph actor from context array
	public static function polymorphActor($actor) {
		if(is_array($actor)) {
			@list($actor_context, $actor_context_id) = $actor;

			switch($actor_context) {
				case CerberusContexts::CONTEXT_APPLICATION:
					$actor = new Model_Application();
					break;
				case CerberusContexts::CONTEXT_ROLE:
					$actor = DAO_WorkerRole::get($actor_context_id);
					break;
				case CerberusContexts::CONTEXT_GROUP:
					$actor = DAO_Group::get($actor_context_id);
					break;
				case CerberusContexts::CONTEXT_WORKER:
					$actor = DAO_Worker::get($actor_context_id);
					break;
				case CerberusContexts::CONTEXT_VIRTUAL_ATTENDANT:
					$actor = DAO_VirtualAttendant::get($actor_context_id);
					break;
			}
		}

		if(!is_object($actor))
			return false;

		$actor_classes = array(
			'Model_Application',
			'Model_WorkerRole',
			'Model_Group',
			'Model_Worker',
			'Model_VirtualAttendant',
		);

		if(!in_array(get_class($actor), $actor_classes))
			return false;

		return $actor;
	}

	public static function getAvailableOwners(Model_Worker $as_worker) {
		$roles = DAO_WorkerRole::getAll();
		$workers = DAO_Worker::getAll();
		$groups = DAO_Group::getAll();

		$owners = array(
			array(
				'label' => 'Application: Cerb',
				'context' => CerberusContexts::CONTEXT_APPLICATION,
				'context_id' => 0,
			),
		);

		if(is_array($groups))
		foreach($groups as $k => $group) { /* @var $group Model_Group */
			if($as_worker->is_superuser || $as_worker->isGroupManager($k)) {
				$owners[] = array(
					'label' => 'Group: ' . $group->name,
					'context' => CerberusContexts::CONTEXT_GROUP,
					'context_id' => $group->id,
				);
			}
		}

		if(is_array($roles))
		foreach($roles as $k => $role) { /* @var $role Model_WorkerRole */
			if($as_worker->is_superuser) {
				$owners[] = array(
					'label' => 'Role: ' . $role->name,
					'context' => CerberusContexts::CONTEXT_ROLE,
					'context_id' => $role->id,
				);
			}
		}

		if($as_worker->is_superuser)
		if(is_array($workers))
		foreach($workers as $worker) { /* @var $worker Model_Worker */
			if(!$worker->is_disabled)
				$owners[] = array(
					'label' => 'Worker: ' . $worker->getName(),
					'context' => CerberusContexts::CONTEXT_WORKER,
					'context_id' => $worker->id,
				);
		}

		// Sort
		DevblocksPlatform::sortObjects($owners, '[label]', true);

		return $owners;
	}

	public static function isReadableByActor($owner_context, $owner_context_id, $actor) {
		if(false == ($actor = CerberusContexts::polymorphActor($actor)))
			return false;

		if($actor instanceof Model_Application)
			return true;

		switch($owner_context) {
			// Everyone can see app-owned content
			case CerberusContexts::CONTEXT_APPLICATION:
				return true;
				break;

			// The role itself, or members of the role, can see it
			case CerberusContexts::CONTEXT_ROLE:
				switch(get_class($actor)) {
					case 'Model_WorkerRole': /* @var $actor Model_WorkerRole */
						return ($owner_context_id == $actor->id);
						break;

					case 'Model_Group': /* @var $actor Model_Group */
						break;

					case 'Model_Worker': /* @var $actor Model_Worker */
						return in_array($owner_context_id, array_keys($actor->getRoles()));
						break;

					case 'Model_VirtualAttendant': /* @var $actor Model_VirtualAttendant */
						return self::isReadableByActor($owner_context, $owner_context_id, array($actor->owner_context, $actor->owner_context_id));
						break;
				}
				break;

			case CerberusContexts::CONTEXT_GROUP:
				switch(get_class($actor)) {
					case 'Model_WorkerRole':
						break;

					case 'Model_Group':
						return ($owner_context_id == $actor->id);
						break;

					case 'Model_Worker':
						return in_array($owner_context_id, array_keys($actor->getMemberships()));
						break;

					case 'Model_VirtualAttendant':
						return self::isReadableByActor($owner_context, $owner_context_id, array($actor->owner_context, $actor->owner_context_id));
						break;
				}
				break;

			case CerberusContexts::CONTEXT_WORKER:
				switch(get_class($actor)) {
					case 'Model_WorkerRole':
						break;

					case 'Model_Group':
						break;

					case 'Model_Worker':
						return ($owner_context_id == $actor->id);
						break;

					case 'Model_VirtualAttendant':
						return self::isReadableByActor($owner_context, $owner_context_id, array($actor->owner_context, $actor->owner_context_id));
						break;
				}
				break;

			case CerberusContexts::CONTEXT_VIRTUAL_ATTENDANT:
				switch(get_class($actor)) {
					case 'Model_WorkerRole':
					case 'Model_Group':
					case 'Model_Worker':
						return self::isReadableByActor($va->owner_context, $va->owner_context_id, $actor);
						break;

					case 'Model_VirtualAttendant':
						return ($owner_context_id == $actor->id);
						break;
				}
				break;
		}

		return false;
	}

	public static function isWriteableByActor($owner_context, $owner_context_id, $actor) {
		// Polymorph actor from context array
		if(false == ($actor = CerberusContexts::polymorphActor($actor)))
			return false;

		if($actor instanceof Model_Application)
			return true;

		switch($owner_context) {
			case CerberusContexts::CONTEXT_APPLICATION:
				switch(get_class($actor)) {
					case 'Model_WorkerRole': /* @var $actor Model_WorkerRole */
						break;

					case 'Model_Group': /* @var $actor Model_Group */
						break;

					case 'Model_Worker': /* @var $actor Model_Worker */
						// [TODO]
						return $actor->is_superuser;
						break;

					case 'Model_VirtualAttendant': /* @var $actor Model_VirtualAttendant */
						return self::isWriteableByActor($owner_context, $owner_context_id, array($actor->owner_context, $actor->owner_context_id));
						break;
				}
				break;

			// The role itself, or members of the role, can see it
			case CerberusContexts::CONTEXT_ROLE:
				switch(get_class($actor)) {
					case 'Model_WorkerRole': /* @var $actor Model_WorkerRole */
						return ($owner_context_id == $actor->id);
						break;

					case 'Model_Group': /* @var $actor Model_Group */
						break;

					case 'Model_Worker': /* @var $actor Model_Worker */
						// [TODO]
						return $actor->is_superuser;
						break;

					case 'Model_VirtualAttendant': /* @var $actor Model_VirtualAttendant */
						return self::isWriteableByActor($owner_context, $owner_context_id, array($actor->owner_context, $actor->owner_context_id));
						break;
				}
				break;

			case CerberusContexts::CONTEXT_GROUP:
				switch(get_class($actor)) {
					case 'Model_WorkerRole':
						break;

					case 'Model_Group':
						return ($owner_context_id == $actor->id);
						break;

					case 'Model_Worker':
						// [TODO]
						return ($actor->is_superuser || $actor->isGroupManager($owner_context_id));
						break;

					case 'Model_VirtualAttendant':
						return self::isWriteableByActor($owner_context, $owner_context_id, array($actor->owner_context, $actor->owner_context_id));
						break;
				}
				break;

			case CerberusContexts::CONTEXT_WORKER:
				switch(get_class($actor)) {
					case 'Model_WorkerRole':
						break;

					case 'Model_Group':
						break;

					case 'Model_Worker':
						// [TODO]
						return ($actor->is_superuser || $owner_context_id == $actor->id);
						break;

					case 'Model_VirtualAttendant':
						return self::isWriteableByActor($owner_context, $owner_context_id, array($actor->owner_context, $actor->owner_context_id));
						break;
				}
				break;

			case CerberusContexts::CONTEXT_VIRTUAL_ATTENDANT:
				if(false == ($va = DAO_VirtualAttendant::get($owner_context_id)))
					return false;

				switch(get_class($actor)) {
					case 'Model_WorkerRole':
					case 'Model_Group':
					case 'Model_Worker':
						return self::isWriteableByActor($va->owner_context, $va->owner_context_id, $actor);
						break;

					case 'Model_VirtualAttendant':
						return ($owner_context_id == $actor->id);
						break;
				}
				break;
		}

		return false;
	}

	// [TODO] This could also cache for request until new links are set involving the source/target
	static public function getWatchers($context, $context_id, $as_contexts=false) {
		$links = DAO_ContextLink::getContextLinks($context, $context_id, CerberusContexts::CONTEXT_WORKER);

		if(empty($links) || !isset($links[$context_id]))
			return array();

		$watcher_ids = array_keys($links[$context_id]);

		$workers = array();

		// Does the caller want the watchers as context objects?
		if($as_contexts) {
			if(is_array($watcher_ids))
			foreach($watcher_ids as $watcher_id) {
				$null_labels = array();
				$watcher_values = array();

				CerberusContexts::getContext(CerberusContexts::CONTEXT_WORKER, $watcher_id, $null_labels, $watcher_values, null, true);

				$workers[$watcher_id] = new DevblocksDictionaryDelegate($watcher_values);
			}

		// Or as Model_* objects?
		} else {
			if(is_array($watcher_ids))
			foreach($watcher_ids as $watcher_id)
				$workers[$watcher_id] = DAO_Worker::get($watcher_id);
		}

		return $workers;
	}

	// [TODO] Are these the only methods that set watcher links?
	static public function addWatchers($context, $context_id, $worker_ids) {
		$workers = DAO_Worker::getAll();

		if(!is_array($worker_ids))
			$worker_ids = array($worker_ids);

		foreach($worker_ids as $worker_id) {
			if(null != ($worker = @$workers[$worker_id]) && $worker instanceof Model_Worker && !$worker->is_disabled) {
				DAO_ContextLink::setLink($context, $context_id, CerberusContexts::CONTEXT_WORKER, $worker_id);
			}
		}
	}

	// [TODO] Are these the only methods that set watcher links?
	static public function removeWatchers($context, $context_id, $worker_ids) {
		if(!is_array($worker_ids))
			$worker_ids = array($worker_ids);

		foreach($worker_ids as $worker_id)
			DAO_ContextLink::deleteLink($context, $context_id, CerberusContexts::CONTEXT_WORKER, $worker_id);
	}

	static public function formatActivityLogEntry($entry, $format=null, $scrub_tokens=array(), $personalize=false) {
		$tpl_builder = DevblocksPlatform::getTemplateBuilder();
		$url_writer = DevblocksPlatform::getUrlService();
		$translate = DevblocksPlatform::getTranslationService();

		// Load the translated version of the message

		$entry['message'] = $translate->_($entry['message']);

		// Scrub desired tokens

		if(is_array($scrub_tokens) && !empty($scrub_tokens)) {
			foreach($scrub_tokens as $token) {
				// Scrub tokens and only preserve trailing whitespace
				$entry['message'] = preg_replace('#\s*\{\{'.$token.'\}\}(\s*)#', '\1', $entry['message']);
			}
		}
		
		// Variables

		$vars = $entry['variables'];
		
		// Personalize variables
		if($personalize && is_array($vars)) {
			if(isset($vars['actor'])) {
				$active_worker = CerberusApplication::getActiveWorker();
				$actor_name = $vars['actor'];
				$actor_self_target = 'themselves';
				
				// Replace actor with 'You'
				if($active_worker) {
					if($vars['actor'] == $active_worker->getName()) {
						$actor_name = 'You';
						$actor_self_target = 'yourself';
					}
				}
				
				// Handle the actor doing things to 'themselves'
				foreach($vars as $k => $v) {
					if($k == 'actor')
						continue;
					
					if($v == $vars['actor'])
						$vars[$k] = $actor_self_target;
					elseif($active_worker && $v == $active_worker->getName())
						$vars[$k] = 'you';
				}
				
				$vars['actor'] = $actor_name;
			}
			
		}

		switch($format) {
			case 'html':
				// HTML formatting and incorporating URLs
				if(is_array($vars))
				foreach($vars as $k => $v) {
					$vars[$k] = DevblocksPlatform::strEscapeHtml($v);
				}

				if(isset($entry['urls']))
				foreach($entry['urls'] as $token => $url) {
					if(0 == strcasecmp('ctx://',substr($url,0,6))) {
						$url = self::parseContextUrl($url);
					} elseif(0 != strcasecmp('http',substr($url,0,4))) {
						$url = $url_writer->writeNoProxy($url, true);
					}

					$vars[$token] = '<a href="'.$url.'" style="font-weight:bold;">'.$vars[$token].'</a>';
				}
				break;

			case 'markdown':
				if(isset($entry['urls']))
				foreach($entry['urls'] as $token => $url) {
					if(0 == strcasecmp('ctx://',substr($url,0,6))) {
						$url = self::parseContextUrl($url);
					} elseif(0 != strcasecmp('http',substr($url,0,4))) {
						$url = $url_writer->writeNoProxy($url, true);
					}

					$vars[$token] = '['.$vars[$token].']('.$url.')';
				}
				break;

			case 'email':
				@$url = reset($entry['urls']);

				if(empty($url))
					break;

				if(0 == strcasecmp('ctx://',substr($url,0,6))) {
					$url = self::parseContextUrl($url);
				} elseif(0 != strcasecmp('http',substr($url,0,4))) {
					$url = $url_writer->writeNoProxy($url, true);
				}

				$entry['message'] .= ' <' . $url . '>';
				break;

			default:
				break;
		}

		if(!is_array($vars))
			$vars = array();

		return $tpl_builder->build($entry['message'], $vars);
	}

	static function parseContextUrl($url) {
		if(0 != strcasecmp('ctx://',substr($url,0,6))) {
			return false;
		}

		$context_parts = explode('/', substr($url,6));
		$context_pair = explode(':', $context_parts[0], 2);

		if(count($context_pair) != 2)
			return false;

		$context = $context_pair[0];
		$context_id = $context_pair[1];

		if(null == ($context_ext = Extension_DevblocksContext::get($context)))
			return null;

		if($context_ext instanceof IDevblocksContextProfile) {
			$url = $context_ext->profileGetUrl($context_id);

		} else {
			$meta = $context_ext->getMeta($context_id);

			if(is_array($meta) && isset($meta['permalink']))
				$url = $meta['permalink'];
		}

		return $url;
	}

	static public function pushActivityDefaultActor($context=null, $context_id=null) {
		if(empty($context) || empty($context_id)) {
			self::$_default_actor_context = null;
			self::$_default_actor_context_id = null;
		} else {
			self::$_default_actor_context = $context;
			self::$_default_actor_context_id = $context_id;
			self::$_default_actor_stack[] = array($context, $context_id);
		}
	}

	static public function popActivityDefaultActor() {
		array_pop(self::$_default_actor_stack);

		if(empty(self::$_default_actor_stack)) {
			$context = null;
			$context_id = null;

		} else {
			$context_pair = end(self::$_default_actor_stack);

			$context = $context_pair['context'];
			$context_id = $context_pair['context_id'];
		}
	}
	
	static public function getCurrentActor($actor_context=null, $actor_context_id=null) {
		
		// Forced actor
		if(!empty($actor_context)) {
			if(null != ($ctx = DevblocksPlatform::getExtension($actor_context, true))
				&& $ctx instanceof Extension_DevblocksContext) {
				$meta = $ctx->getMeta($actor_context_id);
				$actor_name = $meta['name'];
				$actor_url = sprintf("ctx://%s:%d", $actor_context, $actor_context_id);
				
			} else {
				$actor_context = null;
				$actor_context_id = null;
			}
		}

		// Auto-detect the actor
		if(empty($actor_context)) {
			$actor_name = null;
			$actor_context = null;
			$actor_context_id = 0;
			$actor_url = null;

			// See if we're inside of an attendant's running decision tree

			$stack = EventListener_Triggers::getTriggerStack();

			if(EventListener_Triggers::getDepth() > 0
				&& null != ($trigger_id = end($stack))
				&& !empty($trigger_id)
				&& null != ($trigger = DAO_TriggerEvent::get($trigger_id))
				&& false != ($trigger_va = $trigger->getVirtualAttendant())
			) {
				/* @var $trigger Model_TriggerEvent */

				$actor_name = sprintf("%s [%s]", $trigger_va->name, $trigger->title);
				$actor_context = CerberusContexts::CONTEXT_VIRTUAL_ATTENDANT;
				$actor_context_id = $trigger_va->id;
				$actor_url = sprintf("ctx://%s:%d", CerberusContexts::CONTEXT_VIRTUAL_ATTENDANT, $trigger_va->id);

			// Otherwise see if we have an active session
			} else {
				// If we have a default, use it instead of the current session
				if(empty($actor_context) && !empty(self::$_default_actor_context)) {
					$actor_context = self::$_default_actor_context;
					$actor_context_id = self::$_default_actor_context_id;

					if(null != ($ctx = DevblocksPlatform::getExtension($actor_context, true))
						&& $ctx instanceof Extension_DevblocksContext) {
						$meta = $ctx->getMeta($actor_context_id);
						$actor_name = $meta['name'];
						$actor_url = sprintf("ctx://%s:%d", $actor_context, $actor_context_id);
					}
				}

				// Try using current session
				if(empty($actor_context) && null != ($active_worker = CerberusApplication::getActiveWorker())) {
					$actor_name = $active_worker->getName();
					$actor_context = CerberusContexts::CONTEXT_WORKER;
					$actor_context_id = $active_worker->id;
					$actor_url = sprintf("ctx://%s:%d", $actor_context, $actor_context_id);
				}

			}
		}

		if(empty($actor_context)) {
			$actor_context = CerberusContexts::CONTEXT_APPLICATION;
			$actor_context_id = 0;
			$actor_name = 'Cerb';
			$actor_url = null;
		}
		
		return array(
			'context' => $actor_context,
			'context_id' => $actor_context_id,
			'name' => $actor_name,
			'url' => $actor_url,
		);
	}

	static public function logActivity($activity_point, $target_context, $target_context_id, &$entry_array, $actor_context=null, $actor_context_id=null, $also_notify_worker_ids=array(), $also_notify_ignore_self=false) {
		// Target meta
		if(!isset($target_meta)) {
			if(null != ($target_ctx = DevblocksPlatform::getExtension($target_context, true))
				&& $target_ctx instanceof Extension_DevblocksContext) {
					$target_meta = $target_ctx->getMeta($target_context_id);
			}
		}

		$actor = self::getCurrentActor($actor_context, $actor_context_id);

		$entry_array['variables']['actor'] = $actor['name'];

		if(isset($actor['url']) && !empty($actor['url']))
			$entry_array['urls']['actor'] = $actor['url'];

		// Activity Log
		$activity_entry_id = DAO_ContextActivityLog::create(array(
			DAO_ContextActivityLog::ACTIVITY_POINT => $activity_point,
			DAO_ContextActivityLog::CREATED => time(),
			DAO_ContextActivityLog::ACTOR_CONTEXT => $actor['context'],
			DAO_ContextActivityLog::ACTOR_CONTEXT_ID => $actor['context_id'],
			DAO_ContextActivityLog::TARGET_CONTEXT => $target_context,
			DAO_ContextActivityLog::TARGET_CONTEXT_ID => $target_context_id,
			DAO_ContextActivityLog::ENTRY_JSON => json_encode($entry_array),
		));

		// Tell target watchers about the activity

		$do_notifications = true;

		// Only fire notifications if supported by the activity options (!no_notifications)

		$activity_points = DevblocksPlatform::getActivityPointRegistry();

		if(isset($activity_points[$activity_point])) {
			$activity_mft = $activity_points[$activity_point];
			if(
				isset($activity_mft['params'])
				&& isset($activity_mft['params']['options'])
				&& in_array('no_notifications', DevblocksPlatform::parseCsvString($activity_mft['params']['options']))
				)
				$do_notifications = false;
		}

		// Send notifications

		if($do_notifications) {
			$watchers = array();

			// Merge in the record owner if defined
			if(isset($target_meta) && isset($target_meta['owner_id']) && !empty($target_meta['owner_id'])) {
				$watchers = array_merge(
					$watchers,
					array($target_meta['owner_id'])
				);
			}

			// Merge in watchers of the actor (if not a worker)
			if(CerberusContexts::CONTEXT_WORKER != $actor['context']) {
				$watchers = array_merge(
					$watchers,
					array_keys(CerberusContexts::getWatchers($actor['context'], $actor['context_id']))
				);
			}

			// And watchers of the target (if not a worker)
			if(CerberusContexts::CONTEXT_WORKER != $target_context) {
				$watchers = array_merge(
					$watchers,
					array_keys(CerberusContexts::getWatchers($target_context, $target_context_id))
				);
			}

			// Include the 'also notify' list
			if(!is_array($also_notify_worker_ids))
				$also_notify_worker_ids = array();
			
			// Merge watchers and the notification list
			$watchers = array_merge(
				$watchers,
				$also_notify_worker_ids
			);

			// And include any worker-based custom fields with the 'send watcher notifications' option
			$target_custom_fields = DAO_CustomField::getByContext($target_context, true);

			if(is_array($target_custom_fields))
			foreach($target_custom_fields as $target_custom_field_id => $target_custom_field) {
				if($target_custom_field->type != Model_CustomField::TYPE_WORKER)
					continue;

				if(!isset($target_custom_field->params['send_notifications']) || empty($target_custom_field->params['send_notifications']))
					continue;

				$values = DAO_CustomFieldValue::getValuesByContextIds($target_context, $target_context_id);

				if(isset($values[$target_context_id]) && isset($values[$target_context_id][$target_custom_field_id]))
					$watchers = array_merge(
						$watchers,
						array($values[$target_context_id][$target_custom_field_id])
					);
			}

			// Remove dupe watchers

			$watcher_ids = array_unique($watchers);

			// Fire off notifications

			if(is_array($watcher_ids)) {
				$workers = DAO_Worker::getAllActive();
				
				foreach($watcher_ids as $watcher_id) {
					// Skip inactive workers
					if(!isset($workers[$watcher_id]))
						continue;
					
					// If not inside a VA
					if(0 == EventListener_Triggers::getDepth()) {
						// Skip a watcher if they are the actor
						if($actor['context'] == CerberusContexts::CONTEXT_WORKER
							&& $actor['context_id'] == $watcher_id) {
								// If they explicitly added themselves to the notify, allow it.
								// Otherwise, don't tell them what they just did.
								if($also_notify_ignore_self || !in_array($watcher_id, $also_notify_worker_ids))
									continue;
						}
					}

					// Does the worker want this kind of notification?
					$dont_notify_on_activities = WorkerPrefs::getDontNotifyOnActivities($watcher_id);
					if(in_array($activity_point, $dont_notify_on_activities))
						continue;

					// If yes, send it
					DAO_Notification::create(array(
						DAO_Notification::CONTEXT => $target_context,
						DAO_Notification::CONTEXT_ID => $target_context_id,
						DAO_Notification::CREATED_DATE => time(),
						DAO_Notification::IS_READ => 0,
						DAO_Notification::WORKER_ID => $watcher_id,
						DAO_Notification::ACTIVITY_POINT => $activity_point,
						DAO_Notification::ENTRY_JSON => json_encode($entry_array),
					));
				}
			}
		} // end if($do_notifications)

		return $activity_entry_id;
	}

	static function getModels($context, array $ids) {
		$ids = DevblocksPlatform::importVar($ids, 'array:integer', array());

		$models = array();

		if(empty($ids))
			return $models;

		if(false == ($context_ext = Extension_DevblocksContext::get($context)))
			return $models;

		if(false == ($dao_class = $context_ext->getDaoClass()))
			return $models;

		if(method_exists($dao_class, 'getIds')) {
			$models = $dao_class::getIds($ids);

		} elseif(method_exists($dao_class, 'getWhere')) {
			$models = $dao_class::getWhere(sprintf("id IN (%s)", implode(',', $ids)), null);
		}

		return $models;
	}

	static private $_context_checkpoints = array();

	static function checkpointChanges($context, $ids) {
		if(php_sapi_name() == 'cli')
			return;

		$ids = DevblocksPlatform::importVar($ids, 'array:integer');
		$actor = CerberusContexts::getCurrentActor();
		
		if(!isset(self::$_context_checkpoints[$context]))
			self::$_context_checkpoints[$context] = array();

		// Cache full model objects the first time we encounter an ID (before persisting any changes)
		// [TODO] If events could tell us what fields we're watching, we could lazy load ahead of time (custom_, deeply_nested_field_key)

		$load_ids = array_diff($ids, array_keys(self::$_context_checkpoints[$context]));

		if(!empty($load_ids)) {
			$models = CerberusContexts::getModels($context, $load_ids);
			$values = DAO_CustomFieldValue::getValuesByContextIds($context, $load_ids);
			
			foreach($models as $model_id => $model) {
				$model->custom_fields = @$values[$model_id] ?: array();
				$model->_actor = $actor;
				
				self::$_context_checkpoints[$context][$model_id] =
					json_decode(json_encode($model), true);
			}
		}
	}

	static function getCheckpoints($context, $ids) {
		$models = array();

		if(isset(self::$_context_checkpoints[$context]))
		foreach($ids as $id) {
			if(isset(self::$_context_checkpoints[$context][$id]))
				$models[$id] = self::$_context_checkpoints[$context][$id];
		}

		return $models;
	}

	static function shutdown() {
		if(empty(self::$_context_checkpoints))
			return;

		foreach(self::$_context_checkpoints as $context => &$old_models) {

			// Do this in batches of 100 in order to save memory
			$ids = array_keys($old_models);
			
			foreach(array_chunk($ids, 100) as $context_ids) {
				$new_models = CerberusContexts::getModels($context, $context_ids);

				$values = DAO_CustomFieldValue::getValuesByContextIds($context, $context_ids);

				foreach($new_models as $context_id => $new_model) {
					$old_model = $old_models[$context_id];
					$new_model->custom_fields = @$values[$context_id] ?: array();
					$actor = null;
					
					if(isset($old_model['_actor'])) {
						$actor = $old_model['_actor'];
						unset($old_model['_actor']);
					}

					Event_RecordChanged::trigger($context, $new_model, $old_model, $actor);
				}
			}
		}

	}
};

class Model_Application {
	public $id = 0;
	public $name = 'Cerb';
}

class Context_Application extends Extension_DevblocksContext {
	function authorize($context_id, Model_Worker $worker) {
		// Security
		try {
			if(empty($worker))
				throw new Exception();

			if($worker->is_superuser)
				return TRUE;

		} catch (Exception $e) {
			// Fail
		}

		return FALSE;
	}

	function getRandom() {
		return 0;
	}

	function getMeta($context_id) {
		$url_writer = DevblocksPlatform::getUrlService();

		return array(
			'id' => 0,
			'name' => 'Cerb',
			'permalink' => null, //$url_writer->writeNoProxy('', true),
			'updated' => APP_BUILD,
		);
	}

	// [TODO] Interface
	function getDefaultProperties() {
		return array(
			'name',
		);
	}

	function getContext($object, &$token_labels, &$token_values, $prefix=null) {
		if(is_null($prefix))
			$prefix = 'Application:';

		$translate = DevblocksPlatform::getTranslationService();
		$fields = DAO_CustomField::getByContext(CerberusContexts::CONTEXT_APPLICATION);

		// Polymorph
		if(is_numeric($object)) {
			$object = new Model_Application();
			$object->name = 'Application';

 		} elseif($object instanceof Model_Application) {
			// It's what we want already.

 		} elseif(is_array($object)) {
 			$object = Cerb_ORMHelper::recastArrayToModel($object, 'Model_Application');

		} else {
			$object = null;
		}

		// Token labels
		$token_labels = array(
			'_label' => $prefix,
			'name' => $prefix.$translate->_('common.name'),
		);

		// Token types
		$token_types = array(
			'_label' => 'context_url',
			'name' => Model_CustomField::TYPE_SINGLE_LINE,
		);

		// Custom field/fieldset token labels
		if(false !== ($custom_field_labels = $this->_getTokenLabelsFromCustomFields($fields, $prefix)) && is_array($custom_field_labels))
			$token_labels = array_merge($token_labels, $custom_field_labels);

		// Token values
		$token_values = array();

		$token_values['_context'] = CerberusContexts::CONTEXT_APPLICATION;
		$token_values['_types'] = $token_types;

		// Worker token values
		if(null != $object) {
			$token_values['_loaded'] = true;
			$token_values['_label'] = 'Application';
			$token_values['name'] = 'Application';
		}

		return true;
	}

	function lazyLoadContextValues($token, $dictionary) {
		if(!isset($dictionary['id']))
			return;

		$context = CerberusContexts::CONTEXT_APPLICATION;
		$context_id = $dictionary['id'];

		@$is_loaded = $dictionary['_loaded'];
		$values = array();

		if(!$is_loaded) {
			$labels = array();
			CerberusContexts::getContext($context, $context_id, $labels, $values, null, true);
		}

		switch($token) {
			default:
				if(substr($token,0,7) == 'custom_') {
					$fields = $this->_lazyLoadCustomFields($token, $context, $context_id);
					$values = array_merge($values, $fields);
				}
				break;
		}

		return $values;
	}

	function getChooserView($view_id=null) {
		return null;
	}

	function getView($context=null, $context_id=null, $options=array(), $view_id=null) {
		return null;
	}
};

class CerberusLicense {
	private static $instance = null;
	private $data = array();

	/**
	 * Commercial open source is the most constructive business model for our project.
	 * We trust you.
	 */
	private function __construct() {
																																																																																																																																			try{$l=(null!=(@$data=json_decode(DevblocksPlatform::getPluginSetting('cerberusweb.core',CerberusSettings::LICENSE,''),true)))?$data:array();self::validate(base64_decode(@$l[base64_decode('ZGF0YQ==')]),@$l['company'],@$l['email'])?@$this->data=$l:@$this->data=array();}catch(Exception $e){@$this->data=array();}return;
		/*
		 * We've spent many years working on this software.
		 * Please be honest!
		 * http://www.cerberusweb.com/buy
		 */
		$this->data = (null != ($data = json_decode(DevblocksPlatform::getPluginSetting('devblocks.core','license_info',array()),true)))?$data:array();
	}

																																																																																																																																			public function __get($property) {switch($property){case 'w':return(max(1,@intval($this->seats)));break;default:return(isset($this->data[$property]))?$this->data[$property]:null;break;}}
	/**
	 * @return CerberusLicense
	 */
	public static function getInstance() {
		if(is_null(self::$instance)) {
			self::$instance = new CerberusLicense();
		}
		return self::$instance;
	}

	/*
	 * Please purchase a legitimate license and help support the project.
	 * http://www.cerberusweb.com/buy
	 */
	public static function validate($key, $company, $email) {
																																																																																																																																			try{foreach(array('L0tleTogKC4qKS8='=>'s','L0NyZWF0ZWQ6ICguKikv'=>'c','L1VwZGF0ZWQ6ICguKikv'=>'u','L1VwZ3JhZGVzOiAoLiopLw=='=>'e','L1NlYXRzOiAoLiopLw=='=>'w') as $k=>$v)@preg_match(base64_decode($k),$key,$matches)?@$$v=trim($matches[1]):null;$r=array();@$w=intval($w);@$cp=base64_decode('Y29tcGFueQ==');@$em=base64_decode('ZW1haWw=');@$cd=preg_replace('/[^A-Z0-9]/','',$s);@$l=explode('-',$e);@$e=gmmktime(0,0,0,$l[1],$l[2],$l[0]);@$l=explode('-',$c);@$c=gmmktime(0,0,0,$l[1],$l[2],$l[0]);@$l=explode('-',$u);@$u=gmmktime(0,0,0,$l[1],$l[2],$l[0]);@$h=str_split(strtoupper(sha1(sha1('cerb5').sha1($$cp).sha1($$em).sha1(intval($w)).sha1(gmdate('Y-m-d',$c)).sha1(gmdate('Y-m-d',$e)))),1);if(0==@strcasecmp(sprintf("%02X",strlen($$cp)+intval($w)),substr($cd,3,2))&&@intval(hexdec(substr($cd,5,1))==@intval(bindec(sprintf("%d%d%d%d",(182<=gmdate('z',$e))?1:0,(5==gmdate('w',$e))?1:0,('th'==gmdate('S',$e))?1:0,(1==gmdate('w',$e))?1:0))))&&0==@strcasecmp($h[hexdec(substr($cd,1,2))-@hexdec(substr($cd,0,1))],substr($cd,0,1)))@$r=array(base64_decode('a2V5')=>$s,base64_decode('Y3JlYXRlZA==')=>$c,base64_decode('dXBkYXRlZA==')=>$u,base64_decode('dXBncmFkZXM=')=>$e,@$cp=>$$cp,@$em=>$$em,base64_decode('c2VhdHM=')=>intval($w),base64_decode('ZGF0YQ==')=>base64_encode($key));return $r;}catch(Exception $e){return array();}
		/*
		 * Simple, huh?
		 */
		$lines = explode("\n", $key);

		/*
		 * Remember that our cache can return stale data here. Be sure to
		 * clear caches.  The config area does already.
		 */
		return (!empty($key) && !empty($lines))
			? array(
				'company' => $company,
				'email' => $email,
				'key' => (list($k,$v)=explode(":",$lines[1]))?trim($v):null,
				'created' => (list($k,$v)=explode(":",$lines[2]))?trim($v):null,
				'updated' => (list($k,$v)=explode(":",$lines[3]))?trim($v):null,
				'upgrades' => (list($k,$v)=explode(":",$lines[4]))?trim($v):null,
				'seats' => (list($k,$v)=explode(":",$lines[5]))?trim($v):null
			)
			: array();
	}

	public static function getReleases() {
		/*																																																																																																																														*/return array('5.0.0'=>1271894400,'5.1.0'=>1281830400,'5.2.0'=>1288569600,'5.3.0'=>1295049600,'5.4.0'=>1303862400,'5.5.0'=>1312416000,'5.6.0'=>1317686400,'5.7.0'=>1326067200,'6.0.0'=>1338163200,'6.1.0'=>1346025600,'6.2.0'=>1353888000,'6.3.0'=>1364169600,'6.4.0'=>1370217600,'6.5.0'=>1379289600,'6.6.0'=>1391126400,'6.7.0'=>1398124800,'6.8.0'=>1410739200,'6.9.0'=>1422230400,'7.0.0'=>1432598400,'7.1.0'=>1448928000);/*
		 * Major versions by release date in GMT
		 */
		return array(
			'5.0.0' => gmmktime(0,0,0,4,22,2010),
			'5.1.0' => gmmktime(0,0,0,8,15,2010),
			'5.2.0' => gmmktime(0,0,0,11,1,2010),
			'5.3.0' => gmmktime(0,0,0,1,15,2011),
			'5.4.0' => gmmktime(0,0,0,4,27,2011),
			'5.5.0' => gmmktime(0,0,0,8,4,2011),
			'5.6.0' => gmmktime(0,0,0,10,4,2011),
			'5.7.0' => gmmktime(0,0,0,1,9,2012),
			'6.0.0' => gmmktime(0,0,0,5,28,2012),
			'6.1.0' => gmmktime(0,0,0,8,27,2012),
			'6.2.0' => gmmktime(0,0,0,11,26,2012),
			'6.3.0' => gmmktime(0,0,0,3,25,2013),
			'6.4.0' => gmmktime(0,0,0,6,3,2013),
			'6.5.0' => gmmktime(0,0,0,9,16,2013),
			'6.6.0' => gmmktime(0,0,0,1,31,2014),
			'6.7.0' => gmmktime(0,0,0,4,22,2014),
			'6.8.0' => gmmktime(0,0,0,9,15,2014),
			'6.9.0' => gmmktime(0,0,0,1,26,2015),
			'7.0.0' => gmmktime(0,0,0,5,26,2015),
			'7.1.0' => gmmktime(0,0,0,12,1,2015),
		);
	}

	public static function getReleaseDate($version) {
		$latest_licensed = 0;
		$version_parts = explode("-",$version,2);
		$version = array_shift($version_parts);
		foreach(self::getReleases() as $release => $release_date) {
			if(version_compare($release, $version) <= 0)
				$latest_licensed = $release_date;
		}
		return $latest_licensed;
	}
};

class CerberusSettings {
	const HELPDESK_TITLE = 'helpdesk_title';
	const HELPDESK_FAVICON_URL = 'helpdesk_favicon_url';
	const HELPDESK_LOGO_URL = 'helpdesk_logo_url';
	const ATTACHMENTS_ENABLED = 'attachments_enabled';
	const ATTACHMENTS_MAX_SIZE = 'attachments_max_size';
	const PARSER_AUTO_REQ = 'parser_autoreq';
	const PARSER_AUTO_REQ_EXCLUDE = 'parser_autoreq_exclude';
	const TICKET_MASK_FORMAT = 'ticket_mask_format';
	const AUTHORIZED_IPS = 'authorized_ips';
	const LICENSE = 'license_json';
	const RELAY_DISABLE = 'relay_disable';
	const RELAY_DISABLE_AUTH = 'relay_disable_auth';
	const RELAY_SPOOF_FROM = 'relay_spoof_from';
	const SESSION_LIFESPAN = 'session_lifespan';
	const TIME_FORMAT = 'time_format';
	const AVATAR_DEFAULT_STYLE_CONTACT = 'avatar_default_style_contact';
	const AVATAR_DEFAULT_STYLE_WORKER = 'avatar_default_style_worker';
	const HTML_NO_STRIP_MICROSOFT = 'html_no_strip_microsoft';
};

class CerberusSettingsDefaults {
	const HELPDESK_TITLE = 'Cerb - a fast and flexible web-based platform for enterprise collaboration, productivity, and automation.';
	const ATTACHMENTS_ENABLED = 1;
	const ATTACHMENTS_MAX_SIZE = 10;
	const PARSER_AUTO_REQ = 0;
	const PARSER_AUTO_REQ_EXCLUDE = '';
	const TICKET_MASK_FORMAT = 'LLL-NNNNN-NNN';
	const AUTHORIZED_IPS = "127.0.0.1\n::1\n";
	const RELAY_DISABLE = 0;
	const RELAY_DISABLE_AUTH = 0;
	const RELAY_SPOOF_FROM = 0;
	const SESSION_LIFESPAN = 0;
	const TIME_FORMAT = 'D, d M Y h:i a';
	const AVATAR_DEFAULT_STYLE_CONTACT = 'monograms';
	const AVATAR_DEFAULT_STYLE_WORKER = 'monograms';
	const HTML_NO_STRIP_MICROSOFT = 0;
};

// [TODO] Implement our own session handler w/o PHP 'session'
class Cerb_DevblocksSessionHandler implements IDevblocksHandler_Session {
	static $_data = null;

	static function open($save_path, $session_name) {
		return true;
	}

	static function close() {
		return true;
	}

	static function isReady() {
		$tables = DevblocksPlatform::getDatabaseTables();

		if(!isset($tables['devblocks_session']))
			return false;

		return true;
	}

	static function read($id) {
		$db = DevblocksPlatform::getDatabaseService();

		if(!self::isReady())
			return false;

		// [TODO] Don't set a cookie until logging in (redo session code)
		// [TODO] Security considerations in book (don't allow non-SSL connections)
		// [TODO] Allow Cerb to configure sticky IP sessions (or by subnet) as setting
		// [TODO] Allow Cerb to enable user-agent comparisons as setting
		// [TODO] Limit the IPs a worker can log in from (per-worker?)

		if(null != ($session = $db->GetRowSlave(sprintf("SELECT * FROM devblocks_session WHERE session_key = %s", $db->qstr($id))))) {
			$maxlifetime = DevblocksPlatform::getPluginSetting('cerberusweb.core', CerberusSettings::SESSION_LIFESPAN, CerberusSettingsDefaults::SESSION_LIFESPAN);
			$is_ajax = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest');

			// Refresh the session cookie (move expiration forward) after 5 minutes have elapsed
			if(isset($session['refreshed_at']) && $maxlifetime && !$is_ajax && (time() - $session['refreshed_at'] >= 300)) {
				$url_writer = DevblocksPlatform::getUrlService();

				setcookie('Devblocks', $id, time()+$maxlifetime, '/', NULL, $url_writer->isSSL(), true);

				$db->ExecuteMaster(sprintf("UPDATE devblocks_session SET refreshed_at=%d WHERE session_key = %s",
					time(),
					$db->qstr($id)
				));
			}

			self::$_data = $session['session_data'];
			return self::$_data;
		}

		return false;
	}

	static function write($id, $session_data) {
		// Nothing changed!
		if(self::$_data==$session_data) {
			return true;
		}

		$active_worker = CerberusApplication::getActiveWorker();
		$user_ip = $_SERVER['REMOTE_ADDR'];
		$user_agent = (isset($_SERVER['HTTP_USER_AGENT'])) ? $_SERVER['HTTP_USER_AGENT'] : '';

		$db = DevblocksPlatform::getDatabaseService();

		if(!self::isReady())
			return false;

		// Update
		$sql = sprintf("UPDATE devblocks_session SET updated=%d, session_data=%s, user_id=%d, user_ip=%s, user_agent=%s WHERE session_key=%s",
			time(),
			$db->qstr($session_data),
			!is_null($active_worker) ? $active_worker->id : 0,
			$db->qstr($user_ip),
			$db->qstr($user_agent),
			$db->qstr($id)
		);
		$result = $db->ExecuteMaster($sql);

		if(0==$db->Affected_Rows()) {
			// Insert
			$sql = sprintf("INSERT INTO devblocks_session (session_key, created, updated, refreshed_at, user_id, user_ip, user_agent, session_data) ".
				"VALUES (%s, %d, %d, %d, %d, %s, %s, %s)",
				$db->qstr($id),
				time(),
				time(),
				time(),
				!is_null($active_worker) ? $active_worker->id : 0,
				$db->qstr($user_ip),
				$db->qstr($user_agent),
				$db->qstr($session_data)
			);
			$db->ExecuteMaster($sql);
		}

		return true;
	}

	static function destroy($id) {
		$db = DevblocksPlatform::getDatabaseService();

		if(!self::isReady())
			return false;

		$db->ExecuteMaster(sprintf("DELETE FROM devblocks_session WHERE session_key = %s", $db->qstr($id)));
		return true;
	}

	static function gc($maxlifetime) {
		if(!self::isReady())
			return false;

		// We ignore caller's $maxlifetime (session.gc_maxlifetime) on purpose.
		// Look up Cerb's session max lifetime
		$maxlifetime = DevblocksPlatform::getPluginSetting('cerberusweb.core', CerberusSettings::SESSION_LIFESPAN, CerberusSettingsDefaults::SESSION_LIFESPAN);

		if(empty($maxlifetime))
			$maxlifetime = 86400;

		$db = DevblocksPlatform::getDatabaseService();
		$db->ExecuteMaster(sprintf("DELETE FROM devblocks_session WHERE updated + %d < %d", $maxlifetime, time()));
		return true;
	}

	static function getAll() {
		$db = DevblocksPlatform::getDatabaseService();

		if(!self::isReady())
			return false;

		return $db->GetArraySlave("SELECT session_key, created, updated, user_id, user_ip, user_agent, session_data FROM devblocks_session");
	}

	static function destroyAll() {
		$db = DevblocksPlatform::getDatabaseService();

		if(!self::isReady())
			return false;

		$db->ExecuteMaster("DELETE FROM devblocks_session");
	}

	static function destroyByWorkerIds($ids) {
		if(!self::isReady())
			return false;

		if(!is_array($ids)) $ids = array($ids);

		$ids_list = implode(',', $ids);

		if(empty($ids_list))
			return;

		$db = DevblocksPlatform::getDatabaseService();
		$db->ExecuteMaster(sprintf("DELETE FROM devblocks_session WHERE user_id IN (%s)", $ids_list));
	}
};

class Cerb_DevblocksExtensionDelegate implements DevblocksExtensionDelegate {
	static $_worker = null;
	static $_plugin_cache = array();

	static function shouldLoadExtension(DevblocksExtensionManifest $extension_manifest) {
		// Always allow core
		if("devblocks.core" == $extension_manifest->plugin_id)
			return true;
		if("cerberusweb.core" == $extension_manifest->plugin_id)
			return true;

		// [TODO] This should limit to just things we can run with no session
		// Community Tools, Cron/Update.  They are still limited by their own
		// isVisible() otherwise.
		if(null == self::$_worker) {
			if(null == (self::$_worker = CerberusApplication::getActiveWorker()))
				return true;
		}

		// Use plugin cache if exists
		if(isset(self::$_plugin_cache[$extension_manifest->plugin_id]))
			return self::$_plugin_cache[$extension_manifest->plugin_id];

		// ... Otherwise, check it
		$has_priv = self::$_worker->hasPriv('plugin.'.$extension_manifest->plugin_id);

		// ... Then cache it
		self::$_plugin_cache[$extension_manifest->plugin_id] = $has_priv;

		return $has_priv;
	}
};

class CerberusVisit extends DevblocksVisit {
	private $worker_id;
	private $imposter_id;

	const KEY_VIEW_LAST_ACTION = 'view_last_action';
	const KEY_MY_WORKSPACE = 'view_my_workspace';
	const KEY_WORKFLOW_FILTER = 'workflow_filter';

	public function __construct() {
		$this->worker_id = null;
		$this->imposter_id = null;
	}

	/**
	 * @return Model_Worker
	 */
	public function getWorker() {
		if(empty($this->worker_id))
			return null;

		return DAO_Worker::get($this->worker_id);
	}

	public function setWorker(Model_Worker $worker=null) {
		if(is_null($worker)) {
			$this->worker_id = null;

		} else {
			$this->worker_id = $worker->id;

			// Language
			if($worker->language) {
				$_SESSION['locale'] = $worker->language;
				DevblocksPlatform::setLocale($worker->language);
			}

			// Timezone
			if($worker->timezone) {
				$_SESSION['timezone'] = $worker->timezone;
				@date_default_timezone_set($worker->timezone);
			}

			// Time format
			if($worker->time_format) {
				$_SESSION['time_format'] = $worker->time_format;
				DevblocksPlatform::setDateTimeFormat($worker->time_format);
			}
		}
	}

	public function isImposter() {
		return !empty($this->imposter_id);
	}

	/**
	 * @return Model_Worker
	 */
	public function getImposter() {
		if(empty($this->imposter_id))
			return null;

		return DAO_Worker::get($this->imposter_id);
	}

	public function setImposter(Model_Worker $worker=null) {
		if(is_null($worker)) {
			$this->imposter_id = null;
		} else {
			$this->imposter_id = $worker->id;
		}
	}


};

class Cerb_ORMHelper extends DevblocksORMHelper {
	static public function escape($str) {
		$db = DevblocksPlatform::getDatabaseService();
		return $db->escape($str);
	}

	static public function qstr($str) {
		$db = DevblocksPlatform::getDatabaseService();
		return $db->qstr($str);
	}

	static function recastArrayToModel($array, $model_class) {
		if(false == ($model = new $model_class))
			return false;

		if(is_array($array))
		foreach($array as $k => $v) {
			$model->$k = $v;
		}

		return $model;
	}

	static function uniqueFields($fields, $model) {
		if(is_object($model))
			$model = (array) $model;

		if(!is_array($model))
			return false;

		foreach($fields as $k => $v) {
			if(isset($model[$k]) && $model[$k] == $v)
				unset($fields[$k]);
		}

		return $fields;
	}

	/**
	 * 
	 * @param array $ids
	 * @return Model_Address[]
	 */
	static function getIds($ids) {
		if(!is_array($ids))
			$ids = array($ids);

		if(empty($ids))
			return array();

		if(!method_exists(get_called_class(), 'getWhere'))
			return array();

		$db = DevblocksPlatform::getDatabaseService();

		$ids = DevblocksPlatform::importVar($ids, 'array:integer');

		$models = array();

		$results = static::getWhere(sprintf("id IN (%s)",
			implode(',', $ids)
		));

		// Sort $models in the same order as $ids
		foreach($ids as $id) {
			if(isset($results[$id]))
				$models[$id] = $results[$id];
		}

		unset($results);

		return $models;
	}

	static protected function paramExistsInSet($key, $params) {
		$exists = false;

		if(is_array($params))
		array_walk_recursive($params, function($param) use ($key, &$exists) {
			if($param instanceof DevblocksSearchCriteria
				&& 0 == strcasecmp($param->field, $key))
					$exists = true;
		});

		return $exists;
	}
	
	static protected function _getRandom($table, $pkey='id') {
		$db = DevblocksPlatform::getDatabaseService();
		$offset = $db->GetOneSlave(sprintf("SELECT ROUND(RAND()*(SELECT COUNT(*)-1 FROM %s))", $table));
		return $db->GetOneSlave(sprintf("SELECT %s FROM %s LIMIT %d,1", $pkey, $table, $offset));
	}

	static protected function _appendSelectJoinSqlForCustomFieldTables($tables, $params, $key, $select_sql, $join_sql) {
		$custom_fields = DAO_CustomField::getAll();
		$field_ids = array();

		$return_multiple_values = false; // can our CF return more than one hit? (GROUP BY)

		if(is_array($tables))
		foreach($tables as $tbl_name => $null) {
			// Filter and sanitize
			if(substr($tbl_name,0,3) != "cf_" // not a custom field
				|| 0 == ($field_id = intval(substr($tbl_name,3)))) // not a field_id
				continue;

			// Make sure the field exists for this context
			if(!isset($custom_fields[$field_id]))
				continue;

			$field_table = sprintf("cf_%d", $field_id);
			$value_table = '';
			$field_key = $key;

			if(is_array($key)) {
				if(isset($key[$custom_fields[$field_id]->context]))
					$field_key = $key[$custom_fields[$field_id]->context];
				else
					continue;
			}

			// Join value by field data type
			switch($custom_fields[$field_id]->type) {
				case Model_CustomField::TYPE_MULTI_LINE:
					$value_table = 'custom_field_clobvalue';
					break;
				case Model_CustomField::TYPE_CHECKBOX:
				case Model_CustomField::TYPE_DATE:
				case Model_CustomField::TYPE_FILE:
				case Model_CustomField::TYPE_FILES:
				case Model_CustomField::TYPE_LINK:
				case Model_CustomField::TYPE_NUMBER:
				case Model_CustomField::TYPE_WORKER:
					$value_table = 'custom_field_numbervalue';
					break;
				case Model_CustomField::TYPE_DROPDOWN:
				case Model_CustomField::TYPE_MULTI_CHECKBOX:
				case Model_CustomField::TYPE_SINGLE_LINE:
				case Model_CustomField::TYPE_URL:
					$value_table = 'custom_field_stringvalue';
					break;
				default:
					$value_table = null;
					break;
			}

			$has_multiple_values = false;
			switch($custom_fields[$field_id]->type) {
				case Model_CustomField::TYPE_MULTI_CHECKBOX:
				case Model_CustomField::TYPE_FILES:
					$has_multiple_values = true;
					break;
			}

			// If we have multiple values but we don't need to WHERE the JOIN, be efficient and don't GROUP BY
			if(!Cerb_ORMHelper::paramExistsInSet('cf_'.$field_id, $params)) {
				$select_sql .= sprintf(",(SELECT %s FROM %s WHERE %s=context_id AND field_id=%d ORDER BY field_value%s) AS %s ",
					($has_multiple_values ? 'GROUP_CONCAT(field_value SEPARATOR "\n")' : 'field_value'),
					$value_table,
					$field_key,
					$field_id,
					($has_multiple_values ? '' : ' LIMIT 1'),
					$field_table
				);

			} else {
				$select_sql .= sprintf(", %s.field_value as %s ",
					$field_table,
					$field_table
				);

				$join_sql .= sprintf("LEFT JOIN %s %s ON (%s=%s.context_id AND %s.field_id=%d) ",
					$value_table,
					$field_table,
					$field_key,
					$field_table,
					$field_table,
					$field_id
				);

				// If we do need to WHERE this JOIN, make sure we GROUP BY
				if($has_multiple_values)
					$return_multiple_values = true;
			}
		}

		return array($select_sql, $join_sql, $return_multiple_values);
	}

	static function _searchComponentsVirtualOwner(&$param, &$join_sql, &$where_sql) {
		$worker_ids = DevblocksPlatform::sanitizeArray($param->value, 'integer', array('nonzero','unique'));

		// Join and return anything
		if(DevblocksSearchCriteria::OPER_TRUE == $param->operator) {
			$param->operator = DevblocksSearchCriteria::OPER_IS_NOT_NULL;

		} else {
			if(empty($param->value)) {
				switch($param->operator) {
					case DevblocksSearchCriteria::OPER_IN:
						$param->operator = DevblocksSearchCriteria::OPER_IS_NULL;
						break;
					case DevblocksSearchCriteria::OPER_NIN:
						$param->operator = DevblocksSearchCriteria::OPER_IS_NOT_NULL;
						break;
				}
			}

			switch($param->operator) {
				case DevblocksSearchCriteria::OPER_IN:
					$where_sql .= sprintf("AND owner_context = %s AND owner_context_id IN (%s) ",
						self::qstr(CerberusContexts::CONTEXT_WORKER),
						implode(',', $worker_ids)
					);
					break;
				case DevblocksSearchCriteria::OPER_IN_OR_NULL:
				case DevblocksSearchCriteria::OPER_IS_NULL:
					$worker_ids[] = 0;
					$where_sql .= sprintf("AND owner_context = %s AND owner_context_id IN (%s) ",
						self::qstr(CerberusContexts::CONTEXT_WORKER),
						implode(',', $worker_ids)
					);
					break;
				case DevblocksSearchCriteria::OPER_NIN:
					$where_sql .= sprintf("AND owner_context = %s AND owner_context_id NOT IN (%s) ",
						self::qstr(CerberusContexts::CONTEXT_WORKER),
						implode(',', $worker_ids)
					);
					break;
				case DevblocksSearchCriteria::OPER_IS_NOT_NULL:
					$where_sql .= sprintf("AND owner_context = %s AND owner_context_id NOT = 0 ",
						self::qstr(CerberusContexts::CONTEXT_WORKER),
						implode(',', $worker_ids)
					);
					break;
				case DevblocksSearchCriteria::OPER_NIN_OR_NULL:
					$worker_ids[] = 0;
					$where_sql .= sprintf("AND owner_context = %s AND owner_context_id NOT IN (%s) ",
						self::qstr(CerberusContexts::CONTEXT_WORKER),
						implode(',', $worker_ids)
					);
					break;
			}
		}
	}

	static function _searchComponentsVirtualWatchers(&$param, $from_context, $from_index, &$join_sql, &$where_sql, &$tables) {
		if(!is_array($param->value))
			$param->value = array($param->value);

		$table_alias = 'context_watcher'; // . uniq_id();

		$param->value = DevblocksPlatform::sanitizeArray($param->value, 'integer', array('nonzero','unique'));

		// Join and return anything
		if(DevblocksSearchCriteria::OPER_TRUE == $param->operator) {
			if(!isset($tables[$table_alias])) {
				$join_sql .= sprintf("LEFT JOIN context_link AS %s ON (%s.from_context = '%s' AND %s.from_context_id = %s AND %s.to_context = 'cerberusweb.contexts.worker') ",
					$table_alias,
					$table_alias,
					$from_context,
					$table_alias,
					$from_index,
					$table_alias
				);

			} else {
				$where_sql .= sprintf("(%s.from_context = '%s' AND %s.from_context_id = %s AND %s.to_context = 'cerberusweb.contexts.worker') ",
					$table_alias,
					$from_context,
					$table_alias,
					$from_index,
					$table_alias
				);
			}

		} else {
			if(empty($param->value)) {
				switch($param->operator) {
					case DevblocksSearchCriteria::OPER_IN:
						$param->operator = DevblocksSearchCriteria::OPER_IS_NULL;
						break;
					case DevblocksSearchCriteria::OPER_NIN:
						$param->operator = DevblocksSearchCriteria::OPER_IS_NOT_NULL;
						break;
				}
			}

			switch($param->operator) {
				case DevblocksSearchCriteria::OPER_IN:
					if(!isset($tables[$table_alias])) {
						$join_sql .= sprintf("INNER JOIN context_link AS %s ON (%s.from_context = '%s' AND %s.from_context_id = %s AND %s.to_context = 'cerberusweb.contexts.worker' AND %s.to_context_id IN (%s)) ",
							$table_alias,
							$table_alias,
							$from_context,
							$table_alias,
							$from_index,
							$table_alias,
							$table_alias,
							implode(',', $param->value)
						);

					} else {
						$where_sql .= sprintf("AND (%s.from_context = '%s' AND %s.from_context_id = %s AND %s.to_context = 'cerberusweb.contexts.worker' AND %s.to_context_id IN (%s)) ",
							$table_alias,
							$from_context,
							$table_alias,
							$from_index,
							$table_alias,
							$table_alias,
							implode(',', $param->value)
						);

					}
					break;
				case DevblocksSearchCriteria::OPER_IN_OR_NULL:
				case DevblocksSearchCriteria::OPER_IS_NULL:
					if(!isset($tables[$table_alias])) {
						$join_sql .= sprintf("LEFT JOIN context_link AS %s ON (%s.from_context = '%s' AND %s.from_context_id = %s AND %s.to_context = 'cerberusweb.contexts.worker') ",
							$table_alias,
							$table_alias,
							$from_context,
							$table_alias,
							$from_index,
							$table_alias
						);
						$where_sql .= sprintf("AND (%s.to_context_id IS NULL %s) ",
							$table_alias,
							(!empty($param->value) ? sprintf("OR %s.to_context_id IN (%s) ", $table_alias, implode(',',$param->value)) : '')
						);

					} else {
						$where_sql .= sprintf("AND (%s.from_context = '%s' AND %s.from_context_id = %s AND %s.to_context = 'cerberusweb.contexts.worker' AND (%s.to_context_id IS NULL %s)) ",
							$table_alias,
							$table_alias,
							$from_context,
							$table_alias,
							$from_index,
							$table_alias,
							$table_alias,
							(!empty($param->value) ? sprintf("OR %s.to_context_id IN (%s) ", $table_alias, implode(',',$param->value)) : '')
						);

					}
					break;
				case DevblocksSearchCriteria::OPER_NIN:
					if(!isset($tables[$table_alias])) {
						$join_sql .= sprintf("INNER JOIN context_link AS %s ON (%s.from_context = '%s' AND %s.from_context_id = %s AND %s.to_context = 'cerberusweb.contexts.worker' AND %s.to_context_id NOT IN (%s)) ",
							$table_alias,
							$table_alias,
							$from_context,
							$table_alias,
							$from_index,
							$table_alias,
							$table_alias,
							implode(',', $param->value)
						);

					} else {
						$where_sql .= sprintf("AND (%s.from_context = '%s' AND %s.from_context_id = %s AND %s.to_context = 'cerberusweb.contexts.worker' AND %s.to_context_id NOT IN (%s)) ",
							$table_alias,
							$from_context,
							$table_alias,
							$from_index,
							$table_alias,
							$table_alias,
							implode(',', $param->value)
						);

					}
					break;
				case DevblocksSearchCriteria::OPER_IS_NOT_NULL:
					if(!isset($tables[$table_alias])) {
						$join_sql .= sprintf("LEFT JOIN context_link AS %s ON (%s.from_context = '%s' AND %s.from_context_id = %s AND %s.to_context = 'cerberusweb.contexts.worker') ",
							$table_alias,
							$table_alias,
							$from_context,
							$table_alias,
							$from_index,
							$table_alias
						);
						$where_sql .= sprintf("AND (%s.to_context_id IS NOT NULL) ", $table_alias);

					} else {
						$where_sql .= sprintf("AND (%s.from_context = '%s' AND %s.from_context_id = %s AND %s.to_context = 'cerberusweb.contexts.worker' AND %s.to_context_id IS NOT NULL) ",
							$table_alias,
							$from_context,
							$table_alias,
							$from_index,
							$table_alias,
							$table_alias
						);

					}
					break;
				case DevblocksSearchCriteria::OPER_NIN_OR_NULL:
					if(!isset($tables[$table_alias])) {
						$join_sql .= sprintf("LEFT JOIN context_link AS %s ON (%s.from_context = '%s' AND %s.from_context_id = %s AND %s.to_context = 'cerberusweb.contexts.worker') ",
							$table_alias,
							$table_alias,
							$from_context,
							$table_alias,
							$from_index,
							$table_alias
						);
						$where_sql .= sprintf("AND (%s.to_context_id IS NULL %s) ",
							$table_alias,
							(!empty($param->value) ? sprintf("OR %s.to_context_id NOT IN (%s)", $table_alias, implode(',',$param->value)) : '')
						);

					} else {
						$where_sql .= sprintf("AND (%s.from_context = '%s' AND %s.from_context_id = %s AND %s.to_context = 'cerberusweb.contexts.worker' AND (%s.to_context_id IS NULL %s)) ",
							$table_alias,
							$from_context,
							$table_alias,
							$from_index,
							$table_alias,
							$table_alias,
							(!empty($param->value) ? sprintf("OR %s.to_context_id NOT IN (%s)", $table_alias, implode(',',$param->value)) : '')
						);

					}
					break;
			}
		}

		// Mark the table as used
		$tables[$table_alias] = $table_alias;
	}

	static function _searchComponentsVirtualHasFieldset(&$param, $to_context, $to_index, &$join_sql, &$where_sql) {
		if($param->operator != DevblocksSearchCriteria::OPER_TRUE) {
			if(empty($param->value) || !is_array($param->value))
				$param->operator = DevblocksSearchCriteria::OPER_IS_NULL;
		}

		$table_alias = 'fieldset_' . uniqid();
		$where_contexts = array();

		if(is_array($param->value))
		foreach($param->value as $context_id) {
			$where_contexts[] = sprintf("(%s.from_context = %s%s)",
				$table_alias,
				self::qstr(CerberusContexts::CONTEXT_CUSTOM_FIELDSET),
				(!empty($context_id) ? sprintf(" AND %s.from_context_id = %d", $table_alias, $context_id) : '')
			);
		}

		switch($param->operator) {
			case DevblocksSearchCriteria::OPER_TRUE:
				break;

			case DevblocksSearchCriteria::OPER_IS_NULL:
				$where_sql .= sprintf("AND (SELECT count(*) FROM context_link WHERE context_link.to_context=%s AND context_link.to_context_id=%s) = 0 ",
					self::qstr($to_context),
					$to_index
				);
				break;

			case DevblocksSearchCriteria::OPER_IN:
				$join_sql .= sprintf("INNER JOIN context_link AS %s ON (%s.to_context=%s AND %s.to_context_id=%s) ",
					$table_alias,
					$table_alias,
					Cerb_ORMHelper::qstr($to_context),
					$table_alias,
					$to_index
				);

				$where_sql .= 'AND (' . implode(' OR ', $where_contexts) . ') ';
				break;
		}
	}

	static function _searchComponentsVirtualContextLinks(&$param, $to_context, $to_index, &$join_sql, &$where_sql) {
		if($param->operator != DevblocksSearchCriteria::OPER_TRUE) {
			if(empty($param->value) || !is_array($param->value))
				$param->operator = DevblocksSearchCriteria::OPER_IS_NULL;
		}

		$table_alias = 'context_links_' . uniqid();
		$where_contexts = array();

		if(is_array($param->value))
		foreach($param->value as $context_data) {
			@list($context, $context_id) = explode(':', $context_data, 2);

			if(empty($context))
				return;

			$where_contexts[] = sprintf("(%s.from_context = %s%s)",
				$table_alias,
				self::qstr($context),
				(!empty($context_id) ? sprintf(" AND %s.from_context_id = %d", $table_alias, $context_id) : '')
			);
		}

		switch($param->operator) {
			case DevblocksSearchCriteria::OPER_TRUE:
				break;

			case DevblocksSearchCriteria::OPER_IS_NULL:
				$where_sql .= sprintf("AND (SELECT count(*) FROM context_link WHERE context_link.to_context=%s AND context_link.to_context_id=%s) = 0 ",
					self::qstr($to_context),
					$to_index
				);
				break;

			case DevblocksSearchCriteria::OPER_IN:
				$join_sql .= sprintf("INNER JOIN context_link AS %s ON (%s.to_context=%s AND %s.to_context_id=%s) ",
					$table_alias,
					$table_alias,
					Cerb_ORMHelper::qstr($to_context),
					$table_alias,
					$to_index
				);

				$where_sql .= 'AND (' . implode(' OR ', $where_contexts) . ') ';
				break;
		}
	}
};
