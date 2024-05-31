
-- DROP TABLE IF EXISTS `activity`;
CREATE TABLE `activity` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `kimai_id` int(11) DEFAULT NULL,
  `project` int(11) DEFAULT NULL,
  `name` varchar(150) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `kimai_id` (`kimai_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- DROP TABLE IF EXISTS `customer`;
CREATE TABLE `customer` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `kimai_id` int(11) DEFAULT NULL,
  `name` varchar(150) DEFAULT NULL,
  `number` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `kimai_id` (`kimai_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- DROP TABLE IF EXISTS `project`;
CREATE TABLE `project` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `kimai_id` int(11) DEFAULT NULL,
  `customer` int(11) DEFAULT NULL,
  `name` varchar(150) DEFAULT NULL,
  `start` datetime(3) DEFAULT NULL,
  `end` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `kimai_id` (`kimai_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- DROP TABLE IF EXISTS `team_project`;
CREATE TABLE `team_project` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `team_kimai_id` int(11) DEFAULT NULL,
  `project_kimai_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- DROP TABLE IF EXISTS `team_user`;
CREATE TABLE `team_user` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `team_kimai_id` int(11) DEFAULT NULL,
  `user_kimai_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- DROP TABLE IF EXISTS `team`;
CREATE TABLE `team` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `kimai_id` int(11) DEFAULT NULL,
  `name` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `kimai_id` (`kimai_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- DROP TABLE IF EXISTS `timesheet`;
CREATE TABLE `timesheet` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `kimai_id` int(11) DEFAULT NULL,
  `activity` int(11) DEFAULT NULL,
  `project` int(11) DEFAULT NULL,
  `user` int(11) DEFAULT NULL,
  `begin` datetime(3) DEFAULT NULL,
  `end` datetime(3) DEFAULT NULL,
  `duration` int(11) DEFAULT NULL,
  `description` varchar(200) DEFAULT NULL,
  `rate` double DEFAULT NULL,
  `internalRate` double DEFAULT NULL,
  `billable` tinyint(4) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `kimai_id` (`kimai_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- DROP TABLE IF EXISTS `user`;
CREATE TABLE `user` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `kimai_id` int(11) DEFAULT NULL,
  `alias` varchar(60) DEFAULT NULL,
  `username` varchar(60) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `kimai_id` (`kimai_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
