<?php

namespace admin\models;

class TagRelation extends \yii\db\ActiveRecord
{
    public static function tableName()
    {
        return 'admin_tag_relation';
    }
    
    public function rules()
    {
        return [
            [['tag_id', 'table_name', 'pk_id'], 'safe'],  
        ];
    }
}