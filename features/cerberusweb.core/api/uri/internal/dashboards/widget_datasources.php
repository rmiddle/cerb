<?php
// [TODO] This could split up into two worklist datasources (metric, series).
//		This would allow reuse without having to know about the caller at all.
class WorkspaceWidgetDatasource_Worklist extends Extension_WorkspaceWidgetDatasource {
	private function _getSeriesIdxFromPrefix($params_prefix) {
		if(!empty($params_prefix) && preg_match("#\[series\]\[(\d+)\]#", $params_prefix, $matches) && count($matches) == 2) {
			return $matches[1];
		}
		
		return null;
	}
	
	function renderConfig(Model_WorkspaceWidget $widget, $params=array(), $params_prefix=null) {
		$tpl = DevblocksPlatform::getTemplateService();
		
		$tpl->assign('widget', $widget);
		$tpl->assign('params', $params);
		$tpl->assign('params_prefix', $params_prefix);
		
		if(null !== ($series_idx = $this->_getSeriesIdxFromPrefix($params_prefix)))
			$tpl->assign('series_idx', $series_idx);
		
		// Prime the worklist
		
		$view_id = sprintf(
			"widget%d_worklist%s",
			$widget->id,
			(!is_null($series_idx) ? intval($series_idx) : '')
		);
		
		$view = Extension_WorkspaceWidget::getViewFromParams($widget, $params, $view_id);
		
		// Worklists
		
		$context_mfts = Extension_DevblocksContext::getAll(false, 'workspace');
		$tpl->assign('context_mfts', $context_mfts);
		
		switch($widget->extension_id) {
			case 'core.workspace.widget.chart':
			case 'core.workspace.widget.pie_chart':
			case 'core.workspace.widget.scatterplot':
				$tpl->display('devblocks:cerberusweb.core::internal/workspaces/widgets/datasources/config_worklist_series.tpl');
				break;
				
			case 'core.workspace.widget.counter':
			case 'core.workspace.widget.gauge':
				$tpl->display('devblocks:cerberusweb.core::internal/workspaces/widgets/datasources/config_worklist_metric.tpl');
				break;
		}
	}
	
	function getData(Model_WorkspaceWidget $widget, array $params=array(), $params_prefix=null) {
		switch($widget->extension_id) {
			case 'core.workspace.widget.chart':
			case 'core.workspace.widget.scatterplot':
				return $this->_getDataSeries($widget, $params, $params_prefix);
				break;
				
			case 'core.workspace.widget.counter':
			case 'core.workspace.widget.gauge':
			case 'core.workspace.widget.pie_chart':
				return $this->_getDataSingle($widget, $params, $params_prefix);
				break;
		}
	}
	
	private function _getDataSingle(Model_WorkspaceWidget $widget, array $params=array(), $params_prefix=null) {
		$series_idx = $this->_getSeriesIdxFromPrefix($params_prefix);
		
		$view_id = sprintf("widget%d_worklist%s",
			$widget->id,
			(!is_null($series_idx) ? intval($series_idx) : '')
		);
		
		if(null == ($view = Extension_WorkspaceWidget::getViewFromParams($widget, $params, $view_id)))
			return;
		
		@$view_context = $params['worklist_model']['context'];

		if(empty($view_context))
			return;
		
		if(null == ($context_ext = Extension_DevblocksContext::get($view_context)))
			return;

		if(null == ($dao_class = @$context_ext->manifest->params['dao_class']))
			return;
		
		$view->renderPage = 0;
		$view->renderLimit = 1;
		
		$db = DevblocksPlatform::getDatabaseService();
		
		// We need to know what date fields we have
		$fields = $view->getFields();
		@$metric_func = $params['metric_func'];
		@$metric_field = $fields[$params['metric_field']];

		// Build the query
		
		$query_parts = $dao_class::getSearchQueryComponents(
			$view->view_columns,
			$view->getParams(),
			$view->renderSortBy,
			$view->renderSortAsc
		);
		
		if(empty($metric_func))
			$metric_func = 'count';
		
		switch($metric_func) {
			case 'sum':
				$select_func = sprintf("SUM(%s.%s)",
					$metric_field->db_table,
					$metric_field->db_column
				);
				break;
				
			case 'avg':
				$select_func = sprintf("AVG(%s.%s)",
					$metric_field->db_table,
					$metric_field->db_column
				);
				break;
				
			case 'min':
				$select_func = sprintf("MIN(%s.%s)",
					$metric_field->db_table,
					$metric_field->db_column
				);
				break;
				
			case 'max':
				$select_func = sprintf("MAX(%s.%s)",
					$metric_field->db_table,
					$metric_field->db_column
				);
				break;
				
			default:
			case 'count':
				$select_func = 'COUNT(*)';
				break;
		}
		
		$sql = sprintf("SELECT %s AS counter_value " .
			str_replace('%','%%',$query_parts['join']).
			str_replace('%','%%',$query_parts['where']),
			$select_func
		);
		
		switch($widget->extension_id) {
			case 'core.workspace.widget.counter':
			case 'core.workspace.widget.gauge':
				$params['metric_value'] = $db->GetOneSlave($sql);
				break;
		}
		
		return $params;
	}
	
