<?php
/**
 * @class  spamfilterAdminController
 * @author Arnia (dev@karybu.org)
 * @brief The admin controller class of the spamfilter module
 **/

class spamfilterAdminController extends spamfilter {

    /**
     * @brief Initialization
     **/
    function init() {
    }


    function procSpamfilterAdminInsertConfig() {

        // Get the default information
        $argsConfig = Context::gets('limits', 'check_trackback', 'usage', 'action_bad_words');
        $flag = Context::get('flag');
        //interval, limit_count
        if($argsConfig->check_trackback!='Y') $argsConfig->check_trackback = 'N';
        if($argsConfig->limits!='Y') $argsConfig->limits = 'N';
        // Create and insert the module Controller object
        $oModuleController = getController('module');
        $moduleConfigOutput = $oModuleController->insertModuleConfig('spamfilter',$argsConfig);
        if(!$moduleConfigOutput->toBool()) return $moduleConfigOutput;

        if (!in_array(Context::getRequestMethod(), array('XMLRPC', 'JSON'))) {
            $returnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'module', 'admin', 'act', 'dispSpamfilterAdminSetting');
            $this->setRedirectUrl($returnUrl);
        }
        return false;
    }

    function procSpamfilterAdminInsertDeniedIP(){
        //add ips to blacklist
        $ipaddress_list = Context::get('ipaddress_list');
        $oSpamfilterController = &getController('spamfilter');
        if($ipaddress_list){
            $insertIPOutput = $oSpamfilterController->insertIP($ipaddress_list);
            if(!$insertIPOutput->toBool()) return $insertIPOutput;
        }

        $returnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'module', 'admin', 'act', 'dispSpamfilterAdminSetting');
        $this->setRedirectUrl($returnUrl);
    }
    function procSpamfilterAdminInsertDeniedWord(){
        //Spam Add Keyword
        $word_list = Context::get('word_list');
        if($word_list){
            $insertWordOutput = $this->insertWord($word_list);
            if(!$insertWordOutput->toBool()) return $insertWordOutput;
        }
    }

    /**
     * @brief Updates akismet key
     */
    function procSpamfilterAdminUpdateAkismetApiKey() {
        if ($key = Context::get('akismet_api_key')) {
            $out = $this->saveAkismetKey($key);
            if (!$out->toBool()) return $out;
        }
        if (!in_array(Context::getRequestMethod(), array('XMLRPC', 'JSON'))) {
        $returnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'module', 'admin', 'act', 'dispSpamfilterAdminSetting');
            header('location:' . $returnUrl);
            return;
        }
        return false;
    }

    /**
     * @brief Delete the banned IP
     **/
    function procSpamfilterAdminDeleteDeniedIP() {
        $ipaddress = Context::get('ipaddress');
        $output = $this->deleteIP($ipaddress);

        $returnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'module', 'admin', 'act', 'dispSpamfilterAdminSetting');
        return $this->setRedirectUrl($returnUrl, $output);
    }

    /**
     * @brief Delete the prohibited Word
     **/
    function procSpamfilterAdminDeleteDeniedWord() {
        $word = Context::get('word');
        //$word = base64_decode(Context::get('word'));
        $output = $this->deleteWord($word);

        $returnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'module', 'admin', 'act', 'dispSpamfilterAdminSetting');
        return $this->setRedirectUrl($returnUrl, $output);
    }

    /**
     * @brief Delete IP
     * Remove the IP address which was previously registered as a spammers
     **/
    function deleteIP($ipaddress) {
        if(!$ipaddress) return;
        $args = new stdClass();
        $args->ipaddress = $ipaddress;
        return executeQuery('spamfilter.deleteDeniedIP', $args);
    }

    /**
     * @brief Register the spam word
     * The post, which contains the newly registered spam word, should be considered as a spam
     **/
    function insertWord($word_list) {
        $word_list = str_replace("\r","",$word_list);
        $word_list = explode("\n",$word_list);
        $args = new stdClass();
        foreach($word_list as $word) {
            if(trim($word)) $args->word = $word;
            $output = executeQuery('spamfilter.insertDeniedWord', $args);
            if(!$output->toBool()) return $output;
        }
        return $output;
    }

    /**
     * @brief Saves the Akismet user API key
     * @param $key The Akismet API key to be saved
     */
    function saveAkismetKey($key='') {
        $config = array('akismet_api_key' => $key);
        $oModuleController = getController('module');
        return $oModuleController->updateModuleConfig('spamfilter', $config);
    }

    /**
     * @brief Remove the spam word
     * Remove the word which was previously registered as a spam word
     **/
    function deleteWord($word) {
        if(!$word) return;
        $args = new stdClass();
        $args->word = $word;
        return executeQuery('spamfilter.deleteDeniedWord', $args);
    }

}
