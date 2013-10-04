<div id="peekTabs">

<ul>
	<li><a href="#orgPeekProps">{'common.properties'|devblocks_translate|capitalize}</a></li>
	{if !empty($contact)}
		<li><a href="{devblocks_url}ajax.php?c=contacts&a=showTabPeople&org={$contact->id}{/devblocks_url}">{'addy_book.org.tabs.people'|devblocks_translate} <div class="tab-badge">{$counts.people}</div></a></li>
		<li><a href="{devblocks_url}ajax.php?c=internal&a=showTabContextComments&context={CerberusContexts::CONTEXT_ORG}&id={$contact->id}{/devblocks_url}">{'common.comments'|devblocks_translate|capitalize}</a></li>
	{/if}
</ul>

<div id="orgPeekProps">
	<form action="{devblocks_url}{/devblocks_url}" method="POST" id="formOrgPeek" name="formOrgPeek" onsubmit="return false;">
	<input type="hidden" name="c" value="contacts">
	<input type="hidden" name="a" value="saveOrgPeek">
	<input type="hidden" name="view_id" value="{$view_id}">
	<input type="hidden" name="id" value="{$contact->id}">
	{if !empty($link_context)}
	<input type="hidden" name="link_context" value="{$link_context}">
	<input type="hidden" name="link_context_id" value="{$link_context_id}">
	{/if}
	<input type="hidden" name="do_delete" value="0">
	
	<fieldset class="peek">
		<legend>{'common.properties'|devblocks_translate}</legend>
		
		<table cellpadding="0" cellspacing="2" border="0" width="98%">
			<tr>
				<td width="0%" nowrap="nowrap" align="right">{'common.name'|devblocks_translate|capitalize}: </td>
				<td width="100%"><input type="text" name="org_name" value="{$contact->name}" style="width:98%;" class="required"></td>
			</tr>
			<tr>
				<td align="right" valign="top">{'contact_org.street'|devblocks_translate|capitalize}: </td>
				<td><textarea name="street" style="width:98%;height:50px;">{$contact->street}</textarea></td>
			</tr>
			<tr>
				<td align="right">{'contact_org.city'|devblocks_translate|capitalize}: </td>
				<td><input type="text" name="city" value="{$contact->city}" style="width:98%;"></td>
			</tr>
			<tr>
				<td align="right">{'contact_org.province'|devblocks_translate|capitalize}.: </td>
				<td><input type="text" name="province" value="{$contact->province}" style="width:98%;"></td>
			</tr>
			<tr>
				<td align="right">{'contact_org.postal'|devblocks_translate|capitalize}: </td>
				<td><input type="text" name="postal" value="{$contact->postal}" style="width:98%;"></td>
			</tr>
			<tr>
				<td align="right">{'contact_org.country'|devblocks_translate|capitalize}: </td>
				<td>
					<input type="text" name="country" id="org_country_input" value="{$contact->country}" style="width:98%;">
				</td>
			</tr>
			<tr>
				<td align="right">{'contact_org.phone'|devblocks_translate|capitalize}: </td>
				<td><input type="text" name="phone" value="{$contact->phone}" style="width:98%;"></td>
			</tr>
			<tr>
				<td align="right">{if !empty($contact->website)}<a href="{$contact->website}" target="_blank">{'contact_org.website'|devblocks_translate|capitalize}</a>{else}{'contact_org.website'|devblocks_translate|capitalize}{/if}: </td>
				<td><input type="text" name="website" value="{$contact->website}" style="width:98%;" class="url"></td>
			</tr>
			
			{* Watchers *}
			<tr>
				<td width="0%" nowrap="nowrap" valign="top" align="right">{'common.watchers'|devblocks_translate|capitalize}: </td>
				<td width="100%">
					{if empty($contact->id)}
						<button type="button" class="chooser_watcher"><span class="cerb-sprite sprite-view"></span></button>
						<ul class="chooser-container bubbles" style="display:block;"></ul>
					{else}
						{$object_watchers = DAO_ContextLink::getContextLinks(CerberusContexts::CONTEXT_ORG, array($contact->id), CerberusContexts::CONTEXT_WORKER)}
						{include file="devblocks:cerberusweb.core::internal/watchers/context_follow_button.tpl" context=CerberusContexts::CONTEXT_ORG context_id=$contact->id full=true}
					{/if}
				</td>
			</tr>
		</table>
	</fieldset>
	
	{if !empty($custom_fields)}
	<fieldset class="peek">
		<legend>{'common.custom_fields'|devblocks_translate}</legend>
		{include file="devblocks:cerberusweb.core::internal/custom_fields/bulk/form.tpl" bulk=false}
	</fieldset>
	{/if}
	
	{include file="devblocks:cerberusweb.core::internal/custom_fieldsets/peek_custom_fieldsets.tpl" context=CerberusContexts::CONTEXT_ORG context_id=$contact->id}
	
	{* Comment *}
	{if !empty($last_comment)}
		{include file="devblocks:cerberusweb.core::internal/comments/comment.tpl" readonly=true comment=$last_comment}
	{/if}
	
	<fieldset class="peek">
		<legend>{'common.comment'|devblocks_translate|capitalize}</legend>
		<textarea name="comment" rows="5" cols="45" style="width:98%;"></textarea>
		<div class="notify" style="display:none;">
			<b>{'common.notify_watchers_and'|devblocks_translate}:</b>
			<button type="button" class="chooser_notify_worker"><span class="cerb-sprite sprite-view"></span></button>
			<ul class="chooser-container bubbles" style="display:block;"></ul>
		</div>
	</fieldset>
	
	{if $active_worker->hasPriv('core.addybook.org.actions.update')}
		<button type="button" onclick="if($('#formOrgPeek').validate().form()) { genericAjaxPopupPostCloseReloadView(null,'formOrgPeek', '{$view_id}', false, 'org_save'); } "><span class="cerb-sprite2 sprite-tick-circle"></span> {'common.save_changes'|devblocks_translate|capitalize}</button>
		{if $active_worker->hasPriv('core.addybook.org.actions.delete') && !empty($contact->id)}<button type="button" onclick="if(confirm('Are you sure you want to permanently delete this organization?')) { $('#formOrgPeek input[name=do_delete]').val('1'); genericAjaxPopupPostCloseReloadView(null,'formOrgPeek','{$view_id}',false,'org_delete'); } "><span class="cerb-sprite2 sprite-cross-circle"></span> {'common.delete'|devblocks_translate|capitalize}</button>{/if}
	{else}
		<div class="error">{'error.core.no_acl.edit'|devblocks_translate}</div>
	{/if}
	 
	 {if !empty($contact->id)}
	<div style="float:right;">
		<a href="{devblocks_url}&c=profiles&type=org&id={$contact->id}-{$contact->name|devblocks_permalink}{/devblocks_url}">{'addy_book.peek.view_full'|devblocks_translate}</a>
	</div>
	{/if}
	</form>
</div><!-- props tab -->

</div><!-- tabs -->

<script type="text/javascript">
	$popup = genericAjaxPopupFind('#orgPeekProps');
	$popup.one('popup_open',function(event,ui) {
		// Title
		$(this).dialog('option','title', "{'contact_org.name'|devblocks_translate|capitalize}");
		
		// Tabs
		$("#peekTabs").tabs();
		
		// Worker autocomplete
		$(this).find('button.chooser_watcher').each(function() {
			ajax.chooser(this,'cerberusweb.contexts.worker','add_watcher_ids', { autocomplete:true });
		});
		
		// Country autocomplete
		ajax.countryAutoComplete('#org_country_input');
		
		// Form validation
	    $("#formOrgPeek").validate();
		$(this).find('textarea[name=comment]').keyup(function() {
			if($(this).val().length > 0) {
				$(this).next('DIV.notify').show();
			} else {
				$(this).next('DIV.notify').hide();
			}
		});
		$('#formOrgPeek :input:text:first').focus();
	});
	$('#formOrgPeek button.chooser_notify_worker').each(function() {
		ajax.chooser(this,'cerberusweb.contexts.worker','notify_worker_ids', { autocomplete:true });
	});
</script>
