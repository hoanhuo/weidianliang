<?php
/**
 * link: http://www.zjhejiang.com/
 * copyright: Copyright (c) 2018 .浙江禾匠信息科技有限公司
 * author: xay
 */

namespace app\plugins\scratch\forms\api;

use app\forms\api\order\OrderGoodsAttr;
use app\plugins\scratch\forms\common\CommonScratch;
use app\plugins\scratch\models\ScratchLog;
use app\models\Goods;
use app\forms\api\order\OrderSubmitForm;
use app\forms\api\order\OrderException;

class ScratchOrderSubmitForm extends OrderSubmitForm
{
    public $form_data;
    public $scratchLog;

    public function rules()
    {
        return [
            [['form_data'], 'required'],
        ];
    }

    public function subGoodsNum($goodsAttr, $subNum, $goodsItem)
    {
    }

    public function checkGoodsStock($goodsList)
    {
        return true;
    }

    public function checkGoods($goods, $item)
    {
        $scratch_id = $this->form_data->list[0]['scratch_id'];

        $scratchLog = ScratchLog::find()->where([
            'mall_id' => \Yii::$app->mall->id,
            'user_id' => \Yii::$app->user->id,
            'status' => 1,
            'id' => $scratch_id,
            'type' => 4,
        ])->one();
        if (!$scratchLog) {
            throw new OrderException('奖品已过期或不存在');
        }
        $this->scratchLog = $scratchLog;
    }

    public function getGoodsItemData($item)
    {
        /* @var Goods $goods */
        $goods = Goods::find()->with('goodsWarehouse')->where([
            'id' => $item['id'],
            'mall_id' => \Yii::$app->mall->id,
        ])->one();

        if (!$goods) {
            throw new OrderException('商品不存在或已下架。');
        }

        // 其他商品特有判断
        $this->checkGoods($goods, $item);

        try {
            /** @var OrderGoodsAttr $goodsAttr */
            $goodsAttr = $this->getGoodsAttr($item['goods_attr_id'], $goods);
        } catch (\Exception $exception) {
            throw new OrderException('无法查询商品`' . $goods->name . '`的规格信息。');
        }

        $attrList = $goods->signToAttr($goodsAttr->sign_id);

        $itemData = [
            'id' => $goods->id,
            'name' => $goods->goodsWarehouse->name,
            'cover_pic' => $goods->goodsWarehouse->cover_pic,
            'num' => 1,
            'forehead_integral' => 0,
            'forehead_integral_type' => 0,
            'accumulative' => 0,
            'pieces' => 0,
            'forehead' => 0,
            'freight_id' => $goods->freight_id,
            'unit_price' => price_format($goodsAttr->original_price),
            'total_original_price' => price_format($goodsAttr->original_price * $item['num']),
            'total_price' => 0,
            'goods_attr' => $goodsAttr,
            'attr_list' => $attrList,
            'discounts' => [],
            'member_discount' => price_format(0),
            'is_level_alone' => $goods->is_level_alone,
            // 规格自定义货币 例如：步数宝的步数币
            'custom_currency' => $this->getCustomCurrency($goods, $goodsAttr),
            'is_level' => $goods->is_level,
            'goods_warehouse_id' => $goods->goods_warehouse_id,
            'sign' => $goods->sign,
            'goodsWarehouse' => $goods->goodsWarehouse,
            'form_id' => $goods->form_id,
        ];
        return $itemData;
    }

    public function getSendType($mchItem)
    {
        $setting = CommonScratch::getSetting();
        if ($setting) {
            $sendType = $setting['send_type'];
        } else {
            $sendType = ['express', 'offline'];
        }
        return $sendType;
    }

    public function getToken()
    {
        return $this->scratchLog->token ?: parent::getToken();
    }
}
