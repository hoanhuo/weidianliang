<?php
/**
 * ALIPAY API: zoloz.identification.customer.certifyzhub.query request
 *
 * @author auto create
 *
 * @since 1.0, 2019-01-07 20:51:15
 */

namespace Alipay\Request;

class ZolozIdentificationCustomerCertifyzhubQueryRequest extends AbstractAlipayRequest
{
    /**
     * 人脸服务的结果查询(一体化)
     **/
    private $bizContent;

    public function setBizContent($bizContent)
    {
        $this->bizContent = $bizContent;
        $this->apiParams['biz_content'] = $bizContent;
    }

    public function getBizContent()
    {
        return $this->bizContent;
    }
}
