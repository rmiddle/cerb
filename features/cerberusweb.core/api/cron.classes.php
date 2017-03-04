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

/*
 * PARAMS (overloads):
 * parse_max=n (max tickets to parse)
 *
 */
class ParseCron extends CerberusCronPageExtension {
	function scanDirMessages($dir) {
		if(substr($dir,-1,1) != DIRECTORY_SEPARATOR) $dir .= DIRECTORY_SEPARATOR;
		$files = glob($dir . '*.msg');
		if ($files === false) return array();
		return $files;
	}

	function run() {
		$logger = DevblocksPlatform::getConsoleLog();
		
		$logger->info("[Parser] Starting Parser Task");
		
		if (!extension_loaded("imap")) {
			$logger->err("[Parser] The 'IMAP' extension is not loaded.  Aborting!");
			return false;
		}
		
		if (!extension_loaded("mailparse")) {
			$logger->err("[Parser] The 'mailparse' extension is not loaded.  Aborting!");
			return false;
		}
		
		if (!extension_loaded("mbstring")) {
			$logger->err("[Parser] The 'mbstring' extension is not loaded.  Aborting!");
			return false;
		}

		$timeout = ini_get('max_execution_time');
		$runtime = microtime(true);
		 
		// Allow runtime overloads (by host, etc.)
		@$opt_parse_max = DevblocksPlatform::importGPC($_REQUEST['parse_max'],'integer');
		
		$total = !empty($opt_parse_max) ? $opt_parse_max : $this->getParam('max_messages', 500);

		$mailDir = APP_MAIL_PATH . 'new' . DIRECTORY_SEPARATOR;
		$subdirs = glob($mailDir . '*', GLOB_ONLYDIR);
		if ($subdirs === false) $subdirs = array();
		$subdirs[] = $mailDir; // Add our root directory last

		$archivePath = sprintf("%sarchive/%04d/%02d/%02d/",
			APP_MAIL_PATH,
			date('Y'),
			date('m'),
			date('d')
		);
		
		if(defined('DEVELOPMENT_ARCHIVE_PARSER_MSGSOURCE') && DEVELOPMENT_ARCHIVE_PARSER_MSGSOURCE) {
			if(!file_exists($archivePath) && is_writable(APP_MAIL_PATH)) {
				if(false === mkdir($archivePath, 0755, true)) {
					$logger->error("[Parser] Can't write to the archive path: ". $archivePath. " ...skipping copy");
				}
			}
		}
		
		foreach($subdirs as $subdir) {
			if(!is_writable($subdir)) {
				$logger->error('[Parser] Write permission error, unable to parse messages inside: '. $subdir. " ...skipping");
				continue;
			}

			$files = $this->scanDirMessages($subdir);
			 
			foreach($files as $file) {
				$filePart = basename($file);

				if(defined('DEVELOPMENT_ARCHIVE_PARSER_MSGSOURCE') && DEVELOPMENT_ARCHIVE_PARSER_MSGSOURCE) {
					if(!copy($file, $archivePath.$filePart)) {
						//...
					}
				}
				
				if(!is_readable($file)) {
					$logger->error('[Parser] Read permission error, unable to parse ' . $file . " ...skipping");
					continue;
				}

				if(!is_writable($file)) {
					$logger->error('[Parser] Write permission error, unable to parse ' . $file . " ...skipping");
					continue;
				}
				
				$parseFile = sprintf("%s/fail/%s",
					APP_MAIL_PATH,
					$filePart
				);
				rename($file, $parseFile);
				
				$this->_parseFile($parseFile);

				if(--$total <= 0) break;
			}
			if($total <= 0) break;
		}
	  
		unset($files);
		unset($subdirs);
	  
		$logger->info("[Parser] Total Runtime: ".number_format((microtime(true)-$runtime)*1000,2)." ms");
	}

	function _parseFile($full_filename) {
		$logger = DevblocksPlatform::getConsoleLog('Parser');
		
		$fileparts = pathinfo($full_filename);
		$logger->info("Reading ".$fileparts['basename']."...");

		$time = microtime(true);

		$message = CerberusParser::parseMimeFile($full_filename);

		$time = microtime(true) - $time;
		$logger->info("Decoded! (".sprintf("%d",($time*1000))." ms)");

		$time = microtime(true);
		$ticket_id = CerberusParser::parseMessage($message);
		$time = microtime(true) - $time;
		
		$logger->info("Parsed! (".sprintf("%d",($time*1000))." ms) " .
			(!empty($ticket_id) ? ("(Ticket ID: ".$ticket_id.")") : ("(Local Delivery Rejected.)")));

		if(is_bool($ticket_id) && false === $ticket_id) {
			// Leave the message in storage/mail/fail
			$logger->error(sprintf("%s failed to parse and it has been saved to the storage/mail/fail/ directory.", $fileparts['basename']));
			
			// [TODO] Admin notification?
			
		} else {
			@unlink($full_filename);
			$logger->info("The message source has been removed.");
		}
	}

	function configure($instance) {
		$tpl = DevblocksPlatform::getTemplateService();

		$tpl->assign('max_messages', $this->getParam('max_messages', 500));

		$tpl->display('devblocks:cerberusweb.core::cron/parser/config.tpl');
	}

	function saveConfigurationAction() {
		@$max_messages = DevblocksPlatform::importGPC($_POST['max_messages'],'integer');
		$this->setParam('max_messages', $max_messages);
	}
};

/*
 * PARAMS (overloads):
 * maint_max_deletes=n (max tickets to purge)
 *
 */
