<?php

/**
 * Modules Main Gateway File
 * 
 * PHP version 5.6.x | 7.x | 8.x
 * 
 * @category  PLugins
 * @package   Prestashop
 * @author    Pejman Kheyri <pejmankheyri@gmail.com>
 * @copyright @copyright 2021 All rights reserved.
 */

/**
 * Bulk Gateway class
 * 
 * @category  PLugins
 * @package   Prestashop
 * @author    Pejman Kheyri <pejmankheyri@gmail.com>
 * @copyright @copyright 2021 All rights reserved.
 */ 
class Class_Smsir
{
    public  $name  =   'smsir';
    private $_smsApiDomain; // string
    private $_smsLogin; // string
    private $_smsPassword; // string
    private $_prestaKey; // string

    private $_sms_text; // string

    private $_t_nums; // array
    private $_t_fields_1; // array
    private $_t_fields_2; // array
    private $_t_fields_3; // array

    private $_type; // int
    private $_d; // int
    private $_m; // int
    private $_h; // int
    private $_i; // int
    private $_y; // int

    private $_sender; // string
    private $_simulation; // int

    private $_list_name; // string

    /**
     * Gets API Customer Club Send To Categories Url.
     *
     * @return string Indicates the Url
     */
    protected function getAPICustomerClubSendToCategoriesUrl()
    {
        return "api/CustomerClub/SendToCategories";
    }

    /**
     * Gets API Message Send Url.
     *
     * @return string Indicates the Url
     */
    protected function getAPIMessageSendUrl()
    {
        return "api/MessageSend";
    }

    /**
     * Gets API Customer Club Add Contact And Send Url.
     *
     * @return string Indicates the Url
     */
    protected function getAPICustomerClubAddAndSendUrl()
    {
        return "api/CustomerClub/AddContactAndSend";
    }

    /**
     * Gets API credit Url.
     *
     * @return string Indicates the Url
     */
    protected function getAPIcreditUrl()
    {
        return "api/credit";
    }

    /**
     * Gets Api Token Url.
     *
     * @return string Indicates the Url
     */
    protected function getApiTokenUrl()
    {
        return "api/Token";
    }

    /**
     * Class Construction
     *
     * @return void
     */
    public function __construct()
    {
        $this->_smsApiDomain = '';
        $this->_smsLogin = '';
        $this->_smsPassword = '';
        $this->_prestaKey = '';

        $this->_sms_text = '';

        $this->_t_nums = array();
        $this->_t_fields_1 = array();
        $this->_t_fields_2 = array();
        $this->_t_fields_3 = array();

        $this->_type = '';
        $this->_d = 0;
        $this->_m = 0;
        $this->_h = 0;
        $this->_i = 0;
        $this->_y = 0;

        $this->_sender = '';
        $this->_list_name = '';
        $this->_simulation = 0;
    }

    /**
     * Send method
     *
     * @return string indicates the send result
     */
    public function send()
    {
        $data = array(
            'Username' => $this->_smsLogin, 
            'Password' => $this->_smsPassword, 
            'Message' => $this->_sms_text,  
            'RecipientNumbers' => $this->_t_nums,    
            'SenderNumber' => $this->_sender
        );
        $result =  $this->soapSend($data);
        
        switch ($result[0]) { 
        case '1': $res = 'OK_'.$result[1];
            break;

        default : $res = 'KO_Error number:'.$result[0];
        }
        return $res;
    }

