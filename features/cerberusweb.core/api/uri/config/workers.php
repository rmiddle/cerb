<?php
/***********************************************************************
| Cerb(tm) developed by Webgroup Media, LLC.
|-----------------------------------------------------------------------
| All source code & content (c) Copyright 2002-2014, Webgroup Media LLC
|   unless specifically noted otherwise.
|
| This source code is released under the Devblocks Public License.
| The latest version of this license can be found here:
| http://cerberusweb.com/license
|
| By using this software, you acknowledge having read this license
| and agree to be bound thereby.
| ______________________________________________________________________
|	http://www.cerberusweb.com	  http://www.webgroupmedia.com/
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
		
		$defaults = new C4_AbstractViewModel();
		$defaults->id = 'workers_cfg';
		$defaults->class_name = 'View_Worker';
		
		$view = C4_AbstractViewLoader::getView($defaults->id, $defaults);
		$tpl->assign('view', $view);
		
		$tpl->display('devblocks:cerberusweb.core::configuration/section/workers/index.tpl');
	}
	
	function showWorkerPeekAction() {
		@$id = DevblocksPlatform::importGPC($_REQUEST['id'],'integer',0);
		@$view_id = DevblocksPlatform::importGPC($_REQUEST['view_id'],'string','');
		
		$tpl = DevblocksPlatform::getTemplateService();
		
		$tpl->assign('view_id', $view_id);
		
		$worker = DAO_Worker::get($id);
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
		@$email = DevblocksPlatform::importGPC($_POST['email'],'string');
		@$auth_extension_id = DevblocksPlatform::importGPC($_POST['auth_extension_id'],'string');
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
			if(empty($id) && null == DAO_Worker::getByEmail($email)) {
				if(empty($password_new)) {
					// Creating new worker.  If password is empty, email it to them
					$replyto_default = DAO_AddressOutgoing::getDefault();
					$replyto_personal = $replyto_default->getReplyPersonal();
					$url = DevblocksPlatform::getUrlService();
					$password = CerberusApplication::generatePassword(8);
					
					try {
						$mail_service = DevblocksPlatform::getMailService();
						$mailer = $mail_service->getMailer(CerberusMail::getMailerDefaults());
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
	
						if(!$mailer->send($mail)) {
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
				);
				
				$id = DAO_Worker::create($fields);
				
				// View marquee
				if(!empty($id) && !empty($view_id)) {
					C4_AbstractView::setMarqueeContextCreated($view_id, CerberusContexts::CONTEXT_WORKER, $id);
				}
				
			} // end create worker
			
			// Update
			$fields = array(
				DAO_Worker::FIRST_NAME => $first_name,
				DAO_Worker::LAST_NAME => $last_name,
				DAO_Worker::TITLE => $title,
				DAO_Worker::EMAIL => $email,
				DAO_Worker::IS_SUPERUSER => $is_superuser,
				DAO_Worker::IS_DISABLED => $disabled,
				DAO_Worker::AUTH_EXTENSION_ID => $auth_extension_id
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

			// Add the worker e-mail to the addresses table
			if(!empty($email))
				DAO_Address::lookupAddress($email, true);
			
			// Addresses
			// [TODO] This can insert dupe rows under some conditions
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