// [TODO] Clear idle temp files (fileatime())
class MaintCron extends CerberusCronPageExtension {
	function run() {
		$logger = DevblocksPlatform::getConsoleLog();
		
		$logger->info("[Maint] Starting Maintenance Task");
		
		$db = DevblocksPlatform::getDatabaseService();

		// Platform
		DAO_Platform::maint();
		
		// Purge expired sessions
		Cerb_DevblocksSessionHandler::gc(0);
		
		// Purge Deleted Content
		$purge_waitdays = intval($this->getParam('purge_waitdays', 7));
		$purge_waitsecs = time() - (intval($purge_waitdays) * 86400);

		$sql = sprintf("DELETE FROM ticket ".
			"WHERE status_id = %d ".
			"AND updated_date < %d ",
			Model_Ticket::STATUS_DELETED,
			$purge_waitsecs
		);
		$db->ExecuteMaster($sql);
		
		$logger->info("[Maint] Purged " . $db->Affected_Rows() . " ticket records.");

		// Give plugins a chance to run maintenance (nuke NULL rows, etc.)
		$eventMgr = DevblocksPlatform::getEventService();
		$eventMgr->trigger(
			new Model_DevblocksEvent(
				'cron.maint',
				array()
			)
		);
		
		// Nuke orphaned words from the Bayes index
		// [TODO] Make this configurable from job
		$sql = "DELETE FROM bayes_words WHERE nonspam + spam < 2"; // only 1 occurrence
		$db->ExecuteMaster($sql);

		$logger->info('[Maint] Purged ' . $db->Affected_Rows() . ' obscure spam words.');
		
		// [mdf] Remove any empty directories inside storage/mail/new
		$mailDir = APP_MAIL_PATH . 'new' . DIRECTORY_SEPARATOR;
		$subdirs = glob($mailDir . '*', GLOB_ONLYDIR);
		if ($subdirs !== false) {
			foreach($subdirs as $subdir) {
				$directory_empty = count(glob($subdir. DIRECTORY_SEPARATOR . '*')) === 0;
				if($directory_empty && is_writeable($subdir)) {
					rmdir($subdir);
				}
			}
		}
		
		$logger->info('[Maint] Cleaned up mail directories.');
	  
		// [JAS] Remove any empty directories inside storage/import/new
		$importNewDir = APP_STORAGE_PATH . '/import/new' . DIRECTORY_SEPARATOR;
		$subdirs = glob($importNewDir . '*', GLOB_ONLYDIR);
		if ($subdirs !== false) {
			foreach($subdirs as $subdir) {
				$directory_empty = count(glob($subdir. DIRECTORY_SEPARATOR . '*')) === 0;
				if($directory_empty && is_writeable($subdir)) {
					rmdir($subdir);
				}
			}
		}
		$logger->info('[Maint] Cleaned up import directories.');
		
		// Clean up /tmp/php* files if ctime > 12 hours ago
		
		$tmp_dir = APP_TEMP_PATH . DIRECTORY_SEPARATOR;
		$tmp_deletes = 0;
		$tmp_ctime_max = time() - (60*60*12);
		
		if(false !== ($php_tmpfiles = glob($tmp_dir . 'php*', GLOB_NOSORT))) {
			// If created more than 12 hours ago
			foreach($php_tmpfiles as $php_tmpfile) {
				if(filectime($php_tmpfile) < $tmp_ctime_max) {
					unlink($php_tmpfile);
					$tmp_deletes++;
				}
			}
			
			$logger->info(sprintf('[Maint] Cleaned up %d temporary PHP files.', $tmp_deletes));
		}
		
		// Clean up /tmp/mime* files if ctime > 12 hours ago
		
		$tmp_dir = APP_TEMP_PATH . DIRECTORY_SEPARATOR;
		$tmp_deletes = 0;
		$tmp_ctime_max = time() - (60*60*12);
		
		if(false !== ($php_tmpmimes = glob($tmp_dir . 'mime*', GLOB_NOSORT))) {
			foreach($php_tmpmimes as $php_tmpmime) {
				// If created more than 12 hours ago
				if(filectime($php_tmpmime) < $tmp_ctime_max) {
					unlink($php_tmpmime);
					$tmp_deletes++;
				}
			}
			
			$logger->info(sprintf('[Maint] Cleaned up %d temporary MIME files.', $tmp_deletes));
		}
		
	}

	function configure($instance) {
		$tpl = DevblocksPlatform::getTemplateService();

		$tpl->assign('purge_waitdays', $this->getParam('purge_waitdays', 7));

		$tpl->display('devblocks:cerberusweb.core::cron/maint/config.tpl');
	}

	function saveConfigurationAction() {
		@$purge_waitdays = DevblocksPlatform::importGPC($_POST['purge_waitdays'],'integer');
		$this->setParam('purge_waitdays', $purge_waitdays);
	}
};

/**
 * Plugins can implement an event listener on the heartbeat to do any kind of
 * time-dependent or interval-based events.  For example, doing a workflow
 * action every 5 minutes.
 */
class HeartbeatCron extends CerberusCronPageExtension {
	function run() {
		// Heartbeat Event
		$eventMgr = DevblocksPlatform::getEventService();
		$eventMgr->trigger(
			new Model_DevblocksEvent(
				'cron.heartbeat',
				array(
				)
			)
		);
	}

	function configure($instance) {
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->display('devblocks:cerberusweb.core::cron/heartbeat/config.tpl');
	}
};

class ImportCron extends CerberusCronPageExtension {
	function run() {
		$logger = DevblocksPlatform::getConsoleLog();
		
		$logger->info("[Importer] Starting Import Task");
		
		@set_time_limit(1200); // 20m
		 
		$importNewDir = APP_STORAGE_PATH . '/import/new/';
		$importFailDir = APP_STORAGE_PATH . '/import/fail/';

		if(!is_writable($importNewDir)) {
			$logger->error("[Importer] Unable to write in '$importNewDir'.  Please check permissions.");
			return;
		}

		if(!is_writable($importFailDir)) {
			$logger->error("[Importer] Unable to write in '$importFailDir'.  Please check permissions.");
			return;
		}

		if (!extension_loaded("imap")) {
			$logger->err("[Parser] The 'IMAP' extension is not loaded.  Aborting!");
			return false;
		}
		
		if (!extension_loaded("mailparse")) {
			$logger->err("[Parser] The 'mailparse' extension is not loaded.  Aborting!");
			return false;
		}
		
		$limit = 100; // [TODO] Set from config

		$runtime = microtime(true);

		$subdirs = glob($importNewDir . '*', GLOB_ONLYDIR);
		if ($subdirs === false) $subdirs = array();
		$subdirs[] = $importNewDir; // Add our root directory last

		foreach($subdirs as $subdir) {
			if(!is_writable($subdir)) {
				$logger->error('[Importer] Write permission error, unable parse imports inside: '. $subdir. "...skipping");
				continue;
			}

			$files = $this->scanDirMessages($subdir);
			 
			foreach($files as $file) {
				// If we can't nuke the file, there's no sense in trying to import it
				if(!is_writeable($file))
					continue;

				// Preventatively move into the fail dir while we parse
				$move_to_dir = $importFailDir . basename($subdir) . '/';

				if(!file_exists($move_to_dir))
					mkdir($move_to_dir,0770,true);

				$dest_file = $move_to_dir . basename($file);
				@rename($file, $dest_file);
				$file = $dest_file;
				
				// Parse the XML
				if(!@$xml_root = simplexml_load_file($file, 'SimpleXMLElement', LIBXML_PARSEHUGE)) { /* @var $xml_root SimpleXMLElement */
					$logger->error("[Importer] Error parsing XML file: " . $file);
					continue;
				}
				
				if(empty($xml_root)) {
					$logger->error("[Importer] XML root element doesn't exist in: " . $file);
					continue;
				}
				
				$object_type = $xml_root->getName();

				$file_part = basename($file);
				
				$logger->info("[Importer] Reading ".$file_part." ... ($object_type)");

				if($this->_handleImport($object_type, $xml_root)) { // Success
					@unlink($file);
				}
				 
				if(--$limit <= 0)
				break;
			}
				
			if($limit <= 0)
			break;
		}
	  
		unset($files);
		unset($subdirs);

		$logger->info("[Importer] Total Runtime: ".number_format((microtime(true)-$runtime)*1000,2)." ms");
		
		@imap_errors();
	}

	private function _handleImport($object_type, $xml) {
		// [TODO] Import extensions (delegate to plugins)
		switch($object_type) {
		 	case 'comment':
		 		return $this->_handleImportComment($xml);
		 		break;
		 	case 'kbarticle':
		 		return $this->_handleImportKbArticle($xml);
		 		break;
		 	case 'ticket':
		 		return $this->_handleImportTicket($xml);
		 		break;
		 	case 'worker':
		 		return $this->_handleImportWorker($xml);
		 		break;
		 	case 'organization':
		 		return $this->_handleImportOrg($xml);
		 		break;
		 	case 'contact':
		 		return $this->_handleImportAddress($xml);
		 		break;
		 	default:
		 		break;
		 }
	}
	
