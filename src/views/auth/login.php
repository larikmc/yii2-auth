<?php
/** @var yii\web\View $this */
/** @var \larikmc\auth\models\LoginForm $model */
/** @var int $remaining */

use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;
use yii\captcha\Captcha;

$this->title = 'Вход в систему';

$blocked = !empty($remaining) && $remaining > 0;
?>

<div class="registration">
    <h1 class="mb-4"><?= Html::encode($this->title) ?></h1>

    <?php foreach (Yii::$app->session->getAllFlashes() as $type => $msg): ?>
        <div class="alert alert-warning">
            <?= Html::encode($msg) ?>
        </div>
    <?php endforeach; ?>

    <?php if ($blocked): ?>
        <div class="alert alert-warning text-center"
             id="lock-timer" style="z-index:1050;">
            Попытки входа временно ограничены. Повторите попытку через
            <span id="timer"><?= gmdate('i:s', (int)$remaining) ?></span>
        </div>
    <?php endif; ?>

    <?php if (!$blocked): ?>

        <?php $form = ActiveForm::begin(['id' => 'login-form']); ?>

        <?= $form->field($model, 'email')
                ->textInput([
                        'class' => 'form-control form-control-lg',
                        'autofocus' => true,
                ]) ?>

        <?= $form->field($model, 'password')
                ->passwordInput([
                        'class' => 'form-control form-control-lg',
                ]) ?>

        <?= Html::hiddenInput('login_check', '', ['class' => 'bot-trap']) ?>
        <?php $this->registerCss('.bot-trap{display:none!important;visibility:hidden}'); ?>

        <?php if ($model->scenario === 'withCaptcha'): ?>
            <?= $form->field($model, 'verifyCode')->widget(Captcha::class, [
                    'captchaAction' => ['/auth/auth/captcha'],
                    'template' => '<div class="row">
                    <div class="col-lg-6">{image}</div>
                    <div class="col-lg-6">{input}</div>
                </div>',
                    'options' => [
                            'class' => 'form-control',
                            'placeholder' => 'Введите код с картинки',
                    ],
                    'imageOptions' => [
                            'alt' => 'CAPTCHA',
                            'title' => 'Обновить изображение',
                            'style' => 'cursor:pointer;',
                    ],
            ]) ?>
        <?php endif; ?>

        <?= $form->field($model, 'rememberMe')->checkbox() ?>

        <div class="form-group mt-3">
            <?= Html::submitButton('Войти', [
                    'class' => 'btn btn-primary btn-lg w-100',
                    'name'  => 'login-button',
            ]) ?>
        </div>

        <?php ActiveForm::end(); ?>

    <?php endif; ?>
</div>

<?php if ($blocked): ?>
    <?php
    $remaining = (int)$remaining;
    $this->registerJs(<<<JS
let seconds = {$remaining};
const timerEl = document.getElementById('timer');
const alertBox = document.getElementById('lock-timer');

function updateTimer() {
    if (seconds <= 0) {
        location.reload();
        return;
    }

    seconds--;

    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;

    if (seconds < 60) {
        timerEl.style.color = '#d9534f';
        timerEl.style.fontWeight = 'bold';
    }

    timerEl.textContent =
        (mins < 10 ? '0' + mins : mins) + ':' +
        (secs < 10 ? '0' + secs : secs);

    setTimeout(updateTimer, 1000);
}

updateTimer();
JS);
    ?>
<?php endif; ?>
