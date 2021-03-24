<?php

/**
 * Modules Main Classes File
 * 
 * PHP version 5.6.x | 7.x | 8.x
 * 
 * @category  PLugins
 * @package   Prestashop
 * @author    Pejman Kheyri <pejmankheyri@gmail.com>
 * @copyright @copyright 2021 All rights reserved.
 */

/**
 * Sms Logs class
 * 
 * @category  PLugins
 * @package   Prestashop
 * @author    Pejman Kheyri <pejmankheyri@gmail.com>
 * @copyright @copyright 2021 All rights reserved.
 */
class SmsLogs extends ObjectModel
{
    public $id_customer;
    public $recipient;
    public $phone;
    public $event;
    public $message;
    public $uique;
    public $status;
    public $error;
    public $date_add;

    protected $fieldsRequired = array();
    protected $fieldsValidate = array();
    protected $fieldsSize = array();

    protected $fieldsRequiredLang = array();
    protected $fieldsSizeLang = array();
    protected $fieldsValidateLang = array();

    protected $table = 'smsnotifier_logs';
    protected $identifier = 'id_smsnotifier_logs';

    /**
     * Get Fields
     *
     * @return array fields
     */
    public function getFields()
    {
        parent::validateFields();
        $fields['id_customer'] = intval($this->id_customer);
        $fields['recipient'] = pSQL($this->recipient);
        $fields['phone'] = pSQL($this->phone);
        $fields['event'] = pSQL($this->event);
        $fields['message'] = pSQL($this->message, true);
        $fields['unique'] = pSQL($this->unique);
        $fields['status'] = intval($this->status);
        $fields['error'] = pSQL($this->error);
        $fields['date_add'] = pSQL($this->date_add);

        return $fields;
    }
}

/**
 * Sms class
 * 
 * @category  PLugins
 * @package   Prestashop
 * @author    Pejman Kheyri <pejmankheyri@gmail.com>
 * @copyright @copyright 2021 All rights reserved.
 */
class SmsClass
{
    public $hookName;
    public $params;
    public $phone = '';
    public $apidomain;
    public $username;
    public $password;
    public $txt = '';
    public $recipient;
    public $event = '';
    private $_alternative_hooks = array(
        'actionCustomerAccountAdd' => 'createAccount', 
        'actionValidateOrder' => 'newOrder', 
        'actionOrderStatusPostUpdate' => 'postUpdateOrderStatus',
        'smsNotifierOrderMessage' => 'smsNotifierOrderMessage'
    );

    private  $_config = array(
        'actionValidateOrder' => 3, //both 
        'actionOrderStatusPostUpdate' => 2, //customer
        'actionCustomerAccountAdd' => 3 ,
        'smsNotifierOrderMessage' => 2
    );

    /**
     * Class Construction
     *
     * @return void
     */
    public function __construct()
    {
        
    }

    /**
     * Send method
     * 
     * @param string $hookName hook Name
     * @param string $params   parameters
     *
     * @return void
     */
    public function send($hookName, $params)
    { 
        $this->hookName = $hookName;
        $this->params = $params;

        $dest = $this->_config[$this->hookName];

        if ($dest == 2 || $dest == 3) {
            $this->_prepareSms();
        }
        if ($dest == 1 || $dest == 3) {
            $this->_prepareSms(true);
        }
    }

