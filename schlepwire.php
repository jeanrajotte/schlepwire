<?php

/**
*
* SchlepWire 0.x
*
* Containerize a processwire site, ALL code (including /wire/) and DB into a schelp-timestamp.zip file
* Unpack a schlep file and rehydrate a site at the destination
*
* @version 0.5
*	Use OS tar instead of ZipArchive
*	Allow for run running process
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
	echo "<div id='status-bottom'>";
	$fnames = glob('./schlep-*.tar.gz');
	if (!count($fnames)) {
		if (!file_exists( CONFIG_FILE )) {
			echo "<h3>This does not appear to be a ProcessWire site, so, nothing to schlep.<br>And there's nothing to unschlep.</h3>";
			return;
		}

		echo <<<END
<h2>Create a new package</h2>
<form method='POST'>
	<button id='schlep' name='schlep' value='schlep' >Schlep</button>
	or
	<button id='schlep_sql_only' name='schlep_sql_only' value='schlep_sql_only' >Schlep SQL only</button>
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
		$sql_fname = str_replace('.tar.gz', '.sql', $fname);
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
<a href='/' target='_blank'>Test the site &#8599;</a> and <b>remember to remove schlep* files from the root</b>.
<hr/>
END;
	}
	echo "</div>";

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

////////////////////////////////
// create a package
////////////////////////////////
function schlep() {
	$ts = date('Ymd-His');

	?>

<pre id='console'></pre>
<pre id='message'></pre>

<script>

(function($) {

	var logIndex = 0;

	function log( eid, msg ) {
		var eid1 = 'x_' + logIndex++;
		$(eid)
		.append( msg )
		.append( $("<hr id='" + eid1 + "' />"));
		$('#' + eid1)[0].scrollIntoView();
	}

	function exportSql( ts ) {
		log( '#console', 'Exporting SQL' );
		$.get( '', { schlep_export_sql: ts }, function( data ) {
			log( '#console', data );
			testSql( ts );
		});
	}
	function testSql( ts ) {
		log( '#console', 'Testing SQL presence' );
		$.get( '', { schlep_test_sql: ts }, function( data ) {
			log( '#console', data );
			if (data==='OK') {
				log( '#message', "<h4>Created SQL for " + ts + "</h4>");
				tar( ts );
			}
		});
	}
	function tar( ts ) {
		log( '#console', 'Packaging all files' );
		$.get( '', { schlep_tar: ts }, function( data ) {
			log( '#console', data );
			testTar( ts );
		});
	}
	function testTar( ts ) {
		log( '#console', 'Testing TAR completion' );
		$.get( '', { schlep_tar_test: ts }, function( data ) {
			log( '#console', data );
			if (data==='OK') {
				log( '#message', "<h4>Created TAR.GZ for " + ts + "</h4>");
				conclude( ts );
			}
		});
	}
	function conclude( ts ) {
		log( '#console', 'Cleaning up' );
		$.get( '', { schlep_done: ts }, function( data ) {
			log( '#console', data );
			if (data.indexOf('OK') > -1) {
				log('#message', "<h4>Done clean up</h4>");
				log('#message', "<a href=''>Reload Schlepwire to continue</a>");
			}
		});		
	}

	$('document').ready(function() {

		exportSql( '<?php echo $ts; ?>');
	});

})(jQuery);

</script>

	<?php
}

function schlep_export_sql( $ts ) {
	$fname = "schlep-$ts.sql";
	$opts = '--single-transaction -v "--result-file='.$fname.'"';
	$cmd = "mysqldump $opts";
	echo "$cmd ...\n";
	passthru( $cmd . " " . _mysql_args() . ' 2>&1');
	return $fname;
}
function schlep_test_sql( $ts ) {
	$fname = "schlep-$ts.sql";
	echo is_file($fname) ? "OK" : "ERROR: not found: $fname";
}

function schlep_tar( $ts) {
	$fname = "schlep-$ts.tar.gz";
	$db_fname = "schlep-$ts.sql";
	file_put_contents ( 'schleped.txt' , "schleped on $ts" );
	$file_list = ".htaccess index.php *.md *.txt {$db_fname} wire/ site/ schleped.txt";
	$cmd = "tar -cavWf {$fname} {$file_list}";
	echo "$cmd\n";
	passthru( $cmd );
}
function schlep_tar_test( $ts ) {
	$fname = "schlep-$ts.tar.gz";
	if (!is_file($fname)) {
		echo "ERROR not found: $fname";
		return;
	}
	if (is_file( './schleped.txt' )) {
		unlink( './schleped.txt');
	}
	$cmd = "tar -xf {$fname} schleped.txt";
	exec( $cmd );
	echo is_file( './schleped.txt' ) ? 'OK' : 'ERROR: taring did not finish w/ schleped.txt';
}

function schlep_done( $ts ) {
	$fname = "schlep-$ts.sql";
	unlink($fname);
	unlink('schleped.txt');
	echo 'OK';
}

////////////////////////////////
// apply a package
////////////////////////////////
function unschlep() {
	extract( $_REQUEST );
	if (!file_exists($fname)) {
		die('File not found: ' . $fname);
	}
	$db_fname = str_replace('.tar.gz', '.sql', $fname);

	?>

<pre id='console'></pre>
<pre id='message'></pre>

<script>

(function($) {

	var logIndex = 0,
		tarFname = '<?php echo $fname; ?>',
		sqlFname = '<?php echo $db_fname; ?>';

	function log( eid, msg ) {
		var eid1 = 'x_' + logIndex++;
		$(eid)
		.append( msg )
		.append( $("<hr id='" + eid1 + "' />"));
		$('#' + eid1)[0].scrollIntoView();
	}

	function untar() {
		log( '#console', 'Unpacking all files' );
		$.get( '', { unschlep_untar: tarFname }, function( data ) {
			log( '#console', data );
			testUntar();
		});
	}
	function testUntar() {
		log( '#console', 'Test Unpacking' );
		$.get( '', { unschlep_untar_test: tarFname }, function( data ) {
			log( '#console', data );
			if (data==='OK') {
				log( '#message', "<h4>Unpacked " + tarFname + "</h4>");
				importSql();
			}
		});
	}
	function importSql( ) {
		log( '#console', 'Importing SQL' );
		$.get( '', { unschlep_import_sql: sqlFname }, function( data ) {
			log( '#console', data );
			log('#message', "<h4>SQL Imported from " + sqlFname + "</h4>");
			conclude();
		});
	}
	function conclude( ) {
		log( '#console', 'Cleaning up' );
		$.get( '', { unschlep_done: sqlFname }, function( data ) {
			log( '#console', data );
			log('#message', "<h4>Done clean up</h4>");
			log('#message', "<a href=''>Reload Schlepwire to continue</a>");
		});
	}

	$('document').ready(function() {
		untar( '<?php echo $fname; ?>');
	});

})(jQuery);

</script>

	<?php
}

function unschlep_untar( $fname ) {
	if (!file_exists( $fname )) {
		die('File not found: ' . $fname);
	}
	if (file_exists('./schleped.txt')) { unlink('./schleped.txt'); }
	$cmd = "tar -xv --recursive-unlink -f {$fname}";
	echo "$cmd\n";
	passthru( $cmd );
}

function unschlep_untar_test( $fname ) {
	echo file_exists( './schleped.txt' ) ? 'OK' : 'ERROR: no schleped.txt found';
}

function unschlep_import_sql( $fname ) {
	if (!file_exists( $fname )) {
		die('File not found: ' . $fname);
	}
	$opts = _mysql_args();
	$cmd = "mysql $opts < $fname";
	echo "mysql ... < $fname";
	passthru( $cmd . ' 2>&1', $res );
}
function unschlep_done( $fname ) {
	unlink($fname);
	unlink('schleped.txt');
	echo 'OK';
}

//////////////////////////////////////////
// dispatcher
//////////////////////////////////////////
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
    die();
} elseif (isset( $schlep_export_sql )) {
	header('Content-Type: html/text');
	schlep_export_sql( $schlep_export_sql );
	die();
} elseif (isset( $schlep_test_sql )) {
	header('Content-Type: html/text');
	schlep_test_sql( $schlep_test_sql );
	die();
} elseif (isset( $schlep_tar )) {
	header('Content-Type: html/text');
	schlep_tar( $schlep_tar );
	die();
} elseif (isset( $schlep_tar_test )) {
	header('Content-Type: html/text');
	schlep_tar_test( $schlep_tar_test );
	die();
} elseif (isset( $schlep_done )) {
	header('Content-Type: html/text');
	schlep_done( $schlep_done );
	die();
} elseif (isset( $unschlep_untar )) {
	header('Content-Type: html/text');
	unschlep_untar( $unschlep_untar );
	die();
} elseif (isset( $unschlep_untar_test )) {
	header('Content-Type: html/text');
	unschlep_untar_test( $unschlep_untar_test );
	die();
} elseif (isset( $unschlep_import_sql )) {
	header('Content-Type: html/text');
	unschlep_import_sql( $unschlep_import_sql );
	die();
} elseif (isset( $unschlep_done )) {
	header('Content-Type: html/text');
	unschlep_done( $unschlep_done );
	die();
}


// header( 'Content-Encoding: identity' );

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
		#console {
			height: 300px;
			width: 80%;
			padding: 10px;
			border: solid silver 1px;
			margin: 10px;
			overflow: scroll;
		}
		#message {
			margin: 10px;
		}

	</style>
	<script src="//ajax.googleapis.com/ajax/libs/jquery/2.1.1/jquery.min.js"></script>

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

	} elseif (isset( $schlep_sql_only)) {
		echo '<pre>';
		$fname = schlep_export_sql( date('Ymd-His') );
		echo '</pre>';
		echo "<h4>Created {$fname}</h4>";
		show_status();

	} elseif (isset( $unschlep)) {
		unschlep();

	} elseif (isset( $unsql)) {
		echo '<pre>';
		unschlep_import_sql( str_replace('.tar.gz', '.sql', $fname) );
		echo '</pre>';
		show_status();

	} else {
		show_status();

	}
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

