<?php

use Common\Logic\CommentLogic;
use Common\Model\UserWater;
use Mrstock\Mjc\Http\Request;

class PayLogic
{


    //发起支付支付宝配置信息
    public function createAlipaySign($order_name, $pay_amount, $sn)
    {
        $ali_request_data['out_trade_no'] = $sn;//商户单号
        $ali_request_data['total_amount'] = $pay_amount / 100; //金额元
        $ali_request_data['subject'] = $order_name;//订单名
        $orderStr = self::aliTransfer($ali_request_data, 'AlipayTradeAppPayRequest');
        return $orderStr;
    }

    //支付宝支付回调
    public static function aliPayNotify()
    {
        /*** 请填写以下配置信息 ***/
        $aop_dir = '';//aop目录路径
        require_once $aop_dir . 'AopCertClient.php';
        $alipayCertPath = "支付宝公钥证书路径（要确保证书文件可读），例如：/home/admin/cert/alipayCertPublicKey_RSA2.crt";
        $aop = new \AopCertClient();
        $aop->alipayrsaPublicKey = $aop->getPublicKey($alipayCertPath);
        $result = $aop->rsaCheckV1($_POST, $alipayCertPath, $_POST['sign_type']);
        if ($result === true && $_POST['trade_status'] == 'TRADE_SUCCESS') {
            //处理你的逻辑，例如获取订单号$_POST['out_trade_no']，订单金额$_POST['total_amount']等
            //程序执行完后必须打印输出“success”（不包含引号）。如果商户反馈给支付宝的字符不是success这7个字符，支付宝服务器会不断重发通知，直到超过24小时22分钟。一般情况下，25小时以内完成8次通知（通知的间隔频率一般是：4m,10m,10m,1h,2h,6h,15h）；
            echo 'success';
            exit();
        } else {
            echo 'error';
            exit();
        }
    }

    //支付宝退款
    public static function aliPayRefund($trade_no, $out_trade_no, $refund_amount)
    {
        $ali_request_data['out_trade_no'] = $out_trade_no;//商户单号
        $ali_request_data['trade_no'] = $trade_no;//支付宝流水号
        $ali_request_data['refund_amount'] = $refund_amount / 100; //退款金额元
        self::aliTransfer($ali_request_data, 'AlipayTradeRefundRequest');
    }

    //提现到支付宝
    public function transfer_alipayOp()
    {
        $ali_request_data['out_biz_no'] = '';//商户单号
        $ali_request_data['business_params'] = ['payer_show_name_use_alias' => false];
        $ali_request_data['biz_scene'] = 'DIRECT_TRANSFER';
        $ali_request_data['payee_info']['identity'] = '';//支付宝用户ID
        $ali_request_data['payee_info']['identity_type'] = 'ALIPAY_USER_ID';
        $ali_request_data['trans_amount'] = '';//退款金额
        $ali_request_data['product_code'] = 'TRANS_ACCOUNT_NO_PWD';
        $ali_request_data['order_title'] = '微自律-提现';
        self::aliTransfer($ali_request_data, 'AlipayFundTransUniTransferRequest');//提现到支付宝
    }

    //证书模式
    public static function aliTransfer($ali_request_data, $ali_interface)
    {
        /*** 请填写以下配置信息 ***/
        $aop_dir = '';//aop目录路径
        require_once $aop_dir . 'AopCertClient.php';
        require_once $aop_dir . 'AopCertification.php';
        require_once $aop_dir . "request/{$ali_interface}.php";

        $aop = new \AopCertClient();
        $appCertPath = "应用证书路径（要确保证书文件可读），例如：/home/admin/cert/appCertPublicKey.crt";
        $alipayCertPath = "支付宝公钥证书路径（要确保证书文件可读），例如：/home/admin/cert/alipayCertPublicKey_RSA2.crt";
        $rootCertPath = "支付宝根证书路径（要确保证书文件可读），例如：/home/admin/cert/alipayRootCert.crt";

        $aop->gatewayUrl = "https://openapi.alipay.com/gateway.do";//后期要改为线上
        $aop->appId = '';
        $aop->rsaPrivateKey = '';
        $aop->alipayrsaPublicKey = $aop->getPublicKey($alipayCertPath);
        $aop->apiVersion = '1.0';
        $aop->signType = 'RSA2';
        $aop->postCharset = 'utf-8';
        $aop->format = 'json';
        $aop->isCheckAlipayPublicCert = true;//是否校验自动下载的支付宝公钥证书，如果开启校验要保证支付宝根证书在有效期内
        $aop->appCertSN = $aop->getCertSN($appCertPath);//调用getCertSN获取证书序列号
        $aop->alipayRootCertSN = $aop->getRootCertSN($rootCertPath);//调用getRootCertSN获取支付宝根证书序列号
        $request = new $ali_interface();
        $request->setBizContent(json_encode($ali_request_data));
        if ($ali_interface == 'AlipayTradeAppPayRequest') {
            $request->setNotifyUrl('');//支付回调通知
            $responseResult = $aop->sdkExecute($request);
            return $responseResult;
        } else {
            $responseResult = $aop->execute($request);
        }
        $responseApiName = str_replace(".", "_", $request->getApiMethodName()) . "_response";
        $response = $responseResult->$responseApiName;
        if ($response->code != 10000) {
            throw new \Exception($response->sub_msg, -1);
        }
        return (array)$response;
    }

}