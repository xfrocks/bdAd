/** @param {jQuery} $ jQuery Object */
!function ($, window, document, _undefined) {

    XenForo.bdAd_FormWithOptions = function ($form) {
        this.__construct($form);
    };

    XenForo.bdAd_FormWithOptions.prototype =
    {
        __construct: function ($form) {
            this.$form = $form;
            this.$select = $form.find($form.data('selectInputSelector'));
            this.$options = $form.find(this.$select.data('optionsSelector'));

            this.optionsUrl = this.$select.data('optionsUrl');

            this.$select.bind({
                keyup: $.context(this, 'fetchOptionsDelayed'),
                change: $.context(this, 'fetchOptions')
            });
        },

        fetchOptionsDelayed: function () {
            if (this.delayTimer) {
                clearTimeout(this.delayTimer);
            }

            this.delayTimer = setTimeout($.context(this, 'fetchOptions'), 250);
        },

        fetchOptions: function () {
            if (this.xhr) {
                this.xhr.abort();
            }

            this.xhr = XenForo.ajax(
                this.optionsUrl,
                this.$form.serializeArray(),
                $.context(this, 'fetchOptionsSuccess'),
                {
                    error: false
                }
            );
        },

        fetchOptionsSuccess: function (ajaxData) {
            var $target = this.$options;

            $target.children().empty().remove();

            if (XenForo.hasTemplateHtml(ajaxData)) {
                new XenForo.ExtLoader(ajaxData, function () {
                    $('<div></div>')
                        .html(ajaxData['templateHtml'])
                        .children()
                        .xfInsert('appendTo', $target, 'show');
                });
            }
        }

    };

    // *********************************************************************

    XenForo.register('form.SlotEdit, form.AdEdit', 'XenForo.bdAd_FormWithOptions');

}
(jQuery, this, document);