	/* _handleImportKbArticle */
	private function _getCategoryChildByName($list, $node, $name) {
		if($node instanceof Traversable)
		foreach($node as $child_id => $null) {
			if(isset($list[$child_id]) && 0 == strcasecmp($list[$child_id]->name,$name))
				return $child_id;
		}
		
		return NULL;
	}
	
	private function _handleImportKbArticle($xml) {
		static $categoryList = NULL;
		static $categoryMap = NULL;
		
		$title = (string) $xml->title;
		$created = intval((string) $xml->created_date);
		$content_b64 = (string) $xml->content;

		// Bad file
		if(empty($content_b64) || empty($title)) {
			return false;
		}

		if(NULL == $categoryMap || NULL == $categoryList) {
			$categoryList = DAO_KbCategory::getAll();
			$categoryMap = DAO_KbCategory::getTreeMap();
		}
		
		// Handle multiple <categories> elements
		$categoryIds = array();
		foreach($xml->categories as $eCategories) {
			$pid = 0;
			$ptr =& $categoryMap[$pid];
			$categoryId = 0;
			
			foreach($eCategories->category as $eCategory) {
				$catName = (string) $eCategory;
				
				if(NULL == ($categoryId = $this->_getCategoryChildByName($categoryList, $ptr, $catName))) {
					$fields = array(
						DAO_KbCategory::NAME => $catName,
						DAO_KbCategory::PARENT_ID => $pid,
					);
					$categoryId = DAO_KbCategory::create($fields);
					
					$categoryList[$categoryId] = DAO_KbCategory::get($categoryId);
					
					if(!isset($categoryMap[$pid]))
						$categoryMap[$pid] = array();
						
					$categoryMap[$pid][$categoryId] = 0;
					$categoryMap[$categoryId] = array();
					$categoryIds[] = $categoryId;
					
				} else {
					$categoryIds[] = $categoryId;
					
				}
				
				$pid = $categoryId;
				$ptr =& $categoryMap[$categoryId];
			}
		}
		
		// Decode content
		$content = base64_decode($content_b64);

		// [TODO] Dupe check?  (title in category)
		
		$fields = array(
			DAO_KbArticle::TITLE => $title,
			DAO_KbArticle::UPDATED => $created,
			DAO_KbArticle::FORMAT => 1, // HTML
			DAO_KbArticle::CONTENT => $content,
			DAO_KbArticle::VIEWS => 0, // [TODO]
		);

		if(null !== ($articleId = DAO_KbArticle::create($fields))) {
			DAO_KbArticle::setCategories($articleId, $categoryIds, false);
			return true;
		}
		
		return false;
	}
	
