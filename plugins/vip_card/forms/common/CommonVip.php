<?php
/**
 * Created by PhpStorm.
 * User: 风哀伤
 * Date: 2019/11/6
 * Time: 14:25
 * @copyright: ©2019 .浙江禾匠信息科技
 * @link: http://www.zjhejiang.com
 */

namespace app\plugins\vip_card\forms\common;

use app\forms\mall\vip_card\VipCardForm;
use app\models\GoodsCatRelation;
use app\models\Mall;
use app\models\Model;
use app\plugins\vip_card\models\VipCard;
use app\plugins\vip_card\models\VipCardAppointGoods;
use app\plugins\vip_card\models\VipCardUser;
use app\plugins\vip_card\Plugin;

/**
 * Class CommonVip
 * @package app\plugins\vip_card\forms\common
 * @property Mall $mall
 */
class CommonVip extends Model
{
    private static $instance;
    public $mall;

    public static function getCommon($mall = null)
    {
        if (!self::$instance instanceof self) {
            self::$instance = new self();
        }
        if (!$mall) {
            $mall = \Yii::$app->mall;
        }
        self::$instance->mall = $mall;
        return self::$instance;
    }

    private static $setting;
    private static $card;
    private static $vipUser;
    private static $user;
    private static $userInfo;
    private static $catsGoodsWarehouseIds;
    private static $appoints;

    /**
     * 单例模式获取主卡信息
     * @param int $id 主卡id
     * @return array|\yii\db\ActiveRecord|null
     */
    public function getMainCard($id = 0)
    {
        if (self::$card) {
            return self::$card;
        }
        self::$card = VipCard::find()
            ->where([
                'mall_id' => \Yii::$app->mall->id,
                'is_delete' => 0
            ])
            ->keyword($id, ['id' => $id])
            ->limit(1)
            ->one();
        return self::$card;
    }

    /**
     * 是否未超级会员卡用户
     * @param $user
     * @return int[]
     */
    public function getUserInfo($user)
    {
        if (self::$userInfo) {
            $user = self::$userInfo;
        } else {
            $user = VipCardUser::find()
                ->where(['mall_id' => \Yii::$app->mall->id, 'user_id' => $user->id, 'is_delete' => 0])
                ->one();
            if ($user) {
                self::$userInfo = $user;
            } else {
                self::$userInfo = true;
            }
        }

        return [
            'is_vip_card_user' => isset($user->id) ? 1 : 0
        ];
    }

    private function getCats($cats)
    {
        if (!isset(self::$catsGoodsWarehouseIds)) {
            self::$catsGoodsWarehouseIds = GoodsCatRelation::find()->where([
                'cat_id' => $cats,
                'is_delete' => 0,
            ])->select('goods_warehouse_id')->column();
        }
        return self::$catsGoodsWarehouseIds;
    }

    public function getAppoints()
    {
        if (!isset(self::$appoints)) {
            self::$appoints = VipCardAppointGoods::find()
                ->select(['goods_id'])
                ->column();
        }
        return self::$appoints;
    }