    /**
     * Soap Send method
     * 
     * @param string $data data
     *
     * @return array send result
     */
    public function soapSend($data)
    {
        try {
            date_default_timezone_set('Asia/Tehran');

            $SenderCC = $this->SenderCC;
            $num_string = $this->_t_nums;

            if (is_array($num_string)) {
                $mobileNumbers = $num_string;
            } else {
                $mobileNumbers = explode(',', $num_string);
            }

            foreach ($mobileNumbers as $key => $value) {
                if (($this->isMobile($value)) || ($this->isMobileWithz($value))) {
                    $number[] = doubleval($value);
                }
            }
            @$numbers = array_unique($number);

            if (is_array($numbers) && $numbers) {
                foreach ($numbers as $key => $value) {
                    $Messages[] = $this->_sms_text;
                }
            }

            $SendDateTime = date("Y-m-d")."T".date("H:i:s");

            if ($SenderCC == 1) {
                foreach ($numbers as $num_keys => $num_vals) {
                    $contacts[] = array(
                        "Prefix" => "",
                        "FirstName" => "" ,
                        "LastName" => "",
                        "Mobile" => $num_vals,
                        "BirthDay" => "",
                        "CategoryId" => "",
                        "MessageText" => $this->_sms_text
                    );
                }

                $CustomerClubInsertAndSendMessage = $this->customerClubInsertAndSendMessage($contacts);

                if ($CustomerClubInsertAndSendMessage == true) {
                    return array('1', $result['resultMessage']);
                } else {
                    return array('0', $result['resultMessage']);
                }
            } else {
                $SendMessage = $this->sendMessage($numbers, $Messages, $SendDateTime);

                if ($SendMessage == true) {
                    return array('1', $result['resultMessage']);
                } else {
                    return array('0', $result['resultMessage']);
                }
            }
        } catch (Exeption $e) {
            echo 'Error : '.$e->getMessage();
        }
    }

    /**
     * Send to all customer club.
     *
     * @return string Indicates the sent sms result
     */
    public function sendtoallcustomerclub()
    {
        $contactsCustomerClubCategoryIds = array();
        $token = $this->_getToken($this->_smsLogin, $this->_smsPassword);
        if ($token != false) {
            $postData = array(
                'Messages' => $this->_sms_text,
                'contactsCustomerClubCategoryIds' => $contactsCustomerClubCategoryIds,
                'SendDateTime' => '',
                'CanContinueInCaseOfError' => 'false'
            );

            $url = $this->_smsApiDomain.$this->getAPICustomerClubSendToCategoriesUrl();
            $CustomerClubSendToCategories = $this->_execute($postData, $url, $token);
            $object = json_decode($CustomerClubSendToCategories);

            if (is_object($object)) {
                if ($object->IsSuccessful == true) {
                    return 'OK_'.$sendresultvars;
                } else {
                    return 'KO_Error number:';
                }
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    /**
     * Get Credit.
     *
     * @return string Indicates the sent sms result
     */
    public function getCredit()
    {
        $token = $this->_getToken($this->_smsLogin, $this->_smsPassword);

        $result = false;
        if ($token != false) {

            $url = $this->_smsApiDomain.$this->getAPIcreditUrl();
            $GetCredit = $this->_executeCredit($url, $token);

            $object = json_decode($GetCredit);

            if (is_object($object)) {
                if ($object->IsSuccessful == true) {
                    $result = $object->Credit;
                } else {
                    $result = $object->Message;
                }
            } else {
                $result = false;
            }
        } else {
            $result = false;
        }
        return $result;
    }

    /**
     * Send sms.
     *
     * @param MobileNumbers[] $MobileNumbers array structure of mobile numbers
     * @param Messages[]      $Messages      array structure of messages
     * @param string          $SendDateTime  Send Date Time
     * 
     * @return string Indicates the sent sms result
     */
    public function sendMessage($MobileNumbers, $Messages, $SendDateTime = '')
    {
        $token = $this->_getToken($this->_smsLogin, $this->_smsPassword);

        $result = false;
        if ($token != false) {
            $postData = array(
                'Messages' => $Messages,
                'MobileNumbers' => $MobileNumbers,
                'LineNumber' => $this->_sender,
                'SendDateTime' => $SendDateTime,
                'CanContinueInCaseOfError' => 'false'
            );

            $url = $this->_smsApiDomain.$this->getAPIMessageSendUrl();
            $SendMessage = $this->_execute($postData, $url, $token);
            $object = json_decode($SendMessage);

            if (is_object($object)) {
                if ($object->IsSuccessful == true) {
                    $result = true;
                } else {
                    $result = false;
                }
            } else {
                $result = false;
            }
        } else {
            $result = false;
        }
        return $result;
    }

    /**
     * Customer Club Insert And Send Message.
     *
     * @param data[] $data array structure of contacts data
     * 
     * @return string Indicates the sent sms result
     */
    public function customerClubInsertAndSendMessage($data)
    {
        $token = $this->_getToken($this->_smsLogin, $this->_smsPassword);

        $result = false;
        if ($token != false) {
            $postData = $data;

            $url = $this->_smsApiDomain.$this->getAPICustomerClubAddAndSendUrl();
            $CustomerClubInsertAndSendMessage = $this->_execute($postData, $url, $token);
            $object = json_decode($CustomerClubInsertAndSendMessage);

            if (is_object($object)) {
                if ($object->IsSuccessful == true) {
                    $result = true;
                } else {
                    $result = false;
                }
            } else {
                $result = false;
            }
        } else {
            $result = false;
        }
        return $result;
    }

    /**
     * Gets token key for all web service requests.
     *
     * @return string Indicates the token key
     */
    private function _getToken()
    {
        $postData = array(
            'UserApiKey' => $this->_smsLogin,
            'SecretKey' => $this->_smsPassword,
            'System' => 'prestashop_v_3_1'
        );
        $postString = json_encode($postData);

        $ch = curl_init($this->_smsApiDomain.$this->getApiTokenUrl());
        curl_setopt(
            $ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json'
            )
        );
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postString);

        $result = curl_exec($ch);
        curl_close($ch);

        $response = json_decode($result);

        $resp = false;
        if (is_object($response)) {
            @$IsSuccessful = $response->IsSuccessful;
            if ($IsSuccessful == true) {
                @$TokenKey = $response->TokenKey;
                $resp = $TokenKey;
            } else {
                $resp = false;
            }
        }

        return $resp;
    }

    /**
     * Executes the main method.
     *
     * @param postData[] $postData array of json data
     * @param string     $url      url
     * @param string     $token    token string
     * 
     * @return string Indicates the curl execute result
     */
    private function _execute($postData, $url, $token)
    {
        $postString = json_encode($postData);

        $ch = curl_init($url);
        curl_setopt(
            $ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'x-sms-ir-secure-token: '.$token
            )
        );
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postString);

