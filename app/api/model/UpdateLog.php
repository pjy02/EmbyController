<?php
namespace app\api\model;

use think\Model;

class UpdateLog extends Model
{
    protected $name = 'update_logs';

    // Define the fields that can be mass assigned
    protected $schema = [
        'id' => 'int',
        'app_name' => 'varchar',
        'update_date' => 'date',
        'version' => 'varchar',
        'content' => 'text',
    ];
}