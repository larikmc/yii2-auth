<?php

namespace larikmc\auth;

use yii\base\Module as BaseModule;

class Module extends BaseModule
{
    /** @var string */
    public $controllerNamespace = 'larikmc\auth\controllers';

    /**
     * @var string Fully-qualified User class
     * Example: common\models\User
     */
    public string $userClass;

    /* ============================================================
     * 🔐 Security settings (defaults)
     * ============================================================ */

    /** Максимальное количество попыток входа для пользователя */
    public int $maxUserAttempts = 5;

    /** После скольких попыток показывать CAPTCHA */
    public int $captchaAfterAttempts = 3;

    /** Время блокировки пользователя (сек) */
    public int $lockDuration = 900; // 15 минут

    /** TTL счётчика попыток пользователя (сек) */
    public int $userAttemptsTtl = 900; // 15 минут

    /** Максимальная задержка при брутфорсе (сек) */
    public int $maxDelaySeconds = 10;
}
