<?php
/**
 * link: http://www.zjhejiang.com/
 * copyright: Copyright (c) 2018 .浙江禾匠信息科技有限公司
 * author: jack_guo
 */

namespace app\plugins\region\forms\export;

use app\core\CsvExport;
use app\forms\mall\export\BaseExport;
use app\models\DistrictArr;

class RegionStatisticsExport extends BaseExport
{
    private $date;

    public function fieldsList()
    {
        return [
            [
                'key' => 'level',
                'value' => '代理级别',
            ],
            [
                'key' => 'bonus',
                'value' => '分红比例',
            ],
            [
                'key' => 'order_money',
                'value' => '分红总金额',
            ],
            [
                'key' => 'order_count',
                'value' => '分红订单总数',
            ],
            [
                'key' => 'province',
                'value' => '省份',
            ],
            [
                'key' => 'date',
                'value' => '日期',
            ],
        ];
    }

    public function export($query)
    {
        $this->transform($query);
        $this->getFields();
        $dataList = $this->getDataList();
        if (empty($this->date)) {
            unset($this->fieldsNameList[6]);
        }

        $fileName = '区域代理统计' . date('YmdHis');
        (new CsvExport())->export($dataList, $this->fieldsNameList, $fileName);
    }

    protected function transform($list)
    {
        if (!empty($list['province_id'])) {
            $province = DistrictArr::getDistrict($list['province_id'])['name'];
        } else {
            $province = '';
        }
        $this->date = '';
        if (!empty($list['date_start']) && !empty($list['date_end'])) {
            $this->date = $list['date_start'] . '~' . $list['date_end'];
        }
        $this->dataList = [
            [
                'number' => 1,
                'level' => '省代理',
                'bonus' => $list['province_rate'],
                'order_money' => $list['province_money'],
                'order_count' => $list['province_count'],
                'province' =>  $province,
                'date' => $this->date,
            ],
            [
                'number' => 2,
                'level' => '市代理',
                'bonus' => $list['city_rate'],
                'order_money' => $list['city_money'],
                'order_count' => $list['city_count'],
                'province' => $province,
                'date' => $this->date,
            ],
            [
                'number' => 3,
                'level' => '区/县代理',
                'bonus' => $list['district_rate'],
                'order_money' => $list['district_money'],
                'order_count' => $list['district_count'],
                'province' => $province,
                'date' => $this->date,
            ],
        ];
    }

    protected function getFields()
    {
        $arr = [];
        foreach ($this->fieldsList() as $key => $item) {
            $arr[$key] = $item['key'];
        }
        $this->fieldsKeyList = $arr;
        parent::getFields(); // TODO: Change the autogenerated stub
    }
}
