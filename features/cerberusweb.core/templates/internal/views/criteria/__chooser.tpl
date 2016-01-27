<b>{'search.operator'|devblocks_translate|capitalize}:</b><br>
<blockquote style="margin:5px;">
	<select name="oper">
		<option value="in" {if $param && $param->operator=='in'}selected="selected"{/if}>{'search.oper.in_list'|devblocks_translate}</option>
		<option value="not in" {if $param && $param->operator=='not in'}selected="selected"{/if}>{'search.oper.in_list.not'|devblocks_translate}</option>
	</select>
</blockquote>

<b>{'search.value'|devblocks_translate|capitalize}:</b><br>
<blockquote style="margin:5px;">
	{$random = uniqid()}
	<div id="container_{$random}" style="margin-bottom:5px;">
	
	<button type="button" class="chooser"><span class="glyphicons glyphicons-search"></span></button>
	<ul class="chooser-container bubbles" style="display:block;">
	{foreach from=$param->value item=context_id}
		<li>
			{$context_ext = Extension_DevblocksContext::get($context,true)}
			{$meta = $context_ext->getMeta($context_id)}
			<b>{$meta.name}</b><!--
			--><input type="hidden" name="context_id[]" value="{$context_id}"><!--
			--><span class="glyphicons glyphicons-circle-remove" onclick="$(this).closest('li').remove();"></span>
		</li>
	{/foreach}
	</ul>
	</div>
</blockquote>

<script type="text/javascript">
$("#container_{$random}").find('button.chooser').click(function(e) {
	var $this = $(this);
	
	var $popup = genericAjaxPopup("chooser{$random}",'c=internal&a=chooserOpen&context={$context}',null,true,'750');
	$popup.one('popup_close',function(event) {
		event.stopPropagation();
		var $container = $('#container_{$random}');
	});
	$popup.one('chooser_save',function(event) {
		event.stopPropagation();
		
		var $container = $("#container_{$random}");
		var $chooser = $container.find('button.chooser');
		var $ul = $container.find('ul.chooser-container');
		
		for(i in event.labels) {
			// Look for dupes
			if(0 == $ul.find('input:hidden[value="' + event.values[i] + '"]').length) {
				var $li = $('<li/>').append($('<b/>').text(event.labels[i]));
				$li.append($('<input type="hidden" name="context_id[]">').attr('value',event.values[i]));
				$li.append($('<span class="glyphicons glyphicons-circle-remove" onclick="$(this).closest(\'li\').remove();"></span>'));
				
				$ul.append($li);
			}
		}
	});
});
</script>