<?php
/***********************************************************************
 | Cerb(tm) developed by Webgroup Media, LLC.
 |-----------------------------------------------------------------------
 | All source code & content (c) Copyright 2013, Webgroup Media LLC
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

class ChReportVirtualAttendantUsage extends Extension_Report {
	function render() {
		$tpl = DevblocksPlatform::getTemplateService();
		$db = DevblocksPlatform::getDatabaseService();

		// Filter: Start + End
		
		@$start = DevblocksPlatform::importGPC($_REQUEST['start'],'string','-30 days');
		@$start_time = strtotime($start);
		$start_time -= $start_time % 86400;
		$tpl->assign('start', $start);
		
		@$end = DevblocksPlatform::importGPC($_REQUEST['end'],'string','now');
		@$end_time = strtotime($end);
		$end_time -= $end_time % 86400;
		$tpl->assign('end', $end);
		
		@$sort_by = DevblocksPlatform::importGPC($_REQUEST['sort_by'],'string','uses');
		if(!in_array($sort_by, array('uses', 'avg_elapsed_ms', 'elapsed_ms', 'va_name', 'va_owner', 'event')))
			$sort_by = 'uses';
		$tpl->assign('sort_by', $sort_by);

		// Scope
		
		$event_mfts = Extension_DevblocksEvent::getAll(false);

		// Data
		
		$sql = sprintf("SELECT trigger_event.id, trigger_event.title, trigger_event.virtual_attendant_id, trigger_event.event_point, ".
			"SUM(trigger_event_history.uses) AS uses, SUM(trigger_event_history.elapsed_ms) AS elapsed_ms ".
			"FROM trigger_event_history ".
			"INNER JOIN trigger_event ON (trigger_event_history.trigger_id=trigger_event.id) ".
			"WHERE trigger_event_history.ts_day BETWEEN %d AND %d ".
			"GROUP BY trigger_event.id ",
			$start_time,
			$end_time
		);
		
		$stats = $db->GetArray(sprintf($sql, 'DESC'));
		
		$vas = DAO_VirtualAttendant::getAll();
		
		foreach($stats as $idx => $stat) {
			// Avg. Runtime
			
			$stats[$idx]['avg_elapsed_ms'] = !empty($stat['uses']) ? intval($stat['elapsed_ms'] / $stat['uses']) : $stat['elapsed_ms'];
			
			// Event
			
			@$event_mft = $event_mfts[$stat['event_point']];
			$stats[$idx]['event'] = !empty($event_mft) ? $event_mft->name : '';
			
			// VA
			
			if(false == (@$va = $vas[$stat['virtual_attendant_id']]))
				continue;
			
			// Owner

			$meta = $va->getOwnerMeta();
			
			$stats[$idx]['va_id'] = $va->id;
			$stats[$idx]['va_name'] = $va->name;
			$stats[$idx]['va_owner'] = sprintf("%s%s", $meta['context_ext']->manifest->name, (!empty($meta['name']) ? (': '.$meta['name']) : ''));
		}
		
		// Sort
		
		$sort_asc = false;
		switch($sort_by) {
			case 'event':
			case 'va_name':
			case 'va_owner':
				$sort_asc = true;
				break;
		}
		
		DevblocksPlatform::sortObjects($stats, sprintf('[%s]', $sort_by), $sort_asc);
		
		// Render
		
		$tpl->assign('stats', $stats);
		
		$tpl->display('devblocks:cerberusweb.reports::reports/va/va_usage/index.tpl');
	}
};