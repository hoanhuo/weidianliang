<?php
/**
 * @copyright ©2020 .浙江禾匠信息科技
 * Created by PhpStorm.
 * User: Andy - Wangjie
 * Date: 2020/2/12
 * Time: 9:31
 */

namespace app\plugins\pick;

use app\forms\OrderConfig;
use app\handlers\HandlerBase;
use app\helpers\PluginHelper;
use app\models\Order;
use app\plugins\pick\forms\common\CommonForm;
use app\plugins\pick\forms\common\CommonGoods;
use app\plugins\pick\forms\mall\SettingForm;
use app\plugins\pick\forms\mall\StatisticsForm;
use app\plugins\pick\handlers\HandlerRegister;
use app\plugins\pick\handlers\OrderCreatedEventHandler;
use app\plugins\pick\models\PickSetting;

class Plugin extends \app\plugins\Plugin
{
    public function getMenus()
    {
        return [
            [
                'name' => '设置',
                'icon' => 'el-icon-star-on',
                'route' => 'plugin/pick/mall/setting/index',
            ],
            [
                'name' => 'N元任选活动',
                'icon' => 'el-icon-star-on',
                'route' => 'plugin/pick/mall/activity/index',
                'action' => [
                    [
                        'name' => '添加活动',
                        'route' => 'plugin/pick/mall/activity/edit',
                    ],
                    [
                        'name' => '编辑活动商品',
                        'route' => 'plugin/pick/mall/activity/edit-activity-goods',
                    ],
                ],
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
        return 'pick';
    }

    /**
     * 插件显示名称
     * @return string
     */
    public function getDisplayName()
    {
        return 'N元任选';
    }

    public function getAppConfig()
    {
        $imageBaseUrl = PluginHelper::getPluginBaseAssetsUrl($this->getName()) . '/img';
        return [
            'app_image' => [
                'banner_image' => $imageBaseUrl . '/banner.jpg',
            ],
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

    public function getStatisticsMenus($bool = true)
    {
        return [
            'is_statistics_show' => $bool,
            'name' => $bool ? $this->getDisplayName() : '插件统计',
            'key' => $this->getName(),
            'pic_url' => $this->getStatisticIconUrl(),
            'route' => 'mall/pick-statistics/index',
        ];
    }

    public function getIndexRoute()
    {
        return 'plugin/pick/mall/setting/index';
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
                'key' => 'pick',
                'name' => 'N元任选首页',
                'open_type' => '',
                'icon' => $iconBaseUrl . '/pick.png',
                'value' => '/plugins/pick/index/index',
            ],
        ];
    }

    public function getPickForm()
    {
        $form = new SettingForm();
        return $form;
    }

    public function getHomePage($type)
    {
        if ($type == 'mall') {
            $baseUrl = \Yii::$app->request->hostInfo . \Yii::$app->request->baseUrl;
            $plugin = new Plugin();
            return [
                'list' => [
                    [
                        'key' => $plugin->getName(),
                        'name' => 'N元任选',
                        'relation_id' => 0,
                        'is_edit' => 0,
                    ],
                ],
                'bgUrl' => [
                    $plugin->getName() => [
                        'bg_url' => $baseUrl . '/statics/img/mall/home_block/yushou-bg.png',
                    ],
                ],
                'key' => $plugin->getName(),
            ];
        } elseif ($type == 'api') {
            return CommonForm::getList();
        }
    }

    public function getGoodsData($array)
    {
        return CommonGoods::getCommon()->getDiyGoods($array);
    }

    public function getOrderConfig()
    {
        $setting = PickSetting::getList(\Yii::$app->mall->id);
        $config = new OrderConfig([
            'is_sms' => 1,
            'is_print' => 1,
            'is_mail' => 1,
            'is_share' => $setting['is_share'] ?? 0,
            'support_share' => 1,
        ]);

        return $config;
    }

    public function getOrderInfo($orderId, $order)
    {
        $order = Order::findOne(['id' => $orderId, 'sign' => 'pick']);
        if ($order) {
            $data = [
                'discount_list' => [
                    'pick_ladder_discount' => [
                        'label' => '活动优惠',
                        'value' => bcsub($order->total_goods_original_price, $order->total_goods_price, 2),
                    ],
                ],
                'print_list' => [
                    'pick_ladder_discount' => [
                        'label' => '活动优惠',
                        'value' => bcsub($order->total_goods_original_price, $order->total_goods_price, 2),
                    ],
                ],
            ];
            return $data;
        }
    }

    public function getOrderCreatedHandleClass()
    {
        return new OrderCreatedEventHandler();
    }

    //商品详情路径
    public function getGoodsUrl($item)
    {
        return sprintf("/plugins/pick/detail/detail?goods_id=%u", $item['id']);
    }

    public function getBlackList()
    {
        return [
            'plugin/pick/api/pick-order/order-submit',
        ];
    }
}
