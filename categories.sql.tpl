<?php

return [

'sql_table' => <<<END
CREATE TABLE IF NOT EXISTS `%db_table_name%` (
	`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
	`parent_id` INT UNSIGNED,
	`name` VARCHAR(255),
	PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
END,

'sql_ins' => <<<END
INSERT INTO `%db_table_name%` (%sql_cols%) VALUES (%sql_vals%) ON DUPLICATE KEY UPDATE %sql_upds%

END,

'sql_col' => <<<END
`%item%`
END,

'sql_val' => <<<END
:%item%
END,

'sql_upd' => <<<END
`%item%` = :%item%_upd
END,

];

?>