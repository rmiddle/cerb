<?php
/***********************************************************************
| Cerb(tm) developed by Webgroup Media, LLC.
|-----------------------------------------------------------------------
| All source code & content (c) Copyright 2002-2017, Webgroup Media LLC
|   unless specifically noted otherwise.
|
| This source code is released under the Devblocks Public License.
| The latest version of this license can be found here:
| http://cerb.ai/license
|
| By using this software, you acknowledge having read this license
| and agree to be bound thereby.
| ______________________________________________________________________
|	http://cerb.ai	    http://webgroup.media
***********************************************************************/

if(version_compare(PHP_VERSION, "5.5", "<")) {
	header('Status: 500');
	die("Cerb requires PHP 5.5 or later.");
}

if(!extension_loaded('mysqli')) {
	header('Status: 500');
	die("Cerb requires the 'mysqli' PHP extension.  Please enable it.");
}

@set_time_limit(3600); // 1hr
require('../framework.config.php');
require_once(DEVBLOCKS_PATH . 'Devblocks.class.php');
require_once(APP_PATH . '/api/Application.class.php');
require_once(APP_PATH . '/install/classes.php');

DevblocksPlatform::getCacheService()->clean();

// DevblocksPlatform::init() workaround
if(!defined('DEVBLOCKS_WEBPATH')) {
	$php_self = $_SERVER["SCRIPT_NAME"];
	$php_self = str_replace('/install','',$php_self);
	$pos = strrpos($php_self,'/');
	$php_self = substr($php_self,0,$pos) . '/';
	@define('DEVBLOCKS_WEBPATH',$php_self);
	@define('DEVBLOCKS_APP_WEBPATH',$php_self);
}

DevblocksPlatform::setHandlerSession('Cerb_DevblocksSessionHandler');

define('STEP_ENVIRONMENT', 1);
define('STEP_LICENSE', 2);
define('STEP_DATABASE', 3);
define('STEP_SAVE_CONFIG_FILE', 4);
define('STEP_INIT_DB', 5);
define('STEP_CONTACT', 6);
define('STEP_OUTGOING_MAIL', 7);
define('STEP_DEFAULTS', 8);
define('STEP_REGISTER', 9);
define('STEP_UPGRADE', 10);
define('STEP_FINISHED', 11);

define('TOTAL_STEPS', 11);

// Import GPC variables to determine our scope/step.
@$step = DevblocksPlatform::importGPC($_REQUEST['step'],'integer');

/*
 * [TODO] We can run some quick tests to bypass steps we've already passed
 * even when returning to the page with a NULL step.
 */
if(empty($step)) $step = STEP_ENVIRONMENT;

// [TODO] Could convert to CerberusApplication::checkRequirements()

@chmod(APP_TEMP_PATH, 0770);
@mkdir(APP_SMARTY_COMPILE_PATH);
@chmod(APP_SMARTY_COMPILE_PATH, 0770);
@mkdir(APP_TEMP_PATH . '/cache/');
@chmod(APP_TEMP_PATH . '/cache/', 0770);

// Make sure the temporary directories of Devblocks are writeable.
if(!is_writeable(APP_TEMP_PATH)) {
	DevblocksPlatform::dieWithHttpError(APP_TEMP_PATH ." is not writeable by the webserver.  Please adjust permissions and reload this page.", 500);
}

if(!is_writeable(APP_SMARTY_COMPILE_PATH)) {
	DevblocksPlatform::dieWithHttpError(APP_SMARTY_COMPILE_PATH . " is not writeable by the webserver.  Please adjust permissions and reload this page.", 500);
}

if(!is_writeable(APP_TEMP_PATH . "/cache/")) {
	DevblocksPlatform::dieWithHttpError(APP_TEMP_PATH . "/cache/" . " is not writeable by the webserver.  Please adjust permissions and reload this page.", 500);
}

@chmod(APP_STORAGE_PATH, 0770);
@chmod(APP_STORAGE_PATH . '/mail/new/', 0770);
@chmod(APP_STORAGE_PATH . '/mail/fail/', 0770);

if(!is_writeable(APP_STORAGE_PATH)) {
	DevblocksPlatform::dieWithHttpError(APP_STORAGE_PATH . " is not writeable by the webserver.  Please adjust permissions and reload this page.", 500);
}

if(!is_writeable(APP_STORAGE_PATH . "/import/fail/")) {
	DevblocksPlatform::dieWithHttpError(APP_STORAGE_PATH . "/import/fail/ is not writeable by the webserver.  Please adjust permissions and reload this page.", 500);
}

if(!is_writeable(APP_STORAGE_PATH . "/import/new/")) {
	DevblocksPlatform::dieWithHttpError(APP_STORAGE_PATH . "/import/new/ is not writeable by the webserver.  Please adjust permissions and reload this page.", 500);
}

if(!is_writeable(APP_STORAGE_PATH . "/mail/new/")) {
	DevblocksPlatform::dieWithHttpError(APP_STORAGE_PATH . "/mail/new/ is not writeable by the webserver.  Please adjust permissions and reload this page.", 500);
}

