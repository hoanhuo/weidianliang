<?php
/**
 * link: http://www.zjhejiang.com/
 * copyright: Copyright (c) 2018 .浙江禾匠信息科技有限公司
 * author: wxf
 */

namespace app\forms\mall\card;


use app\core\response\ApiCode;
use app\forms\common\card\CommonUserCardList;
use app\models\GoodsCardClerkLog;
use app\models\GoodsCards;
use app\models\Model;
use app\models\UserCard;
use yii\helpers\ArrayHelper;

class CardForm extends Model
{
    public $id;
    public $page;
    public $keyword;
    public $user_card_id;

    public function rules()
    {
        return [
            [['id', 'user_card_id'], 'integer'],
            [['page'], 'default', 'value' => 1],
            [['keyword',], 'default', 'value' => ''],
            [['keyword',], 'string'],
        ];
    }

    public function attributeLabels()
    {
        return [
            'id' => '卡券ID',
        ];
    }

    public function getList()
    {
        if (!$this->validate()) {
            return $this->getErrorResponse();
        }

        $query = GoodsCards::find()
            ->where([
                'mall_id' => \Yii::$app->mall->id,
                'is_delete' => 0,
            ]);

        $list = $query
            ->keyword($this->keyword, ['like', 'name', $this->keyword])
            ->orderBy(['created_at' => SORT_DESC])
            ->page($pagination)
            ->all();

        $newList = [];
        /** @var GoodsCards $item */
        foreach ($list as $item) {
            $newItem = ArrayHelper::toArray($item);
            $newItem['begin_time'] = new_date($item->begin_time);
            $newItem['end_time'] = new_date($item->end_time);
            $newItem['created_at'] = new_date($item->created_at);
            $newList[] = $newItem;
        }

        return [
            'code' => ApiCode::CODE_SUCCESS,
            'msg' => '请求成功',
            'data' => [
                'list' => $newList,
                'pagination' => $pagination
            ]
        ];
    }

    public function getOptionList()
    {
        $list = GoodsCards::find()->where([
            'mall_id' => \Yii::$app->mall->id,
            'is_delete' => 0,
        ])->all();

        $newList = [];
        /** @var GoodsCards $item */
        foreach ($list as $item) {
            $newList[$item->id]['id'] = $item->id;
            $newList[$item->id]['name'] = $item->name;
            $newList[$item->id]['num'] = 1;
            $newList[$item->id]['count'] = $item->total_count == -1 ? '无限量' : $item->total_count;
        }

        $newList = array_values($newList);

        return [
            'code' => ApiCode::CODE_SUCCESS,
            'msg' => '请求成功',
            'data' => [
                'list' => $newList
            ]
        ];
    }

    public function getDetail()
    {
        $detail = GoodsCards::find()->where(['mall_id' => \Yii::$app->mall->id, 'id' => $this->id])->one();
        $detail = ArrayHelper::toArray($detail);
        $sign = '0000-00-00 00:00:00';
        if ($detail['begin_time'] == $sign || $detail['end_time'] == $sign) {
            $detail['time'] = [];
        } else {
            $detail['time'] = [$detail['begin_time'], $detail['end_time']];
        }

        if (!$detail) {
            return [
                'code' => ApiCode::CODE_ERROR,
                'msg' => '请求失败',
            ];
        }
        return [
            'code' => ApiCode::CODE_SUCCESS,
            'msg' => '请求成功',
            'data' => [
                'detail' => $detail,
            ]
        ];
    }

    public function destroy()
    {

        try {
            $card = GoodsCards::findOne(['mall_id' => \Yii::$app->mall->id, 'id' => $this->id]);

            if (!$card) {
                return [
                    'code' => ApiCode::CODE_ERROR,
                    'msg' => '数据异常,该条数据不存在'
                ];
            }

            $card->is_delete = 1;
            $res = $card->save();

            if ($res) {
                return [
                    'code' => ApiCode::CODE_SUCCESS,
                    'msg' => '删除成功',
                ];
            }

            return [
                'code' => ApiCode::CODE_ERROR,
                'msg' => '删除失败',
            ];

        } catch (\Exception $e) {
            return [
                'code' => ApiCode::CODE_ERROR,
                'msg' => $e->getMessage(),
            ];
        }
    }


    public function getHistoryList()
    {
        if (!$this->validate()) {
            return $this->getErrorResponse();
        }

        try {
            $form = new CommonUserCardList();
            $form->user_card_id = $this->user_card_id;
            $form->page = $this->page;
            $res = $form->getClerkHistory();

            return [
                'code' => ApiCode::CODE_SUCCESS,
                'msg' => '请求成功',
                'data' => $res
            ];
        } catch (\Exception $exception) {
            return [
                'code' => ApiCode::CODE_ERROR,
                'msg' => $exception->getMessage(),
                'error' => [
                    'line' => $exception->getLine()
                ]
            ];
        }
    }
}
