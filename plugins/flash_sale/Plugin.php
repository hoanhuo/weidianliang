<?php
/**
 * @copyright ©2020 .浙江禾匠信息科技
 * @link: http://www.zjhejiang.com
 * Created by PhpStorm.
 * User: Andy - Wangjie
 * Date: 2020/4/30
 * Time: 15:09
 */

namespace app\plugins\flash_sale;

use app\forms\api\order\OrderException;
use app\forms\OrderConfig;
use app\forms\PickLinkForm;
use app\handlers\HandlerBase;
use app\helpers\PluginHelper;
use app\models\OrderDetail;
use app\plugins\flash_sale\forms\api\IndexForm;
use app\plugins\flash_sale\forms\common\CommonGoods;
use app\plugins\flash_sale\forms\common\CommonSetting;
use app\plugins\flash_sale\forms\mall\StatisticsForm;
use app\plugins\flash_sale\handlers\HandlerRegister;
use app\plugins\flash_sale\models\FlashSaleActivity;
use app\plugins\flash_sale\models\FlashSaleGoods;
use app\plugins\flash_sale\models\FlashSaleGoodsAttr;
use app\plugins\flash_sale\models\FlashSaleOrderDiscount;
use app\plugins\flash_sale\models\Goods;
use Yii;
use yii\db\ActiveRecord;

class Plugin extends \app\plugins\Plugin
{
    private $pluginSetting;

    public function getMenus()
    {
        return [
            [
                'name' => '设置',
                'icon' => 'el-icon-star-on',
                'route' => 'plugin/flash_sale/mall/setting',
            ],
            [
                'name' => '限时抢购',
                'icon' => 'el-icon-star-on',
                'route' => 'plugin/flash_sale/mall/activity/index',
                'action' => [
                    [
                        'name' => '添加活动',
                        'route' => 'plugin/flash_sale/mall/activity/edit',
                    ],
                    [
                        'name' => '编辑活动商品',
                        'route' => 'plugin/flash_sale/mall/activity/edit-activity-goods',
                    ]
                ]
            ],
            $this->getStatisticsMenus(false)
        ];
    }

    public function handler()
    {
        $register = new HandlerRegister();
        $HandlerClasses = $register->getHandlers();
        foreach ($HandlerClasses as $HandlerClass) {
            $handler = new $HandlerClass();
            if ($handler instanceof HandlerBase) {
                /** @var HandlerBase $handler */
                $handler->register();
            }
        }
        return $this;
    }

    /**
     * 插件唯一id，小写英文开头，仅限小写英文、数字、下划线
     * @return string
     */
    public function getName()
    {
        return 'flash_sale';
    }

    /**
     * 插件显示名称
     * @return string
     */
    public function getDisplayName()
    {
        return '限时抢购';
    }

    //商品详情路径
    public function getGoodsUrl($item)
    {
        return sprintf("/plugins/flash_sale/goods/goods?id=%u", $item['id']);
    }

    public function getAppConfig()
    {
        $imageBaseUrl = PluginHelper::getPluginBaseAssetsUrl($this->getName()) . '/img';
        return [

        ];
    }

    public function getIndexRoute()
    {
        return 'plugin/flash_sale/mall/setting';
    }

    /**
     * 插件小程序端链接
     * @return array
     */
    public function getPickLink()
    {
        $iconBaseUrl = PluginHelper::getPluginBaseAssetsUrl($this->getName()) . '/img/pick-link';

        return [
            [
                'key' => 'flash_sale',
                'name' => '限时抢购',
                'open_type' => '',
                'icon' => $iconBaseUrl . '/icon-flash-sale.png',
                'value' => '/plugins/flash_sale/index/index'
            ],
            [
                'key' => 'flash_sale',
                'name' => '限时抢购详情',
                'open_type' => '',
                'icon' => $iconBaseUrl . '/icon-flash-sale.png',
                'value' => '/plugins/flash_sale/goods/goods',
                'params' => [
                    [
                        'key' => 'id',
                        'value' => '',
                        'desc' => '请填写限时抢购商品ID',
                        'is_required' => true,
                        'data_type' => 'number',
                        'page_url' => 'plugin/flash_sale/mall/activity/index',
                        'pic_url' => $iconBaseUrl . '/example_image/goods-id.png',
                        'page_url_text' => '活动商品列表',
                    ],
                ],
                'ignore' => [PickLinkForm::IGNORE_TITLE, PickLinkForm::IGNORE_NAVIGATE],
            ],
        ];
    }

