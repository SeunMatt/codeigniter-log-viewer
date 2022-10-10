CodeIgniter Log Viewer
======================

[![Latest Stable Version](https://poser.pugx.org/seunmatt/codeigniter-log-viewer/v/stable)](https://packagist.org/packages/seunmatt/codeigniter-log-viewer) [![Total Downloads](https://poser.pugx.org/seunmatt/codeigniter-log-viewer/downloads)](https://packagist.org/packages/seunmatt/codeigniter-log-viewer) [![License](https://poser.pugx.org/seunmatt/codeigniter-log-viewer/license)](https://packagist.org/packages/seunmatt/codeigniter-log-viewer) 

This is a simple Log Viewer for viewing CodeIgniter logs in the browser or via API calls (that returns a JSON response)

This project is inspired by the [laravel-log-viewer project](https://github.com/rap2hpoutre/laravel-log-viewer).

A typical log view looks like this:

![sample.png](sample.png)

Usage
=====

**For CodeIgniter 3, see this [reference guide](https://github.com/SeunMatt/codeigniter-log-viewer/wiki/CodeIgniter-3-Guide)**

Requirements
-----------
- PHP >= 7.4
- CodeIgniter 4

Composer Installation
---------------------
```
composer require seunmatt/codeigniter-log-viewer
```

Controller Integration for Browser Display
------------------------------------------


All that is required is to execute the `showLogs()` method in a Controller that is mapped to a route:

A typical Controller *(LogViewerController.php)* will have the following content:

```php
namespace App\Controllers;
use CILogViewer\CILogViewer;

class LogViewerController extends BaseController
{
    public function index() {
        $logViewer = new CILogViewer();
        return $logViewer->showLogs();
    }
}
```

Then the route `app/Config/Routes.php` can be configured like:

```php
$routes->get('logs', "LogViewerController::index");
```

And that's all! If you visit `/logs` on your browser 
you should see all the logs that are in `writable/logs` folder and their content


Configuration
==============

The package allows you to configure some of its parameters by creating a `CILogViewer` class in CodeIgniter's `Config` folder and then adding the following variables:

- The folder path for log files can be configured with the `$logFolderPath` config var.

- The file pattern for matching all the log files in the log folder can be configured by adding `$logFilePattern` config var.
- The name of the view that renders the logs page can be changed using the  `$viewName` config var. Please note that this can be a route relative to your `View` path or a namespace route.

Example configuration file `app/Config/CILogViewer.php`:

```php
<?php
namespace Config;
use CodeIgniter\Config\BaseConfig;

class CILogViewer extends BaseConfig {
    public $logFilePattern = 'log-*.log';
    public $viewName = 'logs'; //where logs exists in app/Views/logs.php
}
```


Viewing Log Files via API Calls
===============================

If you're developing an API Service, powered by CodeIgniter, this library can still be used to view your log files.

Controller Setup
----------------
**The setup is the same as that mentioned above:** 
 - Create a Controller e.g. `ApiLogViewerController.php`, 
 - Create a function e.g. `index()`
 - In the function, call `echo $this->logViewer->showLogs();`
 - Finally, map your controller function to a route.
 
 API Commands
 ------------
 
 The API is implemented via a set of query params that can be appended to the `/logs` path.
 
 Query:
 
 - `/logs?api=list` will list all the log files available in the configured folder

Response:

 ```json
{
    "status": true,
    "log_files": [
        {
            "file_b64": "bG9nLTIwMTgtMDEtMTkucGhw",
            "file_name": "log-2018-01-19.php"
        },
        {
            "file_b64": "bG9nLTIwMTgtMDEtMTcucGhw",
            "file_name": "log-2018-01-17.php"
        }
    ]
}
```

**file_b64 is the base64 encoded name of the file that will be used in further operations and API calls**
 
 Query:
 
 - `/logs?api=view&f=bG9nLTIwMTgtMDEtMTcucGhw` will return the logs contained in the log file specified by the `f` parameter. 
 
 The value of the `f` (*f stands for file*) is the base64 encoded format of the log file name. It is obtained from the `/logs?api=list` API call. 
 A list of all available log files is also returned.
 
 Response:
 
 ```json
 {
     "log_files": [
         {
             "file_b64": "bG9nLTIwMTgtMDEtMTkucGhw",
             "file_name": "log-2018-01-19.php"
         },
         {
             "file_b64": "bG9nLTIwMTgtMDEtMTcucGhw",
             "file_name": "log-2018-01-17.php"
         }
     ],
     "status": true,
     "logs": [
         "ERROR - 2018-01-23 07:12:31 --> 404 Page Not Found: admin/Logs/index",
         "ERROR - 2018-01-23 07:12:37 --> 404 Page Not Found: admin//index",
         "ERROR - 2018-01-23 15:23:02 --> 404 Page Not Found: Faviconico/index"
     ]
 }
 ```
 
 The API Query can also take one last parameter, `sline` that will determine how the logs are returned
 When it's `true` the logs are returned in a single line:
 
 Query:
 
 `/logs?api=view&f=bG9nLTIwMTgtMDEtMTkucGhw&sline=true`
 
 Response:
 
 ```json
{
    "log_files": [
        {
            "file_b64": "bG9nLTIwMTgtMDEtMTkucGhw",
            "file_name": "log-2018-01-19.php"
        },
        {
            "file_b64": "bG9nLTIwMTgtMDEtMTcucGhw",
            "file_name": "log-2018-01-17.php"
        }
    ],
    "status": true,
    "logs": "ERROR - 2018-01-23 07:12:31 --> 404 Page Not Found: admin/Logs/index\r\nERROR - 2018-01-23 07:12:37 --> 404 Page Not Found: admin//index\r\nERROR - 2018-01-23 15:23:02 --> 404 Page Not Found: Faviconico/index\r\n"
}
```
 
 
 When it's `false` (**Default**), the logs are returned in as an array, where each element is a line in the log file:

Query:

 `/logs?api=view&f=bG9nLTIwMTgtMDEtMTkucGhw&sline=false` OR `logs?api=view&f=bG9nLTIwMTgtMDEtMTkucGhw` 

Response:
 
 ```json
{
    
    "logs": [
        "ERROR - 2018-01-23 07:12:31 --> 404 Page Not Found: admin/Logs/index",
        "ERROR - 2018-01-23 07:12:37 --> 404 Page Not Found: admin//index",
        "ERROR - 2018-01-23 15:23:02 --> 404 Page Not Found: Faviconico/index"
    ]
}
```
 
Query:

`/logs?api=delete&f=bG9nLTIwMTgtMDEtMTkucGhw` will delete a single log file. The **f** parameter is the base64 encoded name of the file
and can be obtained from the view api above.

Query: 

`/logs?api=delete&f=all` will delete all log files in the configured folder path. Take note of the value for **f** which is the literal '**all**'.
 
 **IF A FILE IS TOO LARGE (> 50MB), YOU CAN DOWNLOAD IT WITH THIS API QUERY `/logs?dl=bG9nLTIwMTgtMDEtMTcucGhw`**
 
 
SECURITY NOTE
=============
**It is Highly Recommended that you protect/secure the route for your logs. It should not be an open resource!**

Change Log
==========
[Change Log is available here](./CHANGELOG.md)


Author
======
- [Seun Matt](https://smattme.com)

Contributors
============
- [Miguel Martinez](https://github.com/savioret)


LICENSE
=======
[MIT](LICENSE)
