<?php
/**
 * Author: Seun Matt (https://github.com/SeunMatt)
 * Date: 09-Jan-18
 * Time: 4:30 AM
 */
namespace CILogViewer;

class CILogViewer {

    private static $levelsIcon = [
        'CRITICAL' => 'glyphicon glyphicon-error-sign',
        'INFO'  => 'glyphicon glyphicon-info-sign',
        'ERROR' => 'glyphicon glyphicon-warning-sign',
        'DEBUG' => 'glyphicon glyphicon-exclamation-sign',
        'ALL'   => 'glyphicon glyphicon-minus',
    ];

    private static $levelClasses = [
        'CRITICAL' => 'danger',
        'INFO'  => 'info',
        'ERROR' => 'danger',
        'DEBUG' => 'warning',
        'ALL'   => 'muted',
    ];

    const LOG_LINE_HEADER_PATTERN = '/^([A-Z]+)\s*\-\s*([\-\d]+\s+[\:\d]+)\s*\-\->\s*(.+)$/';

    //this is the path (folder) on the system where the log files are stored
    private $logFolderPath = WRITEPATH . 'logs/';

    //this is the pattern to pick all log files in the $logFilePath
    private $logFilePattern = "log-*.log";

    //this is a combination of the LOG_FOLDER_PATH and LOG_FILE_PATTERN
    private $fullLogFilePath = "";

    /**
     * Name of the view to pass to the renderer
     * Note that it allows namespaced views if your view is outside
     * the View folder.
     *
     * @var string
     */
    private $viewName = "App\ThirdParty\CILogViewer\Views\logs";

    const MAX_LOG_SIZE = 52428800; //50MB
    const MAX_STRING_LENGTH = 300; //300 chars

    /**
     * These are the constants representing the
     * various API commands there are
     */
    private const API_QUERY_PARAM = "api";
    private const API_FILE_QUERY_PARAM = "f";
    private const API_LOG_STYLE_QUERY_PARAM = "sline";
    private const API_CMD_LIST = "list";
    private const API_CMD_VIEW = "view";
    private const API_CMD_DELETE = "delete";


    public function __construct() {
        $this->init();
    }

    /**
     * Bootstrap the library
     * sets the configuration variables
     * @throws \Exception
     */
    private function init() {
        $viewerConfig = config('CILogViewer');

        if($viewerConfig) {
            if(isset($viewerConfig->viewPath)) {
                $this->viewPath = $viewerConfig->viewPath;
            }
            if(isset($viewerConfig->logFilePattern)) {
                $this->logFilePattern = $viewerConfig->logFilePattern;
            }
        }
        //configure the log folder path and the file pattern for all the logs in the folder
        $loggerConfig = config('Logger');
        if(isset($loggerConfig->path)) {
            $this->logFolderPath = $loggerConfig->path;
        }
        
        //concatenate to form Full Log Path
        $this->fullLogFilePath = $this->logFolderPath . $this->logFilePattern;
    }

    /*
     * This function will return the processed HTML page
     * and return it's content that can then be echoed
     *
     * @param $fileName optional base64_encoded filename of the log file to process.
     * @returns the parse view file content as a string that can be echoed
     * */
    public function showLogs() {

        $request = \Config\Services::request();

        if(!is_null($request->getGet("del"))) {
            $this->deleteFiles(base64_decode($request->getGet("del")));
            $uri = \Config\Services::request()->uri->getPath();
            return redirect()->to('/'.$uri);
        }

        //process download of log file command
        //if the supplied file exists, then perform download
        //otherwise, just ignore which will resolve to page reloading
        $dlFile = $request->getGet("dl");
        if(!is_null($dlFile) && file_exists($this->logFolderPath . basename(base64_decode($dlFile))) ) {
            $file = $this->logFolderPath . basename(base64_decode($dlFile));
            $this->downloadFile($file);
        }

        if(!is_null($request->getGet(self::API_QUERY_PARAM))) {
            return $this->processAPIRequests($request->getGet(self::API_QUERY_PARAM));
        }

        //it will either get the value of f or return null
        $fileName = $request->getGet("f");

        //get the log files from the log directory
        $files = $this->getFiles();

        //let's determine what the current log file is
        if(!is_null($fileName)) {
            $currentFile = $this->logFolderPath . basename(base64_decode($fileName));
        }
        else if(is_null($fileName) && !empty($files)) {
            $currentFile = $this->logFolderPath . $files[0];
        } else {
            $currentFile = null;
        }

        //if the resolved current file is too big
        //just trigger a download of the file
        //otherwise process its content as log

        if(!is_null($currentFile) && file_exists($currentFile)) {

            $fileSize = filesize($currentFile);

            if(is_int($fileSize) && $fileSize > self::MAX_LOG_SIZE) {
                //trigger a download of the current file instead
                $logs = null;
            }
            else {
                $logs =  $this->processLogs($this->getLogs($currentFile));
            }
        }
        else {
            $logs = [];
        }

        $data['logs'] = $logs;
        $data['files'] =  !empty($files) ? $files : [];
        $data['currentFile'] = !is_null($currentFile) ? basename($currentFile) : "";
        return view($this->viewName, $data);
    }


