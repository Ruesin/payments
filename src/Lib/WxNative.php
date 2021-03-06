<?php
namespace Ruesin\Payments\Lib;

use Ruesin\Payments\Common\StringUtils;
use Ruesin\Payments\Common\Request;

class WxNative extends PayBase
{

    const UNIFIED_ORDER_URL = 'https://api.mch.weixin.qq.com/pay/unifiedorder';
    
    const QUERY_ORDER_URL = 'https://api.mch.weixin.qq.com/pay/orderquery';
    
    /*
     * 
     * @see https://pay.weixin.qq.com/wiki/doc/api/native.php?chapter=9_1
     *
     */
    public function submit($order = [], $params = [])
    {
        $form = array(
            'appid'       => $this->getConfig('appid'),
            'mch_id'      => $this->getConfig('mch_id'),
            'device_info' => isset($params['device']) ? $params['device'] : 'WEB',
            'nonce_str'   => StringUtils::createNonceString(),
            'sign'        => '',
            'body'        => $order['name'],
            'detail'      => $params['order_detail'],
            'attach'      => $params['order_attach'],
            'out_trade_no' => $order['out_trade_no'],
            'fee_type'    => 'CNY',
            'total_fee'   => ceil($order['money'] * 100),
            'spbill_create_ip' => $_SERVER['REMOTE_ADDR'],
            'time_start'  => date('YmdHis'),
            'time_expire' => date('YmdHis', time() + 7200),
            //'goods_tag' => 'test',
            'notify_url' => $this->getConfig('notify_url'),
            'trade_type' => 'NATIVE',
            'product_id' => $order['order_id'],
            'limit_pay'  => isset($params['no_credit']) ? 'no_credit' : '', //no_credit--指定不能使用信用卡支付
        );
        
        if(isset($params['plat']) && $params['plat'] != 'web') {
            $form['device_info'] = $params['plat'];
        }
        if (isset($params['no_credit']) && $params['no_credit'] == true) {
            $form['limit_pay'] = 'no_credit';
        }
        
        $result = $this->unifiedOrder($form);
        if (! $result)
            return '';
        
        if ($params['type'] == 'src') {
            return 'http://paysdk.weixin.qq.com/example/qrcode.php?data=' . urlencode($result["code_url"]);
        }
        return '<img src="http://paysdk.weixin.qq.com/example/qrcode.php?data=' . urlencode($result["code_url"]) . '"/>';
    }

    protected function unifiedOrder($params, $timeOut = 6)
    {
        $params['sign'] = $this->buildSign($params);
        $xml = StringUtils::arrayToXml($params);
        
        $response = Request::curl(self::UNIFIED_ORDER_URL,$xml);
        $result = StringUtils::XmlToArray($response);
        if ($result['return_code'] != 'SUCCESS') {
            return false;
        }
        if (! $this->verifySign($result)) {
            return false;
        }
        return $result;
    }

    /**
     * 签名
     */
    public function buildSign($params)
    {
        $para_filter = StringUtils::paraFilter($params, array('sign'));
        $para_sort = StringUtils::argSort($para_filter);
        $string = StringUtils::createLinkstring($para_sort);
        $result = strtoupper(md5($string . "&key=" . $this->getConfig('key')));
        return $result;
    }

    /**
     * 验签
     */
    public function verifySign($params)
    {
        if (! isset($params['sign']) || ! $params['sign']) {
            return false;
        }
        $sign = $this->buildSign($params);
        return $params['sign'] == $sign;
    }
    
    public function back()
    {
        return true;
    }

    public function notify()
    {
        $xml = file_get_contents("php://input");
        
        $msg = "OK";
        $result = true;
        
        $data = StringUtils::XmlToArray($xml);
        
        if ($data['return_code'] != 'SUCCESS') {
            return false;
        }
        if (! $this->verifySign($data)) {
            return false;
        }
        
        if(!array_key_exists("transaction_id", $data)){
            return false;
        }
        $input['transaction_id'] = $data["transaction_id"];
        
        if(self::orderQuery($input) == false){
            return false;
        }
        
        if($data['result_code'] == 'FAIL'){
            return false;
        }
        
        return array(
            'out_trade_no' => $data['out_trade_no'],
            'data' => $data
        );
    }
    
    /**
     * 查询订单，WxPayOrderQuery中out_trade_no、transaction_id至少填一个
     * appid、mchid、spbill_create_ip、nonce_str不需要填入
     */
    public function orderQuery($input)
    {
        if(!isset($input['out_trade_no']) && !isset($input['transaction_id'])){
            return false;
        }
        $input['appid']   = $this->getConfig('appid');
        $input['mch_id']  = $this->getConfig('mch_id');
        $input['nonce_str'] = StringUtils::createNonceString();
        
        $input['sign'] = $this->buildSign($input);
        
        $xml = StringUtils::arrayToXml($input);
    
        $response = Request::curl(self::UNIFIED_ORDER_URL,$xml);
        
        $result = StringUtils::XmlToArray($response);
    
        if(!array_key_exists("return_code", $result) || !array_key_exists("result_code", $result) || $result["return_code"] != "SUCCESS" || $result["result_code"] != "SUCCESS")
            return false;
    
        return true;
    }
}