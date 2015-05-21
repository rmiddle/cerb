{$page_context = CerberusContexts::CONTEXT_TICKET}
{$page_context_id = $ticket->id}

{if !empty($merge_parent)}
	<div class="help-box">
	<h1>This ticket was merged</h1>
	
	<p>
	You can find the new ticket here: <a href="{devblocks_url}c=profiles&w=ticket&mask={$merge_parent->mask}{/devblocks_url}"><b>[#{$merge_parent->mask}] {$merge_parent->subject}</b></a>
	</p>
	</div>
{/if}

<div style="float:left">
	<h1>{$ticket->subject}</h1>
</div>

<div style="float:right">
	{$ctx = Extension_DevblocksContext::get($page_context)}
	{include file="devblocks:cerberusweb.core::search/quick_search.tpl" view=$ctx->getSearchView() return_url="{devblocks_url}c=search&context={$ctx->manifest->params.alias}{/devblocks_url}"}
</div>

<div style="clear:both;"></div>

{assign var=ticket_group_id value=$ticket->group_id}
{assign var=ticket_group value=$groups.$ticket_group_id}
{assign var=ticket_bucket_id value=$ticket->bucket_id}
{assign var=ticket_group_bucket_set value=$group_buckets.$ticket_group_id}
{assign var=ticket_bucket value=$ticket_group_bucket_set.$ticket_bucket_id}

<div class="cerb-profile-toolbar">
	<form class="toolbar" action="{devblocks_url}{/devblocks_url}" method="post" style="margin-top:5px;margin-bottom:5px;">
		<input type="hidden" name="c" value="display">
		<input type="hidden" name="a" value="updateProperties">
		<input type="hidden" name="id" value="{$ticket->id}">
		<input type="hidden" name="closed" value="{if $ticket->is_closed}1{else}0{/if}">
		<input type="hidden" name="deleted" value="{if $ticket->is_deleted}1{else}0{/if}">
		<input type="hidden" name="spam" value="0">
		
		<span id="spanWatcherToolbar">
		{$object_watchers = DAO_ContextLink::getContextLinks($page_context, array($page_context_id), CerberusContexts::CONTEXT_WORKER)}
		{include file="devblocks:cerberusweb.core::internal/watchers/context_follow_button.tpl" context=$page_context context_id=$page_context_id full=true}
		</span>
		
		<!-- Macros -->
		{devblocks_url assign=return_url full=true}c=profiles&type=ticket&id={$ticket->mask}{/devblocks_url}
		{include file="devblocks:cerberusweb.core::internal/macros/display/button.tpl" context=$page_context context_id=$page_context_id macros=$macros return_url=$return_url}
		
		<!-- Edit -->		
		<button type="button" id="btnDisplayTicketEdit" title="{'common.edit'|devblocks_translate|capitalize} (E)"><span class="glyphicons glyphicons-cogwheel"></span></button>
		
		{if !$ticket->is_deleted}
			{if $ticket->is_closed}
				<button type="button" title="{'common.reopen'|devblocks_translate|capitalize}" onclick="this.form.closed.value='0';this.form.submit();"><span class="glyphicons glyphicons-upload"></span></button>
			{else}
				{if $active_worker->hasPriv('core.ticket.actions.close')}<button title="{'display.shortcut.close'|devblocks_translate|capitalize}" id="btnClose" type="button" onclick="this.form.closed.value=1;this.form.submit();"><span class="glyphicons glyphicons-circle-ok"></span></button>{/if}
			{/if}
			
			{if empty($ticket->spam_training)}
				{if $active_worker->hasPriv('core.ticket.actions.spam')}<button title="{'display.shortcut.spam'|devblocks_translate|capitalize}" id="btnSpam" type="button" onclick="this.form.spam.value='1';this.form.submit();"><span class="glyphicons glyphicons-ban"></span></button>{/if}
			{/if}
		{/if}
		
		{if $ticket->is_deleted}
			<button type="button" title="{'common.undelete'|devblocks_translate|capitalize}" onclick="this.form.deleted.value='0';this.form.closed.value=0;this.form.submit();"><span class="glyphicons glyphicons-upload"></span></button>
		{else}
			{if $active_worker->hasPriv('core.ticket.actions.delete')}<button title="{'display.shortcut.delete'|devblocks_translate}" id="btnDelete" type="button" onclick="this.form.deleted.value=1;this.form.closed.value=1;this.form.submit();"><span class="glyphicons glyphicons-circle-remove"></span></button>{/if}
		{/if}
		
		{if $active_worker->hasPriv('core.ticket.view.actions.merge')}<button id="btnMerge" type="button" onclick="genericAjaxPopup('merge','c=display&a=showMergePanel&ticket_id={$ticket->id}',null,false,'500');" title="{'mail.merge'|devblocks_translate|capitalize}"><span class="glyphicons glyphicons-git-merge"></span></button>{/if}
		
		<button id="btnPrint" title="{'display.shortcut.print'|devblocks_translate}" type="button" onclick="document.frmPrint.action='{devblocks_url}c=print&a=ticket&id={$ticket->mask}{/devblocks_url}';document.frmPrint.submit();"><span class="glyphicons glyphicons-print"></span></button>
		<button type="button" title="{'common.refresh'|devblocks_translate|capitalize}" onclick="document.location='{devblocks_url}c=profiles&type=ticket&id={$ticket->mask}{/devblocks_url}';"><span class="glyphicons glyphicons-refresh"></span></button>
	</form>
	
	<form action="{devblocks_url}{/devblocks_url}" method="post" name="frmPrint" id="frmPrint" target="_blank" style="display:none;"></form>
	
	{if $pref_keyboard_shortcuts}
	<small>
		{'common.keyboard'|devblocks_translate|lower}:
		(<b>e</b>) {'common.edit'|devblocks_translate|lower} 
		(<b>i</b>) {'ticket.requesters'|devblocks_translate|lower} 
		(<b>w</b>) {'common.watch'|devblocks_translate|lower}  
		{if $active_worker->hasPriv('core.display.actions.comment')}(<b>o</b>) {'common.comment'|devblocks_translate} {/if}
		{if !empty($macros)}(<b>m</b>) {'common.macros'|devblocks_translate|lower} {/if}
		{if !$ticket->is_closed && $active_worker->hasPriv('core.ticket.actions.close')}(<b>c</b>) {'common.close'|devblocks_translate|lower} {/if}
		{if !$ticket->spam_trained && $active_worker->hasPriv('core.ticket.actions.spam')}(<b>s</b>) {'common.spam'|devblocks_translate|lower} {/if}
		{if !$ticket->is_deleted && $active_worker->hasPriv('core.ticket.actions.delete')}(<b>x</b>) {'common.delete'|devblocks_translate|lower} {/if}
		{if empty($ticket->owner_id)}(<b>t</b>) {'common.assign'|devblocks_translate|lower} {/if}
		{if !empty($ticket->owner_id)}(<b>u</b>) {'common.unassign'|devblocks_translate|lower} {/if}
		{if !$expand_all}(<b>a</b>) {'display.button.read_all'|devblocks_translate|lower} {/if} 
		{if $active_worker->hasPriv('core.display.actions.reply')}(<b>r</b>) {'display.ui.reply'|devblocks_translate|lower} {/if}  
		(<b>p</b>) {'common.print'|devblocks_translate|lower} 
		(<b>1-9</b>) change tab 
	</small>
	{/if}
</div>

<fieldset class="properties">
	<legend>
            {'common.conversation'|devblocks_translate|capitalize}
            {if DevblocksPlatform::isPluginEnabled('cerberusweb.timetracking')}
                - Total Ticket Time Worked: {$total_time_hours} Hours {$total_time_minutes} Mins&nbsp;
            {/if}
        </legend>

	<div style="margin-left:15px;">

	{foreach from=$properties item=v key=k name=props}
		<div class="property">
			{if $k == 'mask'}
				<b id="tour-profile-ticket-mask">{'ticket.mask'|devblocks_translate|capitalize}:</b>
				{$ticket->mask} 
				(#{$ticket->id})
			{elseif $k == 'status'}
				<b>{'common.status'|devblocks_translate|capitalize}:</b>
				{if $ticket->is_deleted}
					<span style="font-weight:bold;color:rgb(150,0,0);">{'status.deleted'|devblocks_translate}</span>
				{elseif $ticket->is_closed}
					<span style="font-weight:bold;color:rgb(50,115,185);">{'status.closed'|devblocks_translate}</span>
					{if !empty($ticket->reopen_at)}
						(opens in <abbr title="{$ticket->reopen_at|devblocks_date}">{$ticket->reopen_at|devblocks_prettytime}</abbr>)
					{/if}
				{elseif $ticket->is_waiting}
					<span style="font-weight:bold;color:rgb(50,115,185);">{'status.waiting'|devblocks_translate}</span>
					{if !empty($ticket->reopen_at)}
						(opens in <abbr title="{$ticket->reopen_at|devblocks_date}">{$ticket->reopen_at|devblocks_prettytime}</abbr>)
					{/if}
				{else}
					{'status.open'|devblocks_translate}
				{/if} 
			{elseif $k == 'org'}
				{$ticket_org = $ticket->getOrg()}
				<b>{'contact_org.name'|devblocks_translate|capitalize}:</b>
				{if !empty($ticket_org)}
				<a href="javascript:;" onclick="genericAjaxPopup('peek','c=internal&a=showPeekPopup&context={CerberusContexts::CONTEXT_ORG}&context_id={$ticket->org_id}',null,false,'500');">{$ticket_org->name}</a>
				{/if}
			{elseif $k == 'bucket'}
				<b>{'common.bucket'|devblocks_translate|capitalize}:</b>
				[{$groups.$ticket_group_id->name}]  
				{if !empty($ticket_bucket_id)}
					{$ticket_bucket->name}
				{/if}
			{elseif $k == 'owner'}
				{if !empty($ticket->owner_id) && isset($workers.{$ticket->owner_id})}
					{$owner = $workers.{$ticket->owner_id}}
					<b>{'common.owner'|devblocks_translate|capitalize}:</b>
					<a href="{devblocks_url}c=profiles&p=worker&id={$owner->id}-{$owner->getName()|devblocks_permalink}{/devblocks_url}" target="_blank">{$owner->getName()}</a>
				{else}
					<b>{'common.owner'|devblocks_translate|capitalize}:</b>
					{'common.nobody'|devblocks_translate|lower}
				{/if}
			{else}
				{include file="devblocks:cerberusweb.core::internal/custom_fields/profile_cell_renderer.tpl"}
			{/if}
		</div>
		{if $smarty.foreach.props.iteration % 3 == 0 && !$smarty.foreach.props.last}
			<br clear="all">
		{/if}
	{/foreach}
	<br clear="all">
	
	<a style="color:black;font-weight:bold;" href="javascript:;" id="aRecipients" onclick="genericAjaxPopup('peek','c=display&a=showRequestersPanel&ticket_id={$ticket->id}',null,true,'500');">{'ticket.requesters'|devblocks_translate|capitalize}</a>:
	<span id="displayTicketRequesterBubbles">
		{include file="devblocks:cerberusweb.core::display/rpc/requester_list.tpl" ticket_id=$ticket->id}
	</span>
	<br clear="all">
	</div>
</fieldset>

{include file="devblocks:cerberusweb.core::internal/custom_fieldsets/profile_fieldsets.tpl" properties=$properties_custom_fieldsets}

{include file="devblocks:cerberusweb.core::internal/profiles/profile_record_links.tpl" properties=$properties_links}

<div>
{include file="devblocks:cerberusweb.core::internal/notifications/context_profile.tpl" context=$page_context context_id=$page_context_id}
</div>

<div>
{include file="devblocks:cerberusweb.core::internal/macros/behavior/scheduled_behavior_profile.tpl" context=$page_context context_id=$page_context_id}
</div>

<div id="profileTicketTabs">
	<ul>
		{$tabs = []}

		{$tabs[] = 'conversation'}
		<li data-alias="conversation"><a href="{devblocks_url}ajax.php?c=display&a=showConversation&point={$point}&ticket_id={$ticket->id}{if $convo_focus_ctx && $convo_focus_ctx_id}&focus_ctx={$convo_focus_ctx}&focus_ctx_id={$convo_focus_ctx_id}{/if}{if $expand_all}&expand_all=1{/if}{/devblocks_url}">{'display.tab.timeline'|devblocks_translate|capitalize}</a></li>
				
		{$tabs[] = 'links'}
		<li data-alias="links"><a href="{devblocks_url}ajax.php?c=internal&a=showTabContextLinks&point={$point}&context=cerberusweb.contexts.ticket&id={$ticket->id}{/devblocks_url}">{'common.links'|devblocks_translate} <div class="tab-badge">{DAO_ContextLink::count($page_context, $page_context_id)|default:0}</div></a></li>
		
		{$tabs[] = 'activity'}
		<li data-alias="activity"><a href="{devblocks_url}ajax.php?c=internal&a=showTabActivityLog&scope=target&point={$point}&context={$page_context}&context_id={$page_context_id}{/devblocks_url}">{'common.activity_log'|devblocks_translate|capitalize}</a></li>
		
		{$tabs[] = 'history'}
		<li data-alias="history"><a href="{devblocks_url}ajax.php?c=display&a=showContactHistory&point={$point}&ticket_id={$ticket->id}{/devblocks_url}">{'display.tab.history'|devblocks_translate} <div class="tab-badge">{DAO_Ticket::getViewCountForRequesterHistory('contact_history', $ticket, $visit->get('display.history.scope', ''))|default:0}</div></a></li>

		{foreach from=$tab_manifests item=tab_manifest}
			{$tabs[] = $tab_manifest->params.uri}
			<li><a href="{devblocks_url}ajax.php?c=profiles&a=showTab&ext_id={$tab_manifest->id}&point={$point}&context={$page_context}&context_id={$page_context_id}{/devblocks_url}"><i>{$tab_manifest->params.title|devblocks_translate}</i></a></li>
		{/foreach}
	</ul>
</div>
<br>

<script type="text/javascript">
$(function() {
	// Tabs
	
	var tabOptions = Devblocks.getDefaultjQueryUiTabOptions();
	tabOptions.active = Devblocks.getjQueryUiTabSelected('profileTicketTabs');
	
	var tabs = $("#profileTicketTabs").tabs(tabOptions);
	
	$('#btnDisplayTicketEdit').bind('click', function() {
		$popup = genericAjaxPopup('peek','c=internal&a=showPeekPopup&context={$page_context}&context_id={$page_context_id}&edit=1',null,false,'650');
		$popup.one('ticket_save', function(event) {
			event.stopPropagation();
			document.location.href = '{devblocks_url}c=profiles&type=ticket&id={$ticket->mask}{/devblocks_url}';
		});
	})
});

// Page title
document.title = "[#{$ticket->mask|escape:'javascript' nofilter}] {$ticket->subject|escape:'javascript' nofilter} - {$settings->get('cerberusweb.core','helpdesk_title')|escape:'javascript' nofilter}";

// Menu

{include file="devblocks:cerberusweb.core::internal/macros/display/menu_script.tpl" selector_button=null selector_menu=null}
</script>

{$profile_scripts = Extension_ContextProfileScript::getExtensions(true, $page_context)}
{if !empty($profile_scripts)}
{foreach from=$profile_scripts item=renderer}
	{if method_exists($renderer,'renderScript')}
		{$renderer->renderScript($page_context, $page_context_id)}
	{/if}
{/foreach}
{/if}

<script type="text/javascript">
{if $pref_keyboard_shortcuts}
$(document).keypress(function(event) {
	if(event.altKey || event.ctrlKey || event.metaKey)
		return;
	
	if($(event.target).is(':input'))
		return;

	// We only want shift on the Shift+R shortcut right now
	if(event.shiftKey && event.which != 82)
		return;
	
	hotkey_activated = true;
	
	switch(event.which) {
		case 49:  // (1) tab cycle
		case 50:  // (2) tab cycle
		case 51:  // (3) tab cycle
		case 52:  // (4) tab cycle
		case 53:  // (5) tab cycle
		case 54:  // (6) tab cycle
		case 55:  // (7) tab cycle
		case 56:  // (8) tab cycle
		case 57:  // (9) tab cycle
		case 58:  // (0) tab cycle
			try {
				idx = event.which-49;
				$tabs = $("#profileTicketTabs").tabs();
				$tabs.tabs('option', 'active', idx);
			} catch(ex) { } 
			break;
		case 97:  // (A) read all
			try {
				$('#btnReadAll').click();
			} catch(ex) { } 
			break;
		case 99:  // (C) close
			try {
				$('#btnClose').click();
			} catch(ex) { } 
			break;
		case 101:  // (E) edit
			try {
				$('#btnDisplayTicketEdit').click();
			} catch(ex) { } 
			break;
		case 105:  // (I) recipients
			try {
				$('#aRecipients').click();
			} catch(ex) { } 
			break;
		case 109:  // (M) macros
			try {
				$('#btnDisplayMacros').click();
			} catch(ex) { } 
			break;
		case 111:  // (O) comment
			try {
				$('#btnComment').click();
			} catch(ex) { } 
			break;
		case 112:  // (P) print
			try {
				$('#btnPrint').click();
			} catch(ex) { } 
			break;
		case 82:   // (r)
		case 114:  // (R) reply to first message
			try {
				{if $expand_all}$btn = $('BUTTON.reply').last();{else}$btn = $('BUTTON.reply').first();{/if}
				if(event.shiftKey) {
					$btn.next('BUTTON.split-right').click();
				} else {
					$btn.click();
				}
			} catch(ex) { } 
			break;
		case 115:  // (S) spam
			try {
				$('#btnSpam').click();
			} catch(ex) { } 
			break;
		{if empty($ticket->owner_id)}
		case 116:  // (T) take
			try {
				genericAjaxGet('','c=display&a=doTake&ticket_id={$ticket->id}',function(e) {
					document.location.href = '{devblocks_url}c=profiles&type=ticket&id={$ticket->mask}{/devblocks_url}';
				});
			} catch(ex) { } 
			break;
		{else}
		case 117:  // (U) unassign
			try {
				genericAjaxGet('','c=display&a=doSurrender&ticket_id={$ticket->id}',function(e) {
					document.location.href = '{devblocks_url}c=profiles&type=ticket&id={$ticket->mask}{/devblocks_url}';
				});
			} catch(ex) { } 
			break;
		{/if}
		case 119:  // (W) watch
			try {
				$('#spanWatcherToolbar button:first').click();
			} catch(ex) { } 
			break;
		case 120:  // (X) delete
			try {
				$('#btnDelete').click();
			} catch(ex) { } 
			break;
		default:
			// We didn't find any obvious keys, try other codes
			hotkey_activated = false;
			break;
	}

	if(hotkey_activated)
		event.preventDefault();
});
{/if}
</script>
