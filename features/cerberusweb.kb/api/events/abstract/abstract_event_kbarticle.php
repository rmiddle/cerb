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
 
abstract class AbstractEvent_KbArticle extends Extension_DevblocksEvent {
	protected $_event_id = null; // override

	/**
	 *
	 * @param integer $context_id
	 * @return Model_DevblocksEvent
	 */
	function generateSampleEventModel(Model_TriggerEvent $trigger, $context_id=null) {
		
		if(empty($context_id)) {
			// Pull the latest record
			list($results) = DAO_KbArticle::search(
				array(),
				array(),
				10,
				0,
				SearchFields_KbArticle::ID,
				false,
				false
			);
			
			shuffle($results);
			
			$result = array_shift($results);
			
			$context_id = $result[SearchFields_KbArticle::ID];
		}
		
		return new Model_DevblocksEvent(
			$this->_event_id,
			array(
				'context_id' => $context_id,
			)
		);
	}
	
	function setEvent(Model_DevblocksEvent $event_model=null, Model_TriggerEvent $trigger=null) {
		$labels = array();
		$values = array();
		
		/**
		 * Behavior
		 */
		
		$merge_labels = array();
		$merge_values = array();
		CerberusContexts::getContext(CerberusContexts::CONTEXT_BEHAVIOR, $trigger, $merge_labels, $merge_values, null, true);

			// Merge
			CerberusContexts::merge(
				'behavior_',
				'',
				$merge_labels,
				$merge_values,
				$labels,
				$values
			);

		// We can accept a model object or a context_id
		@$model = $event_model->params['context_model'] ?: $event_model->params['context_id'];
		
		/**
		 * KB Article
		 */
		
		$merge_labels = array();
		$merge_values = array();
		CerberusContexts::getContext(CerberusContexts::CONTEXT_KB_ARTICLE, $model, $merge_labels, $merge_values, null, true);

			// Merge
			CerberusContexts::merge(
				'article_',
				'',
				$merge_labels,
				$merge_values,
				$labels,
				$values
			);

		/**
		 * Return
		 */

		$this->setLabels($labels);
		$this->setValues($values);
	}
	
	function renderSimulatorTarget($trigger, $event_model) {
		$context = CerberusContexts::CONTEXT_KB_ARTICLE;
		$context_id = $event_model->params['context_id'];
		DevblocksEventHelper::renderSimulatorTarget($context, $context_id, $trigger, $event_model);
	}
	
	function getValuesContexts($trigger) {
		$vals = array(
			'behavior_id' => array(
				'label' => 'Behavior',
				'context' => CerberusContexts::CONTEXT_BEHAVIOR,
			),
			'behavior_bot_id' => array(
				'label' => 'Bot',
				'context' => CerberusContexts::CONTEXT_BOT,
			),
			'article_id' => array(
				'label' => 'Article',
				'context' => CerberusContexts::CONTEXT_KB_ARTICLE,
			),
			'article_watchers' => array(
				'label' => 'Article watchers',
				'context' => CerberusContexts::CONTEXT_WORKER,
				'is_multiple' => true,
			),
		);
		
		$vars = parent::getValuesContexts($trigger);
		
		$vals_to_ctx = array_merge($vals, $vars);
		DevblocksPlatform::sortObjects($vals_to_ctx, '[label]');
		
		return $vals_to_ctx;
	}
	
	function getConditionExtensions(Model_TriggerEvent $trigger) {
		$labels = $this->getLabels($trigger);
		$types = $this->getTypes();
		
		$labels['article_link'] = 'Article is linked';
		$labels['article_watcher_count'] = 'Article watcher count';
		
		$types['article_link'] = null;
		$types['article_watcher_count'] = null;
		
		$conditions = $this->_importLabelsTypesAsConditions($labels, $types);
		
		return $conditions;
	}
	
	function renderConditionExtension($token, $as_token, $trigger, $params=array(), $seq=null) {
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->assign('params', $params);

		if(!is_null($seq))
			$tpl->assign('namePrefix','condition'.$seq);
		
		switch($as_token) {
			case 'article_link':
				$contexts = Extension_DevblocksContext::getAll(false);
				$tpl->assign('contexts', $contexts);
				$tpl->display('devblocks:cerberusweb.core::events/condition_link.tpl');
				break;
				
			case 'article_watcher_count':
				$tpl->display('devblocks:cerberusweb.core::internal/decisions/conditions/_number.tpl');
				break;
		}

		$tpl->clearAssign('namePrefix');
		$tpl->clearAssign('params');
	}
	
	function runConditionExtension($token, $as_token, $trigger, $params, DevblocksDictionaryDelegate $dict) {
		$pass = true;
		
		switch($as_token) {
			case 'article_link':
				$not = (substr($params['oper'],0,1) == '!');
				$oper = ltrim($params['oper'],'!');
				
				$from_context = null;
				$from_context_id = null;
				
				switch($as_token) {
					case 'article_link':
						$from_context = CerberusContexts::CONTEXT_KB_ARTICLE;
						@$from_context_id = $dict->article_id;
						break;
					default:
						$pass = false;
				}
				
				// Get links by context+id

				if(!empty($from_context) && !empty($from_context_id)) {
					@$context_strings = $params['context_objects'];
					$links = DAO_ContextLink::intersect($from_context, $from_context_id, $context_strings);
					
					// OPER: any, !any, all
	
					switch($oper) {
						case 'in':
							$pass = (is_array($links) && !empty($links));
							break;
						case 'all':
							$pass = (is_array($links) && count($links) == count($context_strings));
							break;
						default:
							$pass = false;
							break;
					}
					
					$pass = ($not) ? !$pass : $pass;
					
				} else {
					$pass = false;
				}
				break;
				
			case 'article_watcher_count':
				$not = (substr($params['oper'],0,1) == '!');
				$oper = ltrim($params['oper'],'!');
				$value = count($dict->article_watchers);
				
				switch($oper) {
					case 'is':
						$pass = intval($value)==intval($params['value']);
						break;
					case 'gt':
						$pass = intval($value) > intval($params['value']);
						break;
					case 'lt':
						$pass = intval($value) < intval($params['value']);
						break;
				}
				
				$pass = ($not) ? !$pass : $pass;
				break;
				
			default:
				$pass = false;
				break;
		}
		
		return $pass;
	}
	
