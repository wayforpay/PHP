<?php

/**
 * Class WayForPay
 */
class WayForPay
{
    const PURCHASE_URL      = 'https://secure.wayforpay.com/pay';
    const API_URL           = 'https://api.wayforpay.com/api';
    const WIDGET_URL        = 'https://secure.wayforpay.com/server/pay-widget.js';
    const FIELDS_DELIMITER  = ';';
    const API_VERSION       = 1;
    const DEFAULT_CHARSET   = 'utf8';

    const MODE_PURCHASE         = 'PURCHASE';
    const MODE_SETTLE           = 'SETTLE';
    const MODE_CHARGE           = 'CHARGE';
    const MODE_COMPLETE_3DS     = 'COMPLETE_3DS';
    const MODE_REFUND           = 'REFUND';
    const MODE_CHECK_STATUS     = 'CHECK_STATUS';
    const MODE_P2P_CREDIT       = 'P2P_CREDIT';
    const MODE_CREATE_INVOICE   = 'CREATE_INVOICE';
    const MODE_P2_PHONE         = 'P2_PHONE';
    const MODE_TRANSACTION_LIST = 'TRANSACTION_LIST';

    private $_merchant_account;
    private $_merchant_password;
    private $_action;
    private $_params;
    private $_charset;

    /**
     * Init
     *
     * @param $merchant_account
     * @param $merchant_password
     * @param string $charset
     * @throws InvalidArgumentException
     */
    public function __construct($merchant_account, $merchant_password, $charset = self::DEFAULT_CHARSET)
    {
        if (!is_string($merchant_account) || $merchant_account === '') {
            throw new InvalidArgumentException('Merchant account must be string and not empty');
        }

        if (!is_string($merchant_password) || $merchant_password === '') {
            throw new InvalidArgumentException('Merchant password must be string and not empty');
        }

        $this->_merchant_account = $merchant_account;
        $this->_merchant_password = $merchant_password;
        $this->_charset = $charset;
    }

    /**
     * MODE_SETTLE
     *
     * @param $fields
     * @return mixed
     */
    public function settle($fields)
    {
        $this->_prepare(self::MODE_SETTLE, $fields);
        return $this->_query();
    }

    /**
     * MODE_CHARGE
     *
     * @param $fields
     * @return mixed
     */
    public function charge($fields)
    {
        $this->_prepare(self::MODE_CHARGE, $fields);
        return $this->_query();
    }

    /**
     * COMPLETE_3DS
     *
     * @param $fields
     * @return mixed
     */
    public function complete3ds($fields)
    {
        $this->_prepare(self::MODE_COMPLETE_3DS, $fields);
        return $this->_query();
    }

    /**
     * MODE_REFUND
     *
     * @param $fields
     * @return mixed
     */
    public function refund($fields)
    {
        $this->_prepare(self::MODE_REFUND, $fields);
        return $this->_query();
    }

    /**
     * MODE_CHECK_STATUS
     *
     * @param $fields
     * @return mixed
     */
    public function checkStatus($fields)
    {
        $this->_prepare(self::MODE_CHECK_STATUS, $fields);
        return $this->_query();
    }

    /**
     * MODE_P2P_CREDIT
     *
     * @param $fields
     * @return mixed
     */
    public function account2card($fields)
    {
        $this->_prepare(self::MODE_P2P_CREDIT, $fields);
        return $this->_query();
    }

    /**
     * MODE_P2P_CREDIT
     *
     * @param $fields
     * @return mixed
     */
    public function createInvoice($fields)
    {
        $this->_prepare(self::MODE_CREATE_INVOICE, $fields);
        return $this->_query();
    }

    /**
     * MODE_P2P_CREDIT
     *
     * @param $fields
     * @return mixed
     */
    public function account2phone($fields)
    {
        $this->_prepare(self::MODE_P2_PHONE, $fields);
        return $this->_query();
    }

    /**
     * TRANSACTION_LIST
     *
     * @param $fields
     * @return mixed
     */
    public function transactionList($fields)
    {
        $this->_prepare(self::MODE_TRANSACTION_LIST, $fields);
        return $this->_query();
    }

