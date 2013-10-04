{if is_array($values_to_contexts)}
<b>On:</b>
<div style="margin-left:10px;margin-bottom:0.5em;">
<select name="{$namePrefix}[on]" class="on">
	{foreach from=$values_to_contexts item=context_data key=val_key name=context_data}
	{if $smarty.foreach.context_data.first && empty($params.on)}{$params.on = $val_key}{/if}
	{if !$context_data.is_polymorphic}
	<option value="{$val_key}" context="{$context_data.context}" {if $params.on==$val_key}{$selected_context = $context_data.context}selected="selected"{/if}>{$context_data.label}</option>
	{/if}
	{/foreach}
</select>
</div>
{/if}

<b>Run this behavior:</b>
<div style="margin-left:10px;margin-bottom:0.5em;">
	<select class="behavior_defaults" style="display:none;visibility:hidden;">
	{foreach from=$macros item=macro key=macro_id}
		{$is_selected = ($params.behavior_id==$macro_id)}
		{if $is_selected || !$macro->is_disabled}
		<option value="{$macro_id}" context="{$events_to_contexts.{$macro->event_point}}" {if $is_selected}selected="selected"{/if}>{$macro->title}</option>
		{/if}
	{/foreach}
	</select>
	<select name="{$namePrefix}[behavior_id]" class="behavior">
	{foreach from=$macros item=macro key=macro_id}
		{if $events_to_contexts.{$macro->event_point} == $selected_context}
		{if empty($params.behavior_id)}{$params.behavior_id=$macro_id}{/if}
		{$is_selected = ($params.behavior_id==$macro_id)}
		{if $is_selected || !$macro->is_disabled}
		<option value="{$macro_id}" {if $is_selected}selected="selected"{/if}>{$macro->title}</option>
		{/if}
		{/if}
	{/foreach}
	</select>
</div>

<div class="parameters">
{include file="devblocks:cerberusweb.core::events/_action_behavior_params.tpl" params=$params macro_params=$macros.{$params.behavior_id}->variables}
</div>

<b>Also run behavior in simulator mode:</b>
<div style="margin-left:10px;margin-bottom:10px;">
	<label><input type="radio" name="{$namePrefix}[run_in_simulator]" value="1" {if $params.run_in_simulator}checked="checked"{/if}> {'common.yes'|devblocks_translate|capitalize}</label>
	<label><input type="radio" name="{$namePrefix}[run_in_simulator]" value="0" {if !$params.run_in_simulator}checked="checked"{/if}> {'common.no'|devblocks_translate|capitalize}</label>
</div>

<b>Save behavior data to a placeholder named:</b>
<div style="margin-left:10px;margin-bottom:10px;">
	&#123;&#123;<input type="text" name="{$namePrefix}[var]" size="24" value="{if !empty($params.var)}{$params.var}{else}_behavior{/if}" required="required" spellcheck="false">&#125;&#125;
	<div style="margin-top:5px;">
		<i><small>The placeholder name must be lowercase, without spaces, and may only contain a-z, 0-9, and underscores (_)</small></i>
	</div>
</div>

<script type="text/javascript">
$action = $('fieldset#{$namePrefix}');
$action.find('select.behavior').change(function(e) {
	var $div = $(this).closest('fieldset').find('div.parameters');
	genericAjaxGet($div,'c=internal&a=showBehaviorParams&name_prefix={$namePrefix}&trigger_id=' + $(this).val());
});

$action.find('select.on').change(function(e) {
	var $div = $(this).closest('fieldset').find('div.parameters');
	$div.html('');
	
	var $on = $(this).find('option:selected');
	var ctx = $on.attr('context');

	var $sel_behavior = $(this).closest('fieldset').find('select.behavior');
	$sel_behavior.find('option').remove();
	
	var $sel_behavior_defaults = $(this).closest('fieldset').find('select.behavior_defaults');
	$sel_behavior_defaults.find('option').each(function() {
		var $this = $(this);
		if($this.attr('context') == ctx) {
			$sel_behavior.append($this.clone());
		}
	});
	
	$sel_behavior.trigger('change');
});
</script>
