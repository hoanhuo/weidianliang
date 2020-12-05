<?php


namespace app\forms\mall\statistics;


use app\core\response\ApiCode;
use app\forms\api\admin\CashForm;
use app\forms\api\admin\ReviewForm;
use app\forms\common\CommonUser;
use app\forms\mall\export\AllNumStatisticsExport;
use app\forms\mall\export\DataStatisticsExport;
use app\forms\mall\order\OrderForm;
use app\forms\mall\order\OrderRefundListForm;
use app\forms\mall\plugin\PluginListForm;
use app\forms\MenusForm;
use app\models\Goods;
use app\models\MallSetting;
use app\models\Model;
use app\models\Order;
use app\models\OrderDetail;
use app\models\OrderRefund;
use app\models\PaymentOrder;
use app\models\PluginCat;
use app\models\PluginCatRel;
use app\models\ShareSetting;
use app\models\StatisticsDataLog;
use app\models\StatisticsUserLog;
use app\models\Store;
use app\models\User;
use app\models\UserIdentity;
use app\models\UserInfo;
use app\plugins\mch\models\Mch;
use app\plugins\Plugin;
use phpDocumentor\Reflection\Types\This;

class DataForm extends Model
{
    public $date_start;
    public $date_end;
    public $mch_id;

    public $name;

    public $goods_order;
    public $user_order;
    public $sign = 'all';//给默认值，后台统计没有传该值

    public $status;

    public $page;
    public $limit;

    public $flag;
    public $fields;
    public $is_mch_role; // 当前登录是否是商户

    public $mch_per = false;//多商户权限

    public $platform;

    public $is_store;
    public $store_id;

    public $type;


    public function rules()
    {
        return [
            [['mch_id', 'is_store', 'store_id'], 'integer'],
            [['flag', 'goods_order', 'user_order', 'name', 'sign', 'platform', 'type'], 'string'],
            [['page', 'limit'], 'integer'],
            [['page',], 'default', 'value' => 1],
            [['status',], 'default', 'value' => -1],
            [['date_start', 'date_end', 'fields'], 'trim'],
//            [['mch_per',], 'default', 'value' => false],
        ];
    }

    public function validate($attributeNames = null, $clearErrors = true)
    {
        if (\Yii::$app->role->name == 'mch') {
            $this->mch_id = \Yii::$app->mchId;
            $this->is_mch_role = true;
        }
        $permission_arr = \Yii::$app->branch->childPermission(\Yii::$app->mall->user->adminInfo);//直接取商城所属账户权限，对应绑定管理员账户方法修改只给于app_admin权限
        if (!is_array($permission_arr) && $permission_arr) {
            $this->mch_per = true;
        } else {
            foreach ($permission_arr as $value) {
                if ($value == 'mch') {
                    $this->mch_per = true;
                    break;
                }
            }
        }
        return parent::validate($attributeNames, $clearErrors);
    }

    //排行榜
    public function search($type = 0)
    {
        if (!$this->validate()) {
            return $this->getErrorResponse();
        }
        $goods_top_list = [];
        $user_top_list = [];

        if ($type == 1 || $type == 0) {
            //商品排行
            $goods_query = $this->goods_where();
            $goods_query->select("g.`goods_warehouse_id`,COALESCE(SUM(od.`total_price`),0) AS `total_price`,COALESCE(SUM(od.`num`),0) AS `num`")
                ->groupBy('g.goods_warehouse_id');

            if ($this->flag == "EXPORT") {
                $new_query = clone $goods_query;
                $this->export($new_query, $type);
                return false;
            }

            $goods_top_list = $goods_query
                ->limit(10)
                ->asArray()
                ->all();
            $goods_top_list = Order::getGoods_name($goods_top_list);

        }

        if ($type == 2 || $type == 0) {
            //用户排行
            $users_query = $this->users_where();
            $users_query->select("o.user_id,COALESCE(SUM(od.`total_price`),0) AS `total_price`,COALESCE(SUM(od.`num`),0) AS `num`,`i`.`platform`")
                ->groupBy('user_id');


            if ($this->flag == "EXPORT") {
                $new_query = clone $users_query;
                $this->export($new_query, $type);
                return false;
            }

            $user_top_list = $users_query
                ->limit(10)
                ->asArray()
                ->all();
            foreach ($user_top_list as $key => $v) {
                $user_top_list[$key]['nickname'] = $v['user']['nickname'];
                $user_top_list[$key]['avatar'] = $v['user']['userInfo']['avatar'];
                unset($user_top_list[$key]['user']);
            }
        }
        //店铺列表
        $mch_list = [];
        if ($this->mch_per) {
            $list = \Yii::$app->plugin->getList();
            foreach ($list as $value) {
                if ($value['display_name'] == '多商户') {
                    $mch_query = $this->mch_where();
                    $mch_list = $mch_query->select('m.id,s.name')
                        ->asArray()
                        ->all();
                    break;
                }
            }
        }

        return [
            'code' => ApiCode::CODE_SUCCESS,
            'data' => [
                'goods_top_list' => $goods_top_list,
                'user_top_list' => $user_top_list,
                'mch_list' => $mch_list,
                'is_mch_role' => $this->is_mch_role
            ]
        ];
    }

    protected function mch_where()
    {
        $query = Mch::find()->alias('m')->where(['m.is_delete' => 0, 'm.mall_id' => \Yii::$app->mall->id,])
            ->leftJoin(['s' => Store::tableName()], 's.mch_id = m.id')
            ->andWhere(['m.review_status' => 1])->keyword($this->is_mch_role, ['mch_id' => $this->mch_id])
            ->orderBy('s.name');

        //店铺模糊查询
        if ($this->name) {
            $query->andWhere(['like', 's.name', $this->name]);
        }

        return $query;
    }

