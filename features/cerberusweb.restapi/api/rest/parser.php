<?php
class ChRest_Parser extends Extension_RestController { //implements IExtensionRestController
	function getAction($stack) {
		$this->error(self::ERRNO_NOT_IMPLEMENTED);
	}
	
	function putAction($stack) {
		$this->error(self::ERRNO_NOT_IMPLEMENTED);
	}
	
	function postAction($stack) {
		@$action = array_shift($stack);
		
		switch($action) {
			case 'parse':
				$this->postParse();
				break;
		}
		
		$this->error(self::ERRNO_NOT_IMPLEMENTED);
	}
	
	function deleteAction($stack) {
		$this->error(self::ERRNO_NOT_IMPLEMENTED);
	}
	
	private function postParse() {
		$worker = CerberusApplication::getActiveWorker();
		
		if(!$worker->hasPriv('acl.core.mail.send'))
			$this->error(self::ERRNO_ACL);
		
		@$content = DevblocksPlatform::importGPC($_POST['message'],'string','');
		
		if(empty($content))
			$this->error(self::ERRNO_CUSTOM, 'The MIME content of your message cannot be blank.');
		
		if(null == ($parser_msg = CerberusParser::parseMimeString($content))) {
			@unlink($file);
			$this->error(self::ERRNO_CUSTOM, "Your message mime could not be parsed (it's probably malformed).");
		}
		
		if(null == ($ticket_id = CerberusParser::parseMessage($parser_msg))) {
			@unlink($file);
			$this->error(self::ERRNO_CUSTOM, "Your message content could not be parsed (it's probably malformed).");
		}
			
		if(null == ($ticket = DAO_Ticket::get($ticket_id))) {
			@unlink($file);
			$this->error(self::ERRNO_CUSTOM, "Could not return a ticket object.");
		}

		$container = array(
			'id' => $ticket->id,
			'mask' => $ticket->mask,
			'last_message_id' => $ticket->last_message_id,
		);
			
		@unlink($file);
		
		$this->success($container);
	}
};