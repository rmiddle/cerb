<?php
/***********************************************************************
| Cerb(tm) developed by Webgroup Media, LLC.
|-----------------------------------------------------------------------
| All source code & content (c) Copyright 2002-2015, Webgroup Media LLC
|   unless specifically noted otherwise.
|
| This source code is released under the Devblocks Public License.
| The latest version of this license can be found here:
| http://cerberusweb.com/license
|
| By using this software, you acknowledge having read this license
| and agree to be bound thereby.
| ______________________________________________________________________
|	http://www.cerbweb.com	    http://www.webgroupmedia.com/
***********************************************************************/

class PageSection_SetupWorkers extends Extension_PageSection {
	function render() {
		$tpl = DevblocksPlatform::getTemplateService();
		$visit = CerberusApplication::getVisit();
		
		$visit->set(ChConfigurationPage::ID, 'workers');
		
		$workers = DAO_Worker::getAllWithDisabled();
		$tpl->assign('workers', $workers);

		$groups = DAO_Group::getAll();
		$tpl->assign('groups', $groups);
		
		$defaults = C4_AbstractViewModel::loadFromClass('View_Worker');
		$defaults->id = 'workers_cfg';
		
		$view = C4_AbstractViewLoader::getView($defaults->id, $defaults);
		$tpl->assign('view', $view);
		
		$tpl->display('devblocks:cerberusweb.core::configuration/section/workers/index.tpl');
	}
	
	function showWorkerPeekAction() {
		@$id = DevblocksPlatform::importGPC($_REQUEST['id'],'integer',0);
		@$view_id = DevblocksPlatform::importGPC($_REQUEST['view_id'],'string','');
		
		$tpl = DevblocksPlatform::getTemplateService();
		$date = DevblocksPlatform::getDateService();
		
		$active_worker = CerberusApplication::getActiveWorker();
		
		if(!$active_worker->is_superuser) {
			$tpl->assign('error_message', "Only administrators can edit worker records.");
			$tpl->display('devblocks:cerberusweb.core::internal/peek/peek_error.tpl');
		}
		
		$tpl->assign('view_id', $view_id);
		
		if(false == ($worker = DAO_Worker::get($id))) {
			$worker = new Model_Worker();
			$worker->id = 0;
			$worker->timezone = $active_worker->timezone;
			$worker->time_format = $active_worker->time_format;
			$worker->language = $active_worker->language;
		}
		
		$tpl->assign('worker', $worker);
		
		$groups = DAO_Group::getAll();
		$tpl->assign('groups', $groups);
		
		// Custom Fields
		$custom_fields = DAO_CustomField::getByContext(CerberusContexts::CONTEXT_WORKER, false);
		$tpl->assign('custom_fields', $custom_fields);
		
		$custom_field_values = DAO_CustomFieldValue::getValuesByContextIds(CerberusContexts::CONTEXT_WORKER, $id);
		if(isset($custom_field_values[$id]))
			$tpl->assign('custom_field_values', $custom_field_values[$id]);
		
		// Authenticators
		$auth_extensions = Extension_LoginAuthenticator::getAll(false);
		$tpl->assign('auth_extensions', $auth_extensions);
		
		// Calendars
		$calendars = DAO_Calendar::getOwnedByWorker($worker);
		$tpl->assign('calendars', $calendars);
		
		// Languages
		$languages = DAO_Translation::getDefinedLangCodes();
		$tpl->assign('languages', $languages);
		
		// Timezones
		$timezones = $date->getTimezones();
		$tpl->assign('timezones', $timezones);
		
		// Time Format
		$tpl->assign('time_format', DevblocksPlatform::getDateTimeFormat());
		
		$tpl->display('devblocks:cerberusweb.core::configuration/section/workers/peek.tpl');
	}
	
