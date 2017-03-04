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
 * IMPORTANT LICENSING NOTE from your friends at Cerb
 *
 * Sure, it would be really easy to just cheat and edit this file to use
 * Cerb without paying for a license.  We trust you anyway.
 *
 * It takes a significant amount of time and money to develop, maintain,
 * and support high-quality enterprise software with a dedicated team.
 * For Cerb's entire history we've avoided taking money from outside
 * investors, and instead we've relied on actual sales from satisfied
 * customers to keep the project running.
 *
 * We've never believed in hiding our source code out of paranoia over not
 * getting paid.  We want you to have the full source code and be able to
 * make the tweaks your organization requires to get more done -- despite
 * having less of everything than you might need (time, people, money,
 * energy).  We shouldn't be your bottleneck.
 *
 * As a legitimate license owner, your feedback will help steer the project.
 * We'll also prioritize your issues, and work closely with you to make sure
 * your teams' needs are being met.
 *
 * - Jeff Standen and Dan Hildebrandt
 *	 Founders at Webgroup Media LLC; Developers of Cerb
 */

if (class_exists('Extension_AppPreBodyRenderer',true)):
	class ChTimeTrackingPreBodyRenderer extends Extension_AppPreBodyRenderer {
		function render() {
			$tpl = DevblocksPlatform::getTemplateService();
			$tpl->assign('current_timestamp', time());
			$tpl->display('devblocks:cerberusweb.timetracking::timetracking/renderers/prebody.tpl');
		}
	};
endif;

if (class_exists('Extension_ContextProfileScript')):
class ChTimeTrackingProfileScript extends Extension_ContextProfileScript {
	const ID = 'timetracking.profile_script.timer';
	
	function renderScript($context, $context_id) {
		$tpl = DevblocksPlatform::getTemplateService();

		$tpl->assign('page_context', $context);
		$tpl->assign('page_context_id', $context_id);

		$tpl->display('devblocks:cerberusweb.timetracking::timetracking/renderers/toolbar_timer.js.tpl');
	}
}
endif;

if (class_exists('Extension_ReplyToolbarItem',true)):
	class ChTimeTrackingReplyToolbarTimer extends Extension_ReplyToolbarItem {
		function render(Model_Message $message) {
			$tpl = DevblocksPlatform::getTemplateService();
			
			$tpl->assign('message', $message); /* @var $message Model_Message */
			
			$tpl->display('devblocks:cerberusweb.timetracking::timetracking/renderers/tickets/reply_toolbar_timer.tpl');
		}
	};
endif;

class ChTimeTrackingEventListener extends DevblocksEventListenerExtension {
	/**
	 * @param Model_DevblocksEvent $event
	 */
	function handleEvent(Model_DevblocksEvent $event) {
		switch($event->id) {
			case 'cron.maint':
				//DAO_TimeTrackingActivity::maint();
				DAO_TimeTrackingEntry::maint();
				break;
				
			case 'ticket.action.merge':
				$new_ticket_id = $event->params['new_ticket_id'];
				$old_ticket_ids = $event->params['old_ticket_ids'];
				
				// [TODO]
				break;
		}
	}
};

class ChTimeTrackingPage extends CerberusPageExtension {
	function isVisible() {
		// The current session must be a logged-in worker to use this page.
		if(null == ($worker = CerberusApplication::getActiveWorker()))
			return false;
		return true;
	}
	
	function render() {
	}
	
	private function _startTimer() {
		if(!isset($_SESSION['timetracking_started'])) {
			$_SESSION['timetracking_started'] = time();
		}
	}
	
	private function _stopTimer() {
		@$time = intval($_SESSION['timetracking_started']);
		
		// If a timer was running
		if(!empty($time)) {
			$elapsed = time() - $time;
			unset($_SESSION['timetracking_started']);
			@$_SESSION['timetracking_total'] = intval($_SESSION['timetracking_total']) + $elapsed;
		}

		@$total = $_SESSION['timetracking_total'];
		if(empty($total))
			return false;
		
		return $total;
	}
	
	private function _destroyTimer() {
		unset($_SESSION['timetracking_context']);
		unset($_SESSION['timetracking_context_id']);
		unset($_SESSION['timetracking_started']);
		unset($_SESSION['timetracking_total']);
		unset($_SESSION['timetracking_link']);
	}
	
	function startTimerAction() {
		@$context = urldecode(DevblocksPlatform::importGPC($_REQUEST['context'],'string',''));
		@$context_id = intval(DevblocksPlatform::importGPC($_REQUEST['context_id'],'integer',0));
		
		if(!empty($context) && !isset($_SESSION['timetracking_context'])) {
			$_SESSION['timetracking_context'] = $context;
			$_SESSION['timetracking_context_id'] = $context_id;
		}
		
		$this->_startTimer();
	}
	
	function pauseTimerJsonAction() {
		header("Content-Type: application/json");
		
		$total_secs = $this->_stopTimer();
		
		echo json_encode(array(
			'status' => true,
			'total_mins' => ceil($total_secs/60),
		));
		exit;
	}
	
	function viewMarkClosedAction() {
		@$view_id = DevblocksPlatform::importGPC($_REQUEST['view_id'],'string');
		
		@$row_ids = DevblocksPlatform::importGPC($_REQUEST['row_id'],'array',array());

		try {
			if(is_array($row_ids))
			foreach($row_ids as $row_id) {
				$row_id = intval($row_id);
				
				if(!empty($row_id))
					DAO_TimeTrackingEntry::update($row_id, array(
						DAO_TimeTrackingEntry::IS_CLOSED => 1,
					));
			}
		} catch (Exception $e) {
			//
		}
		
		$view = C4_AbstractViewLoader::getView($view_id);
		$view->setAutoPersist(false);
		$view->render();
		
		exit;
	}
	
