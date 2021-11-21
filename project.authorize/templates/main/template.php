<?
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();

use Bitrix\Main,
    Bitrix\Main\Localization\Loc,
    Nextype\Magnet\CSolution;

	Bitrix\Main\Page\Asset::getInstance()->addJs(SITE_TEMPLATE_PATH . "/vendor/inputmask/jquery.inputmask.min.js");
	Bitrix\Main\Page\Asset::getInstance()->addJs($templateFolder . "/js/vue.min.js");
	Bitrix\Main\Page\Asset::getInstance()->addJs($templateFolder . "/js/script.js");

CSolution::getInstance(SITE_ID);
?>
<? if (!$USER->IsAuthorized()): ?>
<? CJSCore::Init(array("date")); ?>



<form method="post" id="bx-nextype-authorize" onsubmit="return false;" autocomplete="off" method="POST" style="opacity: 1">
    <div class="auth auth-new">
        
        <div class="auth-body">
            <div class="name">
                <div class="image">
                    <svg width="30" height="30" viewBox="0 0 30 30" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path
                            d="M15 4C11.695 4 9 6.695 9 10C9 13.305 11.695 16 15 16C18.305 16 21 13.305 21 10C21 6.695 18.305 4 15 4ZM15 16C9.4925 16 5 20.4925 5 26H7C7 21.57 10.57 18 15 18C19.43 18 23 21.57 23 26H25C25 20.4925 20.5075 16 15 16ZM15 6C17.2175 6 19 7.78 19 10C19 12.22 17.2175 14 15 14C12.78 14 11 12.22 11 10C11 7.78 12.78 6 15 6Z"
                            fill="#034488" />
                    </svg>
                </div>
                <h4 class="heading">Войти или создать профиль</h4>
            </div>
        

            <div class="form">

                <div class="form-auth" v-if="result.action == 'getPhoneNumber'">
                    <div class="input-container">
                        <div class="label"><?=GetMessage('AUTH_PHONE_TITLE')?></div>
                        <div class="alert alert-danger show" v-if="result.response.type=='error'" v-html="result.response.message"></div>
                        <input type="tel" v-on:keyup="keyupPhonenumber" data-phone-mask="phone" required="" v-model="values.phone">
                    </div>
                    
                    <div class="message" v-if="result.response.type=='ok'" v-html="result.response.message"></div>
                    
                    <button type="button" v-on:click.prevent="doSendPhoneCode" class="btn submit"><?=GetMessage('NEXT_BUTTON')?></button>
                </div>

                <div class="form-auth" v-if="result.action == 'checkPhoneCode'">
                    <div>
                        <div class="input-container">
                            <div class="label" for=""><?=GetMessage('AUTH_PHONE_CHECK_CODE_TITLE')?></div>
                            <div class="alert alert-danger show" v-if="result.response.type=='error'" v-html="result.response.message"></div>
                            <input type="text" pattern="\d*" required="" oninput="this.value = this.value.replace(/[^0-9.]/g, ''); this.value = this.value.replace(/(\..*)\./g, '$1');" v-on:keyup="keyupSmscode" placeholder="<?=GetMessage('AUTH_CHECK_CODE_PLACEHOLDER')?>" v-model="values.code">

                        </div>
                    </div>
                    
                    <div class="resend" v-if="timeInterval >= 1" v-html="resendTimeoutMessage"></div>
                    
                    <div v-if="timeInterval < 1" class="repeat">
                        <span class="repeat-text">Код не пришел?</span>
                        <a href="#" class="repeat-link" v-on:click.prevent="doResendCode"><?=GetMessage('AUTH_CHECK_CODE_REPEAT_BUTTON')?></a>
                    </div>
                </div>

                <div class="form-auth" v-if="result.action == 'getUserProfile'">
                    <div class="agreement">
                        Нажимая кнопку «Завершить регистрацию» , вы соглашаетесь на обработку персональных данных и принимаете условия публичной оферты
                    </div>

                    <button type="button" v-on:click.prevent="doSaveProfile" class="btn submit"><?=GetMessage('CONFIRM_REGISTRATION')?></button>
                </div>

                <div class="form-auth" v-if="result.action == 'reload'">
                    <?=GetMessage('SUCCESS_AUTH_WAITING_FOR_REGIRECT')?>
                </div>
                <!-- end:reload -->
            </div>
            
        </div>
        
		<input type="hidden" name="referer" value="/" />

    </div>
</form>
<?
$signer = new Main\Security\Sign\Signer;
$signedParams = $signer->sign(base64_encode(serialize($arParams)), 'nextype.nextype.authorize');
$messages = Loc::loadLanguageFile(__FILE__);
?>

<script>
    BX.message(<?= CUtil::PhpToJSObject($messages) ?>);

    if (!!document.getElementById('pagetitle'))
        document.getElementById('pagetitle').style.display = 'none';
            
    $(document).ready(function () {
                    
            BXGipfelAuthorizeComponent.init({
                containerId: '#bx-nextype-authorize',
                data: <?= CUtil::PhpToJSObject(Array (
                    'result' => $arResult,
                    'values' => Array (
                        'code' => '',
                        'phone' => '',
                        'name' => '',
                        'lastName' => '',
                        'email' => '',
                        'subscribe' => false,
                        'birthday' => '',
                        'gender' => ''
                    ),
                    'timeInterval' => $arParams['RESEND_DELAY']
                )); ?>,
                params: <?= CUtil::PhpToJSObject($arParams) ?>,
                signedParamsString: '<?= CUtil::JSEscape($signedParams) ?>',
                siteID: '<?= CUtil::JSEscape($component->getSiteId()) ?>',
                ajaxUrl: '<?= CUtil::JSEscape($templateFolder . "/ajax.php") ?>',
                templateFolder: '<?= CUtil::JSEscape($templateFolder) ?>'
            });
    });
</script>
<? else: ?>
<div class="auth">
    <div class="left-column">
        <p>Вы успешно авторизовались.</p>
        <a href="<?=SITE_DIR?>" class="btn">Вернуться на главную страницу</a>
    </div>
</div>
<? endif; ?>