	// [TODO] Move to an extension
	private function _handleImportTicket($xml) {
		$settings = DevblocksPlatform::getPluginSettingsService();
		$logger = DevblocksPlatform::getConsoleLog();
		$workers = DAO_Worker::getAll();

		static $email_to_worker_id = null;
		static $group_name_to_id = null;
		static $bucket_name_to_id = null;
		
		// Hash Workers so we can ID their incoming tickets
		if(null == $email_to_worker_id) {
			$email_to_worker_id = array();
			
			if(is_array($workers))
			foreach($workers as $worker) { /* @var $worker Model_Worker */
				$email_to_worker_id[DevblocksPlatform::strLower($worker->getEmailString())] = intval($worker->id);
			}
		}
		
		// Hash Group names
		if(null == $group_name_to_id) {
			$groups = DAO_Group::getAll();
			$group_name_to_id = array();

			if(is_array($groups))
			foreach($groups as $group) {
				$group_name_to_id[DevblocksPlatform::strLower($group->name)] = intval($group->id);
			}
		}
		
		// Hash Bucket names
		if(null == $bucket_name_to_id) {
			$buckets = DAO_Bucket::getAll();
			$bucket_name_to_id = array();

			if(is_array($buckets))
			foreach($buckets as $bucket) { /* @var $bucket Model_Bucket */
				// Hash by group ID and bucket name
				$hash = md5($bucket->group_id . DevblocksPlatform::strLower($bucket->name));
				$bucket_name_to_id[$hash] = intval($bucket->id);
			}
		}
		
		$sMask = (string) $xml->mask;
		$sSubject = substr((string) $xml->subject,0,255);
		$sGroup = (string) $xml->group;
		$sBucket = (string) $xml->bucket;
		$sOrg = (string) $xml->org;
		$iCreatedDate = (integer) $xml->created_date;
		$iUpdatedDate = (integer) $xml->updated_date;
		$sStatus = (string) $xml->status;
		
		if(empty($sMask)) {
			$sMask = CerberusApplication::generateTicketMask();
		}
		
		// Find the destination Group + Bucket (or create them)
		if(empty($sGroup)) {
			$iDestGroupId = 0;
			
			if(null != ($iDestGroup = DAO_Group::getDefaultGroup()))
				$iDestGroupId = $iDestGroup->id;
			
		} elseif(null == ($iDestGroupId = @$group_name_to_id[DevblocksPlatform::strLower($sGroup)])) {
			$iDestGroupId = DAO_Group::create(array(
				DAO_Group::NAME => $sGroup,
			));
			
			// Give all superusers manager access to this new group
			if(is_array($workers))
			foreach($workers as $worker) {
				if($worker->is_superuser)
					DAO_Group::setGroupMember($iDestGroupId,$worker->id,true);
			}
			
			// Rehash
			$groups = DAO_Group::getAll(true);
			$group = $groups[$iDestGroupId];
			$group_name_to_id[DevblocksPlatform::strLower($sGroup)] = $iDestGroupId;
			
			foreach($group->getBuckets() as $bucket_id => $bucket) {
				$hash = md5($bucket->group_id . DevblocksPlatform::strLower($bucket->name));
				$bucket_name_to_id[$hash] = $bucket_id;
			}
		}
		
		if(empty($sBucket)) {
			// Get the default bucket id for this group
			$destGroup = DAO_Group::get($iDestGroupId);
			$iDestBucket = $destGroup->getDefaultBucket();
			$iDestBucketId = $iDestBucket->id;
			
		} elseif(null == ($iDestBucketId = @$bucket_name_to_id[md5($iDestGroupId.DevblocksPlatform::strLower($sBucket))])) {
			$fields = array(
				DAO_Bucket::NAME => $sBucket,
				DAO_Bucket::GROUP_ID => $iDestGroupId,
				DAO_Bucket::IS_DEFAULT => 0,
				DAO_Bucket::UPDATED_AT => time(),
			);
			$iDestBucketId = DAO_Bucket::create($fields);
			
			// Rehash
			DAO_Bucket::getAll(true);
			$hash = md5($iDestGroupId . DevblocksPlatform::strLower($sBucket));
			$bucket_name_to_id[$hash] = $iDestBucketId;
		}
			
		// Org
		$iOrgId = 0;
		if(!empty($sOrg)) {
			$iOrgId = DAO_ContactOrg::lookup($sOrg, true);
		}
		
		// Xpath the first and last "from" out of "/ticket/messages/message/headers/from"
		$aMessageNodes = $xml->xpath("/ticket/messages/message");
		$iNumMessages = count($aMessageNodes);

		// Dupe check by ticket mask
		if(null != DAO_Ticket::getTicketByMask($sMask)) {
			$logger->warning("[Importer] Ticket mask '" . $sMask . "' already exists.  Making it unique.");
			
			$uniqueness = 1;
			$origMask = $sMask;
			
			// Append new uniqueness to the ticket mask:  LLL-NNNNN-NNN-1, LLL-NNNNN-NNN-2, ...
			do {
				$sMask = $origMask . '-' . ++$uniqueness;
			} while(null != DAO_Ticket::getTicketIdByMask($sMask));
			
			$logger->info("[Importer] The unique mask for '".$origMask."' is now '" . $sMask . "'");
		}
		
		$statusId = 0;
		
		switch(DevblocksPlatform::strLower($sStatus)) {
			case 'waiting':
				$statusId = 1;
				break;
			case 'closed':
				$statusId = 2;
				break;
			case 'deleted':
				$statusId = 3;
				break;
		}
		
		// Create ticket
		$fields = array(
			DAO_Ticket::MASK => $sMask,
			DAO_Ticket::SUBJECT => $sSubject,
			DAO_Ticket::STATUS_ID => intval($statusId),
			DAO_Ticket::NUM_MESSAGES => $iNumMessages,
			DAO_Ticket::ORG_ID => $iOrgId,
			DAO_Ticket::CREATED_DATE => $iCreatedDate,
			DAO_Ticket::UPDATED_DATE => $iUpdatedDate,
			DAO_Ticket::GROUP_ID => intval($iDestGroupId),
			DAO_Ticket::BUCKET_ID => intval($iDestBucketId),
			DAO_Ticket::IMPORTANCE => 50,
		);
		$ticket_id = DAO_Ticket::create($fields);

		// Create requesters
		if(!is_null($xml->requesters))
		foreach($xml->requesters->address as $eAddress) { /* @var $eAddress SimpleXMLElement */
			$sRequesterAddy = (string) $eAddress; // [TODO] RFC822
			
			// Insert requesters
			DAO_Ticket::createRequester($sRequesterAddy, $ticket_id);
		}
		
		$first_message_id = 0;
		$first_wrote_id = 0;
		$first_outgoing_message_id = 0;
		$last_message_id = 0;
		$last_wrote_id = 0;
		
		// Create messages
		if(!is_null($xml->messages)) {
			$count_messages = count($xml->messages->message);
			$seek_messages = 1;
			foreach($xml->messages->message as $eMessage) { /* @var $eMessage SimpleXMLElement */
				$iIsOutgoing = (integer) $eMessage->is_outgoing;
				
				$eHeaders =& $eMessage->headers; /* @var $eHeaders SimpleXMLElement */
				$rawHeaders = trim((string) $eHeaders);
				
				// If we only have itemized headers, convert them back into raw
				if(empty($rawHeaders)) {
					$rawHeaders = '';
				
					foreach($eHeaders->children() as $eHeader) { /* @var $eHeader SimpleXMLElement */
						$header_key = DevblocksPlatform::strLower($eHeader->getName());
						$header_val = (string) $eHeader;
						$rawHeaders .= $header_key . ': ' . $header_val . "\r\n";
					}
				}
				
				// Parse raw headers
				$headers = DAO_MessageHeaders::parse($rawHeaders, true, false);
				
				@$sMsgFrom = $headers['from'];
				$sMsgDate = @$headers['date'] ?: date('r');
				
				$sMsgFrom = self::_parseRfcAddressList($sMsgFrom, true);
				
				if(NULL == $sMsgFrom) {
					$logger->warning('[Importer] Ticket ' . $sMask . ' - Invalid message sender: ' . $sMsgFrom . ' (skipping)');
					continue;
				}
				
				if(null == ($msgFromInst = CerberusApplication::hashLookupAddress($sMsgFrom, true))) {
					$logger->warning('[Importer] Ticket ' . $sMask . ' - Invalid message sender: ' . $sMsgFrom . ' (skipping)');
					continue;
				}
	
				@$msgWorkerId = intval($email_to_worker_id[DevblocksPlatform::strLower($msgFromInst->email)]);
				
				$fields = array(
					DAO_Message::TICKET_ID => $ticket_id,
					DAO_Message::CREATED_DATE => intval(strtotime($sMsgDate)),
					DAO_Message::ADDRESS_ID => $msgFromInst->id,
					DAO_Message::IS_OUTGOING => $iIsOutgoing,
					DAO_Message::HASH_HEADER_MESSAGE_ID => isset($headers['message-id']) ? sha1(@$headers['message-id']) : '',
					DAO_Message::WORKER_ID => !empty($msgWorkerId) ? $msgWorkerId : 0,
				);
				$email_id = DAO_Message::create($fields);
				
				if(empty($first_outgoing_message_id) && $iIsOutgoing)
					$first_outgoing_message_id = $email_id;
				
				// First thread
				if(1==$seek_messages) {
					$first_message_id = $email_id;
					$first_wrote_id = $msgFromInst->id;
				}
				
				// Last thread
				if($count_messages==$seek_messages) {
					$last_message_id = $email_id;
					$last_wrote_id = $msgFromInst->id;
				}
				
				// Create attachments
				if(!is_null($eMessage->attachments) && $eMessage->attachments instanceof Traversable)
				if($eMessage->attachments->attachment instanceof Traversable)
				foreach($eMessage->attachments->attachment as $eAttachment) { /* @var $eAttachment SimpleXMLElement */
					$sFileName = (string) $eAttachment->name;
					$sMimeType = (string) $eAttachment->mimetype;
					$sFileSize = (integer) $eAttachment->size;
					$sFileContentB64 = (string) $eAttachment->content;
					
					// [TODO] This could be a little smarter about detecting extensions
					if(empty($sMimeType))
						$sMimeType = "application/octet-stream";
					
					$sFileContent = base64_decode($sFileContentB64);
					unset($sFileContentB64);
					
					// Dupe detection
					$sha1_hash = sha1($sFileContent, false);
					
					if(false == ($file_id = DAO_Attachment::getBySha1Hash($sha1_hash, $sFileName))) {
						$fields = array(
							DAO_Attachment::NAME => $sFileName,
							DAO_Attachment::MIME_TYPE => $sMimeType,
							DAO_Attachment::STORAGE_SHA1HASH => $sha1_hash,
						);
						
						$file_id = DAO_Attachment::create($fields);
						
						// Write file to storage
						Storage_Attachments::put($file_id, $sFileContent);
					}

					if(!empty($file_id))
						DAO_Attachment::setLinks(CerberusContexts::CONTEXT_MESSAGE, $email_id, $file_id);
					
					unset($sFileContent);
				}
				
				// Create message content
				$sMessageContentB64 = (string) $eMessage->content;
				$sMessageContent = base64_decode($sMessageContentB64);
				
				// Content-type specific handling
				if(isset($eMessage->content['content-type'])) { // do we have a content-type?
					if(DevblocksPlatform::strLower($eMessage->content['content-type']) == 'html') { // html?
						// Force to plaintext part
						$sMessageContent = DevblocksPlatform::stripHTML($sMessageContent);
					}
				}
				unset($sMessageContentB64);
				
				Storage_MessageContent::put($email_id, $sMessageContent);
				unset($sMessageContent);
	
				// Headers
				
				DAO_MessageHeaders::upsert($email_id, $rawHeaders);
				
				$seek_messages++;
			}
			
			// Update ticket message meta
			DAO_Ticket::update($ticket_id, array(
				DAO_Ticket::FIRST_MESSAGE_ID => $first_message_id,
				DAO_Ticket::FIRST_WROTE_ID => $first_wrote_id,
				DAO_Ticket::FIRST_OUTGOING_MESSAGE_ID => $first_outgoing_message_id,
				DAO_Ticket::LAST_MESSAGE_ID => $last_message_id,
				DAO_Ticket::LAST_WROTE_ID => $last_wrote_id,
			), false);
		}
		
		// Create comments
		$default_sender = DAO_AddressOutgoing::getDefault();
		
		if(!is_null($xml->comments->comment) && $xml->comments->comment instanceof Traversable)
		foreach($xml->comments->comment as $eComment) { /* @var $eMessage SimpleXMLElement */
			$iCommentDate = (integer) $eComment->created_date;
			$sCommentAuthor = (string) $eComment->author;
			
			$sCommentTextB64 = (string) $eComment->content;
			$sCommentText = base64_decode($sCommentTextB64);
			unset($sCommentTextB64);
			
			if(empty($sCommentAuthor) || false == ($commentAuthorInst = CerberusApplication::hashLookupAddress($sCommentAuthor, true))) {
				$iCommentAuthorId = $default_sender->address_id;
			} else {
				$iCommentAuthorId = $commentAuthorInst->id;
			}
			
			if(empty($iCommentAuthorId))
				continue;
			
			$fields = array(
				DAO_Comment::CONTEXT => CerberusContexts::CONTEXT_TICKET,
				DAO_Comment::CONTEXT_ID => intval($ticket_id),
				DAO_Comment::CREATED => intval($iCommentDate),
				DAO_Comment::OWNER_CONTEXT => CerberusContexts::CONTEXT_ADDRESS,
				DAO_Comment::OWNER_CONTEXT_ID => intval($iCommentAuthorId),
				DAO_Comment::COMMENT => $sCommentText,
			);
			$comment_id = DAO_Comment::create($fields);
			
			unset($sCommentText);
		}
		
		$logger->info('[Importer] Imported ticket #'.$ticket_id);
		
		return true;
	}
	
