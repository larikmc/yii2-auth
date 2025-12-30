<?php
namespace larikmc\auth\models;

use Yii;
use yii\base\Model;
use yii\web\IdentityInterface;

class LoginForm extends Model
{
    public $email;
    public $password;
    public $rememberMe = true;
    public $verifyCode;

    private $_user;

    public function rules()
    {
        return [
            [['email', 'password'], 'required'],
            ['email', 'email'],
            ['rememberMe', 'boolean'],
            ['password', 'validatePassword'],
            ['verifyCode', 'captcha', 'on' => 'withCaptcha'],
        ];
    }

    public function validatePassword($attribute)
    {
        if ($this->hasErrors()) {
            return;
        }
        $user = $this->getUser();
        if (!$user || !$user->validatePassword($this->password)) {
            $this->addError($attribute, 'Неверный логин или пароль.');
        }
    }

    public function login()
    {
        if ($this->validate()) {
            return Yii::$app->user->login(
                $this->getUser(),
                $this->rememberMe ? 3600 * 24 * 30 : 0
            );
        }
        return false;
    }

    protected function getUser(): ?IdentityInterface
    {
        if ($this->_user !== null) {
            return $this->_user;
        }
        $userClass = Yii::$app->getModule('auth')->userClass;
        $this->_user = $userClass::findByEmail($this->email);
        return $this->_user;
    }

    public function attributeLabels(): array
{
    return [
        'email'   => 'Email',
        'password'   => 'Пароль',
        'rememberMe' => 'Запомнить меня',
        'verifyCode' => 'Проверочный код',
    ];
}
}