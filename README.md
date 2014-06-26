schlepwire
==========

A simple PHP program to move a whole ProcessWire site and DB.

1. [Getting Started](#getting-started)
	- [Creating a Copy of a Site](#creating-a-copy-of-a-site)
	- [Overwriting an Existing Site](#overwriting-an-existing-site)
	- [Using one Site as a Seed for Another (Creating from Scratch)](#from-scratch)
2. [config.php Configuration](#config-php-configuration) 


## <a name='getting-started'></a>Getting Started 

All you need is the schlepwire.php file in the root of the site you want to copy.

NOTE: This tool is crude and has minimal safety. It relies on having your [config.php](#config-php-configuration) set up to switch between site, and it fails "elegantly enough".


### <a name="creating-a-copy-of-a-site"></a>Creating a Copy of a Site 

- Open **source.com/schlepwire.php** in your browser.
- Click **schlep** to create a package, which includes all source and the DB export.
- **download** the package to your local drive.

### <a name="overwriting-an-existing-site" ></a>Overwriting an Existing Site 

- FTP the package to the destination server's root, along with **schlepwire.php**
- Open **destination.com/schlepwire.php** in your browser and **unschlep** to install.
- It extracts all source code and imports the DB.
- **test** that your site works with a simple link.
- Remember to remove schlep* from your root.

### <a name='from-scratch'></a>Using one Site as a Seed for Another (Creating from Scratch) 

This is fun!  

- FTP the package to the destination server's root, along with **schlepwire.php**
- Open **destination.com/schlepwire.php** in your browser and **unschlep** to install.
- It extracts all source code and **tries** to import the DB.

It **fails** because it's a different domain and the [config.php](#config-php-configuration) is for the other domain, not this new one. And there's probably no DB in place. So...

- create a DB
- change the config accordingly
- Open **destination.com/schlepwire.php** in your browser and **Import SQL Only**.

Now you have a new site that's identical to the old site, from scratch.  You can start hacking at it as you please.

## <a name="config-php-configuration"></a>config.php Configuration 

The idea is to define the site-specific data based on the current domain, *and* to fail explicitly if *none of the abvove* applies.  The <code>die</code> message is what schlepwire.php will show when [installing from scratch](#from-scratch).


		if (preg_match( '/production.com/', $_SERVER['HTTP_HOST'])) {
			// production
			$config->dbHost = 'production';
			$config->dbName = 'production';
			$config->dbUser = 'production';
			$config->dbPass = 'production';
			$config->dbPort = 'production';
			$config->httpHosts = array( 'production.com', 'www.production.com');

		} elseif (preg_match( '/staging.com/', $_SERVER['HTTP_HOST'])) {
			// staging
			$config->dbHost = 'staging';
			$config->dbName = 'staging';
			$config->dbUser = 'staging';
			$config->dbPass = 'staging';
			$config->dbPort = 'staging';
			$config->httpHosts = array( 'staging.com', 'www.staging.com');

		} elseif (preg_match( '/development\.dev/', $_SERVER['HTTP_HOST'])) {		
			// development
			$config->dbHost = 'development';
			$config->dbName = 'development';
			$config->dbUser = 'development';
			$config->dbPass = 'development';
			$config->dbPort = 'development';
			$config->httpHosts = array( 'development.com', 'www.development.com');
		} else {
			die('No known config.');
		}

Enjoy!