    public function getAppoint($goods)
    {
        $my = 0;
        $discount = null;
        $isVipCardUser = 0;
        if (self::$user) {
            $user = self::$user;
        } else {
            $user = \Yii::$app->user->identity;
            self::$user = $user;
        }
        if ($user) {
            $res = $this->getUserInfo($user);
            if ($res['is_vip_card_user'] == 1) {
                $isVipCardUser = 1;
            }
        }
        if (self::$setting) {
            $setting = self::$setting;
        } else {
            $setting = (new Plugin())->getRules();
            self::$setting = $setting;
        }
        $appoint = $this->getAppoints();
        $isAppoint = in_array($goods['id'], $appoint) ? 1 : 0;
        if (empty(\Yii::$app->user->id)) {
            $vipCardUser = null;
        } else {
            if (isset(self::$vipUser)) {
                $vipCardUser = self::$vipUser;
            } else {
                $vipCardUser = VipCardUser::find()->where(['user_id' => \Yii::$app->user->id, 'is_delete' => 0])->one();
                if ($vipCardUser) {
                    self::$vipUser = $vipCardUser;
                } else {
                    self::$vipUser = [];
                }
            }
        }

        if (self::$card) {
            $card = self::$card;
        } else {
            $card = self::getMainCard();
            self::$card = $card;
        }
        if (!$card) {
            return [
                'discount' => $discount,
                'is_my_vip_card_goods' => $my,
                'is_vip_card_user' => $isVipCardUser,
            ];
        }

        //todo 逻辑优化
        if (!empty($vipCardUser)) {
            $type = json_decode($vipCardUser->image_type_info, true);
            if ($type['all'] == true) {
                if ($goods['sign'] == '') {
                    if ($isAppoint && $vipCardUser) {
                        $my = 1;
                        $discount = $vipCardUser->image_discount;
                    }
                } elseif (in_array($goods['sign'], $setting['rules'])) {
                    if ($isAppoint && $vipCardUser) {
                        $my = 1;
                        $discount = $vipCardUser->image_discount;
                    }
                }
            } else {
                if (
                    isset($type['goods']) && !empty($type['goods'])
                    && in_array($goods['goods_warehouse_id'], $type['goods'])
                ) {
                    if ($goods['sign'] == '') {
                        if ($isAppoint && $vipCardUser) {
                            $my = 1;
                            $discount = $vipCardUser->image_discount;
                        }
                    } elseif (in_array($goods['sign'], $setting['rules'])) {
                        if ($isAppoint && $vipCardUser) {
                            $my = 1;
                            $discount = $vipCardUser->image_discount;
                        }
                    }
                }

                if (isset($type['cats']) && !empty($type['cats'])) {
                    $goodsWarehouseIds = $this->getCats($type['cats']);
                    $isInCats = in_array($goods['goods_warehouse_id'], $goodsWarehouseIds) ? true : false;
                    if ($isInCats) {
                        if ($goods['sign'] == '') {
                            if ($isAppoint && $vipCardUser) {
                                $my = 1;
                                $discount = $vipCardUser->image_discount;
                            }
                        } elseif (in_array($goods['sign'], $setting['rules'])) {
                            if ($isAppoint && $vipCardUser) {
                                $my = 1;
                                $discount = $vipCardUser->image_discount;
                            }
                        }
                    }
                }
            }
        } else {
            $type = json_decode($card->type_info, true);

            if (isset($type) && is_array($type) && !empty($type)) {
                if ($type['all'] == true) {
                    if ($isAppoint) {
                        $discount = $card->discount;
                    }
                } else {
                    if (!empty($type['goods']) && in_array($goods['goods_warehouse_id'], $type['goods'])) {
                        if ($goods['sign'] == '') {
                            if ($isAppoint) {
                                $discount = $card->discount;
                            }
                        } elseif (in_array($goods['sign'], $setting['rules'])) {
                            if ($isAppoint) {
                                $discount = $card->discount;
                            }
                        }
                    }

                    if (isset($type['cats']) && !empty($type['cats'])) {
                        $goodsWarehouseIds = $this->getCats($type['cats']);
                        $isInCats = in_array($goods['goods_warehouse_id'], $goodsWarehouseIds) ? true : false;
                        if ($isInCats) {
                            if ($goods['sign'] == '') {
                                if ($isAppoint) {
                                    $discount = $card->discount;
                                }
                            } elseif (in_array($goods['sign'], $setting['rules'])) {
                                if ($isAppoint) {
                                    $discount = $card->discount;
                                }
                            }
                        }
                    }
                }
            }
        }

        return [
            'discount' => $discount,
            'is_my_vip_card_goods' => $my,
            'is_vip_card_user' => $isVipCardUser,
        ];
    }

    public function getGoodsConfig()
    {
        return VipCardForm::check();
    }
}
