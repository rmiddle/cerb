<table cellpadding="0" cellspacing="0" border="0" class="sidebar" id="kb_sidebar">
	<tr>
		<th>{'common.search'|devblocks_translate|capitalize}</th>
	</tr>
	<tr>
		<td>
			<form action="{devblocks_url}c=kb&a=search{/devblocks_url}" method="POST" style="padding-bottom:5px;">
				<input type="text" name="q" value="{$q}" style="width:100%;"><br>
				<input type="hidden" name="_csrf_token" value="{$session->csrf_token}">
				<button type="submit">{'common.search'|devblocks_translate|lower}</button>
			</form>
		</td>
	</tr>
</table>

<div style="padding:2px;"><span class="glyphicons glyphicons-wifi-alt" style="color:rgb(249,154,56);"></span> <a href="{devblocks_url}c=rss&m=kb&a=most_popular{/devblocks_url}">Most Popular</a></div>
<div style="padding:2px;"><span class="glyphicons glyphicons-wifi-alt" style="color:rgb(249,154,56);"></span> <a href="{devblocks_url}c=rss&m=kb&a=new_articles{/devblocks_url}">Recently Added</a></div>
<div style="padding:2px;"><span class="glyphicons glyphicons-wifi-alt" style="color:rgb(249,154,56);"></span> <a href="{devblocks_url}c=rss&m=kb&a=recent_changes{/devblocks_url}">Recently Updated</a></div>
