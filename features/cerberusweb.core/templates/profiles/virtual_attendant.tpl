{$page_context = CerberusContexts::CONTEXT_VIRTUAL_ATTENDANT}
{$page_context_id = $virtual_attendant->id}

<div style="float:left">
	<h1>{$virtual_attendant->name}</h1>
</div>

<div style="float:right;">
{$ctx = Extension_DevblocksContext::get($page_context)}
{include file="devblocks:cerberusweb.core::search/quick_search.tpl" view=$ctx->getSearchView() return_url="{devblocks_url}c=search&context={$ctx->manifest->params.alias}{/devblocks_url}" reset=true}
</div>

<div style="clear:both;"></div>

<div class="cerb-profile-toolbar">
	<form class="toolbar" action="{devblocks_url}{/devblocks_url}" onsubmit="return false;" style="margin-bottom:5px;">
		<!-- Toolbar -->
		
		<span>
		{$object_watchers = DAO_ContextLink::getContextLinks($page_context, array($page_context_id), CerberusContexts::CONTEXT_WORKER)}
		{include file="devblocks:cerberusweb.core::internal/watchers/context_follow_button.tpl" context=$page_context context_id=$page_context_id full=true}
		</span>
		
		<!-- Macros -->
		{devblocks_url assign=return_url full=true}c=profiles&type=virtual_attendant&id={$page_context_id}-{$virtual_attendant->name|devblocks_permalink}{/devblocks_url}
		{include file="devblocks:cerberusweb.core::internal/macros/display/button.tpl" context=$page_context context_id=$page_context_id macros=$macros return_url=$return_url}
		
		<!-- Edit -->
		{if $active_worker->is_superuser}<button type="button" id="btnDisplayVirtualAttendantEdit" title="{'common.edit'|devblocks_translate|capitalize}">&nbsp;<span class="cerb-sprite2 sprite-gear"></span>&nbsp;</button>{/if}
	</form>
	
	{if $pref_keyboard_shortcuts}
		<small>
		{$translate->_('common.keyboard')|lower}:
		(<b>e</b>) {'common.edit'|devblocks_translate|lower}
		{if !empty($macros)}(<b>m</b>) {'common.macros'|devblocks_translate|lower} {/if}
		(<b>1-9</b>) change tab
		</small>
	{/if}
</div>

<fieldset class="properties">
	<legend>{'Virtual Attendant'|devblocks_translate|capitalize}</legend>

	<div style="margin-left:15px;">
	{foreach from=$properties item=v key=k name=props}
		<div class="property">
			{if $k == '_owner'}
				<b>{'common.owner'|devblocks_translate|capitalize}:</b>
				{$meta = $virtual_attendant->getOwnerMeta()}
				
				{if $meta.permalink}
					<a href="{$meta.permalink}">{$meta.name}</a> ({$meta.context_ext->manifest->name})
				{else}
					{$meta.name} ({$meta.context_ext->manifest->name})
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
	</div>
</fieldset>

{include file="devblocks:cerberusweb.core::internal/custom_fieldsets/profile_fieldsets.tpl" properties=$properties_custom_fieldsets}

<div>
{include file="devblocks:cerberusweb.core::internal/notifications/context_profile.tpl" context=$page_context context_id=$page_context_id}
</div>

<div>
{include file="devblocks:cerberusweb.core::internal/macros/behavior/scheduled_behavior_profile.tpl" context=$page_context context_id=$page_context_id}
</div>