	private function _getDataSeries(Model_WorkspaceWidget $widget, array $params=array(), $params_prefix=null) {
		$date = DevblocksPlatform::getDateService();
		$db = DevblocksPlatform::getDatabaseService();
		
		// Use the worker's timezone for MySQL date functions
		$db->ExecuteSlave(sprintf("SET time_zone = %s", $db->qstr($date->formatTime('P', time()))));
		
		$series_idx = $this->_getSeriesIdxFromPrefix($params_prefix);
		
		$view_id = sprintf("widget%d_worklist%s",
			$widget->id,
			(!is_null($series_idx) ? intval($series_idx) : '')
		);
		
		if(null == ($view = Extension_WorkspaceWidget::getViewFromParams($widget, $params, $view_id)))
			return;
		
		@$view_context = $params['worklist_model']['context'];
		
		if(empty($view_context))
			return;
		
		if(null == ($context_ext = Extension_DevblocksContext::get($view_context)))
			return;

		if(null == ($dao_class = @$context_ext->manifest->params['dao_class']))
			return;
			
		$data = array();
		
		$view->renderPage = 0;
		$view->renderLimit = 30;
			
		// Initial query planner
		
		$query_parts = $dao_class::getSearchQueryComponents(
			$view->view_columns,
			$view->getParams(),
			$view->renderSortBy,
			$view->renderSortAsc
		);
		
		// We need to know what date fields we have
		
		$fields = $view->getFields();
		$xaxis_field = null;
		$xaxis_field_type = null;
		
		switch($params['xaxis_field']) {
			case '_id':
				$xaxis_field = new DevblocksSearchField('_id', $query_parts['primary_table'], 'id', null, Model_CustomField::TYPE_NUMBER);
				break;
					
			default:
				@$xaxis_field = $fields[$params['xaxis_field']];
				break;
		}
		
		if(!empty($xaxis_field))
			$params_changed = false;
			
			// If we're subtotalling on a custom field, make sure it's joined
			if($xaxis_field != '_id' && !$view->hasParam($xaxis_field->token, $view->getParams())) {
				$view->addParam(new DevblocksSearchCriteria($xaxis_field->token, DevblocksSearchCriteria::OPER_TRUE));
				$params_changed = true;
			}
			
			@$yaxis_func = $params['yaxis_func'];
			$yaxis_field = null;
			
			switch($yaxis_func) {
				case 'count':
					break;
					
				default:
					@$yaxis_field = $fields[$params['yaxis_field']];
					
					if(empty($yaxis_field)) {
						$yaxis_func = 'count';
						
					} else {
						// If we're subtotalling on a custom field, make sure it's joined
						if(!$view->hasParam($yaxis_field->token, $view->getParams())) {
							$view->addParam(new DevblocksSearchCriteria($yaxis_field->token, DevblocksSearchCriteria::OPER_TRUE));
							$params_changed = true;
						}
					}
					break;
			}
			
			if($params_changed) {
				$query_parts = $dao_class::getSearchQueryComponents(
					$view->view_columns,
					$view->getParams(),
					$view->renderSortBy,
					$view->renderSortAsc
				);
			}
			
			unset($params_changed);
			
			switch($xaxis_field->type) {
				case Model_CustomField::TYPE_DATE:
					// X-axis tick
					@$xaxis_tick = $params['xaxis_tick'];
						
					if(empty($xaxis_tick))
						$xaxis_tick = 'day';
						
					switch($xaxis_tick) {
						case 'hour':
							$date_format_mysql = '%Y-%m-%d %H:00';
							$date_format_php = '%Y-%m-%d %H:00';
							$date_label = $date_format_php;
							break;
								
						default:
						case 'day':
							$date_format_mysql = '%Y-%m-%d';
							$date_format_php = '%Y-%m-%d';
							$date_label = $date_format_php;
							break;
								
						case 'week':
							$date_format_mysql = '%xW%v';
							$date_format_php = '%YW%W';
							$date_label = $date_format_php;
							break;
								
						case 'month':
							$date_format_mysql = '%Y-%m';
							$date_format_php = '%Y-%m';
							$date_label = '%b %Y';
							break;
								
						case 'year':
							$date_format_mysql = '%Y-01-01';
							$date_format_php = '%Y-01-01';
							$date_label = '%Y';
							break;
					}
					
					switch($yaxis_func) {
						case 'sum':
							$select_func = sprintf("SUM(%s.%s)",
								$yaxis_field->db_table,
								$yaxis_field->db_column
							);
							break;
								
						case 'avg':
							$select_func = sprintf("AVG(%s.%s)",
								$yaxis_field->db_table,
								$yaxis_field->db_column
							);
							break;
								
						case 'min':
							$select_func = sprintf("MIN(%s.%s)",
								$yaxis_field->db_table,
								$yaxis_field->db_column
							);
							break;
								
						case 'max':
							$select_func = sprintf("MAX(%s.%s)",
								$yaxis_field->db_table,
								$yaxis_field->db_column
							);
							break;
							
						case 'value':
							$select_func = sprintf("%s.%s",
								$yaxis_field->db_table,
								$yaxis_field->db_column
							);
							break;
								
						default:
						case 'count':
							$select_func = 'COUNT(*)';
							break;
					}
					
					$sql = sprintf("SELECT %s AS hits, DATE_FORMAT(FROM_UNIXTIME(%s.%s), '%s') AS histo ",
						$select_func,
						$xaxis_field->db_table,
						$xaxis_field->db_column,
						$date_format_mysql
					).
					str_replace('%','%%',$query_parts['join']).
					str_replace('%','%%',$query_parts['where']).
					sprintf("GROUP BY DATE_FORMAT(FROM_UNIXTIME(%s.%s), '%s') ",
						$xaxis_field->db_table,
						$xaxis_field->db_column,
						$date_format_mysql
					).
					'ORDER BY histo ASC'
					;
					
					$results = $db->GetArraySlave($sql);
					
					// Find the first and last date
					@$xaxis_param = array_shift(C4_AbstractView::findParam($xaxis_field->token, $view->getParams()));

					$current_tick = null;
					$last_tick = null;
					
					if(!empty($xaxis_param)) {
						if(2 == count($xaxis_param->value)) {
							$current_tick = strtotime($xaxis_param->value[0]);
							$last_tick = strtotime($xaxis_param->value[1]);
						}
					}
					
					$first_result = null;
					$last_result = null;
					
					if(empty($current_tick) && empty($last_tick)) {
						$last_result = end($results);
						$first_result = reset($results);
						$current_tick = strtotime($first_result['histo']);
						$last_tick = strtotime($last_result['histo']);
					}
					
					// Fill in time gaps from no data
						
					// var_dump($current_tick, $last_tick, $xaxis_tick);
					// var_dump($results);

					$array = array();
					
					foreach($results as $k => $v) {
						$array[$v['histo']] = $v['hits'];
					}
					
					$results = $array;
					unset($array);
					
					// Set the first histogram bucket to the beginning of its increment
					//   e.g. 2012-July-09 10:20 -> 2012-July-09 00:00
					switch($xaxis_tick) {
						case 'hour':
						case 'day':
						case 'month':
						case 'year':
							$current_tick = strtotime(strftime($date_format_php, $current_tick));
							break;
							
						// Always Monday
						case 'week':
							$current_tick = strtotime('Monday this week', $current_tick);
							break;
					}
						
					do {
						$histo = strftime($date_format_php, $current_tick);
						// var_dump($histo);

						$value = (isset($results[$histo])) ? $results[$histo] : 0;

						$data[] = array(
							'x' => $histo,
							'y' => (float)$value,
							'x_label' => strftime($date_label, $current_tick),
							'y_label' => ((int) $value != $value) ? sprintf("%0.2f", $value) : sprintf("%d", $value),
						);

						$current_tick = strtotime(sprintf('+1 %s', $xaxis_tick), $current_tick);

					} while($current_tick <= $last_tick);
						
					unset($results);
					break;

				case Model_CustomField::TYPE_NUMBER:
					switch($xaxis_field->token) {
						case '_id':
							$order_by = null;
							$group_by = sprintf("GROUP BY %s.id ", str_replace('%','%%',$query_parts['primary_table']));
							
							if(empty($order_by))
								$order_by = sprintf("ORDER BY %s.id ", str_replace('%','%%',$query_parts['primary_table']));
							
							break;

						default:
							$group_by = sprintf("GROUP BY %s.%s",
								$xaxis_field->db_table,
								$xaxis_field->db_column
							);
							
							$order_by = 'ORDER BY xaxis ASC';
							break;
					}
					
					switch($yaxis_func) {
						case 'sum':
							$select_func = sprintf("SUM(%s.%s)",
								$yaxis_field->db_table,
								$yaxis_field->db_column
							);
							break;
								
						case 'avg':
							$select_func = sprintf("AVG(%s.%s)",
								$yaxis_field->db_table,
								$yaxis_field->db_column
							);
							break;
								
						case 'min':
							$select_func = sprintf("MIN(%s.%s)",
								$yaxis_field->db_table,
								$yaxis_field->db_column
							);
							break;
								
						case 'max':
							$select_func = sprintf("MAX(%s.%s)",
								$yaxis_field->db_table,
								$yaxis_field->db_column
							);
							break;
								
						case 'value':
							$select_func = sprintf("%s.%s",
								$yaxis_field->db_table,
								$yaxis_field->db_column
							);
							break;
								
						default:
						case 'count':
							$select_func = 'COUNT(*)';
							break;
					}
						
					// Scatterplots ignore histograms
					if(isset($widget->params['chart_type']))
					switch($widget->params['chart_type']) {
						case 'scatterplot':
							$group_by = null;
							break;
					}
					
					$sql = sprintf("SELECT %s AS yaxis, %s.%s AS xaxis " .
						str_replace('%','%%',$query_parts['join']).
						str_replace('%','%%',$query_parts['where']).
						"%s ".
						"%s ",
						$select_func,
						$xaxis_field->db_table,
						$xaxis_field->db_column,
						$group_by,
						$order_by
					);

					$results = $db->GetArraySlave($sql);
					$data = array();

					$counter = 0;
						
					foreach($results as $result) {
						$x = ($params['xaxis_field'] == '_id') ? $counter++ : (float)$result['xaxis'];

						$xaxis_label = DevblocksPlatform::formatNumberAs((float)$result['xaxis'], @$params['xaxis_format']);
						$yaxis_label = DevblocksPlatform::formatNumberAs((float)$result['yaxis'], @$params['yaxis_format']);
						
						$data[] = array(
							'x' => $x,
							'y' => (float)$result['yaxis'],
							'x_label' => $xaxis_label,
							'y_label' => $yaxis_label,
						);
					}

					unset($results);
					break;
		}
		
		$params['data'] = $data;
		unset($data);
		
		return $params;
	}
};

