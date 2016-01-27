{$div_id = "peek{uniqid()}"}

<div id="{$div_id}">
	<div style="float:left;margin-right:10px;">
		<img src="{devblocks_url}c=avatars&context=org&context_id={$dict->org_id}{/devblocks_url}?v={$dict->org_updated}" style="height:75px;width:75px;border-radius:5px;vertical-align:middle;">
	</div>
	
	<div style="float:left;font-weight:bold;">
		<div>
			{$dict->mask}
		</div>
	
		<h1 style="color:inherit;">
			{$dict->subject}
		</h1>
		
		<div style="margin-top:5px;">
			{if !empty($dict->id)}
				{$object_recommendations = DAO_ContextRecommendation::getByContexts(CerberusContexts::CONTEXT_TICKET, array($dict->id))}
				{include file="devblocks:cerberusweb.core::internal/recommendations/context_recommend_button.tpl" context=CerberusContexts::CONTEXT_TICKET context_id=$dict->id full=true recommend_group_id=$dict->group_id recommend_bucket_id=$dict->bucket_id}
			{/if}
		
			{if !empty($dict->id)}
				{$object_watchers = DAO_ContextLink::getContextLinks(CerberusContexts::CONTEXT_TICKET, array($dict->id), CerberusContexts::CONTEXT_WORKER)}
				{include file="devblocks:cerberusweb.core::internal/watchers/context_follow_button.tpl" context=CerberusContexts::CONTEXT_TICKET context_id=$dict->id full=true}
			{/if}
			
			<button type="button" class="cerb-peek-edit" data-context="{CerberusContexts::CONTEXT_TICKET}" data-context-id="{$dict->id}" data-edit="true"><span class="glyphicons glyphicons-cogwheel"></span> {'common.edit'|devblocks_translate|capitalize}</button>
			{if $dict->id}<button type="button" class="cerb-peek-profile"><span class="glyphicons glyphicons-nameplate"></span> {'common.profile'|devblocks_translate|capitalize}</button>{/if}
			
			{*
			<button type="button" class="split-left" onclick="$(this).next('button').click();" title="{'common.virtual_attendants'|devblocks_translate|capitalize}"><img src="{devblocks_url}c=avatars&context=app&id=0{/devblocks_url}" style="width:22px;height:22px;margin:-3px 0px 0px 2px;"></button><!--  
			--><button type="button" class="split-right" id="btnDisplayMacros"><span class="glyphicons glyphicons-chevron-down" style="font-size:12px;color:white;"></span></button>
			*}
			
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
			{if $k == 'status'}
				<label>{$labels.$k}</label>
				{$dict->$k}
				{if $dict->is_waiting || $dict->is_closed}
					{if $dict->reopen_date} (<abbr title="{$dict->reopen_date|devblocks_date}">{$dict->reopen_date|devblocks_prettytime}</abbr>){/if}
				{/if}
			{elseif $k == 'importance'}
				<label>{$labels.$k}</label>
				<div style="display:inline-block;margin-top:5px;width:75px;height:8px;background-color:rgb(220,220,220);border-radius:8px;">
					<div style="position:relative;top:-1px;margin-left:-5px;left:{$dict->importance}%;width:10px;height:10px;border-radius:10px;background-color:{if $dict->importance < 50}rgb(0,200,0);{elseif $dict->importance > 50}rgb(230,70,70);{else}rgb(175,175,175);{/if}"></div>
				</div>
			{elseif $k == 'spam_training'}
				<label>{$labels.$k}</label>
				{if $dict->$k == 'N'}
					{'common.notspam'|devblocks_translate|capitalize}
				{elseif $dict->$k == 'S'}
					{'common.spam'|devblocks_translate|capitalize}
				{/if}
			{else}
				{include file="devblocks:cerberusweb.core::internal/peek/peek_property_grid_cell.tpl" dict=$dict k=$k labels=$labels types=$types}
			{/if}
		</div>
		{/if}
		{/foreach}
	</div>
	
	<div style="clear:both;"></div>
	
	<div style="margin-top:5px;">
		<button type="button" class="cerb-search-trigger" data-context="{CerberusContexts::CONTEXT_ADDRESS}" data-query="ticket.id:{$dict->id}"><div class="badge-count">{$activity_counts.participants|default:0}</div> {'common.participants'|devblocks_translate|capitalize}</button>
		<button type="button" class="cerb-search-trigger" data-context="{CerberusContexts::CONTEXT_MESSAGE}" data-query="ticket.id:{$dict->id}"><div class="badge-count">{$activity_counts.messages|default:0}</div> {'common.messages'|devblocks_translate|capitalize}</button>
	</div>
</fieldset>

<div class="cerb-peek-timeline-pager">
	<table width="100%" cellpadding="0" cellspacing="0">
		<tr>
			<td width="40%" align="right" nowrap="nowrap">
				<button type="button" class="cerb-button-first"><span class="glyphicons glyphicons-fast-backward"></span></button>
				<button type="button" class="cerb-button-prev"><span class="glyphicons glyphicons-step-backward"></span></button>
			</td>
			<td width="20%" align="center" nowrap="nowrap" style="font-weight:bold;font-size:1.2em;padding:0px 10px;">
				<span class="cerb-peek-timeline-label"></span>
			</td>
			<td width="40%" align="left" nowrap="nowrap">
				<button type="button" class="cerb-button-next"><span class="glyphicons glyphicons-step-forward"></span></button>
				<button type="button" class="cerb-button-last"><span class="glyphicons glyphicons-fast-forward"></span></button>
			</td>
		</tr>
	</table>
