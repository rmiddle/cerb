{$headers = $message->getHeaders()}
<div class="block" style="margin-bottom:10px;">
<table style="text-align: left; width: 98%;table-layout: fixed;" border="0" cellpadding="2" cellspacing="0">
  <tbody>
	<tr>
	  <td>
		{$sender_id = $message->address_id}
		{if isset($message_senders.$sender_id)}
			{$sender = $message_senders.$sender_id}
			{$sender_org_id = $sender->contact_org_id}
			{$sender_org = $message_sender_orgs.$sender_org_id}
			{$sender_contact = $sender->getContact()}
			{$sender_worker = $message->getWorker()}
			{$is_outgoing = $message->is_outgoing}
			{$is_not_sent = $message->is_not_sent}
			
			{if $expanded}
			{$attachments = DAO_Attachment::getByContextIds(CerberusContexts::CONTEXT_MESSAGE, $message->id)}
			{else}
			{$attachments = []}
			{/if}
			
			<div class="toolbar-minmax" style="float:right;{if !$expanded}display:none;{/if}">
				{if $expanded && $attachments}
				<button type="button" class="cerb-search-trigger" data-context="{CerberusContexts::CONTEXT_ATTACHMENT}" data-query="on.message:(id:{$message->id})"><span class="glyphicons glyphicons-paperclip"></span></button>
				{/if}
				
				{$permalink_url = "{devblocks_url full=true}c=profiles&type=ticket&mask={$ticket->mask}&jump=message&jump_id={$message->id}{/devblocks_url}"}
				<button type="button" onclick="genericAjaxPopup('permalink', 'c=internal&a=showPermalinkPopup&url={$permalink_url|escape:'url'}');" title="{'common.permalink'|devblocks_translate|lower}"><span class="glyphicons glyphicons-link"></span></button>
				
				{if !$expanded}
					<button id="btnMsgMax{$message->id}" type="button" onclick="genericAjaxGet('{$message->id}t','c=display&a=getMessage&id={$message->id}');" title="{'common.maximize'|devblocks_translate|lower}"><span class="glyphicons glyphicons-resize-full"></span></button>
				{else}
					<button id="btnMsgMin{$message->id}" type="button" onclick="genericAjaxGet('{$message->id}t','c=display&a=getMessage&id={$message->id}&hide=1');" title="{'common.minimize'|devblocks_translate|lower}"><span class="glyphicons glyphicons-resize-small"></span></button>
				{/if}
			</div>
		
			<span class="tag" style="color:white;margin-right:5px;{if !$is_outgoing}background-color:rgb(185,50,40);{else}background-color:rgb(100,140,25);{/if}">{if $is_outgoing}{if $is_not_sent}{'mail.saved'|devblocks_translate|lower}{else}{'mail.sent'|devblocks_translate|lower}{/if}{else}{'mail.received'|devblocks_translate|lower}{/if}</span>
			
			{if $sender_worker}
				<a href="javascript:;" class="cerb-peek-trigger" style="font-weight:bold;{if $expanded}font-size:1.3em;{/if}" data-context="{CerberusContexts::CONTEXT_WORKER}" data-context-id="{$sender_worker->id}">{if 0 != strlen($sender_worker->getName())}{$sender_worker->getName()}{else}&lt;{$sender_worker->getEmailString()}&gt;{/if}</a>
			{else}
				{if $sender_contact}
					{$sender_org = $sender_contact->getOrg()}
					<a href="javascript:;" class="cerb-peek-trigger" style="font-weight:bold;{if $expanded}font-size:1.3em;{/if}" data-context="{CerberusContexts::CONTEXT_CONTACT}" data-context-id="{$sender_contact->id}">{$sender_contact->getName()}</a>
					&nbsp;
					{if $sender_contact->title}
						{$sender_contact->title}
					{/if}
					{if $sender_contact->title && $sender_org} at {/if}
					{if $sender_org}
						<a href="javascript:;" class="cerb-peek-trigger no-underline" data-context="{CerberusContexts::CONTEXT_ORG}" data-context-id="{$sender_org->id}"><b>{$sender_org->name}</b></a>
					{/if}
				{else}
					{$sender_org = $sender->getOrg()}
					<a href="javascript:;" class="cerb-peek-trigger" style="font-weight:bold;{if $expanded}font-size:1.3em;{/if}" data-context="{CerberusContexts::CONTEXT_ADDRESS}" data-context-id="{$sender_id}">&lt;{$sender->email}&gt;</a>
					&nbsp;
					{if $sender_org}
						<a href="javascript:;" class="cerb-peek-trigger no-underline" data-context="{CerberusContexts::CONTEXT_ORG}" data-context-id="{$sender_org->id}"><b>{$sender_org->name}</b></a>
					{/if}
				{/if}
			{/if}
			
			<div style="float:left;margin:0px 5px 5px 0px;">
				{if $sender_worker}
					<img src="{devblocks_url}c=avatars&context=worker&context_id={$sender_worker->id}{/devblocks_url}?v={$sender_worker->updated}" style="height:64px;width:64px;border-radius:64px;">
				{else}
					{if $sender_contact}
					<img src="{devblocks_url}c=avatars&context=contact&context_id={$sender_contact->id}{/devblocks_url}?v={$sender_contact->updated_at}" style="height:64px;width:64px;border-radius:64px;">
					{else}
					<img src="{devblocks_url}c=avatars&context=address&context_id={$sender->id}{/devblocks_url}?v={$sender->updated}" style="height:64px;width:64px;border-radius:64px;">
					{/if}
				{/if}
			</div>
			
			<br>
		{/if}
	  
	  <div id="{$message->id}sh" style="display:block;margin-top:2px;">
	  {if isset($headers.from)}<b>{'message.header.from'|devblocks_translate|capitalize}:</b> {$headers.from|escape|nl2br nofilter}<br>{/if}
	  {if isset($headers.to)}<b>{'message.header.to'|devblocks_translate|capitalize}:</b> {$headers.to|escape|nl2br nofilter}<br>{/if}
	  {if isset($headers.cc)}<b>{'message.header.cc'|devblocks_translate|capitalize}:</b> {$headers.cc|escape|nl2br nofilter}<br>{/if}
	  {if isset($headers.bcc)}<b>{'message.header.bcc'|devblocks_translate|capitalize}:</b> {$headers.bcc|escape|nl2br nofilter}<br>{/if}
	  {if isset($headers.subject)}<b>{'message.header.subject'|devblocks_translate|capitalize}:</b> {$headers.subject}<br>{/if}
  	<b>{'message.header.date'|devblocks_translate|capitalize}:</b> {$message->created_date|devblocks_date} (<abbr title="{$headers.date}">{$message->created_date|devblocks_prettytime}</abbr>)

		{if !empty($message->response_time)}
			<span style="margin-left:10px;color:rgb(100,140,25);">Replied in {$message->response_time|devblocks_prettysecs:2}</span>
		{/if}
  	<br>
	  </div>

	  {if $expanded}
	  <div style="margin:2px;margin-left:10px;" id="{$message->id}skip">
	  	 <button type="button" onclick="document.location='#{$message->id}act';">{'display.convo.skip_to_bottom'|devblocks_translate|lower}</button>
	  </div>
	  {/if}
	  
		{if $expanded}
		<div style="clear:both;display:block;padding-top:10px;">
				{if DAO_WorkerPref::get($active_worker->id, 'mail_disable_html_display', 0)}
					{$html_body = null}
				{else}
					{$html_body = $message->getContentAsHtml()}
				{/if}
		
	  		{if !empty($html_body)}
		  		<div class="emailBodyHtml">
			  		{$html_body nofilter}
		  		</div>
	  		{else}
			  	<pre class="emailbody">{$message->getContent()|trim|escape|devblocks_hyperlinks|devblocks_hideemailquotes nofilter}</pre>
		  	{/if}
		  	<br>
		  	
			{if $active_worker->hasPriv('core.display.actions.attachments.download')}
				{include file="devblocks:cerberusweb.core::internal/attachments/list.tpl" context="{CerberusContexts::CONTEXT_MESSAGE}" context_id=$message->id attachments=$attachments}
			{/if}
		  	
		  	<table width="100%" cellpadding="0" cellspacing="0" border="0">
		  		<tr>
		  			<td align="left" id="{$message->id}act">
						{* If not requester *}
						{if !$message->is_outgoing && !isset($requesters.{$sender_id})}
						<button type="button" onclick="$(this).remove(); genericAjaxGet('','c=display&a=requesterAdd&ticket_id={$ticket->id}&email='+encodeURIComponent('{$sender->email}'),function(o) { genericAjaxGet('displayTicketRequesterBubbles','c=display&a=requestersRefresh&ticket_id={$ticket->id}'); } );"><span class="glyphicons glyphicons-circle-plus" style="color:rgb(0,180,0);"></span> {'display.ui.add_to_recipients'|devblocks_translate}</button>
						{/if}
						
					  	{if $active_worker->hasPriv('core.display.actions.reply')}
					  		<button type="button" class="reply split-left" onclick="displayReply('{$message->id}',0,0,{$mail_reply_button});" title="{if 2 == $mail_reply_button}{'display.reply.only_these_recipients'|devblocks_translate}{elseif 1 == $mail_reply_button}{'display.reply.no_quote'|devblocks_translate}{else}{'display.reply.quote'|devblocks_translate}{/if}"><span class="glyphicons glyphicons-share" style="color:rgb(0,180,0);"></span> {'display.ui.reply'|devblocks_translate|capitalize}</button><!--
					  		--><button type="button" class="split-right" onclick="$ul=$(this).next('ul');$ul.toggle();if($ul.is(':hidden')) { $ul.blur(); } else { $ul.find('a:first').focus(); }"><span class="glyphicons glyphicons-chevron-down" style="font-size:12px;color:white;"></span></button>
					  		<ul class="cerb-popupmenu cerb-float" style="margin-top:-5px;">
					  			<li><a href="javascript:;" onclick="displayReply('{$message->id}',0,0,0);">{'display.reply.quote'|devblocks_translate}</a></li>
					  			<li><a href="javascript:;" onclick="displayReply('{$message->id}',0,0,2);">{'display.reply.only_these_recipients'|devblocks_translate}</a></li>
					  			<li><a href="javascript:;" onclick="displayReply('{$message->id}',0,0,1);">{'display.reply.no_quote'|devblocks_translate}</a></li>
					  			{if $active_worker->hasPriv('core.display.actions.forward')}<li><a href="javascript:;" onclick="displayReply('{$message->id}',1);">{'display.ui.forward'|devblocks_translate|capitalize}</a></li>{/if}
					  			<li><a href="javascript:;" class="relay" data-message-id="{$message->id}">Relay to worker email</a></li>
					  		</ul>
					  	{/if}
					  	
					  	{if $active_worker->hasPriv('core.display.actions.note')}
					  		<button type="button" class="cerb-sticky-trigger" data-context="{CerberusContexts::CONTEXT_COMMENT}" data-context-id="0" data-edit="context:{CerberusContexts::CONTEXT_MESSAGE} context.id:{$message->id}"><span class="glyphicons glyphicons-edit"></span> {'display.ui.sticky_note'|devblocks_translate|capitalize}</button>
					  	{/if}
					  	
					  	{if $active_worker->hasPriv('core.display.actions.reply')}
					  	<button type="button" class="edit" data-context="{CerberusContexts::CONTEXT_MESSAGE}" data-context-id="{$message->id}" data-edit="true"><span class="glyphicons glyphicons-cogwheel"></span></button>
					  	{/if}
					  	
				  		<button type="button" onclick="$('#{$message->id}options').toggle();"><span class="glyphicons glyphicons-more"></span></button>
		  			</td>
		  		</tr>
		  	</table>
		  	
		  	<form id="{$message->id}options" style="padding-top:10px;display:none;" method="post" action="{devblocks_url}{/devblocks_url}">
		  		<input type="hidden" name="c" value="display">
		  		<input type="hidden" name="a" value="">
		  		<input type="hidden" name="id" value="{$message->id}">
		  		<input type="hidden" name="_csrf_token" value="{$session.csrf_token}">
		  		
		  		<button type="button" onclick="document.frmPrint.action='{devblocks_url}c=print&a=message&id={$message->id}{/devblocks_url}';document.frmPrint.submit();"><span class="glyphicons glyphicons-print"></span> {'common.print'|devblocks_translate|capitalize}</button>
		  		
		  		{if $ticket->first_message_id != $message->id && $active_worker->hasPriv('core.display.actions.split')} {* Don't allow splitting of a single message *}
		  		<button type="button" onclick="$frm=$(this).closest('form');$frm.find('input:hidden[name=a]').val('doSplitMessage');$frm.submit();" title="Split message into new ticket"><span class="glyphicons glyphicons-duplicate"></span> {'display.button.split_ticket'|devblocks_translate|capitalize}</button>
		  		{/if}
		  		
		  		<button type="button" onclick="genericAjaxPopup('message_headers','c=profiles&a=handleSectionAction&section=ticket&action=showMessageFullHeadersPopup&id={$message->id}');"><span class="glyphicons glyphicons-envelope"></span> {'display.convo.full_headers'|devblocks_translate|capitalize}</button>
		  		
					{* Plugin Toolbar *}
					{if !empty($message_toolbaritems)}
						{foreach from=$message_toolbaritems item=renderer}
							{if !empty($renderer)}{$renderer->render($message)}{/if}
						{/foreach}
					{/if}
		  	</form>
		  	
			{$values = array_shift(DAO_CustomFieldValue::getValuesByContextIds(CerberusContexts::CONTEXT_MESSAGE, $message->id))|default:[]}
			{$message_custom_fieldsets = Page_Profiles::getProfilePropertiesCustomFieldsets(CerberusContexts::CONTEXT_MESSAGE, $message->id, $values)}
			<div style="margin-top:10px;">
				{include file="devblocks:cerberusweb.core::internal/custom_fieldsets/profile_fieldsets.tpl" properties=$message_custom_fieldsets}
			</div>
		</div> <!-- end visible -->
	  	{/if}
	  </td>
	</tr>
  </tbody>
</table>
</div>
<div id="{$message->id}b"></div>
<div id="{$message->id}notes">
	{include file="devblocks:cerberusweb.core::display/modules/conversation/notes.tpl"}
</div>
<div id="reply{$message->id}"></div>

<script type="text/javascript">
$(function() {
	var $msg = $('#{$message->id}t').unbind();
	
	{if !$expanded}
	$msg.hover(
		function() {
			$msg.find('div.toolbar-minmax').show();
		},
		function() {
			$msg.find('div.toolbar-minmax').hide();
		}
	);
	{/if}
	
	$msg.find('.cerb-search-trigger')
		.cerbSearchTrigger()
		;
	
	if($('#{$message->id}act').visible()) {
		$('#{$message->id}skip').hide();
	}
});
</script>

{if $active_worker->hasPriv('core.display.actions.reply')}
<script type="text/javascript">
$(function() {
	var $msg = $('#{$message->id}t');
	var $actions = $('#{$message->id}act');
	var $notes = $('#{$message->id}notes');
	
	$msg.find('.cerb-peek-trigger')
		.cerbPeekTrigger()
		;
	
	$msg.find('.cerb-sticky-trigger')
		.cerbPeekTrigger()
			.on('cerb-peek-saved', function(e) {
				e.stopPropagation();
				
				if(e.id && e.comment_html) {
					var $new_note = $('<div id="comment' + e.id + '"/>').hide();
					$new_note.html(e.comment_html).prependTo($notes).fadeIn();
				}
			})
			;
	
	// Edit
	
	$msg.find('button.edit')
		.cerbPeekTrigger()
		.on('cerb-peek-opened', function(e) {
		})
		.on('cerb-peek-saved', function(e) {
			e.stopPropagation();
			genericAjaxGet('{$message->id}t','c=display&a=getMessage&id={$message->id}&hide=0');
		})
		.on('cerb-peek-deleted', function(e) {
			e.stopPropagation();
			$('#{$message->id}t').remove();
			
		})
		.on('cerb-peek-closed', function(e) {
		})
		;
	
	$actions
		.find('ul.cerb-popupmenu')
		.hover(
			function(e) { }, 
			function(e) { $(this).hide(); }
		)
		.find('> li')
		.click(function(e) {
			$(this).closest('ul.cerb-popupmenu').hide();
	
			e.stopPropagation();
			if(!$(e.target).is('li'))
			return;
	
			$(this).find('a').trigger('click');
		})
	;
	
	$actions
		.find('li a.relay')
		.click(function() {
			genericAjaxPopup('relay', 'c=display&a=showRelayMessagePopup&id={$message->id}', null, false, '650');
		})
		;
	
	});
</script>
{/if}