class WorkspaceWidgetDatasource_Manual extends Extension_WorkspaceWidgetDatasource {
	function renderConfig(Model_WorkspaceWidget $widget, $params=array(), $params_prefix=null) {
		$tpl = DevblocksPlatform::getTemplateService();
		
		$tpl->assign('widget', $widget);
		$tpl->assign('params', $params);
		$tpl->assign('params_prefix', $params);
		
		$tpl->display('devblocks:cerberusweb.core::internal/workspaces/widgets/datasources/config_manual_metric.tpl');
	}
	
	function getData(Model_WorkspaceWidget $widget, array $params=array(), $params_prefix=null) {
		$metric_value = $params['metric_value'];
		$metric_value = floatval(str_replace(',','', $metric_value));
		$params['metric_value'] = $metric_value;
		return $params;
	}
};

class WorkspaceWidgetDatasource_URL extends Extension_WorkspaceWidgetDatasource {
	function renderConfig(Model_WorkspaceWidget $widget, $params=array(), $params_prefix=null) {
		$tpl = DevblocksPlatform::getTemplateService();
		
		$tpl->assign('widget', $widget);
		$tpl->assign('params', $params);
		$tpl->assign('params_prefix', $params);
		
		$tpl->display('devblocks:cerberusweb.core::internal/workspaces/widgets/datasources/config_url.tpl');
	}
	