	function getActionExtensions(Model_TriggerEvent $trigger) {
		$actions =
			array(
				'add_watchers' => array('label' =>'Add watchers'),
				'create_comment' => array('label' =>'Create comment'),
				'create_notification' => array('label' =>'Create notification'),
				'create_task' => array('label' =>'Create task'),
				'create_ticket' => array('label' =>'Create ticket'),
				'send_email' => array('label' => 'Send email'),
				'set_links' => array('label' => 'Set links'),
			)
			+ DevblocksEventHelper::getActionCustomFieldsFromLabels($this->getLabels($trigger))
			;
			
		return $actions;
	}
	
	function renderActionExtension($token, $trigger, $params=array(), $seq=null) {
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->assign('params', $params);

		if(!is_null($seq))
			$tpl->assign('namePrefix','action'.$seq);

		$labels = $this->getLabels($trigger);
		$tpl->assign('token_labels', $labels);
			
		switch($token) {
			case 'add_watchers':
				DevblocksEventHelper::renderActionAddWatchers($trigger);
				break;
			
			case 'create_comment':
				DevblocksEventHelper::renderActionCreateComment($trigger);
				break;
				
			case 'create_notification':
				DevblocksEventHelper::renderActionCreateNotification($trigger);
				break;
				
			case 'create_task':
				DevblocksEventHelper::renderActionCreateTask($trigger);
				break;
				
			case 'create_ticket':
				DevblocksEventHelper::renderActionCreateTicket($trigger);
				break;
				
			case 'send_email':
				DevblocksEventHelper::renderActionSendEmail($trigger);
				break;
				
			case 'set_links':
				DevblocksEventHelper::renderActionSetLinks($trigger);
				break;
				
			default:
				if(preg_match('#set_cf_(.*?_*)custom_([0-9]+)#', $token, $matches)) {
					$field_id = $matches[2];
					$custom_field = DAO_CustomField::get($field_id);
					DevblocksEventHelper::renderActionSetCustomField($custom_field, $trigger);
				}
				break;
		}
		
		$tpl->clearAssign('params');
		$tpl->clearAssign('namePrefix');
		$tpl->clearAssign('token_labels');
	}
	
	function simulateActionExtension($token, $trigger, $params, DevblocksDictionaryDelegate $dict) {
		@$article_id = $dict->article_id;

		if(empty($article_id))
			return;
		
		switch($token) {
			case 'add_watchers':
				return DevblocksEventHelper::simulateActionAddWatchers($params, $dict, 'article_id');
				break;
			
			case 'create_comment':
				return DevblocksEventHelper::simulateActionCreateComment($params, $dict, 'article_id');
				break;
				
			case 'create_notification':
				return DevblocksEventHelper::simulateActionCreateNotification($params, $dict, 'article_id');
				break;
				
			case 'create_task':
				return DevblocksEventHelper::simulateActionCreateTask($params, $dict, 'article_id');
				break;

			case 'create_ticket':
				return DevblocksEventHelper::simulateActionCreateTicket($params, $dict, 'article_id');
				break;
				
			case 'send_email':
				DevblocksEventHelper::simulateActionSendEmail($params, $dict);
				break;
				
			case 'set_links':
				return DevblocksEventHelper::simulateActionSetLinks($trigger, $params, $dict);
				break;
				
			default:
				if(preg_match('#set_cf_(.*?_*)custom_([0-9]+)#', $token))
					return DevblocksEventHelper::simulateActionSetCustomField($token, $params, $dict);
				break;
		}
	}
	
	function runActionExtension($token, $trigger, $params, DevblocksDictionaryDelegate $dict) {
		@$article_id = $dict->article_id;

		if(empty($article_id))
			return;
		
		switch($token) {
			case 'add_watchers':
				DevblocksEventHelper::runActionAddWatchers($params, $dict, 'article_id');
				break;
			
			case 'create_comment':
				DevblocksEventHelper::runActionCreateComment($params, $dict, 'article_id');
				break;
				
			case 'create_notification':
				DevblocksEventHelper::runActionCreateNotification($params, $dict, 'article_id');
				break;
				
			case 'create_task':
				DevblocksEventHelper::runActionCreateTask($params, $dict, 'article_id');
				break;

			case 'create_ticket':
				DevblocksEventHelper::runActionCreateTicket($params, $dict, 'article_id');
				break;
				
			case 'send_email':
				DevblocksEventHelper::runActionSendEmail($params, $dict);
				break;
				
			case 'set_links':
				DevblocksEventHelper::runActionSetLinks($trigger, $params, $dict);
				break;
				
			default:
				if(preg_match('#set_cf_(.*?_*)custom_([0-9]+)#', $token))
					return DevblocksEventHelper::runActionSetCustomField($token, $params, $dict);
				break;
		}
	}
	
};