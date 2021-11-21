<?php
define('SKIP_REG_LOGIC', true);

use Bitrix\Main,
    Bitrix\Main\Localization\Loc,
    Bitrix\Main\SystemException,
    Bitrix\Main\Config\Option;

if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

class CGipfelAuthorizeComponent extends CBitrixComponent
{
    protected $referer;
    protected $request;
    protected $action;
    protected $method;
    protected $arUser;
    protected $response;
    protected $needAuthorize;
    
    public function onPrepareComponentParams($arParams)
    {
        $arParams["PHONE_REGISTRATION"] = (Option::get("main", "new_user_phone_auth", "N") == "Y");
        $arParams["PHONE_REQUIRED"] = ($arParams["PHONE_REGISTRATION"] && Option::get("main", "new_user_phone_required", "N") == "Y");
        $arParams["EMAIL_REGISTRATION"] = (Option::get("main", "new_user_email_auth", "Y") <> "N");
        $arParams["EMAIL_REQUIRED"] = ($arParams["EMAIL_REGISTRATION"] && Option::get("main", "new_user_email_required", "Y") <> "N");
        $arParams["USE_EMAIL_CONFIRMATION"] = (Option::get("main", "new_user_registration_email_confirmation", "N") == "Y" && $arParams["EMAIL_REQUIRED"]? "Y" : "N");
        $arParams["PHONE_CODE_RESEND_INTERVAL"] = CUser::PHONE_CODE_RESEND_INTERVAL;
        
        $arParams['RESEND_DELAY'] = intval($arParams['RESEND_DELAY']) > 60 ? intval($arParams['RESEND_DELAY']) : 60;


        return $arParams;
    }
    
    public function setResult()
    {
        $this->arResult = array_merge($this->arResult, Array (
            'method' => $this->method,
            'action' => $this->action,
            'referer' => $this->referer,
            'response' => $this->response ? $this->response : Array (
                'type' => false,
                'message' => false
            )
        ));

    }
    
    public function sendAjaxResponse()
    {
        global $APPLICATION;

        $APPLICATION->RestartBuffer();
        header('Content-Type: application/json');


        if ($GLOBALS['USER']->IsAuthorized())
        {
            $this->arResult['action'] = "reload";
            $this->arResult['response'] = [
                'type' => 'ok'
            ];
        }
        
        echo \Bitrix\Main\Web\Json::encode($this->arResult);
        
        if ($this->needAuthorize)
        {
            $GLOBALS['USER']->Authorize($this->needAuthorize);
        }

	
        
        CMain::FinalActions();
        die();
    }

    public function sendSmsCode()
    {
        if (!$this->arUser)
            return false;
        
        list($code, $phoneNumber) = \CUser::GeneratePhoneCode($this->arUser['ID']);
            




            $sms = new \Bitrix\Main\Sms\Event(
                    "SMS_USER_CONFIRM_NUMBER",
                    [
                        "USER_PHONE" => $phoneNumber,
                        "CODE" => $code,
                    ]
            );
            $sms->setSite($this->arUser["LID"]);
            $smsResult = $sms->send(true);
            $signedData = \Bitrix\Main\Controller\PhoneAuth::signData(['phoneNumber' => $phoneNumber]);

            if($smsResult->isSuccess())
            {
                $this->response = Array (
                    'type' => 'ok',
                    'message' => Loc::getMessage("SMS_CODE_SUCCESS_SENT", Array ("#PHONE_NUMBER#" => $phoneNumber)),
                );

                $this->arResult = array_merge($this->arResult, Array (
                    'signedData' => $signedData,
                    'userId' => $this->arUser['ID'],
                    'codeLength' => strlen($code),
					'debug' => 'no'
                ));
            }
            else
            {
                $this->response = Array (
                    'type' => 'error',
                    'message' => is_array($smsResult->getErrorMessages()) ? implode('<br>', $smsResult->getErrorMessages()) : $smsResult->getErrorMessages(),
                );
            }

        return $this->response['type'] == 'ok' ? true : false;
    }
    
