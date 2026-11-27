<?php
declare(strict_types=1);
namespace addon\hsx_member_card\app\model;
use core\base\BaseModel;
final class MemberCardOutboxEvent extends BaseModel { protected $pk = 'id'; protected $name = 'member_card_outbox_event'; protected $autoWriteTimestamp = false; }
