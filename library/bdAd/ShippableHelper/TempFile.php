<?php

// updated by DevHelper_Helper_ShippableHelper at 2018-02-10T00:30:27+00:00

/**
 * Class bdAd_ShippableHelper_TempFile
 * @version 16
 * @see DevHelper_Helper_ShippableHelper_TempFile
 */
class bdAd_ShippableHelper_TempFile
{
    protected static $_cached = array();
    protected static $_latestDownloadHeaders = array();
    protected static $_maxDownloadSize = 0;
    protected static $_registeredShutdownFunction = false;

    /**
     * Downloads and put content into the specified temp file
     *
     * @param string $url
     * @param string $tempFile
     */
    public static function cache($url, $tempFile)
    {
        self::$_cached[$url] = $tempFile;

        if (!self::$_registeredShutdownFunction) {
            register_shutdown_function(array(__CLASS__, 'deleteAllCached'));
        }
    }

    /**
     * Creates a new temp file with the given contents and prefix.
     *
     * @param string|null $contents if null, no contents will be written to file
     * @param string|null $prefix if null, the default prefix will be used
     * @return string
     */
    public static function create($contents = null, $prefix = null)
    {
        if ($prefix === null) {
            $prefix = self::_getPrefix();
        }

        $tempFile = tempnam(XenForo_Helper_File::getTempDir(), $prefix);
        self::cache(sprintf('%s::%s', __METHOD__, md5($tempFile)), $tempFile);

        if ($contents !== null) {
            file_put_contents($tempFile, $contents);
        }

        return $tempFile;
    }

    public static function download($url, array $options = array())
    {
        self::$_latestDownloadHeaders = array();

        $options += array(
            'tempFile' => '',
            'userAgent' => '',
            'timeOutInSeconds' => 0,
            'maxRedirect' => 3,
            'maxDownloadSize' => XenForo_Application::getOptions()->get('attachmentMaxFileSize') * 1024,
            'secured' => 0,
        );

        $tempFile = trim(strval($options['tempFile']));
        $managedTempFile = false;
        if (strlen($tempFile) === 0) {
            $tempFile = tempnam(XenForo_Helper_File::getTempDir(), self::_getPrefix());
            $managedTempFile = true;
        }
        if (strlen($tempFile) === 0) {
            return false;
        }

        if (isset(self::$_cached[$url])
            && filesize(self::$_cached[$url]) > 0
        ) {
            if ($managedTempFile) {
                unlink($tempFile);
                return self::$_cached[$url];
            } else {
                copy(self::$_cached[$url], $tempFile);
                return $tempFile;
            }
        }

        if ($managedTempFile) {
            self::cache($url, $tempFile);
        }

        self::$_maxDownloadSize = $options['maxDownloadSize'];

        $fh = fopen($tempFile, 'wb');

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_FILE, $fh);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, array(__CLASS__, 'download_curlHeaderFunction'));
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_NOPROGRESS, 0);
        curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, array(__CLASS__, 'download_curlProgressFunction'));

        if (!empty($options['userAgent'])) {
            curl_setopt($ch, CURLOPT_USERAGENT, $options['userAgent']);
        }
        if ($options['timeOutInSeconds'] > 0) {
            curl_setopt($ch, CURLOPT_TIMEOUT, $options['timeOutInSeconds']);
        }
        if ($options['maxRedirect'] > 0) {
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt($ch, CURLOPT_MAXREDIRS, $options['maxRedirect']);
        } else {
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
        }
        if ($options['secured'] === 0) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        }

        curl_exec($ch);
        $curlInfo = curl_getinfo($ch);
        curl_close($ch);
        fclose($fh);

        $error = null;
        if (!isset($curlInfo['http_code'])
            || $curlInfo['http_code'] < 200
            || $curlInfo['http_code'] >= 300
        ) {
            // no http response status / non success status, must be an error
            $error = 'http_code';
        }

        $fileSize = 0;
        if ($error === null) {
            $fileSize = filesize($tempFile);
            if ($fileSize === 0) {
                clearstatcache();
                $fileSize = filesize($tempFile);
            }
        }
        if ($error === null && $fileSize === 0) {
            // no data written to disk, probably a disk error
            $error = 'file size 0';
        }

        if ($error === null
            && isset($curlInfo['size_download'])
            && $fileSize !== intval($curlInfo['size_download'])
        ) {
            // file size reported by our system seems to be off, probably a write error
            $error = sprintf('file size %d, size_download %d', $fileSize, $curlInfo['size_download']);
        }

        if ($error === null
            && isset($curlInfo['download_content_length'])
            && $curlInfo['download_content_length'] > 0
            && $fileSize !== intval($curlInfo['download_content_length'])
        ) {
            // file size is different from Content-Length header, probably a cancelled download (or corrupted)
            $error = sprintf('file size %d, Content-Length %d', $fileSize, $curlInfo['download_content_length']);
        }

        if (XenForo_Application::debugMode()) {
            XenForo_Helper_File::log(__CLASS__, call_user_func_array('sprintf', array(
                'download %s -> %s, %s, %s',
                $url,
                $tempFile,
                ($error === null ? 'succeeded' : ('failed: ' . $error)),
                json_encode($curlInfo),
            )));
        }

        if ($error === null) {
            return $tempFile;
        } else {
            file_put_contents($tempFile, '');
            return false;
        }
    }

    public static function download_curlHeaderFunction($curl, $header)
    {
        self::$_latestDownloadHeaders[] = $header;

        return strlen($header);
    }

    public static function download_curlProgressFunction($downloadSize, $downloaded)
    {
        return ((self::$_maxDownloadSize > 0
            && ($downloadSize > self::$_maxDownloadSize
                || $downloaded > self::$_maxDownloadSize))
            ? 1 : 0);
    }

    public static function deleteAllCached()
    {
        foreach (self::$_cached as $url => $tempFile) {
            if (XenForo_Application::debugMode()) {
                $fileSize = @filesize($tempFile);
            }

            $deleted = @unlink($tempFile);

            if (XenForo_Application::debugMode()) {
                XenForo_Helper_File::log(__CLASS__, call_user_func_array('sprintf', array(
                    'delete %s -> %s, %s, %d bytes',
                    $url,
                    $tempFile,
                    ($deleted ? 'succeeded' : 'failed'),
                    (!empty($fileSize) ? $fileSize : 0),
                )));
            }
        }

        self::$_cached = array();
    }

    public static function getLatestDownloadHeaders()
    {
        return self::$_latestDownloadHeaders;
    }

    protected static function _getPrefix()
    {
        static $prefix = null;

        if ($prefix === null) {
            $prefix = strtolower(preg_replace('#[^A-Z]#', '', __CLASS__)) . '_';
        }

        return $prefix;
    }

}