<div id="virtual_attendantTabs">
	<ul>
		{$tabs = [behaviors,behavior]}

		<li><a href="{devblocks_url}ajax.php?c=internal&a=showAttendantBehaviorsTab&id={$page_context_id}&point={$point}{/devblocks_url}">Behaviors</a></li>
		<li><a href="{devblocks_url}ajax.php?c=internal&a=showScheduledBehaviorTab&point={$point}&va_id={$page_context_id}{/devblocks_url}">Scheduled Behaviors</a></li>
		
		{if $virtual_attendant->isWriteableByActor($active_worker)}
		{$tabs[] = 'custom_fieldsets'}
		<li><a href="{devblocks_url}ajax.php?c=internal&a=handleSectionAction&section=custom_fieldsets&action=showTabCustomFieldsets&context={$page_context}&context_id={$page_context_id}&point={$point}{/devblocks_url}">{$translate->_('common.custom_fieldsets')|capitalize}</a></li>
		{/if}

		{if $virtual_attendant->isWriteableByActor($active_worker)}
		{$tabs[] = 'calendars'}
		<li><a href="{devblocks_url}ajax.php?c=internal&a=handleSectionAction&section=calendars&action=showCalendarsTab&context={$page_context}&context_id={$page_context_id}&point={$point}{/devblocks_url}">{$translate->_('common.calendars')|capitalize}</a></li>
		{/if}

		{$tabs[] = 'activity'}
		<li><a href="{devblocks_url}ajax.php?c=internal&a=showTabActivityLog&scope=both&point={$point}&context={$page_context}&context_id={$page_context_id}{/devblocks_url}">{'common.activity_log'|devblocks_translate|capitalize}</a></li>
		
		{$tabs[] = 'comments'}
		<li><a href="{devblocks_url}ajax.php?c=internal&a=showTabContextComments&point={$point}&context={$page_context}&id={$page_context_id}{/devblocks_url}">{$translate->_('common.comments')|capitalize} <div class="tab-badge">{DAO_Comment::count($page_context, $page_context_id)|default:0}</div></a></li>
		
		{$tabs[] = 'links'}
		<li><a href="{devblocks_url}ajax.php?c=internal&a=showTabContextLinks&point={$point}&context={$page_context}&id={$page_context_id}{/devblocks_url}">{$translate->_('common.links')} <div class="tab-badge">{DAO_ContextLink::count($page_context, $page_context_id)|default:0}</div></a></li>

		{foreach from=$tab_manifests item=tab_manifest}
			{$tabs[] = $tab_manifest->params.uri}
			<li><a href="{devblocks_url}ajax.php?c=profiles&a=showTab&ext_id={$tab_manifest->id}&point={$point}&context={$page_context}&context_id={$page_context_id}{/devblocks_url}"><i>{$tab_manifest->params.title|devblocks_translate}</i></a></li>
		{/foreach}
	</ul>
</div>
<br>

{$tab_selected_idx=0}
{foreach from=$tabs item=tab_label name=tabs}
	{if $tab_label==$tab_selected}{$tab_selected_idx = $smarty.foreach.tabs.index}{/if}
{/foreach}

<script type="text/javascript">
	$(function() {
		var tabs = $("#virtual_attendantTabs").tabs( { selected:{$tab_selected_idx} } );
		
		$('#btnDisplayVirtualAttendantEdit').bind('click', function() {
			$popup = genericAjaxPopup('peek','c=internal&a=showPeekPopup&context={$page_context}&context_id={$page_context_id}',null,false,'550');
			$popup.one('virtual_attendant_save', function(event) {
				event.stopPropagation();
				document.location.reload();
			});
		});

		{include file="devblocks:cerberusweb.core::internal/macros/display/menu_script.tpl" selector_button=null selector_menu=null}
	});
</script>

<script type="text/javascript">
{if $pref_keyboard_shortcuts}
$(document).keypress(function(event) {
	if(event.altKey || event.ctrlKey || event.shiftKey || event.metaKey)
		return;
	
	if($(event.target).is(':input'))
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
				$tabs = $("#virtual_attendantTabs").tabs();
				$tabs.tabs('select', idx);
			} catch(ex) { }
			break;
		case 101:  // (E) edit
			try {
				$('#btnDisplayVirtualAttendantEdit').click();
			} catch(ex) { }
			break;
		case 109:  // (M) macros
			try {
				$('#btnDisplayMacros').click();
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

{$profile_scripts = Extension_ContextProfileScript::getExtensions(true, $page_context)}
{if !empty($profile_scripts)}
{foreach from=$profile_scripts item=renderer}
	{if method_exists($renderer,'renderScript')}
		{$renderer->renderScript($page_context, $page_context_id)}
	{/if}
{/foreach}
{/if}
