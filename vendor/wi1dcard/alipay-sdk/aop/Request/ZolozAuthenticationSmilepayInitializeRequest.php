<?php
/**
 * ALIPAY API: zoloz.authentication.smilepay.initialize request
 *
 * @author auto create
 *
 * @since 1.0, 2019-01-07 20:51:15
 */

namespace Alipay\Request;

class ZolozAuthenticationSmilepayInitializeRequest extends AbstractAlipayRequest
{
    /**
     * 刷脸支付初始化
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
