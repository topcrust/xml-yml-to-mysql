<?php

return [

'sql_table' => <<<END
CREATE TABLE IF NOT EXISTS `%db_table_name%` (
	`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
	`url` VARCHAR(255),
	`price` INT UNSIGNED,
	`oldprice` INT UNSIGNED,
	`currencyId` VARCHAR(255),
	`categoryId` INT UNSIGNED,
	`market_category` VARCHAR(255),
	`picture` TEXT,
	`store` BOOL,
	`pickup` BOOL,
	`delivery` BOOL,
	`model` VARCHAR(255),
	`name` VARCHAR(255),
	`typePrefix` VARCHAR(255),
	`vendor` VARCHAR(255),
	`description` TEXT,
	`vendorCode` VARCHAR(255),
	`sales_notes` VARCHAR(255),
	`barcode` VARCHAR(255),
	`param` TEXT,
	PRIMARY KEY (`id`),
	UNIQUE KEY `url` (`url`)
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