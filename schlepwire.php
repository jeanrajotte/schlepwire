<?php

/**
*
* SchlepWire 0.x
*
* Containerize a processwire site, ALL code (including /wire/) and DB into a schelp-timestamp.zip file
* Unpack a schlep file and rehydrate a site at the destination
* 
* @version 0.4
*	Add 'working' message when action takes time. 
*	Reference USAGE to the README.md on github
*
* @version 0.3
*	Add UnSQL action
*
* @version 0.2
*	repair unzipping across O/Ses
*
* @version 0.1
*	initial checkin
*
* The MIT License (MIT)
* 
* Copyright (c) 2014 Jean Rajotte
* 
* Permission is hereby granted, free of charge, to any person obtaining a copy
* of this software and associated documentation files (the "Software"), to deal
* in the Software without restriction, including without limitation the rights
* to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
* copies of the Software, and to permit persons to whom the Software is
* furnished to do so, subject to the following conditions:
* 
* The above copyright notice and this permission notice shall be included in all
* copies or substantial portions of the Software.
* 
* THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
* IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
* FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
* AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
* LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
* OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
* SOFTWARE.
*
*/

define('CUR_DIR', dirname(realpath(__FILE__)));
define('CONFIG_FILE', 'site/config.php');

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
		if (!file_exists( CONFIG_FILE )) {
			echo "<h3>This does not appear to be a ProcessWire site, so, nothing to schlep.<br>And there's nothing to unschlep.</h3>";
			return;
		}

		echo <<<END
<h2>Create a new package</h2>
<form method='POST'>
	<button id='schlep' name='schlep' value='schlep' >Schlep</button>
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
		$sql_fname = str_replace('.zip', '.sql', $fname);
		$only_sql = file_exists($sql_fname)
			? " or <button id='unsql' name='unsql' value='unsql'>Import SQL Only</button>"
			: '';
		echo <<<END
<form id='go' method='POST'>
	<input type='hidden' name='fname' value='$fname' />
	<p>
		<b>$fname: </b>
		<button id='unschlep' name='unschlep' value='unschlep'>Unschlep</button>
		or
		<button name='download' value='download'>Download</button>
		$only_sql
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
	$fname = realpath($fname);	// abso path
	for($i = 0; $i < $zip->numFiles; $i++) {
		$filename = $zip->getNameIndex($i);
	    $dest_file = str_replace( '/', DIRECTORY_SEPARATOR, $filename);
	    $dest_dir = dirname($dest_file);
	    if (!file_exists($dest_dir)) {
	        mkdir( $dest_dir, 0777, true);
	    }
	    // the source needs to match the local OS, it seems
	    if (!is_dir($dest_file)) {
	    	if (!copy("zip://".$fname."#".$filename, $dest_file)) {
				echo '<h3>ZIP Extract of ' . basename( $fname ). " FAILED at $filename!</h3>";
				return;
	    	}
	    }
	}           
	$zip->close();
	echo '<h4>Extracted ' . basename( $fname ). '</h4>';

	unsql();
}

function unsql() {
	extract( $_REQUEST );
	if (!file_exists($fname)) {
		die('File not found: ' . $fname);
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
		#bam {
			position: fixed;
			top: 0px;
			bottom: 0px;
			left: 0px;
			right: 0px;
			background-color: silver;
			opacity: 0.5;
			z-index: 10;
			display: none;
		}
		#bam h1 {
			position : fixed;
			top: 0px;
			right: 0px;
			background-color: #fff;
			padding: 10px;
		}

	</style>

</head>

<body>

	<div id='bam'>
		<h1>Working!</h1>
	</div>

	<h1><?php echo ME; ?></h1>
	<hr>
	<h4>
		Visit the
		<a href='http://github.com/jeanrajotte/schlepwire#schlepwire' target='_blank'>project's github page &#8599;</a>
		 for documentation.
	</h4>
	
	<hr>


<?php
	if (isset( $schlep)) {
		schlep();
	} elseif (isset( $unschlep)) {
		unschlep();
	} elseif (isset( $unsql)) {
		unsql();
	} 
	show_status();

?>

<script>
	function addListener( el, eventName, handler) {
		if (el) {
			if (el.addEventListener) {
				el.addEventListener(eventName, handler, false);
			}
			else if (el.attachEvent) {
				el.attachEvent('on' + eventName, handler);
			}
			else {
				el['on' + eventName] = handler;
			}
		}
	}
	function working() {
		document.getElementById('bam').style.display = 'block';	
	}
	addListener( document.getElementById('schlep'), 'click', working );
	addListener( document.getElementById('unschlep'), 'click', working );
	addListener( document.getElementById('unssql'), 'click', working );
</script>
</body>
</html>

