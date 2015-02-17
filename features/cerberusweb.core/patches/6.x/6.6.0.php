<?php
$db = DevblocksPlatform::getDatabaseService();
$logger = DevblocksPlatform::getConsoleLog();
$tables = $db->metaTables();

// ===========================================================================
// Convert `custom_field.options` to `params_json`

if(!isset($tables['custom_field'])) {
	$logger->error("The 'custom_field' table does not exist.");
	return FALSE;
}

list($columns, $indexes) = $db->metaTable('custom_field');

if(!isset($columns['params_json'])) {
	$db->ExecuteMaster("ALTER TABLE custom_field ADD COLUMN params_json TEXT AFTER pos");
	
	$results = $db->GetArrayMaster("SELECT id, options FROM custom_field WHERE options != ''");
	
	foreach($results as $result) {
		$params = array(
			'options' => DevblocksPlatform::parseCrlfString($result['options'])
		);
		
		// Migrate the `options` field on `custom_field` to `params_json`
		$db->ExecuteMaster(sprintf("UPDATE custom_field SET params_json = %s WHERE id = %d",
			$db->qstr(json_encode($params)),
			$result['id']
		));
	}
}

// Drop the `options` field on `custom_field`
if(isset($columns['options'])) {
	$db->ExecuteMaster("ALTER TABLE custom_field DROP COLUMN options");
}

// ===========================================================================
// Add `attachment.storage_sha1hash`

if(!isset($tables['attachment'])) {
	$logger->error("The 'attachment' table does not exist.");
	return FALSE;
}

list($columns, $indexes) = $db->metaTable('attachment');

if(!isset($columns['storage_sha1hash'])) {
	$db->ExecuteMaster("ALTER TABLE attachment ADD COLUMN storage_sha1hash VARCHAR(40) DEFAULT '', ADD INDEX storage_sha1hash (storage_sha1hash(4))");
}

// ===========================================================================
// Fix S3 namespace prefixes in storage keys

$db->ExecuteMaster("UPDATE attachment SET storage_key = REPLACE(storage_key, 'attachments/', '') WHERE storage_extension = 'devblocks.storage.engine.s3'");
$db->ExecuteMaster("UPDATE message SET storage_key = REPLACE(storage_key, 'message_content/', '') WHERE storage_extension = 'devblocks.storage.engine.s3'");

// ===========================================================================
// Clean up missing scheduled behaviors

$db->ExecuteMaster("DELETE context_scheduled_behavior FROM context_scheduled_behavior LEFT JOIN trigger_event ON (trigger_event.id=context_scheduled_behavior.behavior_id) WHERE trigger_event.id IS NULL");

// ===========================================================================
// mail_html_template

