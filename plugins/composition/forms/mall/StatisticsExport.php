<?php
/**
 * Created by PhpStorm.
 * User: 风哀伤
 * Date: 2020/3/4
 * Time: 14:36
 * @copyright: ©2019 .浙江禾匠信息科技
 * @link: http://www.zjhejiang.com
 */

namespace app\plugins\composition\forms\mall;


use app\core\CsvExport;
use app\forms\mall\export\BaseExport;
use app\plugins\composition\models\CompositionGoods;

class StatisticsExport extends BaseExport
{
    public function fieldsList()
    {
        return [
            [
                'key' => 'name',
                'value' => '商品名称'
            ],
            [
                'key' => 'payment_people',
                'value' => '支付人数'
            ],
            [
                'key' => 'payment_num',
                'value' => '支付件数'
            ],
            [
                'key' => 'payment_amount',
                'value' => '支付金额'
            ],
        ];
    }

    public function export($query)
    {
        $list = $query->all();
        $this->transform($list);
        $this->getFields();
        $dataList = $this->getDataList();

        $fileName = '套餐组合统计' . date('YmdHis');
        (new CsvExport())->export($dataList, $this->fieldsNameList, $fileName);
    }

    public function transform($list)
    {
        /* @var CompositionGoods[] $list */
        $newList = [];
        $number = 1;
        foreach ($list as $key => $compositionGoods) {
            $newItem = [
                'number' => $number++,
                'name' => $compositionGoods->goods->goodsWarehouse->name,
                'payment_people' => $compositionGoods->payment_people,
                'payment_num' => $compositionGoods->payment_num,
                'payment_amount' => $compositionGoods->payment_amount,
            ];

            $newList[] = $newItem;
        }
        $this->dataList = $newList;
    }

    public function getFields()
    {
        $arr = [];
        foreach ($this->fieldsList() as $key => $item) {
            $arr[$key] = $item['key'];
        }
        $this->fieldsKeyList = $arr;
        parent::getFields(); // TODO: Change the autogenerated stub
    }
}