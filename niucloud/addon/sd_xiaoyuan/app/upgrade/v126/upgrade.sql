UPDATE `xiaoyuan_runner` SET `can_jiedan` = 1 WHERE `status` = 1 AND (`can_jiedan` IS NULL OR `can_jiedan` = 0);
