{if $active_worker->hasPriv('feedback.actions.create')}
<button type="button" onclick="genericAjaxPopup('peek','c=internal&a=showPeekPopup&context={CerberusContexts::CONTEXT_FEEDBACK}&context_id=0&source_ext_id=feedback.source.ticket&source_id={$message->ticket_id}&msg_id={$message->id}&quote='+encodeURIComponent(Devblocks.getSelectedText())+'&url='+escape(window.location),null,false,'550');"><img src="{devblocks_url}c=resource&p=cerberusweb.feedback&f=images/question_and_answer.png{/devblocks_url}" align="top"> {'feedback.button.capture'|devblocks_translate|capitalize}</button>
{/if}