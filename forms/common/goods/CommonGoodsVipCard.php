<?php
/**
 * @copyright ©2020 .浙江禾匠信息科技
 * @link: http://www.zjhejiang.com
 * Created by PhpStorm.
 * User: Andy - Wangjie
 * Date: 2020/5/15
 * Time: 15:06
 */

namespace app\forms\common\goods;

use app\models\Goods;
use yii\base\BaseObject;

class CommonGoodsVipCard extends BaseObject
{
    private static $instance;
    private $goods;
    private $permission;
    private $plugin;

    public static function getInstance()
    {
        if (!self::$instance instanceof self) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function init()
    {
        $this->permission = $this->getPermission();
        $this->plugin = $this->getPlugin();
    }

    private function getPermission()
    {
        $permission = \Yii::$app->branch->childPermission(\Yii::$app->mall->user->adminInfo);
        if (!in_array('vip_card', $permission)) {
            return false;
        }
        return $permission;
    }

    private function getPlugin()
    {
        try {
            $plugin = \Yii::$app->plugin->getPlugin('vip_card');
        } catch (\Exception $e) {
            $plugin = false;
        }
        return $plugin;
    }

    /**
     * @param Goods $goods
     */
    public function setGoods($goods)
    {
        $this->goods = $goods;
        return $this;
    }

    /**
     * 获取小程序前端超级会员卡商品信息
     * @return array
     */
    public function getAppoint()
    {
        if ($this->plugin !== false && $this->permission !== false) {
            return $this->plugin->getAppoint($this->goods);
        }
        return [
            'discount' => 0,
            'is_my_vip_card_goods' => null,
            'is_vip_card_user' => 0
        ];
    }

    /**
     * @param $orderId
     * @return mixed
     */
    public function getOrderInfo($orderId, $order)
    {
        if ($this->plugin !== false && $this->permission !== false) {
            $res = $this->plugin->getOrderInfo($orderId, $order);
        }
        if (!isset($res['discount_list'])) {
            $res =  [
                'discount_list' => []
            ];
        }
        return $res;
    }
}
