( function( $ ) {
  var SimplePayment = {
    _setup: false,
    params: {op: 'purchase'},
    _form: null,
    init: function(params) {
      window.addEventListener('load', function () {
        if (sp_settings) SimplePayment.params = {...SimplePayment.params, ...sp_settings};
        var btn = $("[sp-data='checkout']");
        if (btn.length) btn.bind('click', function () {
            SimplePayment.submit();
        });

        // Loop over them and prevent submission
        var forms = document.getElementsByClassName('needs-validation');
        Array.prototype.filter.call(forms, function (form) {
          form.addEventListener('submit', function (event) {
            if (!SimplePayment.validate() || form.checkValidity() === false) {
              event.preventDefault()
              event.stopPropagation()
            }
            form.classList.add('was-validated');
            // TODO: show iframe/modal
            if (params['display'] == 'iframe') {
                jQuery.parentsUntil(':hidden').show();
            }
            if (params['display'] == 'modal') {
              jQuery.parentsUntil(':hidden').show();
          }
          }, false);
        });
      }, false);
      return(this);
    },

    validate: function(event) {
      return(true);
    },

    setup: function() {
      var inputs = $("[sp-data]");
      for (var i = 0; i < inputs.length; i++) {
        this.params[$(inputs[i]).attr('sp-data')] = $(inputs[i]).val();
      }
      return(this._setup = true);
    },

    submit: function() {
      if (!this._setup) this.setup();
      if (!this._form) this._form = $('<form method="post" target="' + (this.params['type'] == 'hidden' ? 'sp-frame' : '') + '" action="' + this.params['callback'] + '"></form>');
      else this._form.empty();
      this._form._submit_function_ = this._form.submit;
      Object.keys(this.params).forEach(key => {
        this._form.append('<input type="hidden" name="' + key + '" value="' + (this.params[key] ? this.params[key] : '') + '" />');
      });
      $('body').append(this._form);
      this._form._submit_function_();
    }
  };
  this.SimplePayment = SimplePayment.init();
})(jQuery);