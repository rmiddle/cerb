<div id="widget{$widget->id}ConfigTabs">
	<ul>
		<li><a href="#widget{$widget->id}ConfigTabDatasource">Data Source</a></li>
		<li><a href="#widget{$widget->id}ConfigTabStyle">Style</a></li>
	</ul>
	
    <div id="widget{$widget->id}ConfigTabDatasource">
    	<label><input type="radio" name="params[datasource]" value="" {if empty($widget->params.datasource)}checked="checked"{/if}> Manual</label>
    	<label><input type="radio" name="params[datasource]" value="worklist" {if $widget->params.datasource=='worklist'}checked="checked"{/if}> Worklist</label>
    	<label><input type="radio" name="params[datasource]" value="sensor" {if $widget->params.datasource=='sensor'}checked="checked"{/if}> Sensor</label>
    	<label><input type="radio" name="params[datasource]" value="url" {if $widget->params.datasource=='url'}checked="checked"{/if}> URL</label>
    	
    	<fieldset class="peek manual" style="display:{if empty($widget->params.datasource)}block{else}none{/if};">
    	<table cellspacing="0" cellpadding="0" border="0">
    		<tr>
    			<td>
					<b>Metric Value:</b>
    			</td>
    		</tr>
    		<tr>
    			<td>
					<input type="text" name="params[metric_value]" value="{$widget->params.metric_value}">
    			</td>
    		</tr>
    	</table>
    	</fieldset>
    	
    	<fieldset class="peek worklist" style="display:{if $widget->params.datasource=='worklist'}block{else}none{/if};">
    		{$div_popup_worklist = uniqid()}
			
			{$ctx_id = $widget->params.view_context}
			
			{$ctx = null}
			{$ctx_view = null}
			{$ctx_fields = []}
			
			{if !empty($ctx_id)}
				{$ctx = Extension_DevblocksContext::get($ctx_id)}
				{$ctx_view = $ctx->getChooserView()} 
				{$ctx_fields = $ctx_view->getParamsAvailable()}
			{/if}
			
			<b>Load </b>
			
			<select name="params[view_context]" class="context">
				{foreach from=$context_mfts item=context_mft key=context_id}
				<option value="{$context_id}" {if $ctx_id==$context_id}selected="selected"{/if}>{$context_mft->name}</option>
				{/foreach}
			</select>
			
			<b> data using</b> 
			
			<div id="popup{$div_popup_worklist}" class="badge badge-lightgray" style="font-weight:bold;color:rgb(80,80,80);cursor:pointer;display:inline;"><span class="name">Worklist</span> &#x25be;</div>
			
			<input type="hidden" name="params[view_model]" value="{$widget->params.view_model}" class="model">
			
			<br>
			
			<b>Counter</b> is 
			 
			{$counter_func = $widget->params.counter_func}
			{$counter_field = $widget->params.counter_field}
			
			<select name="params[counter_func]" class="counter_func">
				<option value="count" {if 'count'==$widget->params.counter_func}selected="selected"{/if}>count</option>
				<option value="avg" class="number" {if 'avg'==$widget->params.counter_func}selected="selected"{/if}>average</option>
				<option value="sum" class="number" {if 'sum'==$widget->params.counter_func}selected="selected"{/if}>sum</option>
				<option value="min" class="number" {if 'min'==$widget->params.counter_func}selected="selected"{/if}>min</option>
				<option value="max" class="number" {if 'max'==$widget->params.counter_func}selected="selected"{/if}>max</option>
			</select>
			
			<select name="params[counter_field]" class="counter_field" style="display:{if empty($ctx_fields) || 'count'==$counter_func}none{else}inline{/if};">
				{if !empty($ctx_fields)}
				{foreach from=$ctx_fields item=field}
					{if !empty($field->db_label)}
						{if $field->type == Model_CustomField::TYPE_NUMBER}
						<option value="{$field->token}" {if $counter_field==$field->token}selected="selected"{/if}>{$field->db_label|lower}</option>
						{/if}
					{/if}
				{/foreach}
				{/if}
			</select>
			<br>
			
    	</fieldset>
    	
    	<fieldset class="peek sensor" style="display:{if $widget->params.datasource=='sensor'}block{else}none{/if};">
    		<b>Sensor:</b>
    		<select name="params[sensor_id]">
    			{foreach from=$sensors item=sensor}
    			<option value="{$sensor->id}" {if $widget->params.sensor_id==$sensor->id}selected="selected"{/if}>{$sensor->name}</option>
    			{/foreach}
    		</select>
    	</fieldset>
    	
    	<fieldset class="peek url" style="display:{if $widget->params.datasource=='url'}block{else}none{/if};">
    		<b>URL</b> is 
    		<input type="text" name="params[url]" value="{$widget->params.url}" size="64">
    		<br>
    		
    		<b>Cache</b> for 
    		<input type="text" name="params[url_cache_mins]" value="{$widget->params.url_cache_mins|number_format}" size="3" maxlength="3"> 
    		minute(s)
    		<br>
    	</fieldset>
	</div>
	
    <div id="widget{$widget->id}ConfigTabStyle">
    	<table cellspacing="0" cellpadding="0" border="0">
    		<tr>
    			<td>
    				<b>Type:</b>
    			</td>
    			<td>
    				<b>Prefix:</b>
    			</td>
    			<td>
    				<b>Suffix:</b>
    			</td>
    			<td>
    				<b>Color:</b>
    			</td>
    		</tr>
    		<tr>
    			<td>
    				{$types = [number, decimal, percent, seconds]}
    				<select name="params[metric_type]">
    					{foreach from=$types item=type}
    					<option value="{$type}" {if $widget->params.metric_type==$type}selected="selected"{/if}>{$type}</option>
    					{/foreach}
    				</select>
    			</td>
    			<td>
					<input type="text" name="params[metric_prefix]" value="{$widget->params.metric_prefix}" size="10">
    			</td>
    			<td>
					<input type="text" name="params[metric_suffix]" value="{$widget->params.metric_suffix}" size="10">
    			</td>
    			<td>
    				<input type="hidden" name="params[color]" value="{$widget->params.color|default:'#34434E'}" style="width:100%;" class="color-picker">
    			</td>
    		</tr>
    	</table>    	
    </div>