	function viewTimeExploreAction() {
		@$view_id = DevblocksPlatform::importGPC($_REQUEST['view_id'],'string');
		
		$active_worker = CerberusApplication::getActiveWorker();
		$url_writer = DevblocksPlatform::getUrlService();
		
		// Generate hash
		$hash = md5($view_id.$active_worker->id.time());
		
		// Loop through view and get IDs
		$view = C4_AbstractViewLoader::getView($view_id);
		$view->setAutoPersist(false);

		// Page start
		@$explore_from = DevblocksPlatform::importGPC($_REQUEST['explore_from'],'integer',0);
		if(empty($explore_from)) {
			$orig_pos = 1+($view->renderPage * $view->renderLimit);
		} else {
			$orig_pos = 1;
		}

		$view->renderPage = 0;
		$view->renderLimit = 250;
		$pos = 0;
		
		do {
			$models = array();
			list($results, $total) = $view->getData();

			// Summary row
			if(0==$view->renderPage) {
				$model = new Model_ExplorerSet();
				$model->hash = $hash;
				$model->pos = $pos++;
				$model->params = array(
					'title' => $view->name,
					'created' => time(),
//					'worker_id' => $active_worker->id,
					'total' => $total,
					'return_url' => isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : $url_writer->writeNoProxy('c=search&type=time_entry', true),
//					'toolbar_extension_id' => 'cerberusweb.explorer.toolbar.',
				);
				$models[] = $model;
				
				$view->renderTotal = false; // speed up subsequent pages
			}
			
			if(is_array($results))
			foreach($results as $opp_id => $row) {
				if($opp_id==$explore_from)
					$orig_pos = $pos;
				
				$model = new Model_ExplorerSet();
				$model->hash = $hash;
				$model->pos = $pos++;
				$model->params = array(
					'id' => $row[SearchFields_TimeTrackingEntry::ID],
					'url' => $url_writer->writeNoProxy(sprintf("c=profiles&type=time_tracking&id=%d", $row[SearchFields_TimeTrackingEntry::ID]), true),
				);
				$models[] = $model;
			}
			
			DAO_ExplorerSet::createFromModels($models);
			
			$view->renderPage++;
			
		} while(!empty($results));
		
		DevblocksPlatform::redirect(new DevblocksHttpResponse(array('explore',$hash,$orig_pos)));
	}
	
	function clearEntryAction() {
		$this->_destroyTimer();
	}
};

if(class_exists('Extension_PageSection')):
class ChTimeTracking_SetupPageSection extends Extension_PageSection {
	const ID = 'timetracking.setup.section.timetracking';
	
	function render() {
		$settings = DevblocksPlatform::getPluginSettingsService();
		$tpl = DevblocksPlatform::getTemplateService();

		$activities = DAO_TimeTrackingActivity::getWhere();
		$tpl->assign('activities', $activities);
		
		$tpl->display('devblocks:cerberusweb.timetracking::config/activities/index.tpl');
	}

	function saveAction() {
		$settings = DevblocksPlatform::getPluginSettingsService();
		@$plugin_id = DevblocksPlatform::importGPC($_REQUEST['plugin_id'],'string');

		@$id = DevblocksPlatform::importGPC($_REQUEST['id'],'integer',0);
		@$name = DevblocksPlatform::importGPC($_REQUEST['name'],'string','');
		@$do_delete = DevblocksPlatform::importGPC($_REQUEST['do_delete'],'integer',0);

		if(empty($name)) {
			$name = "(no name)";
		}
		
		if(empty($id)) { // Add
			$fields = array(
				DAO_TimeTrackingActivity::NAME => $name,
			);
			$activity_id = DAO_TimeTrackingActivity::create($fields);
			
		} else { // Edit
			if($do_delete) { // Delete
				DAO_TimeTrackingActivity::delete($id);
				
			} else { // Modify
				$fields = array(
					DAO_TimeTrackingActivity::NAME => $name,
				);
				DAO_TimeTrackingActivity::update($id, $fields);
			}
			
		}
		
		DevblocksPlatform::redirect(new DevblocksHttpResponse(array('config','timetracking')));
		exit;
	}
	
	function getActivityAction() {
		@$id = DevblocksPlatform::importGPC($_REQUEST['id'], 'integer', 0);
		
		$tpl = DevblocksPlatform::getTemplateService();
		
		if(!empty($id) && null != ($activity = DAO_TimeTrackingActivity::get($id)))
			$tpl->assign('activity', $activity);
		
		$tpl->display('devblocks:cerberusweb.timetracking::config/activities/edit_activity.tpl');
	}
}
endif;

if(class_exists('Extension_PageMenuItem')):
class ChTimeTracking_SetupPluginsMenuItem extends Extension_PageMenuItem {
	const ID = 'timetracking.setup.menu.plugins.timetracking';
	
	function render() {
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->display('devblocks:cerberusweb.timetracking::config/menu_item.tpl');
	}
}
endif;

if (class_exists('Extension_ReportGroup',true)):
class ChReportGroupTimeTracking extends Extension_ReportGroup {
	// [TODO] This stub is pointless and should be refactored out.
};
endif;

