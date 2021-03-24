<?php

/**
 * Modules Main File
 * 
 * PHP version 5.6.x | 7.x | 8.x
 * 
 * @category  PLugins
 * @package   Prestashop
 * @author    Pejman Kheyri <pejmankheyri@gmail.com>
 * @copyright @copyright 2021 All rights reserved.
 */

if (!defined('_PS_VERSION_'))
    exit;

require_once _PS_MODULE_DIR_ . 'SMSIRModule/class.php';

/**
 * Main install class
 * 
 * @category  PLugins
 * @package   Prestashop
 * @author    Pejman Kheyri <pejmankheyri@gmail.com>
 * @copyright @copyright 2021 All rights reserved.
 */
class SMSIRModule extends Module
{
    private $_html = '';
    private $_postErrors = array();
    private $_hooks = array();
    private $_version = '';
    private $_ids = array();

    /**
     * Class Construction And Module Configuration
     *
     * @return void
     */
    function __construct()
    {
        $this->name = 'SMSIRModule';
        $this->version = '1.0.0';
        $this->author = 'Pejman Kheyri';
        $this->tab = 'emailing';
        $this->need_instance = 1;
        $this->bootstrap = true;
        $this->ps_versions_compliancy['min'] = '1.4.6';
        $this->_version = (version_compare(_PS_VERSION_, '1.5.0') >= 0) ? '1.5' : '1.4';
        $this->_hooks = array(
                            '1.5' => array(
                                    array('name' => 'actionCustomerAccountAdd'), 
                                    array('name' => 'actionValidateOrder'), 
                                    array('name' => 'actionOrderStatusPostUpdate'),
                                    array('name' => 'smsNotifierOrderMessage', 'add' => true , 'title' => 'Send message in order page')),
                             '1.4' => array(
                                    array('name' => 'createAccount'), 
                                    array('name' => 'newOrder'), 
                                    array('name' => 'postUpdateOrderStatus'),
                                    array('name' => 'smsNotifierOrderMessage', 'add' => true , 'title' => 'Send message in order page')) 
                             );

        $this->_initContext();
        parent::__construct();

        $this->displayName = $this->l('ارسال پیامک');
        $this->description = $this->l('ماژول ارسال پیامک به همراه امکان ارسال از طریق خطوط باشگاه مشتریان');
        $this->confirmUninstall = $this->l('ماژول ارسال پیامک حذف می شود، ادامه می دهید؟');

        /*$config = Configuration::getMultiple(array( 'SMSNOTIFIER_SERVICE',
                                                    'SMSNOTIFIER_PASSWORD', 
                                                    'SMSNOTIFIER_USERNAME', 
                                                    'SMSNOTIFIER_SENDER',
                                                    'SMSNOTIFIER_ADMIN_MOB', 
                                                    'SMSNOTIFIER_IS_FLASH'));
        foreach ($config as $con)
            if (!isset($conf))
            {
                $this->warning = $this->l('ماژول ارسال پیامک پیکربندی نشده است');
                break;
            }*/
    }

    /**
     * Init context method
     *
     * @return void
     */
    private function _initContext()
    {
        if (class_exists('Context'))
            $this->context = Context::getContext();
        else {
            global $smarty, $cookie, $language;
            $this->context = new StdClass();
            $this->context->smarty = $smarty;
            $this->context->cookie = $cookie;
            $this->context->language = $language;
        }
    }

    /**
     * Modules install method
     *
     * @return boolean
     */    
    public function install()
    {
        if (!parent::install() || !$this->_installDatabase() || !$this->_installConfig() || !$this->_installHooks() || !$this->_installFiles())
            return false;

        return true;
    }

    /**
     * Modules uninstall method
     *
     * @return boolean
     */  
    public function uninstall()
    {
        if (!parent::uninstall() || !$this->_uninstallDatabase() || !$this->_uninstallConfig() || !$this->_uninstallHooks() || !$this->_uninstallFiles())
            return false;
        return true;
    }

    /**
     * Modules install Database method
     *
     * @return boolean
     */
    private function _installDatabase()
    {
        // Add log table to database
        Db::getInstance()->Execute(
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ .
            'smsnotifier_logs` (
			  `id_smsnotifier_logs` int(10) unsigned NOT NULL auto_increment,
			  `id_customer` int(10) unsigned default NULL,
			  `recipient` varchar(100) NOT NULL,
			  `phone` varchar(16) NOT NULL,
			  `event` varchar(64) NOT NULL,
			  `message` text NOT NULL,
			  `status` tinyint(1) NOT NULL default \'0\',
			  `unique` varchar(255) default NULL,
              `error` varchar(255) default NULL,
			  `date_add` datetime NOT NULL,
			  PRIMARY KEY  (`id_smsnotifier_logs`)
            ) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci;'
        );
        Db::getInstance()->Execute(
            'ALTER TABLE `' . _DB_PREFIX_ . 'smsnotifier_logs` ENGINE=InnoDB;'
        );