    private function processAPIRequests($command) {
        $request = \Config\Services::request();
        if($command === self::API_CMD_LIST) {
            //respond with a list of all the files
            $response["status"] = true;
            $response["log_files"] = $this->getFilesBase64Encoded();
        }
        else if($command === self::API_CMD_VIEW) {
            //respond to view the logs of a particular file
            $file = $request->getGet(self::API_FILE_QUERY_PARAM);
            $response["log_files"] = $this->getFilesBase64Encoded();

            if(is_null($file) || empty($file)) {
                $response["status"] = false;
                $response["error"]["message"] = "Invalid File Name Supplied: [" . json_encode($file) . "]";
                $response["error"]["code"] = 400;
            }
            else {
                $singleLine = $request->getGet(self::API_LOG_STYLE_QUERY_PARAM);
                $singleLine = !is_null($singleLine) && ($singleLine === true || $singleLine === "true" || $singleLine === "1") ? true : false;
                $logs = $this->processLogsForAPI($file, $singleLine);
                $response["status"] = true;
                $response["logs"] = $logs;
            }
        }
        else if($command === self::API_CMD_DELETE) {

            $file = $request->getGet(self::API_FILE_QUERY_PARAM);

            if(is_null($file)) {
                $response["status"] = false;
                $response["error"]["message"] = "NULL value is not allowed for file param";
                $response["error"]["code"] = 400;
            }
            else {

                //decode file if necessary
                $fileExists = false;

                if($file !== "all") {
                    $file = basename(base64_decode($file));
                    $fileExists = file_exists($this->logFolderPath . $file);
                }
                else {
                    //check if the directory exists
                    $fileExists = file_exists($this->logFolderPath);
                }


                if($fileExists) {
                    $this->deleteFiles($file);
                    $response["status"] = true;
                    $response["message"] = "File [" . $file . "] deleted";
                }
                else {
                    $response["status"] = false;
                    $response["error"]["message"] = "File does not exist";
                    $response["error"]["code"] = 404;
                }


            }
        }
        else {
            $response["status"] = false;
            $response["error"]["message"] = "Unsupported Query Command [" . $command . "]";
            $response["error"]["code"] = 400;
        }

        //convert response to json and respond
        header("Content-Type: application/json");
        if(!$response["status"]) {
            //set a generic bad request code
            http_response_code(400);
        } else {
            http_response_code(200);
        }
        return json_encode($response);
    }


    /*
     * This function will process the logs. Extract the log level, icon class and other information
     * from each line of log and then arrange them in another array that is returned to the view for processing
     *
     * @params logs. The raw logs as read from the log file
     * @return array. An [[], [], [] ...] where each element is a processed log line
     * */
    private function processLogs($logs) {

        if(is_null($logs)) {
            return null;
        }

        $superLog = [];

        foreach ($logs as $log) {

            if($this->getLogHeaderLine($log, $level, $logDate, $logMessage)) {
                //this is actually the start of a new log and not just another line from previous log
                $data = [
                    "level" => $level,
                    "date" => $logDate,
                    "icon" => self::$levelsIcon[$level],
                    "class" => self::$levelClasses[$level],
                ];

                if(strlen($logMessage) > self::MAX_STRING_LENGTH) {
                    $data['content'] = substr($logMessage, 0, self::MAX_STRING_LENGTH);
                    $data["extra"] = substr($logMessage, (self::MAX_STRING_LENGTH + 1));
                } else {
                    $data["content"] = $logMessage;
                }

                array_push($superLog, $data);

            } else if(!empty($superLog)) {
                //this log line is a continuation of previous logline
                //so let's add them as extra
                $prevLog = $superLog[count($superLog) - 1];
                $extra = (array_key_exists("extra", $prevLog)) ? $prevLog["extra"] : "";
                $prevLog["extra"] = $extra . "<br>" . $log;
                $superLog[count($superLog) - 1] = $prevLog;
            }
        }

        return $superLog;
    }


