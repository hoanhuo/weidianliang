<?php
/**
 * link: http://www.zjhejiang.com/
 * copyright: Copyright (c) 2018 .浙江禾匠信息科技有限公司
 * author: wxf
 */

namespace app\plugins\diy\forms\common;


use app\forms\api\goods\ApiGoods;
use app\models\Goods;
use app\models\Model;

class DiyGiftForm extends Model
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

        $list = Goods::find()->where([
            'id' => $goodsIds,
            'status' => 1,
            'is_delete' => 0,
        ])->with('goodsWarehouse')->all();

        $newList = [];
        /** @var Goods $item */
        foreach ($list as $item) {
            $apiGoods = ApiGoods::getCommon();
            $apiGoods->goods = $item;
            $apiGoods->isSales = 0;
            $arr = $apiGoods->getDetail();
            $arr['original_price'] = '';// 不显示原价
            $arr['page_url'] = '/plugins/gift/goods/goods?is_share=1&id=' . $item->id;
            $arr['is_level'] = 0;
            $newList[] = $arr;
        }

        return $newList;
    }

    public function getNewGoods($data, $goods)
    {
        $newArr = [];
        foreach ($data['list'] as $item) {
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
