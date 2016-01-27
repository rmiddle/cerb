<form action="{devblocks_url}{/devblocks_url}" method="POST" id="formBatchUpdate" name="formBatchUpdate" onsubmit="return false;">
<input type="hidden" name="c" value="contacts">
<input type="hidden" name="a" value="doOrgBulkUpdate">
<input type="hidden" name="view_id" value="{$view_id}">
<input type="hidden" name="org_ids" value="{$org_ids}">
<input type="hidden" name="_csrf_token" value="{$session.csrf_token}">

<fieldset class="peek">
	<legend>{'common.bulk_update.with'|devblocks_translate|capitalize}</legend>
	<label><input type="radio" name="filter" value="" {if empty($org_ids)}checked{/if}> {'common.bulk_update.filter.all'|devblocks_translate}</label> 
 	{if !empty($org_ids)}
		<label><input type="radio" name="filter" value="checks" {if !empty($org_ids)}checked{/if}> {'common.bulk_update.filter.checked'|devblocks_translate}</label> 
	{else}
		<label><input type="radio" name="filter" value="sample"> {'common.bulk_update.filter.random'|devblocks_translate} </label><input type="text" name="filter_sample_size" size="5" maxlength="4" value="100" class="input_number">
	{/if}
</fieldset>

<fieldset class="peek">
	<legend>Set Fields</legend>
	
	<table cellspacing="0" cellpadding="2" width="100%">
		<tr>
			<td width="0%" nowrap="nowrap" align="right">{'contact_org.country'|devblocks_translate|capitalize}:</td>
			<td width="100%">
				<input type="text" name="country" value="" size="35">
			</td>
		</tr>
		
		{if $active_worker->hasPriv('core.watchers.assign') || $active_worker->hasPriv('core.watchers.unassign')}
		<tr>
			<td width="0%" nowrap="nowrap" valign="top" align="right">{'common.watchers'|devblocks_translate|capitalize}:</td>
			<td width="100%">
				{if $active_worker->hasPriv('core.watchers.assign')}
				<button type="button" class="chooser-worker add"><span class="glyphicons glyphicons-search"></span></button>
				<br>
				{/if}
				
				{if $active_worker->hasPriv('core.watchers.unassign')}
				<button type="button" class="chooser-worker remove"><span class="glyphicons glyphicons-search"></span></button>
				{/if}
			</td>
		</tr>
		{/if}
	</table>
</fieldset>

{if !empty($custom_fields)}
<fieldset class="peek">
	<legend>Set Custom Fields</legend>
	{include file="devblocks:cerberusweb.core::internal/custom_fields/bulk/form.tpl" bulk=true}	
</fieldset>
{/if}

{include file="devblocks:cerberusweb.core::internal/custom_fieldsets/peek_custom_fieldsets.tpl" context=CerberusContexts::CONTEXT_ORG bulk=true}

{include file="devblocks:cerberusweb.core::internal/macros/behavior/bulk.tpl" macros=$macros}

{if $active_worker->hasPriv('core.addybook.org.actions.update')}<button type="button" class="submit"><span class="glyphicons glyphicons-circle-ok" style="color:rgb(0,180,0);"></span> {'common.save_changes'|devblocks_translate|capitalize}</button>{/if}
<br>
</form>

<script type="text/javascript">
$(function() {
	var $popup = genericAjaxPopupFetch('peek');
	
	$popup.one('popup_open', function(event,ui) {
		$popup.dialog('option','title',"{'common.bulk_update'|devblocks_translate|escape:'javascript' nofilter}");
		
		$popup.find('button.submit').click(function() {
			genericAjaxPost('formBatchUpdate', 'view{$view_id}', null, function() {
				genericAjaxPopupClose($popup);
			});
		})
		
		$('#formBatchUpdate button.chooser-worker').each(function() {
			var $button = $(this);
			var context = 'cerberusweb.contexts.worker';
			
			if($button.hasClass('remove'))
				ajax.chooser(this, context, 'do_watcher_remove_ids', { autocomplete: true, autocomplete_class:'input_remove' } );
			else
				ajax.chooser(this, context, 'do_watcher_add_ids', { autocomplete: true, autocomplete_class:'input_add'} );
		});
	});
});
</script>