    public function checkSmsCode($signedData, $code)
    {
        if(($params = \Bitrix\Main\Controller\PhoneAuth::extractData($signedData)) !== false)
        {
            if($userId = CUser::VerifyPhoneCode($params['phoneNumber'], $code))
            {
                $this->arUser = \CUser::GetByID($userId)->fetch();
                
                $this->response = Array (
                    'type' => 'ok',
                    'message' => false
                );
                
                //$_SESSION['NEXTYPE_AUTHORIZE_USER_ID'] = $userId;
                             
            }
            else
            {
                $this->response = Array (
                    'type' => 'error',
                    'message' => Loc::getMessage('SMS_CODE_WRONG')
                );
            }
        }
        else
        {
            $this->response = Array (
                'type' => 'error',
                'message' => Loc::getMessage('WRONG_SIGNED_DATA')
            );
            
        }
        
        $this->arResult = array_merge($this->arResult, Array (
            'signedData' => $signedData,
        ));
        
        return $this->response['type'] == 'ok' ? $userId : false;
    }
    
    public function userRegister($USER_LOGIN, $USER_NAME, $USER_LAST_NAME, $USER_PASSWORD, $USER_CONFIRM_PASSWORD, $USER_EMAIL, $SITE_ID = false, $captcha_word = "", $captcha_sid = 0, $bSkipConfirm = false, $USER_PHONE_NUMBER = "", $USER_BIRTHDAY = '', $USER_GENDER = '')
    {
        /**
         * @global CMain $APPLICATION
         * @global CUserTypeManager $USER_FIELD_MANAGER
         */
        global $APPLICATION, $DB, $USER_FIELD_MANAGER;

 
        $strError = "";
        $obUser = new \CUser;
        
        if ($strError)
        {
            if (COption::GetOptionString("main", "event_log_register_fail", "N") === "Y")
            {
                CEventLog::Log("SECURITY", "USER_REGISTER_FAIL", "main", false, $strError);
            }

            return array("MESSAGE" => $strError, "TYPE" => "ERROR");
        }

        if ($SITE_ID === false)
            $SITE_ID = SITE_ID;

        $bConfirmReq = !$bSkipConfirm && (COption::GetOptionString("main", "new_user_registration_email_confirmation", "N") == "Y" && COption::GetOptionString("main", "new_user_email_required", "Y") <> "N");
        $phoneRegistration = (COption::GetOptionString("main", "new_user_phone_auth", "N") == "Y");
        $phoneRequired = ($phoneRegistration && COption::GetOptionString("main", "new_user_phone_required", "N") == "Y");

        $checkword = md5(CMain::GetServerUniqID() . uniqid());
        //$active = ($bConfirmReq || $phoneRequired ? "N" : "Y");
        $active = "N";

        $arFields = array(
            "LOGIN" => $USER_LOGIN,
            "NAME" => $USER_NAME,
            "LAST_NAME" => $USER_LAST_NAME,
            "PASSWORD" => $USER_PASSWORD,
            "CHECKWORD" => $checkword,
            "~CHECKWORD_TIME" => $DB->CurrentTimeFunction(),
            "CONFIRM_PASSWORD" => $USER_CONFIRM_PASSWORD,
            "EMAIL" => $USER_EMAIL,
            "PHONE_NUMBER" => $USER_PHONE_NUMBER,
            "ACTIVE" => $active,
            "CONFIRM_CODE" => ($bConfirmReq ? randString(8) : ""),
            "SITE_ID" => $SITE_ID,
            "LANGUAGE_ID" => LANGUAGE_ID,
            "USER_IP" => $_SERVER["REMOTE_ADDR"],
            "USER_HOST" => @gethostbyaddr($_SERVER["REMOTE_ADDR"]),
            "PERSONAL_BIRTHDAY" => $USER_BIRTHDAY,
            "PERSONAL_GENDER" => $USER_GENDER
        );
        $USER_FIELD_MANAGER->EditFormAddFields("USER", $arFields);

        $def_group = COption::GetOptionString("main", "new_user_registration_def_group", "");
        if ($def_group != "")
            $arFields["GROUP_ID"] = explode(",", $def_group);

        $bOk = true;
        $result_message = true;
        foreach (GetModuleEvents("main", "OnBeforeUserRegister", true) as $arEvent)
        {
            if (ExecuteModuleEventEx($arEvent, array(&$arFields)) === false)
            {
                if ($err = $APPLICATION->GetException())
                {
                    $result_message = array("MESSAGE" => $err->GetString() . "<br>", "TYPE" => "ERROR");
                }
                else
                {
                    $result_message = array("MESSAGE" => "Unknown error" . "<br>", "TYPE" => "ERROR");
                }

                $bOk = false;
                break;
            }
        }

        $ID = false;
        $phoneReg = false;
        if ($bOk)
        {
            if ($arFields["SITE_ID"] === false)
            {
                $arFields["SITE_ID"] = CSite::GetDefSite();
            }
            $arFields["LID"] = $arFields["SITE_ID"];

            if ($ID = $obUser->Add($arFields))
            {
                
                    $result_message = array(
                        "MESSAGE" => GetMessage("USER_REGISTER_OK"),
                        "TYPE" => "OK",
                        "ID" => $ID
                    );
                

                $arFields["USER_ID"] = $ID;

                $arEventFields = $arFields;
                unset($arEventFields["PASSWORD"]);
                unset($arEventFields["CONFIRM_PASSWORD"]);
                unset($arEventFields["~CHECKWORD_TIME"]);

                $event = new CEvent;
                $event->SendImmediate("NEW_USER", $arEventFields["SITE_ID"], $arEventFields);
                if ($bConfirmReq)
                {
                    $event->SendImmediate("NEW_USER_CONFIRM", $arEventFields["SITE_ID"], $arEventFields);
                }
            }
            else
            {
                $result_message = array("MESSAGE" => $obUser->LAST_ERROR, "TYPE" => "ERROR");
            }
        }

        if (is_array($result_message))
        {
            if ($result_message["TYPE"] == "OK")
            {
                if (COption::GetOptionString("main", "event_log_register", "N") === "Y")
                {
                    $res_log["user"] = ($USER_NAME != "" || $USER_LAST_NAME != "") ? trim($USER_NAME . " " . $USER_LAST_NAME) : $USER_LOGIN;
                    CEventLog::Log("SECURITY", "USER_REGISTER", "main", $ID, serialize($res_log));
                }
            }
            else
            {
                if (COption::GetOptionString("main", "event_log_register_fail", "N") === "Y")
                {
                    CEventLog::Log("SECURITY", "USER_REGISTER_FAIL", "main", $ID, $result_message["MESSAGE"]);
                }
            }
        }

        //authorize succesfully registered user, except email or phone confirmation is required
        

        $arFields["RESULT_MESSAGE"] = $result_message;
        foreach (GetModuleEvents("main", "OnAfterUserRegister", true) as $arEvent)
            ExecuteModuleEventEx($arEvent, array(&$arFields));

        return $arFields["RESULT_MESSAGE"];
    }
    
