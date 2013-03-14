-- Here's my DB structure

CREATE TABLE IF NOT EXISTS `emails` (
    `id` int(255) NOT NULL AUTO_INCREMENT,
    `from` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
    `subject` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
    `body` text COLLATE utf8_unicode_ci NOT NULL,
    `date` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;

CREATE TABLE IF NOT EXISTS `files` (
    `id` int(255) NOT NULL AUTO_INCREMENT,
    `email_id` int(255) NOT NULL,
    `filename` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
    `size` varchar(20) COLLATE utf8_unicode_ci NOT NULL,
    `mime` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
    PRIMARY KEY (`id`)
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;
