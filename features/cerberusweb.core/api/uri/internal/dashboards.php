<?php
/***********************************************************************
 | Cerb(tm) developed by WebGroup Media, LLC.
 |-----------------------------------------------------------------------
 | All source code & content (c) Copyright 2012, WebGroup Media LLC
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

if(class_exists('Extension_PageSection')):
class PageSection_InternalDashboards extends Extension_PageSection {
	function render() {}
	
	function renderWidgetAction() {
		@$widget_id = DevblocksPlatform::importGPC($_REQUEST['widget_id'], 'integer', 0);
		
		$tpl = DevblocksPlatform::getTemplateService();
				
		if(!empty($widget_id) && null != ($widget = DAO_WorkspaceWidget::get($widget_id))) {
			$tpl->assign('widget', $widget);
			$tpl->display('devblocks:cerberusweb.core::internal/workspaces/widgets/render.tpl');
		}
	}
	
	function showWidgetPopupAction() {
		@$widget_id = DevblocksPlatform::importGPC($_REQUEST['widget_id'], 'integer', 0);
		
		$tpl = DevblocksPlatform::getTemplateService();

		if(empty($widget_id)) {
			// [TODO] Verify this ID
			@$workspace_tab_id = DevblocksPlatform::importGPC($_REQUEST['workspace_tab_id'], 'integer', 0);
			$tpl->assign('workspace_tab_id', $workspace_tab_id);
			
			$widget_extensions = Extension_WorkspaceWidget::getAll(false);
			$tpl->assign('widget_extensions', $widget_extensions);
			
			$tpl->display('devblocks:cerberusweb.core::internal/workspaces/widgets/add.tpl');
			
		} else {
			if(null != ($widget = DAO_WorkspaceWidget::get($widget_id))) {
				$tpl->assign('widget', $widget);
				
				if(null != ($extension = Extension_WorkspaceWidget::get($widget->extension_id))) {
					$tpl->assign('extension', $extension);
					$tpl->display('devblocks:cerberusweb.core::internal/workspaces/widgets/peek.tpl');
				}
			}
		}
	}
	
	function saveWidgetPopupAction() {
		@$id = DevblocksPlatform::importGPC($_REQUEST['id'], 'integer', 0);
		@$label = DevblocksPlatform::importGPC($_REQUEST['label'], 'string', 'Widget');
		@$do_delete = DevblocksPlatform::importGPC($_REQUEST['do_delete'], 'integer', 0);

		if(!empty($id) && !empty($do_delete)) {
			DAO_WorkspaceWidget::delete($id);
			
		} else {
			$fields = array(
				DAO_WorkspaceWidget::LABEL => $label,
			);
			
			if(null != ($widget = DAO_WorkspaceWidget::get($id))) {
				DAO_WorkspaceWidget::update($widget->id, $fields);
				
				if(null != ($widget_extension = Extension_WorkspaceWidget::get($widget->extension_id))) {
					$widget_extension->saveConfig($widget);
				}
			}
		}
	}
	
	function addWidgetPopupJsonAction() {
		@$extension_id = DevblocksPlatform::importGPC($_REQUEST['extension_id'], 'string', null);
		@$workspace_tab_id = DevblocksPlatform::importGPC($_REQUEST['workspace_tab_id'], 'integer', 0);
		
		header('Content-Type: application/json');
		
		if(empty($extension_id) || null == ($extension = Extension_WorkspaceWidget::get($extension_id))) {
			echo json_encode(false);
			return;
		}
		
		if(empty($workspace_tab_id)) {
			echo json_encode(false);
			return;
		}
		
		$widget_id = DAO_WorkspaceWidget::create(array(
			DAO_WorkspaceWidget::LABEL => 'New widget',
			DAO_WorkspaceWidget::EXTENSION_ID => $extension_id,
			DAO_WorkspaceWidget::WORKSPACE_TAB_ID => $workspace_tab_id,
			DAO_WorkspaceWidget::POS => '0000',
		));
		
		echo json_encode(array(
			'widget_id' => $widget_id,
			'widget_extension_id' => $extension_id,
			'widget_tab_id' => $workspace_tab_id,
		));
	}
	
	function getContextFieldsJsonAction() {
		@$context = DevblocksPlatform::importGPC($_REQUEST['context'], 'string', null);
		
		header('Content-Type: application/json');
		
		if(null == ($context_ext = Extension_DevblocksContext::get($context))) {
			echo json_encode(false);
			return;
		}

		$view_class = $context_ext->getViewClass();
		
		if(null == ($view = new $view_class())) { /* @var $view C4_AbstractView */
			echo json_encode(false);
			return;
		}
		
		$results = array();
		$params_avail = $view->getParamsAvailable();

		if(is_array($params_avail))
		foreach($params_avail as $param) { /* @var $param DevblocksSearchField */
			if(empty($param->db_label))
				continue;
		
			$results[] = array(
				'key' => $param->token,
				'label' => mb_convert_case($param->db_label, MB_CASE_LOWER),
				'type' => $param->type
			);
		}
		
		echo json_encode($results);
	}
	
	function setWidgetPositionsAction() {
		@$workspace_tab_id = DevblocksPlatform::importGPC($_REQUEST['workspace_tab_id'], 'integer', 0);
		@$columns = DevblocksPlatform::importGPC($_REQUEST['column'], 'array', array());

		if(is_array($columns))
		foreach($columns as $idx => $widget_ids) {
			foreach(DevblocksPlatform::parseCsvString($widget_ids) as $n => $widget_id) {
				$pos = sprintf("%d%03d", $idx, $n);
				
				DAO_WorkspaceWidget::update($widget_id, array(
					DAO_WorkspaceWidget::POS => $pos,
				));
			}
			
			// [TODO] Kill cache on dashboard
		}
	}
	
	function getWidgetDatasourceConfigAction() {
		@$widget_id = DevblocksPlatform::importGPC($_REQUEST['widget_id'], 'string', '');
		@$params_prefix = DevblocksPlatform::importGPC($_REQUEST['params_prefix'], 'string', null);
		@$ext_id = DevblocksPlatform::importGPC($_REQUEST['ext_id'], 'string', '');

		if(null == ($widget = DAO_WorkspaceWidget::get($widget_id)))
			return;
		
		if(null == ($datasource_ext = Extension_WorkspaceWidgetDatasource::get($ext_id)))
			return;
		
		$datasource_ext->renderConfig($widget, $widget->params, $params_prefix);
	}
}
endif;

