{$owner_meta = $comment->getOwnerMeta()}
{$is_writeable = Context_Comment::isWriteableByActor($comment, $active_worker)}

<div class="block" style="overflow:auto;margin-bottom:10px;">
	<span class="tag" style="background-color:rgb(71,133,210);color:white;margin-right:5px;">{'common.comment'|devblocks_translate|lower}</span>
	
	<b style="font-size:1.3em;">
		{if empty($owner_meta)}
			(system)
		{else}
			{if $owner_meta.context_ext instanceof IDevblocksContextPeek} 
			<a href="javascript:;" class="cerb-peek-trigger" data-context="{$comment->owner_context}" data-context-id="{$comment->owner_context_id}">{$owner_meta.name}</a>
			{elseif !empty($owner_meta.permalink)} 
			<a href="{$owner_meta.permalink}" target="_blank">{$owner_meta.name}</a>
			{else}
			{$owner_meta.name}
			{/if}
		{/if}
	</b>
	
	({$owner_meta.context_ext->manifest->name|lower})
	
	<div class="toolbar" style="display:none;float:right;margin-right:20px;">
		{if $comment->context == CerberusContexts::CONTEXT_TICKET}
			{$permalink_url = "{devblocks_url full=true}c=profiles&type=ticket&mask={$ticket->mask}&focus=comment&focus_id={$comment->id}{/devblocks_url}"}
			<button type="button" onclick="genericAjaxPopup('permalink', 'c=internal&a=showPermalinkPopup&url={$permalink_url|escape:'url'}');" title="{'common.permalink'|devblocks_translate|lower}"><span class="glyphicons glyphicons-link"></span></button>
		{/if}
		
		<button type="button" class="cerb-peek-trigger" data-context="{CerberusContexts::CONTEXT_COMMENT}" data-context-id="{$comment->id}"><span class="glyphicons glyphicons-cogwheel" title="{'common.edit'|devblocks_translate|lower}"></span></button>
	</div>
	
	{if isset($owner_meta.context_ext->manifest->params.alias)}
	<div style="float:left;margin:0px 5px 5px 0px;">
		<img src="{devblocks_url}c=avatars&context={$owner_meta.context_ext->manifest->params.alias}&context_id={$owner_meta.id}{/devblocks_url}?v={$owner_meta.updated}" style="height:64px;width:64px;border-radius:64px;">
	</div>
	{/if}
	
	<br>
	
	{if isset($comment->created)}<b>{'message.header.date'|devblocks_translate|capitalize}:</b> {$comment->created|devblocks_date} ({$comment->created|devblocks_prettytime})<br>{/if}
	
	<pre class="emailbody" style="padding-top:10px;">{$comment->comment|trim|escape|devblocks_hyperlinks nofilter}</pre>
	<br clear="all">
	
	{* Attachments *}
	{include file="devblocks:cerberusweb.core::internal/attachments/list.tpl" context="{CerberusContexts::CONTEXT_COMMENT}" context_id=$comment->id attachments=[]}
</div>

<script type="text/javascript">
$(function() {
	var $comment = $('#comment{$comment->id}')
		.hover(
			function() {
				$(this).find('div.toolbar').show();
			},
			function() {
				$(this).find('div.toolbar').hide();
			}
		)
		.find('.cerb-peek-trigger')
			.cerbPeekTrigger()
		;
});
</script>