	function saveWorkerPeekAction() {
		$translate = DevblocksPlatform::getTranslationService();
		$active_worker = CerberusApplication::getActiveWorker();
		
		if(!$active_worker || !$active_worker->is_superuser) {
			return;
		}
		
		@$id = DevblocksPlatform::importGPC($_POST['id'],'integer');
		@$view_id = DevblocksPlatform::importGPC($_POST['view_id'],'string');
		@$first_name = DevblocksPlatform::importGPC($_POST['first_name'],'string');
		@$last_name = DevblocksPlatform::importGPC($_POST['last_name'],'string');
		@$title = DevblocksPlatform::importGPC($_POST['title'],'string');
		@$email = trim(strtolower(DevblocksPlatform::importGPC($_POST['email'],'string')));
		@$auth_extension_id = DevblocksPlatform::importGPC($_POST['auth_extension_id'],'string');
		@$at_mention_name = DevblocksPlatform::strToPermalink(DevblocksPlatform::importGPC($_POST['at_mention_name'],'string'));
		@$language = DevblocksPlatform::importGPC($_POST['lang_code'],'string');
		@$timezone = DevblocksPlatform::importGPC($_POST['timezone'],'string');
		@$time_format = DevblocksPlatform::importGPC($_POST['time_format'],'string');
		@$calendar_id = DevblocksPlatform::importGPC($_POST['calendar_id'],'string');
		@$password_new = DevblocksPlatform::importGPC($_POST['password_new'],'string');
		@$password_verify = DevblocksPlatform::importGPC($_POST['password_verify'],'string');
		@$is_superuser = DevblocksPlatform::importGPC($_POST['is_superuser'],'integer', 0);
		@$disabled = DevblocksPlatform::importGPC($_POST['is_disabled'],'integer',0);
		@$group_ids = DevblocksPlatform::importGPC($_POST['group_ids'],'array');
		@$group_roles = DevblocksPlatform::importGPC($_POST['group_roles'],'array');
		@$delete = DevblocksPlatform::importGPC($_POST['do_delete'],'integer',0);

		// [TODO] The superuser set bit here needs to be protected by ACL
		// [TODO] AKA, only admins can create new admins
		
		if(empty($first_name)) $first_name = "Anonymous";
		
		if(!empty($id) && !empty($delete)) {
			// Can't delete or disable self
			if($active_worker->id != $id)
				DAO_Worker::delete($id);
			
		} else {
			// Validate the email address
			if(false == ($worker_address = DAO_Address::lookupAddress($email, true)))
				return false;
			
			if(empty($id) && null == DAO_Worker::getByEmail($email)) {
				if(empty($password_new)) {
					// Creating new worker.  If password is empty, email it to them
					$replyto_default = DAO_AddressOutgoing::getDefault();
					$replyto_personal = $replyto_default->getReplyPersonal();
					$url = DevblocksPlatform::getUrlService();
					$password = CerberusApplication::generatePassword(8);
					
					try {
						$mail_service = DevblocksPlatform::getMailService();
						$mail = $mail_service->createMessage();
						
						$mail->setTo(array($email => $first_name . ' ' . $last_name));
						
						if(!empty($replyto_personal)) {
							$mail->setFrom($replyto_default->email, $replyto_personal);
						} else {
							$mail->setFrom($replyto_default->email);
						}
						
						$mail->setSubject('Your new Cerb login information!');
						$mail->generateId();
						
						$headers = $mail->getHeaders();
						
						$headers->addTextHeader('X-Mailer','Cerb ' . APP_VERSION . ' (Build '.APP_BUILD.')');
						
						$body = sprintf("Your new Cerb login information is below:\r\n".
							"\r\n".
							"URL: %s\r\n".
							"Login: %s\r\n".
							"\r\n",
								$url->write('',true),
								$email
						);
						
						$mail->setBody($body);
	
						if(!$mail_service->send($mail)) {
							throw new Exception('Password notification email failed to send.');
						}
						
					} catch (Exception $e) {
						// [TODO] need to report to the admin when the password email doesn't send.  The try->catch
						// will keep it from killing php, but the password will be empty and the user will never get an email.
					}
				}
				
				$fields = array(
					DAO_Worker::FIRST_NAME => $first_name,
					DAO_Worker::LAST_NAME => $last_name,
					DAO_Worker::TITLE => $title,
					DAO_Worker::IS_SUPERUSER => $is_superuser,
					DAO_Worker::IS_DISABLED => $disabled,
					DAO_Worker::EMAIL => $email,
					DAO_Worker::AUTH_EXTENSION_ID => $auth_extension_id,
					DAO_Worker::AT_MENTION_NAME => $at_mention_name,
					DAO_Worker::LANGUAGE => $language,
					DAO_Worker::TIMEZONE => $timezone,
					DAO_Worker::TIME_FORMAT => $time_format,
				);
				
				if(false == ($id = DAO_Worker::create($fields)))
					return false;
				
				// View marquee
				if(!empty($id) && !empty($view_id)) {
					C4_AbstractView::setMarqueeContextCreated($view_id, CerberusContexts::CONTEXT_WORKER, $id);
				}
				
			} // end create worker
			
			// Calendar
			
			// Create a calendar for this worker
			if('new' == $calendar_id) {
				$fields = array(
					DAO_Calendar::NAME => sprintf("%s%s's Calendar", $first_name, $last_name ? (' ' . $last_name) : ''),
					DAO_Calendar::OWNER_CONTEXT => CerberusContexts::CONTEXT_WORKER,
					DAO_Calendar::OWNER_CONTEXT_ID => $id,
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
				
			} else {
				// [TODO] Validate calendar id
				$calendar_id = intval($calendar_id);
			}
			
			// Update
			$fields = array(
				DAO_Worker::FIRST_NAME => $first_name,
				DAO_Worker::LAST_NAME => $last_name,
				DAO_Worker::TITLE => $title,
				DAO_Worker::EMAIL => $email,
				DAO_Worker::IS_SUPERUSER => $is_superuser,
				DAO_Worker::IS_DISABLED => $disabled,
				DAO_Worker::AUTH_EXTENSION_ID => $auth_extension_id,
				DAO_Worker::AT_MENTION_NAME => $at_mention_name,
				DAO_Worker::LANGUAGE => $language,
				DAO_Worker::TIMEZONE => $timezone,
				DAO_Worker::TIME_FORMAT => $time_format,
				DAO_Worker::CALENDAR_ID => $calendar_id,
			);
			
			// Update worker
			DAO_Worker::update($id, $fields);
			
			// Auth
			if(!empty($password_new) && $password_new == $password_verify) {
				DAO_Worker::setAuth($id, $password_new);
			}
			
			// Update group memberships
			if(is_array($group_ids) && is_array($group_roles))
			foreach($group_ids as $idx => $group_id) {
				if(empty($group_roles[$idx])) {
					DAO_Group::unsetGroupMember($group_id, $id);
				} else {
					DAO_Group::setGroupMember($group_id, $id, (2==$group_roles[$idx]));
				}
			}

			// Set the name on the worker email address
			
			if($worker_address instanceof Model_Address) {
				$addy_fields = array();
				
				if(empty($worker_address->first_name) && !empty($first_name))
					$addy_fields[DAO_Address::FIRST_NAME] = $first_name;
				
				if(empty($worker_address->last_name) && !empty($last_name))
					$addy_fields[DAO_Address::LAST_NAME] = $last_name;
				
				if(!empty($addy_fields))
					DAO_Address::update($worker_address->id, $addy_fields);
			}
			
			// Addresses
			if(null == DAO_AddressToWorker::getByAddress($email)) {
				DAO_AddressToWorker::assign($email, $id, true);
			}
			
			// Custom field saves
			@$field_ids = DevblocksPlatform::importGPC($_POST['field_ids'], 'array', array());
			DAO_CustomFieldValue::handleFormPost(CerberusContexts::CONTEXT_WORKER, $id, $field_ids);
			
			// Flush caches
			DAO_WorkerRole::clearWorkerCache($id);
		}
	}
	
	function showWorkersBulkPanelAction() {
		@$id_csv = DevblocksPlatform::importGPC($_REQUEST['ids']);
		@$view_id = DevblocksPlatform::importGPC($_REQUEST['view_id']);

		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->assign('view_id', $view_id);

		if(!empty($id_csv)) {
			$ids = DevblocksPlatform::parseCsvString($id_csv);
			$tpl->assign('ids', implode(',', $ids));
		}
		
		// Custom Fields
		$custom_fields = DAO_CustomField::getByContext(CerberusContexts::CONTEXT_WORKER, false);
		$tpl->assign('custom_fields', $custom_fields);
		
		// Auth extensions
		$auth_extensions = Extension_LoginAuthenticator::getAll(false);
		$tpl->assign('auth_extensions', $auth_extensions);
		
		$tpl->display('devblocks:cerberusweb.core::configuration/section/workers/bulk.tpl');
	}
	
	function doWorkersBulkUpdateAction() {
		// Filter: whole list or check
		@$filter = DevblocksPlatform::importGPC($_REQUEST['filter'],'string','');
		$ids = array();
		
		// View
		@$view_id = DevblocksPlatform::importGPC($_REQUEST['view_id'],'string');
		$view = C4_AbstractViewLoader::getView($view_id);
		$view->setAutoPersist(false);
		
		// Worker fields
		@$is_disabled = trim(DevblocksPlatform::importGPC($_POST['is_disabled'],'string',''));
		@$auth_extension_id = trim(DevblocksPlatform::importGPC($_POST['auth_extension_id'],'string',''));

		$do = array();
		
		// Do: Disabled
		if(0 != strlen($is_disabled))
			$do['is_disabled'] = $is_disabled;
		
		// Do: Authentication Extension
		if(0 != strlen($auth_extension_id))
			$do['auth_extension_id'] = $auth_extension_id;
			
		// Do: Custom fields
		$do = DAO_CustomFieldValue::handleBulkPost($do);
		
		switch($filter) {
			// Checked rows
			case 'checks':
				@$ids_str = DevblocksPlatform::importGPC($_REQUEST['ids'],'string');
				$ids = DevblocksPlatform::parseCsvString($ids_str);
				break;
			default:
				break;
		}
		
		$view->doBulkUpdate($filter, $do, $ids);
		
		$view->render();
		return;
	}
}