<?php
/**
 * link: http://www.zjhejiang.com/
 * copyright: Copyright (c) 2018 .浙江禾匠信息科技有限公司
 * author: wxf
 */

namespace app\plugins\integral_mall\forms\api;

use app\core\response\ApiCode;
use app\models\GoodsCatRelation;
use app\models\GoodsCats;
use app\models\Model;
use app\plugins\integral_mall\models\Goods;
use app\plugins\integral_mall\Plugin;

class CatsForm extends Model
{
    public $page;

    public function rules()
    {
        return [
            [['page'], 'safe'],
            [['page'], 'default', "value" => 1]
        ];
    }

    public function getList()
    {
        if (!$this->validate()) {
            return $this->getErrorResponse();
        }

        try {
            if (!$this->validate()) {
                return $this->getErrorResponse();
            }

            $goodsWarehouseIds = Goods::find()->where([
                'mall_id' => \Yii::$app->mall->id,
                'is_delete' => 0,
                'status' => 1,
                'sign' => (new Plugin())->getName()
            ])->select('goods_warehouse_id');

            // TODO 表结构问题导致查询量太大 表没有goods_id
            $catIds = GoodsCatRelation::find()->where([
                'goods_warehouse_id' => $goodsWarehouseIds,
                'is_delete' => 0
            ])->select('cat_id');

            $list = GoodsCats::find()->where([
                'id' => $catIds,
                'parent_id' => 0,
                'status' => 1
            ])->orderBy(['sort' => SORT_ASC])->all();

            return [
                'code' => ApiCode::CODE_SUCCESS,
                'data' => [
                    'list' => $list,
                ]
            ];
        } catch (\Exception $e) {
            return [
                'code' => ApiCode::CODE_ERROR,
                'msg' => $e->getMessage(),
            ];
        }
    }
}
