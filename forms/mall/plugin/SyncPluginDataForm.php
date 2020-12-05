<?php


namespace app\forms\mall\plugin;


use app\core\response\ApiCode;
use app\models\CorePlugin;
use app\models\Form;
use app\models\PluginCat;
use app\models\PluginCatRel;

class SyncPluginDataForm extends Form
{
    public function sync()
    {
        $time = 3600;
        $cacheKey = 'PLUGIN_LAST_SYNC_TIME';
        $lastSyncTime = \Yii::$app->cache->get($cacheKey);
        if ($lastSyncTime && (time() - $lastSyncTime) < $time) {
            return [
                'code' => ApiCode::CODE_SUCCESS,
                'msg' => 'synced recently, do nothing.',
            ];
        }
        $data = \Yii::$app->cloud->plugin->getPluginData();
        $cats = $data['cats'];
        $relations = $data['relations'];
        $plugins = $data['plugins'];
        foreach ($plugins as $item) {
            $model = CorePlugin::find()->where([
                'name' => $item['name'],
            ])->one();
            if (!$model) {
                $model = new CorePlugin();
                $model->name = $item['name'];
                $model->is_delete = 1;
            }
            $model->display_name = $item['display_name'];
            $model->pic_url = $item['new_icon'] ? $item['new_icon'] : $item['pic_url'];
            $model->desc = $item['desc'];
            $model->save();
        }
        foreach ($cats as $item) {
            $exists = PluginCat::find()->where([
                'name' => $item['name'],
            ])->exists();
            if ($exists) continue;
            $model = new PluginCat();
            $model->attributes = [
                'name' => $item['name'],
                'display_name' => $item['display_name'],
                'sort' => $item['sort'],
                'icon' => $item['icon'],
                'color' => $item['color'],
            ];
            $model->save();
        }
        foreach ($relations as $item) {
            $exists = PluginCatRel::find()->where([
                'plugin_name' => $item['plugin_name'],
            ])->exists();
            if ($exists) continue;
            $model = new PluginCatRel();
            $model->attributes = [
                'plugin_name' => $item['plugin_name'],
                'plugin_cat_name' => $item['plugin_cat_name'],
            ];
            $model->save();
        }
        \Yii::$app->cache->set($cacheKey, time(), $time);
        return [
            'code' => ApiCode::CODE_SUCCESS,
            'msg' => 'sync ok.',
        ];
    }
}
