{if !empty($last_error)}
	<div class="error" style="width:550px;">
		{$last_error}
	</div>
{/if}

<form id="openTicketForm" action="{devblocks_url}c=contact{/devblocks_url}" method="post" enctype="multipart/form-data">
<input type="hidden" name="a" value="doContactSend">
<input type="hidden" name="_csrf_token" value="{$session->csrf_token}">
<table border="0" cellpadding="0" cellspacing="0" width="99%">
  <tbody>
	<tr>
	<td colspan="2">
		<fieldset>
			<legend>{'portal.common.open_ticket'|devblocks_translate}:</legend>
			
			<b>{'portal.public.what_email_reply'|devblocks_translate}</b><br>
			<input type="hidden" name="nature" value="{$sNature}">
			
			{if empty($last_from) && !empty($active_contact)}
				{$primary_email = $active_contact->getEmail()}
				{$last_from = $primary_email->email}
			{/if}
			
			<input type="text" name="from" value="{if !empty($last_from)}{$last_from}{/if}" autocomplete="off" style="width:100%;" class="required email"><br>
			<br>
			
			{if $allow_cc}
			<b>{'message.header.cc'|devblocks_translate|capitalize}:</b> ({'common.help.comma_separated'|devblocks_translate|lower})<br>
			<input type="text" name="cc" value="{if !empty($last_cc)}{$last_cc}{/if}" autocomplete="off" style="width:100%;"><br>
			<br>
			{/if}
			
			<b>{'ticket.subject'|devblocks_translate|capitalize}:</b><br>
			{if $allow_subjects}
			<input type="text" name="subject" value="{if !empty($last_subject)}{$last_subject}{/if}" placeholder="{$situation}" autocomplete="off" style="width:100%;" class="required"><br>
			{else}
			{$situation}<br>
			{/if}
			<br>
			
			<b>{'portal.public.open_ticket.message'|devblocks_translate}:</b><br>
			<textarea name="content" rows="15" cols="60" style="width:100%;" class="required">{$last_content}</textarea><br>
		</fieldset>

		{if !empty($situation_params.followups)}
		<fieldset>
			<legend>{'portal.public.open_ticket.additional_info'|devblocks_translate}</legend>
			
			{foreach from=$situation_params.followups key=question item=field_id name=situations}
				{math assign=idx equation="x-1" x=$smarty.foreach.situations.iteration}
	
				{if '*'==substr($question,0,1)}
					{assign var=required value=true}
				{else}
					{assign var=required value=false}
				{/if}
				
				<h2>{$question}</h2>
				<input type="hidden" name="followup_q[]" value="{$question}">
				{if !empty($field_id)}
					{assign var=field value=$ticket_fields.$field_id}
					<input type="hidden" name="field_ids[]" value="{$field_id}">
					
					{if $field->type=='S'}
						<input type="text" name="followup_a_{$idx}" value="{$last_followup_a.$idx}" autocomplete="off" style="width:100%;" class="{if $required}required{/if}">
					{elseif $field->type=='U'}
						<input type="text" name="followup_a_{$idx}" value="{$last_followup_a.$idx}" autocomplete="off" style="width:100%;" class="url {if $required}required{/if}">
					{elseif $field->type=='N'}
						<input type="text" name="followup_a_{$idx}" size="12" maxlength="20" value="{$last_followup_a.$idx}" autocomplete="off" class="number {if $required}required{/if}">
					{elseif $field->type=='T'}
						<textarea name="followup_a_{$idx}" rows="5" cols="60" style="width:100%;" class="{if $required}required{/if}">{$last_followup_a.$idx}</textarea>
					{elseif $field->type=='D'}
						<select name="followup_a_{$idx}" class="{if $required}required{/if}">
							<option value=""></option>
							{foreach from=$field->params.options item=opt}
							<option value="{$opt}" {if $last_followup_a.$idx==$opt}selected="selected"{/if}>{$opt}
							{/foreach}
						</select>
					{elseif $field->type=='W'}
						<select name="followup_a_{$idx}" class="{if $required}required{/if}">
							<option value=""></option>
							{foreach from=$workers item=worker key=worker_id}
							<option value="{$worker_id}" {if $last_followup_a.$idx==$worker_id}selected="selected"{/if}>{$worker->getName()}</option>
							{/foreach}
						</select>
					{elseif $field->type=='E'}
						<input type="text" name="followup_a_{$idx}" value="{$last_followup_a.$idx}" autocomplete="off" class="date {if $required}required{/if}">
					{elseif $field->type=='X'}
						{foreach from=$field->params.options item=opt}
						<label><input type="checkbox" name="followup_a_{$idx}[]" value="{$opt}"> {$opt}</label><br>
						{/foreach}
					{elseif $field->type=='C'}
						<label><input name="followup_a_{$idx}" type="checkbox" value="Yes" {if $last_followup_a.$idx}checked="checked"{/if}> {'common.yes'|devblocks_translate|capitalize}</label>
					{elseif $field->type=='F'}
						<input type="file" name="followup_a_{$idx}">
					{elseif $field->type=='I'}
						<input type="file" name="followup_a_{$idx}[]" multiple="multiple">
					{elseif $field->type=='L'}
						{* N/A *}
					{/if}
					
				{else}
					<input type="hidden" name="field_ids[]" value="0">
					<input type="text" name="followup_a_{$idx}" value="{$last_followup_a.$idx}" autocomplete="off" style="width:100%;" class="{if $required}required{/if}">
				{/if}
				<br>
				<br>
			{/foreach}
		</fieldset>
		{/if}

		{if 0==$attachments_mode || (1==$attachments_mode && !empty($active_contact))}
		<fieldset>
			<legend>Attachments:</legend>
			<input type="file" name="attachments[]" multiple="multiple"><br>
		</fieldset>
		{/if}
		
		{if $captcha_enabled}
		<fieldset>
			<legend>{'portal.public.captcha_instructions'|devblocks_translate}</legend>
			{'portal.sc.public.contact.text'|devblocks_translate} <input type="text" id="captcha" name="captcha" class="question" value="" size="10" autocomplete="off"><br>
			<div style="padding-top:10px;padding-left:10px;"><img src="{devblocks_url}c=captcha{/devblocks_url}?color=0,0,0&bgcolor=235,235,235"></div>
		</fieldset>
		{/if}
		
		<br>
		<b>{'portal.public.logged_ip'|devblocks_translate}</b> {$fingerprint.ip}<br>
		<br>
		
		<div class="buttons">
			<button type="submit"><span class="glyphicons glyphicons-circle-ok"></span> {'portal.public.send_message'|devblocks_translate}</button>
			<button type="button" onclick="document.location='{devblocks_url}{/devblocks_url}';"><span class="glyphicons glyphicons-circle-remove"></span> {'common.discard'|devblocks_translate|capitalize}</button>
		</div>
	</td>
	</tr>
	
  </tbody>
</table>
</form>