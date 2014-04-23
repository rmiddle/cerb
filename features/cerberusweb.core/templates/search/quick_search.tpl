{$pref_token = DAO_WorkerPref::get($active_worker->id, "quicksearch_{get_class($view)|lower}", "")}

{capture "options"}
{foreach from=$view->getParamsAvailable() item=field key=token}
{if !empty($field->db_label) && (!empty($field->type) || ($view instanceof IAbstractView_QuickSearch && $view->isQuickSearchField($token)))}
<option value="{$token}" {if $pref_token==$token}selected="selected"{/if} field_type="{$field->type}">{$field->db_label|capitalize}</option>
{/if}
{/foreach}
{/capture}

{$uniqid = uniqid()}

{if !empty($smarty.capture.options)}
<form action="javascript:;" method="post" id="{$uniqid}" class="quick-search">
	<input type="hidden" name="c" value="search">
	<input type="hidden" name="a" value="ajaxQuickSearch">
	<input type="hidden" name="view_id" value="{$view->id}">
	
	<select name="field">
		{$smarty.capture.options nofilter}
	</select><input type="text" name="query" class="input_search" size="32" value="" autocomplete="off" spellcheck="false">
	
	<div class="hints{if !$is_popup} hints-float{/if} hints-shadow">
		<b>examples:</b> 
		<ul class="bubbles"></ul>
	</div>
</form>
{/if}

