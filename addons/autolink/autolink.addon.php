<?php
if(!defined('__KARYBU__')) exit();

/**
 * @file autolink.addon.php
 * @author Arnia (dev@karybu.org)
 * @brief Automatic link add-on
 **/
if($called_position == 'after_module_proc' && Context::getResponseMethod()!="XMLRPC") {
	Context::loadFile(array('./addons/autolink/autolink.js', 'body', '', null), true);
}
?>
