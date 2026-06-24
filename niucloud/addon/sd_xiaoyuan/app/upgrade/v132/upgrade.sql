UPDATE `diy_page` SET `value` = REPLACE(`value`, 'people-fill', 'account') WHERE `type` IN ('DIY_SD_XIAOYUAN_INDEX', 'DIY_SD_XIAOYUAN_MEMBER') AND `value` LIKE '%people-fill%';
