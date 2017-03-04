{$div_id = "peek{uniqid()}"}
{$peek_context = CerberusContexts::CONTEXT_CLASSIFIER}
{$is_writeable = Context_Classifier::isWriteableByActor($dict, $active_worker)}

<div id="{$div_id}">
	<div style="float:left;">
		<h1 style="color:inherit;">
			{$dict->_label}
		</h1>
		
		<div style="margin-top:5px;">
			{if $is_writeable}
			<button type="button" class="cerb-peek-edit" data-context="{$peek_context}" data-context-id="{$dict->id}" data-edit="true"><span class="glyphicons glyphicons-cogwheel"></span> {'common.edit'|devblocks_translate|capitalize}</button>
			<button type="button" class="cerb-peek-import" data-context="{$peek_context}" data-context-id="{$dict->id}" data-edit="true"><span class="glyphicons glyphicons-file-import"></span> {'common.import'|devblocks_translate|capitalize}</button>
			{/if}
			
			{if $dict->id}<button type="button" class="cerb-peek-profile"><span class="glyphicons glyphicons-nameplate"></span> {'common.profile'|devblocks_translate|capitalize}</button>{/if}
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
	
	<div style="clear:both;"></div>
	
	<div style="margin-top:5px;">
		<button type="button" class="cerb-search-trigger" data-context="{CerberusContexts::CONTEXT_CLASSIFIER_CLASS}" data-query="classifier.id:{$dict->id}"><div class="badge-count">{$activity_counts.classes|default:0}</div> {'common.classifier.classifications'|devblocks_translate|capitalize}</button>
		<button type="button" class="cerb-search-trigger" data-context="{CerberusContexts::CONTEXT_CLASSIFIER_EXAMPLE}" data-query="classifier.id:{$dict->id}"><div class="badge-count">{$activity_counts.examples|default:0}</div> {'common.examples'|devblocks_translate|capitalize}</button>
	</div>
	
</fieldset>

{include file="devblocks:cerberusweb.core::internal/profiles/profile_record_links.tpl" properties_links=$links peek=true page_context=$peek_context page_context_id=$dict->id}

{include file="devblocks:cerberusweb.core::internal/notifications/context_profile.tpl" context=$peek_context context_id=$dict->id view_id=$view_id}

<fieldset class="peek">
	<legend>Train Classifier</legend>
	<input type="text" class="expression-tester" style="width:100%;" autocomplete="off" spellcheck="false" autofocus="autofocus" placeholder="Enter some text and press ENTER for a classification prediction">
	
	<div class="output" style="margin:5px;"></div>
</fieldset>

<script type="text/javascript">
$(function() {
	var $div = $('#{$div_id}');
	var $popup = genericAjaxPopupFind($div);
	var $layer = $popup.attr('data-layer');
	
	$popup.one('popup_open',function(event,ui) {
		$popup.dialog('option','title', "{'common.classifier'|devblocks_translate|capitalize|escape:'javascript' nofilter}");
		$popup.css('overflow', 'inherit');
		
		// Properties grid
		$popup.find('div.cerb-properties-grid').cerbPropertyGrid();
		
		// Edit button
		{if $is_writeable}
		$popup.find('button.cerb-peek-edit')
			.cerbPeekTrigger({ 'view_id': '{$view_id}' })
			.on('cerb-peek-saved', function(e) {
				genericAjaxPopup($layer,'c=internal&a=showPeekPopup&context={$peek_context}&context_id={$dict->id}&view_id={$view_id}','reuse',false,'50%');
			})
			.on('cerb-peek-deleted', function(e) {
				genericAjaxPopupClose($layer);
			})
			;
		
		$popup.find('button.cerb-peek-import')
			.click(function() {
				var $import_popup = genericAjaxPopup('classifier_import','c=profiles&a=handleSectionAction&section=classifier&action=showImportPopup&classifier_id={$dict->id}',null,false,'50%');
				
				$import_popup.on('dialogclose', function() {
					genericAjaxPopup($layer,'c=internal&a=showPeekPopup&context={$peek_context}&context_id={$dict->id}&view_id={$view_id}','reuse',false,'50%');
				});
			})
		{/if}
		
		// Peeks
		$popup.find('.cerb-peek-trigger')
			.cerbPeekTrigger()
			;
		
		// Searches
		$popup.find('.cerb-search-trigger')
			.cerbSearchTrigger()
			;
		
		// Menus
		$popup.find('ul.cerb-menu').menu();
		
		// View profile
		$popup.find('.cerb-peek-profile').click(function(e) {
			if(e.shiftKey || e.metaKey) {
				window.open('{devblocks_url}c=profiles&type=classifier&id={$dict->id}-{$dict->_label|devblocks_permalink}{/devblocks_url}', '_blank');
				
			} else {
				document.location='{devblocks_url}c=profiles&type=classifier&id={$dict->id}-{$dict->_label|devblocks_permalink}{/devblocks_url}';
			}
		});
		
		// Test classifier
		var $input = $popup.find('INPUT.expression-tester');
		var $output = $popup.find('DIV.output');
			
		$input.on('keyup', function(e) {
			e.stopPropagation();
			
			if(13 == e.keyCode) {
				e.preventDefault();
				
				genericAjaxGet($output, 'c=profiles&a=handleSectionAction&section=classifier&action=predict&classifier_id={$dict->id}&text=' + encodeURIComponent($input.val()), function(json) {
					$input.select().focus();
				});
			}
		});
		
		$output.on('cerb-peek-saved', function() {
			genericAjaxPopup($layer,'c=internal&a=showPeekPopup&context={$peek_context}&context_id={$dict->id}&view_id={$view_id}','reuse',false,'50%');
		});
		
		$input.select().focus();
	});
});
</script>