<?php

namespace admin\models;

use Yii;
use yii\helpers\Url; 

class LoginForm extends \yii\base\Model
{
    private $_user = false;

    public $email;
    public $password;

    public function rules()
    {
        return [
            [['email', 'password'], 'required'],
            ['password', 'validatePassword'],
        ];
    }

    public function attributeLabels()
    {
        return [
            'email' => 'E-Mail',
            'password' => 'Passwort',
        ];
    }

    public function validatePassword($attribute)
    {
        if (!$this->hasErrors()) {
            $user = $this->getUser();
            if (!$user || !$user->validatePassword($this->password)) {
                $this->addError($attribute, 'Falscher Benutzer oder Passwort.');
            }
        }
    }

    public function sendSecureLogin()
    {
        $token = $this->getUser()->getAndStoreToken();

        $txt = '<h1>Luya Sicherheitscode</h1><p>Verwenden Sie den folgenden Sicherheitscode für den Zugriff auf die Administration der Website '.Url::base(true).':</p><p><strong>'.$token.'</strong></p>';
        
        Yii::$app->mail->compose('Luya Sicherheitscode', $txt)->address($this->getUser()->email)->send();
        
        return true;
    }
    
    public function validateSecureToken($token, $userId)
    {
        $user = \admin\models\User::findOne($userId);
        // @todo chekc if secure token timestamp is to old ?!
        if ($user->secure_token == sha1($token)) {
            return $user;
        }
        
        return false;
    }
    
    public function login()
    {
        if ($this->validate()) {
            $user = $this->getUser();
            $user->scenario = 'login';
            $user->force_reload = 0;
            $user->auth_token = Yii::$app->security->hashData(Yii::$app->security->generateRandomString(), $user->password_salt);
            $user->save();

            $login = new UserLogin();
            $login->setAttributes([
                'auth_token' => $user->auth_token,
                'user_id' => $user->id,
            ]);
            $login->insert();
            UserOnline::refreshUser($user->id, 'login');

            return $user;
        } else {
            return false;
        }
    }

    public function getUser()
    {
        if ($this->_user === false) {
            $this->_user = \admin\models\User::findByEmail($this->email);
        }

        return $this->_user;
    }
}
