<?php
/**
 * link: http://www.zjhejiang.com/
 * copyright: Copyright (c) 2018 .浙江禾匠信息科技有限公司
 * author: xay
 */

namespace app\forms\common\order;

use app\core\response\ApiCode;
use app\forms\common\CommonOption;
use app\models\Delivery;
use app\models\Express;
use app\models\MallSetting;
use app\models\Model;
use app\models\Option;
use app\models\Order;
use app\models\OrderDetailExpress;
use app\models\OrderExpressSingle;

class PrintForm extends Model
{
    public $order_id;
    public $express;
    public $zip_code;
    public $mch_id;
    public $delivery_account;
    public $order_detail_ids;

    public function rules()
    {
        return [
            [['order_id', 'express'], 'required'],
            [['order_id', 'mch_id'], 'integer'],
            [['zip_code', 'express', 'delivery_account'], 'string'],
            [['order_detail_ids'], 'trim'],
            [['zip_code'], 'default', 'value' => 0],
        ];
    }

    public function attributeLabels()
    {
        return [
            'express' => "快递公司名称",
            'delivery_account' => '面单账户',
        ];
    }

    public function save()
    {
        if (!$this->validate()) {
            return $this->getErrorResponse();
        }
        try {
            $result = $this->track();
            return [
                'code' => ApiCode::CODE_SUCCESS,
                'msg' => 'success',
                'data' => $result
            ];
        } catch (\DomainException $e) {
            $json = \yii\helpers\BaseJson::decode($e->getMessage());
            return [
                'code' => ApiCode::CODE_ERROR,
                'msg' => $json['Reason'],
                'result' => $json,
            ];
        } catch (\Exception $e) {
            return [
                'code' => ApiCode::CODE_ERROR,
                'msg' => $e->getMessage(),
                'error' => [
                    'line' => $e->getLine()
                ]
            ];
        }
    }

    /**
     * @return array|mixed
     * @throws \Exception
     */
    public function track()
    {
        $express = Express::getOne($this->express);
        if (!$express) {
            throw new \Exception('快递公司不正确');
        }

        $order = $this->getOrder();
        if ($cache = $this->getCache($order, $express)) {
            return $cache;
        }

        $delivery = $this->formatDelivery($express['id']);
        //构造电子面单提交信息
        $eorder = [];
        $eorder["PayType"] = 1;
        $delivery['customer_account'] && $eorder['PayType'] = 3;
        $delivery['customer_account'] && $eorder['CustomerName'] = $delivery['customer_account'];
        $delivery['customer_pwd'] && $eorder['CustomerPwd'] = $delivery['customer_pwd'];
        $delivery['outlets_code'] && $eorder['SendSite'] = $delivery['outlets_code'];
        $delivery['month_code'] && $eorder['MonthCode'] = $delivery['month_code'];

        $eorder['TemplateSize'] = $delivery['template_size'];
        $eorder['IsSendMessage'] = $delivery['is_sms'];
        $eorder["ShipperCode"] = $express['code'];
        $eorder["ShipperCode"] === 'JD' ? $eorder["ExpType"] = 6 : $eorder["ExpType"] = 1;
        $eorder["IsReturnPrintTemplate"] = 1;
        $eorder["Quantity"] = 1;
        $eorder["OrderCode"] = empty($order->detailExpress) ? $order->order_no : $order->order_no . "-" . count($order->detailExpress);

        $eorder["Sender"] = $this->selectSender($delivery);
        $eorder["Receiver"] = $this->selectReceiver($order);
        $eorder["Commodity"] = $this->selectCommodity($delivery, $order);

        //调用电子面单
        $jsonParam = \Yii::$app->serializer->encode($eorder);
        $jsonResult = \Yii::$app->kdOrder->submitEOrder($jsonParam);

        //解析电子面单返回结果
        $result = \yii\helpers\BaseJson::decode($jsonResult);

        if (isset($result["ResultCode"]) && $result["ResultCode"] == "100" || $result["ResultCode"] == '106') {
            $form = new OrderExpressSingle();
            $form->mall_id = \Yii::$app->mall->id;
            $form->order_id = $order->id;
            $form->ebusiness_id = $result['EBusinessID'];
            $form->order = \Yii::$app->serializer->encode($result['Order']);
            $form->print_teplate = empty($result['PrintTemplate']) ? '' : $result['PrintTemplate'];
            $form->is_delete = 0;
            $form->express_code = $express['code'];
            if (!$form->save()) {
                throw new \Exception($this->getErrorMsg($form));
            }
            return array_merge($result, ['express_single' => $form]);
        } else {
            throw new \DomainException(\yii\helpers\BaseJson::encode($result));
        }
    }

    private function formatDelivery($express_id): array
    {
        $otherWhere = [];
        $this->delivery_account && $otherWhere = ['customer_account' => $this->delivery_account];
        $delivery = Delivery::findOne(array_merge([
            'express_id' => $express_id,
            'is_delete' => 0,
            'mch_id' => $this->mch_id ?: \Yii::$app->user->identity->mch_id,
            'mall_id' => \Yii::$app->mall->id
        ], $otherWhere));

        empty($delivery) && $delivery = CommonOption::get(Option::NAME_DELIVERY_DEFAULT_SENDER, \Yii::$app->mall->id, 'app');

        if (!$delivery) {
            throw new \Exception('请先设置发件人信息');
        }
        return [
            'customer_account' => $delivery['customer_account'] ?? '',
            'customer_pwd' => $delivery['customer_pwd'] ?? '',
            'outlets_code' => $delivery['outlets_code'] ?? '',
            'month_code' => $delivery['month_code'] ?? '',
            'template_size' => $delivery['template_size'] ?? '',
            'is_sms' => $delivery['is_sms'] ?? '',
            'is_goods' => $delivery['is_goods'] ?? 0,
            'goods_alias' => $delivery['goods_alias'] ?? '商品',
            'is_goods_alias' => $delivery['is_goods_alias'] ?? 0,

            'company' => $delivery['company'],
            'name' => $delivery['name'],
            'tel' => $delivery['tel'],
            'mobile' => $delivery['mobile'],
            'zip_code' => $delivery['zip_code'],
            'province' => $delivery['province'],
            'city' => $delivery['city'],
            'district' => $delivery['district'],
            'address' => $delivery['address'],
        ];
    }


