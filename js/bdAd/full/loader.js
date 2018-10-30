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

    XenForo.bdAd_Widget = function ($widget) {
        debugSetup($widget);

        var adSlot = $widget.data('adSlot'),
            loaderVersion = parseInt($widget.data('loaderVersion'));
        if (!adSlot || loaderVersion !== 2017080202) {
            return;
        }

        var maxClientWidth = parseInt($widget.data('maxClientWidth'));
        if (maxClientWidth > 0 &&
            document.documentElement.clientWidth > maxClientWidth) {
            debug(
                'Ad Slot #%d: document.documentElement.clientWidth (%d) > maxClientWidth (%d)',
                adSlot,
                document.documentElement.clientWidth,
                maxClientWidth
            );
            return;
        }

        var minClientWidth = parseInt($widget.data('minClientWidth'));
        if (minClientWidth > 0 &&
            document.documentElement.clientWidth < minClientWidth) {
            debug(
                'Ad Slot #%d: document.documentElement.clientWidth (%d) < minClientWidth (%d)',
                adSlot,
                document.documentElement.clientWidth,
                minClientWidth
            );
            return;
        }

        $widget.show();

        switch ($widget.data('adLayout')) {
            case 'adsense':
                adSenseLoader($widget);
                break;
            case 'gpt':
                gptLoader($widget);
                break;
        }
    };

    // *********************************************************************

    var adSenseGotScript = 0;
    var adSenseLoader = function ($loader) {
        var adSlot = $loader.data('adSlot'),
            adsenseClient = $loader.data('adsenseClient'),
            adsenseSlot = $loader.data('adsenseSlot'),
            adsenseFormat = $loader.data('adsenseFormat');

        if (!adsenseClient) {
            debug('AdSense Slot #%d: adsenseClient is missing', adSlot);
            return;
        }
        if (!adsenseSlot) {
            debug('AdSense Slot #%d: adsenseSlot is missing', adSlot);
            return;
        }
        if (!adsenseFormat) {
            debug('AdSense Slot #%d: adsenseFormat is missing', adSlot);
            return;
        }

        if (adSenseGotScript === 0) {
            //noinspection SpellCheckingInspection,JSUnresolvedFunction
            $.getScript('//pagead2.googlesyndication.com/pagead/js/adsbygoogle.js', function () {
                adSenseGotScript = 2;
            });
            adSenseGotScript = 1;
        }

        $loader.append('<ins class="adsbygoogle renderedAd"' +
            ' data-ad-client="' + adsenseClient +
            '" data-ad-slot="' + adsenseSlot +
            '" data-ad-format="' + adsenseFormat +
            '"></ins>');

        //noinspection JSUndefinedPropertyAssignment
        window.adsbygoogle = window.adsbygoogle || [];
        window.adsbygoogle.push({});
        debug('AdSense Slot #%d: %s/%s/%s ok', adSlot, adsenseClient, adsenseSlot, adsenseFormat);
    };

    // *********************************************************************

    var gptGotScript = 0;
    var gptLoader = function ($loader) {
        //noinspection JSUndefinedPropertyAssignment
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

        var responsive = XenForo.isPositive($loader.data('gptResponsive'));
        if (!responsive) {
            return gptLoaderReal($loader, false);
        } else {
            setTimeout(function () {
                gptLoaderReal($loader, true);
            }, 1000);
        }
    };

    var gptLoaderReal = function ($loader, responsive) {
        var adSlot = $loader.data('adSlot'),
            $ad = null,
            containerWidth = $loader.width();

        $loader.children('ins').each(function () {
            var $_ad = $(this);

            if (responsive) {
                var _adWidth = parseInt($_ad.data('gptSizeWidth'));
                if (!(_adWidth > 0)) {
                    debug(
                        'GPT Slot #%d: Invalid ad width',
                        adSlot,
                        _adWidth
                    );
                    return;
                }
                if (containerWidth < _adWidth) {
                    debug(
                        'GPT Slot #%d: containerWidth (%d) < _adWidth (%d)',
                        adSlot,
                        containerWidth,
                        _adWidth
                    );
                    return;
                }
            }

            $ad = $_ad;

            // stop as soon as one suitable ad is found
            return false;
        });
        if ($ad === null) {
            debug('GPT Slot #%d: no ad', adSlot);
            return;
        }

        var $container = $('<ins class="renderedAd"></ins>').appendTo($loader),
            containerId = $container.uniqueId().attr('id'),
            gptUnitPath = $ad.data('gptUnitPath'),
            gptSizeWidth = parseInt($ad.data('gptSizeWidth')),
            gptSizeHeight = parseInt($ad.data('gptSizeHeight')),
            gptSize = null;

        if (!gptUnitPath) {
            debug('GPT Slot #%d: gptUnitPath is missing', adSlot);
            return;
        }

        if (gptSizeWidth > 0 && gptSizeHeight > 0) {
            gptSize = [gptSizeWidth, gptSizeHeight];
            $container.css('width', gptSizeWidth + 'px');
            $container.css('height', gptSizeHeight + 'px');
        }

        window.googletag.cmd.push(function () {
            //noinspection JSUnresolvedFunction
            window.googletag.defineSlot(gptUnitPath, gptSize, containerId).addService(googletag.pubads());
            window.googletag.display(containerId);
            debug(
                'GPT Slot #%d: window.googletag.display(%s); # adUnitPath=%s, adSize',
                adSlot,
                containerId,
                gptUnitPath,
                gptSize
            );
        });
    };

    // *********************************************************************

    XenForo.register('div.adWidget', 'XenForo.bdAd_Widget');

}(jQuery, this, document);