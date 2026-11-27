<?php
declare(strict_types=1);

namespace addon\hsx_member_card\app\model;

use core\base\BaseModel;

final class MemberCardMigrationTask extends BaseModel
{
    protected $pk = 'id';
    protected $name = 'member_card_migration_task';
    protected $autoWriteTimestamp = false;
    protected $json = ['summary_json', 'result_json'];
    protected $jsonAssoc = true;
}