        $result = curl_exec($ch);
        curl_close($ch);

        return $result;
    }

    /**
     * Executes the main method.
     *
     * @param string $url   url
     * @param string $token token string
     * 
     * @return string Indicates the curl execute result
     */
    private function _executeCredit($url, $token)
    {
        $ch = curl_init($url);
        curl_setopt(
            $ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'x-sms-ir-secure-token: '.$token
            )
        );
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $result = curl_exec($ch);
        curl_close($ch);

        return $result;
    }

    /**
     * Check if mobile number is valid.
     *
     * @param string $mobile mobile number
     * 
     * @return boolean Indicates the mobile validation
     */
    public function isMobile($mobile)
    {
        if (preg_match('/^09(0[1-5]|1[0-9]|3[0-9]|2[0-2]|9[0-1])-?[0-9]{3}-?[0-9]{4}$/', $mobile)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Check if mobile with zero number is valid.
     *
     * @param string $mobile mobile with zero number
     * 
     * @return boolean Indicates the mobile with zero validation
     */
    public function isMobileWithz($mobile)
    {
        if (preg_match('/^9(0[1-5]|1[0-9]|3[0-9]|2[0-2]|9[0-1])-?[0-9]{3}-?[0-9]{4}$/', $mobile)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Set Sms Api Domain
     *
     * @param string $apidomain api domain
     * 
     * @return void
     */
    public function setSmsApiDomain($apidomain)
    {
        $this->_smsApiDomain = $apidomain;
    }
    
    /**
     * Set Sms Login
     *
     * @param string $login login
     * 
     * @return void
     */
    public function setSmsLogin($login)
    {
        $this->_smsLogin = $login;
    }

    /**
     * Set Sms Password
     *
     * @param string $password password
     * 
     * @return void
     */
    public function setSmsPassword($password)
    {
        $this->_smsPassword = $password;
    }

    /**
     * Set Sms Text
     *
     * @param string $text text
     * 
     * @return void
     */
    public function setSmsText($text)
    {
        $this->_sms_text = $text;
    }

    /**
     * Set Nums
     *
     * @param string $nums nums
     * 
     * @return void
     */
    public function setNums($nums)
    {
        $this->_t_nums = $nums;
    }

    /**
     * Set Sender
     *
     * @param string $sender sender
     * 
     * @return void
     */
    public function setSender($sender)
    {
        $this->_sender = $sender;
    }
    
    /**
     * Set Sender Customer Club
     *
     * @param string $SenderCC SenderCC
     * 
     * @return void
     */
    public function setSenderCC($SenderCC)
    {
        $this->SenderCC = $SenderCC;
    }
}
?>