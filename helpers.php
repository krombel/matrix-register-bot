<?php

/**
 * Copyright 2018 Matthias Kesler
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 * 
 * http://www.apache.org/licenses/LICENSE-2.0
 * 
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
function stripLocalpart($mxid) {
    $localpart = NULL;
    if (!empty($mxid)) {
        // A mxid would start with an @ so we start at the 2. position
        $sepPos = strpos($mxid, ':', 1);
        if ($sepPos === false) {
            // : not found. Assume mxid is localpart
            // TODO: further checks
            $localpart = $mxid;
        } else {
            $localpart = substr($mxid, 1, strpos($mxid, ':') - 1);
        }
    }
    return $localpart;
}

function getCurlHandle($url) {
    $handle = curl_init($url);
    curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($handle, CURLOPT_TIMEOUT, 60);
    curl_setopt($handle, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));
    return $handle;
}

?>