//noinspection ThisExpressionReferencesGlobalObjectJS,JSUnusedLocalSymbols
/** @param {jQuery} $ jQuery Object */
!function ($, window, document, _undefined) {


    var debugEnabled = null;
    var debugSetup = function ($element) {
        if (debugEnabled !== null) {
            return;
        }

        debugEnabled = XenForo.isPositive($element.data('debug'));
    };
    var debug = function () {
        if (debugEnabled !== true) {
            return;
        }

        if (typeof console.log !== 'function') {
            return;
        }

        console.log.apply(console, arguments);
    };

    // *********************************************************************

    var adSenseGotScript = 0;

    XenForo.bdAd_AdSenseLoader = function ($loader) {
        debugSetup($loader);

        if (adSenseGotScript === 0) {
            //noinspection SpellCheckingInspection,JSUnresolvedFunction
            $.getScript('//pagead2.googlesyndication.com/pagead/js/adsbygoogle.js', function () {
                adSenseGotScript = 2;
            });
            adSenseGotScript = 1;
        }

        window.adsbygoogle = window.adsbygoogle || [];
        window.adsbygoogle.push({});
    };

    // *********************************************************************

    var gptGotScript = 0;

    XenForo.bdAd_GptLoader = function ($loader) {
        debugSetup($loader);

        var minClientWidth = parseInt($loader.data('minClientWidth'));
        if (minClientWidth > 0 &&
            document.documentElement.clientWidth < minClientWidth) {
            debug('document.documentElement.clientWidth (%d) < minClientWidth (%d)',
                document.documentElement.clientWidth, minClientWidth);
            return;
        }

        var $container = $loader;
        var $parent = $loader.parent();
        if ($parent.is('.ad-set')) {
            $container = $parent;
        }
        if ($container.data('GptLoader')) {
            return;
        }
        $container.data('GptLoader', this);

        window.googletag = window.googletag || {};
        window.googletag.cmd = googletag.cmd || [];
        if (gptGotScript === 0) {
            window.googletag.cmd.push(function () {
                //noinspection JSUnresolvedFunction
                window.googletag.pubads().enableSingleRequest();
                //noinspection JSUnresolvedFunction
                window.googletag.enableServices();
            });

            //noinspection JSUnresolvedFunction
            $.getScript('//www.googletagservices.com/tag/js/gpt.js', function () {
                gptGotScript = 2;
            });
            gptGotScript = 1;
        }

        var $ad = null;
        if ($container.is('.ad-set')) {
            var containerWidth = $container.width();
            $container.children('.GptLoader').each(function () {
                var $_ad = $(this);
                var _adSizeWidth = parseInt($_ad.data('adSizeWidth'));
                if (containerWidth >= _adSizeWidth) {
                    $ad = $_ad;
                    return false;
                } else {
                    debug('containerWidth (%d) < _adSizeWidth (%d)', _adSizeWidth, containerWidth);
                }
            });
        } else {
            $ad = $loader;
        }
        if ($ad === null) {
            debug('$ad === null');
            return;
        }

        var containerId = $container.uniqueId().attr('id');
        var adUnitPath = $ad.data('adUnitPath');
        var adSizeWidth = parseInt($ad.data('adSizeWidth'));
        var adSizeHeight = parseInt($ad.data('adSizeHeight'));
        var adSize = null;
        if (adSizeWidth > 0 && adSizeHeight > 0) {
            adSize = [adSizeWidth, adSizeHeight];
            $container.css('min-width', adSizeWidth + 'px');
            $container.css('min-height', adSizeHeight + 'px');
        }

        window.googletag.cmd.push(function () {
            //noinspection JSUnresolvedFunction
            window.googletag.defineSlot(adUnitPath, adSize, containerId).addService(googletag.pubads());
            window.googletag.display(containerId);
            debug('window.googletag.display(%s); # adUnitPath=%s, adSize=%s', containerId, adUnitPath, adSize);
        });
    };

    // *********************************************************************

    XenForo.register('ins.AdSenseLoader', 'XenForo.bdAd_AdSenseLoader');
    XenForo.register('ins.GptLoader', 'XenForo.bdAd_GptLoader');

}(jQuery, this, document);