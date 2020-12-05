<?php
/**
 * Created by PhpStorm.
 * User: 风哀伤
 * Date: 2020/3/13
 * Time: 14:06
 * @copyright: ©2019 .浙江禾匠信息科技
 * @link: http://www.zjhejiang.com
 */

namespace app\plugins\ecard\forms\mall;


use app\core\CsvExport;
use app\models\Ecard;
use yii\helpers\Json;

/**
 * Class ExportForm
 * @package app\plugins\ecard\forms\mall
 * @property Ecard $ecard
 */
class ExportForm extends CsvExport
{
    public $ecard;
    public $ecardOptions = [];

    public function getFileName()
    {
        return '卡密模板--' . $this->ecard->name;
    }

    public function exportData()
    {
        $headList = Json::decode($this->ecard->list, true);
        if (!empty($this->ecardOptions)) {
            $headList[] = '状态';
        }
        $this->exportToExcel($this->ecardOptions, $headList, $this->getFileName());
    }

    public function exportToExcel($data, $headList, $fileName)
    {
        $php_excel = new \PHPExcel();
        $properties = $php_excel->getProperties();
        $properties->setTitle($fileName);
        $php_excel->setActiveSheetIndex(0);
        $active_sheet = $php_excel->getActiveSheet();
        $active_sheet->setTitle($fileName);
        $char = $this->getChar();
        foreach ($headList as $index => $item) {
            $count = 0;
            $title = $this->getCell($char, $index);
            $active_sheet->setCellValue($title . 1, $item);
            while ($count < count($data)) {
                $active_sheet->setCellValue($title . (2 + $count), $data[$count][$index]);
                $count++;
            }
        }
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . $fileName . '--' . mysql_timestamp() . '.xls"');
        header('Cache-Control: max-age=0');
        $writer = \PHPExcel_IOFactory::createWriter($php_excel, 'Excel5');
        $writer->save('php://output');
        exit();
    }

    public function getChar()
    {
        $char = [];
        for ($i = 65; $i < 91; $i++) {
            $char[] = strtoupper(chr($i));
        }
        return $char;
    }

    public function getCell($char, $index)
    {
        $n = intval($index / 26);
        $m = $index % 26;
        $string = $char[$m];
        if ($n >= 1) {
            $string = $this->getCell($char, $n - 1) . $string;
        }
        return $string;
    }

    public function getErrorFileName()
    {
        return '卡密模板--' . $this->ecard->name . '--未导入数据' . mysql_timestamp();
    }

    public function exportError()
    {
        $headList = Json::decode($this->ecard->list, true);
        $fileName = urlencode($this->getErrorFileName());
        $fileName = $fileName . '.csv';
        $newFilePath = \Yii::$app->basePath . '/web/temp/';
        if (!is_dir($newFilePath)) {
            mkdir($newFilePath, 0777, true);
        }
        $fp = fopen($newFilePath . $fileName, 'a+');
        try {
            //输出Excel列名信息
            foreach ($headList as $key => $value) {
                //CSV的Excel支持GBK编码，一定要转换，否则乱码
                $headList[$key] = iconv('utf-8', 'gbk', $value);
            }

            //将数据通过fputcsv写到文件句柄
            fputcsv($fp, $headList);

            //计数器
            $num = 0;
            //每隔$limit行，刷新一下输出buffer，不要太大，也不要太小
            $limit = 100000;
            $i = 0;
            while ($i < count($this->ecardOptions)) {
                $num++;
                //刷新一下输出buffer，防止由于数据过多造成问题
                if ($limit == $num) {
                    ob_flush();
                    flush();
                    $num = 0;
                }
                $data = array_reduce($this->ecardOptions[$i], function ($v1, $v2) {
                    $v1[] = $v2['value'];
                    return $v1;
                }, []);

                $row = $this->handleRowData($data);
                fputcsv($fp, $row);
                $i++;
            }
            fclose($fp);
        } catch (\Exception $exception) {
            fclose($fp);
            throw $exception;
        }
        return \Yii::$app->request->hostInfo . \Yii::$app->request->baseUrl . '/temp/' . $fileName;
    }
}
