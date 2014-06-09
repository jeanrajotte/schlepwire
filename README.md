schlepwire
==========

A simple PHP file to move a whole ProcessWire site and DB.

## USAGE

- Open **source.com/schlepwire.php** in your browser.
- Click **schlep** to create a package, which includes all source and the DB export.
- **download** the package.
- FTP the package to the destination server's root, along with **schlepwire.php**
- Open **destination.com/schlepwire.php** in your browser and **unschlep** to install.
- It extracts all source code and imports the DB.
- **test** that your site works with a simple link.

There is crude, with minimal safety fussing. It relies on having your config.php set up to switch between site, something like:

<code>
if (preg_match( '/production.com/', $_SERVER['HTTP_HOST'])) {
	// production
	$config->dbHost = 'production';
	$config->dbName = 'production';
	$config->dbUser = 'production';
	$config->dbPass = 'production';
	$config->dbPort = 'production';
	$config->httpHosts = array( 'production.com', 'www.production.com');

} elseif (preg_match( '/jejotte.com/', $_SERVER['HTTP_HOST'])) {
	// staging
	$config->dbHost = 'staging';
	$config->dbName = 'staging';
	$config->dbUser = 'staging';
	$config->dbPass = 'staging';
	$config->dbPort = 'staging';
	$config->httpHosts = array( 'staging.com', 'www.staging.com');

} else {	
	// development
	$config->dbHost = 'development';
	$config->dbName = 'development';
	$config->dbUser = 'development';
	$config->dbPass = 'development';
	$config->dbPort = 'development';
	$config->httpHosts = array( 'development.com', 'www.development.com');
} 
</code>

Enjoy!