	private function _parseRfcAddressList($addressStr, $only_one) {
			// Need to parse the 'From' header as RFC-2822: "name" <user@domain.com>
			@$rfcAddressList = imap_rfc822_parse_adrlist($addressStr, 'host');
			
			if(!is_array($rfcAddressList) || empty($rfcAddressList))
				return NULL;
			
			$addresses = array();
			foreach($rfcAddressList as $rfcAddress) {
				if(empty($rfcAddress->host) || $rfcAddress->host == 'host') {
					continue;
				}
				$addresses[] =  trim(DevblocksPlatform::strLower($rfcAddress->mailbox.'@'.$rfcAddress->host));
			}
			
			if(empty($addresses)) {
				return NULL;
			}
			
			$result = ($only_one) ? $addresses[0] : $addresses;
			return $result;
	}

	private function _handleImportWorker($xml) {
		$settings = DevblocksPlatform::getPluginSettingsService();
		$logger = DevblocksPlatform::getConsoleLog();

		$sFirstName = (string) $xml->first_name;
		$sLastName = (string) $xml->last_name;
		$sEmail = (string) $xml->email;
		$sPassword = (string) $xml->password;
		$isSuperuser = (integer) $xml->is_superuser;
		
		// Dupe check worker email
		if(null != ($worker = DAO_Worker::getByEmail($sEmail))) {
			$logger->info('[Importer] Avoiding creating duplicate worker #'.$worker->id.' ('.$sEmail.')');
			return true;
		}
		
		if(false == ($addy_model = DAO_Address::getByEmail($sEmail)))
			return false;
		
		$fields = array(
			DAO_Worker::EMAIL_ID => $addy_model->id,
			DAO_Worker::FIRST_NAME => $sFirstName,
			DAO_Worker::LAST_NAME => $sLastName,
			DAO_Worker::IS_SUPERUSER => intval($isSuperuser),
			DAO_Worker::AUTH_EXTENSION_ID => 'login.password',
		);
		$worker_id = DAO_Worker::create($fields);
		
		// Set pasword auth, if exists
		if(!empty($sPassword))
			DAO_Worker::setAuth($worker_id, $sPassword, true);
		
		$logger->info('[Importer] Imported worker #'.$worker_id.' ('.$sEmail.')');
		
		DAO_Worker::clearCache();
		
		return true;
	}

	private function _handleImportOrg($xml) {
		$settings = DevblocksPlatform::getPluginSettingsService();
		$logger = DevblocksPlatform::getConsoleLog();

		$sName = (string) $xml->name;
		$sStreet = (string) $xml->street;
		$sCity = (string) $xml->city;
		$sProvince = (string) $xml->province;
		$sPostal = (string) $xml->postal;
		$sCountry = (string) $xml->country;
		$sPhone = (string) $xml->phone;
		$sWebsite = (string) $xml->website;
		
		// Dupe check org
		if(null != ($org_id = DAO_ContactOrg::lookup($sName))) {
			$logger->info('[Importer] Avoiding creating duplicate org #'.$org_id.' ('.$sName.')');
			return true;
		}
		
		$fields = array(
			DAO_ContactOrg::NAME => $sName,
			DAO_ContactOrg::STREET => $sStreet,
			DAO_ContactOrg::CITY => $sCity,
			DAO_ContactOrg::PROVINCE => $sProvince,
			DAO_ContactOrg::POSTAL => $sPostal,
			DAO_ContactOrg::COUNTRY => $sCountry,
			DAO_ContactOrg::PHONE => $sPhone,
			DAO_ContactOrg::WEBSITE => $sWebsite,
		);
		$org_id = DAO_ContactOrg::create($fields);
		
		$logger->info('[Importer] Imported org #'.$org_id.' ('.$sName.')');
		
		return true;
	}

	private function _handleImportAddress($xml) {
		$settings = DevblocksPlatform::getPluginSettingsService();
		$logger = DevblocksPlatform::getConsoleLog();

		$sFirstName = (string) $xml->first_name;
		$sLastName = (string) $xml->last_name;
		$sEmail = (string) $xml->email;
		$sPassword = (string) $xml->password;
		$sOrganization = (string) $xml->organization;
		
		$addy_exists = false;
		// Dupe check org
		if(null != ($address = DAO_Address::lookupAddress($sEmail))) {
			$logger->info('[Importer] Avoiding creating duplicate contact #'.$address->id.' ('.$sEmail.')');
			$addy_exists = true;
			$address_id = $address->id;
		}
		
		if(!$addy_exists) {
			$fields = array(
				DAO_Address::EMAIL => $sEmail,
			);
			$address_id = DAO_Address::create($fields);
		}
		
		if(!empty($sPassword)) {
			if(null == ($contact = DAO_Contact::getWhere(sprintf("%s = %d", DAO_Contact::PRIMARY_EMAIL_ID, $address_id)))) {
				$salt = CerberusApplication::generatePassword(8);
				$fields = array(
					DAO_Contact::PRIMARY_EMAIL_ID => $address_id,
					DAO_Contact::FIRST_NAME => $sFirstName,
					DAO_Contact::LAST_NAME => $sLastName,
					DAO_Contact::LAST_LOGIN_AT => time(),
					DAO_Contact::CREATED_AT => time(),
					DAO_Contact::AUTH_SALT => $salt,
					DAO_Contact::AUTH_PASSWORD => md5($salt.$sPassword)
				);
				
				$contact_id = DAO_Contact::create($fields);
				
				DAO_Address::update($address_id, array(
					DAO_Address::CONTACT_ID => $contact_id
				));
				$logger->info('[Importer] Imported contact '. $sEmail);
			}
		}
		
		// Associate with organization
		if(!empty($sOrganization)) {
			if(null != ($org_id = DAO_ContactOrg::lookup($sOrganization, true))) {
				DAO_Address::update($address_id, array(
					DAO_Address::CONTACT_ORG_ID => $org_id
				));
				$logger->info('[Importer] Associated address '.$sEmail.' with org '.$sOrganization);
			}
		}
		
		$logger->info('[Importer] Imported address #'.$address_id.' ('.$sEmail.')');
		
		return true;
	}
	
