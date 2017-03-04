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

class PageSection_SetupCustomFields extends Extension_PageSection {
	function render() {
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->display('devblocks:cerberusweb.core::configuration/section/fields/index.tpl');
	}
	
	function showFieldsTabAction() {
		$tpl = DevblocksPlatform::getTemplateService();
		$visit = CerberusApplication::getVisit();
		
		$visit->set(ChConfigurationPage::ID, 'fields');
		
		$context_manifests = Extension_DevblocksContext::getAll(false, array('custom_fields'));
		$tpl->assign('context_manifests', $context_manifests);
		
		$tpl->display('devblocks:cerberusweb.core::configuration/section/fields/fields/index.tpl');
	}
	
	function showFieldsetsTabAction() {
		$tpl = DevblocksPlatform::getTemplateService();

		$defaults = C4_AbstractViewModel::loadFromClass('View_CustomFieldset');
		$defaults->id = 'cfg_fieldsets';
		$defaults->renderSubtotals = SearchFields_CustomFieldset::CONTEXT;
		
		$view = C4_AbstractViewLoader::getView($defaults->id, $defaults);
		$tpl->assign('view', $view);
		
		$tpl->display('devblocks:cerberusweb.core::internal/views/search_and_view.tpl');
	}
	
	private function _getRecordType($ext_id) {
		$tpl = DevblocksPlatform::getTemplateService();
		
		$tpl->assign('ext_id', $ext_id);

		//  Make sure the extension exists before continuing
		if(false == ($context_manifest = DevblocksPlatform::getExtension($ext_id, false)))
			return;
		
		$tpl->assign('context_manifest', $context_manifest);
		
		$types = Model_CustomField::getTypes();
		$tpl->assign('types', $types);

		// Look up the defined global fields by the given extension
		$fields = DAO_CustomField::getByContext($ext_id, false);
		$tpl->assign('fields', $fields);
		
		// Get the custom fieldsets for this type (visible to owner)
		$fieldsets = DAO_CustomFieldset::getByContext($ext_id);
		$tpl->assign('fieldsets', $fieldsets);
		
		$contexts = Extension_DevblocksContext::getAll(false, array('workspace'));
		$tpl->assign('contexts', $contexts);
		
		$tpl->display('devblocks:cerberusweb.core::configuration/section/fields/edit_source.tpl');
	}
	
	// Ajax
	function getRecordTypeAction() {
		$translate = DevblocksPlatform::getTranslationService();
		$worker = CerberusApplication::getActiveWorker();
		
		if(!$worker || !$worker->is_superuser) {
			echo $translate->_('common.access_denied');
			return;
		}
		
		@$ext_id = DevblocksPlatform::importGPC($_REQUEST['ext_id']);
		$this->_getRecordType($ext_id);
	}
	
	function saveRecordTypeAction() {
		$translate = DevblocksPlatform::getTranslationService();
		
		$worker = CerberusApplication::getActiveWorker();
		if(!$worker || !$worker->is_superuser) {
			echo $translate->_('common.access_denied');
			return;
		}
		
		// Type of custom fields
		@$ext_id = DevblocksPlatform::importGPC($_POST['ext_id'],'string','');
		
		// Properties
		@$submit = DevblocksPlatform::importGPC($_POST['submit'],'string','');
		@$ids = DevblocksPlatform::importGPC($_POST['ids'],'array',array());
		@$names = DevblocksPlatform::importGPC($_POST['names'],'array',array());
		@$params = DevblocksPlatform::importGPC($_POST['params'],'array',array());
		@$selected = DevblocksPlatform::importGPC($_POST['selected'],'array',array());
		
		if(!empty($ids) && !empty($ext_id))
		foreach($ids as $idx => $id) {
			@$name = $names[$idx];
			@$order = $idx;

			// Are we deleting this field?
			$is_delete = ($submit == 'delete' && in_array($id, $selected)) ? true : false;
			
			if($is_delete) {
				DAO_CustomField::delete($id);
				
			} else {
				$fields = array(
					DAO_CustomField::NAME => $name,
					DAO_CustomField::POS => $order,
				);
				
				if(isset($params[$id]['options']))
					$params[$id]['options'] = DevblocksPlatform::parseCrlfString($params[$id]['options']);
				
				if(isset($params[$id]))
					$fields[DAO_CustomField::PARAMS_JSON] = json_encode($params[$id]);
				else
					$fields[DAO_CustomField::PARAMS_JSON] = json_encode(array());
				
				// Handle moves to fieldset
				@$move_to_fieldset_id = DevblocksPlatform::importGPC($_POST['move_to_fieldset_id'],'integer',0);
				
				if($submit == 'move' && $move_to_fieldset_id && in_array($id, $selected)) {
					$fields[DAO_CustomField::CUSTOM_FIELDSET_ID] = $move_to_fieldset_id;
					
					// Set up links for the custom field
					DAO_CustomFieldset::linkToContextsByFieldValues($move_to_fieldset_id, $id);
				}
				
				DAO_CustomField::update($id, $fields);
			}
		}
		
		// Adding
		@$add_name = DevblocksPlatform::importGPC($_POST['add_name'],'string','');
		@$add_type = DevblocksPlatform::importGPC($_POST['add_type'],'string','');
		
		if(!empty($add_name) && !empty($add_type)) {
			$fields = array(
				DAO_CustomField::NAME => $add_name,
				DAO_CustomField::TYPE => $add_type,
				DAO_CustomField::CUSTOM_FIELDSET_ID => 0,
				DAO_CustomField::CONTEXT => $ext_id,
				DAO_CustomField::PARAMS_JSON => '',
				DAO_CustomField::POS => 99,
			);
			$id = DAO_CustomField::create($fields);
		}

		// Redraw the form
		$this->_getRecordType($ext_id);
	}
};