    public function executeComponent()
    {

        $this->request = Bitrix\Main\Application::getInstance()->getContext()->getRequest();
        $this->request->addFilter(new \Bitrix\Main\Web\PostDecodeFilter);
        $this->action = $this->arParams["PHONE_REGISTRATION"] ? "getPhoneNumber" : "getEmailAndPassword";
        $this->method = $this->arParams["PHONE_REGISTRATION"] ? "phone" : "email";

        $emailConfirmation = \COption::GetOptionString("main","new_user_registration_email_confirmation","N");
        $captchaRegistartion = COption::GetOptionString('main', 'captcha_registration', 'N');
        
        if (!$this->arParams["PHONE_REGISTRATION"] && !$this->arParams["EMAIL_REGISTRATION"])
        {
            ShowMessage(Loc::getMessage("REGISTRATION_BY_PHONE_AND_EMAIL_NOTHING"));
            return;
        }
        
        if ($this->method != 'phone')
        {
            ShowMessage(Loc::getMessage("REGISTRATION_ONLY_BY_PHONE"));
            return;
        }
        
        if (isset($_REQUEST['backurl']) && strlen($_REQUEST['backurl']) > 0)
        {
            $this->referer = strip_tags($_REQUEST['backurl']);
        }
        
        if (!$this->referer && !empty($_SERVER['HTTP_REFERER']))
        {
            $this->referer = str_replace($_SERVER['HTTP_HOST'], "", str_replace("http://", "", str_replace("https://", "", $_SERVER['HTTP_REFERER'])));
        }
	

        if ($this->method == 'phone')
        {	
			
            if ($this->request->isPost())
            {
                try
                {

                    if (strlen($this->request->get('referer')) > 0)
                        $this->referer = $this->request->get('referer');

                    $this->action = strlen($this->request->get('action')) > 0 ? $this->request->get('action') : $this->action;

				

                    if ($this->action == 'getPhoneNumber' && strlen($this->request->get('phone')) > 5)
                    {

                            if (($validateNumber = Main\UserPhoneAuthTable::validatePhoneNumber ($this->request->get('phone'))) !== true)
                            {
                                $this->response = Array (
                                    'type' => 'error',
                                    'message' => $validateNumber
                                );
								var_dump('test');
								die;
							}

							

                            $arUserPhoneAuthTable = Main\UserPhoneAuthTable::getList([
                                "select" => Array (
                                    'USER_ID'
                                ),
                                "filter" => Array (
                                    "=PHONE_NUMBER" => Main\UserPhoneAuthTable::normalizePhoneNumber($this->request->get('phone'))
                                ),
                            ])->fetch();


                            if ($arUserPhoneAuthTable && $arUserPhoneAuthTable['USER_ID'])
                            {
                                $this->arUser = \CUser::GetByID($arUserPhoneAuthTable['USER_ID'])->fetch();
                            }


                            if ($this->arUser)
                            {
                                if ($this->sendSmsCode())
                                    $this->action = 'checkPhoneCode';
                            }
                            else
                            {
                                $randomUserCode = randString(10);
                                $randomUserPassword = randString(10);

                                if ($emailConfirmation == "Y")
                                    \COption::SetOptionString("main","new_user_registration_email_confirmation","N");

                                if ($captchaRegistartion == "Y")
                                    \COption::SetOptionString('main', 'captcha_registration', 'N');

                                $obUser = new \CUser;
                                $arUserFields = Array (
                                    'LID' => SITE_ID,
                                    'ACTIVE' => 'N',
                                    'LOGIN' => 'us' . $randomUserCode,
                                    'PHONE_NUMBER' => Main\UserPhoneAuthTable::normalizePhoneNumber($this->request->get('phone')),
                                    'PERSONAL_PHONE' => $this->request->get('phone'),
                                    'PASSWORD' => $randomUserPassword,
                                    'CONFIRM_PASSWORD' => $randomUserPassword,
                                    'PERSONAL_BIRTHDAY' => $this->request->get('birthday'),
                                    'PERSONAL_GENDER' => $this->request->get('gender')
                                );

                                if (COption::GetOptionString("main", "new_user_email_required", "Y") <> "N")
                                     $arUserFields['EMAIL'] = $randomUserCode . "@site.ru";

                                $arNewUser = $this->userRegister(
                                        $arUserFields["LOGIN"],
                                        $arUserFields["NAME"],
                                        "",
                                        $arUserFields['PASSWORD'],
                                        $arUserFields['CONFIRM_PASSWORD'],
                                        $arUserFields["EMAIL"],
                                        SITE_ID,
                                        "",
                                        0,
                                        false,
                                        $arUserFields['PHONE_NUMBER'],
                                        $arUserFields['PERSONAL_BIRTHDAY'],
                                        $arUserFields['PERSONAL_GENDER']
                                );

                                if (intval($arNewUser['ID']) > 0)
                                {
                                    $obUser->Update($arNewUser['ID'], $arUserFields);

                                    $this->arUser = \CUser::GetByID($arNewUser['ID'])->fetch();

                                    if ($this->sendSmsCode())
                                        $this->action = 'checkPhoneCode';
                                }
                                else
                                {
                                    $this->response = Array (
                                        'type' => 'error',
                                        'message' => $arNewUser['MESSAGE']
                                    );
                                }

                                if ($emailConfirmation == "Y")
                                    \COption::SetOptionString("main","new_user_registration_email_confirmation","Y");

                                if ($captchaRegistartion == "Y")
                                    \COption::SetOptionString("main","captcha_registration","Y");

                            }

                    }
                    elseif ($this->action == 'checkPhoneCode' && $this->request->get('signedData') && $this->request->get('code'))
                    {
                        if (($userId = $this->checkSmsCode($this->request->get('signedData'), $this->request->get('code'))) != false)
                        {

                            if ($this->arUser['ACTIVE'] == 'Y')
                            {
                                // make redirect
                                $this->action = 'reload';
                                
                                $this->needAuthorize = $userId;
                                //$GLOBALS['USER']->Authorize($userId);
                            }
                            else
                            {
                                // get additional user data
                                $this->action = 'getUserProfile';

                                $this->arResult = array_merge($this->arResult, Array (
                                    'signedData' => $this->request->get('signedData'),
                                ));
                            }

                            if(API_PROCESSING === true && $this->arParams['RETURN_RESULT'] == 'Y')
							{
								$this->arResult['USER'] = $this->arUser;
							}
                        }
                    }
                    elseif ($this->action == 'getUserProfile' && is_array($this->request->get('userProfile')))
                    {

                        $arPostData = $this->request->get('userProfile'); 
                        $params = \Bitrix\Main\Controller\PhoneAuth::extractData($this->request->get('signedData'));



                        if (!isset($params['phoneNumber']) || empty($params['phoneNumber']))
                        {
                            $this->response = Array (
                                'type' => 'error',
                                'message' => "empty params string"    
                             );
                        }
                        else
                        {
                            $arUserPhoneAuthTable = Main\UserPhoneAuthTable::getList([
                                "select" => Array (
                                    'USER_ID'
                                ),
                                "filter" => Array (
                                    "=PHONE_NUMBER" => $params['phoneNumber']
                                ),
                            ])->fetch();

                            if (!$arUserPhoneAuthTable['USER_ID'])
                            {
                                $this->response = Array (
                                    'type' => 'error',
                                    'message' => "not found user by phone number"    
                                 );
                            }
                            else
                            {
                                $userId = $arUserPhoneAuthTable['USER_ID'];
                                $this->arUser = \CUser::GetByID($userId)->fetch();

                                $obUser = new \CUser;

                                $arUserFields = Array (
                                    'ACTIVE' => 'Y',
                                );

                           

                                if (!empty($arPostData['name']))
                                    $arUserFields['NAME'] = $arPostData['name'];

                                if (!empty($arPostData['lastName']))
                                    $arUserFields['LAST_NAME'] = $arPostData['lastName'];

                                if (!empty($arPostData['email']))
                                    $arUserFields['EMAIL'] = $arPostData['email'];

                                if (!empty($arPostData['password']))
                                    $arUserFields['PASSWORD'] = $arUserFields['CONFIRM_PASSWORD'] = $arPostData['password'];
                                
                                if (!empty($arPostData['birthday']))
                                    $arUserFields['PERSONAL_BIRTHDAY'] = $arPostData['birthday'];
                                
                                if (!empty($arPostData['gender']))
                                    $arUserFields['PERSONAL_GENDER'] = $arPostData['gender'];


                                if ($obUser->Update($userId, $arUserFields))
                                {
                                    $this->action = 'reload';

                                    //$GLOBALS['USER']->Authorize($userId);
                                    
                                    $this->needAuthorize = $userId;
                                    
                                    $this->response = Array (
                                        'type' => 'ok',
                                        'message' => ''    
                                     );
                                }
                                else
                                {
                                    $this->response = Array (
                                        'type' => 'error',
                                        'message' => $obUser->LAST_ERROR    
                                     );
                                }
                            }
                        }

                        $this->arResult = array_merge($this->arResult, Array (
                            'signedData' => $this->request->get('signedData'),
                        ));
                    }
                }
                catch (\Exception $e)
                {
                    $this->response = Array (
                        'type' => 'error',
                        'message' => Loc::getMessage('UNKNOWN_ERROR') 
                    );
                }
            }
        }
        
        $this->setResult();
        
        if ($this->request->get('ajax') == 'y')
        {
            $this->sendAjaxResponse();
        }

        if($this->arParams['RETURN_RESULT'] == 'Y') {
        	return ($this->arResult);
		}
        else {
			$this->includeComponentTemplate();
		}
    }
}