	function getData(Model_WorkspaceWidget $widget, array $params=array(), $params_prefix=null) {
		$cache = DevblocksPlatform::getCacheService();
		
		@$url = $params['url'];
		
		@$cache_mins = $params['url_cache_mins'];
		$cache_mins = max(1, intval($cache_mins));
		
		$cache_key = sprintf("widget%d_datasource", $widget->id);
		
		if(true || null === ($data = $cache->load($cache_key))) {
			$ch = DevblocksPlatform::curlInit($url);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			$raw_data = DevblocksPlatform::curlExec($ch);
			$info = curl_getinfo($ch);
			
			//@$status = $info['http_code'];
			@$content_type = DevblocksPlatform::strLower($info['content_type']);
			
			$data = array(
				'raw_data' => $raw_data,
				'info' => $info,
			);
			
			DAO_WorkspaceWidget::update($widget->id, array(
				DAO_WorkspaceWidget::UPDATED_AT => time(),
			), DevblocksORMHelper::OPT_UPDATE_NO_READ_AFTER_WRITE);
			
			$cache->save($data, $cache_key, array(), $cache_mins*60);
		}
	
		switch($widget->extension_id) {
			case 'core.workspace.widget.chart':
			case 'core.workspace.widget.pie_chart':
			case 'core.workspace.widget.scatterplot':
				return $this->_getDataSeries($widget, $params, $data);
				break;
				
			case 'core.workspace.widget.counter':
			case 'core.workspace.widget.gauge':
				return $this->_getDataSingle($widget, $params, $data);
				break;
		}
	}
	