	// [TODO] Move to an extension
	private function _handleImportComment($xml) {
		$mask = (string) $xml->mask;
		$author_email = (string) $xml->author_email;
		$note = trim((string) $xml->note);
		$created = intval((string) $xml->created_date);

		$author_address = CerberusApplication::hashLookupAddress($author_email,true);

		// Bad file
		if(empty($note) || empty($author_address) || empty($mask)) {
			return false;
		}

		if(null !== ($ticket = DAO_Ticket::getTicketByMask($mask))) {
			$fields = array(
				DAO_Comment::CREATED => $created,
				DAO_Comment::CONTEXT => CerberusContexts::CONTEXT_TICKET,
				DAO_Comment::CONTEXT_ID => $ticket->id,
				DAO_Comment::COMMENT => $note,
				DAO_Comment::OWNER_CONTEXT => CerberusContexts::CONTEXT_ADDRESS,
				DAO_Comment::OWNER_CONTEXT_ID => $author_address->id,
			);
			
			if(null !== ($comment_id = DAO_Comment::create($fields)))
				return true;
		}
		
		return false;
	}

	function scanDirMessages($dir) {
		if(substr($dir,-1,1) != DIRECTORY_SEPARATOR) $dir .= DIRECTORY_SEPARATOR;
		$files = glob($dir . '*.xml');
		if ($files === false) return array();
		return $files;
	}

	function configure($instance) {
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->display('devblocks:cerberusweb.core::cron/import/config.tpl');
	}
};

/*
 * PARAMS (overloads):
 * mailbox_max=n (max messages to download at once)
 *
 */