<script type="text/javascript">
var $frm = $('#{$uniqid}').each(function(e) {
	var $frm = $(this);
	var $select = $frm.find('select[name=field]');
	var $input = $frm.find('input:text');

	var $bubbles = $select.siblings('div.hints').find('ul.bubbles');
	$bubbles.css('cursor', 'pointer');
	
	$bubbles.on('click', function(e) {
		var txt = $(e.target).text();
		$input.insertAtCursor(txt).select();
	});
	
	$select.bind('load_hints', function(e) {
		var $this = $(this);
		var $token = $this.find('option:selected');
		var token = $token.val();
		var cf_id = $token.attr('cf_id');
		var field_type = $token.attr('field_type');
		
		$bubbles.find('li').remove();
		
{capture "field_hints"}
{foreach from=$view->getParamsAvailable() item=field key=token}

{if $field->type == 'FT' && $field->ft_schema}
	{$schema = Extension_DevblocksSearchSchema::get($field->ft_schema)}
	{if $schema}
	{$engine = $schema->getEngine()}
	{if $engine}
	{$examples = $engine->getQuickSearchExamples($schema)}
	{if $examples}
	else if (token == '{$token}') {
		{foreach from=$examples item=example}
		$bubbles.append($('<li><tt>{$example|escape:'javascript'}</tt></li>'));
		{/foreach}
	}
	{/if}
	{/if}
	{/if}
{/if}

{$cf_id = substr($token,3)}
{if substr($token,0,3) == 'cf_' && !empty($cf_id)}
{$cf = DAO_CustomField::get($cf_id)}
{if $cf->type == 'D' || $cf->type == 'X'}
	else if (token == '{$token}') { 
		{foreach $cf->params.options as $opt}
		$bubbles.append($('<li><tt>{$opt|lower|escape:'javascript'}</tt></li>'));
		{/foreach}
		$bubbles.append($('<li><i>option1,option2</i></li>'));
		$bubbles.append($('<li><i>!option3</i></li>'));
	}
{/if}
{/if}
{/foreach}
{/capture}
	
		// [TODO] This should come from IAbstractView_QuickSearch
		if(token == '*_status' || token == '*_ticket_status') {
			$bubbles.append($('<li><tt>open,waiting</tt></li>'));
			$bubbles.append($('<li><tt>closed</tt></li>'));
			$bubbles.append($('<li><tt>!deleted</tt></li>'));
			$bubbles.append($('<li><tt>o,w</tt></li>'));
			$bubbles.append($('<li><tt>!c,d</tt></li>'));
		}
		
		// [TODO] This should come from IAbstractView_QuickSearch
		else if(token == 't_group_id') {
			{$groups = DAO_Group::getAll()}
			{foreach $groups as $group}
			$bubbles.append($('<li><tt>{$group->name|lower|escape:'javascript'}</tt></li>'));
			{/foreach}
			$bubbles.append($('<li><i>group1, group2</i></li>'));
			$bubbles.append($('<li><i>!group</i></li>'));
		}
		
		{if !empty($smarty.capture.field_hints)}
		{$smarty.capture.field_hints nofilter}
		{/if}
		
		else {
			if (field_type == 'E') {
				$bubbles.append($('<li><tt>today to +5 days</tt></li>'));
				$bubbles.append($('<li><tt>big bang to now</tt></li>'));
				$bubbles.append($('<li><tt>Jan 1 2010 to +1 year</tt></li>'));
				$bubbles.append($('<li><tt>-2 weeks to now</tt></li>'));
				$bubbles.append($('<li><tt>last Monday to next Monday</tt></li>'));
				$bubbles.append($('<li><tt>blank</tt></li>'));
				$bubbles.append($('<li><tt>last Monday to last Friday 23:59 OR today to now</tt></li>'));
				
			} else if (field_type == 'W' || field_type == 'WS') {
				$bubbles.append($('<li><tt>jeff</tt></li>'));
				$bubbles.append($('<li><tt>darren,dan,scott</tt></li>'));
				$bubbles.append($('<li><tt>!jeff</tt></li>'));
				$bubbles.append($('<li><tt>me</tt></li>'));
				$bubbles.append($('<li><tt>any</tt></li>'));
				$bubbles.append($('<li><tt>none</tt></li>'));
				
			} else if (field_type == 'S' || field_type == 'T' || field_type == 'U') {
				$bubbles.append($('<li><tt>some words</tt></li>'));
				$bubbles.append($('<li><tt>"exact phrase"</tt></li>'));
				$bubbles.append($('<li><tt>*substring*</tt></li>'));
				$bubbles.append($('<li><tt>prefix*</tt></li>'));
				$bubbles.append($('<li><tt>*suffix</tt></li>'));
				$bubbles.append($('<li><tt>!text</tt></li>'));
				$bubbles.append($('<li><tt>term1 OR term2</tt></li>'));
				
			} else if (field_type == 'D' || field_type == 'X') {
				$bubbles.append($('<li><tt>option</tt></li>'));
				$bubbles.append($('<li><tt>option1,option2</tt></li>'));
				$bubbles.append($('<li><tt>!option3</tt></li>'));
				
			} else if (field_type == 'FT') {
				$bubbles.append($('<li><tt>a multiple word phrase</tt></li>'));
				$bubbles.append($('<li><tt>"any" "of" "these" "words"</tt></li>'));
				$bubbles.append($('<li><tt>"this phrase" or any of these words</tt></li>'));
				$bubbles.append($('<li><tt>person@example.com</tt></li>'));
				
			} else if (field_type == 'N') {
				$bubbles.append($('<li><tt>&gt; 0</tt></li>'));
				$bubbles.append($('<li><tt>&lt; 100</tt></li>'));
				$bubbles.append($('<li><tt>= 5</tt></li>'));
				$bubbles.append($('<li><tt>!= 20</tt></li>'));
				$bubbles.append($('<li><tt>0 OR &gt; 10</tt></li>'));
				
			} else if (field_type == 'C') {
				$bubbles.append($('<li><tt>yes</tt></li>'));
				$bubbles.append($('<li><tt>no</tt></li>'));
				$bubbles.append($('<li><tt>y</tt></li>'));
				$bubbles.append($('<li><tt>n</tt></li>'));
				$bubbles.append($('<li><tt>true</tt></li>'));
				$bubbles.append($('<li><tt>false</tt></li>'));
				$bubbles.append($('<li><tt>0</tt></li>'));
				$bubbles.append($('<li><tt>1</tt></li>'));
			
			}
		}
	});
	
	$select.change(function(e) {
		$(this)
			.trigger('load_hints')
			.next('input:text[name=query]')
			.focus();
	});
	
	$select.trigger('load_hints');
	
	$input.keydown(function(e) {
		if(e.which == 13) {
			var $txt = $(this);
			
			// [TODO] Store quick search history in HTML localStorage and use it as new hints?
			// [TODO] Adapt examples as text is entered?
			// [TODO] Convert this to autocomplete instead?
			// [TODO] Make them with with TAB focus
			// [TODO] Don't hide the popup until we actually intend to (e.g. not on blur from textbox to popup or SELECT)
			
			genericAjaxPost('{$uniqid}','',null,function(json) {
				if(json.status == true) {
					{if !empty($return_url)}
						window.location.href = '{$return_url}';
					{else}
						$view_filters = $('#viewCustomFilters{$view->id}');
						
						if(0 != $view_filters.length) {
							$view_filters.html(json.html);
							$view_filters.trigger('view_refresh')
						}
					{/if}
				}
				
				$txt.select().focus();
			});
		}
	});
	
	$input.focus(function(e) {
		$('#{$uniqid} div.hints').fadeIn();
	});
	
	$input.blur(function(e) {
		$('#{$uniqid} div.hints').fadeOut();
	});
});


</script>