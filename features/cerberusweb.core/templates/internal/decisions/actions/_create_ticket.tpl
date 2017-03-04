<b>{'message.header.to'|devblocks_translate|capitalize}:</b>
<div style="margin-left:10px;margin-bottom:0.5em;">
	<select name="{$namePrefix}[group_id]">
		{foreach from=$groups item=group key=group_id}
		<option value="{$group_id}" {if $group_id==$params.group_id}selected="selected"{/if}>{$group->name}</option>
		{/foreach}
	</select>
</div>

<b>{'common.participants'|devblocks_translate|capitalize}:</b>
<div style="margin-left:10px;margin-bottom:0.5em;">
	<input type="text" name="{$namePrefix}[requesters]" value="{$params.requesters}" size="45" style="width:100%;" class="placeholders">
</div>

<b>{'message.header.subject'|devblocks_translate|capitalize}:</b>
<div style="margin-left:10px;margin-bottom:0.5em;">
	<input type="text" name="{$namePrefix}[subject]" value="{$params.subject}" size="45" style="width:100%;" class="placeholders">
</div>

<b>{'common.content'|devblocks_translate|capitalize}:</b>
<div style="margin-left:10px;margin-bottom:0.5em;">
	<textarea name="{$namePrefix}[content]" rows="3" cols="45" style="width:100%;" class="placeholders">{$params.content}</textarea>
</div>

<b>{'common.status'|devblocks_translate|capitalize}:</b>
<div style="margin-left:10px;margin-bottom:0.5em;">
	<label><input type="radio" name="{$namePrefix}[status_id]" value="{Model_Ticket::STATUS_OPEN}" {if empty($params.status_id)}checked="checked"{/if}> {'status.open'|devblocks_translate|capitalize}</label>
	<label><input type="radio" name="{$namePrefix}[status_id]" value="{Model_Ticket::STATUS_WAITING}" {if Model_Ticket::STATUS_WAITING==$params.status_id}checked="checked"{/if}> {'status.waiting'|devblocks_translate|capitalize}</label>
	<label><input type="radio" name="{$namePrefix}[status_id]" value="Model_Ticket::STATUS_CLOSED" {if Model_Ticket::STATUS_CLOSED==$params.status_id}checked="checked"{/if}> {'status.closed'|devblocks_translate|capitalize}</label>
</div>

<div class="peek-status-reopen" style="{if empty($params.status_id)}display:none;{/if}">
<b>{'ticket.reopen_at'|devblocks_translate|capitalize}:</b>
<div style="margin-left:10px;margin-bottom:0.5em;">
	<input type="text" name="{$namePrefix}[reopen_at]" value="{$params.reopen_at}" size="45" style="width:100%;" class="placeholders">
</div>
</div>

<b>{'common.owner'|devblocks_translate|capitalize}:</b>
<div style="margin-left:10px;margin-bottom:0.5em;">
	{$available_workers = DAO_Worker::getAllActive()}

	<select name="{$namePrefix}[owner_id]">
		<option value="0"></option>
		{foreach from=$available_workers item=worker}
		<option value="{$worker->id}" {if $worker->id == $params.owner_id}selected="selected"{/if}>{$worker->getName()}</option>
		{/foreach}
	</select>
</div>

{if !empty($custom_fields)}
<fieldset class="peek">
	<legend>{'common.custom_fields'|devblocks_translate|capitalize}:</legend>
	{include file="devblocks:cerberusweb.core::internal/custom_fields/bulk/form.tpl" bulk=false field_wrapper="{$namePrefix}"}
</fieldset>
{/if}

{include file="devblocks:cerberusweb.core::internal/decisions/actions/_shared_add_custom_fieldsets.tpl" context=CerberusContexts::CONTEXT_TICKET field_wrapper="{$namePrefix}"}

<b>{'common.watchers'|devblocks_translate|capitalize}:</b>
<div style="margin-left:10px;margin-bottom:0.5em;">
	{include file="devblocks:cerberusweb.core::internal/decisions/actions/_shared_var_worker_picker.tpl" param_name="worker_id" values_to_contexts=$values_to_contexts}
</div>

{if !empty($values_to_contexts)}
<b>Link to:</b>
<div style="margin-left:10px;margin-bottom:0.5em;">
{include file="devblocks:cerberusweb.core::internal/decisions/actions/_shared_var_picker.tpl" param_name="link_to" values_to_contexts=$values_to_contexts}
</div>
{/if}

<b>Also create records in simulator mode:</b>
<div style="margin-left:10px;margin-bottom:10px;">
	<label><input type="radio" name="{$namePrefix}[run_in_simulator]" value="1" {if $params.run_in_simulator}checked="checked"{/if}> {'common.yes'|devblocks_translate|capitalize}</label>
	<label><input type="radio" name="{$namePrefix}[run_in_simulator]" value="0" {if !$params.run_in_simulator}checked="checked"{/if}> {'common.no'|devblocks_translate|capitalize}</label>
</div>

{* Check for object variables *}
{capture name="object_vars"}
{foreach from=$trigger->variables item=var key=var_key}
{if $var.type == "ctx_{CerberusContexts::CONTEXT_TICKET}"}
<option value="{$var_key}" {if $params.object_var==$var_key}selected="selected"{/if}>{$var.label}</option>
{/if}
{/foreach}
{/capture}

{if $smarty.capture.object_vars}
<b>Add object to list variable:</b><br>
<div style="margin-left:10px;margin-bottom:10px;">
	<select name="{$namePrefix}[object_var]">
		<option value=""></option>
		{$smarty.capture.object_vars nofilter}
	</select>
</div>
{/if}

<script type="text/javascript">
$(function() {
	var $action = $('fieldset#{$namePrefix}');
	$action.find('textarea').autosize();
	$action.find('input:radio[name$="[status]"]').change(function() {
		var $val = $(this).val();
		
		if($val != '0')
			$action.find('div.peek-status-reopen').fadeIn();
		else
			$action.find('div.peek-status-reopen').hide();
	});
});
</script>