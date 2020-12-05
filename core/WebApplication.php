<?php

/**
 * @copyright ©2018 .浙江禾匠信息科技
 * @author Lu Wei
 * @link http://www.zjhejiang.com/
 * Created by IntelliJ IDEA
 * Date Time: 2018/10/30 12:00
 */

namespace app\core;


use app\core\cloud\CloudBase;
use app\core\cloud\CloudException;
use yii\web\ForbiddenHttpException;

/***
 * Class Application
 * @package app\core
 */
class WebApplication extends \yii\web\Application
{
    use Application;

    public $classVersion = '4.2.10';

    private $appIsRunning = true;

    /**
     * Application constructor.
     * @param null $config
     * @throws \yii\base\InvalidConfigException
     * @throws \Exception
     */
    public function __construct($config = null)
    {
        $this->setInitParams()
            ->loadDotEnv()
            ->defineConstants();

        require __DIR__ . '/../vendor/yiisoft/yii2/Yii.php';

        if (!$config) {
            $config = require __DIR__ . '/../config/web.php';
        }

        parent::__construct($config);

        $this->enableObjectResponse()
            ->enableErrorReporting()
            //->checkAuth('ip')
            ->loadAppLogger()
            ->loadAppHandler()
            ->loadPluginsHandler();
    }

    private function checkAuth($type)
    {
        $checkPrefixList = ['admin', 'mall', 'install'];
        $route = \Yii::$app->request->get('r');
        $routePathList = [];
        if ($route) {
            $route = trim(mb_strtolower(urldecode($route)), '/');
            $routePathList = explode('/', $route);
        }
        $inList = false;
        foreach ($checkPrefixList as $checkPrefix) {
            if (count($routePathList) && in_array($checkPrefix, $routePathList)) {
                $inList = true;
                break;
            }
        }
        if (!$inList) {
            return $this;
        }
        if ($type === 'ip') {
            $cacheKey = md5('CHECK_IP_AUTH_CACHE_' . \Yii::$app->request->hostName);
            $cloudApi = '/mall/site/check-ip';
        } else {
            $cacheKey = md5('CHECK_DOMAIN_AUTH_CACHE_' . \Yii::$app->request->hostName);
            $cloudApi = '/mall/site/check-domain';
        }
        $result = \Yii::$app->cache->get($cacheKey);
        if (!$result) {
            try {
                $cloudBase = new CloudBase();
                $result = $cloudBase->httpGet($cloudApi);
                \Yii::$app->cache->set($cacheKey, $result, 60 * 60);
            } catch (CloudException $exception) {
                $result = $exception->raw;
                \Yii::$app->cache->set($cacheKey, $result, 10);
            }
        }
        if (isset($result['code']) && $result['code'] !== 0) {
            $msg = isset($result['msg']) ? $result['msg'] : '检查服务器授权出错。';
            throw new ForbiddenHttpException($msg);
        }
        return $this;
    }

    public function setSessionMallId($id)
    {
        if (!is_numeric($id)) {
            return;
        }
        $key1 = md5('Mall_Id_Key_1_' . date('Ym'));
        $key2 = md5('Mall_Id_Key_2_' . date('Ym'));
        $value1 = base64_encode(\Yii::$app->security->encryptByPassword($id, 'key' . $key1));
        $value2 = base64_encode(\Yii::$app->security->encryptByPassword('0' . $id, 'key' . $key1));
        $this->getSession()->set($key1, $value1);
        $this->getSession()->set($key2, $value2);
    }

    public function getSessionMallId($defaultValue = null)
    {
        $key1 = md5('Mall_Id_Key_1_' . date('Ym'));
        $encodeDataBase64 = $this->getSession()->get($key1, null);
        if ($encodeDataBase64 === null) {
            return $defaultValue;
        }
        $encodeData = base64_decode($encodeDataBase64);
        if (!$encodeData) {
            return $defaultValue;
        }
        $value = \Yii::$app->security->decryptByPassword($encodeData, 'key' . $key1);
        if (!$value) {
            return $defaultValue;
        }
        return $value;
    }

    public function removeSessionMallId()
    {
        $key1 = md5('Mall_Id_Key_1_' . date('Ym'));
        $key2 = md5('Mall_Id_Key_2_' . date('Ym'));
        \Yii::$app->session->remove($key1);
        \Yii::$app->session->remove($key2);
    }

    public function setDb($db)
    {
        $this->db = $db;
    }

    public function validateCloudFile()
    {
        $cloud = $this->getCloud();
        $classList = [
            $cloud,
            $cloud->base,
            $cloud->auth,
            $cloud->collect,
            $cloud->plugin,
            $cloud->update,
            $cloud->wxapp,
        ];
        foreach ($classList as $class) {
            if (!property_exists($class, 'classVersion') || $this->classVersion !== $class->classVersion) {
                throw new \Exception('系统文件错误。');
            }
        }
    }

    public function 。()
    {
        return true;
    }
}
