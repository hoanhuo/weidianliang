<?php
/**
 * Created by PhpStorm.
 * User: 风哀伤
 * Date: 2019/5/11
 * Time: 11:30
 * @copyright: ©2019 .浙江禾匠信息科技
 * @link: http://www.zjhejiang.com
 */

namespace app\plugins\advance\models;

/**
 * @property AdvanceGoodsAttr $attr
 */
class GoodsAttr extends \app\models\GoodsAttr
{
    public function getAttr()
    {
        return $this->hasOne(AdvanceGoodsAttr::className(), ['goods_attr_id' => 'id'])->where(['is_delete' => 0]);
    }
}
