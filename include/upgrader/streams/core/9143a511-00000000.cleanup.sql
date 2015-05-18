/**
 * @signature 959aca6ed189cd918d227a3ea8a135a3
 * @version v1.9.6
 * @title Retire `private`, `required`, and `edit_mask` for fields
 *
 */

ALTER TABLE `%TABLE_PREFIX%form_field`
    DROP `private`,
    DROP `required`,
    DROP `edit_mask`;

ALTER TABLE `%TABLE_PREFIX%content`
    DROP `lang`;

-- DROP IF EXISTS `%content.content_id`
SET @s = (SELECT IF(
    (SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE table_name = '%TABLE_PREFIX%content'
        AND table_schema = DATABASE()
        AND column_name = 'content_id'
    ) > 0,
    "SELECT 1",
    "ALTER TABLE `%TABLE_PREFIX%content` DROP `content_id`"
));
PREPARE stmt FROM @s;
EXECUTE stmt;