class MailboxCron extends CerberusCronPageExtension {
	function run() {
		$logger = DevblocksPlatform::getConsoleLog();
		
		$logger->info("[Mailboxes] Started Mailbox Checker job");
		
		if (!extension_loaded("imap")) {
			$logger->err("[Mailboxes] The 'IMAP' extension is not loaded. Aborting!");
			return false;
		}
		
		if (!extension_loaded("mailparse")) {
			$logger->err("[Mailboxes] The 'mailparse' extension is not loaded. Aborting!");
			return false;
		}
		
		@set_time_limit(600); // 10m

		if(false == ($accounts = DAO_Mailbox::getAll())) {
			$logger->err("[Mailboxes] There are no mailboxes to check. Aborting!");
			return false;
		}
		
		// Sort by the least recently checked mailbox
		DevblocksPlatform::sortObjects($accounts, 'checked_at');
		
		$timeout = ini_get('max_execution_time');
		
		// Allow runtime overloads (by host, etc.)
		@$opt_max_messages = DevblocksPlatform::importGPC($_REQUEST['max_messages'],'integer');
		@$opt_max_mailboxes = DevblocksPlatform::importGPC($_REQUEST['max_mailboxes'],'integer');
		
		$max_downloads = !empty($opt_max_messages) ? $opt_max_messages : $this->getParam('max_messages', (($timeout) ? 20 : 50));
		
		// [JAS]: Make sure our output directory is writeable
		if(!is_writable(APP_MAIL_PATH . 'new' . DIRECTORY_SEPARATOR)) {
			$logger->error("[Mailboxes] The mail storage directory is not writeable.  Skipping mailbox download.");
			return;
		}

		$runtime = microtime(true);
		$mailboxes_checked = 0;
		
		if(is_array($accounts))
		foreach ($accounts as $account) { /* @var $account Model_Mailbox */
			if(!$account->enabled)
				continue;
			
			if($account->delay_until > time()) {
				$logger->info(sprintf("[Mailboxes] Delaying failing mailbox '%s' check for %d more seconds (%s)", $account->name, $account->delay_until - time(), date("h:i a", $account->delay_until)));
				continue;
			}
			
			if($opt_max_mailboxes && $mailboxes_checked >= $opt_max_mailboxes) {
				$logger->info(sprintf("[Mailboxes] We're limited to checking %d mailboxes per invocation. Stopping early.", $opt_max_mailboxes));
				break;
			}
			
			// Per-account IMAP timeouts
			$imap_timeout = !empty($account->timeout_secs) ? $account->timeout_secs : 30;
			
			imap_timeout(IMAP_OPENTIMEOUT, $imap_timeout);
			imap_timeout(IMAP_READTIMEOUT, $imap_timeout);
			imap_timeout(IMAP_CLOSETIMEOUT, $imap_timeout);
			
			$imap_timeout_read_ms = imap_timeout(IMAP_READTIMEOUT) * 1000; // ms
			$imap_options = array();
			
			// [TODO] Also allow disabling GSSAPI, NTLM from UI (requires patch)
			$disable_authenticators = [];
			
			if($account->auth_disable_plain)
				$disable_authenticators[] = 'PLAIN';
			
			if(defined('APP_MAIL_IMAP_DISABLE_NTLM') && APP_MAIL_IMAP_DISABLE_NTLM)
				$disable_authenticators[] = 'NTLM';
			
			if(defined('APP_MAIL_IMAP_DISABLE_GSSAPI') && APP_MAIL_IMAP_DISABLE_GSSAPI)
				$disable_authenticators[] = 'GSSAPI';
			
			if(!empty($disable_authenticators))
				$imap_options['DISABLE_AUTHENTICATOR'] = $disable_authenticators;
			
			$mailboxes_checked++;

			$logger->info('[Mailboxes] Account being parsed is '. $account->name);
			 
			$imap_connect = $account->getImapConnectString();

			$mailbox_runtime = microtime(true);
			 
			if(false === ($mailbox = @imap_open($imap_connect,
				!empty($account->username)?$account->username:"",
				!empty($account->password)?$account->password:"",
				null,
				0,
				$imap_options
				))) {
				
				$logger->error("[Mailboxes] Failed with error: ".imap_last_error());
				
				// Increment fails
				$num_fails = $account->num_fails + 1;
				$delay_until = time() + (min($num_fails, 15) * 120);
				
				$fields = array(
					DAO_Mailbox::CHECKED_AT => time(),
					DAO_Mailbox::NUM_FAILS => $num_fails,
					DAO_Mailbox::DELAY_UNTIL => $delay_until, // Delay 2 mins per consecutive failure
				);
				
				$logger->error("[Mailboxes] Delaying next mailbox check until ".date('h:i a', $delay_until));
				
				// Notify admins about consecutive mailbox failures at an interval
				if(in_array($num_fails, array(2,5,10,20))) {
					$logger->info(sprintf("[Mailboxes] Sending notification about %d consecutive failures on this mailbox", $num_fails));
					
					$url_writer = DevblocksPlatform::getUrlService();
					$admin_workers = DAO_Worker::getAllAdmins();
					
					/*
					 * Log activity (mailbox.check.error)
					 */
					$entry = array(
						//Mailbox {{target}} has failed to download mail on {{count}} consecutive attempts: {{error}}
						'message' => 'activities.mailbox.check.error',
						'variables' => array(
							'target' => $account->name,
							'count' => $num_fails,
							'error' => imap_last_error(),
							),
						'urls' => array(
							'target' => sprintf("ctx://%s:%s/%s", CerberusContexts::CONTEXT_MAILBOX, $account->id, DevblocksPlatform::strToPermalink($account->name)),
							)
					);
					CerberusContexts::logActivity('mailbox.check.error', CerberusContexts::CONTEXT_MAILBOX, $account->id, $entry, null, null, array_keys($admin_workers));
				}
				
				DAO_Mailbox::update($account->id, $fields);
				continue;
			}
			 
			$messages = array();
			$mailbox_stats = imap_check($mailbox);
			 
			// [TODO] Make this an account setting?
			$total = min($max_downloads, $mailbox_stats->Nmsgs);
			 
			$logger->info("[Mailboxes] Connected to mailbox '".$account->name."' (".number_format((microtime(true)-$mailbox_runtime)*1000,2)." ms)");

			$mailbox_runtime = microtime(true);
			
			$msgs_stats = imap_fetch_overview($mailbox, sprintf("1:%d", $total));
			
			foreach($msgs_stats as &$msg_stats) {
				$time = microtime(true);

				do {
					$unique = sprintf("%s.%04d",
					time(),
					mt_rand(0,9999)
					);
					$filename = APP_MAIL_PATH . 'new' . DIRECTORY_SEPARATOR . $unique;
				} while(file_exists($filename));

				$fp = fopen($filename,'w+');

				if($fp) {
					$mailbox_xheader = "X-Cerberus-Mailbox: " . $account->name . "\r\n";
					fwrite($fp, $mailbox_xheader);

					// If the message is too big, save a message stating as much
					if($account->max_msg_size_kb && $msg_stats->size >= $account->max_msg_size_kb * 1000) {
						$logger->warn(sprintf("[Mailboxes] This message is %s which exceeds the mailbox limit of %s",
							DevblocksPlatform::strPrettyBytes($msg_stats->size),
							DevblocksPlatform::strPrettyBytes($account->max_msg_size_kb*1000)
						));
						
						$error_msg = sprintf("This %s message exceeded the mailbox limit of %s",
							DevblocksPlatform::strPrettyBytes($msg_stats->size),
							DevblocksPlatform::strPrettyBytes($account->max_msg_size_kb*1000)
						);
						
						$truncated_message = sprintf(
							"X-Cerb-Parser-Error: message-size-limit-exceeded\r\n".
							"X-Cerb-Parser-ErrorMsg: %s\r\n".
							"From: %s\r\n".
							"To: %s\r\n".
							"Subject: %s\r\n".
							"Date: %s\r\n".
							"Message-Id: %s\r\n".
							"\r\n".
							"(%s)\r\n",
							$error_msg,
							$msg_stats->from,
							$msg_stats->to,
							$msg_stats->subject,
							$msg_stats->date,
							$msg_stats->message_id,
							$error_msg
						);
						
						fwrite($fp, $truncated_message);
						
					// Otherwise, save the message like normal
					} else {
						$result = imap_savebody($mailbox, $fp, $msg_stats->msgno); // Write the message directly to the file handle
					}

					@fclose($fp);
				}
				
				$time = microtime(true) - $time;
				
				// If this message took a really long time to download, skip it and retry later
				// [TODO] We may want to keep track if the same message does this repeatedly
				if(($time*1000) > (0.95 * $imap_timeout_read_ms)) {
					$logger->warn("[Mailboxes] This message took more than 95% of the IMAP_READTIMEOUT value to download. We probably timed out. Aborting to retry later...");
					unlink($filename);
					break;
				}
				
				/*
				 * [JAS]: We don't add the .msg extension until we're done with the file,
				 * since this will safely be ignored by the parser until we're ready
				 * for it.
				 */
				rename($filename, dirname($filename) . DIRECTORY_SEPARATOR . basename($filename) . '.msg');

				$logger->info("[Mailboxes] Downloaded message ".$msg_stats->msgno." (".sprintf("%d",($time*1000))." ms)");
				
				imap_delete($mailbox, $msg_stats->msgno);
			}
			
			// Clear the fail count if we had past fails
			DAO_Mailbox::update(
				$account->id,
				array(
					DAO_Mailbox::CHECKED_AT => time(),
					DAO_Mailbox::NUM_FAILS => 0,
					DAO_Mailbox::DELAY_UNTIL => 0,
				)
			);
			
			imap_expunge($mailbox);
			imap_close($mailbox);
			@imap_errors();
			 
			$logger->info("[Mailboxes] Closed mailbox (".number_format((microtime(true)-$mailbox_runtime)*1000,2)." ms)");
		}
		
		if(empty($mailboxes_checked))
			$logger->info('[Mailboxes] There are no mailboxes ready to be checked.');
		
		$logger->info("[Mailboxes] Finished Mailbox Checker job (".number_format((microtime(true)-$runtime)*1000,2)." ms)");
	}

	function configure($instance) {
		$tpl = DevblocksPlatform::getTemplateService();

		$timeout = ini_get('max_execution_time');
		$tpl->assign('max_messages', $this->getParam('max_messages', (($timeout) ? 20 : 50)));

		$tpl->display('devblocks:cerberusweb.core::cron/mailbox/config.tpl');
	}

	function saveConfigurationAction() {

		@$max_messages = DevblocksPlatform::importGPC($_POST['max_messages'],'integer');
		$this->setParam('max_messages', $max_messages);

		DevblocksPlatform::setHttpResponse(new DevblocksHttpResponse(array('config','jobs')));
	}
};

