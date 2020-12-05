<?php
/**
 * @copyright ©2019 .浙江禾匠信息科技
 * Created by PhpStorm.
 * User: Andy - Wangjie
 * Date: 2020/3/24
 * Time: 10:32
 */

namespace app\forms\mall\finance;

class FinanceFactory
{
    public function create($operate)
    {
        $permission = \Yii::$app->branch->childPermission(\Yii::$app->mall->user->adminInfo);
        if (!in_array($operate, $permission)) {
            throw new \Exception('无' . $operate . '权限');
        }
        if ($operate == 'share') {
            $class = 'app\\forms\\mall\\share\\CashApplyForm';
        } else {
            $class = "app\\plugins\\{$operate}\\forms\\mall\\CashApplyForm";
        }
        if (!class_exists($class)) {
            throw new \Exception($operate . '操作失败');
        }
        $result = new $class();
        return $result;
    }
}