if(class_exists('Extension_WorkspaceTab')):
class WorkspaceTab_Dashboards extends Extension_WorkspaceTab {
	public function renderTab(Model_WorkspacePage $page, Model_WorkspaceTab $tab) {
		$tpl = DevblocksPlatform::getTemplateService();
		
		$tpl->assign('workspace_page', $page);
		$tpl->assign('workspace_tab', $tab);
		
		$widget_extensions = Extension_WorkspaceWidget::getAll();
		$tpl->assign('widget_extensions', $widget_extensions);
		
		// Get by workspace tab
		// [TODO] Cache
		$widgets = DAO_WorkspaceWidget::getWhere(
				sprintf("%s = %d",
					DAO_WorkspaceWidget::WORKSPACE_TAB_ID,
					$tab->id
				),
				DAO_WorkspaceWidget::POS,
				true
		);

		$columns = array();

		// [TODO] If the col_idx is greater than the number of cols on this dashboard,
		//   move widget to first col
		
		if(is_array($widgets))
		foreach($widgets as $widget) { /* @var $widget Model_WorkspaceWidget */
			$pos = !empty($widget->pos) ? $widget->pos : '0000';
			$col_idx = substr($pos,0,1);
			$n = substr($pos,1);
			
			if(!isset($columns[$col_idx]))
				$columns[$col_idx] = array();
			
			$columns[$col_idx][$widget->id] = $widget;
		}

		unset($widgets);
		
		$tpl->assign('columns', $columns);
		
		$tpl->display('devblocks:cerberusweb.core::internal/workspaces/widgets/tab.tpl');
	}
}
endif;