	private function _getDataSeries($widget, $params=array(), $data=null) {
		if(!is_array($data) || !isset($data['info']) || !isset($data['raw_data']))
			return;
		
		if(!isset($params['url_format']) || empty($params['url_format'])) {
			$content_type = $data['info']['content_type'];
		} else {
			$content_type = $params['url_format'];
		}
			
		$raw_data = $data['raw_data'];
		
		if(empty($raw_data) || empty($content_type)) {
			return;
		}
		
		$url_format = '';
		
		switch($content_type) {
			case 'application/json':
			case 'text/json':
				$url_format = 'json';
				break;
				
			case 'text/xml':
				$url_format = 'xml';
				break;
				
			case 'text/csv':
				$url_format = 'csv';
				break;
				
			default:
				return;
				break;
		}
		
		switch($url_format) {
			case 'json':
				if(false != (@$json = json_decode($raw_data, true))) {
					$results = array();
					
					if(is_array($json))
					foreach($json as $object) {
						if(!isset($object['value']))
							continue;
						
						$result = array();
						
						if(isset($object['value']))
							$result['metric_value'] = floatval($object['value']);
		
						if(isset($object['label']))
							$result['metric_label'] = $object['label'];
						
						/*
						if(isset($object['type']))
							$result['metric_type'] = $object['type'];
		
						if(isset($object['prefix']))
							$result['metric_prefix'] = $object['prefix'];
						
						if(isset($object['suffix']))
							$result['metric_suffix'] = $object['suffix'];
						*/
						
						$results[] = $result;
					}
					
					$params['data'] = $results;
				}
				break;
				
			case 'xml':
				if(null != ($xml = simplexml_load_string($raw_data))) {
					$results = array();
					
					foreach($xml as $object) {
						if(!isset($object->value))
							continue;
						
						$result = array();
						
						if(isset($object->value))
							$result['metric_value'] = floatval($object->value);
		
						if(isset($object->label))
							$result['metric_label'] = (string)$object->label;
						
						/*
						if(isset($object->type))
							$result['metric_type'] = (string)$object->type;
		
						if(isset($object->prefix))
							$result['metric_prefix'] = (string)$object->prefix;
						
						if(isset($object->suffix))
							$result['metric_suffix'] = (string)$object->suffix;
						*/
						
						$results[] = $result;
					}
					
					$params['data'] = $results;
				}
				break;
				
			case 'csv':
				$fp = DevblocksPlatform::getTempFile();
				fwrite($fp, $raw_data, strlen($raw_data));
				
				$results = array();
				
				fseek($fp, 0);
				
				while(false != ($row = fgetcsv($fp, 0, ',', '"'))) {
					if(is_array($row) && count($row) >= 1) {
						$result['metric_value'] = floatval($row[0]);
						$result['metric_label'] = @$row[1] ?: '';
						$results[] = $result;
					}
				}
				
				fclose($fp);
				
				$params['data'] = $results;
				break;
		}
		
		return $params;
	}
	
