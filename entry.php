<?php

require 'vendor/autoload.php';

use Monolog\Level;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$logger = new Logger('logger');
$logger->pushHandler(new StreamHandler('log/critical.log', Level::Critical));

require 'config.php';
require 'db.php';
require 'short-functions.php';


$dispatcher = FastRoute\simpleDispatcher(function(FastRoute\RouteCollector $r) {
    
  //! Pages
  $r->addRoute('GET', '/[short]', 'pf_home');
  $r->addRoute('GET', '/image', 'pf_image');
  $r->addRoute('GET', '/{sh}', 'pf_load_id');

  //! API
  $r->addRoute('POST', '/register-short', 'f_register_short'); // ---> short-functions.php
  $r->addRoute('POST', '/get-short-gate', 'f_get_short_gate'); // ---> short-functions.php
});

// Fetch method and URI from somewhere
$httpMethod = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH );
$routeInfo = $dispatcher->dispatch($httpMethod, $uri);

switch ($routeInfo[0]) {
    case FastRoute\Dispatcher::NOT_FOUND:
        echo("ERROR: 404");
        break;
    case FastRoute\Dispatcher::METHOD_NOT_ALLOWED:
        $allowedMethods = $routeInfo[1];
        header("HTTP/1.0 405 Method Not Allowed"); 
        echo("ERROR: 405");
        break;
    case FastRoute\Dispatcher::FOUND:
        $handler = $routeInfo[1];
        $vars = $routeInfo[2];
        $handler($vars);
        break;
}
//? ======================================================
//! THE FUNCTIONS THAT INVITE PAGES START HERE
//? ======================================================

function pf_home(){
    require 'views/short.html';
}


function pf_load_id($vars){
    // Check if the shortened URL ID is set in the request, redirect to home if not
    if(!isset($vars['sh'])){
        header("Location: /");
        return;
    }

    // Retrieve the ID from request variables
    $sh = $vars['sh'];
    // Query the database for the shortened URL record
    $db_res = db_short_request($sh);

    // Respond with error if the URL does not exist in the database
    if(!$db_res){
        json_response_shortener("URL not exist", false);
    }

    // Check if the URL has expired and respond accordingly
    if($db_res['expiration_date'] !== null){
        if(new DateTime($db_res['expiration_date']) < new DateTime(date('Y-m-d'))){
            json_response_shortener("URL expired", false);
        }
    }

    // Determine if reCAPTCHA or a password is required for the URL
    $recaptcha_required = ($db_res['recaptcha_required'] == true) ? true : false;
    $password = ($db_res['password'] !== null) ? true : false;

    // If either reCAPTCHA or password is required, display the gate view
    if($recaptcha_required || $password){
        $short_url = $db_res['short'];
        $smarty = new Smarty();

        $smarty->assign('password', $password);
        $smarty->assign('recaptcha_required', $recaptcha_required);
        $smarty->assign('short_url', $short_url);
        $smarty->assign('public_key', RECAPTCHA_V2_PUBLIC_KEY);
        
        $smarty->display('views/gate.tpl');
    } else {
        log_visit($sh);
        header("Location: " . $db_res['url']);
    }
}
