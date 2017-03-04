<?php
class UmScAjaxController extends Extension_UmScController {
	function __construct($manifest=null) {
		parent::__construct($manifest);
		
		$tpl = DevblocksPlatform::getTemplateSandboxService();
		$umsession = ChPortalHelper::getSession();
		
		@$active_contact = $umsession->getProperty('sc_login',null);
		$tpl->assign('active_contact', $active_contact);

		// Usermeet Session
		if(null == ($fingerprint = ChPortalHelper::getFingerprint())) {
			DevblocksPlatform::dieWithHttpError("A problem occurred.", 500);
		}
		$tpl->assign('fingerprint', $fingerprint);
	}
	
	function handleRequest(DevblocksHttpRequest $request) {
		@$path = $request->path;
		@$a = DevblocksPlatform::importGPC($_REQUEST['a'],'string');
		
		@array_shift($path); // ajax
		
		if(empty($a)) {
			@$action = array_shift($path) . 'Action';
		} else {
			@$action = $a . 'Action';
		}
		
		switch($action) {
			default:
				// Default action, call arg as a method suffixed with Action
				if(method_exists($this,$action)) {
					call_user_func(array($this, $action), new DevblocksHttpRequest($path)); // Pass HttpRequest as arg
				}
				break;
		}
		
		exit;
	}
	
	function viewRefreshAction(DevblocksHttpRequest $request) {
		@$view_id = DevblocksPlatform::importGPC($_REQUEST['id'],'string','');
		
		if(null != ($view = UmScAbstractViewLoader::getView('', $view_id))) {
			$view->render();
		}
	}

	function viewPageAction(DevblocksHttpRequest $request) {
		@$view_id = DevblocksPlatform::importGPC($_REQUEST['id'],'string','');
		@$page = DevblocksPlatform::importGPC($_REQUEST['page'],'integer',0);
		
		if(null != ($view = UmScAbstractViewLoader::getView('', $view_id))) {
			$view->renderPage = $page;
			UmScAbstractViewLoader::setView($view->id, $view);
			
			$view->render();
		}
	}
	
	function viewSortByAction(DevblocksHttpRequest $request) {
		@$view_id = DevblocksPlatform::importGPC($_REQUEST['id'],'string','');
		@$sort_by = DevblocksPlatform::importGPC($_REQUEST['sort_by'],'string','');
		
		if(null != ($view = UmScAbstractViewLoader::getView('', $view_id))) {
			$fields = $view->getColumnsAvailable();
			if(isset($fields[$sort_by])) {
				if(0==strcasecmp($view->renderSortBy,$sort_by)) { // clicked same col?
					$view->renderSortAsc = !(bool)$view->renderSortAsc; // flip order
				} else {
					$view->renderSortBy = $sort_by;
					$view->renderSortAsc = true;
				}
				
				$view->renderPage = 0;
				
				UmScAbstractViewLoader::setView($view->id, $view);
			}
			
			$view->render();
		}
	}
	
	function viewFilterAddAction(DevblocksHttpRequest $request) {
		@$view_id = DevblocksPlatform::importGPC($_REQUEST['id'],'string','');
		@$field = DevblocksPlatform::importGPC($_REQUEST['field'],'string','');
		@$oper = DevblocksPlatform::importGPC($_REQUEST['oper'],'string','');
		@$value = DevblocksPlatform::importGPC($_REQUEST['value'],'string','');
		
		$tpl = DevblocksPlatform::getTemplateSandboxService();
		
		if(null != ($view = UmScAbstractViewLoader::getView('', $view_id))) {
			$view->doSetCriteria($field, $oper, $value);
			UmScAbstractViewLoader::setView($view->id, $view);
			
			$tpl->assign('view', $view);
			$tpl->assign('reload_view', true);
			$tpl->display('devblocks:cerberusweb.support_center::support_center/internal/view/view_filters.tpl');
		}
		
		exit;
	}
	
	function viewFilterGetAction(DevblocksHttpRequest $request) {
		@$view_id = DevblocksPlatform::importGPC($_REQUEST['id'],'string','');
		@$field = DevblocksPlatform::importGPC($_REQUEST['field'],'string','');
		
		$tpl = DevblocksPlatform::getTemplateSandboxService();
		
		if(null != ($view = UmScAbstractViewLoader::getView('', $view_id))) {
			$view->renderCriteria($field);
			//UmScAbstractViewLoader::setView($view->id, $view);
		}
		
		exit;
	}
	
	function viewFiltersDoAction(DevblocksHttpRequest $request) {
		@$view_id = DevblocksPlatform::importGPC($_REQUEST['id'],'string','');
		@$do = DevblocksPlatform::importGPC($_REQUEST['do'],'string','');
		@$filters = DevblocksPlatform::importGPC($_REQUEST['filters'],'array',array());
		
		$tpl = DevblocksPlatform::getTemplateSandboxService();
		
		if(null != ($view = UmScAbstractViewLoader::getView('', $view_id))) {
			switch($do) {
				case 'remove':
					foreach($filters as $filter_key) {
						$view->doRemoveCriteria($filter_key);
					}
					UmScAbstractViewLoader::setView($view->id, $view);
					break;
					
				case 'reset':
					$view->doResetCriteria();
					UmScAbstractViewLoader::setView($view->id, $view);
					break;
			}
			
			$tpl->assign('view', $view);
			$tpl->assign('reload_view', true);
			$tpl->display('devblocks:cerberusweb.support_center::support_center/internal/view/view_filters.tpl');
		}
		
		exit;
	}
	
	function downloadFileAction(DevblocksHttpRequest $request) {
		$umsession = ChPortalHelper::getSession();
		$stack = $request->path;
		
		// Attachment hash + display name
		@$hash = array_shift($stack);
		@$name = array_shift($stack);
		
		if(empty($hash) || empty($name))
			return;
		
		// Attachment
		if(null == ($file_id = DAO_Attachment::getBySha1Hash($hash)))
			return;
		
		if(null == ($file = DAO_Attachment::get($file_id)))
			return;
		
		$pass = false;
		
		if(false == ($links = DAO_Attachment::getLinks($file_id)))
			return;
		
		if(!$pass && isset($links[CerberusContexts::CONTEXT_KB_ARTICLE])) {
			// [TODO] Compare KB links to this portal
			$pass = true;
		}
		
		if(!$pass && isset($links[CerberusContexts::CONTEXT_MESSAGE])) {
			if(null == ($active_contact = $umsession->getProperty('sc_login',null))) /* @var $active_contact Model_Contact */
				return;
			
			if(false == ($contact_emails = $active_contact->getEmails()))
				return;
			
			$pass = DAO_Ticket::authorizeByParticipantsAndMessages(array_keys($contact_emails), $links[CerberusContexts::CONTEXT_MESSAGE]);
		}
		
		if(!$pass)
			return;

		$contents = $file->getFileContents();
			
		// Set headers
		header("Expires: Mon, 26 Nov 1962 00:00:00 GMT");
		header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
		header("Accept-Ranges: bytes");
		header("Content-Type: " . $file->mime_type);
		header("Content-Length: " . strlen($contents));
		
		// Dump contents
		echo $contents;
		exit;
	}
};