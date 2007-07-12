<div class="block">
<table cellpadding="2" cellspacing="0" border="0">
	<tr>
		<td><h2>Outgoing Mail</h2></td>
	</tr>
	<tr>
		<td>
			<form action="{devblocks_url}{/devblocks_url}" method="post">
			<input type="hidden" name="c" value="config">
			<input type="hidden" name="a" value="saveOutgoingMailSettings">

			<b>SMTP Server:</b><br>
			<input type="text" name="smtp_host" value="{$settings->get('smtp_host')}" size="45"><br>
			<br>

			<b>SMTP Server Requires Login:</b> (optional)<br>
			<label><input type="checkbox" name="smtp_auth_enabled" value="1" onclick="toggleDiv('configGeneralSmtpAuth',(this.checked?'block':'none'));" {if $settings->get('smtp_auth_enabled')}checked{/if}> Enabled</label><br>
			<br>
			
			<div id="configGeneralSmtpAuth" style="margin-left:15px;display:{if $settings->get('smtp_auth_enabled')}block{else}none{/if};">
			<b>SMTP Auth Username:</b><br>
			<input type="text" name="smtp_auth_user" value="{$settings->get('smtp_auth_user')}" size="45"><br>
			<br>
			
			<b>SMTP Auth Password:</b><br>
			<input type="text" name="smtp_auth_pass" value="{$settings->get('smtp_auth_pass')}" size="45"><br>
			<br>
			</div>
			
			<b>By default, reply to mail as:</b> (E-mail Address)<br>
			<input type="text" name="sender_address" value="{$settings->get('default_reply_from')}" size="45"> (e.g., support@yourcompany.com)<br>
			<br>
			
			<b>By default, reply to mail as:</b> (Personal Name)<br>
			<input type="text" name="sender_personal" value="{$settings->get('default_reply_personal')}" size="45"> (e.g., Acme Widgets)<br>
			<br>
			
			<b>Default E-mail Signature:</b><br>
			<textarea name="default_signature" rows="4" cols="76">{$settings->get('default_signature')|escape:"html"}</textarea><br>
				E-mail Tokens: 
				<select name="" onchange="this.form.default_signature.value += this.options[this.selectedIndex].value;scrollElementToBottom(this.form.default_signature);this.selectedIndex=0;this.form.default_signature.focus();">
					<option value="">-- choose --</option>
					<optgroup label="Worker">
						<option value="#first_name#">#first_name#</option>
						<option value="#last_name#">#last_name#</option>
						<option value="#title#">#title#</option>
					</optgroup>
				</select>
			<br> 
			<br>
			
			<button type="submit"><img src="{devblocks_url}c=resource&p=cerberusweb.core&f=images/check.gif{/devblocks_url}" align="top"> {$translate->_('common.save_changes')|capitalize}</button>
			</form>
		</td>
	</tr>
</table>
</div>