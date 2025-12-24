<?php

namespace larikmc\auth\assets;

use yii\web\AssetBundle;

class AuthAsset extends AssetBundle
{
    public $sourcePath = '@larikmc/auth/assets/dist';

    public $css = [
        'auth.css',
    ];

    public $js = [
        // 'auth.js', // если понадобится
    ];

    public $depends = [
        'yii\bootstrap5\BootstrapAsset',
        'yii\bootstrap5\BootstrapPluginAsset',
    ];
}
