<?php

class Bootstrap extends Zend_Application_Bootstrap_Bootstrap
{
    protected function _initAppSetup()
    {

        define('APP_VERSION', '7.2.2');
        $conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);
        $authNameSpace = new Zend_Session_Namespace('datamanagers');
        if (isset($authNameSpace->language) && $authNameSpace->language != "") {
            $locale = (!empty($authNameSpace->language) ? $authNameSpace->language : "en_US");
        } else {
            $locale = (!empty($conf->locale) ? $conf->locale : "en_US");
        }

        $timezone = (!empty($conf->timezone) ? $conf->timezone : "UTC");
        
        Zend_Session::start();
        date_default_timezone_set($timezone);
        $appLocale = new Zend_Locale($locale ?: 'en_US');
        Zend_Registry::set('Zend_Locale', $appLocale);

        /** @var Zend_Controller_Router_Rewrite $router */

        $router = Zend_Controller_Front::getInstance()->getRouter();

        $router->addRoute("captchaRoute", new Zend_Controller_Router_Route('captcha/:r', array('controller' => 'captcha', 'action' => 'index', 'r' => '')));
        $router->addRoute("downloadRoute", new Zend_Controller_Router_Route('d/:filepath', array('controller' => 'download', 'action' => 'index', 'filepath' => '')));
        $router->addRoute("checkCaptchaRoute", new Zend_Controller_Router_Route_Static('captcha/check-captcha', array('controller' => 'captcha', 'action' => 'check-captcha')));


        //Database Cache

        $session = new Zend_Session_Namespace('cacheSpace');
        if (isset($session->defaultCache)) {
            Zend_Db_Table_Abstract::setDefaultMetadataCache(unserialize($session->defaultCache));
        } else {
            $frontendOptions = array(
                'lifetime' => 7200000000,
                'automatic_serialization' => true
            );
            $backendOptions = array(
                'cache_dir' => APPLICATION_PATH . DIRECTORY_SEPARATOR . "cache" . DIRECTORY_SEPARATOR
            );
            $frontend = "Core";
            $backend = "File";

            $cache = Zend_Cache::factory($frontend, $backend, $frontendOptions, $backendOptions);
            $session->defaultCache = serialize($cache);
            Zend_Db_Table_Abstract::setDefaultMetadataCache($cache);
        }
    }

    protected function _initTranslate()
    {
        $locale = "en_US"; // default locale

        $authNameSpace = new Zend_Session_Namespace('datamanagers');

        if (isset($authNameSpace->language) && !empty(trim($authNameSpace->language))) {
            $locale = trim($authNameSpace->language);
        } else {
            $conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);
            if (isset($conf->locale) && !empty(trim($conf->locale))) {
                $locale = trim($conf->locale);
            }
        }

        $translate = new Zend_Translate(array(
            'adapter' => 'gettext',
            'content' => APPLICATION_PATH . DIRECTORY_SEPARATOR . "languages/$locale/$locale.mo",
            'locale'  => $locale
        ));

        Zend_Registry::set('Zend_Locale', $locale);
        Zend_Registry::set('translate', $translate);

        $this->bootstrap('view');
        $view = $this->getResource('view');
        $view->translate = $translate;
    }
}
