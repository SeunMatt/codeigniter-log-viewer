CHANGELOG
===========

V2.0.2
------
- Fix bug with deleting single file. Changed to using `service('request')->getUri()`


V2.0.0
------
- Added support for CodeIgniter 4 and deprecate support for CodeIgniter 3

V1.1.2
-------
- Fix issue [#13](https://github.com/SeunMatt/codeigniter-log-viewer/issues/13) and improve regex patterns

V1.1.1
-------
- Fix security bug with file download [#8](https://github.com/SeunMatt/codeigniter-log-viewer/issues/8)
- Updated required PHP version to >=7.1

V1.1.0
-------
- Added API capability, such that log and log files can be obtained in a JSON response
- Added log folder path and log file pattern configuration such that they can be configured via CodeIgniter's config.php file
