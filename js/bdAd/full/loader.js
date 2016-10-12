//noinspection ThisExpressionReferencesGlobalObjectJS,JSUnusedLocalSymbols
/** @param {jQuery} $ jQuery Object */
!function ($, window, document, _undefined) {

    window.bdAd_AdSenseGotScript = false;

    XenForo.bdAd_AdSenseLoader = function () {
        if (window.bdAd_AdSenseGotScript === false) {
            //noinspection SpellCheckingInspection,JSUnresolvedFunction
            $.getScript('//pagead2.googlesyndication.com/pagead/js/adsbygoogle.js');
            window.bdAd_AdSenseGotScript = true;
        }

        //noinspection JSUndeclaredVariable
        (adsbygoogle = window.adsbygoogle || []).push({});
    };

    // *********************************************************************

    XenForo.register('ins.AdSenseLoader', 'XenForo.bdAd_AdSenseLoader');

}(jQuery, this, document);