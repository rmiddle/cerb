{include file="$tpl_path/portal/kb/header.tpl.php"}

<div style="margin:10px;">
<form action="{devblocks_url}c=search{/devblocks_url}" method="post">
<input type="hidden" name="a" value="doSearch">
<table style="border: 1px solid rgb(0, 128, 255); width: 100%; padding: 5px; background-color: rgb(237, 241, 255);" border="0" cellpadding="0" cellspacing="0">
  <tbody>
  <tr>
  	<td colspan="2"><h2 style="margin:0px;">Search</h2></td>
  </tr>
  <tr>
  	<td width="0%" nowrap="nowrap"><b>Search for:&nbsp; </td>
  	<td width="100%">
  		<input type="text" name="query" value="" size="35" style="width:98%;">
  	</td>
  </tr>
  <tr>
  	<td width="0%" nowrap="nowrap"><b>Match:&nbsp; </td>
  	<td width="100%">
  		<label><input type="radio" name="match" value=""> any words</label>
  		<label><input type="radio" name="match" value=""> all words</label>
  		<label><input type="radio" name="match" value="" checked> exact phrase</label>
  	</td>
  </tr>
  <tr>
  	<td colspan="2" align="right"><button type="submit"><img src="{devblocks_url}c=resource&p=usermeet.core&f=images/book_blue_view.gif{/devblocks_url}" alt="Search" align="top"> Search</button></td>
  </tr>
  </tbody>
</table>
</form>

<br>

<table style="border-top: 1px solid rgb(0, 128, 255); border-left: 1px solid rgb(0, 128, 255); text-align: left; width: 100%;" border="0" cellpadding="3" cellspacing="0">
  <tbody>
    <tr>
      <td style="background-color: rgb(237, 241, 255);"><span style="font-weight: bold;">Search Results</span></td>
    </tr>
    <tr>
      <td>
      <ul>
        <li><a href="{devblocks_url}c=article&id=1000{/devblocks_url}">Installing with cPanel</a><br>
          <span style="font-size: 90%;">
	          Cerberus Helpdesk lets you blah to XML documents and then micro ferment them with a special sauce that ends up tasting like something missing from your wallet.<br>
	          <span style="color: rgb(120, 120, 120);">{devblocks_url full=true}c=article&id=1000{/devblocks_url}</span><br>
	          <br>
          </span>
        </li>
        <li><a href="{devblocks_url}c=article&id=2000{/devblocks_url}">Installing the C Parser in Linux</a><br>
          <span style="font-size: 90%;">
	          Sauce that ends up tasting like something missing from your wallet Cerberus Helpdesk lets you blah to XML documents and then micro ferment them with a special.<br>
	          <span style="color: rgb(120, 120, 120);">{devblocks_url full=true}c=article&id=2000{/devblocks_url}</span><br>
	          <br>
          </span>
        </li>
      </ul>
      </td>
    </tr>
  </tbody>
</table>
</div>

{include file="$tpl_path/portal/kb/footer.tpl.php"}