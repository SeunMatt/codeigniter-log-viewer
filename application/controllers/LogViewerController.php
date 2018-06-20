<?php

if ( !defined( 'BASEPATH' ) ) {
    exit( 'No direct script access allowed' );
}

// include composer autoload file
include_once './vendor/autoload.php';

/**
 * Controller Class LogViewerController (Password Protected)
 * View CodeIgniter log file in your browser by accessing http://wwww.ci-project.com/LogViewerController
 *
 * This is CodeIgniter Controller to display CodeIgniter log data
 * You have to enter Web Auth User Name & Password to see log file
 *
 * @author Seun Matt
 * @link   https://github.com/SeunMatt/codeigniter-log-viewer
 * @author Neeraj Singh <Added Password Protection in Controller>
 */
class LogViewerController extends CI_Controller
{
    /**
     * Log Viewer Object
     * @var mixed
     */
    private $logViewer;

    /**
     * Web Auth User Password (Support@Development)
     * @var string
     */
    private $auth_password = "9df9fe18094e73a140acfbf447c208c8";

    /**
     * Web Auth User Name
     * @var string
     */
    private $auth_user = "Support";

    /**
     * Class Constructor
     */
    public function __construct()
    {
        $this->logViewer = new \CILogViewer\CILogViewer();
    }

    /**
     * Auto run method for Controller
     * @return null
     */
    public function index()
    {

        if ( $this->check_auth() ) {
            // print log file data
            echo $this->logViewer->showLogs();
        } else {
            // ask for user validation
            $this->validate_user();
        }
    }

    /**
     * Make RISKY Secure
     * @return [type] [description]
     */
    private function check_auth()
    {
        // Adding Web Auth because viewing log in browser is RISKY!
        $authenticated = $this->session->userdata( 'authenticated' );

        if ( empty( $authenticated ) || $authenticated !== 'Permission Granted' ) {
            return false;
        }

        return true;
    }

    /**
     * HTTP Web Auth Popup
     * @return [type] [description]
     */
    private function validate_user()
    {
        // Status flag:
        $loginsuccessful = false;
        // Check username and password:

        if ( isset( $_SERVER['PHP_AUTH_USER'] ) && isset( $_SERVER['PHP_AUTH_PW'] ) ) {
            $username = trim( $_SERVER['PHP_AUTH_USER'] );
            $password = trim( $_SERVER['PHP_AUTH_PW'] );

            if ( $username === $this->auth_user && md5( $password ) === $this->auth_password ) {
                $loginsuccessful = true;
            }
        }

        // Login passed successful?

        if ( !$loginsuccessful ) {
            // The text inside the realm section will be visible for the
            // user in the login box
            header( 'WWW-Authenticate: Basic realm="CodeIgniter Secret Page"' );
            header( 'HTTP/1.0 401 Unauthorized' );
            //show_404();
        } else {
            $this->session->set_userdata( array(
                'authenticated' => 'Permission Granted'
            ) );
            // reload current controller
            redirect( 'LogViewerController' );
        }
    }
}

/* End of file LogViewerController.php */
/* Location: ./application/controllers/LogViewerController.php */
