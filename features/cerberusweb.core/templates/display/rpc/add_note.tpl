<form id="reply{$message->id}_form" action="{devblocks_url}{/devblocks_url}" method="POST" enctype="multipart/form-data">
<input type="hidden" name="c" value="display">
<input type="hidden" name="a" value="doAddNote">
<input type="hidden" name="id" value="{$message->id}">
<input type="hidden" name="ticket_id" value="{$message->ticket_id}">
<input type="hidden" name="_csrf_token" value="{$session.csrf_token}">

<div class="block" style="width:98%;margin:10px;">
<table cellpadding="2" cellspacing="0" border="0" width="100%">
	<tr>
		<td><h2 style="color:rgb(50,50,50);">Add Sticky Note</h2></td>
	</tr>
	<tr>
		<td><b>Author:</b> {$worker->getName()}</td>
	</tr>
	<tr>
		<td>
			<textarea name="content" rows="8" cols="80" id="note_content" class="reply" style="width:98%;border:1px solid rgb(180,180,180);padding:5px;" placeholder="{'comment.notify.at_mention'|devblocks_translate}"></textarea>
			<button type="button" onclick="ajax.chooserSnippet('snippets',$('#note_content'), { '{CerberusContexts::CONTEXT_TICKET}':'{$message->ticket_id}', '{CerberusContexts::CONTEXT_MESSAGE}':'{$message->id}', '{CerberusContexts::CONTEXT_WORKER}':'{$active_worker->id}' });">{'common.snippets'|devblocks_translate|capitalize}</button>
		</td>
	</tr>
	<tr>
		<td nowrap="nowrap" valign="top">
			<button type="button" onclick="genericAjaxPost('reply{$message->id}_form','{$message->id}notes','c=display&a=doAddNote');$('#reply{$message->id}').html('');"><span class="glyphicons glyphicons-circle-ok" style="color:rgb(0,180,0);"></span> Add Note</button>
			<button type="button" onclick="$('#reply{$message->id}').html('');"><span class="glyphicons glyphicons-circle-remove" style="color:rgb(200,0,0);"></span> Cancel</button>
		</td>
	</tr>
</table>
</div>
</form>

<script type="text/javascript">
$(function() {
	var $frm = $('#reply{$message->id}_form');
	var $textarea = $frm.find('textarea[name=content]');
	
	// @mentions
	
	var atwho_workers = {CerberusApplication::getAtMentionsWorkerDictionaryJson() nofilter};

	$textarea.atwho({
		at: '@',
		{literal}displayTpl: '<li>${name} <small style="margin-left:10px;">${title}</small> <small style="margin-left:10px;">@${at_mention}</small></li>',{/literal}
		{literal}insertTpl: '@${at_mention}',{/literal}
		data: atwho_workers,
		searchKey: '_index',
		limit: 10
	});
});
</script>