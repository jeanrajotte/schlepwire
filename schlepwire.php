<?php

/*

schlepwire.php

Containerize a processwire site, ALL code (including /wire/) and DB into a schelp-timestamp.zip file

The MIT License (MIT)

Copyright (c) 2014 Jean Rajotte

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.

*/

define('CUR_DIR', dirname(realpath(__FILE__)));
define('CONFIG_FILE', 'site/config.php');

if (!file_exists( CONFIG_FILE )) {
	die('ERROR: This does not appear to be a ProcessWire site. Nothing to do here!');
}

define( 'PROCESSWIRE', true);
define( 'ME', 'SchlepWire');

define( 'DEBUG', false);

$this_prog = basename( __FILE__ );

class X {
	function set() {}
}

function _dir( $dname ) {
	return array_filter( scandir($dname), function($fname) { return is_file($fname); });
}

// show existing packages
function schlep_files() {
	return _dir(SCHLEP_DIR);
}

// where we at
function show_status() {
	$fnames = glob('./schlep-*.zip');
	if (!count($fnames)) {
		echo <<<END
<h2>Create a new package</h2>
<form method='POST'>
	<button name='schlep' value='schlep' >Schlep</button>
</form>
<hr/>
END;
	} elseif (count($fnames)>1) {
		$s = join('<br>', $fnames);
		echo <<<END
<h3>There is more than one Schlep package.</h3> 
<p>Please remove all but the one of interest:<br><br>
$s
END;
	} else {
		$fname = basename( $fnames[0] );
		echo <<<END
<form id='go' method='POST'>
	<input type='hidden' name='fname' value='$fname' />
	<p>
		<b>$fname: </b>
		<button name='unschlep' value='unschlep'>Unschlep</button>
		or
		<button name='download' value='download'>Download</button>
	</p>
</form>
<a href='/'>Test the site</a> and <b>remember to remove schlep* files from the root</b>. 
<hr/>
END;
	}
}

function zip_relative( $zip, $dname) {

	// Create recursive directory iterator
	$files = new RecursiveIteratorIterator(
	    new RecursiveDirectoryIterator( $dname ),
	    RecursiveIteratorIterator::LEAVES_ONLY
	);

	foreach ($files as $name => $file) {
	    // keep relative path, but replace O/S specific chars
	    $zip->addFile( $file, str_replace(DIRECTORY_SEPARATOR, "/", $file) );

	}

}

// load the bits of config we need, is all...
function _mysql_args() {
	$config = new X;
	include CONFIG_FILE ;
	return  // '-v' .
		' "--host='.$config->dbHost
		.'" "--port='.$config->dbPort
		.'" "--password='.$config->dbPass . (DEBUG ? '222' : '')
		.'" "--user='.$config->dbUser
		.'" '.$config->dbName;
}

// create a package
function schlep() {
	if (!class_exists( 'ZipArchive')) {
		die( ME.' needs the ZipArchive class in your PHP install to function. Boom!');
	}
	$ts = date('Ymd-His');
	$db_fname = "schlep-$ts.sql";
	$opts = '--single-transaction -v "--result-file='.$db_fname.'"' . _mysql_args();
	$cmd = "mysqldump $opts";
	// echo "Running: $cmd<br/>";
	exec( $cmd . ' 2>&1', $a, $res );
	if ($res===0) {
		echo '<h4>Created ' . basename( $db_fname ). '</h4>';
	} else {
		$s = join("\n", $a);
		echo <<<END
	<h3>SQL Export failed with code: $res</h3>
	<pre>$s</pre>
END;
		return;
	}

	$zip_fname = str_replace('.sql', '.zip', $db_fname );
	// Initialize archive object
	$zip = new ZipArchive;
	if (!$zip->open( $zip_fname, ZipArchive::CREATE)) {
		die('Cannot create archive');
	}

	zip_relative( $zip, 'site');
	zip_relative($zip, 'wire');
	$files = _dir(CUR_DIR);
	$me = basename(__FILE__);
	foreach( $files as $file ) {
		if ($file!==$me) {
			$zip->addFile( $file, str_replace(DIRECTORY_SEPARATOR, "\t", $file) );
		}
	}

	// Zip archive will be created only after closing object
	if (!$zip->close()) {
		echo "<h3>ERROR CLOSING zip: {$zip->getStatusString()}</h3>";
	} else {
		echo '<h4>Created ' . basename( $zip_fname ). '</h4>';
	}
	unlink($db_fname);
	echo '<hr>';
}

function unschlep() {
	extract( $_REQUEST );
	if (!file_exists($fname)) {
		die('File not found: ' . $fname);
	}
	$zip = new ZipArchive;
	if (!$zip->open( $fname)) {
		die('Cannot open zip: ' . $fname);
	}
	$res = $zip->extractTo('.');
	$zip->close();
	if ($res) {
		echo '<h4>Extracted ' . basename( $fname ). '</h4>';
	} else {
		echo '<h3>ZIP Extract of ' . basename( $fname ). ' FAILED!</h3>';
		return;
	}

	$db_fname = str_replace('.zip', '.sql', $fname);

	// die($db_name);
	$opts = _mysql_args();
	$cmd = "mysql $opts < $db_fname";

	exec( $cmd . ' 2>&1', $a, $res );
	if ($res === 0) {
		echo '<h4>Imported ' . basename( $db_fname ). '</h4>';
	} else {
		$s = join("\n", $a);
		echo <<<END
	<h3>SQL Import failed with code: $res</h3>
	<pre>$s</pre>
END;
	}
}


extract($_REQUEST);

if (isset( $download)) {
	if (!file_exists($fname)) {
		die('File not found: ' . $fname);
	}	
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename='.basename($fname));
    header('Content-Transfer-Encoding: binary');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($fname));
    ob_clean();
    flush();
    readfile($fname);
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1">

	<title><?php echo ME; ?> - moving your processwire site</title>

	<style>
		h3 { color: red; }
	</style>

</head>

<body>

	<h1><?php echo ME; ?></h1>
	<hr>
	<i>
	<h2>USAGE</h2>
	<p>
		Open <b>source.com/schlepwire.php</b> in your browser<br>
		<b>schlep</b> to create a package, which includes all source and the DB export.<br>
		<b>Download</b> the package. <br>
		FTP the package to the destination server's root, along with $this_prog.<br>
		Open <b>destination.com/schlepwire.php</b> in your browser and <b>unschlep</b> to install.<br>
		It extracts all source code and imports the DB.<br>
		<b>test</b> that your site works with a simple link.
	</p>
	</i>
	<hr>


<?php
	if (isset( $schlep)) {
		schlep();
	} elseif (isset( $unschlep)) {
		unschlep();
	} 
	show_status();

?>
</body>
</html>

