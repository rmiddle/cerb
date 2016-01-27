{$div_id = "peek{uniqid()}"}
{$group = $bucket->getGroup()}

<div id="{$div_id}">
	<div style="float:left;margin-right:10px;">
		<img src="{devblocks_url}c=avatars&context=group&context_id={$group->id}{/devblocks_url}?v={$group->updated}" style="height:75px;width:75px;border-radius:5px;vertical-align:middle;">
	</div>
	
	<div style="float:left;">
		{if $group}
		<a href="javascript:;" class="no-underline cerb-peek-trigger" data-context="{CerberusContexts::CONTEXT_GROUP}" data-context-id="{$group->id}"><b>{$group->name}</b></a>
		{/if}
		
		<h1 style="color:inherit;">
			{$bucket->name}
		</h1>
		
		<div style="margin-top:5px;">
			{if $active_worker->is_superuser || $active_worker->isGroupManager($group->id)}<button type="button" class="cerb-peek-edit" data-context="{CerberusContexts::CONTEXT_BUCKET}" data-context-id="{$bucket->id}" data-edit="true"><span class="glyphicons glyphicons-cogwheel"></span> {'common.edit'|devblocks_translate|capitalize}</button>{/if}
			{if $bucket}<button type="button" class="cerb-peek-profile"><span class="glyphicons glyphicons-nameplate"></span> {'common.profile'|devblocks_translate|capitalize}</button>{/if}
		</div>
	</div>
</div>

<div style="clear:both;padding-top:10px;"></div>

<fieldset class="peek">
	<legend>{'common.tickets'|devblocks_translate|capitalize}</legend>
	<div>
		<button type="button" class="cerb-search-trigger" data-context="{CerberusContexts::CONTEXT_TICKET}" data-query="bucket:{$bucket->id} status:o,w,c"><div class="badge-count">{$activity_counts.tickets.total|default:0}</div> {'common.all'|devblocks_translate|capitalize}</button>
		<button type="button" class="cerb-search-trigger" data-context="{CerberusContexts::CONTEXT_TICKET}" data-query="bucket:{$bucket->id} status:o"><div class="badge-count">{$activity_counts.tickets.open|default:0}</div> {'status.open'|devblocks_translate|capitalize}</button>
		<button type="button" class="cerb-search-trigger" data-context="{CerberusContexts::CONTEXT_TICKET}" data-query="bucket:{$bucket->id} status:w"><div class="badge-count">{$activity_counts.tickets.waiting|default:0}</div> {'status.waiting'|devblocks_translate|capitalize}</button>
		<button type="button" class="cerb-search-trigger" data-context="{CerberusContexts::CONTEXT_TICKET}" data-query="bucket:{$bucket->id} status:c"><div class="badge-count">{$activity_counts.tickets.closed|default:0}</div> {'status.closed'|devblocks_translate|capitalize}</button>
	</div>
</fieldset>

{* [TODO] Custom fields and fieldsets *}
{include file="devblocks:cerberusweb.core::internal/peek/peek_links.tpl" links=$links}

<script type="text/javascript">
$(function() {
	var $div = $('#{$div_id}');
	var $popup = genericAjaxPopupFind($div);
	var $layer = $popup.attr('data-layer');

	$popup.one('popup_open',function(event,ui) {
		$popup.dialog('option','title', "{'common.bucket'|devblocks_translate|capitalize|escape:'javascript' nofilter}");
		
		// Edit button
		$popup.find('button.cerb-peek-edit')
			.cerbPeekTrigger({ 'view_id': '{$view_id}' })
			.on('cerb-peek-saved', function(e) {
				genericAjaxPopup($layer,'c=internal&a=showPeekPopup&context={CerberusContexts::CONTEXT_BUCKET}&context_id={$bucket->id}&view_id={$view_id}','reuse',false,'50%');
			})
			.on('cerb-peek-deleted', function(e) {
				genericAjaxPopupClose($layer);
			})
			;
		
		// Peeks
		$popup.find('.cerb-peek-trigger')
			.cerbPeekTrigger()
			;
		
		// Searches
		$popup.find('button.cerb-search-trigger')
			.cerbSearchTrigger()
			;
		
		// View profile
		$popup.find('.cerb-peek-profile').click(function(e) {
			if(e.metaKey) {
				window.open('{devblocks_url}c=profiles&type=bucket&id={$bucket->id}-{$bucket->name|devblocks_permalink}{/devblocks_url}', '_blank');
				
			} else {
				document.location='{devblocks_url}c=profiles&type=bucket&id={$bucket->id}-{$bucket->name|devblocks_permalink}{/devblocks_url}';
			}
		});
		
	});
});
</script>