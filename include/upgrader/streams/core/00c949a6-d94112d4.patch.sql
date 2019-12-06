/**
* @signature d94112d43ce34eb9981eee2fc642d523
* @version v1.11.0
* @title Adding PLCH notifications
*
* Adding plch_flags column to ost_help_topic for ticket closing notifications
* and future notification needs
*
*/

-- Add plch_flags column to help_topic
ALTER TABLE `%TABLE_PREFIX%help_topic`
    ADD `plch_flags` int(10) unsigned DEFAULT '0' AFTER `flags`;

-- Finished with patch
UPDATE `%TABLE_PREFIX%config`
   SET `value` = 'd94112d43ce34eb9981eee2fc642d523', `updated` = NOW()
   WHERE `key` = 'schema_signature' AND `namespace` = 'core';