class WorkspaceWidget_Gauge extends Extension_WorkspaceWidget {
	function render(Model_WorkspaceWidget $widget) {
		$tpl = DevblocksPlatform::getTemplateService();

		// Per series datasources
		@$datasource_extid = $widget->params['datasource'];

		if(empty($datasource_extid)) {
			echo "This gauge doesn't have a data source. Configure it and select one.";
			return;
		}
		
		if(null == ($datasource_ext = Extension_WorkspaceWidgetDatasource::get($datasource_extid)))
			return;
		
		$data = $datasource_ext->getData($widget, $widget->params);
		
		if(!empty($data))
			$widget->params = $data;
		
		$tpl->assign('widget', $widget);
		
		$tpl->display('devblocks:cerberusweb.core::internal/workspaces/widgets/gauge/gauge.tpl');
	}
	
	// Config
	
	function renderConfig(Model_WorkspaceWidget $widget) {
		if(empty($widget))
			return;
		
		$tpl = DevblocksPlatform::getTemplateService();
		
		// Widget
		
		$tpl->assign('widget', $widget);
		
		// Limit to widget
		
		$datasource_mfts = Extension_WorkspaceWidgetDatasource::getAll(false, $this->manifest->id);
		$tpl->assign('datasource_mfts', $datasource_mfts);
		
		// Template
		
		$tpl->display('devblocks:cerberusweb.core::internal/workspaces/widgets/gauge/config.tpl');
	}
	
	function saveConfig(Model_WorkspaceWidget $widget) {
		@$params = DevblocksPlatform::importGPC($_REQUEST['params'], 'array', array());
		
		if(isset($params['threshold_values']))
		foreach($params['threshold_values'] as $idx => $val) {
			if(empty($val)) {
				unset($params['threshold_values'][$idx]);
				continue;
			}
			
			@$label = $params['threshold_labels'][$idx];
			
			if(empty($label))
				$params['threshold_labels'][$idx] = $val;
			
			@$color = $params['threshold_colors'][$idx];
			
			if(empty($color))
				$params['threshold_colors'][$idx] = sprintf("#%s%s%s",
					dechex(mt_rand(0,255)),
					dechex(mt_rand(0,255)),
					dechex(mt_rand(0,255))
				);
		}
		
		DAO_WorkspaceWidget::update($widget->id, array(
			DAO_WorkspaceWidget::PARAMS_JSON => json_encode($params),
		));

		// Clear caches
		$cache = DevblocksPlatform::getCacheService();
		$cache->remove(sprintf("widget%d_datasource", $widget->id));
	}
};

class WorkspaceWidget_Counter extends Extension_WorkspaceWidget {
	function render(Model_WorkspaceWidget $widget) {
		$tpl = DevblocksPlatform::getTemplateService();

		// Per series datasources
		@$datasource_extid = $widget->params['datasource'];

		if(empty($datasource_extid)) {
			echo "This counter doesn't have a data source. Configure it and select one.";
			return;
		}
		
		if(null == ($datasource_ext = Extension_WorkspaceWidgetDatasource::get($datasource_extid)))
			return;
		
		$data = $datasource_ext->getData($widget, $widget->params);

		if(!empty($data))
			$widget->params = $data;
		
		$tpl->assign('widget', $widget);
		
		$tpl->display('devblocks:cerberusweb.core::internal/workspaces/widgets/counter/counter.tpl');
	}
	
	// Config
	
	function renderConfig(Model_WorkspaceWidget $widget) {
		if(empty($widget))
			return;
		
		$tpl = DevblocksPlatform::getTemplateService();
		
		// Widget
		
		$tpl->assign('widget', $widget);
		
		// Limit to widget
		
		$datasource_mfts = Extension_WorkspaceWidgetDatasource::getAll(false, $this->manifest->id);
		$tpl->assign('datasource_mfts', $datasource_mfts);
		
		// Template
		
		$tpl->display('devblocks:cerberusweb.core::internal/workspaces/widgets/counter/config.tpl');
	}
	
