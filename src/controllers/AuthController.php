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
    public function actionLogin()
    {
        if (!Yii::$app->user->isGuest) {
            return $this->goHome();
        }

        /** @var Module $module */
        $module = $this->module;

        $model = new LoginForm();

        $request = Yii::$app->request;
        $cache   = Yii::$app->cache;

        $ip        = $request->userIP;
        $userAgent = substr((string)$request->userAgent, 0, 120);

        $remaining = 0;

        /* ============================================================
         * 🕵️ Honeytoken — ловушка для ботов
         * ============================================================ */
        if (!empty($request->post('login_check'))) {
            Yii::warning("Auth bot detected from IP {$ip}", __METHOD__);

            $cache->set(
                'login_ip_attempts_' . md5($ip),
                $module->ipMaxAttempts + 100,
                $module->ipAttemptsTtl
            );

            Yii::$app->session->setFlash(
                'error',
                'Подозрительная активность. Попробуйте позже.'
            );

            return $this->render('login', compact('model', 'remaining'));
        }

        /* ============================================================
         * 🌐 Глобальная блокировка IP + UA
         * ============================================================ */
        $ipKey = 'login_ip_attempts_' . md5($ip . '_' . $userAgent);
        $ipAttempts = (int)($cache->get($ipKey) ?? 0);

        if ($ipAttempts >= $module->ipMaxAttempts) {
            Yii::warning("IP blocked {$ip} ({$ipAttempts})", __METHOD__);

            Yii::$app->session->setFlash(
                'warning',
                'Мы временно ограничили попытки входа с вашего IP.'
            );

            return $this->render('login', compact('model', 'remaining'));
        }

        /* ============================================================
         * ⏱ Проверка блокировки при GET (таймер)
         * ============================================================ */
        if ($request->isGet) {
            $username = Yii::$app->session->get('lastUsername') ?? 'guest';
            $lockKey  = 'login_lock_' . md5($ip . '_' . $username);
            $lockTime = $cache->get($lockKey);

            if ($lockTime !== false) {
                $remaining = max(0, $lockTime - time());
            }
        }

        /* ============================================================
         * 🔑 Обработка POST
         * ============================================================ */
        if ($model->load($request->post())) {
            $username = $model->username ?: 'guest';
            Yii::$app->session->set('lastUsername', $username);

            $userKey = 'login_attempts_' . md5($ip . '_' . $username . '_' . $userAgent);
            $lockKey = 'login_lock_' . md5($ip . '_' . $username);

            $userAttempts = (int)($cache->get($userKey) ?? 0);
            $lockTime     = $cache->get($lockKey);

            /* 🔒 Уже заблокирован */
            if ($lockTime !== false) {
                $remaining = max(0, $lockTime - time());
                return $this->render('login', compact('model', 'remaining'));
            }

            /* 🧮 Экспоненциальная задержка */
            $delay = min($userAttempts ** 2, $module->maxDelaySeconds);
            if ($delay > 0) {
                sleep($delay);
            }

            /* ✅ Успешный вход */
            if ($model->login()) {
                $cache->delete($userKey);
                $cache->delete($ipKey);
                $cache->delete($lockKey);

                Yii::$app->session->remove('lastUsername');
                Yii::$app->session->setFlash('success', 'Добро пожаловать!');

                return $this->goBack();
            }

            /* ❌ Ошибка входа */
            $userAttempts++;
            $ipAttempts++;

            $cache->set($userKey, $userAttempts, $module->userAttemptsTtl);
            $cache->set($ipKey, $ipAttempts, $module->ipAttemptsTtl);

            Yii::warning(
                "Failed login #{$userAttempts} for {$username} ({$ip})",
                __METHOD__
            );

            $remainingAttempts = max(
                $module->maxUserAttempts - $userAttempts,
                0
            );

            /* 🤖 CAPTCHA */
            if ($userAttempts >= $module->captchaAfterAttempts) {
                $model->scenario = 'withCaptcha';
            }

            /* 🔒 Блокировка пользователя */
            if ($remainingAttempts <= 0) {
                $lockTime = time() + $module->lockDuration;
                $cache->set($lockKey, $lockTime, $module->lockDuration);

                $remaining = $lockTime - time();

                Yii::warning(
                    "User {$username} locked for {$module->lockDuration}s ({$ip})",
                    __METHOD__
                );

                return $this->render('login', compact('model', 'remaining'));
            }

            Yii::$app->session->setFlash(
                'error',
                "Неверный логин или пароль. Осталось попыток: {$remainingAttempts}."
            );
        }

        $model->password = '';

        return $this->render('login', [
            'model'     => $model,
            'remaining' => (int)$remaining,
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