    //小程序端
    public function all_search()
    {
        if (!$this->validate()) {
            return $this->getErrorResponse();
        }
        $plugins = $this->getPluginSign();
        //插件分类订单list
        $plugins_list = Order::find()->select('sign')
            ->where(['and', ['in', 'sign', $plugins['list']], 'mall_id' => \Yii::$app->mall->id,])
            ->groupBy('sign')->asArray()->all();

        foreach ($plugins_list as $key => $value) {
            $plugin = \Yii::$app->plugin->getPlugin($value['sign']);
            $plugins_list[$key]['name'] = $plugin->getDisplayName();
        }

        //小程序管理端分销商，多商户，订单评论开关状态
        $admin_info = [
            'share' => 0,
            'mch' => 0,
            'comment' => 0,
            'bonus' => 0,
            'stock' => 0,
            'region' => 0
        ];
        $share_info = ShareSetting::findOne(['mall_id' => \Yii::$app->mall->id, 'key' => 'level', 'is_delete' => 0]);
        if (!empty($share_info) && $share_info['value'] >= 1) {
            $admin_info['share'] = 1;
        }
        $mall_info = MallSetting::findOne(['mall_id' => \Yii::$app->mall->id, 'key' => 'is_comment', 'is_delete' => 0]);
        if (!empty($mall_info) && $mall_info['value'] == 1) {
            $admin_info['comment'] = 1;
        }
        $list = \Yii::$app->plugin->getList();
        foreach ($list as $value) {
            if ($this->mch_per) {
                if ($value['display_name'] == '多商户') {
                    $admin_info['mch'] = 1;
                }
            }
            if ($value['display_name'] == '团队分红') {
                $admin_info['bonus'] = 1;
            }
            if ($value['display_name'] == '股东分红') {
                $admin_info['stock'] = 1;
            }
            if ($value['display_name'] == '区域代理') {
                $admin_info['region'] = 1;
            }
        }

        $permissions = \Yii::$app->branch->childPermission(\Yii::$app->mall->user->adminInfo);
        $isScanCodePay = 0;
        if (in_array('scan_code_pay', $permissions)) {
            $isScanCodePay = 1;
        }

        $get_all_data = $this->get_all_data();
        $all_data = array_merge($this->order_data_info(), $this->user_data_info(),
            ['wait_send_num' => $get_all_data['wait_send_num'], 'pro_order' => $get_all_data['pro_order']]);

        return [
            'code' => ApiCode::CODE_SUCCESS,
            'data' => [
                'plugins_list' => $plugins['list_cn'],
                'all_data' => $all_data,
                'new_msg_num' => $this->new_msg_num(),//首页新消息提醒数量
                'admin_info' => $admin_info,
                'is_scan_code_pay' => $isScanCodePay
            ]
        ];
    }

    public function getPluginSign()
    {
        $plugins = [
            'pintuan',
            'booking',
            'bargain',
            'step',
            'advance',
            'integral_mall',
            'miaosha',
            'vip_card',
            'gift',
            'composition',
            'pick',
        ];

        // 判断是否有相应插件权限
        $permissions = \Yii::$app->branch->childPermission(\Yii::$app->mall->user->adminInfo);
        $plugins = array_intersect($plugins, $permissions);

        // 检测是否安装插件
        $newPlugins = [];
        foreach ($plugins as $item) {
            try {
                $exist = \Yii::$app->plugin->getPlugin($item);
                $newPlugins[] = [
                    'sign' => $exist->getName(),
                    'name' => $exist->getDisplayName()
                ];
            } catch (\Exception $exception) {

            }
        }

        // 按插件名称从小到大排序
        usort($newPlugins, function ($item1, $item2) {
            return strlen($item1['name']) > strlen($item2['name']);
        });


        $dataList = [
            [
                'sign' => 'mall',
                'name' => '商城'
            ],
            [
                'sign' => 'all',
                'name' => '全部'
            ]

        ];
        foreach ($dataList as $item) {
            array_unshift($newPlugins, $item);
        }

        return [
            'list' => $plugins,
            'list_cn' => $newPlugins
        ];
    }

    public function head_all()
    {
        if (!$this->validate()) {
            return $this->getErrorResponse();
        }

        return [
            'code' => ApiCode::CODE_SUCCESS,
            'data' => $this->get_all_data()
        ];
    }