	function saveConfig(Model_WorkspaceWidget $widget) {
		@$params = DevblocksPlatform::importGPC($_REQUEST['params'], 'array', array());
		
		DAO_WorkspaceWidget::update($widget->id, array(
			DAO_WorkspaceWidget::PARAMS_JSON => json_encode($params),
		));

		// Clear caches
		$cache = DevblocksPlatform::getCacheService();
		$cache->remove(sprintf("widget%d_datasource", $widget->id));
	}
};

class WorkspaceWidget_Chart extends Extension_WorkspaceWidget {
	function render(Model_WorkspaceWidget $widget) {
		$tpl = DevblocksPlatform::getTemplateService();

		// Per series datasources
		if(is_array($widget->params['series']))
		foreach($widget->params['series'] as $series_idx => $series) {
			@$datasource_extid = $series['datasource'];

			if(empty($datasource_extid))
				continue;
			
			if(null == ($datasource_ext = Extension_WorkspaceWidgetDatasource::get($datasource_extid)))
				continue;
			
			$data = $datasource_ext->getData($widget, $series);
			
			if(!empty($data))
				$widget->params['series'][$series_idx] = $data;
		}
		
		$tpl->assign('widget', $widget);
		
		switch($widget->params['chart_type']) {
			case 'bar':
				$tpl->display('devblocks:cerberusweb.core::internal/workspaces/widgets/chart/bar_chart.tpl');
				break;
				
			default:
			case 'line':
				$tpl->display('devblocks:cerberusweb.core::internal/workspaces/widgets/chart/line_chart.tpl');
				break;
		}
	}
	
	// Config
	
	function renderConfig(Model_WorkspaceWidget $widget) {
		if(empty($widget))
			return;
		
		$tpl = DevblocksPlatform::getTemplateService();
		
		// Widget
		
		$tpl->assign('widget', $widget);
		
		// Datasource Extensions
		
		$datasource_mfts = Extension_WorkspaceWidgetDatasource::getAll(false, $this->manifest->id);
		$tpl->assign('datasource_mfts', $datasource_mfts);
		
		// Template
		
		$tpl->display('devblocks:cerberusweb.core::internal/workspaces/widgets/chart/config.tpl');
	}
	
	function saveConfig(Model_WorkspaceWidget $widget) {
		@$params = DevblocksPlatform::importGPC($_REQUEST['params'], 'array', array());
		
		foreach($params['series'] as $idx => $series) {
			if(isset($series['line_color'])) {
				if(false != ($rgb = $this->_hex2RGB($series['line_color']))) {
					$params['series'][$idx]['fill_color'] = sprintf("rgba(%d,%d,%d,0.15)", $rgb['r'], $rgb['g'], $rgb['b']);
				}
			}
		}
		
		DAO_WorkspaceWidget::update($widget->id, array(
			DAO_WorkspaceWidget::PARAMS_JSON => json_encode($params),
		));
	}
	
	// Source: http://www.php.net/manual/en/function.hexdec.php#99478
	private function _hex2RGB($hex_color) {
		$hex_color = preg_replace("/[^0-9A-Fa-f]/", '', $hex_color); // Gets a proper hex string
		$rgb = array();
	  
		// If a proper hex code, convert using bitwise operation. No overhead... faster
		if (strlen($hex_color) == 6) {
			$color_value = hexdec($hex_color);
			$rgb['r'] = 0xFF & ($color_value >> 0x10);
			$rgb['g'] = 0xFF & ($color_value >> 0x8);
			$rgb['b'] = 0xFF & $color_value;
			 
		// If shorthand notation, need some string manipulations
		} elseif (strlen($hex_color) == 3) {
			$rgb['r'] = hexdec(str_repeat(substr($hex_color, 0, 1), 2));
			$rgb['g'] = hexdec(str_repeat(substr($hex_color, 1, 1), 2));
			$rgb['b'] = hexdec(str_repeat(substr($hex_color, 2, 1), 2));
			 
		} else {
			return false;
		}
	  
		return $rgb;
	}
};

