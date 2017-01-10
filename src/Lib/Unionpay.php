<?php
namespace Ruesin\Payments\Lib;

use Ruesin\Payments\Common\StringUtils;

class Unionpay extends PayBase
{
    use \Ruesin\Payments\Common\SubmitForm;
    
    
    // 前台交易请求地址
    const FRONT_TRANS_URL = 'https://101.231.204.80:5000/gateway/api/frontTransReq.do';
    
//     APP交易请求地址:
//     https://101.231.204.80:5000/gateway/api/appTransReq.do
    
//     后台交易请求地址:
//     https://101.231.204.80:5000/gateway/api/backTransReq.do
    
//     后台交易请求地址(若为有卡交易配置该地址)：
//     https://101.231.204.80:5000/gateway/api/cardTransReq.do
    
//     单笔查询请求地址:
//     https://101.231.204.80:5000/gateway/api/queryTrans.do
    
//     批量交易请求地址:
//     https://101.231.204.80:5000/gateway/api/batchTrans.do
    
//     文件传输类交易地址:
//     https://101.231.204.80:9080/
    
    private $config = [];
    
    public function __construct($config = [])
    {
        $this->setConfig($config);
    }
    
    public function getPayForm($order = [], $params = [])
    {
        $params = [
            'version' => '5.0.0',//版本号
            'encoding' => 'UTF-8',//编码方式
            'certId'  => '', //证书ID
            'signature' => '', //签名
            'signMethod' => '01',//签名方法
            'txnType' => '01',//交易类型
            'txnSubType' => '01',//交易子类
            'bizType' => '000201',//产品业务类型
            'channelType' => '07',//渠道类型，07-PC，08-手机
            'frontUrl' => $this->config['return_url'],  //前台通知地址~
            'backUrl' => $this->config['notify_url'],	  //后台通知地址
            'accessType' => '0',//接入类型
            'merId' => $this->config['merId'],//商户代码
            'orderId' => $order["out_trade_no"],//商户订单号
            'txnTime' => date('YmdHis'),//订单发送时间
            'txnAmt' => intval($order['money'] * 100),//交易金额，单位分
            'currencyCode' => '156',//交易币种
            //'reqReserved' =>'透传信息',
        ];
        
        $params['certId'] = $this->getCert('certId');
        
        if (!$this->buildSign($params)) return false;
        
        $formParam = array(
            'action' => self::FRONT_TRANS_URL,
            'method' => 'post',
            'text' => 'Connect gateway...'
        );
        return $this->buildRequestForm($params, $formParam);
    }
    
    private function setConfig($config = [])
    {
        $this->config = $config;
    }

    function notify()
    {}

    function back()
    {}

    /**
     * 获取证书信息
     *
     * @author Ruesin
     */
    private function getCert($key = '')
    {
        $result = [];
        $pkcs12certdata = file_get_contents($this->config['sign_cert_path']);
        if ($pkcs12certdata === false) {
            return false;
        }
        
        openssl_pkcs12_read($pkcs12certdata, $certs, $this->config['sign_cert_pwd']);
        $x509data = $certs['cert'];
        
        openssl_x509_read($x509data);
        $certdata = openssl_x509_parse($x509data);
        $result['certId'] = $certdata['serialNumber'];
        
        $result['key'] = $certs['pkey'];
        $result['cert'] = $x509data;
        
        return $key ? $result[$key] : $result;
    }
    
    /**
     * 签名
     *
     * @author Ruesin
     */
    private function buildSign(&$params)
    {
        if(isset($params['signature'])) {
            unset($params['signature']);
        }
        
        $params = StringUtils::argSort($params);
        
        $params_str = StringUtils::createLinkstring($params);
        
        $params_sha1x16 = sha1 ( $params_str, FALSE );//摘要
        
        $private_key = $this->getCert('key');
        
        // 签名
        $sign_falg = openssl_sign ( $params_sha1x16, $signature, $private_key, OPENSSL_ALGO_SHA1 );
        
        if (!$sign_falg) return false;
        
        $params ['signature'] = base64_encode ( $signature );
        
        return true;
    }
    
}