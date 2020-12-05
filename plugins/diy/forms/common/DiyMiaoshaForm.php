<?php
/**
 * link: http://www.zjhejiang.com/
 * copyright: Copyright (c) 2018 .浙江禾匠信息科技有限公司
 * author: wxf
 */

namespace app\plugins\diy\forms\common;


use app\forms\api\goods\ApiGoods;
use app\models\Model;
use app\plugins\miaosha\models\Goods;
use app\plugins\miaosha\models\MiaoshaActivitys;
use app\plugins\miaosha\models\MiaoshaGoods;
use app\plugins\miaosha\Plugin;


class DiyMiaoshaForm extends Model
{
    public function getGoodsIds($data)
    {
        $goodsIds = [];
        foreach ($data['list'] as $item) {
            $goodsIds[] = $item['id'];
        }

        return $goodsIds;
    }

    public function getGoodsById($goodsIds)
    {
        if (!$goodsIds) {
            return [];
        }

        if (version_compare(\Yii::$app->getAppVersion(), (new Plugin())->version) == 1) {
            return $this->getNewGoodsById($goodsIds);
        } else {
            $goodsIds = MiaoshaGoods::find()->where([
                'is_delete' => 0,
                'mall_id' => \Yii::$app->mall->id,
                'activity_id' => 0,
                'goods_id' => $goodsIds
            ])->select('goods_id');

            $list = Goods::find()->where([
                'id' => $goodsIds,
                'status' => 1,
                'is_delete' => 0
            ])->with('goodsWarehouse', 'miaoshaGoods')->all();
            $newList = [];
            /** @var Goods $item */
            foreach ($list as $item) {
                $apiGoods = ApiGoods::getCommon();
                $apiGoods->goods = $item;
                $apiGoods->isSales = 0;
                $arr = $apiGoods->getDetail();
                $arr['start_time'] = $item->miaoshaGoods->open_date . ' ' . $item->miaoshaGoods->open_time . ':00:00';
                $arr['end_time'] = $item->miaoshaGoods->open_date . ' ' . $item->miaoshaGoods->open_time . ':59:59';
                $newList[] = $arr;
            }

            return $newList;
        }
    }

    private function getNewGoodsById($goodsIds) {
        $activityIds = MiaoshaActivitys::find()->where(['status' => 1, 'mall_id' => \Yii::$app->mall->id, 'is_delete' => 0])->select('id');
        $goodsIds = MiaoshaGoods::find()->where([
            'goods_id' => $goodsIds,
            'mall_id' => \Yii::$app->mall->id,
            'activity_id' => $activityIds
        ])->select('goods_id');
        $list = Goods::find()->where([
            'id' => $goodsIds,
            'status' => 1,
            'is_delete' => 0
        ])->with('goodsWarehouse', 'miaoshaGoods')->all();

        $newList = [];
        /** @var Goods $item */
        foreach ($list as $item) {
            $apiGoods = ApiGoods::getCommon();
            $apiGoods->goods = $item;
            $apiGoods->isSales = 0;
            $arr = $apiGoods->getDetail();
            $arr['start_time'] = $item->miaoshaGoods->open_date . ' ' . $item->miaoshaGoods->open_time . ':00:00';
            $arr['end_time'] = $item->miaoshaGoods->open_date . ' ' . $item->miaoshaGoods->open_time . ':59:59';
            $newList[] = $arr;
        }

        return $newList;
    }

    public function getNewGoods($data, $goods)
    {
        $newArr = [];
        foreach ($data['list'] as &$item) {
            foreach ($goods as $gItem) {
                if ($item['id'] == $gItem['id']) {
                    $newArr[] = $gItem;
                    break;
                }
            }
        }

        $data['list'] = $newArr;

        return $data;
    }
}
