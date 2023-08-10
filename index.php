<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 * Adapted from /src/api.php in the LOVD3 project.
 *
 * Created     : 2023-08-02
 * Modified    : 2023-08-09   // When modified, also change the library_version.
 * For LOVD    : 3.0-29
 *
 * Supported URIs (/v# is optional):
 *  3.0-29 (v1)  /v#/                        (GET/HEAD)
 *  3.0-29 (v1)  /v#/catalogs                (GET/HEAD)
 *  3.0-29 (v1)  /v#/catalog/<UUID>          (GET/HEAD)
 *  3.0-29 (v1)  /v#/catalog/<UUID>/datasets (GET/HEAD)
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

define('ROOT_PATH', './');
require ROOT_PATH . 'inc-init.php';
require ROOT_PATH . 'class/api.php';

// The API's constructor already parses the URL, makes sure the method is valid, and handles the request.
$_API = new LOVD_API();
?>
