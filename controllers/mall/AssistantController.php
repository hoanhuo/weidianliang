<?php
/**
 * Created by PhpStorm.
 * User: 风哀伤
 * Date: 2020/5/18
 * Time: 17:04
 * @copyright: ©2019 .浙江禾匠信息科技
 * @link: http://www.zjhejiang.com
 */

namespace app\controllers\mall;


use app\forms\mall\goods\GoodsCollect;

class AssistantController extends MallController
{
    public function actionIndex()
    {
        if (\Yii::$app->request->isAjax) {
            $form = new \app\plugins\assistant\forms\mall\SettingForm();
            if (\Yii::$app->request->isPost) {
                $form->attributes = \Yii::$app->request->post();
                return $this->asJson($form->save());
            } else {
                return $this->asJson($form->getSetting());
            }
        } else {
            return $this->render('index');
        }
    }

    public function actionCollect()
    {
        if (\Yii::$app->request->isAjax) {
            if (\Yii::$app->request->isPost) {
                $form = new GoodsCollect();
                $form->attributes = \Yii::$app->request->post();
                return $this->asJson($form->save());
            }
        } else {
            return $this->render('@app/views/mall/assistant/collect');
        }
    }
}
