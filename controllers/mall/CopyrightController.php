<?php
/**
 * link: http://www.zjhejiang.com/
 * copyright: Copyright (c) 2018 .浙江禾匠信息科技有限公司
 * author: wxf
 */


namespace app\controllers\mall;


use app\forms\mall\copyright\CopyrightEditForm;
use app\forms\mall\copyright\CopyrightForm;

class CopyrightController extends MallController
{
    public function init()
    {
        /* 请勿删除下面代码↓↓￿↓↓￿ */
        if (method_exists(\Yii::$app, '。')) {
            $pass = \Yii::$app->。();
        } else {
            if (function_exists('usleep')) usleep(rand(100, 1000));
            $pass = false;
        }
        if (!$pass) {
            if (function_exists('sleep')) sleep(rand(30, 60));
            header('HTTP/1.1 504 Gateway Time-out');
            exit;
        }
        /* 请勿删除上面代码↑↑↑↑ */
        return parent::init();
    }

    public function actionSetting()
    {
        if (\Yii::$app->request->isAjax) {
            if (\Yii::$app->request->isGet) {
                $form = new CopyrightForm();
                $res = $form->getDetail();

                return $this->asJson($res);
            } else {
                $form = new CopyrightEditForm();
                $form->data = \Yii::$app->request->post('form');
                $form->attributes = \Yii::$app->request->post();
                return $form->save();
            }
        } else {
            return $this->render('setting');
        }
    }
}