class StorageCron extends CerberusCronPageExtension {
	function run() {
		$logger = DevblocksPlatform::getConsoleLog();
		
		$runtime = microtime(true);
		
		$logger->info("[Storage] Starting...");

		$max_runtime = time() + 30; // [TODO] Make configurable
		
		// Run any pending batch DELETEs
		$pending_profiles = DAO_DevblocksStorageQueue::getPendingProfiles();
		
		if(is_array($pending_profiles))
		foreach($pending_profiles as $pending_profile) {
			if($max_runtime < time())
				continue;
			
			// Use a profile or a base extension
			$engine =
				!empty($pending_profile['storage_profile_id'])
				? $pending_profile['storage_profile_id']
				: $pending_profile['storage_extension']
				;
			
			if(false == ($storage = DevblocksPlatform::getStorageService($engine)))
				continue;
			
			// Get one page of 500 pending delete keys for this profile
			$keys = DAO_DevblocksStorageQueue::getKeys($pending_profile['storage_namespace'], $pending_profile['storage_extension'], $pending_profile['storage_profile_id'], 500);
			
			$logger->info(sprintf("[Storage] Batch deleting %d %s object(s) for %s:%d",
				count($keys),
				$pending_profile['storage_namespace'],
				$pending_profile['storage_extension'],
				$pending_profile['storage_profile_id']
			));
			
			// Pass the keys to the storage engine
			if(false !== ($keys = $storage->batchDelete($pending_profile['storage_namespace'], $keys))) {

				// Remove the entries on success
				if(is_array($keys) && !empty($keys))
					DAO_DevblocksStorageQueue::purgeKeys($keys, $pending_profile['storage_namespace'], $pending_profile['storage_extension'], $pending_profile['storage_profile_id']);
			}
		}
		
		// Synchronize storage schemas (active+archive)
		$storage_schemas = DevblocksPlatform::getExtensions('devblocks.storage.schema', true);
		
		if(is_array($storage_schemas))
		foreach($storage_schemas as $schema) { /* @var $schema Extension_DevblocksStorageSchema */
			if($max_runtime > time())
				$schema->unarchive($max_runtime);
			if($max_runtime > time())
				$schema->archive($max_runtime);
		}
		
		$logger->info("[Storage] Total Runtime: ".number_format((microtime(true)-$runtime)*1000,2)." ms");
	}
	
	function configure($instance) {
		$tpl = DevblocksPlatform::getTemplateService();

//		$timeout = ini_get('max_execution_time');
//		$tpl->assign('max_messages', $this->getParam('max_messages', (($timeout) ? 20 : 50)));

		//$tpl->display('devblocks:cerberusweb.core::cron/storage/config.tpl');
	}

	function saveConfigurationAction() {
//		@$max_messages = DevblocksPlatform::importGPC($_POST['max_messages'],'integer');
//		$this->setParam('max_messages', $max_messages);

		DevblocksPlatform::setHttpResponse(new DevblocksHttpResponse(array('config','jobs')));
	}
};

class MailQueueCron extends CerberusCronPageExtension {
	function run() {
		$logger = DevblocksPlatform::getConsoleLog();
		$runtime = microtime(true);

		$stop_time = time() + 30; // [TODO] Make configurable
		$last_id = 0;
		
		$logger->info("[Mail Queue] Starting...");
		
		if (!extension_loaded("mailparse")) {
			$logger->err("[Parser] The 'mailparse' extension is not loaded.  Aborting!");
			return false;
		}
		
		// Drafts->SMTP
		
		do {
			$messages = DAO_MailQueue::getWhere(
				sprintf("%s = %d AND %s <= %d AND %s > %d AND %s < %d",
					DAO_MailQueue::IS_QUEUED,
					1,
					DAO_MailQueue::QUEUE_DELIVERY_DATE,
					time(),
					DAO_MailQueue::ID,
					$last_id,
					DAO_MailQueue::QUEUE_FAILS,
					10
				),
				array(DAO_MailQueue::QUEUE_DELIVERY_DATE, DAO_MailQueue::UPDATED),
				array(true, true),
				25
			);
	
			if(!empty($messages)) {
				$message_ids = array_keys($messages);
				
				foreach($messages as $message) { /* @var $message Model_MailQueue */
					if(!$message->send()) {
						$logger->error(sprintf("[Mail Queue] Failed sending message %d", $message->id));
						DAO_MailQueue::update($message->id, array(
							DAO_MailQueue::QUEUE_FAILS => min($message->queue_fails+1,255),
							DAO_MailQueue::QUEUE_DELIVERY_DATE => time() + 900, // retry in 15 min
						));
						
					} else {
						$logger->info(sprintf("[Mail Queue] Sent message %d", $message->id));
					}
				}
				
				$last_id = end($message_ids);
			}
			
		} while(!empty($messages) && $stop_time > time());
		
		$logger->info("[Mail Queue] Total Runtime: ".number_format((microtime(true)-$runtime)*1000,2)." ms");
	}

	function configure($instance) {
		//$tpl = DevblocksPlatform::getTemplateService();
		//$tpl->display('devblocks:cerberusweb.core::cron/mail_queue/config.tpl');
	}
};

class Cron_BotScheduledBehavior extends CerberusCronPageExtension {
	function run() {
		$logger = DevblocksPlatform::getConsoleLog('Bot Scheduler');
		$runtime = microtime(true);

		$stop_time = time() + 20; // [TODO] Make configurable

		$logger->info("Starting...");
		$last_behavior_id = 0;

		do {
			$behaviors = DAO_ContextScheduledBehavior::getWhere(
				sprintf("%s < %d AND %s > %d",
					DAO_ContextScheduledBehavior::RUN_DATE,
					time(),
					DAO_ContextScheduledBehavior::ID,
					$last_behavior_id
				),
				array(DAO_ContextScheduledBehavior::RUN_DATE),
				array(true),
				25
			);

			if(!empty($behaviors)) {
				foreach($behaviors as $behavior) {
					/* @var $behavior Model_ContextScheduledBehavior */
					try {
						if(empty($behavior->context) || empty($behavior->context_id) || empty($behavior->behavior_id))
							throw new Exception("Incomplete macro.");
					
						// Load context
						if(null == ($context_ext = DevblocksPlatform::getExtension($behavior->context, true)))
							throw new Exception("Invalid context.");
					
						// [TODO] ACL: Ensure access to the context object
							
						// Load macro
						if(null == ($macro = DAO_TriggerEvent::get($behavior->behavior_id))) /* @var $macro Model_TriggerEvent */
							throw new Exception("Invalid macro.");
						
						if($macro->is_disabled)
							throw new Exception("Macro disabled.");
							
						// [TODO] ACL: Ensure the worker owns the macro
					
						// Load event manifest
						if(null == ($ext = DevblocksPlatform::getExtension($macro->event_point, false))) /* @var $ext DevblocksExtensionManifest */
							throw new Exception("Invalid event.");

						// Execute
						$behavior->run();

						// Log
						$logger->info(sprintf("Executed behavior %d", $behavior->id));
						
					} catch (Exception $e) {
						$logger->error(sprintf("Failed executing behavior %d: %s", $behavior->id, $e->getMessage()));

						DAO_ContextScheduledBehavior::delete($behavior->id);
					}
					
					$last_behavior_id = $behavior->id;
				}
			}
			
		} while(!empty($behaviors) && $stop_time > time());

		$logger->info("Total Runtime: ".number_format((microtime(true)-$runtime)*1000,2)." ms");
	}

	function configure($instance) {
	}
};

class SearchCron extends CerberusCronPageExtension {
	function run() {
		$logger = DevblocksPlatform::getConsoleLog();
		$runtime = microtime(true);
		
		$logger->info("[Search] Starting...");
		
		// Loop through search schemas and batch index by ID or timestamp
		
		$schemas = DevblocksPlatform::getExtensions('devblocks.search.schema', true);

		$stop_time = time() + 30; // [TODO] Make configurable
		
		foreach($schemas as $schema) {
			if($stop_time > time()) {
				if($schema instanceof Extension_DevblocksSearchSchema)
					$schema->index($stop_time);
			}
		}
		
		$logger->info("[Search] Total Runtime: ".number_format((microtime(true)-$runtime)*1000,2)." ms");
	}
	
	function configure($instance) {
	}
};