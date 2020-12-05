<?php

namespace app\plugins\booking\controllers\api;

use app\controllers\api\ApiController;
use app\controllers\api\filters\LoginFilter;
use app\plugins\booking\forms\api\BookingOrderSubmitForm;
use app\plugins\booking\forms\common\CommonBooking;

class OrderController extends ApiController
{
    public function behaviors()
    {
        return array_merge(parent::behaviors(), [
            'login' => [
                'class' => LoginFilter::class,
            ],
        ]);
    }

    public function actionOrderPreview()
    {
        $form = new BookingOrderSubmitForm();
        $form->form_data = \Yii::$app->serializer->decode(\Yii::$app->request->post('form_data'));

        $setting = CommonBooking::getSetting();
        return $this->asJson($form->setEnableAddressEnable($setting['is_territorial_limitation'] ? true : false)
            ->setEnableIntegral($setting['is_integral'] ? true : false)
            ->setEnableMemberPrice($setting['is_member_price'] ? true : false)
            ->setEnableCoupon($setting['is_coupon'] ? true : false)
            ->setEnableVipPrice($setting['svip_status'] ? true : false)
            ->setEnableOrderForm(true)
            ->setSupportPayTypes($setting['payment_type'])
            ->setSign('booking')
            ->preview());
    }

    public function actionOrderSubmit()
    {
        $form = new BookingOrderSubmitForm();
        $form->form_data = \Yii::$app->serializer->decode(\Yii::$app->request->post('form_data'));
        $setting = CommonBooking::getSetting();
        return $this->asJson($form->setEnableAddressEnable($setting['is_territorial_limitation'] ? true : false)
            ->setEnableIntegral($setting['is_integral'] ? true : false)
            ->setEnableMemberPrice($setting['is_member_price'] ? true : false)
            ->setEnableCoupon($setting['is_coupon'] ? true : false)
            ->setEnableVipPrice($setting['svip_status'] ? true : false)
            ->setSupportPayTypes($setting['payment_type'])
            ->setEnableOrderForm(false)
            ->setSign('booking')
            ->submit());
    }
}
