<?php
    /**
    * @class ModuleHandler
    * @author NHN (developers@xpressengine.com)
    * Handling modules
    *
    * @remarks This class is to excute actions of modules.
    *          Constructing an instance without any parameterconstructor, it finds the target module based on Context.
    *          If there is no act on the found module, excute an action referencing action_forward.
    **/

    require_once 'ModuleMatcher.class.php';

    class ModuleHandler extends Handler {

        var $module = NULL; ///< Module
        var $act = NULL; ///< action
        var $mid = NULL; ///< Module ID
        var $document_srl = NULL; ///< Document Number
        var $module_srl = NULL; ///< Module Number

        var $module_info = NULL; ///< Module Info. Object

        var $error = NULL; ///< an error code.
        var $httpStatusCode = NULL; ///< http status code.

        /**
         * prepares variables to use in moduleHandler
		 * @param string $module name of module
		 * @param string $act name of action
		 * @param int $mid
		 * @param int $document_srl
		 * @param int $module_srl
		 * @return void
         **/
        function ModuleHandler($module = '', $act = '', $mid = '', $document_srl = '', $module_srl = '') {
            // If XE has not installed yet, set module as install
            if(!Context::isInstalled()) {
                $this->module = 'install';
                $this->act = Context::get('act');
                return;
            }

			$oContext = Context::getInstance();
			if($oContext->isSuccessInit == false)
			{
				$this->error = 'msg_invalid_request';
				return;
			}

            // Set variables from request arguments
            $this->module = $module?$module:Context::get('module');
            $this->act    = $act?$act:Context::get('act');
            $this->mid    = $mid?$mid:Context::get('mid');
            $this->document_srl = $document_srl?(int)$document_srl:(int)Context::get('document_srl');
            $this->module_srl   = $module_srl?(int)$module_srl:(int)Context::get('module_srl');
            $this->entry  = Context::convertEncodingStr(Context::get('entry'));

            // Validate variables to prevent XSS
			$isInvalid = null;
            if($this->module && !preg_match("/^([a-z0-9\_\-]+)$/i",$this->module)) $isInvalid = true;
            if($this->mid && !preg_match("/^([a-z0-9\_\-]+)$/i",$this->mid)) $isInvalid = true;
            if($this->act && !preg_match("/^([a-z0-9\_\-]+)$/i",$this->act)) $isInvalid = true;
			if ($isInvalid)
			{
				htmlHeader();
				echo Context::getLang("msg_invalid_request");
				htmlFooter();
				Context::close();
				exit;
			}

			if(isset($this->act) && substr($this->act, 0, 4) == 'disp')
			{
				if(Context::get('_use_ssl') == 'optional' && Context::isExistsSSLAction($this->act) && $_SERVER['HTTPS'] != 'on')
				{
					header('location:https://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']);
					return;
				}
			}

            // execute addon (before module initialization)
            $called_position = 'before_module_init';
            $oAddonController = &getController('addon');
            $addon_file = $oAddonController->getCacheFilePath(Mobile::isFromMobilePhone()?'mobile':'pc');
            @include($addon_file);
        }

        /**
         * Initialization. It finds the target module based on module, mid, document_srl, and prepares to execute an action
         * @return boolean true: OK, false: redirected
         **/
        function init() {
            $oModuleModel = &getModel('module');
            $site_module_info = Context::get('site_module_info');
            $oDocumentModel = &getModel('document');
            $db_info = Context::getDBInfo();

            $moduleMatcher = new ModuleMatcher();
            try
            {
                $found_module_info = $moduleMatcher->getModuleInfo($this->module
                                                                    , $this->act
                                                                    , $this->mid
                                                                    , $this->document_srl
                                                                    , $this->module_srl
                                                                    , $this->entry
                                                                    , $oModuleModel
                                                                    , $site_module_info
                                                                    , $oDocumentModel
                                                                    , $db_info->default_url);
            }
            catch(MidMismatchException $e)
            {
                header('location:' . getNotEncodedSiteUrl($site_module_info->domain, 'mid', $e->getMid(), 'document_srl', $e->getDocumentSrl()));
            }
            catch(DefaultModuleSiteSrlMismatchException $e)
            {
                header("location:".getNotEncodedSiteUrl($e->getDomain(),'mid',$e->getMid()));
            }
            catch(SiteSrlMismatchException $e)
            {
                getNotEncodedSiteUrl($e->getDomain()
                    , 'mid',$e->getMid()
                    ,'document_srl',$e->getDocumentSrl()
                    ,'module_srl',$e->getModuleSrl()
                    ,'entry',$e->getEntry());
            }
            catch(DefaultUrlNotDefined $e)
            {
                return Context::getLang('msg_default_url_is_not_defined');
            }
            catch(ModuleDoesNotExistException $e)
            {
                $this->error = 'msg_module_is_not_exists';
                $this->httpStatusCode = '404';
            }
            catch(Exception $e)
            {
                $this->error = 'An error occurred';
                $this->httpStatusCode = '500';
            }

            $this->document_srl = $found_module_info->document_srl;
            $this->module_info = $found_module_info->module_info;
            $this->module = $found_module_info->module;
            $this->mid = $found_module_info->mid;

            Context::setBrowserTitle($this->module_info->browser_title);

            if($this->module_info->use_mobile && Mobile::isFromMobilePhone())
            {
                $layoutSrl = $this->module_info->mlayout_srl;
            }
            else
            {
                $layoutSrl = $this->module_info->layout_srl;
            }

            $part_config= $oModuleModel->getModulePartConfig('layout',$layoutSrl);
            Context::addHtmlHeader($part_config->header_script);


            if($this->document_srl) Context::set('document_srl', $this->document_srl);
            // If mid exists, set mid into context
            if($this->mid) Context::set('mid', $this->mid, true);


            // Call a trigger after moduleHandler init
            $output = ModuleHandler::triggerCall('moduleHandler.init', 'after', $this->module_info);
            if(!$output->toBool()) {
                $this->error = $output->getMessage();
                return false;
            }

            // Set current module info into context
            Context::set('current_module_info', $this->module_info);

            return true;
        }

        private function showErrorToUser()
        {
            $type = Mobile::isFromMobilePhone() ? 'mobile' : 'view';
            $oMessageObject = &ModuleHandler::getModuleInstance('message',$type);
            $oMessageObject->setError(-1);
            $oMessageObject->setMessage($this->error);
            $oMessageObject->dispMessage();
            if($this->httpStatusCode)
            {
                $oMessageObject->setHttpStatusCode($this->httpStatusCode);
            }
            return $oMessageObject;
        }

        /**
         * get a module instance and execute an action
         * @return ModuleObject executed module instance
         **/
        function procModule() {

            // If error occurred while preparation, return a message instance
            if($this->error) {
                return $this->showErrorToUser();
            }

            if($this->module_info->use_mobile != "Y") Mobile::setMobile(false);

            $oModuleModel = &getModel('module');
            // Get action information with conf/module.xml
            $xml_info = $oModuleModel->getModuleActionXml($this->module);

            // Get info necessary to retrieve the module's instance
            $module_matcher = new ModuleMatcher();
            try
            {
                $module_key = $module_matcher->getModuleKey($this->act, $this->module, $xml_info, Mobile::isFromMobilePhone(),  Context::isInstalled());
            }
            catch(ModuleDoesNotExistException $e)
            {
                $this->error = 'msg_module_is_not_exists';
                $this->httpStatusCode = '404';

                return $this->showErrorToUser();
            }

            // If kind is admin, do some initialization
            if($module_key->isAdmin())
            {
                $this->initAdminMenu();
                if($_SESSION['denied_admin'] == 'Y')
                {
                    $this->error = "msg_not_permitted_act";
                    return $this->showErrorToUser();
                }
            }

            // Get the instance
            $oModule = $this->getModuleInstanceFromKey($module_key);

            // If the module still wasn't found, we return
			if(!is_object($oModule)) {
                return $this->showErrorToUser();
			}

			// If there is no such action in the module object
			if(!isset($xml_info->action->{$this->act}) || !method_exists($oModule, $this->act))
			{

				if(!Context::isInstalled())
				{
					$this->error = 'msg_invalid_request';
                    return $this->showErrorToUser();
				}

                $forward = null;
				// 1. Look for the module specified in the action name (dispPageIndex -> page)
                if(preg_match('/^([a-z]+)([A-Z])([a-z0-9\_]+)(.*)$/', $this->act, $matches)) {
                    $module = strtolower($matches[2].$matches[3]);
                    $xml_info = $oModuleModel->getModuleActionXml($module);
                    if($xml_info->action->{$this->act}) {
                        $forward->module = $module;
                        $forward->type = $xml_info->action->{$this->act}->type;
            			$forward->ruleset = $xml_info->action->{$this->act}->ruleset;
                        $forward->act = $this->act;
                    }
                }

                // 2. If it still wasn't found, look for the module in the database
				if(!$forward)
				{
					$forward = $oModuleModel->getActionForward($this->act);
				}

                // 3.1. If forward was found ..
                if($forward->module && $forward->type && $forward->act && $forward->act == $this->act) {
                    $xml_info = $oModuleModel->getModuleActionXml($forward->module);
                    $forward_module_key = $module_matcher->getModuleKey($forward->act, $forward->module, $xml_info, Mobile::isFromMobilePhone(), Context::isInstalled());

					$ruleset = $forward->ruleset;
					$tpl_path = $oModule->getTemplatePath();
					$orig_module = $oModule;

                    $oModule = &$this->getModuleInstanceFromKey($forward_module_key);

					if(!is_object($oModule)) {
                        $this->error = 'msg_module_is_not_exists';
                        return $this->showErrorToUser();
					}

					if($this->module == "admin" && $forward_module_key->getType() == "view")
					{
                        $logged_info = Context::get('logged_info');
						if($logged_info->is_admin=='Y'){
							if ($this->act != 'dispLayoutAdminLayoutModify')
							{
								$oAdminView = &getAdminView('admin');
								$oAdminView->makeGnbUrl($forward->module);
								$oModule->setLayoutPath("./modules/admin/tpl");
								$oModule->setLayoutFile("layout.html");
							}
						}else{
							$this->error = 'msg_is_not_administrator';
							return $this->showErrorToUser();
						}
					}
					if ($forward_module_key->getKind() == 'admin'){
						$grant = $oModuleModel->getGrant($this->module_info, $logged_info);		
						if(!$grant->is_admin && !$grant->manager) {
                            $this->error = 'msg_is_not_manager';
                            return $this->showErrorToUser();
                        }   
						
					}
				}
				// 3.2. Otherwise, fallback to module's default action
                else if($xml_info->default_index_act && method_exists($oModule, $xml_info->default_index_act))
				{
					$this->act = $xml_info->default_index_act;
				}
                // 3.3 Else, error
				else
				{
					$this->error = 'msg_invalid_request';
					$oModule->setError(-1);
					$oModule->setMessage($this->error);
					return $oModule;
				}
			}

            // TODO There is a bug here - no one ever checked if "action->{$this->act}" is set
            $ruleset = $xml_info->action->{$this->act}->ruleset;

			// ruleset check...
			if(!empty($ruleset))
			{
				$rulesetModule = $forward->module ? $forward->module : $this->module;
				$rulesetFile = $oModuleModel->getValidatorFilePath($rulesetModule, $ruleset, $this->mid);
				if(!empty($rulesetFile))
				{
					if($_SESSION['XE_VALIDATOR_ERROR_LANG'])
					{
						$errorLang = $_SESSION['XE_VALIDATOR_ERROR_LANG'];
						foreach($errorLang as $key => $val)
						{
							Context::setLang($key, $val);
						}
						unset($_SESSION['XE_VALIDATOR_ERROR_LANG']);
					}

					$Validator = new Validator($rulesetFile);
					$result = $Validator->validate();
					if(!$result)
					{
						$lastError = $Validator->getLastError();
						$returnUrl = Context::get('error_return_url');
						$errorMsg = $lastError['msg'] ? $lastError['msg'] : 'validation error';

						//for xml response
						$oModule->setError(-1);
						$oModule->setMessage($errorMsg);
						//for html redirect
						$this->error = $errorMsg;
						$_SESSION['XE_VALIDATOR_ERROR'] = -1;
						$_SESSION['XE_VALIDATOR_MESSAGE'] = $this->error;
						$_SESSION['XE_VALIDATOR_MESSAGE_TYPE'] = 'error';
						$_SESSION['XE_VALIDATOR_RETURN_URL'] = $returnUrl;
						$this->_setInputValueToSession();
						return $oModule;
					}
				}
			}

            $oModule->setAct($this->act);

            $this->module_info->module_type = $type;
            $oModule->setModuleInfo($this->module_info, $xml_info);

			if($type == "view" && $this->module_info->use_mobile == "Y" && Mobile::isMobileCheckByAgent())
			{
				global $lang;
				$header = '<style type="text/css">div.xe_mobile{opacity:0.7;margin:1em 0;padding:.5em;background:#333;border:1px solid #666;border-left:0;border-right:0}p.xe_mobile{text-align:center;margin:1em 0}a.xe_mobile{color:#ff0;font-weight:bold;font-size:24px}@media only screen and (min-width:500px){a.xe_mobile{font-size:15px}}</style>';
				$footer = '<div class="xe_mobile"><p class="xe_mobile"><a class="xe_mobile" href="'.getUrl('m', '1').'">'.$lang->msg_pc_to_mobile.'</a></p></div>';
				Context::addHtmlHeader($header);
				Context::addHtmlFooter($footer);
			}

			if($type == "view" && $kind != 'admin'){
				$module_config= $oModuleModel->getModuleConfig('module');
				if($module_config->htmlFooter){
						Context::addHtmlFooter($module_config->htmlFooter);
				}
			}


            // if failed message exists in session, set context
			$this->_setInputErrorToContext();

            $procResult = $oModule->proc();

			$methodList = array('XMLRPC'=>1, 'JSON'=>1);
			if(!$oModule->stop_proc && !isset($methodList[Context::getRequestMethod()]))
			{
				$error = $oModule->getError();
				$message = $oModule->getMessage();
				$messageType = $oModule->getMessageType();
				$redirectUrl = $oModule->getRedirectUrl();

				if (!$procResult)
				{
					$this->error = $message;
					if (!$redirectUrl && Context::get('error_return_url')) $redirectUrl = Context::get('error_return_url');
					$this->_setInputValueToSession();

				}
				else
				{
					if(count($_SESSION['INPUT_ERROR']))
					{
						Context::set('INPUT_ERROR', $_SESSION['INPUT_ERROR']);
						$_SESSION['INPUT_ERROR'] = '';
					}
				}

				$_SESSION['XE_VALIDATOR_ERROR'] = $error;
				if ($message != 'success') $_SESSION['XE_VALIDATOR_MESSAGE'] = $message;
				$_SESSION['XE_VALIDATOR_MESSAGE_TYPE'] = $messageType;

				if(Context::get('xeVirtualRequestMethod') != 'xml')
				{
					$_SESSION['XE_VALIDATOR_RETURN_URL'] = $redirectUrl;
				}
			}	
			
			unset($logged_info);
            return $oModule;
        }

        public function getModuleInstanceFromKey(ModuleKey $key)
        {
            // 5. Get the instance
            $oModule = &$this->getModuleInstance($key->getModule(), $key->getType(), $key->getKind());

            // 5.1. If module wasn't found and we're on mobile platform, we try to fallback to classic view
            if($key->getType() == 'mobile' && (!is_object($oModule) || !method_exists($oModule, $this->act)))
            {
                $key = new ModuleKey($key->getModule(), 'view', $key->getKind());
                Mobile::setMobile(false);
                $oModule = &$this->getModuleInstance($key->getModule(), $key->getType(), $key->getKind());
            }
            return $oModule;
        }

        private function initAdminMenu()
        {
            // admin menu check
            if(Context::isInstalled())
            {
                $oMenuAdminModel = &getAdminModel('menu');
                $output = $oMenuAdminModel->getMenuByTitle('__XE_ADMIN__');

                if(!$output->menu_srl)
                {
                    $oAdminClass = &getClass('admin');
                    $oAdminClass->createXeAdminMenu();
                }
                else if(!is_readable($output->php_file))
                {
                    $oMenuAdminController = &getAdminController('menu');
                    $oMenuAdminController->makeXmlFile($output->menu_srl);
                }
            }
        }

        /**
         * set error message to Session.
         * @return void
         **/
		function _setInputErrorToContext()
		{
			if($_SESSION['XE_VALIDATOR_ERROR'] && !Context::get('XE_VALIDATOR_ERROR')) Context::set('XE_VALIDATOR_ERROR', $_SESSION['XE_VALIDATOR_ERROR']);
			if($_SESSION['XE_VALIDATOR_MESSAGE'] && !Context::get('XE_VALIDATOR_MESSAGE')) Context::set('XE_VALIDATOR_MESSAGE', $_SESSION['XE_VALIDATOR_MESSAGE']);
			if($_SESSION['XE_VALIDATOR_MESSAGE_TYPE'] && !Context::get('XE_VALIDATOR_MESSAGE_TYPE')) Context::set('XE_VALIDATOR_MESSAGE_TYPE', $_SESSION['XE_VALIDATOR_MESSAGE_TYPE']);
			if($_SESSION['XE_VALIDATOR_RETURN_URL'] && !Context::get('XE_VALIDATOR_RETURN_URL')) Context::set('XE_VALIDATOR_RETURN_URL', $_SESSION['XE_VALIDATOR_RETURN_URL']);

			$this->_clearErrorSession();
		}

        /**
         * clear error message to Session.
         * @return void
         **/
		function _clearErrorSession()
		{
			$_SESSION['XE_VALIDATOR_ERROR'] = '';
			$_SESSION['XE_VALIDATOR_MESSAGE'] = '';
			$_SESSION['XE_VALIDATOR_MESSAGE_TYPE'] = '';
			$_SESSION['XE_VALIDATOR_RETURN_URL'] = '';
		}

        /**
         * occured error when, set input values to session.
         * @return void
         **/
		function _setInputValueToSession()
		{
			$requestVars = Context::getRequestVars();
			unset($requestVars->act, $requestVars->mid, $requestVars->vid, $requestVars->success_return_url, $requestVars->error_return_url);
			foreach($requestVars AS $key=>$value) $_SESSION['INPUT_ERROR'][$key] = $value;
		}

        /**
         * display contents from executed module
         * @param ModuleObject $oModule module instance
         * @return void
         **/
        function displayContent($oModule = NULL) {
            // If the module is not set or not an object, set error
            if(!$oModule || !is_object($oModule)) {
                $this->error = 'msg_module_is_not_exists';
				$this->httpStatusCode = '404';
            }

            // If connection to DB has a problem even though it's not install module, set error
            if($this->module != 'install' && $GLOBALS['__DB__'][Context::getDBType()]->isConnected() == false) {
                $this->error = 'msg_dbconnect_failed';
            }

            // Call trigger after moduleHandler proc
            $output = ModuleHandler::triggerCall('moduleHandler.proc', 'after', $oModule);
            if(!$output->toBool()) $this->error = $output->getMessage();

            // Use message view object, if HTML call
			$methodList = array('XMLRPC'=>1, 'JSON'=>1);
            if(!isset($methodList[Context::getRequestMethod()])) {

				if($_SESSION['XE_VALIDATOR_RETURN_URL'])
				{
					$display_handler = new DisplayHandler();
					$display_handler->_debugOutput();

					header('location:'.$_SESSION['XE_VALIDATOR_RETURN_URL']);
					return;
				}

                // If error occurred, handle it
                if($this->error) {
                    // display content with message module instance
					$type = Mobile::isFromMobilePhone() ? 'mobile' : 'view';
					$oMessageObject = &ModuleHandler::getModuleInstance('message',$type);
					$oMessageObject->setError(-1);
					$oMessageObject->setMessage($this->error);
					$oMessageObject->dispMessage();

					if($oMessageObject->getHttpStatusCode() && $oMessageObject->getHttpStatusCode() != '200')
					{
						$this->_setHttpStatusMessage($oMessageObject->getHttpStatusCode());
						$oMessageObject->setTemplateFile('http_status_code');
					}

                    // If module was called normally, change the templates of the module into ones of the message view module
                    if($oModule) {
						$oModule->setTemplatePath($oMessageObject->getTemplatePath());
						$oModule->setTemplateFile($oMessageObject->getTemplateFile());
                    // Otherwise, set message instance as the target module
                    } else {
                        $oModule = $oMessageObject;
                    }

					$this->_clearErrorSession();
                }

                // Check if layout_srl exists for the module
				if(Mobile::isFromMobilePhone())
				{
					$layout_srl = $oModule->module_info->mlayout_srl;
				}
				else
				{
					$layout_srl = $oModule->module_info->layout_srl;
				}

                if($layout_srl && !$oModule->getLayoutFile()) {

                    // If layout_srl exists, get information of the layout, and set the location of layout_path/ layout_file
                    $oLayoutModel = &getModel('layout');
                    $layout_info = $oLayoutModel->getLayout($layout_srl);
                    if($layout_info) {

                        // Input extra_vars into $layout_info
                        if($layout_info->extra_var_count) {

                            foreach($layout_info->extra_var as $var_id => $val) {
                                if($val->type == 'image') {
                                    if(preg_match('/^\.\/files\/attach\/images\/(.+)/i',$val->value)) $val->value = Context::getRequestUri().substr($val->value,2);
                                }
                                $layout_info->{$var_id} = $val->value;
                            }
                        }
                        // Set menus into context
                        if($layout_info->menu_count) {
                            foreach($layout_info->menu as $menu_id => $menu) {
                                if(file_exists($menu->php_file)) @include($menu->php_file);
                                Context::set($menu_id, $menu);
                            }
                        }

                        // Set layout information into context
                        Context::set('layout_info', $layout_info);

                        $oModule->setLayoutPath($layout_info->path);
                        $oModule->setLayoutFile('layout');

                        // If layout was modified, use the modified version
                        $edited_layout = $oLayoutModel->getUserLayoutHtml($layout_info->layout_srl);
                        if(file_exists($edited_layout)) $oModule->setEditedLayoutFile($edited_layout);
                    }
                }
            }

            // Display contents
            $oDisplayHandler = new DisplayHandler();
            $oDisplayHandler->printContent($oModule);
        }

        /**
         * returns module's path
         * @param string $module module name
         * @return string path of the module
         **/
        function getModulePath($module) {
            return sprintf('./modules/%s/', $module);
        }

        /**
         * It creates a module instance
         * @param string $module module name
         * @param string $type instance type, (e.g., view, controller, model)
         * @param string $kind admin or svc
         * @return ModuleObject module instance (if failed it returns null)
         * @remarks if there exists a module instance created before, returns it.
         **/
        function &getModuleInstance($module, $type = 'view', $kind = '') {

            if(__DEBUG__==3) $start_time = getMicroTime();

			$parent_module = $module;
			$kind = strtolower($kind);
			$type = strtolower($type);

			$kinds = array('svc'=>1, 'admin'=>1);
			if(!isset($kinds[$kind])) $kind = 'svc';

			$key = $module.'.'.($kind!='admin'?'':'admin').'.'.$type;

			if(is_array($GLOBALS['__MODULE_EXTEND__']) && array_key_exists($key, $GLOBALS['__MODULE_EXTEND__']))
			{
				$module = $extend_module = $GLOBALS['__MODULE_EXTEND__'][$key];
			}

            // if there is no instance of the module in global variable, create a new one
			if(!isset($GLOBALS['_loaded_module'][$module][$type][$kind]))
			{
				ModuleHandler::_getModuleFilePath($module, $type, $kind, $class_path, $high_class_file, $class_file, $instance_name);

				if($extend_module && (!is_readable($high_class_file) || !is_readable($class_file)))
				{
					$module = $parent_module;
					ModuleHandler::_getModuleFilePath($module, $type, $kind, $class_path, $high_class_file, $class_file, $instance_name);
				}

                // Get base class name and load the file contains it
                if(!class_exists($module)) {
                    $high_class_file = sprintf('%s%s%s.class.php', _XE_PATH_,$class_path, $module);
                    if(!file_exists($high_class_file)) return NULL;
                    require_once($high_class_file);
                }

                // Get the name of the class file
                if(!is_readable($class_file)) return NULL;

                // Create an instance with eval function
                require_once($class_file);
                if(!class_exists($instance_name)) return NULL;
				$tmp_fn  = create_function('', "return new {$instance_name}();");
				$oModule = $tmp_fn();
                if(!is_object($oModule)) return NULL;

                // Load language files for the class
                Context::loadLang($class_path.'lang');
				if($extend_module) {
					Context::loadLang(ModuleHandler::getModulePath($parent_module).'lang');
				}

                // Set variables to the instance
                $oModule->setModule($module);
                $oModule->setModulePath($class_path);

                // If the module has a constructor, run it.
                if(!isset($GLOBALS['_called_constructor'][$instance_name])) {
                    $GLOBALS['_called_constructor'][$instance_name] = true;
                    if(@method_exists($oModule, $instance_name)) $oModule->{$instance_name}();
                }

                // Store the created instance into GLOBALS variable
                $GLOBALS['_loaded_module'][$module][$type][$kind] = $oModule;
            }

            if(__DEBUG__==3) $GLOBALS['__elapsed_class_load__'] += getMicroTime() - $start_time;

            // return the instance
            return $GLOBALS['_loaded_module'][$module][$type][$kind];
        }

		function _getModuleFilePath($module, $type, $kind, &$classPath, &$highClassFile, &$classFile, &$instanceName)
		{
			$classPath = ModuleHandler::getModulePath($module);

			$highClassFile = sprintf('%s%s%s.class.php', _XE_PATH_,$classPath, $module);
			$highClassFile = FileHandler::getRealPath($highClassFile);

			$types = explode(' ', 'view controller model api wap mobile class');
			if(!in_array($type, $types)) $type = $types[0];
			if($type == 'class') {
				$instanceName = '%s';
				$classFile    = '%s%s.%s.php';
			} elseif($kind == 'admin' && array_search($type, $types) < 3) {
				$instanceName = '%sAdmin%s';
				$classFile    = '%s%s.admin.%s.php';
			} else{
				$instanceName = '%s%s';
				$classFile    = '%s%s.%s.php';
			}

			$instanceName = sprintf($instanceName, $module, ucfirst($type));
			$classFile    = sprintf($classFile, $classPath, $module, $type);
			$classFile    = FileHandler::getRealPath($classFile);
		}

        /**
         * call a trigger
         * @param string $trigger_name trigger's name to call
         * @param string $called_position called position
         * @param object $obj an object as a parameter to trigger
         * @return Object
         **/
        function triggerCall($trigger_name, $called_position, &$obj) {
            // skip if not installed
            if(!Context::isInstalled()) return new Object();

            $oModuleModel = &getModel('module');
            $triggers = $oModuleModel->getTriggers($trigger_name, $called_position);
            if(!$triggers || !count($triggers)) return new Object();

            foreach($triggers as $item) {
                $module = $item->module;
                $type = $item->type;
                $called_method = $item->called_method;

                $oModule = null;
                $oModule = &getModule($module, $type);
                if(!$oModule || !method_exists($oModule, $called_method)) continue;

                $output = $oModule->{$called_method}($obj);
                if(is_object($output) && method_exists($output, 'toBool') && !$output->toBool()) return $output;
                unset($oModule);
            }

            return new Object();
        }

		/**
		 * get http status message by http status code
		 * @param string $code
		 * @return string
		 **/
		function _setHttpStatusMessage($code) {
			$statusMessageList = array(
				'100'=>'Continue',
				'101'=>'Switching Protocols',
				'201'=>'OK',
				'201'=>'Created',
				'202'=>'Accepted',
				'203'=>'Non-Authoritative Information',
				'204'=>'No Content',
				'205'=>'Reset Content',
				'206'=>'Partial Content',
				'300'=>'Multiple Choices',
				'301'=>'Moved Permanently',
				'302'=>'Found',
				'303'=>'See Other',
				'304'=>'Not Modified',
				'305'=>'Use Proxy',
				'307'=>'Temporary Redirect',
				'400'=>'Bad Request',
				'401'=>'Unauthorized',
				'402'=>'Payment Required',
				'403'=>'Forbidden',
				'404'=>'Not Found',
				'405'=>'Method Not Allowed',
				'406'=>'Not Acceptable',
				'407'=>'Proxy Authentication Required',
				'408'=>'Request Timeout',
				'409'=>'Conflict',
				'410'=>'Gone',
				'411'=>'Length Required',
				'412'=>'Precondition Failed',
				'413'=>'Request Entity Too Large',
				'414'=>'Request-URI Too Long',
				'415'=>'Unsupported Media Type',
				'416'=>'Requested Range Not Satisfiable',
				'417'=>'Expectation Failed',
				'500'=>'Internal Server Error',
				'501'=>'Not Implemented',
				'502'=>'Bad Gateway',
				'503'=>'Service Unavailable',
				'504'=>'Gateway Timeout',
				'505'=>'HTTP Version Not Supported',
            );
			$statusMessage = $statusMessageList[$code];
			if(!$statusMessage) $statusMessage = 'OK';

			Context::set('http_status_code', $code);
			Context::set('http_status_message', $statusMessage);
		}
    }
?>
