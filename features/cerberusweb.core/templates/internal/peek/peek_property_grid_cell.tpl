<label>{$labels.$k}</label>
{if $types.$k == Model_CustomField::TYPE_SINGLE_LINE}
	{$dict->$k}
{elseif $types.$k == Model_CustomField::TYPE_MULTI_LINE}
	{$dict->$k}
{elseif $types.$k == Model_CustomField::TYPE_URL}
	{$url = $dict->$k|replace:'http://':''|replace:'https://':''|replace:'www.':''|trim:'/'}
	<a href="{$dict->$k}" target="_blank">{$url|truncate:45}</a>
{elseif $types.$k == Model_CustomField::TYPE_CHECKBOX}
	{if $dict->$k}<span class="glyphicons glyphicons-check"></span>{else}<span class="glyphicons glyphicons-unchecked"></span>{/if}
{elseif $types.$k == Model_CustomField::TYPE_DATE}
	<abbr title="{$dict->$k|devblocks_date}">{$dict->$k|devblocks_prettytime}</abbr>
{elseif $types.$k == Model_CustomField::TYPE_NUMBER}
	{$dict->$k|number_format}
{elseif $types.$k == Model_CustomField::TYPE_DROPDOWN}
	{$dict->$k}
{elseif $types.$k == 'context_url'}
	{if substr($k,-6) == '_label'}
		{$k_prefix = substr($k,0,strlen($k)-6)}
		{$k_context = $k_prefix|cat:"_context"}
		{$k_id = $k_prefix|cat:"id"}
		{$k_label = $k_prefix|cat:"_label"}
		{if $dict->$k_context && $dict->$k_id}
			<ul class="bubbles">
				<li class="bubble-gray">
					{$k_alias = ''}
					{$k_updated = 0}
					{if $dict->$k_context == "{CerberusContexts::CONTEXT_ADDRESS}"}
						{$k_alias = 'address'}
						{$k_updated = $k_prefix|cat:"updated"}
					{elseif $dict->$k_context == "{CerberusContexts::CONTEXT_CONTACT}"}
						{$k_alias = 'contact'}
						{$k_updated = $k_prefix|cat:"updated_at"}
					{elseif $dict->$k_context == "{CerberusContexts::CONTEXT_GROUP}"}
						{$k_alias = 'group'}
						{$k_updated = $k_prefix|cat:"updated"}
					{elseif $dict->$k_context == "{CerberusContexts::CONTEXT_ORG}"}
						{$k_alias = 'org'}
						{$k_updated = $k_prefix|cat:"updated"}
					{elseif $dict->$k_context == "{CerberusContexts::CONTEXT_VIRTUAL_ATTENDANT}"}
						{$k_alias = 'va'}
						{$k_updated = $k_prefix|cat:"updated_at"}
					{elseif $dict->$k_context == "{CerberusContexts::CONTEXT_WORKER}"}
						{$k_alias = 'worker'}
						{$k_updated = $k_prefix|cat:"updated"}
					{/if}
					{if $k_alias && $k_updated}
						<img src="{devblocks_url}c=avatars&context={$k_alias}&context_id={$dict->$k_id}{/devblocks_url}?v={$dict->$k_updated}" style="height:16px;width:16px;border-radius:16px;vertical-align:middle;">
					{/if}
					<a href="javascript:;" class="cerb-peek-trigger no-underline" data-context="{$dict->$k_context}" data-context-id="{$dict->$k_id}">{$dict->$k_label}</a>
				</li>
			</ul>
		{/if}
	{/if}
{elseif $types.$k == 'percent'}
	{$percent = $dict->$k * 100}
	{$percent|number_format:2}%
{elseif $types.$k == 'phone'}
	<a href="tel:{$dict->$k}">{$dict->$k}</a>
{elseif $types.$k == 'time_secs'}
	{$dict->$k|devblocks_prettysecs:2}
{else}
	{$dict->$k} ({$types.$k})
{/if}
{*<i>{$k}</i>*}