class WorkspaceWidget_Subtotals extends Extension_WorkspaceWidget {
	function render(Model_WorkspaceWidget $widget) {
		$view_id = sprintf("widget%d_worklist", $widget->id);

		if(null == ($view_model = self::getParamsViewModel($widget, $widget->params)))
			return;
		
		// Force reload parameters (we can't trust the session)
		if(false == ($view = C4_AbstractViewLoader::unserializeAbstractView($view_model)))
			return;
		
		C4_AbstractViewLoader::setView($view->id, $view);
		
		if(!($view instanceof IAbstractView_Subtotals))
			return;
		
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->assign('view_id', $view_id);
		$tpl->assign('view', $view);

		$fields = $view->getSubtotalFields();
		$tpl->assign('subtotal_fields', $fields);

		if(empty($view->renderSubtotals) || !isset($fields[$view->renderSubtotals])) {
			echo "You need to enable subtotals on the worklist in this widget's configuration.";
			return;
		}
		
		$counts = $view->getSubtotalCounts($view->renderSubtotals);

		if(null != ($limit_to = $widget->params['limit_to'])) {
			$counts = array_slice($counts, 0, $limit_to, true);
		}
		
		switch(@$widget->params['style']) {
			case 'pie':
				$wedge_colors = array(
					'#57970A',
					'#007CBD',
					'#7047BA',
					'#8B0F98',
					'#CF2C1D',
					'#E97514',
					'#FFA100',
					'#3E6D07',
					'#345C05',
					'#005988',
					'#004B73',
					'#503386',
					'#442B71',
					'#640A6D',
					'#55085C',
					'#951F14',
					'#7E1A11',
					'#A8540E',
					'#8E470B',
					'#B87400',
					'#9C6200',
					'#CCCCCC',
				);
				$widget->params['wedge_colors'] = $wedge_colors;

				$wedge_labels = array();
				$wedge_values = array();
				
				DevblocksPlatform::sortObjects($counts, '[hits]', false);
				
				foreach($counts as $data) {
					$wedge_labels[] = $data['label'];
					$wedge_values[] = intval($data['hits']);
				}

				$widget->params['wedge_labels'] = $wedge_labels;
				$widget->params['wedge_values'] = $wedge_values;
				
				$widget->params['show_legend'] = true;
				$widget->params['metric_type'] = 'number';
				
				$tpl->assign('widget', $widget);
				
				$tpl->display('devblocks:cerberusweb.core::internal/workspaces/widgets/pie_chart/pie_chart.tpl');
				break;
				
			default:
			case 'list':
				$tpl->assign('subtotal_counts', $counts);
				$tpl->display('devblocks:cerberusweb.core::internal/workspaces/widgets/subtotals/subtotals.tpl');
				break;
		}
		
	}
	
	function renderConfig(Model_WorkspaceWidget $widget) {
		if(empty($widget))
			return;
		
		$tpl = DevblocksPlatform::getTemplateService();
		
		// Widget
		
		$tpl->assign('widget', $widget);
		
		// Contexts
		
		$context_mfts = Extension_DevblocksContext::getAll(false, 'workspace');
		$tpl->assign('context_mfts', $context_mfts);
		
		// Template
		
		$tpl->display('devblocks:cerberusweb.core::internal/workspaces/widgets/subtotals/config.tpl');
	}
	
	function saveConfig(Model_WorkspaceWidget $widget) {
		@$params = DevblocksPlatform::importGPC($_REQUEST['params'], 'array', array());
		
		// Save the widget
		
		DAO_WorkspaceWidget::update($widget->id, array(
			DAO_WorkspaceWidget::PARAMS_JSON => json_encode($params),
		));
	}
};

