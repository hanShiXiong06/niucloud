<?php
declare(strict_types=1);

namespace addon\hsx_member_card\app\model;

use core\base\BaseModel;

final class MemberCardMigrationItem extends BaseModel
{
    protected $pk = 'id';
    protected $name = 'member_card_migration_item';
    protected $autoWriteTimestamp = false;
    protected $json = ['snapshot_json'];
    protected $jsonAssoc = true;
}
