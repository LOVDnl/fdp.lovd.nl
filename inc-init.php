<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 * Adapted from /src/inc-init.php in the LOVD3 project.
 *
 * Created     : 2023-08-02
 * Modified    : 2023-08-04   // When modified, also change the library_version.
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

// Don't allow direct access.
if (!defined('ROOT_PATH')) {
    exit;
}

// Copied from api.lovd.nl. Not fully applicable, but better than leaving it out.
// This instance mostly returns JSON. Catch all errors and warnings and return these as JSON.
function lovd_API_handleError ($nError, $sError, $sFile, $nLine)
{
    // Based on the example given within the documentation text
    //  at https://www.php.net/set_error_handler.
    // License, according to https://www.php.net/license/index.php,
    //  is CC3.0-BY, compatible with GPL.
    if (!(error_reporting() & $nError)) {
        // This error code is not included in error_reporting, so let it
        //  fall through to the standard PHP error handler
        return false;
    }

    $aReturn = array(
        'version' => '',
        'messages' => array(),
        'warnings' => array(),
        'errors' => array(),
        'data' => array(),
    );

    switch ($nError) {
        case E_NOTICE:
            $aReturn['messages'][] = "PHP notice: \"$sError\" in $sFile on line $nLine.";
            $aReturn['warnings'][] = "Unhandled PHP notice in $sFile on line $nLine.";
            break;

        case E_WARNING:
            $aReturn['warnings'][] = "PHP warning: \"$sError\" in $sFile on line $nLine.";
            break;

        case E_ERROR:
        default:
            $aReturn['errors'][] = "PHP error: \"$sError\" in $sFile on line $nLine.";
            break;
    }

    header('Content-type: application/json; charset=UTF-8', true, 500);
    die(json_encode($aReturn, JSON_PRETTY_PRINT));
}
set_error_handler('lovd_API_handleError');

function lovd_API_handleException ($oException)
{
    return lovd_API_handleError(
        E_ERROR, // All Exceptions will be handled as Errors.
        $oException->getMessage(),
        $oException->getFile(),
        $oException->getLine()
    );
}
set_exception_handler('lovd_API_handleException');

// Sometimes inc-init.php gets run over CLI (LOVD+, external scripts, etc.).
// Handle that here, instead of building lots of code in many different places.
if (!isset($_SERVER['HTTP_HOST'])) {
    // To prevent notices...
    $_SERVER = array_merge($_SERVER, array(
        'HTTP_HOST' => 'localhost',
        'REQUEST_URI' => '/' . basename(__FILE__),
        'QUERY_STRING' => '',
        'REQUEST_METHOD' => 'GET',
    ));
}

// Require library standard functions.
require_once ROOT_PATH . 'inc-lib-init.php';

// Set error_reporting if necessary. We don't want notices to show. This will do
// fine most of the time.
if (ini_get('error_reporting') == E_ALL) {
    error_reporting(E_ALL ^ E_NOTICE);
}





// Define constants needed throughout LOVD.
// Find out whether or not we're using SSL.
if ((!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https') || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') || !empty($_SERVER['SSL_PROTOCOL'])) {
    // We're using SSL!
    define('SSL', true);
    define('PROTOCOL', 'https://');
} else {
    define('SSL', false);
    define('PROTOCOL', 'http://');
}

// Prevent some troubles with the menu or lovd_getProjectFile() when the URL contains double slashes or backslashes.
$_SERVER['SCRIPT_NAME'] = lovd_cleanDirName(str_replace('\\', '/', $_SERVER['SCRIPT_NAME']));

// Our output formats.
// Help a hand in case the user forgot to encode + to %2B.
$aFormats = array('application/ld+json', 'application/ld json', 'text/turtle');
if (!empty($_GET['format']) && in_array($_GET['format'], $aFormats)) {
    define('FORMAT', str_replace(' ', '+', $_GET['format']));
} else {
    // Don't enforce a default, so the API object will check the headers.
    define('FORMAT', '');
}



// Initiate Database Connection.
$_DB = false;



@ini_set('default_charset','UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}



// The following applies only if the system is fully installed.
if (!defined('NOT_INSTALLED')) {
    // Define $_PE ($_PATH_ELEMENTS) and CURRENT_PATH.
    // Take the part of REQUEST_URI before the '?' before rawurldecode()ing the string and running lovd_cleanDirName(),
    //  to make sure URL encoded question marks and arguments with '../' don't break the URL parsing.
    $sPath = preg_replace(
        '/^' . preg_quote(lovd_getInstallURL(false), '/') . '/',
        '',
        lovd_cleanDirName(
            html_entity_decode(
                rawurldecode(
                    strstr($_SERVER['REQUEST_URI'] . '?', '?', true)
                ), ENT_HTML5))); // 'login' or 'genes?create' or 'users/00001?edit'
    // Removed filtering of characters that we need to successfully receive variants in the URL.
    // Since we don't produce HTML, XSS isn't a problem for us.
    $_PE = explode('/', rtrim($sPath, '/')); // array('login') or array('genes') or array('users', '00001')

    define('CURRENT_PATH', implode('/', $_PE));
    define('PATH_COUNT', count($_PE)); // So you don't need !empty($_PE[1]) && ...

    // Define ACTION.
    if ($_SERVER['QUERY_STRING'] && preg_match('/^([\w-]+)(&.*)?$/', $_SERVER['QUERY_STRING'], $aRegs)) {
        define('ACTION', $aRegs[1]);
    } else {
        define('ACTION', false);
    }

    // Define constant for request method.
    define($_SERVER['REQUEST_METHOD'], true);
    @define('GET', false);
    @define('HEAD', false);
    @define('POST', false);
    @define('PUT', false);
    @define('DELETE', false);

} else {
    define('ACTION', false);
}
?>