    public function getOrderConfig()
    {
        $setting = (new CommonSetting())->search();
        return new OrderConfig(
            [
                'is_sms' => $setting['is_sms'] ?? 1,
                'is_print' => $setting['is_print'] ?? 1,
                'is_mail' => $setting['is_mail'] ?? 1,
                'is_share' => $setting['is_share'] ?? 0,
                'support_share' => 1,
                'is_member_price' => $setting['is_member_price'] ?? 1
            ]
        );
    }

    public function getGoodsData($array)
    {
        return CommonGoods::getDiyGoods($array);
    }

    public function getHomePage($type)
    {
        if ($type == 'mall') {
            $baseUrl = Yii::$app->request->hostInfo . Yii::$app->request->baseUrl;
            $plugin = new Plugin();
            return [
                'list' => [
                    [
                        'key' => $plugin->getName(),
                        'name' => '限时抢购',
                        'relation_id' => 0,
                        'is_edit' => 0
                    ]
                ],
                'bgUrl' => [
                    $plugin->getName() => [
                        'bg_url' => $baseUrl . '/statics/img/mall/home_block/yushou-bg.png',
                    ]
                ],
                'key' => $plugin->getName()
            ];
        } elseif ($type == 'api') {
            return CommonGoods::getList('', '', 4);
        }
    }

    public function getStatisticsMenus($bool = true)
    {
        return [
            'is_statistics_show' => $bool,
            'name' => $bool ? $this->getDisplayName() : '插件统计',
            'key' => $this->getName(),
            'pic_url' => $this->getStatisticIconUrl(),
            'route' => 'mall/flash-sale-statistics/index',
        ];
    }

    /**
     * 返回实例化后台统计数据接口
     * @return StatisticsForm
     */
    public function getApi()
    {
        return new StatisticsForm();
    }

    public function getEnableVipDiscount()
    {
        $setting = (new CommonSetting())->search();
        return $setting['svip_status'] == 0 ? false : true;
    }

    /**
     * @param Goods $goods
     * @return array|ActiveRecord[]
     */
    public function getGoodsDetailExtra($goods)
    {
        return CommonGoods::getFlashSaleGoods($goods->goods_warehouse_id);
    }

    public function getCartList()
    {
        $form = new IndexForm();
        $res = $form->getCartList();
        return $res;
    }

    public function getPluginSetting()
    {
        if ($this->pluginSetting !== null) {
            return $this->pluginSetting;
        }
        $this->pluginSetting = (new CommonSetting())->search();
        return $this->pluginSetting;
    }

    public function isGoodsEnableMemberPrice($goodsItem)
    {
        $pluginSetting = $this->getPluginSetting();
        return $pluginSetting['is_member_price'] ? true : false;
    }

    public function isGoodsEnableVipPrice($goodsItem)
    {
        $pluginSetting = $this->getPluginSetting();
        return $pluginSetting['svip_status'] ? true : false;
    }

    public function isGoodsEnableIntegral($goodsItem)
    {
        $pluginSetting = $this->getPluginSetting();
        return $pluginSetting['is_integral'] ? true : false;
    }

    public function isGoodsEnableCoupon($goodsItem)
    {
        $pluginSetting = $this->getPluginSetting();
        return $pluginSetting['is_coupon'] ? true : false;
    }

    public function checkGoods($goods, $item)
    {
        if ($goods->sign != (new Plugin())->getName()) {
            return parent::checkGoods($goods, $item);
        }

        $flashGoods = FlashSaleGoods::findOne(
            [
                'goods_id' => $goods->id,
                'is_delete' => 0,
            ]
        );
        if (!$flashGoods) {
            throw new OrderException('商品不存在');
        }

        $activity = FlashSaleActivity::find()->where(
            [
                'status' => 1,
                'mall_id' => Yii::$app->mall->id,
                'id' => $flashGoods->activity_id,
                'is_delete' => 0,
            ]
        )->one();
        if (!$activity) {
            throw new OrderException('当前活动已结束');
        }

        if ($activity->start_at > mysql_timestamp()) {
            throw new OrderException('限时抢购活动尚未开始');
        }

        if ($activity->end_at < mysql_timestamp()) {
            throw new OrderException('当前活动已结束');
        }

        $buyCount = OrderDetail::find()->where(
            [
                'goods_id' => $flashGoods->goods_id,
            ]
        )->joinWith(
            [
                'order' => function ($query) {
                    $query->andWhere(
                        [
                            'user_id' => Yii::$app->user->id,
                            'is_pay' => 1
                        ]
                    );
                }
            ]
        )->groupBy('order_id')->count();

        if ($goods->confine_order_count != -1 && $buyCount >= $goods->confine_order_count) {
            throw new OrderException('超出购买限单(' . $goods->confine_order_count . ')次数');
        }
    }