if(!isset($tables['mail_html_template'])) {
	$sql = sprintf("
		CREATE TABLE IF NOT EXISTS mail_html_template (
			id INT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(255) DEFAULT '',
			updated_at INT UNSIGNED NOT NULL DEFAULT 0,
			owner_context varchar(128) NOT NULL DEFAULT '',
			owner_context_id int(11) NOT NULL DEFAULT '0',
			content mediumtext,
			PRIMARY KEY (id),
			INDEX owner (owner_context, owner_context_id)
		) ENGINE=%s;
	", APP_DB_ENGINE);
	$db->ExecuteMaster($sql);

	$tables['mail_html_template'] = 'mail_html_template';

	// Insert a default HTML template
	$db->ExecuteMaster(sprintf("INSERT INTO mail_html_template (name, updated_at, owner_context, owner_context_id, content) VALUES (%s, %d, %s, %d, %s)",
		$db->qstr('Default'),
		time(),
		$db->qstr('cerberusweb.contexts.app'),
		0,
		$db->qstr("<div id=\"body\">\n{{message_body}}\n</div>\n\n<style type=\"text/css\">\n#body {\n  font-family: Arial, Verdana, sans-serif;\n  font-size: 10pt;\n}\n\na { \n  color: black;\n}\n\nblockquote {\n  color: rgb(0, 128, 255);\n  font-style: italic;\n  margin-left: 0px;\n  border-left: 1px solid rgb(0, 128, 255);\n  padding-left: 5px;\n}\n\nblockquote a {\n  color: rgb(0, 128, 255);\n}\n</style>")
	));
}

// ===========================================================================
// Add HTML template support to groups

if(!isset($tables['worker_group'])) {
	$logger->error("The 'worker_group' table does not exist.");
	return FALSE;
}

list($columns, $indexes) = $db->metaTable('worker_group');

if(!isset($columns['reply_html_template_id'])) {
	$db->ExecuteMaster("ALTER TABLE worker_group ADD COLUMN reply_html_template_id INT UNSIGNED NOT NULL DEFAULT 0");
}

// ===========================================================================
// Add HTML template support to buckets

if(!isset($tables['bucket'])) {
	$logger->error("The 'bucket' table does not exist.");
	return FALSE;
}

list($columns, $indexes) = $db->metaTable('bucket');

if(!isset($columns['reply_html_template_id'])) {
	$db->ExecuteMaster("ALTER TABLE bucket ADD COLUMN reply_html_template_id INT UNSIGNED NOT NULL DEFAULT 0");
}

// ===========================================================================
// Add HTML template support on reply-to addresses

if(!isset($tables['address_outgoing'])) {
	$logger->error("The 'address_outgoing' table does not exist.");
	return FALSE;
}

list($columns, $indexes) = $db->metaTable('address_outgoing');

if(!isset($columns['reply_html_template_id'])) {
	$db->ExecuteMaster("ALTER TABLE address_outgoing ADD COLUMN reply_html_template_id INT UNSIGNED NOT NULL DEFAULT 0");
	
	// Add the default HTML template to the default reply-to addy
	if(false != ($default_html_template_id = $db->GetOneMaster("SELECT id FROM mail_html_template WHERE name = 'Default'"))) {
		$db->ExecuteMaster(sprintf("UPDATE address_outgoing SET reply_html_template_id = %d WHERE is_default = 1", $default_html_template_id));
	}
}

// ===========================================================================
// Add an updated field to snippet records

if(!isset($tables['snippet'])) {
	$logger->error("The 'snippet' table does not exist.");
	return FALSE;
}

list($columns, $indexes) = $db->metaTable('snippet');

if(!isset($columns['updated_at'])) {
	$db->ExecuteMaster("ALTER TABLE snippet ADD COLUMN updated_at INT UNSIGNED NOT NULL DEFAULT 0, ADD INDEX updated_at (updated_at)");
	$db->ExecuteMaster("UPDATE snippet SET updated_at = UNIX_TIMESTAMP()");
}

// ===========================================================================
// Reset Search->Snippet worklists to fix an issue with old cached filters

$db->ExecuteMaster("DELETE FROM worker_view_model WHERE view_id = 'search_cerberusweb_contexts_snippet'");

// ===========================================================================
// Convert old built-in mail header conditions to the newer variation

// Ticket events
$db->ExecuteMaster("UPDATE decision_node SET params_json = REPLACE(params_json, '\"condition\":\"ticket_initial_message_header\"', '\"condition\":\"ticket_initial_message_headers\"') WHERE node_type = 'outcome' AND trigger_id in (SELECT id FROM trigger_event WHERE event_point IN ('event.comment.ticket.group','event.macro.ticket','event.ticket.viewed.worker','event.mail.assigned.group','event.mail.closed.group','event.mail.moved.group'))");
$db->ExecuteMaster("UPDATE decision_node SET params_json = REPLACE(params_json, '\"condition\":\"ticket_latest_message_header\"', '\"condition\":\"ticket_latest_message_headers\"') WHERE node_type = 'outcome' AND trigger_id in (SELECT id FROM trigger_event WHERE event_point IN ('event.comment.ticket.group','event.macro.ticket','event.ticket.viewed.worker','event.mail.assigned.group','event.mail.closed.group','event.mail.moved.group'))");

// Message events
$db->ExecuteMaster("UPDATE decision_node SET params_json = REPLACE(params_json, '\"condition\":\"header\"', '\"condition\":\"headers\"') WHERE node_type = 'outcome' AND trigger_id IN (SELECT id FROM trigger_event WHERE event_point IN ('event.mail.after.sent','event.mail.after.sent.group','event.mail.received.group','event.mail.reply.pre.ui.worker','event.mail.reply.during.ui.worker'))");

// ===========================================================================
// Finish up

return TRUE;
