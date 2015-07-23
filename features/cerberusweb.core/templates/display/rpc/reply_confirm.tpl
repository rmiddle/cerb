<div class="block" id="replyConfirm{$id}" style="margin:10px;padding:10px;border-radius:5px;">

<form action="#">

<h1 style="color:rgb(50,50,50);">There is new activity on this ticket:</h1>

<table style="margin-left:20px;">
	{foreach from=$recent_activity item=activity}
	<tr>
		<td align="right"><div class="badge badge-lightgray" style="margin-right:5px;">{$activity.timestamp|devblocks_prettytime}</div></td>
		<td>{$activity.message}</td>
	</tr>
	{/foreach}
</table>

<br>

<button type="button" class="submit"><span class="glyphicons glyphicons-circle-ok" style="color:rgb(0,180,0);"></span> Continue replying</button>
<button type="button" class="cancel"><span class="glyphicons glyphicons-circle-remove" style="color:rgb(200,0,0);"></span> Cancel</button>

</form>
</div>

<script type="text/javascript">
$div = $('#replyConfirm{$id}');

$div.find('button.submit')
	.click(function(e) {
		displayReply('{$id}',0,0,{$reply_mode},1);
	})
	.focus()
	;

$div.find('button.cancel').click(function(e) {
	$('#replyConfirm{$id}').fadeOut();
});

</script>