    /**
     * Prepare sms method
     * 
     * @param boolean $bAdmin admin variable
     *
     * @return void
     */
    private function _prepareSms($bAdmin = false)
    {
        /*if (class_exists('Context'))
            $context = Context::getContext();
        else {
            global $smarty, $cookie, $language;
            $context = new StdClass();
            $context->language = $language;
        }*/

        $method = '_get' . ucfirst($this->hookName) . 'Values';

        if (method_exists(__CLASS__, $method)) {
            if (version_compare(_PS_VERSION_, '1.5.0') < 0) //  1.4
                $hookId = Hook::get($this->_alternative_hooks[$this->hookName]);
            else
                $hookId = Hook::getIdByName($this->hookName);
            
            if ($hookId) {
                $this->recipient = null;
                $this->event = $this->hookName;
                $idLang =  null ;

                switch ($this->hookName) {
                case 'actionOrderStatusPostUpdate':
                    $stateId = $this->params['newOrderStatus']->id;
                    //if($stateId == Configuration::get('PS_OS_SHIPPING')){
                        $order = new Order((int)$this->params['id_order']);
                        $idLang = $order->id_lang;
                        $keyActive = 'SMS_ISACTIVE_' . $hookId;
                        $keyTxt = 'SMS_TXT_' . $hookId;
                        $this->event .= '_SHIPPING';
                        $values = $this->$method(false, false);    
                    //}
                    break;
                default :
                    $keyActive = ($bAdmin) ? 'SMS_ISACTIVE_' . $hookId . '_ADMIN' : 'SMS_ISACTIVE_' . $hookId;
                    $keyTxt = ($bAdmin) ? 'SMS_TXT_' . $hookId . '_ADMIN' : 'SMS_TXT_' . $hookId;
                    $values = $this->$method(false, $bAdmin);
                    break;
                }

                if (is_array($values) && $this->_isEverythingValidForSending($keyActive, $keyTxt, $idLang, $bAdmin)) {
                    $this->txt = str_replace(array_keys($values), array_values($values), Configuration::get($keyTxt));
                    $this->_sendSMS();
                }
            }
        }
    }
    
    /**
     * Checking Sending Validation
     * 
     * @param string  $keyActive key active
     * @param string  $keyTxt    text keyword
     * @param integer $idLang    language id
     * @param boolean $bAdmin    admin variable
     *
     * @return boolean validation status
     */
    private function _isEverythingValidForSending($keyActive, $keyTxt, $idLang=null, $bAdmin=false)
    {
        if (Configuration::get($keyActive) != 1)
            return false;
        $this->apidomain = Configuration::get('SMSNOTIFIER_APIDOMAIN');
        $this->username = Configuration::get('SMSNOTIFIER_USERNAME');
        $this->password = Configuration::get('SMSNOTIFIER_PASSWORD');
       
        if (!empty($this->phone) 
            && Configuration::get('SMSNOTIFIER_SENDER') 
            && Configuration::get('SMSNOTIFIER_SERVICE') 
            && Configuration::get('SMSNOTIFIER_ADMIN_MOB') 
            && $this->apidomain 
            && $this->username 
            && $this->password
        )
            return true;
        return false;
    }
    
    /**
     * Send sms method
     * 
     * @return boolean sending status
     */
    private function _sendSMS()
    {
        if ($service = Configuration::get('SMSNOTIFIER_SERVICE')) {
            $s = str_replace('Class_', '', $service);
            include_once _PS_MODULE_DIR_.'SMSIRModule/services/'.$s.'.php';
            $sms = new $service;
        } else {
            return false;
        }

        //$sms = new SMS();
        $sms->setSmsApiDomain($this->apidomain); 
        $sms->setSmsLogin($this->username); 
        $sms->setSmsPassword($this->password);
        $sms->setSmsText($this->txt);
        $sms->setNums(array($this->phone));
        $sms->setSender(Configuration::get('SMSNOTIFIER_SENDER'));
        $sms->setSenderCC(Configuration::get('SMSNOTIFIER_SENDER_CUSTOMERCLUB'));
        $reponse = $sms->send();
        $result = @explode('_', $reponse);

        $log = new SmsLogs();
        if (isset($this->recipient)) {
            $log->id_customer = $this->recipient->id;
            $log->recipient = $this->recipient->firstname . ' ' . $this->recipient->lastname;
        } else {
            $log->recipient = '--';
        }
        $log->phone = $this->phone;
        $log->event = $this->event;
        $log->message = $this->txt;
        $log->unique = $result[1];
        $log->status = ($result[0] == 'OK') ? 1 : 0;
        $log->error = ($result[0] == 'KO') ? $result[1] : null;   
        $log->save();

        if ($result[0] == 'OK')
            return true;
        return false;
    }

