/** @param {jQuery} $ jQuery Object */
!function ($, window, document, _undefined) {

    XenForo.bdAd_OptionsLoader = function ($input) {
        this.__construct($input);
    };

    XenForo.bdAd_OptionsLoader.prototype =
    {
        __construct: function ($input) {
            this.$form = $input.parents('form');
            this.$options = this.$form.find(this.$form.data('optionsSelector'));
            this.optionsUrl = this.$form.data('optionsUrl');

            $input.bind({
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

    XenForo.register('.bdAd_OptionsLoader', 'XenForo.bdAd_OptionsLoader');

}
(jQuery, this, document);