class WorkspaceWidget_Worklist extends Extension_WorkspaceWidget {
	function render(Model_WorkspaceWidget $widget) {
		if(null == ($view_model = Extension_WorkspaceWidget::getParamsViewModel($widget, $widget->params)))
			return;
		
		// Force reload parameters (we can't trust the session)
		if(false == ($view = C4_AbstractViewLoader::unserializeAbstractView($view_model)))
			return;
		
		$view->id = sprintf("widget%d_worklist", $widget->id);
		
		C4_AbstractViewLoader::setView($view->id, $view);
		
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->assign('view_id', $view->id);
		$tpl->assign('view', $view);

		$tpl->display('devblocks:cerberusweb.core::internal/workspaces/widgets/worklist/render.tpl');
	}
	
	function renderConfig(Model_WorkspaceWidget $widget) {
		if(empty($widget))
			return;
		
		$tpl = DevblocksPlatform::getTemplateService();
		
		// Widget
		
		$tpl->assign('widget', $widget);
		
		// Contexts
		
		$context_mfts = Extension_DevblocksContext::getAll(false, 'workspace');
		$tpl->assign('context_mfts', $context_mfts);
		
		// Mirrored worklist for config (two worklists can render at once)
		
		if(
			null != ($view_model = Extension_WorkspaceWidget::getParamsViewModel($widget, $widget->params))
			&& false != ($view = C4_AbstractViewLoader::unserializeAbstractView($view_model))
			) {
			
			// Mirror the worklist to the config worklist
			$view->id = sprintf("widget%d_worklist_config", $widget->id);
			$view->is_ephemeral = true;
			
			C4_AbstractViewLoader::setView($view->id, $view);
		}
		
		// Template
		
		$tpl->display('devblocks:cerberusweb.core::internal/workspaces/widgets/worklist/config.tpl');
	}
	
	function saveConfig(Model_WorkspaceWidget $widget) {
		@$params = DevblocksPlatform::importGPC($_REQUEST['params'], 'array', array());
		
		if(
			null != ($view_model = self::getParamsViewModel($widget, $params))
			&& false != ($view = C4_AbstractViewLoader::unserializeAbstractView($view_model))
		) {
			// Set the usable worklist
			$view->id = sprintf("widget%d_worklist", $widget->id);
			$view->is_ephemeral = false;
			
			C4_AbstractViewLoader::setView($view->id, $view);
		}
		
		// Save
		DAO_WorkspaceWidget::update($widget->id, array(
			DAO_WorkspaceWidget::PARAMS_JSON => json_encode($params),
		));
	}
};

class WorkspaceWidget_CustomHTML extends Extension_WorkspaceWidget {
	function render(Model_WorkspaceWidget $widget) {
		$tpl = DevblocksPlatform::getTemplateService();

		$tpl->assign('widget', $widget);
		
		$tpl->display('devblocks:cerberusweb.core::internal/workspaces/widgets/custom_html/html.tpl');
	}
	
	// Config
	
	function renderConfig(Model_WorkspaceWidget $widget) {
		if(empty($widget))
			return;
		
		$tpl = DevblocksPlatform::getTemplateService();
		
		// Widget
		
		$tpl->assign('widget', $widget);
		
		// Template
		
		$tpl->display('devblocks:cerberusweb.core::internal/workspaces/widgets/custom_html/config.tpl');
	}
	
	function saveConfig(Model_WorkspaceWidget $widget) {
		@$params = DevblocksPlatform::importGPC($_REQUEST['params'], 'array', array());
		
		DAO_WorkspaceWidget::update($widget->id, array(
			DAO_WorkspaceWidget::PARAMS_JSON => json_encode($params),
		));

		// Clear caches
		$cache = DevblocksPlatform::getCacheService();
		$cache->remove(sprintf("widget%d_datasource", $widget->id));
	}
};