    /**
     * 限时购买商品优惠
     * @param $goodsItem
     * @return mixed
     */
    public function pluginDiscount($goodsItem)
    {
        $flashSaleGoodsAttr = FlashSaleGoodsAttr::findOne(['goods_attr_id' => $goodsItem['goods_attr']->id]);
        if (empty($flashSaleGoodsAttr) || !isset($flashSaleGoodsAttr->type)) {
            throw new OrderException('商品异常');
        }
        $goodsItem['flash_sale_discount'] = price_format(0);
        if ($flashSaleGoodsAttr->type == 1) {
            $flashSubPrice = $goodsItem['total_price'] * (1 - $flashSaleGoodsAttr->discount / 10);
        } elseif ($flashSaleGoodsAttr->type == 2) {
            $flashSubPrice = $flashSaleGoodsAttr->cut * $goodsItem['num'];
        } else {
            throw new OrderException('优惠类型错误');
        }
        if ($flashSubPrice != 0) {
            // 减去限时抢购优惠金额
            $flashSubPrice = min($goodsItem['total_price'], $flashSubPrice);
            $goodsItem['total_price'] = price_format($goodsItem['total_price'] - $flashSubPrice);
            $goodsItem['flash_sale_discount'] = price_format($flashSubPrice);
        }
        return $goodsItem;
    }

    /**
     * 限时购买商户订单优惠信息
     * @param $mchItem
     * @return mixed
     */
    public function pluginDiscountData($mchItem)
    {
        $goodsId = '';
        $totalFlashSalePrice = 0;
        $totalSubPrice = 0; // 限时抢购总计优惠金额
        $mchItem['flash_sale_discount'] = price_format(0);
        foreach ($mchItem['goods_list'] as &$goodsItem) {
            if (isset($goodsItem['sign']) && in_array($goodsItem['sign'], ['flash_sale'])) {
                $totalFlashSalePrice += $goodsItem['total_price'];
                $goodsId = $goodsItem['id'];
                $totalSubPrice += $goodsItem['flash_sale_discount'];
                $mchItem['total_goods_price'] = price_format(
                    $mchItem['total_goods_price'] - $goodsItem['flash_sale_discount']
                );
            }
        }

        $mchItem['insert_rows'][] = [
            'title' => '限时抢购优惠',
            'value' => '-￥' . $totalSubPrice,
            'data' => price_format(-$totalSubPrice),
        ];

        $activity = FlashSaleActivity::find()
            ->alias('fsa')
            ->joinWith(
                [
                    'flashSaleGoods fsg' => function ($query) use ($goodsId) {
                        $query->where(['fsg.goods_id' => $goodsId, 'fsg.is_delete' => 0]);
                    }
                ]
            )
            ->one();

        if (!$activity) {
            throw new OrderException('活动不存在');
        }

        if ($totalSubPrice) {
            $mchItem['flash_sale_discount'] = price_format($totalSubPrice);
        }
        return $mchItem;
    }

    public function getOrderInfo($orderId, $order)
    {
        $flash = FlashSaleOrderDiscount::findOne(['order_id' => $orderId, 'is_delete' => 0]);

        if ($flash) {
            $data = [
                'discount_list' => [
                    'flash_sale_discount' => [
                        'label' => '限时抢购优惠',
                        'value' => $flash->discount
                    ]
                ],
                'print_list' => [
                    'flash_sale_discount' => [
                        'label' => '限时抢购优惠',
                        'value' => $flash->discount
                    ]
                ]
            ];
            return $data;
        }
    }

    public function isGoodsEnableAddressLimit($goodsItem)
    {
        $pluginSetting = $this->getPluginSetting();
        return $pluginSetting['is_territorial_limitation'] ? true : false;
    }
}