    protected function get_all_data()
    {
        $user_query = UserInfo::find()->alias('i')
            ->leftJoin(['u' => User::tableName()], 'u.id = i.user_id');
        //平台标识查询
        if ($this->platform) {
            $user_query->andWhere(['i.platform' => $this->platform]);
        }
        $data_arr['user_count'] = $user_query->andWhere(['u.mch_id' => 0, 'u.is_delete' => 0, 'i.is_delete' => 0, 'u.mall_id' => \Yii::$app->mall->id,])
            ->count();//用户数
        //以下随时间查询改变
        $order_query = Order::find()->alias('o')->where(['o.is_recycle' => 0, 'o.is_delete' => 0, 'o.mall_id' => \Yii::$app->mall->id,])
            ->leftJoin(['i' => UserInfo::tableName()], 'i.user_id = o.user_id')
            ->andWhere(['not', ['o.cancel_status' => 1]]);

        $good_query = Goods::find()->alias('g')->where(['g.mall_id' => \Yii::$app->mall->id, 'is_delete' => 0]);
        //时间查询
//        if ($this->date_start) {
//            $order_query->andWhere(['>=', 'o.created_at', $this->date_start . ' 00:00:00']);
////            $good_query->andWhere(['>=', 'g.created_at', $this->date_start . ' 00:00:00']);
//        }
//
//        if ($this->date_end) {
//            $order_query->andWhere(['<=', 'o.created_at', $this->date_end . ' 23:59:59']);
////            $good_query->andWhere(['<=', 'g.created_at', $this->date_end . ' 23:59:59']);
//        }

        //插件分类查询
        if ($this->sign == 'all') {
        } else if ($this->sign == 'mall') {
            $order_query->andWhere(['o.sign' => '']);
        } else {
            $order_query->andWhere(['o.sign' => $this->sign]);
        }
        if ($this->store_id) {
            $order_query->andWhere(['o.store_id' => $this->store_id]);
        }

        if ($this->mch_id) {
            $order_query->andWhere(['o.mch_id' => $this->mch_id]);
            $good_query->andWhere(['g.mch_id' => $this->mch_id]);
        } else {
            $good_query->andWhere(['g.sign' => '']);
        }
        //平台标识查询
        if ($this->platform) {
            $order_query->andWhere(['i.platform' => $this->platform]);
        }
        $data_arr['goods_num'] = $good_query->count();

//        $all_query = clone $order_query;
//        $data_arr['order_num'] = $all_query->count();
        $pay_query = clone $order_query;
        $data_arr['pay_num'] = $pay_query->andWhere(['or', ['o.is_pay' => 1], ['o.pay_type' => 2]])->count();

//        $price_query = clone $order_query;
//        $data_arr['pay_price'] = $price_query->andWhere(['or', ['o.is_pay' => 1], ['o.pay_type' => 2]])->sum('o.total_pay_price') ?? '0';

        $wait_query = clone $order_query;
        $data_arr['wait_send_num'] = $wait_query->andWhere(['is_send' => 0])
            ->andWhere(['or', ['o.is_pay' => 1], ['o.pay_type' => 2]])
            ->andWhere(['o.cancel_status' => 0, 'o.sale_status' => 0])
            ->count();

        $wait_pay_query = clone $order_query;
        $data_arr['wait_pay_num'] = $wait_pay_query->andWhere(['is_send' => 0, 'is_pay' => 0])
            ->andWhere(['!=', 'pay_type', 2])->count();

        $pro_query = clone $order_query;
        $data_arr['pro_order'] = $pro_query->leftJoin(['or' => OrderRefund::tableName()], 'or.order_id = o.id and or.is_delete = 0')
            ->leftJoin(['od' => OrderDetail::tableName()], 'od.id = or.order_detail_id')
            ->andWhere(['or', ['or.status' => 1], ['or.status' => 2]])->andWhere(['od.refund_status' => 1])
            ->andWhere(['or', ['and', ['or.type' => 2], ['or.is_confirm' => 0]], ['and', ['in', 'or.type', [1, 3]], ['or.is_refund' => 0]]])
            ->count();

        return $data_arr;
    }

    //经营概况
    public function data_search()
    {
        if (!$this->validate()) {
            return $this->getErrorResponse();
        }
        $arr_list = [];

        // 订单数据
        $arr_list['order_data'] = $this->order_data();

        // 用户数据
        $arr_list['user_data'] = $this->user_data();

        // 支付数据
        $arr_list['pay_data'] = $this->pay_data();
        if ($this->flag == 'EXPORT') {
            $exp = new AllNumStatisticsExport();
            $exp->type = json_decode($this->type, true);
            $exp->export($arr_list);
            return false;
        }
        return [
            'code' => ApiCode::CODE_SUCCESS,
            'data' => $arr_list
        ];
    }