    private function selectCommodity(array $delivery, Order $order): array
    {
        $commodity = [];
        if (true || $delivery['is_goods']) {
            foreach ($order->detail as $v) {
                if (!empty($this->order_detail_ids) && !in_array($v['id'], $this->order_detail_ids)) {
                    //排除订单
                    continue;
                }
                {
                    /** 规格名 **/
                    $goods_attr_list = \Yii::$app->serializer->decode($v['goods_info'])['attr_list'];
                    $attr_str = array_map(function ($item) {
                        return $item['attr_group_name'] . ':' . $item['attr_name'] . ';';
                    }, $goods_attr_list);

                    $goods_attr = \Yii::$app->serializer->decode($v['goods_info'])['goods_attr'];

                    $goodsName = $delivery['is_goods_alias'] == 1 ? $delivery['goods_alias'] ?: '商品' : $goods_attr['name'];
                    $goodsName = $goodsName . '（' . trim(join($attr_str), ';') . '）';
                    $text = substr($goodsName, 0, 100);
                    $goodsName === $text || $goodsName = mb_substr($text, 0, mb_strlen($text) - 1);// 乱码
                }

                $commodityOne = [];
                $commodityOne["GoodsName"] = str_replace('+', '', $goodsName);
                $commodityOne["GoodsCode"] = "";
                $commodityOne["Goodsquantity"] = (int)$v->num;
                $commodityOne["GoodsPrice"] = $goods_attr['price'];
                $commodityOne["GoodsWeight"] = floor($goods_attr['weight']) / 1000;
                $commodityOne['GoodsDesc'] = "";
                $commodityOne['GoodsVol'] = "";
                $commodity[] = $commodityOne;
            }
        } else {
            $commodityOne = [];
            $commodityOne["GoodsName"] = '商品';
            $commodityOne["GoodsCode"] = "";
            $commodityOne["Goodsquantity"] = "";
            $commodityOne["GoodsPrice"] = "";
            $commodityOne["GoodsWeight"] = "";
            $commodityOne['GoodsDesc'] = "";
            $commodityOne['GoodsVol'] = "";
            $commodity[] = $commodityOne;
        }
        return $commodity;
    }

    private function getCache($order, $express)
    {
        /** @var MallSetting $mallSetting */
        $mallSetting = MallSetting::find()->where([
            'mall_id' => \Yii::$app->mall->id,
            'is_delete' => 0,
            'key' => 'kdniao_mch_id'
        ])->one();
        if (empty($mallSetting)) {
            return false;
        }

        $expressSingle = OrderExpressSingle::findOne([
            'mall_id' => \Yii::$app->mall->id,
            'ebusiness_id' => $mallSetting->value,
            'order_id' => $order->id,
            'express_code' => $express['code'],
        ]);
        if (!$expressSingle) {
            return false;
        }
        $detailExpress = OrderDetailExpress::findOne([
            'express_single_id' => $expressSingle->id,
            'is_delete' => 0,
            'mall_id' => \Yii::$app->mall->id,
        ]);
        if ($detailExpress) {
            return false;
        }
        return [
            'EBusinessID' => $expressSingle->ebusiness_id,
            'Order' => json_decode($expressSingle->order, true),
            'PrintTemplate' => $expressSingle->print_teplate,
            'express_single' => $expressSingle
        ];
    }

    private function selectSender(array $delivery): array
    {
        return [
            'Company' => $delivery['company'],
            'Name' => $delivery['name'],
            'Tel' => $delivery['tel'],
            'Mobile' => $delivery['mobile'],
            'PostCode' => $delivery['zip_code'],
            'ProvinceName' => $delivery['province'],
            'CityName' => $delivery['city'],
            'ExpAreaName' => $delivery['district'],
            'Address' => $delivery['address'],
        ];
    }

    private function selectReceiver(Order $order): array
    {
        $address_data = explode(' ', $order->address, 4);
        return [
            //'Company' => '',
            //'Tel' => '',
            'Name' => $order->name,
            'Mobile' => $order->mobile,
            'PostCode' => $this->zip_code,
            'ProvinceName' => $address_data[0] ?: '空',
            'CityName' => $address_data[1] ?: '空',
            'ExpAreaName' => $address_data[2] ?: '空',
            'Address' => str_replace(PHP_EOL, '', $address_data[3] ?: $order->address),
        ];
    }

    /**
     * @return Order
     * @throws \Exception
     */
    private function getOrder(): Order
    {
        /** @var Order $order */
        $order = Order::find()->where([
            'id' => $this->order_id,
            'is_delete' => 0,
            'mall_id' => \Yii::$app->mall->id,
            'mch_id' => $this->mch_id ?: \Yii::$app->user->identity->mch_id,
        ])->with('detailExpress')->one();
        if (!$order) {
            throw new \Exception('订单不存在');
        }

        if ($order->status == 0) {
            throw new \Exception('订单进行中,不能进行操作');
        }
        return $order;
    }
}