</div>

<script type="text/javascript">
	$tabs = $('#widget{$widget->id}ConfigTabs').tabs();
	
	$tabs.find('input:hidden.color-picker').miniColors({
		color_favorites: ['#CF2C1D','#FEAF03','#57970A','#007CBD','#7047BA','#D5D5D5','#ADADAD','#34434E']
	});
	
	$datasource_tab = $('#widget{$widget->id}ConfigTabDatasource');
	$radios = $datasource_tab.find('> label input:radio');
	$fieldsets = $datasource_tab.find('> fieldset');
	
	$radios.click(function(e) {
		val = $(this).val();
		
		if(val == '')
			val = 'manual';
		
		$fieldsets = $(this).closest('div').find('> fieldset');
		$fieldsets.hide();
		
		$datasource_tab.find('fieldset.' + val).show();
	});
	
	$fieldsets.find('select.counter_func').change(function(e) {
		val = $(this).val();
		
		var $select_counter_field = $(this).siblings('select.counter_field');
		
		if(val == 'count')
			$select_counter_field.hide();
		else
			$select_counter_field.show();
	});
	
	$fieldsets.find('select.counter_field').change(function(e) {
		
	});
	
	$fieldsets.find('select.context').change(function(e) {
		ctx = $(this).val();
		
		// Hide options until we know the context
		var $select = $(this);
		
		if(0 == ctx.length)
			return;
		
		genericAjaxGet('','c=internal&a=handleSectionAction&section=dashboards&action=getContextFieldsJson&context=' + ctx, function(json) {
			if('object' == typeof(json) && json.length > 0) {
				$select_counter_field = $select.siblings('select.counter_field').html('');
				
				for(idx in json) {
					field = json[idx];
					field_type = (field.type=='E') ? 'date' : ((field.type=='N') ? 'number' : '');
					
					$option = $('<option value="'+field.key+'" class="'+field_type+'">'+field.label+'</option>');

					// Number
					if(field_type == 'number')
						$select_counter_field.append($option.clone());
					
					delete $option;
				}
			}
		});
	});	
	
	$('#popup{$div_popup_worklist}').click(function(e) {
		context = $(this).siblings('select.context').val();
		$chooser=genericAjaxPopup('chooser','c=internal&a=chooserOpenParams&context='+context+'&view_id={"widget{$widget->id}_worklist"}',null,true,'750');
		$chooser.bind('chooser_save',function(event) {
			if(null != event.view_model) {
				//$('#popup{$div_popup_worklist}').find('span.name').html(event.view_name);
				$('#popup{$div_popup_worklist}').parent().find('input:hidden.model').val(event.view_model);
			}
		});
	});
</script>