if(!is_writeable(APP_STORAGE_PATH . "/mail/fail/")) {
	DevblocksPlatform::dieWithHttpError(APP_STORAGE_PATH . "/mail/fail/ is not writeable by the webserver.  Please adjust permissions and reload this page.", 500);
}

// [TODO] Move this to the framework init (installer blocks this at the moment)
DevblocksPlatform::setLocale('en_US');

// Get a reference to the template system and configure it
$tpl = DevblocksPlatform::getTemplateService();
$tpl->template_dir = APP_PATH . '/install/templates';
$tpl->caching = 0;

$tpl->assign('step', $step);

switch($step) {
	default:
	case STEP_ENVIRONMENT:
		$results = array();
		$fails = 0;
		
		// PHP Version
		if(version_compare(PHP_VERSION,"5.5") >=0) {
			$results['php_version'] = PHP_VERSION;
		} else {
			$results['php_version'] = false;
			$fails++;
		}
		
		// File Uploads
		$ini_file_uploads = ini_get("file_uploads");
		if($ini_file_uploads == 1 || strcasecmp($ini_file_uploads,"on")==0) {
			$results['file_uploads'] = true;
		} else {
			$results['file_uploads'] = false;
			$fails++;
		}
		
		// File Upload Temporary Directory
		$ini_upload_tmp_dir = ini_get("upload_tmp_dir");
		if(!empty($ini_upload_tmp_dir)) {
			$results['upload_tmp_dir'] = true;
		} else {
			$results['upload_tmp_dir'] = false;
			//$fails++; // Not fatal
		}

		$memory_limit = ini_get("memory_limit");
		if ($memory_limit == '' || $memory_limit == -1) { // empty string means failure or not defined, assume no compiled memory limits
			$results['memory_limit'] = true;
		} else {
			$ini_memory_limit = DevblocksPlatform::parseBytesString($memory_limit);
			if($ini_memory_limit >= 16777216) {
				$results['memory_limit'] = true;
			} else {
				$results['memory_limit'] = false;
				$fails++;
			}
		}
		
		// Extension: Sessions
		if(extension_loaded("session")) {
			$results['ext_session'] = true;
		} else {
			$results['ext_session'] = false;
			$fails++;
		}
		
		// Extension: cURL
		if(extension_loaded("curl")) {
			$results['ext_curl'] = true;
		} else {
			$results['ext_curl'] = false;
			$fails++;
		}
		
		// Extension: PCRE
		if(extension_loaded("pcre")) {
			$results['ext_pcre'] = true;
		} else {
			$results['ext_pcre'] = false;
			$fails++;
		}

		// Extension: SPL
		if(extension_loaded("spl")) {
			$results['ext_spl'] = true;
		} else {
			$results['ext_spl'] = false;
			$fails++;
		}

		// Extension: ctype
		if(extension_loaded("ctype")) {
			$results['ext_ctype'] = true;
		} else {
			$results['ext_ctype'] = false;
			$fails++;
		}

		// Extension: GD
		if(extension_loaded("gd") && function_exists('imagettfbbox')) {
			$results['ext_gd'] = true;
		} else {
			$results['ext_gd'] = false;
			$fails++;
		}
		
		// Extension: IMAP
		if(extension_loaded("imap")) {
			$results['ext_imap'] = true;
		} else {
			$results['ext_imap'] = false;
			$fails++;
		}
		
		// Extension: MailParse
		if(extension_loaded("mailparse")) {
			$results['ext_mailparse'] = true;
		} else {
			$results['ext_mailparse'] = false;
			$fails++;
		}
		
		// Extension: mbstring
		if(extension_loaded("mbstring")) {
			$results['ext_mbstring'] = true;
		} else {
			$results['ext_mbstring'] = false;
			$fails++;
		}
		
		// Extension: MySQLi
		if(extension_loaded("mysqli")) {
			$results['ext_mysqli'] = true;
		} else {
			$results['ext_mysqli'] = false;
			$fails++;
		}
		
		// Extension: DOM
		if(extension_loaded("dom")) {
			$results['ext_dom'] = true;
		} else {
			$results['ext_dom'] = false;
			$fails++;
		}
		
		// Extension: XML
		if(extension_loaded("xml")) {
			$results['ext_xml'] = true;
		} else {
			$results['ext_xml'] = false;
			$fails++;
		}
		
		// Extension: SimpleXML
		if(extension_loaded("simplexml")) {
			$results['ext_simplexml'] = true;
		} else {
			$results['ext_simplexml'] = false;
			$fails++;
		}
		
		// Extension: JSON
		if(extension_loaded("json")) {
			$results['ext_json'] = true;
		} else {
			$results['ext_json'] = false;
			$fails++;
		}
		
		// Extension: OpenSSL
		if(extension_loaded("openssl")) {
			$results['ext_openssl'] = true;
		} else {
			$results['ext_openssl'] = false;
			$fails++;
		}
		
		$tpl->assign('fails', $fails);
		$tpl->assign('results', $results);
		$tpl->assign('template', 'steps/step_environment.tpl');
		
		break;
	
	case STEP_LICENSE:
	    @$accept = DevblocksPlatform::importGPC($_POST['accept'],'integer', 0);
	    
	    if(1 == $accept) {
			$tpl->assign('step', STEP_DATABASE);
			$tpl->display('steps/redirect.tpl');
			exit;
	    }
		
		$tpl->assign('template', 'steps/step_license.tpl');
		
	    break;
	
	// Configure and test the database connection
	// [TODO] This should remind the user to make a backup (and refer to a wiki article how)
	case STEP_DATABASE:
		// Import scope (if post)
		@$db_driver = DevblocksPlatform::importGPC($_POST['db_driver'],'string');
		@$db_engine = DevblocksPlatform::importGPC($_POST['db_engine'],'string');
		@$db_server = DevblocksPlatform::importGPC($_POST['db_server'],'string');
		@$db_name = DevblocksPlatform::importGPC($_POST['db_name'],'string');
		@$db_user = DevblocksPlatform::importGPC($_POST['db_user'],'string');
		@$db_pass = DevblocksPlatform::importGPC($_POST['db_pass'],'string');

		@$db = DevblocksPlatform::getDatabaseService();
		if(!is_null($db)) {
			// If we've been to this step, skip past framework.config.php
			$tpl->assign('step', STEP_INIT_DB);
			$tpl->display('steps/redirect.tpl');
			exit;
		}
		unset($db);
		
		// [JAS]: Detect available database drivers
		
		$drivers = array();
		
		if(extension_loaded('mysqli'))
			$drivers['mysqli'] = 'MySQLi';
		
		$tpl->assign('drivers', $drivers);
		
		// [JAS]: Possible storage engines
		
		$engines = array(
			'innodb' => 'InnoDB (Recommended)',
			'myisam' => 'MyISAM (Legacy)',
		);
		
		$tpl->assign('engines', $engines);
		
		if(!empty($db_driver) && !empty($db_engine) && !empty($db_server) && !empty($db_name) && !empty($db_user)) {
			$db_passed = false;
			$errors = array();
			
			if(false !== (@$_db = mysqli_connect($db_server, $db_user, $db_pass))) {
				if(false !== mysqli_select_db($_db, $db_name)) {
					$db_passed = true;
				} else {
					$db_passed = false;
					$errors[] = mysqli_error($_db);
				}
				
				// Check if the engine we want exists, otherwise default
				$rs = mysqli_query($_db, "SHOW ENGINES");
				
				if(!($rs instanceof mysqli_result)) {
					$db_passed = false;
					$errors[] = "Can't run SHOW ENGINES query against the database.";
				}
				
				$discovered_engines = array();
				while($row = mysqli_fetch_assoc($rs)) {
					$discovered_engines[] = mb_strtolower($row['Engine']);
				}
				mysqli_free_result($rs);
				
				// Check the preferred DB engine
				if(!in_array($db_engine, $discovered_engines)) {
					$db_passed = false;
					$errors[] = sprintf("The '%s' storage engine is not enabled.", $db_engine);
				}
				
				// Check user privileges
				if($db_passed) {
					// RESET
					mysqli_query($_db, "DROP TABLE IF EXISTS _installer_test_suite");
					
					// CREATE TABLE
					if($db_passed && false === mysqli_query($_db, "CREATE TABLE _installer_test_suite (id int)")) {
						$db_passed = false;
						$errors[] = sprintf("The database user lacks the CREATE privilege.");
					}
					// INSERT
					if($db_passed && false === mysqli_query($_db, "INSERT INTO _installer_test_suite (id) values(1)")) {
						$db_passed = false;
						$errors[] = sprintf("The database user lacks the INSERT privilege.");
					}
					// SELECT
					if($db_passed && false === mysqli_query($_db, "SELECT id FROM _installer_test_suite")) {
						$db_passed = false;
						$errors[] = sprintf("The database user lacks the SELECT privilege.");
					}
					// UPDATE
					if($db_passed && false === mysqli_query($_db, "UPDATE _installer_test_suite SET id = 2")) {
						$db_passed = false;
						$errors[] = sprintf("The database user lacks the UPDATE privilege.");
					}
					// DELETE
					if($db_passed && false === mysqli_query($_db, "DELETE FROM _installer_test_suite WHERE id > 0")) {
						$db_passed = false;
						$errors[] = sprintf("The database user lacks the DELETE privilege.");
					}
					// ALTER TABLE
					if($db_passed && false === mysqli_query($_db, "ALTER TABLE _installer_test_suite MODIFY COLUMN id int unsigned")) {
						$db_passed = false;
						$errors[] = sprintf("The database user lacks the ALTER privilege.");
					}
					// ADD FULLTEXT INDEX
					if($db_passed && false === mysqli_query($_db, "ALTER TABLE _installer_test_suite ADD COLUMN content TEXT, ADD FULLTEXT (content)")) {
						$db_passed = false;
						$errors[] = sprintf("The database engine doesn't support FULLTEXT indexes.");
					}
					// DROP TABLE
					if($db_passed && false === mysqli_query($_db, "DROP TABLE IF EXISTS _installer_test_suite")) {
						$db_passed = false;
						$errors[] = sprintf("The database user lacks the DROP privilege.");
					}
					// CREATE TEMPORARY TABLES
					if($db_passed && false === mysqli_query($_db, "CREATE TEMPORARY TABLE IF NOT EXISTS _installer_test_suite_tmp (id int)")) {
						$db_passed = false;
						$errors[] = sprintf("The database user lacks the CREATE TEMPORARY TABLES privilege.");
					}
					if($db_passed && false === mysqli_query($_db, "DROP TABLE IF EXISTS _installer_test_suite_tmp")) {
						$db_passed = false;
					}
					
					// Privs summary
					if(!$db_passed)
						$errors[] = sprintf("The database user must have the following privileges: CREATE, ALTER, DROP, SELECT, INSERT, UPDATE, DELETE, CREATE TEMPORARY TABLES");
				}
				
				unset($discovered_engines);
				
			} else {
				$db_passed = false;
				$errors[] = "Database connection failed!  Please check your settings and try again.";
			}
			
			$tpl->assign('db_driver', $db_driver);
			$tpl->assign('db_engine', $db_engine);
			$tpl->assign('db_server', $db_server);
			$tpl->assign('db_name', $db_name);
			$tpl->assign('db_user', $db_user);
			$tpl->assign('db_pass', $db_pass);
			
			// If passed, write config file and continue
			if($db_passed) {
				@$row = mysqli_fetch_row(mysqli_query($_db, "SHOW VARIABLES LIKE 'character_set_database'"));
				$encoding = (is_array($row) && 0==strcasecmp($row[1],'utf8')) ? 'utf8' : 'latin1';
				
				// Write database settings to framework.config.php
				$result = CerberusInstaller::saveFrameworkConfig($db_driver, $db_engine, $encoding, $db_server, $db_name, $db_user, $db_pass);
				
				// [JAS]: If we didn't save directly to the config file, user action required
				if(0 != strcasecmp($result,'config')) {
					$tpl->assign('result', $result);
					$tpl->assign('config_path', APP_PATH . "/framework.config.php");
					$tpl->assign('template', 'steps/step_config_file.tpl');
					
				} else { // skip the config writing step
					$tpl->assign('step', STEP_INIT_DB);
					$tpl->display('steps/redirect.tpl');
					exit;
				}
				
			} else { // If failed, re-enter
				$tpl->assign('failed', true);
				$tpl->assign('errors', $errors);
				$tpl->assign('template', 'steps/step_database.tpl');
			}
			
		} else {
			$tpl->assign('db_server', 'localhost');
			$tpl->assign('template', 'steps/step_database.tpl');
		}
		break;
		
	// [JAS]: If we didn't save directly to the config file, user action required
	case STEP_SAVE_CONFIG_FILE:
		@$db_driver = DevblocksPlatform::importGPC($_POST['db_driver'],'string');
		@$db_engine = DevblocksPlatform::importGPC($_POST['db_engine'],'string');
		@$db_server = DevblocksPlatform::importGPC($_POST['db_server'],'string');
		@$db_name = DevblocksPlatform::importGPC($_POST['db_name'],'string');
		@$db_user = DevblocksPlatform::importGPC($_POST['db_user'],'string');
		@$db_pass = DevblocksPlatform::importGPC($_POST['db_pass'],'string');
		@$result = DevblocksPlatform::importGPC($_POST['result'],'string');
		
		// Check to make sure our constants match our input
		if(
			0 == strcasecmp($db_engine,APP_DB_ENGINE) &&
			0 == strcasecmp($db_server,APP_DB_HOST) &&
			0 == strcasecmp($db_name,APP_DB_DATABASE) &&
			0 == strcasecmp($db_user,APP_DB_USER) &&
			0 == strcasecmp($db_pass,APP_DB_PASS)
		) { // we did it!
			$tpl->assign('step', STEP_INIT_DB);
			$tpl->display('steps/redirect.tpl');
			exit;
			
		} else { // oops!
			$tpl->assign('db_driver', $db_driver);
			$tpl->assign('db_engine', $db_engine);
			$tpl->assign('db_server', $db_server);
			$tpl->assign('db_name', $db_name);
			$tpl->assign('db_user', $db_user);
			$tpl->assign('db_pass', $db_pass);
			$tpl->assign('failed', true);
			$tpl->assign('result', $result);
			$tpl->assign('config_path', APP_PATH . "/framework.config.php");
			
			$tpl->assign('template', 'steps/step_config_file.tpl');
		}
		
		break;

	// Initialize the database
	case STEP_INIT_DB:
		$tables = array();
		
		if(false != ($db = DevblocksPlatform::getDatabaseService()))
			$tables = $db->metaTables();
		
		// [TODO] Add current user to patcher/upgrade authorized IPs
		
		if(empty($tables)) { // install
			try {
				DevblocksPlatform::update();
				
			} catch(Exception $e) {
				$tpl->assign('error', $e->getMessage());
				$tpl->assign('template', 'steps/step_init_db.tpl');
			}
			
			// Read in plugin information from the filesystem to the database
			$plugins = DevblocksPlatform::readPlugins();
			
			// Tailor which plugins are enabled by default
			if(is_array($plugins))
			foreach($plugins as $plugin) { /* @var $plugin DevblocksPluginManifest */
				switch ($plugin->id) {
					case 'devblocks.core':
					case 'cerberusweb.core':
					case 'cerberusweb.crm':
					case 'cerberusweb.feedback':
					case 'cerberusweb.kb':
					case 'cerberusweb.reports':
					case 'cerberusweb.support_center':
					case 'cerberusweb.simulator':
					case 'cerberusweb.timetracking':
						$plugin->setEnabled(true);
						break;
					
					default:
						$plugin->setEnabled(false);
						break;
				}
			}
			
			// Platform + App
			try {
				DevblocksPlatform::clearCache();
				
				CerberusApplication::update();
				
				// Reload plugin translations
				DAO_Translation::reloadPluginStrings();
				
				// Success
				$tpl->assign('step', STEP_CONTACT);
				$tpl->display('steps/redirect.tpl');
				exit;
				
				// [TODO] Verify the database
				
			} catch(Exception $e) {
				$tpl->assign('error', $e->getMessage());
				$tpl->assign('template', 'steps/step_init_db.tpl');
				exit;
			}
			
		} else { // upgrade / patch
			/*
			 * [TODO] We should probably only forward to upgrade when we know
			 * the proper tables were installed.  We may be repeating an install
			 * request where the clean DB failed.
			 */
			$tpl->assign('step', STEP_UPGRADE);
			$tpl->display('steps/redirect.tpl');
			exit;
		}
			
		break;
		

	// Personalize system information (title, timezone, language)
	case STEP_CONTACT:
		$settings = DevblocksPlatform::getPluginSettingsService();
		
		@$default_reply_from = DevblocksPlatform::importGPC($_POST['default_reply_from'],'string','do-not-reply@localhost');
		@$default_reply_personal = DevblocksPlatform::importGPC($_POST['default_reply_personal'],'string','');
		@$helpdesk_title = DevblocksPlatform::importGPC($_POST['helpdesk_title'],'string',$settings->get('cerberusweb.core',CerberusSettings::HELPDESK_TITLE,CerberusSettingsDefaults::HELPDESK_TITLE));
		@$form_submit = DevblocksPlatform::importGPC($_POST['form_submit'],'integer');
		
		if(!empty($form_submit) && !empty($default_reply_from)) {
			
			$validate = imap_rfc822_parse_adrlist(sprintf("<%s>", $default_reply_from),"localhost");

			$fields = array(
				DAO_AddressOutgoing::REPLY_SIGNATURE => '',
			);
			
			if(!empty($default_reply_from) && is_array($validate) && 1==count($validate)) {
				if(null != ($address = DAO_Address::lookupAddress($default_reply_from, true))) {
					$address_id = $address->id;
					$fields[DAO_AddressOutgoing::ADDRESS_ID] = $address->id;
				}
			}
			
			if(!empty($default_reply_personal)) {
				$fields[DAO_AddressOutgoing::REPLY_PERSONAL] = $default_reply_personal;
			}

			// Create or update
			if(null == DAO_AddressOutgoing::get($address->id)) {
				$address_id = DAO_AddressOutgoing::create($fields);
			} else {
				DAO_AddressOutgoing::update($address->id, $fields);
			}
			
			if(!empty($address_id))
				DAO_AddressOutgoing::setDefault($address_id);
			
			if(!empty($helpdesk_title))
				$settings->set('cerberusweb.core',CerberusSettings::HELPDESK_TITLE, $helpdesk_title);
			
			$tpl->assign('step', STEP_OUTGOING_MAIL);
			$tpl->display('steps/redirect.tpl');
			exit;
		}
		
		if(!empty($form_submit) && empty($default_reply_from)) {
			$tpl->assign('failed', true);
		}
		
		$tpl->assign('default_reply_from', $default_reply_from);
		$tpl->assign('default_reply_personal', $default_reply_personal);
		$tpl->assign('helpdesk_title', $helpdesk_title);
		
		$tpl->assign('template', 'steps/step_contact.tpl');
		
		break;
	
	// Set up and test the outgoing SMTP
	case STEP_OUTGOING_MAIL:
		@$extension_id = DevblocksPlatform::importGPC($_POST['extension_id'],'string');
		@$smtp_host = DevblocksPlatform::importGPC($_POST['smtp_host'],'string');
		@$smtp_port = DevblocksPlatform::importGPC($_POST['smtp_port'],'integer');
		@$smtp_enc = DevblocksPlatform::importGPC($_POST['smtp_enc'],'string');
		@$smtp_auth_user = DevblocksPlatform::importGPC($_POST['smtp_auth_user'],'string');
		@$smtp_auth_pass = DevblocksPlatform::importGPC($_POST['smtp_auth_pass'],'string');
		
		@$form_submit = DevblocksPlatform::importGPC($_POST['form_submit'],'integer');
		@$passed = DevblocksPlatform::importGPC($_POST['passed'],'integer');
		
		if(!empty($form_submit)) {

			// Test the given mail transport details
			try {
				if(false == ($mail_transport = Extension_MailTransport::get($extension_id)))
					throw new Exception_CerbInstaller("Invalid mail transport extension.");
				
				$error = null;
				
				if($mail_transport->id == CerbMailTransport_Smtp::ID) {
					$options = array(
						'host' => $smtp_host,
						'port' => $smtp_port,
						'auth_user' => $smtp_auth_user,
						'auth_pass' => $smtp_auth_pass,
						'enc' => $smtp_enc,
					);
				
					if(false == ($mail_transport->testConfig($options, $error)))
						throw new Exception_CerbInstaller($error);
					
					$fields = array(
						DAO_MailTransport::NAME => $smtp_host . ' SMTP',
						DAO_MailTransport::EXTENSION_ID => CerbMailTransport_Smtp::ID,
						DAO_MailTransport::IS_DEFAULT => 1,
						DAO_MailTransport::PARAMS_JSON => json_encode($options),
					);
					$transport_id = DAO_MailTransport::create($fields);
					
				} elseif($mail_transport->id == CerbMailTransport_Null::ID) {
					// This is always valid
					$fields = array(
						DAO_MailTransport::NAME => 'Null Mailer',
						DAO_MailTransport::EXTENSION_ID => CerbMailTransport_Null::ID,
						DAO_MailTransport::IS_DEFAULT => 1,
						DAO_MailTransport::PARAMS_JSON => json_encode(array()),
					);
					$transport_id = DAO_MailTransport::create($fields);
					
				}
				
				if($transport_id) {
					// Set this as the default transport id
					DAO_MailTransport::setDefault($transport_id);
					
					// Set it as the transport on the default reply-to
					if(false != ($default_replyto = DAO_AddressOutgoing::getDefault())) {
						DAO_AddressOutgoing::update($default_replyto->address_id, array(
							DAO_AddressOutgoing::REPLY_MAIL_TRANSPORT_ID => $transport_id,
						));
					}
				}
				
				// If we made it this far then we succeeded
				
				$tpl->assign('step', STEP_DEFAULTS);
				$tpl->display('steps/redirect.tpl');
				exit;
				
			} catch (Exception_CerbInstaller $e) {
				$error = $e->getMessage();
				
				$tpl->assign('extension_id', $extension_id);
				$tpl->assign('smtp_host', $smtp_host);
				$tpl->assign('smtp_port', $smtp_port);
				$tpl->assign('smtp_auth_user', $smtp_auth_user);
				$tpl->assign('smtp_auth_pass', $smtp_auth_pass);
				$tpl->assign('smtp_enc', $smtp_enc);
				$tpl->assign('form_submit', true);
				
				$tpl->assign('error_display', 'SMTP Connection Failed! ' . $e->getMessage());
				$tpl->assign('template', 'steps/step_outgoing_mail.tpl');
			}
			
		} else {
			$tpl->assign('template', 'steps/step_outgoing_mail.tpl');
		}
		
		break;

	// Set up the default objects
	case STEP_DEFAULTS:
		@$form_submit = DevblocksPlatform::importGPC($_POST['form_submit'],'integer');
		@$worker_email = DevblocksPlatform::importGPC($_POST['worker_email'],'string');
		@$worker_firstname = DevblocksPlatform::importGPC($_POST['worker_firstname'],'string');
		@$worker_lastname = DevblocksPlatform::importGPC($_POST['worker_lastname'],'string');
		@$worker_pass = DevblocksPlatform::importGPC($_POST['worker_pass'],'string');
		@$worker_pass2 = DevblocksPlatform::importGPC($_POST['worker_pass2'],'string');
		@$timezone = DevblocksPlatform::importGPC($_POST['timezone'],'string');

		$date = DevblocksPlatform::getDateService();
		
		$timezones = $date->getTimezones();
		$tpl->assign('timezones', $timezones);
		
		if(!empty($form_submit)) {
			// Persist form scope
			$tpl->assign('worker_firstname', $worker_firstname);
			$tpl->assign('worker_lastname', $worker_lastname);
			$tpl->assign('worker_email', $worker_email);
			$tpl->assign('worker_pass', $worker_pass);
			$tpl->assign('worker_pass2', $worker_pass2);
			$tpl->assign('timezone', $timezone);
			
			// Sanity/Error checking
			if(!empty($worker_email) && !empty($worker_pass) && $worker_pass == $worker_pass2) {
				// If we have no groups, make a Dispatch group
				$groups = DAO_Group::getAll(true);
				if(empty($groups)) {
					// Dispatch Group
					$dispatch_gid = DAO_Group::create(array(
						DAO_Group::NAME => 'Dispatch',
					));
					
					// Support Group
					$support_gid = DAO_Group::create(array(
						DAO_Group::NAME => 'Support',
					));

					// Sales Group
					$sales_gid = DAO_Group::create(array(
						DAO_Group::NAME => 'Sales',
					));
					
					// Default catchall
					DAO_Group::update($dispatch_gid, array(
						DAO_Group::IS_DEFAULT => 1
					));
				}

				// Default role
				$roles = DAO_WorkerRole::getAll();
				
				if(empty($roles)) {
					$fields = array(
						DAO_WorkerRole::NAME => 'Default',
						DAO_WorkerRole::PARAMS_JSON => json_encode(array(
							'who' => 'all',
							'what' => 'all',
						)),
					);
					DAO_WorkerRole::create($fields);
				}
				
				// If this worker doesn't exist, create them
				if(null == ($lookup = DAO_Worker::getByEmail($worker_email))) {
					$email_id = 0;
					
					// Add the worker e-mail to the addresses table
					if(!empty($worker_email))
						if(false != ($address = DAO_Address::lookupAddress($worker_email, true)))
							$email_id = $address->id;
					
					// [TODO] If the email address creation fails, report an error
					
					$fields = array(
						DAO_Worker::EMAIL_ID => $email_id,
						DAO_Worker::FIRST_NAME => 'Super',
						DAO_Worker::LAST_NAME => 'User',
						DAO_Worker::TITLE => 'Administrator',
						DAO_Worker::IS_SUPERUSER => 1,
						DAO_Worker::AUTH_EXTENSION_ID => 'login.password',
						DAO_Worker::TIME_FORMAT => 'D, d M Y h:i a',
						DAO_Worker::LANGUAGE => 'en_US',
					);
					
					if(!empty($worker_firstname)) {
						$fields[DAO_Worker::FIRST_NAME] = $worker_firstname;
						$fields[DAO_Worker::AT_MENTION_NAME] = $worker_firstname;
					}
					
					if(!empty($worker_lastname))
						$fields[DAO_Worker::LAST_NAME] = $worker_lastname;
					
					if(!empty($timezone))
						$fields[DAO_Worker::TIMEZONE] = $timezone;
					
					$worker_id = DAO_Worker::create($fields);
					
					DAO_Worker::setAuth($worker_id, $worker_pass);
					
					// Default group memberships
					
					if(!empty($dispatch_gid))
						DAO_Group::setGroupMember($dispatch_gid,$worker_id,true);
					if(!empty($support_gid))
						DAO_Group::setGroupMember($support_gid,$worker_id,true);
					if(!empty($sales_gid))
						DAO_Group::setGroupMember($sales_gid,$worker_id,true);
					
					// Create a default calendar
					
					if(!empty($worker_firstname))
						$label = sprintf("%s%s's Calendar", $worker_firstname, $worker_lastname ? (' ' . $worker_lastname) : '');
					else
						$label = 'My Calendar';
					
					$fields = array(
						DAO_Calendar::NAME => $label,
						DAO_Calendar::OWNER_CONTEXT => CerberusContexts::CONTEXT_WORKER,
						DAO_Calendar::OWNER_CONTEXT_ID => $worker_id,
						DAO_Calendar::PARAMS_JSON => json_encode(array(
							"manual_disabled" => "0",
							"sync_enabled" => "0",
							"start_on_mon" => "1",
							"hide_start_time" => "0",
							"color_available" => "#A0D95B",
							"color_busy" => "#C8C8C8",
							"series" => array(
								array("datasource"=>""),
								array("datasource"=>""),
								array("datasource"=>""),
							)
						)),
						DAO_Calendar::UPDATED_AT => time(),
					);
					$calendar_id = DAO_Calendar::create($fields);
					
					DAO_Worker::update($worker_id, array(
						DAO_Worker::CALENDAR_ID => $calendar_id,
					));
				}
				
				// Send a first ticket which allows people to reply for support
				$replyto_default = DAO_AddressOutgoing::getDefault();
				
				if(null != $replyto_default) {
					$message = new CerberusParserMessage();
						$message->headers['from'] = '"Webgroup Media, LLC." <support@webgroupmedia.com>';
						$message->headers['to'] = $replyto_default->email;
						$message->headers['subject'] = "Welcome to Cerb!";
						$message->headers['date'] = date('r');
						$message->headers['message-id'] = CerberusApplication::generateMessageId();
						$message->body = <<< EOF
Welcome to Cerb!

We automatically set up a few things for you during the installation process.

You'll notice that you have three groups:
* Dispatch: All your mail will be delivered to this group by default.
* Support: This is a group for holding tickets related to customer service.
* Sales: This is a group for holding tickets relates to sales.

If these default groups don't meet your needs, feel free to change them by clicking 'Setup' in the top-right and selecting the 'Groups' from the 'Workers and Groups' menu.

Simply reply to this message if you have any questions.  Our response will show up on this page as a new message.

For project news, training resources, sneak peeks of development progress, tips & tricks, and more:
 * http://www.facebook.com/cerbapp
 * http://twitter.com/cerb_app
 * https://vimeo.com/channels/cerb
 * http://cerbweb.com/book/latest/worker_guide/

Enjoy!
-- 
the Cerb team
Webgroup Media, LLC.
http://www.cerbweb.com/
EOF;
					CerberusParser::parseMessage($message);
				}
				
				$tpl->assign('step', STEP_REGISTER);
				$tpl->display('steps/redirect.tpl');
				exit;
				
			} else {
				$tpl->assign('failed', true);
				
			}
			
		} else {
			// Defaults
			
		}
		
		$tpl->assign('template', 'steps/step_defaults.tpl');
		
		break;
		
	case STEP_REGISTER:
		@$form_submit = DevblocksPlatform::importGPC($_POST['form_submit'],'integer');
		@$skip = DevblocksPlatform::importGPC($_POST['skip'],'integer',0);
		
		if(!empty($form_submit)) {
			@$contact_name = str_replace(array("\r","\n"),'',stripslashes($_REQUEST['contact_name']));
			@$contact_email = str_replace(array("\r","\n"),'',stripslashes($_REQUEST['contact_email']));
			@$contact_company = stripslashes($_REQUEST['contact_company']);
			
			if(empty($skip) && !empty($contact_name)) {
				@$contact_phone = stripslashes($_REQUEST['contact_phone']);
				@$contact_refer = stripslashes($_REQUEST['contact_refer']);
				@$q1 = stripslashes($_REQUEST['q1']);
				@$q2 = stripslashes($_REQUEST['q2']);
				@$q3 = stripslashes($_REQUEST['q3']);
				@$q4 = stripslashes($_REQUEST['q4']);
				@$q5 = stripslashes($_REQUEST['q5']);
				@$comments = stripslashes($_REQUEST['comments']);
				
				if(isset($_REQUEST['form_submit'])) {
				  $msg = sprintf(
				    "Contact Name: %s\r\n".
				    "Organization: %s\r\n".
				    "Referred by: %s\r\n".
				    "Phone: %s\r\n".
				    "\r\n".
				    "#1: Briefly, what does your organization do?\r\n%s\r\n\r\n".
				    "#2: How is your team currently handling e-mail management?\r\n%s\r\n\r\n".
				    "#3: Are you considering both free and commercial solutions?\r\n%s\r\n\r\n".
				    "#4: What will be your first important milestone?\r\n%s\r\n\r\n".
				    "#5: How many workers do you expect to use the helpdesk simultaneously?\r\n%s\r\n\r\n".
				    "\r\n".
				    "Additional Comments: \r\n%s\r\n\r\n"
				    ,
				    $contact_name,
				    $contact_company,
				    $contact_refer,
				    $contact_phone,
				    $q1,
				    $q2,
				    $q3,
				    $q4,
				    $q5,
				    $comments
				  );

				  CerberusMail::quickSend('aboutme@cerberusweb.com',"About: $contact_name of $contact_company",$msg, $contact_email, $contact_name);
				}
			}
			
			$tpl->assign('step', STEP_FINISHED);
			$tpl->display('steps/redirect.tpl');
			exit;
		}
		
		$tpl->assign('template', 'steps/step_register.tpl');
		break;
		
	case STEP_UPGRADE:
		$tpl->assign('template', 'steps/step_upgrade.tpl');
		break;
		
	// [TODO] Delete the /install/ directory (security)
	case STEP_FINISHED:
		
		// Set up the default cron jobs
		$crons = DevblocksPlatform::getExtensions('cerberusweb.cron', true);
		if(is_array($crons))
		foreach($crons as $id => $cron) { /* @var $cron CerberusCronPageExtension */
			switch($id) {
				case 'cron.mailbox':
					$cron->setParam(CerberusCronPageExtension::PARAM_ENABLED, true);
					$cron->setParam(CerberusCronPageExtension::PARAM_DURATION, '5');
					$cron->setParam(CerberusCronPageExtension::PARAM_TERM, 'm');
					$cron->setParam(CerberusCronPageExtension::PARAM_LASTRUN, strtotime('Today'));
					break;
				case 'cron.parser':
					$cron->setParam(CerberusCronPageExtension::PARAM_ENABLED, true);
					$cron->setParam(CerberusCronPageExtension::PARAM_DURATION, '1');
					$cron->setParam(CerberusCronPageExtension::PARAM_TERM, 'm');
					$cron->setParam(CerberusCronPageExtension::PARAM_LASTRUN, strtotime('Today'));
					break;
				case 'cron.maint':
					$cron->setParam(CerberusCronPageExtension::PARAM_ENABLED, true);
					$cron->setParam(CerberusCronPageExtension::PARAM_DURATION, '24');
					$cron->setParam(CerberusCronPageExtension::PARAM_TERM, 'h');
					$cron->setParam(CerberusCronPageExtension::PARAM_LASTRUN, strtotime('Yesterday'));
					break;
				case 'cron.heartbeat':
					$cron->setParam(CerberusCronPageExtension::PARAM_ENABLED, true);
					$cron->setParam(CerberusCronPageExtension::PARAM_DURATION, '5');
					$cron->setParam(CerberusCronPageExtension::PARAM_TERM, 'm');
					$cron->setParam(CerberusCronPageExtension::PARAM_LASTRUN, strtotime('Yesterday'));
					break;
			}
			
		}
		
		$tpl->assign('template', 'steps/step_finished.tpl');
		break;
}

// [TODO] Check apache rewrite (somehow)

$tpl->display('base.tpl');