    /**
     * Get Credit method
     * 
     * @return string credit amount
     */
    public function getCredit()
    {
        if ($service = Configuration::get('SMSNOTIFIER_SERVICE')) {
            $s = str_replace('Class_', '', $service);
            include_once _PS_MODULE_DIR_.'SMSIRModule/services/'.$s.'.php';
            $sms = new $service;
        } else {
            return false;
        }

        $sms->setSmsApiDomain(Configuration::get('SMSNOTIFIER_APIDOMAIN')); 
        $sms->setSmsLogin(Configuration::get('SMSNOTIFIER_USERNAME')); 
        $sms->setSmsPassword(Configuration::get('SMSNOTIFIER_PASSWORD'));
        $reponse = $sms->getCredit();
        return $reponse;
    }
   
    /**
     * Setting Phone number
     * 
     * @param integer $addressId address Id
     * @param boolean $bAdmin    admin variable
     * 
     * @return void
     */
    private function _setPhone($addressId, $bAdmin)
    {
        $this->phone = '';
        if ($bAdmin)
            $this->phone = Configuration::get('SMSNOTIFIER_ADMIN_MOB');
        else if (!empty($addressId)) {
            $address = new Address($addressId);
            if (!empty($address->phone_mobile)) {
                $this->phone = $address->phone_mobile;
            }
        }
    }
    
    /**
     * Setting Recipient
     * 
     * @param string $customer customer
     * 
     * @return void
     */
    private function _setRecipient($customer)
    {
        $this->recipient = $customer;
    }

    /**
     * Get Base Values
     * 
     * @return array base values
     */
    private function _getBaseValues()
    {
        $host = 'http://'.Tools::getHttpHost(false, true);
        $values = array(
            '{shopname}' => Configuration::get('PS_SHOP_NAME'),
            '{shopurl}' => $host.__PS_BASE_URI__
        );
        return $values;
    }
    
    /**
     * Get Action Validate Order Values
     * 
     * @param boolean $bSimu  variable
     * @param boolean $bAdmin admin variable
     * 
     * @return array validation values
     */
    private function _getActionValidateOrderValues($bSimu = false, $bAdmin = false)
    {
            $order = $this->params['order'];
            $customer = $this->params['customer'];
            $currency = $this->params['currency'];

            if (!$bAdmin)
                $this->_setRecipient($customer);
            $this->_setPhone($order->id_address_delivery, $bAdmin);

            $values = array(
                '{firstname}' => $customer->firstname,
                '{lastname}' => $customer->lastname,
                '{order_id}' => sprintf("%06d", $order->id),
                '{payment}' => $order->payment,
                '{total_paid}' => $order->total_paid,
                '{currency}' => $currency->sign
            );

        return array_merge($values, $this->_getBaseValues());
    }
   
    /**
     * Get Action Customer Account Add Values
     * 
     * @param boolean $bSimu  variable
     * @param boolean $bAdmin admin variable
     * 
     * @return array account values
     */
    private function _getActionCustomerAccountAddValues($bSimu = false, $bAdmin = false)
    {
        $customer = $this->params['newCustomer'];
        if (!$bAdmin)
            $this->_setRecipient($customer);
        $this->_setPhone(Address::getFirstCustomerAddressId($customer->id), $bAdmin);

        $values = array(
            '{firstname}' => $customer->firstname,
            '{lastname}' => $customer->lastname,
            '{email}' => $customer->email,
            '{passwd}' => $this->params['_POST']['passwd']
        );

        return array_merge($values, $this->_getBaseValues());
    }

    /**
     * Get Action Order Status Post Update Values
     * 
     * @param boolean $bSimu  variable
     * @param boolean $bAdmin admin variable
     * 
     * @return array account values
     */
    private function _getActionOrderStatusPostUpdateValues($bSimu = false, $bAdmin = false)
    {
        $order = new Order((int)$this->params['id_order']);
        $state = $this->params['newOrderStatus']->name;
        $customer = new Customer((int)$order->id_customer);

        $this->_setRecipient($customer);
        $this->_setPhone($order->id_address_delivery, false);

        $values = array(
            '{firstname}' => $customer->firstname,
            '{lastname}' => $customer->lastname,
            '{order_id}' => sprintf("%06d", $order->id),
            '{order_state}' => $state
        );
        return array_merge($values, $this->_getBaseValues());
    } 
    
