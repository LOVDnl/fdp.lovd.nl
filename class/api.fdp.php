<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2023-08-03
 * Modified    : 2023-08-15   // When modified, also change the library_version.
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
    private $aLOVDs = [];
    private $aDistributions = [
        'html' => [
            'title' => 'Web interface',
            'type' => 'dcat:accessURL',
            'url' => 'genes/{{GENE}}',
        ],
        'json/v2' => [
            'title' => 'LOVD2-style JSON API',
            'type' => 'dcat:downloadURL',
            'url' => 'api/rest/variants/{{GENE}}?format=application/json',
        ],
    ];
    private $sEpoch = '2023-08-03T15:38:19+02:00';





    function __construct (&$oAPI)
    {
        // Links the API to the private variable.

        if (!is_object($oAPI) || !is_a($oAPI, 'LOVD_API')) {
            return false;
        }
        $this->API = $oAPI;
        $this->API->aResponse['library_version'] = '2023-08-15';

        // Fetch the LOVD data.
        // Currently, we just have a fixed list of LSDB IDs that we include here.
        $aLOVDs = array(
            '53786324d4c6cf1d33a3e594a92591aa',
        );
        $this->aLOVDs = array_combine(
            array_map(
                [$this->API, 'generateUUIDFromLOVDID'],
                $aLOVDs
            ),
            $aLOVDs
        );

        return true;
    }





    private function checkIfLOVDExistsOrDie ($sUUID)
    {
        // Checks if the LOVD is known to us; returns a fatal error otherwise.

        if (!isset($this->aLOVDs[$sUUID])) {
            // LOVD does not exist.
            $this->API->aResponse['errors'][] = 'The catalog you requested does not exist.';
            $this->API->sendHeader(404, true); // Send HTTP status code, print response, and quit.
        }
    }





    private function downloadFromLOVDOrDie ($sURL, $sGene)
    {
        // Downloads the gene's data from the LOVD API; returns a fatal error otherwise.

        // FIXME: Set a short timeout?
        $aGene = array();
        $aJSONResponse = @lovd_php_file($sURL . 'api/rest/genes/' . $sGene . '?format=application/json');
        if ($aJSONResponse !== false) {
            $aJSONResponse = @json_decode(implode($aJSONResponse), true);
            if ($aJSONResponse !== false) {
                $aGene = $aJSONResponse;
            }
        }

        if (!$aGene) {
            // Somehow, we couldn't fetch data from the LOVD.
            $this->API->aResponse['errors'][] = "Could not fetch remote data for the $sGene gene at $sURL.";
            $this->API->sendHeader(500, true); // Send HTTP status code, print response, and quit.
        }

        return $aGene;
    }





    private function downloadFromVarcacheOrDie ($sUUID, $sGene = false)
    {
        // Downloads the LOVD data from Varcache; returns a fatal error otherwise.

        $aLOVD = array();
        $aJSONResponse = @lovd_php_file('https://varcache.lovd.nl/api/locations/' . $this->aLOVDs[$sUUID] . '/genes' .
            (!$sGene? '' : '/' . $sGene));
        if ($aJSONResponse !== false) {
            $aJSONResponse = @json_decode(implode($aJSONResponse), true);
            if ($aJSONResponse !== false) {
                $aLOVD = $aJSONResponse;
            }
        }

        if (!$aLOVD) {
            // Somehow, we couldn't fetch data from Varcache.
            $this->API->aResponse['errors'][] = "Could not fetch remote data for catalog $sUUID.";
            $this->API->sendHeader(500, true); // Send HTTP status code, print response, and quit.
        }

        // But does the gene exist?
        if ($sGene && (!isset($aLOVD['genes']) || !$aLOVD['genes'])) {
            // Genes array doesn't exist (shouldn't happen!), or it's empty. Gene does not exist.
            $this->API->aResponse['errors'][] = "Could not fetch remote data for catalog $sUUID, dataset $sGene.";
            $this->API->sendHeader(500, true); // Send HTTP status code, print response, and quit.
        }

        return $aLOVD;
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

        // Check URL structure and handle the request.
        // We receive all FDP endpoints here.
        if (!$this->API->sResource && !$aURLElements) {
            return $this->showFDP();
        } elseif ($this->API->sResource == 'catalogs' && !$aURLElements) {
            // Return just the catalogs; unset the FAIRDataPoint data.
            return ($this->showFDP() && array_shift($this->API->aResponse['@graph']));
        } elseif ($this->API->sResource == 'catalog' && count($aURLElements) == 1) {
            // Return just one catalog, possibly containing datasets.
            return $this->showFDPCatalog($aURLElements[0]);
        } elseif ($this->API->sResource == 'catalog' && count($aURLElements) == 2 && $aURLElements[1] == 'datasets') {
            // Return just the catalog's datasets; unset the catalog data.
            return ($this->showFDPCatalog($aURLElements[0]) && array_shift($this->API->aResponse['@graph']));
        } elseif ($this->API->sResource == 'catalog' && count($aURLElements) == 3 && $aURLElements[1] == 'dataset') {
            // Return just one dataset; containing two distributions.
            return $this->showFDPDataset($aURLElements[0], $aURLElements[2]);
        } elseif ($this->API->sResource == 'catalog' && count($aURLElements) == 4 && $aURLElements[1] == 'dataset' && $aURLElements[3] == 'distributions') {
            // Return just the dataset's distributions; unset the dataset data.
            return ($this->showFDPDataset($aURLElements[0], $aURLElements[2]) && array_shift($this->API->aResponse['@graph']));
        } elseif ($this->API->sResource == 'catalog' && count($aURLElements) >= 5 && $aURLElements[1] == 'dataset' && $aURLElements[3] == 'distribution') {
            // Return just one distribution.
            // A distribution can be made out of multiple URL elements (e.g., "json/v2"). Take the whole string.
            $sDistribution = implode('/', array_slice($aURLElements, 4));
            return $this->showFDPDistribution($aURLElements[0], $aURLElements[2], $sDistribution);
        } else {
            // Something invalid happened.
            $this->API->nHTTPStatus = 400; // Send 400 Bad Request.
            $this->API->aResponse['errors'][] = 'Could not parse the given request.';
            return false;
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
                        '@value' => $this->sEpoch,
                    ],
                    'http://purl.org/fdp/fdp-o#metadataModified' => [
                        '@type' => 'http://www.w3.org/2001/XMLSchema#dateTime',
                        '@value' => date('c'),
                    ],
                    'http://purl.org/fdp/fdp-o#hasSoftwareVersion' => $this->API->aResponse['library_version'],
                    'http://purl.org/fdp/fdp-o#conformsToFdpSpec' => 'https://specs.fairdatapoint.org/',
                    'http://purl.org/fdp/fdp-o#metadataCatalog' => [],
                ],
                [
                    '@id' => lovd_getInstallURL() . 'catalogs/',
                    '@type' => 'http://www.w3.org/ns/ldp#DirectContainer',
                    'http://purl.org/dc/terms/title' => 'Leiden Open Variation Database (LOVD) Catalogs',
                    'http://www.w3.org/ns/ldp#membershipResource' => lovd_getInstallURL(),
                    'http://www.w3.org/ns/ldp#hasMemberRelation' => 'http://purl.org/fdp/fdp-o#metadataCatalog',
                    'http://www.w3.org/ns/ldp#contains' => [],
                ],
            ],
        ];

        foreach (array_keys($this->aLOVDs) as $sUUID) {
            $this->API->aResponse['@graph'][0]['http://purl.org/fdp/fdp-o#metadataCatalog'][] = lovd_getInstallURL() . 'catalog/' . $sUUID;
        }
        $this->API->aResponse['@graph'][1]['http://www.w3.org/ns/ldp#contains'] = $this->API->aResponse['@graph'][0]['http://purl.org/fdp/fdp-o#metadataCatalog'];

        return true;
    }





    private function showFDPCatalog ($sUUID)
    {
        // Shows one of the FDP's catalogs (an LOVD instance).

        // First, check if the LOVD exist.
        $this->checkIfLOVDExistsOrDie($sUUID);

        // For HEAD requests, we're done here.
        if (!$this->bReturnBody) {
            $this->API->sendHeader(200, true); // Send HTTP status code, print response, and quit.
            return true;
        }

        // Fetch data from varcache.
        $aLOVD = $this->downloadFromVarcacheOrDie($sUUID);

        // Create simplified array structure. The API code will later convert it to proper JSON-LD or TTL.
        $this->API->aResponse = [
            // Unnamed (default) graph, as no '@id' is specified here. A graph of all nodes.
            // In this case, the catalog's node and the datasets node.
            '@graph' => [
                [
                    '@id' => lovd_getInstallURL() . CURRENT_PATH,
                    '@type' => 'http://www.w3.org/ns/dcat#Catalog',
                    'http://purl.org/dc/terms/title' => 'Leiden Open Variation Database (LOVD) instance at ' . $aLOVD['url'],
                    'http://purl.org/dc/terms/description' => 'This catalog lists the metadata for the public LOVD instance at ' . $aLOVD['url'] . '.',
                    'http://purl.org/dc/terms/publisher' => [
                        '@id' => lovd_getInstallURL() . '#publisher',
                        '@type' => 'http://xmlns.com/foaf/0.1/Agent',
                        'http://xmlns.com/foaf/0.1/name' => 'Leiden Open Variation Database',
                        'http://xmlns.com/foaf/0.1/homepage' => 'https://lovd.nl',
                    ],
                    'http://purl.org/dc/terms/language' => 'http://id.loc.gov/vocabulary/iso639-1/en',
                    'http://purl.org/dc/terms/license' => 'http://purl.org/net/rdflicense/cc-by-sa4.0',
                    'http://purl.org/dc/terms/isPartOf' => lovd_getInstallURL(),
                    'http://purl.org/fdp/fdp-o#metadataIdentifier' => lovd_getInstallURL() . CURRENT_PATH . '#identifier',
                    'http://purl.org/fdp/fdp-o#metadataIssued' => [
                        '@type' => 'http://www.w3.org/2001/XMLSchema#dateTime',
                        '@value' => $this->sEpoch, // This is the time the FDP was launched. All instances we share are older than this.
                    ],
                    'http://purl.org/fdp/fdp-o#metadataModified' => [
                        '@type' => 'http://www.w3.org/2001/XMLSchema#dateTime',
                        '@value' => date('c', strtotime($aLOVD['updated_date'])),
                    ],
                    'http://xmlns.com/foaf/0.1/homepage' => $aLOVD['url'],
                    'http://www.w3.org/ns/dcat#dataset' => [],
                ],
                [
                    '@id' => lovd_getInstallURL() . 'catalog/' . $this->API->generateUUIDFromLOVDID('53786324d4c6cf1d33a3e594a92591aa') . '/datasets',
                    '@type' => 'http://www.w3.org/ns/ldp#DirectContainer',
                    'http://purl.org/dc/terms/title' => 'Datasets as at ' . $aLOVD['url'],
                    'http://www.w3.org/ns/ldp#membershipResource' => lovd_getInstallURL() . 'catalog/' . $this->API->generateUUIDFromLOVDID('53786324d4c6cf1d33a3e594a92591aa'),
                    'http://www.w3.org/ns/ldp#hasMemberRelation' => 'http://www.w3.org/ns/dcat#dataset',
                    'http://www.w3.org/ns/ldp#contains' => [],
                ],
            ],
        ];

        foreach ($aLOVD['genes'] as $sGene) {
            $this->API->aResponse['@graph'][0]['http://www.w3.org/ns/dcat#dataset'][] = lovd_getInstallURL() . 'catalog/' . $sUUID . '/dataset/' . $sGene;
        }
        $this->API->aResponse['@graph'][1]['http://www.w3.org/ns/ldp#contains'] = $this->API->aResponse['@graph'][0]['http://www.w3.org/ns/dcat#dataset'];

        return true;
    }





    private function showFDPDataset ($sUUID, $sGene)
    {
        // Shows one of the FDP's catalog's datasets (a gene).

        // First, check if the LOVD exist.
        $this->checkIfLOVDExistsOrDie($sUUID);

        // Fetch data from varcache.
        $aLOVD = $this->downloadFromVarcacheOrDie($sUUID, $sGene);

        // For HEAD requests, we're done here.
        if (!$this->bReturnBody) {
            $this->API->sendHeader(200, true); // Send HTTP status code, print response, and quit.
            return true;
        }

        // Now also fetch some data directly from this LOVD.
        $aGene = $this->downloadFromLOVDOrDie($aLOVD['url'], $sGene);

        // Create simplified array structure. The API code will later convert it to proper JSON-LD or TTL.
        $this->API->aResponse = [
            // Unnamed (default) graph, as no '@id' is specified here. A graph of all nodes.
            // In this case, the dataset's node and the distributions node.
            '@graph' => [
                [
                    '@id' => lovd_getInstallURL() . CURRENT_PATH,
                    '@type' => 'http://www.w3.org/ns/dcat#Dataset',
                    'http://purl.org/dc/terms/title' => 'Leiden Open Variation Database (LOVD) instance at ' . $aLOVD['url'] . ', dataset ' . $sGene,
                    'http://purl.org/dc/terms/description' => 'This dataset lists the metadata for the ' . $sGene . ' gene in the public LOVD instance at ' . $aLOVD['url'] . '.',
                    'http://purl.org/dc/terms/publisher' => [
                        '@id' => lovd_getInstallURL() . '#publisher',
                        '@type' => 'http://xmlns.com/foaf/0.1/Agent',
                        'http://xmlns.com/foaf/0.1/name' => 'Leiden Open Variation Database',
                        'http://xmlns.com/foaf/0.1/homepage' => 'https://lovd.nl',
                    ],
                    'http://purl.org/dc/terms/language' => 'http://id.loc.gov/vocabulary/iso639-1/en',
                    'http://purl.org/dc/terms/license' => 'http://purl.org/net/rdflicense/cc-by-sa4.0',
                    'http://purl.org/dc/terms/isPartOf' => lovd_getInstallURL() . 'catalog/' . $this->API->generateUUIDFromLOVDID('53786324d4c6cf1d33a3e594a92591aa'),
                    'http://purl.org/fdp/fdp-o#metadataIdentifier' => lovd_getInstallURL() . CURRENT_PATH . '#identifier',
                    'http://purl.org/fdp/fdp-o#metadataIssued' => [
                        '@type' => 'http://www.w3.org/2001/XMLSchema#dateTime',
                        '@value' => date('c', max(strtotime($this->sEpoch), strtotime($aGene['created_date']))), // The epoch or the gene's creation date, whatever came last.
                    ],
                    'http://purl.org/fdp/fdp-o#metadataModified' => [
                        '@type' => 'http://www.w3.org/2001/XMLSchema#dateTime',
                        '@value' => date('c', max(strtotime($this->sEpoch), strtotime($aGene['updated_date']))), // The epoch or the gene's modification date, whatever came last.
                    ],
                    'http://xmlns.com/foaf/0.1/homepage' => $aLOVD['url'] . 'genes/' . $sGene,
                    'http://www.w3.org/ns/dcat#distribution' => [],
                ],
                [
                    '@id' => lovd_getInstallURL() . 'catalog/' . $this->API->generateUUIDFromLOVDID('53786324d4c6cf1d33a3e594a92591aa') . '/dataset/' . $sGene . '/distributions',
                    '@type' => 'http://www.w3.org/ns/ldp#DirectContainer',
                    'http://purl.org/dc/terms/title' => 'Distributions for the ' . $sGene . ' gene at ' . $aLOVD['url'],
                    'http://www.w3.org/ns/ldp#membershipResource' => lovd_getInstallURL() . 'catalog/' . $this->API->generateUUIDFromLOVDID('53786324d4c6cf1d33a3e594a92591aa') . '/dataset/' . $sGene,
                    'http://www.w3.org/ns/ldp#hasMemberRelation' => 'http://www.w3.org/ns/dcat#distribution',
                    'http://www.w3.org/ns/ldp#contains' => [],
                ],
            ],
        ];

        foreach (array_keys($this->aDistributions) as $sDistribution) {
            $this->API->aResponse['@graph'][0]['http://www.w3.org/ns/dcat#distribution'][] = lovd_getInstallURL() . 'catalog/' . $sUUID . '/dataset/' . $sGene . '/distribution/' . $sDistribution;
        }
        $this->API->aResponse['@graph'][1]['http://www.w3.org/ns/ldp#contains'] = $this->API->aResponse['@graph'][0]['http://www.w3.org/ns/dcat#distribution'];

        return true;
    }





    private function showFDPDistribution ($sUUID, $sGene, $sDistribution)
    {
        // Shows one of the FDP's catalog's dataset's distributions (an interface).

        // First, check if the LOVD exist.
        $this->checkIfLOVDExistsOrDie($sUUID);

        // Fetch data from varcache.
        $aLOVD = $this->downloadFromVarcacheOrDie($sUUID, $sGene);

        // And does the distribution exist?
        if (!isset($this->aDistributions[$sDistribution])) {
            // Nope, distribution is unknown.
            $this->API->aResponse['errors'][] = 'The distribution you requested does not exist.';
            $this->API->sendHeader(404, true); // Send HTTP status code, print response, and quit.
        }

        // For HEAD requests, we're done here.
        if (!$this->bReturnBody) {
            $this->API->sendHeader(200, true); // Send HTTP status code, print response, and quit.
            return true;
        }

        // Create simplified array structure. The API code will later convert it to proper JSON-LD or TTL.
        $this->API->aResponse = [
            // Unnamed (default) graph, as no '@id' is specified here. A graph of all nodes.
            // In this case, the distribution's node.
            '@graph' => [
                [
                    '@id' => lovd_getInstallURL() . CURRENT_PATH,
                    '@type' => 'http://www.w3.org/ns/dcat#Distribution',
                    'http://purl.org/dc/terms/title' => 'Leiden Open Variation Database (LOVD) instance at ' . $aLOVD['url'] . ', dataset ' . $sGene . ', the ' . $this->aDistributions[$sDistribution]['title'],
                    'http://purl.org/dc/terms/description' => 'This distribution lists the metadata for the ' . $this->aDistributions[$sDistribution]['title'] . ' of the ' . $sGene . ' gene in the public LOVD instance at ' . $aLOVD['url'] . '.',
                    'http://purl.org/dc/terms/publisher' => [
                        '@id' => lovd_getInstallURL() . '#publisher',
                        '@type' => 'http://xmlns.com/foaf/0.1/Agent',
                        'http://xmlns.com/foaf/0.1/name' => 'Leiden Open Variation Database',
                        'http://xmlns.com/foaf/0.1/homepage' => 'https://lovd.nl',
                    ],
                    'http://purl.org/dc/terms/language' => 'http://id.loc.gov/vocabulary/iso639-1/en',
                    'http://purl.org/dc/terms/license' => 'http://purl.org/net/rdflicense/cc-by-sa4.0',
                    'http://purl.org/dc/terms/isPartOf' => lovd_getInstallURL() . 'catalog/' . $this->API->generateUUIDFromLOVDID('53786324d4c6cf1d33a3e594a92591aa') . '/dataset/' . $sGene,
                    'http://purl.org/fdp/fdp-o#metadataIdentifier' => lovd_getInstallURL() . CURRENT_PATH . '#identifier',
                    'http://purl.org/fdp/fdp-o#metadataIssued' => [
                        '@type' => 'http://www.w3.org/2001/XMLSchema#dateTime',
                        '@value' => $this->sEpoch,
                    ],
                    'http://purl.org/fdp/fdp-o#metadataModified' => [
                        '@type' => 'http://www.w3.org/2001/XMLSchema#dateTime',
                        '@value' => date('c'),
                    ],
                    str_replace('dcat:', 'http://www.w3.org/ns/dcat#', $this->aDistributions[$sDistribution]['type']) => $aLOVD['url'] . str_replace('{{GENE}}', $sGene, $this->aDistributions[$sDistribution]['url']),
                ],
            ],
        ];
        return true;
    }
}
?>
