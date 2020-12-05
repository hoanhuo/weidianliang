<?php
/**
 * @copyright ©2020 .浙江禾匠信息科技
 * Created by PhpStorm.
 * User: Andy - Wangjie
 * Date: 2020/2/14
 * Time: 14:36
 */

namespace app\plugins\diy\forms\common;

use app\forms\api\goods\ApiGoods;
use app\models\Model;
use app\plugins\pick\models\Goods;

class DiyPickForm extends Model
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
        ])->with('goodsWarehouse', 'pickGoods.activity')->all();

        $newList = [];
        /** @var Goods $item */
        foreach ($list as $item) {
            if (
                $item->pickGoods->activity->end_at <= mysql_timestamp() ||
                $item->pickGoods->activity->start_at > mysql_timestamp() ||
                $item->pickGoods->activity->status == 0
            ) {
                continue;
            }
            $apiGoods = ApiGoods::getCommon();
            $apiGoods->goods = $item;
            $apiGoods->isSales = 0;
            $arr = $apiGoods->getDetail();
            $arr['rule_num'] = $item->pickGoods->activity->rule_num;
            $arr['rule_price'] = $item->pickGoods->activity->rule_price;
            $arr['page_url'] = '/plugins/pick/detail/detail?goods_id=' . $item->pickGoods->goods_id;
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
