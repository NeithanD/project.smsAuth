(function() {
	'use strict';
        
        window.BXGipfelAuthorizeComponent = {
            
            init: function(parameters)
            {
                this.containerId = parameters.containerId || false;
                this.data = parameters.data || {
                    error: false,
                    values: {},
                    order: {}
                };
                this.params = parameters.params || {};
                this.signedParamsString = parameters.signedParamsString || '';
                this.siteId = parameters.siteID || '';
                this.ajaxUrl = parameters.ajaxUrl || '';
                this.templateFolder = parameters.templateFolder || '';
                this.timeOut = false;

                if (!this.containerId)
                    return false;
                
                this.months = [
                    'января',
                    'февраля',
                    'марта',
                    'апреля',
                    'мая',
                    'июня',
                    'июля',
                    'августа',
                    'сентября',
                    'октября',
                    'ноября',
                    'декабря',
                ];
                
                var _ = this;
                
                this.app = new Vue({
                    el: _.containerId,
                    data: _.data,
                    
                    mounted: function () {
                        var self = this;
                        
                        if (window.arOptions['phone_mask'] != "") {
                            
                           /* $(this.$el).find('[data-phone-mask]').inputmask(window.arOptions['phone_mask'], {
                                oncomplete: function () {
                                    var element = $(this);
                                    if (element.data('phone-mask') != "")
                                        self.values[element.data('phone-mask')] = this.value;
                                }
                            });*/
                            
                            $(this.$el).find('[data-phone-mask]').mask(window.arOptions['phone_mask'], {
                                completed: function() {
                                    if (this.data('phone-mask') != "")
                                        self.values[this.data('phone-mask')] = this.val();
                                }
                            });
                        }
                        
                        $(this.$el).fadeTo(200, 1);
                        
                    },
                    
                    
                    methods: {
                        
                        keyupPhonenumber: function (event) {
                            if (event.keyCode == 13 && this.values.phone.length > 0) {
                                this.doSendPhoneCode();
                            }
                            
                        },
                        
                        keyupSmscode: function (event) {
                            if (event.keyCode == 13 && this.values.phone.length > 0) {
                                this.doActionCheckCode();
                            }
                        },
                        
                        keyupEmail: function (event) {
                            if (this.values.email.length > 0 && this.values.email.indexOf('@') != -1 && this.values.email.indexOf('.') != -1) {
                                this.values.subscribe = true;
                            } else {
                                this.values.subscribe = false;
                            }
                        },
                        
                        genderMaleSelect: function(event) {
                            this.values.gender = 'M';
                            BX('gender-male').setAttribute('checked', 'checked');
                            BX('gender-female').removeAttribute('checked');
                        },
                        
                        genderFemaleSelect: function(event) {
                            this.values.gender = 'F';
                            BX('gender-female').setAttribute('checked', 'checked');
                            BX('gender-male').removeAttribute('checked');
                        },
                        
                        selectBirthday: function(event) {
                            if (event.type == 'keyup')
                            {
                                var code = event.keyCode || event.which;
                                /*if (code !== 9)
                                    return;*/
                                if (!BX.hasClass(event.target, 'masked'))
                                {
                                    $(event.target).mask('99.99.9999');
                                    BX.addClass(event.target, 'masked');
                                }
                                let numVal = /\d{2}\.\d{2}\.\d{4}/g.exec(event.target.value);
                                if (numVal && numVal[0])
                                {
                                    if (validateDate(numVal[0], true))
                                        this.values.birthday = numVal[0];
                                }
                            }
                            
                            if (event.type == 'click')
                            {                            
                                BX.calendar({node: BX.findChild(BX('calendar-block'), {'class':'calendar-input-wrap'}),
                                    field: BX.findChild(BX('calendar-block'), {'tag':'input'}),
                                    bTime: false,
                                    callback: validateDate,
                                    callback_after: _.app.selectBirthdayFinal});
                            }
                        },
                        
                        selectBirthdayFinal: function(data)
                        {
                            var newDate, monthNum, realMonth, dayNum, realDay, formattedDate, normalDate = '', bDateCheck = false;
                            
                            newDate = new Date(data);
                            monthNum = newDate.getMonth();
                            if (monthNum == 0)
                                realMonth = '01';
                            else if (monthNum < 9)
                                realMonth = '0' + (monthNum+1).toString();
                            else
                                realMonth = monthNum + 1;

                            dayNum = newDate.getDate();
                            realDay = (dayNum < 10) ? '0' + dayNum.toString() : dayNum.toString();

                            formattedDate = dayNum + ' ' + _.months[monthNum] + ' ' + newDate.getFullYear();
                            normalDate = realDay + '.' + realMonth + '.' + newDate.getFullYear();
                            document.getElementById('calendar-input').value = formattedDate;
                            
                            bDateCheck = validateDate(normalDate, true);
                            if (bDateCheck)
                            {
                                this.values.birthday = normalDate;
                            }
                        },
                                
                        doSendPhoneCode: function () {
                            var data = {
                                sessid: BX.bitrix_sessid(),
                                ajax: 'y',
                                SITE_ID: _.siteId,
                                signedParamsString: _.signedParamsString,
                                phone: this.values.phone,
                                action: this.result.action
                            };
                            
                            _.sendAjaxRequest(data, function (response) {
                                this.app.doStartResendTimer();
                            });
                        },
                        
                        doStartResendTimer: function () {
                            
                            this.timeInterval = _.params.RESEND_DELAY;
                            
                                _.timeOut = setInterval(function () {
                                    _.app.timeInterval--;

                                    if (_.app.timeInterval <= 0) {
                                        clearInterval(_.timeOut);
                                    }
                                }, 1000);
                            
                        },
                        
                        doResendCode: function () {
                            var data = {
                                sessid: BX.bitrix_sessid(),
                                ajax: 'y',
                                SITE_ID: _.siteId,
                                signedParamsString: _.signedParamsString,
                                phone: this.values.phone,
                                action: this.result.action
                            };
                            
                            this.timeInterval = _.params.RESEND_DELAY;
                            console.log('Action: doResendCode');
                            
                            _.sendAjaxRequest(data, function (response) {
                                 this.app.doStartResendTimer();
                            });
                        },
                        
                        doActionCheckCode: function () {
                            var data = {
                                signedData: this.result.signedData,
                                code: this.values.code,
                                sessid: BX.bitrix_sessid(),
                                ajax: 'y',
                                SITE_ID: _.siteId,
                                signedParamsString: _.signedParamsString,
                                action: this.result.action
                            };

                            _.sendAjaxRequest(data, function (response) {
                                if (typeof this.app.result == 'object' && this.app.result.response.type == 'ok')
                                {
                                    if (this.app.result.action == 'reload') {
                                        if (!!this.app.result.referer && this.app.result.referer != "") {
                                             window.location.href = this.app.result.referer;
                                         } else {
                                            window.location.reload();
                                        }
                                    } 
                                    
                                }
                            });
                        },
                        
                        doSaveProfile: function () {
                                                            
                            if (!!this.result.userId) {
                                
                                var data = {
                                    sessid: BX.bitrix_sessid(),
                                    ajax: 'y',
                                    SITE_ID: _.siteId,
                                    signedParamsString: _.signedParamsString,
                                    signedData: this.result.signedData,
                                    userProfile: {
                                        name: this.values.name,
                                        lastName: this.values.lastName,
                                        email: this.values.email,
                                        subscribe: this.values.subscribe,
                                        birthday: this.values.birthday,
                                        gender: this.values.gender
                                    },
                                    action: this.result.action,
                                    
                                };
                                
                                

                                _.sendAjaxRequest(data, function (response) {
                                     if (this.app.result.action == 'reload') {
                                         
                                         if (this.app.values.email != "" && this.app.values.subscribe && BX.message('SITE_ID') == "s1")
                                         {
                                            var self = this;
                                            var obMoreParams = {};
                                            if (self.app.values.gender != "")
                                                obMoreParams.gender = self.app.values.gender == "M" ? "Male" : "Female";
                                            if (self.app.values.birthday != "")
                                                obMoreParams.birthday = self.app.values.birthday;

                                            (window["rrApiOnReady"] = window["rrApiOnReady"] || []).push(function() { rrApi.setEmail(self.app.values.email, obMoreParams); });
                                         }
                                         
                                         if (!!this.app.result.referer && this.app.result.referer != "") {
                                             window.location.href = this.app.result.referer;
                                         } else {
                                            window.location.reload();
                                         }
                                    } 
                                });
                            }
                        },
                       
                    },
                    watch: {
                        'values.code': function (value)
                        {
                            
                            if (typeof this.result == 'object' && !!this.result.codeLength) {
                                var length = value.length;
                                if (parseInt(value.length) == parseInt(this.result.codeLength)) {
                                    this.doActionCheckCode();
                                }
                            }
                        }
                    },
                    computed: {
                        
                        resendTimeoutMessage: function () {
                            return BX.message('AUTH_CHECK_CODE_REPEAT_WAITING').replace('#SECONDS#', this.timeInterval);
                        },
                        
                        
                    }
                });
                
                        //this.app.doStartResendTimer();
            },
            
            sendAjaxRequest: function (data, callback) {
                callback = (callback) || false;
                var _ = this;

                data['recaptcha_response'] = $(_.containerId + ' [name="recaptcha_response"]').val();
                data['referer'] = $(_.containerId + ' [name="referer"]').val();

                $(_.app.$el).find('.form').addClass('loading');

				console.log(this.ajaxUrl);

                $.ajax({
                    type: 'post',
                    url: this.ajaxUrl,
					dataType: 'json',
                    data: data,

                    success: function (response) {
                        console.log((response));

                        $(_.app.$el).find('.form').removeClass('loading');
                                                
                        if (response != "" && typeof response == 'object') {
                            for (var key in response) {
                                _.app.result[key] = response[key];
                            }
                        }
                        else
                        {
                            _.app.result.response.type = 'error';
                            _.app.result.response.message = "Что-то пошло не так. Пожалуйста, перезагрузите страницу.";
                            return;
                        }

                        if (typeof callback == 'function')
                            callback.call(_, response);
                    },
                   /* error: function (jqXHR, exception) {

                        $(_.app.$el).find('.form').removeClass('loading');

                        _.showAjaxError(jqXHR, exception);
                    }*/
                });
            },

            showAjaxError: function (jqXHR, exception) {
                var msg = '';
                if (jqXHR.status === 0) {
                    msg = 'Not connect.\n Verify Network.';
                } else if (jqXHR.status == 404) {
                    msg = 'Requested page not found. [404]';
                } else if (jqXHR.status == 500) {
                    msg = 'Internal Server Error [500].';
                } else if (exception === 'parsererror') {
                    msg = 'Requested JSON parse failed.';
                } else if (exception === 'timeout') {
                    msg = 'Time out error.';
                } else if (exception === 'abort') {
                    msg = 'Ajax request aborted.';
                } else {
                    msg = 'Uncaught Error.\n' + jqXHR.responseText;
                }

                console.log(msg);
            },
                
        };
})();

function validateDate(data, bSimpleCheck = false)
{
    if (bSimpleCheck)
    {
        let newDateString = data.split('.');
        return (Date.parse(newDateString[2], newDateString[1] - 1, newDateString[0]) < new Date());
    }
    
    var errorNode = {};
    if (!BX('calendar-error-block'))
    {
        errorNode = BX.create('div', {props: {className: 'calendar-error', id: 'calendar-error-block'}, style: {'display': 'none'}, text: 'Ошибка. Нельзя выбрать будущую дату'});
        BX.prepend(errorNode, this.popup.popupContainer);
    }
    else
        errorNode = BX('calendar-error-block');

    if (Date.parse(data) > Date.now())
    {
        BX.show(errorNode);
        return false;
    }
    else
    {
        BX.hide(errorNode);
    }
}