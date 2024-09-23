<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 * Adapted from /src/inc-lib-init.php in the LOVD3 project.
 *
 * Created     : 2023-08-02
 * Modified    : 2023-08-09   // When modified, also change the library_version.
 *
 * Copyright   : 2004-2023 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmer  : Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
 *
 *
 * This file is part of LOVD.
 *
 * LOVD is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * LOVD is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with LOVD.  If not, see <http://www.gnu.org/licenses/>.
 *
 *************/

function lovd_cleanDirName ($s)
{
    // Cleans a given path by resolving a relative path.
    if (!is_string($s)) {
        // No input.
        return false;
    }

    // Clean up the pwd; remove '\' (some PHP versions under Windows seem to escape the slashes with backslashes???)
    $s = stripslashes($s);
    // Clean up the pwd; remove '//'
    $s = preg_replace('/\/+/', '/', $s);
    // Clean up the pwd; remove '/./'
    $s = preg_replace('/\/\.\//', '/', $s);
    // Clean up the pwd; remove '/dir/../'
    $s = preg_replace('/\/[^\/]+\/\.\.\//', '/', $s);
    // Hackers may try to give us links that start with a parent dir. That would cause an infinite loop.
    $s = preg_replace('/^\/\.\.\//', '/', $s);

    if (preg_match('/\/(\.)?\.\//', $s)) {
        // Still not clean... Pff...
        $s = lovd_cleanDirName($s);
    }

    return $s;
}





function lovd_getInstallURL ($bFull = true)
{
    // Returns URL that can be used in URLs or redirects.
    // ROOT_PATH can be relative or absolute.
    return (!$bFull? '' : PROTOCOL . $_SERVER['HTTP_HOST']) .
        lovd_cleanDirName(substr(ROOT_PATH, 0, 1) == '/'? ROOT_PATH : dirname($_SERVER['SCRIPT_NAME']) . '/' . ROOT_PATH);
}





function lovd_php_file ($sURL, $bHeaders = false, $sPOST = false, $aAdditionalHeaders = array()) {
    // LOVD's alternative to file(), not dependent on the fopen wrappers, and can do POST requests.
    global $_CONF, $_SETT;

    // Check additional headers.
    if (!is_array($aAdditionalHeaders)) {
        $aAdditionalHeaders = array($aAdditionalHeaders);
    }

    // Prepare proxy authorization header.
    if (!empty($_CONF['proxy_username']) && !empty($_CONF['proxy_password'])) {
        $aAdditionalHeaders[] = 'Proxy-Authorization: Basic ' . base64_encode($_CONF['proxy_username'] . ':' . $_CONF['proxy_password']);
    }

    $aAdditionalHeaders[] = ''; // To make sure we end with a \r\n.

    // Use the simple file() method, only if:
    // - We're working with local files, OR:
    // - We're using HTTPS (because our fsockopen() currently doesn't support that, let's hope allow_url_fopen is on), OR:
    // - Fopen wrappers are on.
    if (substr($sURL, 0, 4) != 'http' || substr($sURL, 0, 5) == 'https' || ini_get('allow_url_fopen')) {
        // Normal file() is fine.
        $aOptions = array(
            'http' => array(
                'method' => ($sPOST? 'POST' : 'GET'),
                'header' => $aAdditionalHeaders,
                'user_agent' => 'fdp.lovd.nl',
            ),
        );

        if ($sPOST) {
            // Add POST content to HTTP options and headers.
            $aOptions['http']['content'] = $sPOST;
            array_unshift($aOptions['http']['header'], 'Content-Type: application/x-www-form-urlencoded');
        }

        // If we're connecting through a proxy, we need to set some additional information.
        if (!empty($_CONF['proxy_host'])) {
            $aOptions['http']['proxy'] = 'tcp://' . $_CONF['proxy_host'] . ':' . $_CONF['proxy_port'];
            $aOptions['http']['request_fulluri'] = true;
        }
        if (substr($sURL, 0, 5) == 'https') {
            $aOptions['ssl'] = array('allow_self_signed' => 1, 'SNI_enabled' => 1, (PHP_VERSION_ID >= 50600? 'peer_name' : 'SNI_server_name') => parse_url($sURL, PHP_URL_HOST));
            $aOptions['http']['request_fulluri'] = false; // Somehow this breaks when testing through squid3 and using HTTPS.
        }

        return @file($sURL, FILE_IGNORE_NEW_LINES, stream_context_create($aOptions));
    }

    $aHeaders = array();
    $aOutput = array();
    $aURL = parse_url($sURL);
    if ($aURL['host']) {
        // fsockopen() can only connect to an HTTPS (proxy or host), when using "ssl" as the scheme, and having OpenSSL installed.
        $f = @fsockopen((!empty($_CONF['proxy_host'])? $_CONF['proxy_host'] : $aURL['host']), (!empty($_CONF['proxy_port'])? $_CONF['proxy_port'] : 80));
        if ($f === false) {
            // No use continuing - it will only cause errors.
            return false;
        }
        $sRequest = ($sPOST? 'POST ' : 'GET ') . (!empty($_CONF['proxy_host'])? $sURL : $aURL['path'] . (empty($aURL['query'])? '' : '?' . $aURL['query'])) . ' HTTP/1.0' . "\r\n" .
                    'Host: ' . $aURL['host'] . "\r\n" .
                    'User-Agent: fdp.lovd.nl' . "\r\n" .
                    (!$sPOST? '' :
                    'Content-length: ' . strlen($sPOST) . "\r\n" .
                    'Content-Type: application/x-www-form-urlencoded' . "\r\n") .
            implode("\r\n", $aAdditionalHeaders) .
                    'Connection: Close' . "\r\n\r\n" .
                    (!$sPOST? '' :
                    $sPOST . "\r\n");
        fputs($f, $sRequest);
        $bListen = false; // We want to start capturing the output AFTER the headers have ended.
        while (!feof($f)) {
            $s = fgets($f);
            if ($s === false) {
                // This mysteriously may happen at the first fgets() call???
                continue;
            }
            $s = rtrim($s, "\r\n");
            if ($bListen) {
                $aOutput[] = $s;
            } else {
                if (!$s) {
                    $bListen = true;
                } else {
                    $aHeaders[] = $s;
                }
            }
        }
        fclose($f);

        // On some status codes we return false.
        if (isset($aHeaders[0]) && preg_match('/^HTTP\/1\.. (\d{3}) /', $aHeaders[0], $aRegs)) {
            if ($aRegs[1] == '404') {
                return false;
            }
        }
    }

    if (!$bHeaders) {
        return($aOutput);
    } else {
        return(array($aHeaders, $aOutput));
    }
}
?>
