{if !empty($links)}
{$link_ctxs = Extension_DevblocksContext::getAll(false)}

{* Loop through the link contexts *}
{foreach from=$links key=from_ctx_extid item=from_ctx_ids}
	{$from_ctx = $link_ctxs.$from_ctx_extid}

	{* Loop through the parent records for each link context *}
	{foreach from=$from_ctx_ids key=from_ctx_id item=link_counts}
	
		{* Do we have links to display? *}
		{if $link_ctxs.$from_ctx_extid && !empty($link_counts)}
		<fieldset class="peek">
			<legend>{if $links_label}{$links_label}{else}{if $page_context == $from_ctx_extid && $page_context_id == $from_ctx_id}{else}{$from_ctx->name} {/if}{'common.links'|devblocks_translate|capitalize}{/if}</legend>
			
			<div>
			
			{* Loop through each possible context so they remain alphabetized *}
			{foreach from=$link_ctxs item=link_ctx key=link_ctx_extid name=links}
			{if $link_counts.$link_ctx_extid}
				{$popup_id = "links_{DevblocksPlatform::strAlphaNum($link_ctx_id,'_','_')}"}
				<button type="button" onclick="genericAjaxPopup('{$popup_id}','c=internal&a=linksOpen&context={$from_ctx_extid}&context_id={$from_ctx_id}&to_context={$link_ctx_extid}',null,false,'90%');"><div class="badge-count">{$link_counts.$link_ctx_extid|number_format}</div> {$link_ctx->name}</button>
			{/if}
			{/foreach}
			
			</div>
		</fieldset>
		{/if}
			
	{/foreach}

{/foreach}
{/if}
