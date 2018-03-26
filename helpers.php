<?php
function stripLocalpart($mxid) {
	$localpart = NULL;
	if (!empty($mxid)) {
		// A mxid would start with an @ so we start at the 2. position
		$sepPos = strpos($mxid,':', 1);
		if ($sepPos === false) {
			// : not found. Assume mxid is localpart
			// TODO: further checks
			$localpart = $mxid;
		} else {
			$localpart = substr($mxid, 1, strpos($mxid,':') - 1 );
		}
	}
	return $localpart;
}

?>