	private function _getDataSingle($widget, $params=array(), $data=null) {
		if(!is_array($data) || !isset($data['info']) || !isset($data['raw_data']))
			return;
		
		if(!isset($params['url_format']) || empty($params['url_format'])) {
			$content_type = $data['info']['content_type'];
		} else {
			$content_type = $params['url_format'];
		}
			
		$raw_data = $data['raw_data'];
		
		if(empty($raw_data) || empty($content_type)) {
			return;
		}
		
		$url_format = '';
		
		switch($content_type) {
			case 'application/json':
			case 'text/json':
				$url_format = 'json';
				break;
				
			case 'text/xml':
				$url_format = 'xml';
				break;
				
			default:
			case 'text/plain':
				$url_format = 'text';
				break;
		}
		
		switch($url_format) {
			case 'json':
				if(false != (@$json = json_decode($raw_data, true))) {
					if(isset($json['value']))
						$params['metric_value'] = floatval($json['value']);

					if(isset($json['label']))
						$params['metric_label'] = $json['label'];
	
					if((isset($params['metric_type']) && !empty($params['metric_type'])) && isset($json['type']))
						$params['metric_type'] = $json['type'];
	
					if((isset($params['metric_prefix']) && !empty($params['metric_prefix'])) && isset($json['prefix']))
						$params['metric_prefix'] = $json['prefix'];
					
					if((isset($params['metric_suffix']) && !empty($params['metric_suffix'])) && isset($json['suffix']))
						$params['metric_suffix'] = $json['suffix'];
				}
				break;
				
			case 'xml':
				if(null != ($xml = simplexml_load_string($raw_data))) {
					if(isset($xml->value))
						$params['metric_value'] = (float)$xml->value;
	
					if((isset($params['metric_type']) && !empty($params['metric_type'])) && isset($xml->type))
						$params['metric_type'] = (string) $xml->type;
	
					if((isset($params['metric_prefix']) && !empty($params['metric_prefix'])) && isset($xml->prefix))
						$params['metric_prefix'] = (string) $xml->prefix;
					
					if((isset($params['metric_suffix']) && !empty($params['metric_suffix'])) && isset($xml->suffix))
						$params['metric_suffix'] = (string) $xml->suffix;
				}
				break;
				
			default:
			case 'text':
				$params['metric_value'] = floatval($raw_data);
				break;
		}
		
		return $params;
	}
};