    /**
     * MODE_PURCHASE
     * Generate html form
     *
     * @param $fields
     * @return string
     */
    public function buildForm($fields)
    {
        $this->_prepare(self::MODE_PURCHASE, $fields);

        $form = sprintf('<form method="POST" action="%s" accept-charset="utf-8">', self::PURCHASE_URL);

        foreach ($this->_params as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $field) {
                    $form .= sprintf('<input type="hidden" name="%s" value="%s" />', $key . '[]', htmlspecialchars($field));
                }
            } else {
                $form .= sprintf('<input type="hidden" name="%s" value="%s" />', $key, htmlspecialchars($value));
            }
        }

        $form .= '<input type="submit" value="Submit purchase form"></form>';

        return $form;
    }

    /**
     * MODE_PURCHASE
     * If GET redirect is used to redirect to purchase form, i.e.
     * https://secure.wayforpay.com/pay/get?merchantAccount=test_merch_n1&merchantDomainName=domain.ua&merchantSignature=c6d08855677ec6beca68e292b2c3c6ae&orderReference=RG3656-1430373125&orderDate=1430373125&amount=0.16&currency=UAH&productName=Saturn%20BUE%201.2&productPrice=0.16&productCount=1&language=RU
     *
     * @param $fields
     * @return string
     */
    public function generatePurchaseUrl($fields) {
        $this->_prepare(self::MODE_PURCHASE, $fields);
        return self::PURCHASE_URL.'/get?'.http_build_query($this->_params);
    }

    /**
     * Return signature hash
     *
     * @param $action
     * @param $fields
     * @return mixed
     */
    public function createSignature($action, $fields)
    {
        $this->_prepare($action, $fields);

        return $this->_buildSignature();
    }

    /**
     * @param $action
     * @param array $params
     * @throws InvalidArgumentException
     */
    private function _prepare($action, array $params)
    {
        $this->_action = $action;

        if(empty($params)){
            throw new InvalidArgumentException('Arguments must be not empty');
        }

        $this->_params = $params;
        $this->_params['transactionType'] = $this->_action;
        $this->_params['merchantAccount'] = $this->_merchant_account;
        $this->_params['merchantSignature'] = $this->_buildSignature();

        if ($this->_action !== self::MODE_PURCHASE) $this->_params['apiVersion'] = self::API_VERSION;

        $this->_checkFields();

    }

    /**
     * Check required fields
     *
     * @param $fields
     * @return bool
     * @throws InvalidArgumentException
     */
    private function _checkFields()
    {
        $required = $this->_getRequiredFields();
        $error = array();

        foreach ($required as $item) {
            if (array_key_exists($item, $this->_params)) {
                if (empty($this->_params[$item])) {
                    $error[] = $item;
                }
            } else {
                $error[] = $item;
            }
        }

        if (!empty($error)) {
            throw new InvalidArgumentException('Missed required field(s): ' . implode(', ', $error) . '.');
        }

        return true;
    }

    /**
     * Generate signature hash
     *
     * @param $fields
     * @return string
     * @throws InvalidArgumentException
     */
    private function _buildSignature()
    {
        $signFields = $this->_getFieldsNameForSignature();
        $data = array();
        $error = array();

        foreach ($signFields as $item) {
            if (array_key_exists($item, $this->_params)) {
                $value = $this->_params[$item];
                if (is_array($value)) {
                    $data[] = implode(self::FIELDS_DELIMITER, $value);
                } else {
                    $data[] = (string) $value;
                }
            } else {
                $error[] = $item;
            }
        }

        if ( $this->_charset != self::DEFAULT_CHARSET) {
            if (!function_exists('iconv')) {
                throw new \RuntimeException('iconv extension required');
            }

            foreach($data as $key => $value) {
                $data[$key] = iconv($this->_charset, self::DEFAULT_CHARSET, $data[$key]);
            }
        }

        if (!empty($error)) {
            throw new InvalidArgumentException('Missed signature field(s): ' . implode(', ', $error) . '.');
        }

        return hash_hmac('md5', implode(self::FIELDS_DELIMITER, $data), $this->_merchant_password);
    }

    /**
     * Request method
     * @return mixed
     */
    private function _query()
    {
        $fields = json_encode($this->_params);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, self::API_URL);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json;charset=utf-8'));
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS,$fields);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response, true);
    }


    /**
     * Signature fields
     *
     * @return array
     * @throws InvalidArgumentException
     */
    private function _getFieldsNameForSignature()
    {
        $purchaseFieldsAlias = array(
            'merchantAccount',
            'merchantDomainName',
            'orderReference',
            'orderDate',
            'amount',
            'currency',
            'productName',
            'productCount',
            'productPrice'
        );

        switch ($this->_action) {
            case self::MODE_PURCHASE:
                return $purchaseFieldsAlias;
                break;
            case self::MODE_REFUND:
                return array(
                    'merchantAccount',
                    'orderReference',
                    'amount',
                    'currency'
                );
            case self::MODE_CHECK_STATUS:
                return array(
                    'merchantAccount',
                    'orderReference'
                );
                break;
            case self::MODE_CHARGE:
                return $purchaseFieldsAlias;
                break;
            case self::MODE_COMPLETE_3DS:
                return array(
                    'authorization_ticket',
                    'd3ds_md',
                    'd3ds_pares'
                );
                break;
            case 'SETTLE':
                return array(
                    'merchantAccount',
                    'orderReference',
                    'amount',
                    'currency'
                );
                break;
            case self::MODE_P2P_CREDIT:
                return array(
                    'merchantAccount',
                    'orderReference',
                    'amount',
                    'currency',
                    'cardBeneficiary',
                    'rec2Token',
                );
                break;
            case self::MODE_CREATE_INVOICE:
                return $purchaseFieldsAlias;
                break;
            case self::MODE_P2_PHONE:
                return array(
                    'merchantAccount',
                    'orderReference',
                    'amount',
                    'currency',
                    'phone',
                );
                break;
            case self::MODE_TRANSACTION_LIST:
                return array(
                    'merchantAccount',
                    'dateBegin',
                    'dateEnd',
                );
                break;
            default:
                throw new InvalidArgumentException('Unknown transaction type: '.$this->_action);
        }
    }

    /**
     * Required fields
     *
     * @return array
     */
    private function _getRequiredFields()
    {
        switch ($this->_action) {
            case self::MODE_PURCHASE:
                return array(
                    'merchantAccount',
                    'merchantDomainName',
                    'merchantTransactionSecureType',
                    'orderReference',
                    'orderDate',
                    'amount',
                    'currency',
                    'productName',
                    'productCount',
                    'productPrice'
                );
            case self::MODE_SETTLE:
                return array(
                    'transactionType',
                    'merchantAccount',
                    'orderReference',
                    'amount',
                    'currency',
                    'apiVersion'
                );
            case self::MODE_CHARGE:
                $required = array(
                    'transactionType',
                    'merchantAccount',
                    'merchantDomainName',
                    'orderReference',
                    'apiVersion',
                    'orderDate',
                    'amount',
                    'currency',
                    'productName',
                    'productCount',
                    'productPrice',
                    'clientFirstName',
                    'clientLastName',
                    'clientEmail',
                    'clientPhone',
                    'clientCountry',
                    'clientIpAddress'
                );

                $additional = !empty($this->_params['recToken']) ?
                    array('recToken') :
                    array('card', 'expMonth', 'expYear', 'cardCvv', 'cardHolder');

                return array_merge($required, $additional);
            case self::MODE_COMPLETE_3DS:
                return array(
                    'authorization_ticket',
                    'd3ds_md',
                    'd3ds_pares'
                );
                break;
            case self::MODE_REFUND:
                return array(
                    'transactionType',
                    'merchantAccount',
                    'orderReference',
                    'amount',
                    'currency',
                    'comment',
                    'apiVersion'
                );
            case self::MODE_CHECK_STATUS:
                return array(
                    'transactionType',
                    'merchantAccount',
                    'orderReference',
                    'apiVersion'
                );
            case self::MODE_P2P_CREDIT:
                return array(
                    'transactionType',
                    'merchantAccount',
                    'orderReference',
                    'amount',
                    'currency',
                    'cardBeneficiary',
                    'merchantSignature',
                );
            case self::MODE_CREATE_INVOICE:
                return array(
                    'transactionType',
                    'merchantAccount',
                    'merchantDomainName',
                    'orderReference',
                    'amount',
                    'currency',
                    'productName',
                    'productCount',
                    'productPrice',
                );
            case self::MODE_P2_PHONE:
                return array(
                    'merchantAccount',
                    'orderReference',
                    'orderDate',
                    'currency',
                    'amount',
                    'phone',
                );
                break;
            case self::MODE_TRANSACTION_LIST:
                return array(
                    'merchantAccount',
                    'dateBegin',
                    'dateEnd',
                );
                break;
            default:
                throw new InvalidArgumentException('Unknown transaction type');
        }
    }

    /**
     * @param array $fields Widget(https://wiki.wayforpay.com/pages/viewpage.action?pageId=852091)
     * @param null $callbackFunction JavaScript callback function called on widget response
     * @return string
     */
    public function buildWidgetButton(array $fields, $callbackFunction = null)
    {
        $this->_prepare(self::MODE_PURCHASE, $fields);

        $button = '<script id="widget-wfp-script" language="javascript" type="text/javascript" src="'. self::WIDGET_URL .'"></script>
        <script type="text/javascript">
            var wayforpay = new Wayforpay();
            var pay = function () {
            wayforpay.run(' . json_encode($this->_params) . ');
            }
            window.addEventListener("message", '. ($callbackFunction ? $callbackFunction : "receiveMessage").');
            function receiveMessage(event)
            {
                if(
                    event.data == "WfpWidgetEventClose" ||      //при закрытии виджета пользователем
                    event.data == "WfpWidgetEventApproved" ||   //при успешном завершении операции
                    event.data == "WfpWidgetEventDeclined" ||   //при неуспешном завершении
                    event.data == "WfpWidgetEventPending")      // транзакция на обработке
                {
                    console.log(event.data);
                }
            }
        </script>
        <button type="button" onclick="pay();">Оплатить</button>';

        return $button;
    }
}