    public function table_search()
    {
        if (!$this->validate()) {
            return $this->getErrorResponse();
        }
        if ($this->type == 1) {
            $query = $this->table_where();
            //天的数据需要按小时分组
            if (empty($this->date_start) || $this->date_start == $this->date_end) {
                $query->select("DATE_FORMAT(`o`.`created_at`, '%H') AS `time`,COUNT(DISTINCT `o`.`user_id`) AS `user_num`,
  COUNT(DATE_FORMAT(`o`.`created_at`, '%Y-%m-%d')) AS `order_num`,SUM(`o`.`total_pay_price`) AS `total_pay_price`,SUM(`d`.`num`) AS `goods_num`");
            } else {
                $query->select("DATE_FORMAT(`o`.`created_at`, '%Y-%m-%d') AS `time`,COUNT(DISTINCT `o`.`user_id`) AS `user_num`,
  COUNT(DATE_FORMAT(`o`.`created_at`, '%Y-%m-%d')) AS `order_num`,SUM(`o`.`total_pay_price`) AS `total_pay_price`,SUM(`d`.`num`) AS `goods_num`");
            }

            //店铺查询
            if ($this->store_id) {
                $query->andWhere(['o.store_id' => $this->store_id]);
            }
            if ($this->mch_id) {
                $query->andWhere(['o.mch_id' => $this->mch_id]);
            }

            //时间查询，默认查询昨天
            if ($this->date_start) {
                $query->andWhere(['>=', 'o.created_at', $this->date_start . ' 00:00:00']);
            } else {
                $query->andWhere(['>=', 'o.created_at', date("Y-m-d", strtotime("-1 day")) . ' 00:00:00']);
            }
            if ($this->date_end) {
                $query->andWhere(['<=', 'o.created_at', $this->date_end . ' 23:59:59']);
            } else {
                $query->andWhere(['<=', 'o.created_at', date("Y-m-d", strtotime("-1 day")) . ' 23:59:59']);
            }

            $list = $query->groupBy('time')
                ->orderBy('time')
                ->asArray()
                ->all();
            $table_data = [
                'user_num' => 0,
                'order_num' => 0,
                'total_pay_price' => 0,
                'goods_num' => 0
            ];
            foreach ($list as $value) {
                $table_data['user_num'] += $value['user_num'];
                $table_data['order_num'] += $value['order_num'];
                $table_data['total_pay_price'] = bcadd($table_data['total_pay_price'], $value['total_pay_price'], 2);
                $table_data['goods_num'] += $value['goods_num'];
            }


            if (empty($this->date_start) || $this->date_start == $this->date_end) {
                $list = $this->hour_24($list);
            } else {
                $day = floor((strtotime($this->date_end) - strtotime($this->date_start)) / 86400) + 1;
                $list = $this->day_data($list, $day);
            }
        }
        if ($this->type == 2) {
            $query = $this->pay_where();
            //天的数据需要按小时分组
            if (empty($this->date_start) || $this->date_start == $this->date_end) {
                $query->select("DATE_FORMAT(`o`.`created_at`, '%H') AS `time`,
                sum(case po.pay_type when 1 then po.amount end) as wx_amount,
                sum(case po.pay_type when 2 then po.amount end) as huodao_amount,
                sum(case po.pay_type when 3 then po.amount end) as balance_amount,
                sum(case po.pay_type when 4 then po.amount end) as ali_amount,
                sum(case po.pay_type when 1 then po.refund end) as wx_refund,
                sum(case po.pay_type when 2 then po.refund end) as huodao_refund,
                sum(case po.pay_type when 3 then po.refund end) as balance_refund,
                sum(case po.pay_type when 4 then po.refund end) as ali_refund");
            } else {
                $query->select("DATE_FORMAT(`o`.`created_at`, '%Y-%m-%d') AS `time`,
                sum(case po.pay_type when 1 then po.amount end) as wx_amount,
                sum(case po.pay_type when 2 then po.amount end) as huodao_amount,
                sum(case po.pay_type when 3 then po.amount end) as balance_amount,
                sum(case po.pay_type when 4 then po.amount end) as ali_amount,
                sum(case po.pay_type when 1 then po.refund end) as wx_refund,
                sum(case po.pay_type when 2 then po.refund end) as huodao_refund,
                sum(case po.pay_type when 3 then po.refund end) as balance_refund,
                sum(case po.pay_type when 4 then po.refund end) as ali_refund");
            }

            //时间查询，默认查询昨天
            if ($this->date_start) {
                $query->andWhere(['>=', 'o.created_at', $this->date_start . ' 00:00:00']);
            } else {
                $query->andWhere(['>=', 'o.created_at', date("Y-m-d", strtotime("-1 day")) . ' 00:00:00']);
            }
            if ($this->date_end) {
                $query->andWhere(['<=', 'o.created_at', $this->date_end . ' 23:59:59']);
            } else {
                $query->andWhere(['<=', 'o.created_at', date("Y-m-d", strtotime("-1 day")) . ' 23:59:59']);
            }

            $list = $query->andWhere(['<=', 'po.pay_type', 4])
                ->groupBy('time')
                ->orderBy('time')
                ->asArray()
                ->all();

            $table_data = [
                'wx_amount' => 0,
                'huodao_amount' => 0,
                'balance_amount' => 0,
                'ali_amount' => 0,
            ];
            foreach ($list as &$value) {
                $table_data['wx_amount'] = bcsub(bcadd($table_data['wx_amount'], $value['wx_amount'], 2), $value['wx_refund'], 2);
                $table_data['huodao_amount'] = bcsub(bcadd($table_data['huodao_amount'], $value['huodao_amount'], 2), $value['huodao_refund'], 2);
                $table_data['balance_amount'] = bcsub(bcadd($table_data['balance_amount'], $value['balance_amount'], 2), $value['balance_refund'], 2);
                $table_data['ali_amount'] = bcsub(bcadd($table_data['ali_amount'], $value['ali_amount'], 2), $value['ali_refund'], 2);
                $value['wx_amount'] = bcsub($value['wx_amount'], $value['wx_refund'], 2);
                $value['huodao_amount'] = bcsub($value['huodao_amount'], $value['huodao_refund'], 2);
                $value['balance_amount'] = bcsub($value['balance_amount'], $value['balance_refund'], 2);
                $value['ali_amount'] = bcsub($value['ali_amount'], $value['ali_refund'], 2);
            }

            if (empty($this->date_start) || $this->date_start == $this->date_end) {
                $list = $this->pay_hour_24($list);
            } else {
                $day = floor((strtotime($this->date_end) - strtotime($this->date_start)) / 86400) + 1;
                $list = $this->pay_day_data($list, $day);
            }
        }
        return [
            'code' => ApiCode::CODE_SUCCESS,
            'data' => [
                'list' => $list,
                'table_data' => $table_data
            ]
        ];
    }

    protected function table_where()
    {
//        $orderQuery = OrderDetail::find()->alias('od')->where(['is_delete' => 0])
//            ->select(['od.order_id', 'SUM(`od`.`num`) num'])->groupBy('od.order_id');

        $query = Order::find()->alias('o')
            ->where(['o.is_recycle' => 0, 'o.is_pay' => 1, 'o.mall_id' => \Yii::$app->mall->id,])
            ->andWhere(['o.is_delete' => 0])->andWhere(['not', ['o.cancel_status' => 1]])
//            ->leftJoin(['d' => $orderQuery], 'd.order_id = o.id')
            ->leftJoin(['d' => OrderDetail::tableName()], 'd.order_id = o.id')
            ->leftJoin(['i' => UserInfo::tableName()], 'i.user_id = o.user_id');
        //店铺查询
        if ($this->store_id) {
            $query->andWhere(['o.store_id' => $this->store_id]);
        }
        if ($this->mch_id) {
            $query->andWhere(['o.mch_id' => $this->mch_id]);
        }

        //插件分类查询
        if ($this->sign == 'all') {
        } else if ($this->sign == 'mall') {
            $query->andWhere(['o.sign' => '']);
        } else {
            $query->andWhere(['o.sign' => $this->sign]);
        }

        return $query;
    }

    protected function user_log_where()
    {
        $query = StatisticsUserLog::find()->where(['mall_id' => \Yii::$app->mall->id, 'is_delete' => 0]);
        return $query;
    }

    protected function data_log_where()
    {
        $query = StatisticsDataLog::find()->where(['mall_id' => \Yii::$app->mall->id, 'is_delete' => 0, 'key' => 'visits']);
        return $query;
    }

    protected function goods_where()
    {
        $query = Order::find()->alias('o')
            ->where(['g.mall_id' => \Yii::$app->mall->id, 'o.is_recycle' => 0, 'o.is_delete' => 0])->andWhere(['not', ['o.cancel_status' => 1]])
            ->leftJoin(['od' => OrderDetail::tableName()], 'od.order_id = o.id and od.is_refund = 0')//过滤退款
            ->leftJoin(['g' => Goods::tableName()], 'g.id = od.goods_id');

        //店铺查询
        if ($this->store_id) {
            $query->andWhere(['g.store_id' => $this->store_id]);
        }
        if ($this->mch_id) {
            $query->andWhere(['g.mch_id' => $this->mch_id]);
        }

        //时间查询
        if ($this->date_start) {
            $query->andWhere(['>=', 'od.created_at', $this->date_start . ' 00:00:00']);
        }

        if ($this->date_end) {
            $query->andWhere(['<=', 'od.created_at', $this->date_end . ' 23:59:59']);
        }

        //排序
        $query->orderBy((!empty($this->goods_order) ? $this->goods_order : 'total_price DESC') . ',g.goods_warehouse_id');

        return $query;
    }

    protected function users_where()
    {
        $query = Order::find()->alias('o')
            ->where(['o.mall_id' => \Yii::$app->mall->id, 'o.is_recycle' => 0, 'o.is_delete' => 0, 'is_pay' => 1])->andWhere(['not', ['o.cancel_status' => 1]])
            ->rightJoin(['od' => OrderDetail::tableName()], 'od.order_id = o.id')
            ->with('user.userInfo')
//            ->leftJoin(['u' => User::tableName()], 'o.user_id = u.id')
            ->leftJoin(['i' => UserInfo::tableName()], 'i.user_id = o.user_id')
            ->andWhere(['od.is_delete' => 0]);

        //店铺查询
        if ($this->store_id) {
            $query->andWhere(['o.store_id' => $this->store_id]);
        }
        if ($this->mch_id) {
            $query->andWhere(['o.mch_id' => $this->mch_id]);
        }

        //时间查询
        if ($this->date_start) {
            $query->andWhere(['>=', 'od.created_at', $this->date_start . ' 00:00:00']);
        }

        if ($this->date_end) {
            $query->andWhere(['<=', 'od.created_at', $this->date_end . ' 23:59:59']);
        }
        //平台标识查询
        if ($this->platform) {
            $query->andWhere(['i.platform' => $this->platform]);
        }

        //排序
        $query->orderBy((!empty($this->user_order) ? $this->user_order : 'total_price DESC') . ',o.user_id');

        return $query;
    }

    protected function export($query, $type)
    {
        $exp = new DataStatisticsExport();
        $exp->type = $type;
        $exp->export($query);
    }

    protected function hour_24($list)
    {
        for ($i = 0; $i < 24; $i++) {
            $bool = false;
            foreach ($list as $item) {
                if ($i == intval($item['time'])) {
                    $bool = true;
                    $arr[$i]['created_at'] = intval($item['time']);
                    $arr[$i]['user_num'] = $item['user_num'];
                    $arr[$i]['order_num'] = $item['order_num'];
                    $arr[$i]['total_pay_price'] = $item['total_pay_price'];
                    $arr[$i]['goods_num'] = $item['goods_num'];
                }
            }
            if (!$bool) {
                $arr[$i]['created_at'] = $i;
                $arr[$i]['user_num'] = '0';
                $arr[$i]['order_num'] = '0';
                $arr[$i]['total_pay_price'] = '0.00';
                $arr[$i]['goods_num'] = '0';
            }
        }

        return $arr;
    }

    protected function day_data($list, $day)
    {
        for ($i = 0; $i < $day; $i++) {
            $date = date('Y-m-d', strtotime("-$i day"));
            $bool = false;
            foreach ($list as $item) {
                if ($date == $item['time']) {
                    $bool = true;
                    $arr[$i]['created_at'] = $item['time'];
                    $arr[$i]['user_num'] = $item['user_num'];
                    $arr[$i]['order_num'] = $item['order_num'];
                    $arr[$i]['total_pay_price'] = $item['total_pay_price'];
                    $arr[$i]['goods_num'] = $item['goods_num'];
                }
            }
            if (!$bool) {
                $arr[$i]['created_at'] = $date;
                $arr[$i]['user_num'] = '0';
                $arr[$i]['order_num'] = '0';
                $arr[$i]['total_pay_price'] = '0.00';
                $arr[$i]['goods_num'] = '0';
            }
        }
        return !empty($arr) ? array_reverse($arr) : [];
    }

    protected function pay_hour_24($list)
    {
        for ($i = 0; $i < 24; $i++) {
            $bool = false;
            foreach ($list as $item) {
                if ($i == intval($item['time'])) {
                    $bool = true;
                    $arr[$i]['created_at'] = intval($item['time']);
                    $arr[$i]['wx_amount'] = $item['wx_amount'];
                    $arr[$i]['huodao_amount'] = $item['huodao_amount'];
                    $arr[$i]['balance_amount'] = $item['balance_amount'];
                    $arr[$i]['ali_amount'] = $item['ali_amount'];
                }
            }
            if (!$bool) {
                $arr[$i]['created_at'] = $i;
                $arr[$i]['wx_amount'] = '0.00';
                $arr[$i]['huodao_amount'] = '0.00';
                $arr[$i]['balance_amount'] = '0.00';
                $arr[$i]['ali_amount'] = '0.00';
            }
        }

        return $arr;
    }

    protected function pay_day_data($list, $day)
    {
        for ($i = 0; $i < $day; $i++) {
            $date = date('Y-m-d', strtotime("-$i day"));
            $bool = false;
            foreach ($list as $item) {
                if ($date == $item['time']) {
                    $bool = true;
                    $arr[$i]['created_at'] = $item['time'];
                    $arr[$i]['wx_amount'] = $item['wx_amount'];
                    $arr[$i]['huodao_amount'] = $item['huodao_amount'];
                    $arr[$i]['balance_amount'] = $item['balance_amount'];
                    $arr[$i]['ali_amount'] = $item['ali_amount'];
                }
            }
            if (!$bool) {
                $arr[$i]['created_at'] = $date;
                $arr[$i]['wx_amount'] = '0.00';
                $arr[$i]['huodao_amount'] = '0.00';
                $arr[$i]['balance_amount'] = '0.00';
                $arr[$i]['ali_amount'] = '0.00';
            }
        }
        return !empty($arr) ? array_reverse($arr) : [];
    }

    protected function new_msg_num()
    {
        $num_arr = $this->new_order_msg();
        $cash = new CashForm(['mch_per' => $this->mch_per]);
        $cash_num = $cash->getCount();
        $review = new ReviewForm(['mch_per' => $this->mch_per]);
        $review_num = $review->getCount();
        $data = [
            'order_num' => $num_arr['all_num'] ? $num_arr['all_num'] : 0,//订单提醒
            'cash_num' => !empty($cash_num) ? $cash_num : 0,
            'review_num' => !empty($review_num) ? $review_num : 0,
        ];

        return $data;
    }

    protected function new_order_msg()
    {
        $order = new OrderForm();
        $order->status = 8;//下单提醒数量
        $put_num = $order->search_num();
        $order->status = 4;//退款提醒数量
        $cancel_num = $order->search_num();
        $order_refund = new OrderRefundListForm();
        $order_refund->status = 0;
        $refund_num = $order_refund->search_num();

        return [
            'put_num' => $put_num ? $put_num : 0,
            'cancel_num' => $cancel_num ? $cancel_num : 0,
            'refund_num' => $refund_num ? $refund_num : 0,
            'all_num' => ($put_num + $cancel_num + $refund_num) ? ($put_num + $cancel_num + $refund_num) : 0
        ];
    }

    public function menus()
    {
        if (!$this->validate()) {
            return $this->getErrorResponse();
        }
        $plugins = \Yii::$app->plugin->list;
        $statisticsMenus = [];
        foreach ($plugins as $plugin) {
            $pluginClass = 'app\\plugins\\' . $plugin->name . '\\Plugin';
            /** @var Plugin $object */
            if (!class_exists($pluginClass)) {
                continue;
            }
            $object = new $pluginClass();
            if (method_exists($object, 'getStatisticsMenus')) {
                $arr = $object->getStatisticsMenus();
                if (count($arr) > 0) {
                    // TODO 判断children 为了兼容
                    if (isset($arr['children'])) {
                        foreach ($arr['children'] as $child) {
                            $child['key'] = $object->getName();
                            array_push($statisticsMenus, $child);
                        }
                    } else {
                        if (count($arr) == count($arr, 1)) {
                            array_push($statisticsMenus, $arr);
                        } else {
                            foreach ($arr as $aItem) {
                                array_push($statisticsMenus, $aItem);
                            }
                        }
                    }
                }
            }
        }
        $userPermissions = [];
        $userIdentity = CommonUser::getUserIdentity();
        // 获取员工账号 权限路由
        if ($userIdentity->is_operator == 1) {
            $userPermissions = CommonUser::getUserPermissions();
        }
        // 多商户
        if (\Yii::$app->user->identity->mch_id) {
            $userPermissions = CommonUser::getMchPermissions();
        }
        $statisticsMenus = (new MenusForm())->checkMenus($statisticsMenus, $userPermissions);
        $statisticsMenus = (new MenusForm())->deleteMenus($statisticsMenus);

        //重新按插件中心排序
        $plugin_list = (new PluginListForm())->search()['data']['cats'];
        $plugin_arr = [];
        foreach ($plugin_list as $plugins) {
            if (isset($plugins['plugins'])) {
                foreach ($plugins['plugins'] as $plugin) {
                    array_push($plugin_arr, $plugin);
                }
            }
        }
        $arr_menus = [];
        foreach ($plugin_arr as $item) {
            foreach ($statisticsMenus as $value) {
                if ($item['name'] == $value['key']) {
                    array_push($arr_menus, $value);
                }
            }
        }
        $statisticsMenus = $arr_menus;

        $commonMenus = [
            [
                'name' => '添加商品',
                'route' => 'mall/goods/edit',
                'pic_url' => 'statics/img/mall/statistic/function_icon_add.png'
            ],
            [
                'name' => '店铺装修',
                'route' => 'mall/home-page/setting',
                'pic_url' => 'statics/img/mall/statistic/function_icon_Decoration_.png'
            ],
            [
                'name' => '订单管理',
                'route' => 'mall/order/index',
                'pic_url' => 'statics/img/mall/statistic/function_icon_order.png'
            ],
            [
                'name' => '插件中心',
                'route' => 'mall/plugin/index',
                'pic_url' => 'statics/img/mall/statistic/function_icon_Plugin.png'
            ],
            [
                'name' => '优惠券',
                'route' => 'mall/coupon/index',
                'pic_url' => 'statics/img/mall/statistic/function_coupon_icon.png'
            ],
        ];

        try {
            $diy_plugin = \Yii::$app->plugin->getPlugin('diy');
            if ($diy_plugin->getHasPage()) {
                foreach ($commonMenus as &$commonMenu) {
                    if ($commonMenu['name'] == '店铺装修') {
                        $commonMenu['route'] = "plugin/diy/mall/template/edit";
                        $commonMenu['params'] = ['has_home' => 1];
                        break;
                    }
                }
            }
        } catch (\Exception $exception) {
        }
        $commonMenus = (new MenusForm())->checkMenus($commonMenus, $userPermissions);
        $commonMenus = (new MenusForm())->deleteMenus($commonMenus);

        return [
            'code' => ApiCode::CODE_SUCCESS,
            'menus' => $statisticsMenus,
            'common_menus' => $commonMenus
        ];
    }

    protected function pay_where()
    {
        $query = Order::find()->alias('o')->where(['o.mall_id' => \Yii::$app->mall->id, 'o.is_delete' => 0, 'o.is_pay' => 1, 'o.is_recycle' => 0])
            ->innerJoin(['po' => PaymentOrder::tableName()], 'po.order_no = o.order_no and po.is_pay = 1');
        //店铺查询
        if ($this->store_id) {
            $query->andWhere(['o.store_id' => $this->store_id]);
        }
        if ($this->mch_id) {
            $query->andWhere(['o.mch_id' => $this->mch_id]);
        }
        return $query;
    }

    protected function order_data()
    {
        $order_query = $this->table_where();

        $order_query->select("COALESCE(COUNT(DISTINCT `o`.`user_id`),0) AS `user_num`,COALESCE(COUNT(`o`.`id`),0) AS `order_num`,
        COALESCE(SUM(`o`.`total_pay_price`),0) AS `total_pay_price`,COALESCE(SUM(`d`.`num`),0) AS `goods_num`");
        $list_1 = [];
        if (strtotime($this->date_end . ' 23:59:59') - strtotime($this->date_start . ' 00:00:00') <= 604800
            && $this->date_start == date('Y-m-d')) {//判断时间小于一周
            $order_query_1 = clone $order_query;
            if (strtotime($this->date_end . ' 23:59:59') - strtotime($this->date_start . ' 00:00:00') >= 604799) {//7天
                $order_query_1->andWhere(['>', 'o.created_at', $this->date_end . ' 23:59:59']);
                $order_query_1->andWhere(['<=', 'o.created_at', date('Y-m-d', strtotime('-2 week')) . ' 23:59:59']);
            } elseif ($this->date_start == $this->date_end && $this->date_end == date('Y-m-d')) {//今日
                $order_query_1->andWhere(['>=', 'o.created_at', date('Y-m-d', strtotime('-1 day')) . ' 00:00:00']);
                $order_query_1->andWhere(['<=', 'o.created_at', date('Y-m-d', strtotime('-1 day')) . ' 23:59:59']);
            } elseif ($this->date_start == $this->date_end && $this->date_end == date('Y-m-d', strtotime('-1 day'))) {//昨日
                $order_query_1->andWhere(['>=', 'o.created_at', date('Y-m-d', strtotime('-8 day')) . ' 00:00:00']);
                $order_query_1->andWhere(['<=', 'o.created_at', date('Y-m-d', strtotime('-8 day')) . ' 23:59:59']);
            }
            $list_1 = $order_query_1->asArray()->one();
        }

        //时间查询
        if ($this->date_start) {
            $order_query->andWhere(['>=', 'o.created_at', $this->date_start . ' 00:00:00']);
        }
        if ($this->date_end) {
            $order_query->andWhere(['<=', 'o.created_at', $this->date_end . ' 23:59:59']);
        }
        $list = $order_query
            ->asArray()
            ->one();
        $list['user_num_status'] = empty($list_1) || $list['user_num'] > $list_1['user_num'] ? 'up' : ($list['user_num'] < $list_1['user_num'] ? 'down' : 'equal');
        $list['order_num_status'] = empty($list_1) || $list['order_num'] > $list_1['order_num'] ? 'up' : ($list['order_num'] < $list_1['order_num'] ? 'down' : 'equal');
        $list['total_pay_price_status'] = empty($list_1) || $list['total_pay_price'] > $list_1['total_pay_price'] ? 'up' : ($list['total_pay_price'] < $list_1['total_pay_price'] ? 'down' : 'equal');
        $list['goods_num_status'] = empty($list_1) || $list['goods_num'] > $list_1['goods_num'] ? 'up' : ($list['goods_num'] < $list_1['goods_num'] ? 'down' : 'equal');

        return $list;
    }

    protected function user_data()
    {
        $user_query = $this->user_log_where();
        $data_query = $this->data_log_where();
        $data = [];
        $data_1 = [];
        if (strtotime($this->date_end . ' 23:59:59') - strtotime($this->date_start . ' 00:00:00') <= 604800
            && $this->date_start == date('Y-m-d')) {//判断时间小于一周
            $user_query_1 = clone $user_query;
            $data_query_1 = clone $data_query;
            if (strtotime($this->date_end . ' 23:59:59') - strtotime($this->date_start . ' 00:00:00') >= 604799) {//7天
                $user_query_1->andWhere(['>', 'created_at', $this->date_end . ' 23:59:59']);
                $user_query_1->andWhere(['<=', 'created_at', date('Y-m-d', strtotime('-2 week')) . ' 23:59:59']);

                $data_query_1->andWhere(['>', 'created_at', $this->date_end . ' 23:59:59']);
                $data_query_1->andWhere(['<=', 'created_at', date('Y-m-d', strtotime('-2 week')) . ' 23:59:59']);
            } elseif ($this->date_start == $this->date_end && $this->date_end == date('Y-m-d')) {//今日
                $user_query_1->andWhere(['>=', 'created_at', date('Y-m-d', strtotime('-1 day')) . ' 00:00:00']);
                $user_query_1->andWhere(['<=', 'created_at', date('Y-m-d', strtotime('-1 day')) . ' 23:59:59']);

                $data_query_1->andWhere(['>=', 'created_at', date('Y-m-d', strtotime('-1 day')) . ' 00:00:00']);
                $data_query_1->andWhere(['<=', 'created_at', date('Y-m-d', strtotime('-1 day')) . ' 23:59:59']);
            } elseif ($this->date_start == $this->date_end && $this->date_end == date('Y-m-d', strtotime('-1 day'))) {//昨日
                $user_query_1->andWhere(['>=', 'created_at', date('Y-m-d', strtotime('-8 day')) . ' 00:00:00']);
                $user_query_1->andWhere(['<=', 'created_at', date('Y-m-d', strtotime('-8 day')) . ' 23:59:59']);

                $data_query_1->andWhere(['>=', 'created_at', date('Y-m-d', strtotime('-8 day')) . ' 00:00:00']);
                $data_query_1->andWhere(['<=', 'created_at', date('Y-m-d', strtotime('-8 day')) . ' 23:59:59']);
            }
            $data_1['user_num'] = $user_query_1->groupBy('user_id')->count();
            $data_1['data_num'] = $data_query_1->sum('value') ?? '0';
        }

        if ($this->date_start) {
            $user_query->andWhere(['>=', 'created_at', $this->date_start . ' 00:00:00']);
            $data_query->andWhere(['>=', 'created_at', $this->date_start . ' 00:00:00']);
        }
        if ($this->date_end) {
            $user_query->andWhere(['<=', 'created_at', $this->date_end . ' 23:59:59']);
            $data_query->andWhere(['<=', 'created_at', $this->date_end . ' 23:59:59']);
        }
        $data['user_num'] = $user_query->groupBy('user_id')->count();
        $data['data_num'] = $data_query->sum('value') ?? '0';

        $data['user_num_status'] = empty($data_1) || $data['user_num'] > $data_1['user_num'] ? 'up' : ($data['user_num'] < $data_1['user_num'] ? 'down' : 'equal');
        $data['data_num_status'] = empty($data_1) || $data['data_num'] > $data_1['data_num'] ? 'up' : ($data['data_num'] < $data_1['data_num'] ? 'down' : 'equal');

        return $data;
    }

    protected function pay_data()
    {
        $pay_query = $this->pay_where();
        $data_1 = [];
        if (strtotime($this->date_end . ' 23:59:59') - strtotime($this->date_start . ' 00:00:00') <= 604800
            && $this->date_start == date('Y-m-d')) {//判断时间小于一周
            $pay_query_1 = clone $pay_query;
            if (strtotime($this->date_end . ' 23:59:59') - strtotime($this->date_start . ' 00:00:00') >= 604799) {//7天
                $pay_query_1->andWhere(['>', 'po.created_at', $this->date_end . ' 23:59:59']);
                $pay_query_1->andWhere(['<=', 'po.created_at', date('Y-m-d', strtotime('-2 week')) . ' 23:59:59']);
            } elseif ($this->date_start == $this->date_end && $this->date_end == date('Y-m-d')) {//今日
                $pay_query_1->andWhere(['>=', 'po.created_at', date('Y-m-d', strtotime('-1 day')) . ' 00:00:00']);
                $pay_query_1->andWhere(['<=', 'po.created_at', date('Y-m-d', strtotime('-1 day')) . ' 23:59:59']);
            } elseif ($this->date_start == $this->date_end && $this->date_end == date('Y-m-d', strtotime('-1 day'))) {//昨日
                $pay_query_1->andWhere(['>=', 'po.created_at', date('Y-m-d', strtotime('-8 day')) . ' 00:00:00']);
                $pay_query_1->andWhere(['<=', 'po.created_at', date('Y-m-d', strtotime('-8 day')) . ' 23:59:59']);
            }
            $data_1 = $pay_query_1->select('po.pay_type,sum(po.amount-po.refund) as amount,sum(po.refund) as refund')->groupBy('po.pay_type')->asArray()->all();
        }

        if ($this->date_start) {
            $pay_query->andWhere(['>=', 'po.created_at', $this->date_start . ' 00:00:00']);
        }
        if ($this->date_end) {
            $pay_query->andWhere(['<=', 'po.created_at', $this->date_end . ' 23:59:59']);
        }

        $data = $pay_query->select('po.pay_type,sum(po.amount-po.refund) as amount,sum(po.refund) as refund')->groupBy('po.pay_type')->asArray()->all();

        $list = [];
        $data = $this->list_4($data);
        $data_1 = $this->list_4($data_1);
        foreach ($data as &$datum) {
            foreach ($data_1 as $datum_1) {
                if ($datum['pay_type'] == $datum_1['pay_type']) {
                    $list[$datum['pay_type']]['amount_status'] = $datum['amount'] > $datum_1['amount'] ? 'up' : ($datum['amount'] < $datum_1['amount'] ? 'down' : 'equal');
                    $list[$datum['pay_type']]['amount'] = $datum['amount'];
                    $list[$datum['pay_type']]['refund'] = $datum['refund'];
                }
            }
        }

        return $list;
    }


    protected function list_4($list)
    {
        for ($i = 1; $i < 5; $i++) {
            $bool = false;
            foreach ($list as $item) {
                if ($i == intval($item['pay_type'])) {
                    $bool = true;
                    $arr[$i]['pay_type'] = $item['pay_type'];
                    $arr[$i]['amount'] = $item['amount'];
                    $arr[$i]['refund'] = $item['refund'];
                }
            }
            if (!$bool) {
                $arr[$i]['pay_type'] = $i;
                $arr[$i]['amount'] = '0';
                $arr[$i]['refund'] = '0';
            }
        }

        return $arr;
    }

    protected function order_data_info()
    {
        $order_query = $this->table_where();

        $order_query->select("COALESCE(COUNT(DISTINCT `o`.`user_id`),0) AS `user_num`,COALESCE(COUNT(`o`.`id`),0) AS `order_num`,
        COALESCE(SUM(`o`.`total_pay_price`),0) AS `total_pay_price`,COALESCE(SUM(`d`.`num`),0) AS `goods_num`");

        //时间查询
        if ($this->date_start) {
            $order_query->andWhere(['>=', 'o.created_at', $this->date_start . ' 00:00:00']);
        }
        if ($this->date_end) {
            $order_query->andWhere(['<=', 'o.created_at', $this->date_end . ' 23:59:59']);
        }
        $list = $order_query
            ->asArray()
            ->one();

        return $list;
    }

    private function user_data_info()
    {
        $user_query = $this->user_log_where();
        $data_query = $this->data_log_where();

        if ($this->date_start) {
            $user_query->andWhere(['>=', 'created_at', $this->date_start . ' 00:00:00']);
            $data_query->andWhere(['>=', 'created_at', $this->date_start . ' 00:00:00']);
        }
        if ($this->date_end) {
            $user_query->andWhere(['<=', 'created_at', $this->date_end . ' 23:59:59']);
            $data_query->andWhere(['<=', 'created_at', $this->date_end . ' 23:59:59']);
        }
        $data['user_num'] = $user_query->groupBy('user_id')->count();
        $data['data_num'] = $data_query->sum('value') ?? '0';

        return $data;
    }
}
