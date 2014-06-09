schlepwire
==========

A simple PHP file to move a whole ProcessWire site and DB.

## USAGE

- Open **source.com/schlepwire.php** in your browser.
- Click **schlep** to create a package, which includes all source and the DB export.
- **download** the package.
- FTP the package to the destination server's root, along with **schlepwire.php**
- Open **destination.com/schlepwire.php** in your browser and **unschlep** to install.
- It extract all source code and will import the DB.
- **test** that your site.

There is minimal safety. It relies on having your config.php set up to switch between site.

Enjoy!