</div>

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
	
	var $timeline = {
		'objects': [],
		'length': {count($timeline)},
		'last': 0,
		'index': 0,
		'context': '',
		'context_id': 0,
	};
	
	{foreach from=$timeline item=timeline_object name=timeline_objects key=idx}
	{if $timeline_object instanceof Model_Message}
		{$context = CerberusContexts::CONTEXT_MESSAGE}
		{$context_id = $timeline_object->id}
	{elseif $timeline_object instanceof Model_Comment}
		{$context = CerberusContexts::CONTEXT_COMMENT}
		{$context_id = $timeline_object->id}
	{/if}
	{if $smarty.foreach.timeline_objects.last}
		$timeline.last = {$idx};
		$timeline.index = {$idx};
		$timeline.context = '{$context}';
		$timeline.context_id = {$context_id};
	{/if}
	$timeline.objects.push({ 'context': '{$context}', 'context_id': {$context_id} });
	{/foreach}
	
	$popup.one('popup_open',function(event,ui) {
		$popup.dialog('option','title', "{'common.ticket'|devblocks_translate|capitalize|escape:'javascript' nofilter}");
		
		// Properties grid
		$popup.find('div.cerb-properties-grid').cerbPropertyGrid();
		
		// Edit button
		$popup.find('button.cerb-peek-edit')
			.cerbPeekTrigger({ 'view_id': '{$view_id}' })
			.on('cerb-peek-saved', function(e) {
				genericAjaxPopup($layer,'c=internal&a=showPeekPopup&context={CerberusContexts::CONTEXT_TICKET}&context_id={$dict->id}&view_id={$view_id}','reuse',false,'50%');
			})
			.on('cerb-peek-deleted', function(e) {
				genericAjaxPopupClose($layer);
			})
			;
		
		// Peek triggers
		$popup.find('a.cerb-peek-trigger')
			.cerbPeekTrigger()
			;
		
		// Searches
		$popup.find('button.cerb-search-trigger')
			.cerbSearchTrigger()
			;
		
		// View profile
		$popup.find('.cerb-peek-profile').click(function(e) {
			if(e.metaKey) {
				window.open('{devblocks_url}c=profiles&type=ticket&id={$dict->id}-{$dict->subject|devblocks_permalink}{/devblocks_url}', '_blank');
				
			} else {
				document.location='{devblocks_url}c=profiles&type=ticket&id={$dict->id}-{$dict->subject|devblocks_permalink}{/devblocks_url}';
			}
		});
		
		// Timeline
		var $timeline_fieldset = $popup.find('fieldset.cerb-peek-timeline');
		var $timeline_pager = $popup.find('div.cerb-peek-timeline-pager');
		var $timeline_preview = $popup.find('div.cerb-peek-timeline-preview').width($timeline_fieldset.width());
		
		$timeline_fieldset.on('cerb-redraw', function() {
			// Spinner
			$timeline_preview.html('<span class="cerb-ajax-spinner"></span>');
			
			// Label
			$timeline_pager.find('span.cerb-peek-timeline-label').text('{'common.message'|devblocks_translate|capitalize} ' + ($timeline.index+1) + ' of ' + $timeline.length);
			
			// Paget
			if($timeline.objects.length <= 1)
				$timeline_pager.hide();
			else
				$timeline_pager.show();
			
			// Buttons
			if($timeline.index == 0) {
				$timeline_pager.find('button.cerb-button-first').hide();
				$timeline_pager.find('button.cerb-button-prev').hide();
			} else {
				$timeline_pager.find('button.cerb-button-first').show();
				$timeline_pager.find('button.cerb-button-prev').show();
			}
			
			if($timeline.index == $timeline.last) {
				$timeline_pager.find('button.cerb-button-next').hide();
				$timeline_pager.find('button.cerb-button-last').hide();
			} else {
				$timeline_pager.find('button.cerb-button-next').show();
				$timeline_pager.find('button.cerb-button-last').show();
			}
			
			// Ajax update
			var $timeline_object = $timeline.objects[$timeline.index];
			var context = $timeline_object.context;
			var context_id = $timeline_object.context_id;
			genericAjaxGet($timeline_preview, 'c=profiles&a=handleSectionAction&section=ticket&action=getPeekPreview&context=' + context + '&context_id=' + context_id);
		});
		
		$timeline_pager.find('button.cerb-button-first').click(function() {
			$timeline.index = 0;
			$timeline_fieldset.trigger('cerb-redraw');
		});
		
		$timeline_pager.find('button.cerb-button-prev').click(function() {
			$timeline.index = Math.max(0, $timeline.index - 1);
			$timeline_fieldset.trigger('cerb-redraw');
		});
		
		$timeline_pager.find('button.cerb-button-next').click(function() {
			$timeline.index = Math.min($timeline.last, $timeline.index + 1);
			$timeline_fieldset.trigger('cerb-redraw');
		});
		
		$timeline_pager.find('button.cerb-button-last').click(function() {
			$timeline.index = $timeline.last;
			$timeline_fieldset.trigger('cerb-redraw');
		});
		
		$timeline_fieldset.trigger('cerb-redraw');
	});
});
</script>