        return true;
    }

    /**
     * Modules uninstall Database method
     *
     * @return boolean
     */
    private function _uninstallDatabase()
    {
        Db::getInstance()->Execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ .'smsnotifier_logs`');
        return true;
    }

    /**
     * Modules install Config method
     *
     * @return boolean
     */
    private function _installConfig()
    {
        return true;
    }

    /**
     * Modules uninstall Config method
     *
     * @return boolean
     */
    private function _uninstallConfig()
    {
        Db::getInstance()->Execute(
            'DELETE FROM `' . _DB_PREFIX_ . 'configuration`
            WHERE `name` like \'SMSNOTIFIER_%\''
        );
        Db::getInstance()->Execute(
            'DELETE FROM `' . _DB_PREFIX_ . 'configuration`
            WHERE `name` like \'SMS_TXT_%\''
        );
        Db::getInstance()->Execute(
            'DELETE FROM `' . _DB_PREFIX_ . 'configuration`
            WHERE `name` like \'SMS_ISACTIVE_%\''
        );
        Db::getInstance()->Execute(
            'DELETE FROM `' . _DB_PREFIX_ . 'configuration_lang`
			WHERE `id_configuration` NOT IN (SELECT `id_configuration` from `' .
            _DB_PREFIX_ . 'configuration`)'
        );
        return true;
    }

    /**
     * Modules install Hooks method
     *
     * @return boolean
     */
    private function _installHooks()
    {
        foreach ($this->_hooks[$this->_version] as $hook) {
            if (isset($hook['add']) and version_compare(_PS_VERSION_, '1.6.0') < 0 )
                Db::getInstance()->Execute('INSERT INTO `'._DB_PREFIX_.'hook` (name,title,description,position) VALUES ("'.$hook['name'].'", "'.$hook['title'].'", "", "0")');


            if (!$this->registerHook($hook['name']))
                return false;
        }
        return true;
    }

    /**
     * Modules uninstall Hooks method
     *
     * @return boolean
     */
    private function _uninstallHooks()
    {
        foreach ($this->_hooks[$this->_version] as $hook) {
            if (!$this->unregisterHook($hook['name']))
                return false;
                
            if (isset($hook['add']))
                Db::getInstance()->Execute('DELETE FROM `'._DB_PREFIX_.'hook` WHERE `name` like \''.$hook['name'].'%\'');
        }
        return true;
    }
    
    /**
     * Modules install Files method
     *
     * @return boolean
     */
    private function _installFiles()
    {
        if($this->_version == '1.4')
            $this->_modifyFile(
                _PS_ADMIN_DIR_.'/tabs/AdminOrders.php', 
                'smsNotifierOrderMessage', 
                "if (@Mail::Send((int)(\$order->id_lang), 'order_merchant_comment'", 
                "Module::hookExec('smsNotifierOrderMessage', array('customer' => '', 'order' => \$message->id_order, 'message' => \$message->message));\nif (@Mail::Send((int)(\$order->id_lang), 'order_merchant_comment'"
            );
        elseif($this->_version == '1.5')
            $this->_modifyFile(
                '../controllers/admin/AdminOrdersController.php', 'smsNotifierOrderMessage', 
                "if (@Mail::Send((int)\$order->id_lang, 'order_merchant_comment'",
                "Module::hookExec('smsNotifierOrderMessage', array('customer' => '', 'order' => \$order->id, 'message' => \$message));\nif (@Mail::Send((int)\$order->id_lang, 'order_merchant_comment'"
            );

        return true;
    }
    
    /**
     * Modules uninstall Files method
     *
     * @return boolean
     */
    private function _uninstallFiles()
    {
        if($this->_version == '1.4')
            $this->_restoreFile(_PS_ADMIN_DIR_.'/tabs/AdminOrders.php', 'smsNotifierOrderMessage');
        
        elseif($this->_version == '1.5')
             $this->_restoreFile('../controllers/admin/AdminOrdersController.php', 'smsNotifierOrderMessage');    

        return true;
    }

    /**
     * Modules modify Files method
     * 
     * @param string $path     file path
     * @param string $search   search string
     * @param string $replace1 replace first string
     * @param string $replace2 replace second string
     *
     * @return void
     */
    private function _modifyFile($path, $search, $replace1, $replace2)
    {
        if (file_exists($path)) {
            $fd = fopen($path, 'r');
            $contents = fread($fd, filesize($path));
            if (strpos($contents, $search) === false) {
                copy($path, $path . '-savedbysmsnotifier');
                $content2 = $contents;
                if (is_array($replace1) && is_array($replace2)) {
                    foreach ($replace1 as $key => $val1) {
                        $contents = str_replace($val1, $replace2[$key], $contents);
                    }
                } else
                    $contents = str_replace($replace1, $replace2, $contents);
                fclose($fd);
                //copy($path, $path . '-savedbysmsnotifier');
                $fd = fopen($path, 'w+');
                fwrite($fd, $contents);
                fclose($fd);
            } else {
                fclose($fd);
            }
        }
    }

    /**
     * Modules restore Files method
     * 
     * @param string $path   file path
     * @param string $search search string
     *
     * @return void
     */
    private function _restoreFile($path, $search)
    {
        if (file_exists($path. '-savedbysmsnotifier')) {
            @unlink($path);
            copy($path . '-savedbysmsnotifier', $path);
            @unlink($path. '-savedbysmsnotifier');
            /*$fd = fopen($path, 'r');
            $contents = fread($fd, filesize($path));
            if (is_array($search)) {
                foreach($search as $val) {
                    $contents = str_replace($val, "", $contents);
                }
            } else
                $contents = str_replace($search, "", $contents);

            fclose($fd);
            $fd = fopen($path, 'w+');
            fwrite($fd, $contents);
            fclose($fd);
            @unlink($path . '-savedbysmsnotifier');*/
        }
    }

    /**
     * Hook Action Customer Account Add
     * 
     * @param string $params parameters
     *
     * @return void
     */
    public function hookActionCustomerAccountAdd($params)
    {
        $sms = new SmsClass();
        $sms->send('actionCustomerAccountAdd', $params);
    }
    
    /**
     * Hook Create Account
     * 
     * @param string $params parameters
     *
     * @return void
     */
    public function hookCreateAccount($params)
    {
        $this->hookActionCustomerAccountAdd($params);
        return;
    }

    /**
     * Hook Action Validate Order
     * 
     * @param string $params parameters
     *
     * @return void
     */
    public function hookActionValidateOrder($params)
    {
        $sms = new SmsClass();
        $sms->send('actionValidateOrder', $params);
    }
    
    /**
     * Hook New Order
     * 
     * @param string $params parameters
     *
     * @return void
     */
    public function hookNewOrder($params)
    {
        $this->hookActionValidateOrder($params);
        return;
    }

    /**
     * Hook Action Order Status Post Update
     * 
     * @param string $params parameters
     *
     * @return void
     */
    public function hookActionOrderStatusPostUpdate($params)
    {
        $sms = new SmsClass();
        $sms->send('actionOrderStatusPostUpdate', $params);
    }
    
    /**
     * Hook Post Update Order Status
     * 
     * @param string $params parameters
     *
     * @return void
     */
    public function hookPostUpdateOrderStatus($params)
    {
        $this->hookActionOrderStatusPostUpdate($params);
        return;
    }
  
    /**
     * Hook Sms Notifier Order Message
     * 
     * @param string $params parameters
     *
     * @return void
     */
    public function hookSmsNotifierOrderMessage($params)
    {
        $sms = new SmsClass();
        $sms->send('smsNotifierOrderMessage', $params);
    }
    
    /**
     * Get Content
     * 
     * @return string HTML code
     */
    public function getContent()
    {
        if($this->_version == '1.5' or $this->_version == '1.6')
            foreach($this->_hooks[$this->_version] as $hook)
                $this->_ids[$hook['name']] = Hook::getIdByName($hook['name']);
        else
            foreach($this->_hooks[$this->_version] as $hook)
                $this->_ids[$hook['name']] = Hook::get($hook['name']);

        $this->_html .= $this->_headerHTML();
        $this->_html .= '<h2>'.$this->displayName.'</h2>';

        /* Validate & process */
        if (Tools::isSubmit('submitSmsnotifier') || Tools::isSubmit('submitSmsnotifierText')) {
            if ($this->_postValidation())
                $this->_postProcess();
            $this->_displayForm();
        } elseif (Tools::isSubmit('submitSmsnotifierSend')) {
            if (Tools::getValue('SMSNOTIFIER_MESSAGE_CUSTOMER') != '') {
                $text = Tools::getValue('SMSNOTIFIER_MESSAGE_CUSTOMER');
                $numbers = $this->_getNumbers();
                $this->_postProcessSend($text, $numbers);
            } else 
                $this->_html .= '<div class="alert error">متن پیام ارسال را وارد کنید</div>';

            $this->_displayForm();
        } elseif (Tools::isSubmit('submitSmsnotifierSendcustomerclub')) {
            if (Tools::getValue('SMSNOTIFIER_MESSAGE_ALL_CUSTOMERCLUB') != '') {
                $text = Tools::getValue('SMSNOTIFIER_MESSAGE_ALL_CUSTOMERCLUB');
                $this->_postProcessSendCustomerClub($text);
            } else 
                $this->_html .= '<div class="alert error">متن پیام ارسال را وارد کنید</div>';

            $this->_displayForm();
        } elseif (Tools::isSubmit('submitSmsnotifierSendONE')) {
            if (Tools::getValue('SMSNOTIFIER_MESSAGE_MOBILE') != '' or Tools::getValue('SMSNOTIFIER_MOBILE') != '') {
                $text = Tools::getValue('SMSNOTIFIER_MESSAGE_MOBILE');
                $numbers = Tools::getValue('SMSNOTIFIER_MOBILE');
                $this->_postProcessSend($text, $numbers);
            } else 
                $this->_html .= '<div class="alert error">متن پیامک و شماره همراه را وارد نمایید.</div>';

            $this->_displayForm();
        } else
            $this->_displayForm();

        return $this->_html;
    }
 
    /**
     * Services list
     * 
     * @return string HTML code
     */
    private function _servicesList()
    {
        $path = _PS_MODULE_DIR_.$this->name.'/services';
        $m = '<div class="margin-form" style="min-height:20px;">';
        if ($handle = opendir($path)) {
            $selected = Tools::getValue('SMSNOTIFIER_SERVICE', Configuration::get('SMSNOTIFIER_SERVICE'));
            $m .= '<select name="SMSNOTIFIER_SERVICE" style="min-width:155px; padding: 2px 2px;display: none;">';
            //$m .= '<option value="0"> انتخاب کنید </option>';
            $m .= '<option value="Class_Smsir" selected="selected">'. $name .'</option>';           
            $m .= '</select>'; 
        }//end if
        $m .= '</div>';
        
        return $m;
    }
    
    /**
     * Display Form
     * 
     * @return string HTML code
     */
    private function _displayForm()
    {
        foreach ($this->_ids as $name => $id) {    
            $check[$name] = (Tools::getValue('SMS_ISACTIVE_'.$id, Configuration::get('SMS_ISACTIVE_'.$id)) == 1) ? 'checked="checked"' : "";
            $check_admin[$name] = (Tools::getValue('SMS_ISACTIVE_'.$id.'_ADMIN', Configuration::get('SMS_ISACTIVE_'.$id.'_ADMIN')) == 1) ? 'checked="checked"' : "";
        }

        $sms = new SmsClass();
        $this->_html .= 'برای تهیه پنل جهت ارسال پیامک به وب سایت <a href="http://sms.ir/" target="_blank">sms.ir</a> مراجعه کنید.<br><br>';
        $this->_html .= '
		<div class="row row-margin-bottom">
			<div class="col-md-6" style=" padding: 20px 10px; margin: 10px 0;">
			<legend style="background-color: #ffffff;padding: 10px;"><img src="'._PS_BASE_URL_.__PS_BASE_URI__.'modules/'.$this->name.'/logo.png" alt="" /> تنظیمات پنل sms.ir '; 

        if ($sms->getCredit() > 0) {
            $this->_html .= '( اعتبار پنل : ';
            $this->_html .= $sms->getCredit();
            $this->_html .= ' پیامک )';      
        }
        $this->_html .= '</legend>';

        $this->_html .= '<form action="'.Tools::safeOutput($_SERVER['REQUEST_URI']).'" method="post">';
        
        //service
        //$this->_html .= '<label>سرویس دهنده : </label>';
        $this->_html .= $this->_servicesList();

        //API domain 
        $this->_html .= '<div class="col-md-6">';
        $this->_html .= '
		<label>لینک وب سرویس مانند : https://ws.sms.ir/</label>
			<input class="form-control" type="text" name="SMSNOTIFIER_APIDOMAIN" id="apidomain" size="25" value="'.htmlentities(Tools::getValue('SMSNOTIFIER_APIDOMAIN', Configuration::get('SMSNOTIFIER_APIDOMAIN'))).'" />';
        $this->_html .= '</div>';        
        //username
        $this->_html .= '<div class="col-md-6">';
        $this->_html .= '
		<label><a href="http://ip.sms.ir/#/UserApiKey" target="_blank">کلید وب سرویس : </a></label>
			<input class="form-control" type="text" name="SMSNOTIFIER_USERNAME" id="username" size="25" value="'.htmlentities(Tools::getValue('SMSNOTIFIER_USERNAME', Configuration::get('SMSNOTIFIER_USERNAME'))).'" />';
        $this->_html .= '</div>';
        $this->_html .= '<div class="col-md-6">';
        //password
        $this->_html .= '
		<label><a href="http://ip.sms.ir/#/UserApiKey" target="_blank">کد امنیتی : </a></label>
			<input class="form-control" type="password" name="SMSNOTIFIER_PASSWORD" id="password" size="25" value="'.htmlentities(Tools::getValue('SMSNOTIFIER_PASSWORD', Configuration::get('SMSNOTIFIER_PASSWORD'))).'" />';
        $this->_html .= '</div>';
        //sender
        $sms_customerclub_status = (htmlentities(Tools::getValue('SMSNOTIFIER_SENDER_CUSTOMERCLUB', Configuration::get('SMSNOTIFIER_SENDER_CUSTOMERCLUB')))=="1")? 'checked="checked"' : "";
        $this->_html .= '<div class="col-md-6">';
        $this->_html .= '
		<label><a href="http://ip.sms.ir/#/UserSetting" target="_blank">خط ارسال کننده : </a></label>
			<input class="form-control" type="text" name="SMSNOTIFIER_SENDER" id="sender" size="25" value="'.htmlentities(Tools::getValue('SMSNOTIFIER_SENDER', Configuration::get('SMSNOTIFIER_SENDER'))).'" /> <br>
			<input type="checkbox" id="SMSNOTIFIER_SENDER_CUSTOMERCLUB" name="SMSNOTIFIER_SENDER_CUSTOMERCLUB" '.$sms_customerclub_status.' value="1" /> 
			<label for="SMSNOTIFIER_SENDER_CUSTOMERCLUB">درصورتیکه شماره ارسالی شما شماره باشگاه مشتریانتان است این گزینه را حتما باید انتخاب نمایید.</label>';
        $this->_html .= '</div>';
        $this->_html .= '<div class="col-md-6">';
        //admin phone
        $this->_html .= '
		<label>شماره مدیر فروشگاه : </label>
		<input class="form-control" type="text" name="SMSNOTIFIER_ADMIN_MOB" id="sender" size="25" value="'.htmlentities(Tools::getValue('SMSNOTIFIER_ADMIN_MOB', Configuration::get('SMSNOTIFIER_ADMIN_MOB'))).'" />';
        $this->_html .= '</div>';
        //admin phone

        // save
        $this->_html .= '<input style="margin: 10px;" type="submit" class="btn btn-primary" name="submitSmsnotifier" value="ذخیره" />';       
        $this->_html .= '</div>';
        $this->_html .= '<div class="col-md-6" style="padding: 20px 10px; margin: 10px 0;">
				<legend style="background-color: #ffffff;padding: 10px;"><img src="'._PS_BASE_URL_.__PS_BASE_URI__.'modules/'.$this->name.'/logo.png" alt="" /> ارسال پیامک به همه مخاطبین باشگاه مشتریان </legend>';   
        $this->_html .= '
		<label>متن پیامک : </label>
			<textarea class="form-control" style="overflow: auto" name="SMSNOTIFIER_MESSAGE_ALL_CUSTOMERCLUB" rows="4" cols="80"></textarea>';
        $numbers = $this->_getNumbers();
        $this->_html .= '
		<div class="margin-form">  این گزینه با استفاده از شماره باشگاه مشتریان پنل شما در sms.ir به همه مخاطبین شما ارسال پیامک انجام می دهد.  </div>';
        $this->_html .= '<input style="margin: 10px;" type="submit" class="btn btn-primary" name="submitSmsnotifierSendcustomerclub" value="ارسال پیامک به همه مخاطبین باشگاه" />';

        $this->_html .= '</div></div>';    

        $this->_html .= '<br /><br />';
        $this->_html .= '
		<div class="row row-margin-bottom">
			<div class="col-md-6" style="padding: 20px 10px; margin: 10px 0;">
				<legend style="background-color: #ffffff;padding: 10px;"><img src="'._PS_BASE_URL_.__PS_BASE_URI__.'modules/'.$this->name.'/logo.png" alt="" /> ارسال پیامک به همه مشتریان </legend>';   
        $this->_html .= '
		<label>متن پیامک : </label>
			<textarea class="form-control" style="overflow: auto" name="SMSNOTIFIER_MESSAGE_CUSTOMER" rows="4" cols="80"></textarea>';
        $numbers = $this->_getNumbers();
        $this->_html .= '
		<div class="margin-form">تعداد <b>'. count($numbers) .'</b> شماره موبایل از مشتریان پیدا شد.</div>';
        $this->_html .= '<input style="margin: 10px;" type="submit" class="btn btn-primary" name="submitSmsnotifierSend" value="ارسال پیامک به همه مشتریان" />';

        $this->_html .= '</div>'; 
        $this->_html .= '<div class="col-md-6" style="padding: 20px 10px; margin: 10px 0;">';
        $this->_html .= '
				<legend style="background-color: #ffffff;padding: 10px;"><img src="'._PS_BASE_URL_.__PS_BASE_URI__.'modules/'.$this->name.'/logo.png" alt="" /> ارسال پیامک به مشتریان خاص </legend>';   
        $this->_html .= '
		<label>شماره همراه : </label>
			<input class="form-control" type="text" name="SMSNOTIFIER_MOBILE" /> 
			شماره های همراه را با , از همدیگر جدا کنید. مثال : 09123456789,09111111111<br>';
        $this->_html .= '
		<label>متن پیامک : </label>
			<textarea class="form-control" name="SMSNOTIFIER_MESSAGE_MOBILE"></textarea>';

        $this->_html .= '<input style="margin: 10px;" type="submit" class="btn btn-primary" name="submitSmsnotifierSendONE" value="ارسال پیامک" />';

        $this->_html .= '</div>';
        $this->_html .= '
		<div class="row row-margin-bottom">
			<div class="col-md-12">
			<legend style="background-color: #ffffff;padding: 10px;"><img src="'._PS_BASE_URL_.__PS_BASE_URI__.'modules/'.$this->name.'/logo.png" alt="" /> تنظیمات پیامک ها </legend>';
        
        //
        $this->_html .= '
			<div id="smstext" style=" margin-top: 30px;">';
        
        $helper = array(
            '1.5' => array(
                'actionCustomerAccountAdd' => array('name' =>'ایجاد حساب کاربری جدید', 'admin' => true, 'tags' => ' {firstname}, {lastname}, {email}, {passwd}, {shopname}, {shopurl}'), 
                'actionValidateOrder' => array('name' =>'ثبت سفارش جدید', 'admin' => true, 'tags' => ' {firstname}, {lastname}, {order_id}, {payment}, {total_paid}, {currency}, {shopname}, {shopurl}'), 
                'actionOrderStatusPostUpdate' => array('name' =>'تغییر وضعیت سفارش', 'admin' => false, 'tags' => ' {firstname}, {lastname}, {order_id}, {order_state}, {shopname}, {shopurl}'), 
                'smsNotifierOrderMessage' => array('name' =>'ارسال پیام در صفحه سفارش', 'admin' => false, 'tags' => ' {firstname}, {lastname}, {order_id}, {message}, {shopname}, {shopurl}')
            ),
            '1.4' => array(
                'createAccount' => array('name' =>'ایجاد حساب کاربری جدید', 'admin' => true, 'tags' => ' {firstname}, {lastname}, {email}, {passwd}, {shopname}, {shopurl}'), 
                'newOrder' => array('name' =>'ثبت سفارش جدید', 'admin' => true, 'tags' => ' {firstname}, {lastname}, {order_id}, {payment}, {total_paid}, {currency}, {shopname}, {shopurl}'),  
                'postUpdateOrderStatus' => array('name' =>'تغییر وضعیت سفارش', 'admin' => false, 'tags' => ' {firstname}, {lastname}, {order_id}, {order_state}, {shopname}, {shopurl}'), 
                'smsNotifierOrderMessage' => array('name' =>'ارسال پیام در صفحه سفارش', 'admin' => false, 'tags' => ' {firstname}, {lastname}, {order_id}, {message}, {shopname}, {shopurl}')
            )
        );

        foreach ($this->_ids as $name => $id) {
            if ($helper[$this->_version][$name]['admin'])
                $this->_html .='
				<div class="col-md-4">
			     <div id="box_admin_'.$id.'">
			         <p style="float: right">
						<div style="padding-bottom: 4px">
							<input type="checkbox" name="SMS_ISACTIVE_'.$id.'_ADMIN" id="SMS_ISACTIVE_'.$id.'_ADMIN" value="1" '.$check_admin[$name].'><label for="SMS_ISACTIVE_'.$id.'_ADMIN">فعال باشد ؟</label>
						</div>
                        <label for="text_admin_'.$id.'">'.$helper[$this->_version][$name]['name'].' <strong style="color:#C55;">برای مدیر</strong> </label>	
						<textarea class="form-control" id="text_admin_'.$id.'" style="overflow: auto" name="SMS_TXT_'.$id.'_ADMIN" rows="4" cols="80">'.htmlentities(Tools::getValue('SMS_TXT_'.$id.'_ADMIN', Configuration::get('SMS_TXT_'.$id.'_ADMIN')), ENT_COMPAT, 'UTF-8').'</textarea>
						<div id="help_admin_'.$id.'" class="clear" style="padding-top: 4px; padding-bottom: 10px;">تگ های مجاز </br>'.$helper[$this->_version][$name]['tags'].' </div>
                    </p>
				</div></div>';
            
            if (!($name == 'smsNotifierOrderMessage'))
                $this->_html .= '
				<div class="col-md-4">
			     <div id="box_'.$id.'">
			         <p style="float: right">
						<div style="padding-bottom: 4px">
							<input type="checkbox" name="SMS_ISACTIVE_'.$id.'" id="SMS_ISACTIVE_'.$id.'" value="1" '.$check[$name].'><label for="SMS_ISACTIVE_'.$id.'">فعال باشد ؟</label>
						</div>
                        <label for="text_'.$id.'">'.$helper[$this->_version][$name]['name'].' <strong style="color:#5A5;">برای کاربر</strong> </label>
						<textarea class="form-control" id="text_'.$id.'" style="overflow: auto" name="SMS_TXT_'.$id.'" rows="4" cols="80">'.htmlentities(Tools::getValue('SMS_TXT_'.$id, Configuration::get('SMS_TXT_'.$id)), ENT_COMPAT, 'UTF-8').'</textarea>
						<div id="help_'.$id.'" class="clear" style="padding-top: 4px; padding-bottom: 10px;">تگ های مجاز </br>'.$helper[$this->_version][$name]['tags'].' </div>
                    </p>
				</div></div>';
        }
        $this->_html .= '<br><div class="col-md-4"><input style="float: left;" type="submit" class="btn btn-primary" name="submitSmsnotifier" value="ذخیره تنظیمات" /></div>';
        $this->_html .= '</div>';  
        $this->_html .= '</form>';
        $this->_html .= '<div style="padding:10px; margin:5px; text-align: center; font: 11px tahoma;">
	        </div>';
        $this->_html .= '</div>';
    }
    
    /**
     * Header HTML
     * 
     * @return void
     */
    private function _headerHTML()
    {
        return ;
    }

    /**
     * Post Validation
     * 
     * @return boolean
     */
    private function _postValidation()
    {    
        if (!Tools::getValue('SMSNOTIFIER_SERVICE'))
            $this->_postErrors[] = 'سرویس دهنده انتخاب نشده است';
            
        if (!Tools::getValue('SMSNOTIFIER_USERNAME') or !Validate::isString(Tools::getValue('SMSNOTIFIER_USERNAME')))
            $this->_postErrors[] = 'کلید وب سرویس وارد شده نامعتبر است';
            
        if (!Tools::getValue('SMSNOTIFIER_PASSWORD') or !Validate::isString(Tools::getValue('SMSNOTIFIER_PASSWORD')))
            $this->_postErrors[] = 'کد امنیتی وارد شده نامعتبر است';
            
        if (!Tools::getValue('SMSNOTIFIER_SENDER') or !Validate::isString(Tools::getValue('SMSNOTIFIER_SENDER')))
            $this->_postErrors[] = 'شماره اختصاصی وارد شده نامعتبر است';
        
        if (!Tools::getValue('SMSNOTIFIER_ADMIN_MOB') or !Validate::isString(Tools::getValue('SMSNOTIFIER_ADMIN_MOB')))
            $this->_postErrors[] = 'شماره ای که برای مدیر وارد شده نامعتبر است';
                        
        foreach ($this->_ids as $name => $id) {
            if(!Validate::isCleanHtml(Tools::getValue('SMS_TXT_'.$id.'_ADMIN')))
                $this->_postErrors[] = 'داده های وارد شده به عنوان متن پیامک نامعتبر هستند، لطفا دوباره آن ها را بررسی کنید';
        
            if(!Validate::isCleanHtml(Tools::getValue('SMS_TXT_'.$id)))
                $this->_postErrors[] = 'داده های وارد شده به عنوان متن پیامک نامعتبر هستند، لطفا دوباره آن ها را بررسی کنید';
        }

        if(!count($this->_postErrors))
            return true;

        foreach ($this->_postErrors as $err)
            $this->_html .= '<div class="alert error">'.$err.'</div>';

        return false;
    }

    /**
     * Post Process
     * 
     * @return void
     */
    private function _postProcess()
    {
        $helper = array(
            '1.5' => array(
                'actionCustomerAccountAdd' => array('name' =>'ایجاد حساب کاربری جدید', 'admin' => true, 'tags' => ' {firstname}, {lastname}, {shopname}, {shopurl}'), 
                'actionValidateOrder' => array('name' =>'ثبت سفارش جدید', 'admin' => true, 'tags' => ' {firstname}, {lastname}, {order_id}, {payment}, {total_paid}, {currency}, {shopname}, {shopurl}'), 
                'actionOrderStatusPostUpdate' => array('name' =>'تغییر وضعیت سفارش به ارسال شده', 'admin' => false, 'tags' => ' {firstname}, {lastname}, {order_id}, {order_state}, {shopname}, {shopurl}'), 
                'smsNotifierOrderMessage' => array('name' =>'ارسال پیام در صفحه سفارش', 'admin' => false, 'tags' => ' {firstname}, {lastname}, {order_id}, {message}, {shopname}, {shopurl}')
            ),
            '1.4' => array(
                'createAccount' => array('name' =>'ایجاد حساب کاربری جدید', 'admin' => true, 'tags' => ' {firstname}, {lastname}, {shopname}, {shopurl}'), 
                'newOrder' => array('name' =>'ثبت سفارش جدید', 'admin' => true, 'tags' => ' {firstname}, {lastname}, {order_id}, {payment}, {total_paid}, {currency}, {shopname}, {shopurl}'),  
                'postUpdateOrderStatus' => array('name' =>'تغییر وضعیت سفارش به ارسال شده', 'admin' => false, 'tags' => ' {firstname}, {lastname}, {order_id}, {order_state}, {shopname}, {shopurl}'), 
                'smsNotifierOrderMessage' => array('name' =>'ارسال پیام در صفحه سفارش', 'admin' => false, 'tags' => ' {firstname}, {lastname}, {order_id}, {message}, {shopname}, {shopurl}')
            )
        );

        if (Configuration::updateValue('SMSNOTIFIER_SERVICE', Tools::getValue('SMSNOTIFIER_SERVICE')) 
            AND Configuration::updateValue('SMSNOTIFIER_APIDOMAIN', Tools::getValue('SMSNOTIFIER_APIDOMAIN')) 
            AND Configuration::updateValue('SMSNOTIFIER_USERNAME', Tools::getValue('SMSNOTIFIER_USERNAME')) 
            AND Configuration::updateValue('SMSNOTIFIER_PASSWORD', Tools::getValue('SMSNOTIFIER_PASSWORD')) 
            AND Configuration::updateValue('SMSNOTIFIER_SENDER', Tools::getValue('SMSNOTIFIER_SENDER')) 
            AND Configuration::updateValue('SMSNOTIFIER_SENDER_CUSTOMERCLUB', Tools::getValue('SMSNOTIFIER_SENDER_CUSTOMERCLUB')) 
            AND Configuration::updateValue('SMSNOTIFIER_ADMIN_MOB', Tools::getValue('SMSNOTIFIER_ADMIN_MOB')) 
        ) {
            $res = true;
            foreach ($this->_ids as $name => $id) {
                if ($helper[$this->_version][$name]['admin']) {
                    if (!Configuration::updateValue('SMS_TXT_'.$id.'_ADMIN', Tools::getValue('SMS_TXT_'.$id.'_ADMIN')) 
                        OR !Configuration::updateValue('SMS_ISACTIVE_'.$id.'_ADMIN', Tools::getValue('SMS_ISACTIVE_'.$id.'_ADMIN'))
                    )
                        $res = false;
                }

                if (!Configuration::updateValue('SMS_TXT_'.$id, Tools::getValue('SMS_TXT_'.$id)) 
                    OR !Configuration::updateValue('SMS_ISACTIVE_'.$id, Tools::getValue('SMS_ISACTIVE_'.$id))
                )
                           $res = false;
            }
            if ($res)
                $this->_html .= $this->displayConfirmation('تنظیمات بروز شد');
            else
                $this->_html .= $this->displayErrors('خطایی رخ داده است، لطفا دوباره تلاش کنید'); 
        } else
            $this->_html .= $this->displayErrors('خطایی رخ داده است، لطفا دوباره تلاش کنید'); // an Error occured
    }
    
    /**
     * Post Process Send
     * 
     * @param string $text    text
     * @param array  $numbers numbers
     * 
     * @return void
     */
    private function _postProcessSend($text, $numbers)
    {
        if ($text != '') {
            $sms = new SmsClass();
            $res = $sms->sendMessageAllCustomer(array('numbers'=>$numbers,'text'=>$text));
            if ($res) {
                $this->_html .= $this->displayConfirmation('عمليات با موفقيت انجام شده است.');
            } else
                $this->_html .= $this->displayConfirmation('اشکال در ارسال پیامک');
        }
    }

    /**
     * Post Process Send Customer Club
     * 
     * @param string $text text message
     * 
     * @return boolean
     */
    private function _postProcessSendCustomerClub($text)
    {
        if ($text != '') {
            $sms = new SmsClass();
            $res = $sms->sendMessageAllCustomerClub(array('text'=>$text));
            if ($res) {
                $this->_html .= $this->displayConfirmation('عمليات با موفقيت انجام شده است.');
            } else
                $this->_html .= $this->displayConfirmation('اشکال در ارسال پیامک');
        }
    }

    /**
     * Get Numbers
     * 
     * @return array numbers list
     */
    private function _getNumbers()
    {
        $numbers = array();
        $result = Db::getInstance()->executeS('SELECT phone_mobile FROM '._DB_PREFIX_.'address');
        foreach ($result as $res) {
            if ($res['phone_mobile'] != '' and !in_array($res['phone_mobile'], $numbers)) {
                $numbers[] = $res['phone_mobile'];
            }
        }
        return $numbers;
    } 
}
?>