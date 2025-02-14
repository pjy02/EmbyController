<?php

namespace app\api\model;

use think\Model;

class BetParticipantModel extends Model
{

    protected $name = 'bet_participant';
    protected $pk = 'id';

    protected $schema = [
        'id' => 'int',
        'betId' => 'int',
        'telegramId' => 'varchar',
        'userId' => 'int',
        'type' => 'varchar',
        'amount' => 'decimal',
        'status' => 'int',
        'winAmount' => 'decimal',
    ];
}