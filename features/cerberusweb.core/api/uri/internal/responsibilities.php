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

if(class_exists('Extension_PageSection')):
class PageSection_InternalResponsibilities extends Extension_PageSection {
	function render() {}
	
	function showResponsibilitiesTabAction() {
		@$context = DevblocksPlatform::importGPC($_REQUEST['context'], 'string', '');
		@$context_id = DevblocksPlatform::importGPC($_REQUEST['context_id'], 'string', '');
		
		$tpl = DevblocksPlatform::getTemplateService();
		
		switch($context) {
			case CerberusContexts::CONTEXT_WORKER:
				if(false == ($worker = DAO_Worker::get($context_id)))
					return;
					
				$tpl->assign('worker', $worker);
				
				$responsibilities = $worker->getResponsibilities();
				$tpl->assign('responsibilities', $responsibilities);
				
				$groups = DAO_Group::getAll();
				$tpl->assign('groups', $groups);
				
				$tpl->display('devblocks:cerberusweb.core::internal/responsibilities/tab_by_worker_readonly.tpl');
				break;
				
			case CerberusContexts::CONTEXT_GROUP:
				if(false == ($group = DAO_Group::get($context_id)))
					return;
					
				$tpl->assign('group', $group);
				
				$buckets = $group->getBuckets();
				$tpl->assign('buckets', $buckets);
				
				$members = $group->getMembers();
				$tpl->assign('members', $members);
				
				$workers = DAO_Worker::getAllActive();
				$tpl->assign('workers', $workers);
				
				$responsibilities = $group->getResponsibilities();
				$tpl->assign('responsibilities', $responsibilities);
				
				$tpl->display('devblocks:cerberusweb.core::internal/responsibilities/tab_by_group_readonly.tpl');
				break;
		}
	}
	
	function showResponsibilitiesPopupAction() {
		@$context = DevblocksPlatform::importGPC($_REQUEST['context'], 'string', '');
		@$context_id = DevblocksPlatform::importGPC($_REQUEST['context_id'], 'string', '');
		
		$tpl = DevblocksPlatform::getTemplateService();
		
		switch($context) {
			case CerberusContexts::CONTEXT_GROUP:
				if(false == ($group = DAO_Group::get($context_id)))
					return;
					
				$tpl->assign('group', $group);
				
				$buckets = $group->getBuckets();
				$tpl->assign('buckets', $buckets);
				
				$members = $group->getMembers();
				$tpl->assign('members', $members);
				
				$workers = DAO_Worker::getAllActive();
				$tpl->assign('workers', $workers);
				
				$responsibilities = $group->getResponsibilities();
				$tpl->assign('responsibilities', $responsibilities);
				
				$tpl->display('devblocks:cerberusweb.core::internal/responsibilities/peek_by_group_editable.tpl');
				break;
		}
	}
	
	function saveResponsibilitiesPopupAction() {
		@$context = DevblocksPlatform::importGPC($_REQUEST['context'], 'string', '');
		@$context_id = DevblocksPlatform::importGPC($_REQUEST['context_id'], 'string', '');

		switch($context) {
			case CerberusContexts::CONTEXT_GROUP:
				@$responsibilities = DevblocksPlatform::importGPC($_REQUEST['responsibilities'], 'array', array());
				
				if(false == ($group = DAO_Group::get($context_id)))
					return;
				
				$group->setResponsibilities($responsibilities);
				
				break;
		}
	}
}
endif;