    /**
     * This function will extract the logs in the supplied
     * fileName
     * @param      $fileNameInBase64
     * @param bool $singleLine
     * @return array|null
     * @internal param $logs
     */
    private function processLogsForAPI($fileNameInBase64, $singleLine = false) {

        $logs = null;

        //let's prepare the log file name sent from the client
        $currentFile = $this->prepareRawFileName($fileNameInBase64);

        //if the resolved current file is too big
        //just return null
        //otherwise process its content as log
        if(!is_null($currentFile)) {

            $fileSize = filesize($currentFile);

            if (is_int($fileSize) && $fileSize > self::MAX_LOG_SIZE) {
                //trigger a download of the current file instead
                $logs = null;
            } else {
                $logs =  $this->getLogsForAPI($currentFile, $singleLine);
            }
        }

        return $logs;
    }

    private function getLogHeaderLine($logLine, &$level, &$dateTime, &$message) {
        $matches = [];
        if(preg_match(self::LOG_LINE_HEADER_PATTERN, $logLine, $matches)) {
            $level = $matches[1];
            $dateTime = $matches[2];
            $message = $matches[3];
        }
        return $matches;
    }

    /*
     * returns an array of the file contents
     * each element in the array is a line
     * in the underlying log file
     * @returns array | each line of file contents is an entry in the returned array.
     * @params complete fileName
     * */
    private function getLogs($fileName) {
        $size = filesize($fileName);
        if(!$size || $size > self::MAX_LOG_SIZE){
            return null;
        }
        return file($fileName, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    }

    /**
     * This function will get the contents of the log
     * file as a string. It will first check for the
     * size of the file before attempting to get the contents.
     *
     * By default it will return all the log contents as an array where the
     * elements of the array is the individual lines of the files
     * otherwise, it will return all file content as a single string with each line ending
     * in line break character "\n"
     * @param      $fileName
     * @param bool $singleLine
     * @return bool|string
     */
    private function getLogsForAPI($fileName, $singleLine = false) {
        $size = filesize($fileName);
        if(!$size || $size > self::MAX_LOG_SIZE) {
            return "File Size too Large. Please donwload it locally";
        }

        return (!$singleLine) ? file($fileName, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : file_get_contents($fileName);
    }


    /*
     * This will get all the files in the logs folder
     * It will reverse the files fetched and
     * make sure the latest log file is in the first index
     *
     * @param boolean. If true returns the basename of the files otherwise full path
     * @returns array of file
     * */
    private function getFiles($basename = true)
    {

        $files = glob($this->fullLogFilePath);

        $files = array_reverse($files);
        $files = array_filter($files, 'is_file');
        if ($basename && is_array($files)) {
            foreach ($files as $k => $file) {
                $files[$k] = basename($file);
            }
        }
        return array_values($files);
    }


    /**
     * This function will return an array of available log
     * files
     * The array will containt the base64encoded name
     * as well as the real name of the fiile
     * @return array
     * @internal param bool $appendURL
     * @internal param bool $basename
     */
    private function getFilesBase64Encoded() {

        $files = glob($this->fullLogFilePath);

        $files = array_reverse($files);
        $files = array_filter($files, 'is_file');

        $finalFiles = [];

        //if we're to return the base name of the files
        //let's do that here
        foreach ($files as $file) {
            array_push($finalFiles, ["file_b64" => base64_encode(basename($file)), "file_name" => basename($file)]);
        }

        return $finalFiles;
    }

    /*
     * Delete one or more log file in the logs directory
     * @param filename. It can be all - to delete all log files - or specific for a file
     * */
    private function deleteFiles($fileName) {

        if($fileName == "all") {
            array_map("unlink", glob($this->fullLogFilePath));
        }
        else {
            unlink($this->logFolderPath . basename($fileName));
        }
    }

    /*
     * Download a particular file to local disk
     * This should only be called if the file exists
     * hence, the file exist check has ot be done by the caller
     * @param $fileName the complete file path
     * */
    private function downloadFile($file) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="'.basename($file).'"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($file));
        readfile($file);
        exit;
    }


    /**
     * This function will take in the raw file
     * name as sent from the browser/client
     * and append the LOG_FOLDER_PREFIX and decode it from base64
     * @param $fileNameInBase64
     * @return null|string
     * @internal param $fileName
     */
    private function prepareRawFileName($fileNameInBase64) {

        //let's determine what the current log file is
        if(!is_null($fileNameInBase64) && !empty($fileNameInBase64)) {
            $currentFile = $this->logFolderPath . basename(base64_decode($fileNameInBase64));
        }
        else {
            $currentFile = null;
        }

        return $currentFile;
    }



}



