<?php
/**
 * ALIPAY API: alipay.user.certify.open.query request
 *
 * @author auto create
 *
 * @since 1.0, 2019-01-07 20:51:15
 */

namespace Alipay\Request;

class AlipayUserCertifyOpenQueryRequest extends AbstractAlipayRequest
{
    /**
     * 支付宝开放认证查询
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