class WorkspaceWidget_PieChart extends Extension_WorkspaceWidget {
	function render(Model_WorkspaceWidget $widget) {
		$tpl = DevblocksPlatform::getTemplateService();

		// Per series datasources
		@$datasource_extid = $widget->params['datasource'];

		if(empty($datasource_extid)) {
			echo "This pie chart doesn't have a data source. Configure it and select one.";
			return;
		}
		
		if(null == ($datasource_ext = Extension_WorkspaceWidgetDatasource::get($datasource_extid)))
			return;
		
		$data = $datasource_ext->getData($widget, $widget->params);

		if(!empty($data))
			$widget->params = $data;
		
		$wedge_colors = array(
			'#57970A',
			'#007CBD',
			'#7047BA',
			'#8B0F98',
			'#CF2C1D',
			'#E97514',
			'#FFA100',
			'#3E6D07',
			'#345C05',
			'#005988',
			'#004B73',
			'#503386',
			'#442B71',
			'#640A6D',
			'#55085C',
			'#951F14',
			'#7E1A11',
			'#A8540E',
			'#8E470B',
			'#B87400',
			'#9C6200',
			'#CCCCCC',
		);
		$widget->params['wedge_colors'] = $wedge_colors;

		$tpl->assign('widget', $widget);
		
		$tpl->display('devblocks:cerberusweb.core::internal/workspaces/widgets/pie_chart/pie_chart.tpl');
	}
	
	// Config
	
	function renderConfig(Model_WorkspaceWidget $widget) {
		if(empty($widget))
			return;
		
		$tpl = DevblocksPlatform::getTemplateService();
		
		// Widget
		
		$tpl->assign('widget', $widget);
		
		// Limit to widget
		
		$datasource_mfts = Extension_WorkspaceWidgetDatasource::getAll(false, $this->manifest->id);
		$tpl->assign('datasource_mfts', $datasource_mfts);
		
		// Template
		
		$tpl->display('devblocks:cerberusweb.core::internal/workspaces/widgets/pie_chart/config.tpl');
	}
	
	function saveConfig(Model_WorkspaceWidget $widget) {
		@$params = DevblocksPlatform::importGPC($_REQUEST['params'], 'array', array());
		
		DAO_WorkspaceWidget::update($widget->id, array(
			DAO_WorkspaceWidget::PARAMS_JSON => json_encode($params),
		));

		// Clear caches
		$cache = DevblocksPlatform::getCacheService();
		$cache->remove(sprintf("widget%d_datasource", $widget->id));
	}
};

class WorkspaceWidget_Scatterplot extends Extension_WorkspaceWidget {
	function render(Model_WorkspaceWidget $widget) {
		$tpl = DevblocksPlatform::getTemplateService();

		@$series = $widget->params['series'];
		
		if(empty($series)) {
			echo "This scatterplot doesn't have any data sources. Configure it and select one.";
			return;
		}

		// Multiple datasources
		foreach($series as $series_idx => $series_params) {
			@$datasource_extid = $series_params['datasource'];

			if(empty($datasource_extid))
				continue;
			
			if(null == ($datasource_ext = Extension_WorkspaceWidgetDatasource::get($datasource_extid)))
				continue;
			
			$data = $datasource_ext->getData($widget, $series_params);

			if(!empty($data))
				$widget->params['series'][$series_idx] = $data;
		}
		
		$tpl->assign('widget', $widget);

		$tpl->display('devblocks:cerberusweb.core::internal/workspaces/widgets/scatterplot/scatterplot.tpl');
	}
	
	// Config
	
	function renderConfig(Model_WorkspaceWidget $widget) {
		if(empty($widget))
			return;
		
		$tpl = DevblocksPlatform::getTemplateService();
		
		// Widget
		
		$tpl->assign('widget', $widget);
		
		// Limit to widget
		
		$datasource_mfts = Extension_WorkspaceWidgetDatasource::getAll(false, $this->manifest->id);
		$tpl->assign('datasource_mfts', $datasource_mfts);
		
		// Template
		
		$tpl->display('devblocks:cerberusweb.core::internal/workspaces/widgets/scatterplot/config.tpl');
	}
	
	function saveConfig(Model_WorkspaceWidget $widget) {
		@$params = DevblocksPlatform::importGPC($_REQUEST['params'], 'array', array());
		
		DAO_WorkspaceWidget::update($widget->id, array(
			DAO_WorkspaceWidget::PARAMS_JSON => json_encode($params),
		));

		// Clear caches
		$cache = DevblocksPlatform::getCacheService();
		$cache->remove(sprintf("widget%d_datasource", $widget->id));
	}
};