CREATE TABLE `users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(32) NOT NULL,
  `password` varchar(32) NOT NULL,
  `firstName` varchar(32) NOT NULL,
  `lastName` varchar(32) NOT NULL,
  `username` varchar(255) NOT NULL DEFAULT '',
  `fbId` varchar(25) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE='InnoDB' DEFAULT CHARSET=utf8_czech_ci COLLATE=utf8_czech_ci;

CREATE TABLE `groups` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE='InnoDB' DEFAULT CHARSET=utf8_czech_ci COLLATE=utf8_czech_ci;

CREATE TABLE `groups_users` (
  `id` int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `users_id` int(10) unsigned NOT NULL,
  `groups_id` int(10) unsigned NOT NULL,
  UNIQUE INDEX `users_id_groups_id` (`users_id`, `groups_id`),
  FOREIGN KEY (`users_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`groups_id`) REFERENCES `groups` (`id`) ON DELETE CASCADE
) ENGINE='InnoDB' DEFAULT CHARSET=utf8_czech_ci COLLATE 'utf8_czech_ci';