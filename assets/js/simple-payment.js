( function( $ ) {
  var SimplePayment = {
    _setup: false,
    params: {op: 'purchase', classname: 'needs-validation'},
    _validations: [],
    _form: null,
    bootstrap: null,
    
    init: function(params) {
      if (SimplePayment.bootstrap == null) SimplePayment.bootstrap = (typeof $().modal == 'function');
      if (typeof(params) !== 'undefined' && params) SimplePayment.params = Object.assign({}, SimplePayment.params, params);

      if ($(document).triggerHandler( 'simple_payment_init' ) === false ) return(this);

      window.addEventListener('load', function () { 
        if (typeof(sp_settings) !== 'undefined') SimplePayment.params = Object.assign({}, SimplePayment.params, sp_settings);
        var btn = $("[sp-data='checkout']");
        var target = null;
        if (btn.length) btn.bind('click', function () {
            SimplePayment.submit();
        }); else if (SimplePayment.params['display'] == 'modal') {
          target = SimplePayment.params['target'];
          target = SimplePayment.params['type'] == 'hidden' || !target ? 'sp-frame' : target;
          SimplePayment.modal(target);
        } else if (SimplePayment.params['display'] == 'iframe') {
          target = SimplePayment.params['target'];
          target = SimplePayment.params['type'] == 'hidden' || !target ? 'sp-frame' : target;
          SimplePayment.submit();
        }
        
        // Loop over them and prevent submission
        var forms = document.getElementsByClassName(SimplePayment.params['classname']);
        Array.prototype.filter.call(forms, function (form) {
          if (target && !$(form).attr('target')) $(form).attr('target', target);
          form.addEventListener('submit', function (event) {
            SimplePayment.validate();
            if (form.checkValidity() === false) {
              form.classList.add('was-validated');
              event.preventDefault()
              event.stopPropagation();
              return(false);
            }
            form.classList.add('was-validated');
            SimplePayment.pre(target);
          }, false);
        });
      }, false);
      return(this);
    },

    settings: function(params) {
      if (typeof(params) !== 'undefined' && params) SimplePayment.params = Object.assign({}, SimplePayment.params, params);
    },

    filter: function(input, fn) {
      if (!input) return;
      ["input", "keydown", "keyup", "mousedown", "mouseup", "select", "contextmenu", "drop"].forEach(function(event) {
        input.oldValue = "";
        input.addEventListener(event, function() {
          if (fn(this.value)) {
            this.oldValue = this.value;
            this.oldSelectionStart = this.selectionStart;
            this.oldSelectionEnd = this.selectionEnd;
          } else if (this.hasOwnProperty("oldValue")) {
            this.value = this.oldValue;
            this.setSelectionRange(this.oldSelectionStart, this.oldSelectionEnd);
          }
        });
      });
    },

    validation: function(type, ele, fn) {
      if (!ele) return;
      this._validations[type] = [ele, fn];
    },

    validExpiry: function(month, year) {
      if (!month && !year) return(true);
      var now = new Date();
      now.setHours(0,0,0,0);
      var expiry = new Date(year, month, 0);
      expiry.setHours(0,0,0,0);
      return(now <= expiry);
    },
    
    validCard: function(number) {
        if (!number) return(true);
        var regex = new RegExp("^[0-9]{16}$");
        return(regex.test(number) && SimplePayment.luhn(number));
    },
    
    luhn: function(val) {
        var sum = 0;
        for (var i = 0; i < val.length; i++) {
            var intVal = parseInt(val.substr(i, 1));
            if (i % 2 == 0) {
                intVal *= 2;
                if (intVal > 9) {
                    intVal = 1 + (intVal % 10);
                }
            }
            sum += intVal;
        }
        return((sum % 10) == 0);
    },

    validate: function(event) {
      var valid = true;
      Object.values(this._validations).forEach(validation => {
        var ele = validation[0];
        var fn = validation[1];
        if (ele && fn) {
          var passed = fn(ele);
          ele = Array.isArray(ele) ? ele[0] : ele;
          ele.setCustomValidity(!passed ? 'Failed Validation' : '');
          valid = valid && passed;
        }
      });
      return(valid);
    },

    form: function(elem) {
      this._form = elem;
    },

    setup: function() {
      var inputs = $("[sp-data]");
      for (var i = 0; i < inputs.length; i++) {
        var type = $(inputs[i]).attr('type');
        var value = $(inputs[i]).val();
        if (type == 'checkbox') {
          value = $(inputs[i]).attr('checked') ? value : null
        }
        this.params[$(inputs[i]).attr('sp-data')] = value;
      }
      return(this._setup = true);
    },

    modal: function(target, _url) {
      var _modal;
      var _url = typeof(_url) !== 'undefined' && _url ? _url : 'about:blank';

      if (SimplePayment.bootstrap) {
        _modal = $('<div class="modal" tabindex="-1" role="dialog" sp-data="modal" ' + (SimplePayment.params['modal_disable_close'] ? 'data-backdrop="static" data-keyboard="false"' : '') + 'aria-labelledby="" aria-hidden="true"></div>');
        _modal.append('<div class="modal-dialog modal-dialog-centered" role="document">' 
          + '<div class="modal-content"><div class="modal-body"><div class="embed-responsive embed-responsive-1by1">'
          + '<iframe name="' + (typeof(target) !== 'undefined' && target ? target : SimplePayment.params['target']) + '" src="' + _url + '" class="embed-responsive-item h100 w100"></iframe>'
          + '</div></div></div></div>');
      } else {
        _modal = $('<div class="sp-legacy-modal" tabindex="-1" role="dialog" sp-data="modal" aria-labelledby="" aria-hidden="true"></div>');
        _modal.append('<div class="sp-modal-dialog" role="document">'
          + (SimplePayment.params['modal_disable_close'] ? '' : '<a href="javascript:SimplePayment.close(' + (target ? "'" + target + "'" : '') + ');" class="sp-close">X</a>')
          + '<iframe name="' + (typeof(target) !== 'undefined' && target ? target : SimplePayment.params['target']) + '" src="' + _url + '"></iframe>'
          + '</div>');
      }
      $('body').append(_modal);
      return($('[sp-data="modal"]'));
    },

    pre: function(target, _url) {
      var target = typeof(target) !== 'undefined' && target ? target : SimplePayment.params['target'];
      var _url = typeof(_url) !== 'undefined' && _url ? _url : 'about:blank';
      if (SimplePayment.params['display'] == 'iframe') {
        target = SimplePayment.params['type'] == 'hidden' || !target ? 'sp-frame' : target;
        var iframe = $('[name="' + target + '"]');
        if (!iframe.length) $('[sp-data="container"]').append('<iframe name="' + target + '" src="'+ _url + '" sp-data="iframe"></iframe>');
        $('[name="' + target + '"]').closest(':hidden').show();
      }
      if (SimplePayment.params['display'] == 'modal') {
        if (!jQuery.fn.modal) {
          // We do not have bootstrap, or old and it is not supporting modal
          jQuery.fn.extend({
            modal: function( action ) { 
              return this.each(function() {
                if (action == 'hide') jQuery(this).hide();
                else jQuery(this).show();
              })
            },
          });
        }        
        target = SimplePayment.params['type'] == 'hidden' || !target ? 'sp-frame' : target;
        var modal = SimplePayment.params['modal'] ? jQuery(SimplePayment.params['modal']) : jQuery('[name="' + target + '"]').closest('[sp-data="modal"]');
        if (!modal || modal.length == 0) {
          modal = this.modal(target, _url);
        }
        modal.modal('show');
      }
    },

    close: function(target) {
      if (SimplePayment.params['modal']) jQuery(SimplePayment.params['modal']).modal('hide');
      else {
        var target = typeof(target) !== 'undefined' && target ? target : SimplePayment.params['target'];
        target = SimplePayment.params['type'] == 'hidden' || !target ? 'sp-frame' : target;
        jQuery('[name="' + target + '"]').closest('[sp-data="modal"]').modal('hide');
      }
    },

    show: function(_url, target) {
      this.pre(target, _url);
    },

    submit: function(params, target) {
      if (typeof(params) !== 'undefined' && params) this.init(params);
      if (!this._setup) this.setup();
      var target = typeof(target) !== 'undefined' && target ? target : SimplePayment.params['target'];
      if (!this._form) {
        target = SimplePayment.params['type'] == 'hidden' || !target ? 'sp-frame' : target;
        this._form = $('<form method="post" target="' + target + '" action="' + (SimplePayment.params['callback'] ? SimplePayment.params['callback'] : '') + '"></form>');
      } else this._form.empty();
      this._form._submit_function_ = this._form.submit;
      Object.keys(this.params).forEach(key => {
        this._form.append('<input type="hidden" name="' + key + '" value="' + (this.params[key] ? this.params[key] : '') + '" />');
      });
      $('body').append(this._form);
      this.pre(target);
      this._form._submit_function_();
    }
  };
  this.SimplePayment = SimplePayment.init(typeof(sp_settings) !== 'undefined' && sp_settings ? sp_settings : null);
})(jQuery);