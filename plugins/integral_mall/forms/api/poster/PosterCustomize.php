<?php
/**
 * link: http://www.zjhejiang.com/
 * copyright: Copyright (c) 2020 .浙江禾匠信息科技有限公司
 * author: xay
 */

namespace app\plugins\integral_mall\forms\api\poster;

use app\forms\api\poster\common\BaseConst;
use app\forms\api\poster\common\CommonFunc;
use app\helpers\PluginHelper;
use app\models\Model;
use app\plugins\integral_mall\Plugin;

class PosterCustomize extends Model implements BaseConst
{
    use CommonFunc;

    public function traitQrcode($class)
    {
        return [
            ['goods_id' => $class->goods->id, 'user_id' => \Yii::$app->user->id],
            240,
            'plugins/integral_mall/goods/goods'
        ];
    }

    public function traitPrice($model, $left, $top, $has_center, $color)
    {
        $font_path = \Yii::$app->basePath . '/web/statics/font/st-heiti-light.ttc';

        $integral_mall = $model->other->integral_num . '积分+';
        $price = $model->other->goods->price;

        $text = $this->setText($integral_mall, $left, $top + 13, 30, $color);
        $t = imagettfbbox($text['font'], 0, $font_path, $text['text']);
        $t_width = $t[2] - $t[0];

        $mark_width = 28;

        $mark = $this->setText('￥', 0, $top + 10, 32, $color);

        $price = $this->setText($price, 0, $top, 52, $color);

        if ($has_center) {
            $left = 0;
            $g = imagettfbbox($price['font'], 0, $font_path, $price['text']);
            $g_width = $g[2] - $g[0];
            $left = (750 - $g_width - $t_width - $mark_width) / 2;
        }

        $text['left'] = $left;
        $mark['left'] = $left + $t_width + 3;
        $price['left'] = $left + $t_width + 3 + $mark_width;
        return [
            $text,
            $mark,
            $price,
        ];

    }

    public function traitMultiMapContent()
    {
        $plugin = new Plugin();
        $twoImageUrl = PluginHelper::getPluginBaseAssetsUrl($plugin->getName()) . '/img/poster-style-two.png';
        $image_url = PluginHelper::getPluginBaseAssetsUrl($plugin->getName()) . '/img/poster-bg.png';

        $image = [
            'file_type' => self::TYPE_IMAGE,
            'bottom' => 0,
            'left' => 0,
            'height' => 174,
            'width' => "100%",
            'image_url' => $image_url,
            'two_image_url' => $twoImageUrl,
            'two_image_height' => 223
        ];
        return [
            $image,
        ];
    }
}