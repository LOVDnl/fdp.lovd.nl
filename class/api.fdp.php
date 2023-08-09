<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2023-08-03
 * Modified    : 2023-08-09   // When modified, also change the library_version.
 * For LOVD    : 3.0-29
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



class LOVD_API_FDP
{
    // This class defines the LOVD API object handling the FDP.

    private $API;                     // The API object.
    private $bReturnBody = true;      // Return the body? false for HEAD requests.





    function __construct (&$oAPI)
    {
        // Links the API to the private variable.

        if (!is_object($oAPI) || !is_a($oAPI, 'LOVD_API')) {
            return false;
        }
        $this->API = $oAPI;
        $this->API->aResponse['library_version'] = '2023-08-09';

        return true;
    }





    public function processGET ($aURLElements, $bReturnBody)
    {
        // Handle GET and HEAD requests for the FDP.
        // For HEAD requests, we won't print any output.
        // We could just check for the HEAD constant but this way the code will
        //  be more independent on the rest of the infrastructure.
        // Note that LOVD API's sendHeaders() function does check for HEAD and
        //  automatically won't print any contents if HEAD is used.
        $this->bReturnBody = $bReturnBody;

        if (is_array($aURLElements)) {
            // Strip the padded elements off.
            $aURLElements = array_diff($aURLElements, array(''));
        }

        // Check URL structure.
        if (!is_array($aURLElements)
            || ($this->API->sResource == 'catalog' && count($aURLElements) != 1)
            || ($this->API->sResource != 'catalog' && $aURLElements)) {
            // Something invalid happened.
            $this->API->nHTTPStatus = 400; // Send 400 Bad Request.
            $this->API->aResponse['errors'][] = 'Could not parse the given request.';
            return false;
        }

        // Now actually handle the request.
        // We receive all FDP endpoints here.
        if (!$this->API->sResource) {
            return $this->showFDP();
        } elseif ($this->API->sResource == 'catalogs') {
            // Return just the catalogs; unset the FAIRDataPoint data.
            return ($this->showFDP() && array_shift($this->API->aResponse['@graph']));
        } elseif ($this->API->sResource == 'catalog') {
            // Return just one catalog, possibly containing datasets or distributions.
            return $this->showFDPCatalog($aURLElements[0]);
        }

        // If we end up here, we didn't handle the request well.
        return false;
    }





    private function showFDP ()
    {
        // Shows the FDP output.

        // For HEAD requests, we're done here.
        if (!$this->bReturnBody) {
            $this->API->sendHeader(200, true); // Send HTTP status code, print response, and quit.
            return true;
        }

        // Create simplified array structure. The API code will later convert it to proper JSON-LD or TTL.
        $this->API->aResponse = [
            // Unnamed (default) graph, as no '@id' is specified here. A graph of all nodes.
            // In this case, the FDP node and the catalogs node.
            '@graph' => [
                [
                    '@id' => lovd_getInstallURL(),
                    '@type' => 'http://purl.org/fdp/fdp-o#FAIRDataPoint',
                    'http://purl.org/dc/terms/title' => 'Leiden Open Variation Database (LOVD) FAIR Data Point (FDP)',
                    'http://purl.org/dc/terms/description' => 'This FAIR Data Point lists public LOVD instances and some of their metadata.',
                    'http://purl.org/dc/terms/publisher' => [
                        '@id' => lovd_getInstallURL() . '#publisher',
                        '@type' => 'http://xmlns.com/foaf/0.1/Agent',
                        'http://xmlns.com/foaf/0.1/name' => 'Leiden Open Variation Database',
                        'http://xmlns.com/foaf/0.1/homepage' => 'https://lovd.nl',
                    ],
                    'http://purl.org/dc/terms/language' => 'http://id.loc.gov/vocabulary/iso639-1/en',
                    'http://purl.org/dc/terms/license' => 'http://purl.org/net/rdflicense/cc-by-sa4.0',
                    'http://www.w3.org/ns/dcat#contactPoint' => [
                        '@id' => lovd_getInstallURL() . '#contactPoint',
                        '@type' => 'http://www.w3.org/2006/vcard/ns#VCard',
                        'http://www.w3.org/2006/vcard/ns#fn' => 'LOVD team',
                        'http://www.w3.org/2006/vcard/ns#hasEmail' => 'LOVD@LOVD.nl',
                        'http://www.w3.org/2006/vcard/ns#hasURL' => 'https://lovd.nl/contact',
                    ],
                    'http://www.w3.org/ns/dcat#endpointURL' => lovd_getInstallURL(),
                    'http://purl.org/fdp/fdp-o#metadataIdentifier' => lovd_getInstallURL() . '#identifier',
                    'http://purl.org/fdp/fdp-o#metadataIssued' => [
                        '@type' => 'http://www.w3.org/2001/XMLSchema#dateTime',
                        '@value' => '2023-08-03T15:38:19+02:00',
                    ],
                    'http://purl.org/fdp/fdp-o#metadataModified' => [
                        '@type' => 'http://www.w3.org/2001/XMLSchema#dateTime',
                        '@value' => date('c'),
                    ],
                    'http://purl.org/fdp/fdp-o#hasSoftwareVersion' => $this->API->aResponse['library_version'],
                    'http://purl.org/fdp/fdp-o#conformsToFdpSpec' => 'https://specs.fairdatapoint.org/',
                    'http://purl.org/fdp/fdp-o#metadataCatalog' => [
                    ],
                ],
                [
                    '@id' => lovd_getInstallURL() . 'catalogs/',
                    '@type' => 'http://www.w3.org/ns/ldp#DirectContainer',
                    'http://purl.org/dc/terms/title' => 'Leiden Open Variation Database (LOVD) Catalogs',
                    'http://www.w3.org/ns/ldp#membershipResource' => lovd_getInstallURL(),
                    'http://www.w3.org/ns/ldp#hasMemberRelation' => 'http://purl.org/fdp/fdp-o#metadataCatalog',
                    'http://www.w3.org/ns/ldp#contains' => [
                    ],
                ],
            ],
        ];
        return true;
    }
}
?>
