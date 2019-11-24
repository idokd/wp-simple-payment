( function( $ ) {
  var SimplePayment = {
    _setup: false,
    params: {op: 'purchase', classname: 'needs-validation'},
    _validations: [],
    _form: null,
    init: function(params) {
      if (typeof(sp_settings) !== 'undefined') SimplePayment.params = {...SimplePayment.params, ...sp_settings};
      if (typeof(params) !== 'undefined' && params) SimplePayment.params = {...SimplePayment.params, ...params};
      window.addEventListener('load', function () {
        var btn = $("[sp-data='checkout']");
        if (btn.length) btn.bind('click', function () {
            SimplePayment.submit();
        });

        // Loop over them and prevent submission
        var forms = document.getElementsByClassName(SimplePayment.params['classname']);
        Array.prototype.filter.call(forms, function (form) {
          form.addEventListener('submit', function (event) {
            SimplePayment.validate();
            if (form.checkValidity() === false) {
              event.preventDefault()
              event.stopPropagation();
              return(false);
            }
            form.classList.add('was-validated');
            SimplePayment.pre();
          }, false);
        });
      }, false);
      return(this);
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

    modal: function(target) {
      this._modal = $('<div class="modal fade" tabindex="-1" role="dialog" sp-data="modal" aria-labelledby="" aria-hidden="true"></div>');
      this._modal.append('<div class="modal-dialog modal-dialog-centered" role="document">' 
        + '<div class="modal-content"><div class="modal-body"><div class="embed-responsive embed-responsive-1by1">'
        + '<iframe name="' + (typeof(target) !== 'undefined' && target ? target : SimplePayment.params['target']) + '" src="about:blank" class="embed-responsive-item h100 w100"></iframe>'
        + '</div></div></div></div>');
      $('body').append(this._modal);
      return($('[sp-data="modal"]'));
    },

    pre: function(target) {
      var target = typeof(target) !== 'undefined' ? target : SimplePayment.params['target'];
      if (SimplePayment.params['display'] == 'iframe') {
        var iframe = $('[name="' + target + '"]');        
        if (!iframe.length) $('[sp-data="container"]').append('<iframe name="' + target + '" src="about:blank" sp-data="iframe"></iframe>');
        $('[name="' + target + '"]').closest(':hidden').show();
      }
      if (SimplePayment.params['display'] == 'modal') {
        if (SimplePayment.params['modal']) jQuery(SimplePayment.params['modal']).modal('show');
        else {
          var modal = jQuery('[name="' + target + '"]').closest('[sp-data="modal"]');
          if (modal.length) modal.modal('show');
          else (this.modal(target)).modal('show'); 
        }
      }
    },

    submit: function(params, target) {
      if (typeof(params) !== 'undefined' && params) this.init(params);
      if (!this._setup) this.setup();
      var target = typeof(target) !== 'undefined' && target ? target : this.params['target'];
      if (!this._form) {
        var target = this.params['type'] == 'hidden' ? 'sp-frame' : target;
        this._form = $('<form method="post" target="' + target + '" action="' + this.params['callback'] + '"></form>');
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
  this.SimplePayment = SimplePayment.init();
})(jQuery);