    /**
     * Get Sms Notifier Order Message Values
     * 
     * @param boolean $bSimu  variable
     * @param boolean $bAdmin admin variable
     * 
     * @return array message values
     */
    private function _getSmsNotifierOrderMessageValues($bSimu = false, $bAdmin = false)
    { 
        $order = new Order((int)$this->params['order']);
        $customer = $customer = new Customer((int)$order->id_customer);
        $message  = $this->params['message'];

        $this->_setRecipient($customer);
        $this->_setPhone($order->id_address_delivery, false);

        $values = array(
            '{firstname}' => $customer->firstname,
            '{lastname}' => $customer->lastname,
            '{order_id}' => $order->id,
            '{message}'  => $message
        );

        return array_merge($values, $this->_getBaseValues());
    }

    /**
     * Send Message To All Customer
     * 
     * @param array $params parameters
     * 
     * @return boolean sent message status
     */
    public function sendMessageAllCustomer($params)
    {
        if ($service = Configuration::get('SMSNOTIFIER_SERVICE')) {
            $s = str_replace('Class_', '', $service);
            include_once _PS_MODULE_DIR_.'SMSIRModule/services/'.$s.'.php';
            $sms = new $service;
        } else {
            return false;
        }

        $this->apidomain = Configuration::get('SMSNOTIFIER_APIDOMAIN');
        $this->username = Configuration::get('SMSNOTIFIER_USERNAME');
        $this->password = Configuration::get('SMSNOTIFIER_PASSWORD');       
        //$sms = new SMS();
        $sms->setSmsApiDomain($this->apidomain); 
        $sms->setSmsLogin($this->username); 
        $sms->setSmsPassword($this->password);
        $sms->setSmsText($params['text']);
        $sms->setNums($params['numbers']);
        $sms->setSender(Configuration::get('SMSNOTIFIER_SENDER'));
        $sms->setSenderCC(Configuration::get('SMSNOTIFIER_SENDER_CUSTOMERCLUB'));

        $reponse = $sms->send();
        $result = @explode('_', $reponse);

        $log = new SmsLogs();
        if (isset($this->recipient)) {
            $log->id_customer = $this->recipient->id;
            $log->recipient = $this->recipient->firstname . ' ' . $this->recipient->lastname;
        } else {
            $log->recipient = '--';
        }
        $log->phone = $this->phone;
        $log->event = $this->event;
        $log->message = $this->txt;
        $log->unique = $result[1];
        $log->status = ($result[0] == 'OK') ? 1 : 0;
        $log->error = ($result[0] == 'KO') ? $result[1] : null;   
        $log->save();

        if ($result[0] == 'OK')
            return true;
        return false;
    }

    /**
     * Send Message To All Customer Club Contacts
     * 
     * @param array $params parameters
     * 
     * @return boolean sent message status
     */
    public function sendMessageAllCustomerClub($params)
    {
        if ($service = Configuration::get('SMSNOTIFIER_SERVICE')) {
            $s = str_replace('Class_', '', $service);
            include_once _PS_MODULE_DIR_.'SMSIRModule/services/'.$s.'.php' ;
            $sms = new $service;
        } else {
            return false;
        }

        $this->apidomain = Configuration::get('SMSNOTIFIER_APIDOMAIN');
        $this->username = Configuration::get('SMSNOTIFIER_USERNAME');
        $this->password = Configuration::get('SMSNOTIFIER_PASSWORD');       

        $sms->setSmsApiDomain($this->apidomain); 
        $sms->setSmsLogin($this->username); 
        $sms->setSmsPassword($this->password);
        $sms->setSmsText($params['text']);

        $reponse = $sms->sendtoallcustomerclub();
        $result = @explode('_', $reponse);

        $log = new SmsLogs();
        if (isset($this->recipient)) {
            $log->id_customer = $this->recipient->id;
            $log->recipient = $this->recipient->firstname . ' ' . $this->recipient->lastname;
        } else {
            $log->recipient = '--';
        }
        $log->phone = $this->phone;
        $log->event = $this->event;
        $log->message = $this->txt;
        $log->unique = $result[1];
        $log->status = ($result[0] == 'OK') ? 1 : 0;
        $log->error = ($result[0] == 'KO') ? $result[1] : null;   
        $log->save();

        if ($result[0] == 'OK')
            return true;
        return false;
    }
}
?>