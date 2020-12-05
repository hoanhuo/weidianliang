<?php
/**
 * @copyright ©2018 .浙江禾匠信息科技
 * @author Lu Wei
 * @link http://www.zjhejiang.com/
 * Created by IntelliJ IDEA
 * Date Time: 2018/12/5 12:09
 */


namespace app\plugins\wxapp\models;


use app\forms\api\LoginUserInfo;
use app\models\UserInfo;
use app\plugins\wxapp\Plugin;

class LoginForm extends \app\forms\api\LoginForm
{
    /**
     * @return LoginUserInfo
     * @throws \Exception
     */
    public function getUserInfo()
    {
        /** @var Plugin $plugin */
        $plugin = new Plugin();
        $postData = \Yii::$app->request->post();
        $rawData = $postData['rawData'];
        $postUserInfo = json_decode($rawData, true);
        $data = $plugin->getWechat()->decryptData(
            $postData['encryptedData'],
            $postData['iv'],
            $postData['code']
        );
        $userInfo = new LoginUserInfo();
        $userInfo->username = $data['openId'];
        $userInfo->nickname = $data['nickName'] ? $data['nickName'] : $postUserInfo['nickName'];
        $userInfo->avatar = $data['avatarUrl'] ? $data['avatarUrl'] : $postUserInfo['avatarUrl'];
        $userInfo->platform_user_id = $data['openId'];
        $userInfo->platform = UserInfo::PLATFORM_WXAPP;
        return $userInfo;
    }
}
