{$div_id = "peek{uniqid()}"}

<div id="{$div_id}">
	<div style="float:left;">
		<div>
			<h1 style="color:inherit;">{$dict->ticket_subject}</h1>
		</div>

		<div style="margin:5px 0px 10px 0px;">
			{if $dict->ticket_group_is_private && (!$active_worker->isGroupMember($dict->ticket_group_id) && !$active_worker->is_superuser)}
			{else}
				{if $active_worker->hasPriv('core.display.actions.reply')}
				<button type="button" class="cerb-peek-edit" data-context="{CerberusContexts::CONTEXT_MESSAGE}" data-context-id="{$dict->id}" data-edit="true"><span class="glyphicons glyphicons-cogwheel"></span> {'common.edit'|devblocks_translate|capitalize}</button>
				{/if}
			{/if}
			
			{if $dict->ticket_mask}<button type="button" class="cerb-peek-profile"><span class="glyphicons glyphicons-link"></span> {'common.permalink'|devblocks_translate|capitalize}</button>{/if}
		</div>
	</div>
</div>

<div style="clear:both;padding-top:10px;"></div>

<fieldset class="peek">
	<legend>{'common.properties'|devblocks_translate|capitalize}</legend>
	
	<div class="cerb-properties-grid" data-column-width="100">
		{$labels = $dict->_labels}
		{$types = $dict->_types}
		{foreach from=$properties item=k name=props}
			{if $dict->$k}
			<div>
			{if $k == ''}
			{else}
				{include file="devblocks:cerberusweb.core::internal/peek/peek_property_grid_cell.tpl" dict=$dict k=$k labels=$labels types=$types}
			{/if}
			</div>
			{/if}
		{/foreach}
	</div>
</fieldset>

{include file="devblocks:cerberusweb.core::internal/peek/peek_links.tpl" links=$links}

<fieldset class="peek cerb-peek-timeline" style="margin:5px 0px 0px 0px;">
	<div class="cerb-peek-timeline-preview" style="margin:0px 5px;">
		<span class="cerb-ajax-spinner"></span>
	</div>
</fieldset>

<script type="text/javascript">
$(function() {
	var $div = $('#{$div_id}');
	var $popup = genericAjaxPopupFind($div);
	var $layer = $popup.attr('data-layer');
	
	$popup.one('popup_open',function(event,ui) {
		// Title
		$popup.dialog('option','title', '{'common.message'|devblocks_translate|escape:'javascript' nofilter}');
		
		// Properties grid
		$popup.find('div.cerb-properties-grid').cerbPropertyGrid();
		
		// Edit button
		$popup.find('button.cerb-peek-edit')
			.cerbPeekTrigger({ 'view_id': '{$view_id}' })
			.on('cerb-peek-saved', function(e) {
				genericAjaxPopup($layer,'c=internal&a=showPeekPopup&context={CerberusContexts::CONTEXT_MESSAGE}&context_id={$dict->id}&view_id={$view_id}','reuse',false,'50%');
			})
			.on('cerb-peek-deleted', function(e) {
				genericAjaxPopupClose($layer);
			})
			;
		
		// Preview
		var $timeline_fieldset = $popup.find('fieldset.cerb-peek-timeline');
		var $timeline_preview = $popup.find('div.cerb-peek-timeline-preview').width($timeline_fieldset.width());
		genericAjaxGet($timeline_preview, 'c=profiles&a=handleSectionAction&section=ticket&action=getPeekPreview&context={CerberusContexts::CONTEXT_MESSAGE}&context_id={$dict->id}');
		
		// Searches
		$popup.find('button.cerb-search-trigger')
			.cerbSearchTrigger()
			;
		
		// Peek triggers
		$popup.find('.cerb-peek-trigger').cerbPeekTrigger();
		
		// View profile
		$popup.find('.cerb-peek-profile').click(function(e) {
			if(e.metaKey) {
				window.open('{devblocks_url}c=profiles&type=ticket&id={$dict->ticket_mask}&what=message&msgid={$dict->id}{/devblocks_url}', '_blank');
				
			} else {
				document.location='{devblocks_url}c=profiles&type=ticket&id={$dict->ticket_mask}&what=message&msgid={$dict->id}{/devblocks_url}';
			}
		});
	});
});
</script>