<?php

namespace larikmc\auth\controllers;

use Yii;
use yii\web\Controller;
use yii\captcha\CaptchaAction;
use larikmc\auth\models\LoginForm;
use larikmc\auth\Module;

class AuthController extends Controller
{
    public $layout = '@larikmc/auth/views/layouts/auth';
    public $remaining;

    /**
     * CAPTCHA action
     */
    public function actions(): array
    {
        return [
            'captcha' => [
                'class' => CaptchaAction::class,
                'fixedVerifyCode' => YII_ENV_TEST ? 'testme' : null,
            ],
        ];
    }

    /**
     * Login action with brute-force protection
     */
    public function actionLogin($email = null){

        if (!Yii::$app->user->isGuest) {
            return $this->goHome();
        }

        /** @var Module $module */
        $module = $this->module;

        $model   = new LoginForm();
        $request = Yii::$app->request;
        $cache   = Yii::$app->cache;

        $ip      = $request->userIP;
        $userKey = 'login_attempts_' . md5($ip);
        $lockKey = 'login_lock_' . md5($ip);

        $userAttempts = (int) ($cache->get($userKey) ?? 0);
        $lockTime = $cache->get($lockKey);
        $remaining = 0;

//        $cache->delete($userKey);
//        $cache->delete($lockKey);

        /* ============================================================
       * 🕵️ Honeytoken — ловушка для ботов
       * ============================================================ */
        if (!empty($request->post('login_check'))) {
            Yii::warning("Auth bot detected from IP {$ip}", __METHOD__);

            $lockTime = time() + $module->lockDuration;

            $cache->set(
                $lockKey,
                $lockTime,
                $module->lockDuration
            );

            return $this->redirect('login');
        }


        /* ============================================================
        * 🔑 Обработка POST
        * ============================================================ */
        if ($model->load($request->post())) {
            if ($model->login()) {
                return $this->goBack();
            }

            /* 🧮 Экспоненциальная задержка */
            $delay = min($userAttempts ** 2, $module->maxDelaySeconds);
            if ($delay > 0) {
                sleep($delay);
            }

            if ($model->login()) {
                $cache->delete($userKey);
                $cache->delete($lockKey);
                return $this->goBack();
            }

            /* сохраняем количество попыток */
            $userAttempts++;
            $cache->set($userKey, $userAttempts, $module->userAttemptsTtl);

            $remainingAttempts = max(
                $module->maxUserAttempts - $userAttempts,
                0
            );

            /* 🔒 Блокировка пользователя */
            if ($remainingAttempts <= 0) {
                $lockTime = time() + $module->lockDuration;

                $cache->set(
                    $lockKey,
                    $lockTime,
                    $module->lockDuration
                );
            }

            return $this->redirect(['login', 'email' => $model->email]);
        }

        /* ============================================================
       * 🔑 Обработка GET
       * ============================================================ */
        $model->password = '';
        if($email){
            $model->email = $email;
        }

        if ($lockTime !== false) {
            $remaining = max(0, $lockTime - time());
            return $this->render('login', compact('model', 'remaining'));
        }

        if($userAttempts > 0){
            /* ❌ Ошибка входа */
            $remainingAttempts = max(
                $module->maxUserAttempts - $userAttempts,
                0
            );
            Yii::$app->session->setFlash(
                'error',
                "Неверный логин или пароль. Осталось попыток: {$remainingAttempts}"
            );
        }

        /* 🤖 CAPTCHA */
        if ($userAttempts >= $module->captchaAfterAttempts) {
            $model->scenario = 'withCaptcha';
        }

        return $this->render('login', [
            'model'     => $model,
            'remaining' => (int) $remaining,
        ]);
    }

    /**
     * Logout
     */
    public function actionLogout()
    {
        Yii::$app->user->logout();
        return $this->goHome();
    }
}
