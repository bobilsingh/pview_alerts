<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\CLIRequest;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * BaseController — every controller in this app extends this.
 * Sets up session and loads the alert_helper so its functions
 * (check_isvalidated, badge helpers, send_email, etc.) are
 * available everywhere.
 */
class BaseController extends Controller
{
    protected $helpers = ['alert', 'flow', 'security', 'url', 'form'];

    protected $session;
    protected $db;

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);

        $this->session = \Config\Services::session();
        $this->session->start();
        $this->db = \Config\Database::connect();
    }
}
