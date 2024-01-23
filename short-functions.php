<?php

// Functions for URL shortener

// Function to register a new shortened URL
function f_register_short(){
    $post_data = array();

    try{
        // Decode the JSON input from the request
        $json = json_decode(file_get_contents('php://input'));

        // Check if a valid URL is provided
        if(isset($json->url) && valid_URL($json->url)){
            $post_data['url'] = $json->url;
        } else {
            json_response_shortener("Not a valid url", false);
        }

        // Check and set the expiration date for the URL
        if(isset($json->expiryDate)){
            $expiryDate = new DateTime($json->expiryDate);
            if($expiryDate < new DateTime(date("Y-m-d"))){
                json_response_shortener("The date must be in the future", false);
            }
            $post_data['expiryDate'] = $expiryDate->format('Y-m-d');
        } else {
            $post_data['expiryDate'] = null;
        }

        // Check and set the password for the URL, if provided
        if(isset($json->password)){
            if(strlen($json->password) >= MIN_PASSWORD_LENGTH){
                $post_data['password'] = hash('sha256', $json->password);
            } else {
                json_response_shortener("The Password must be min. ". MIN_PASSWORD_LENGTH ." characters", false);
            }
        } else {
            $post_data['password'] = null;
        }
        
        // Check and set the reCAPTCHA requirement for the URL
        if(isset($json->recaptchaRequired)){
            if($json->recaptchaRequired === true){
                $post_data['recaptchaRequired'] = true;
            } else if($json->recaptchaRequired === false){
                $post_data['recaptchaRequired'] = false;
            } else { // For the API
                json_response_shortener("Recaptcha Required field need to be boolean or not given in query", false);
            }
        } else {
            $post_data['recaptchaRequired'] = false;
        }
        
    } catch(Exception $e) {
        json_response_shortener("Invalid request", false);
    }

    // Generate the next short URL and insert the record into the database
    $post_data['short'] = get_next_short_url();
    $insert_response = db_insert_short($post_data);
    if($insert_response){
        json_response_shortener($insert_response, true);
    } else {
        json_response_shortener("Error", false);
    }
}




// Function to insert a new shortened URL record into the database
function db_insert_short($post_data){

    try {
        // Establishing a new PDO connection
        $pdo = db_connect();

        // Preparing an SQL query to insert the new short URL record
        $sql = "INSERT INTO shorts (url, short, password, expiration_date, recaptcha_required) VALUES (:url, :short, :password, :expiration_date, :recaptcha_required)";
        $stmt = $pdo->prepare($sql);

        // Binding the input data to the prepared SQL statement
        $stmt->bindParam(':url', $post_data['url']);
        $stmt->bindParam(':short', $post_data['short']);
        $stmt->bindParam(':password', $post_data['password']);
        $stmt->bindParam(':expiration_date', $post_data['expiryDate']);
        $stmt->bindParam(':recaptcha_required', $post_data['recaptchaRequired'], PDO::PARAM_BOOL);

        // Executing the SQL query
        $stmt->execute();

        // Return the short URL if the insertion was successful
        return $post_data['short'];
    } catch (\PDOException $e) {
        // Handling any exceptions that occur during the database operation
        return false;
    }
}

// Function to create a JSON response for the shortener service
function json_response_shortener($message, $success){
    // Setting the header for JSON content type
    header('Content-Type: application/json; charset=utf-8');

    // Creating a response object based on the success parameter
    if($success){
        $response_object = [
            "success" => true,
            "response" => $message
        ];
        echo(json_encode($response_object));
    } else {
        $response_object = [
            "success" => false,
            "reason" => $message
        ];
        echo(json_encode($response_object));
    }

    // Ending the script execution after sending the response
    exit;
}

// Function to validate a URL format
function valid_URL($url){
    // Check if the URL length exceeds a maximum limit
    if(strlen($url) > 900){
        return false;
    }

    // Regular expression to validate the URL format
    return preg_match('%^(?:(?:https?|ftp)://)(?:\S+(?::\S*)?@|\d{1,3}(?:\.\d{1,3}){3}|(?:(?:[a-z\d\x{00a1}-\x{ffff}]+-?)*[a-z\d\x{00a1}-\x{ffff}]+)(?:\.(?:[a-z\d\x{00a1}-\x{ffff}]+-?)*[a-z\d\x{00a1}-\x{ffff}]+)*(?:\.[a-z\x{00a1}-\x{ffff}]{2,6}))(?::\d+)?(?:[^\s]*)?$%iu', $url);
}

// Function to generate the next short URL
function get_next_short_url(){
    // Retrieve the last short URL from the database
    $last = get_last_short();

    // If there is no last short URL, start with 'aaa'
    if($last == null){
        return 'aaa';
    }

    // Increment the last short URL to get the next one
    return ++$last;
}

// Function to get the last short URL from the database
function get_last_short(){
    // Database connection parameters
    
    
    try {
        // Establishing a new PDO connection
        $pdo = db_connect();
    
        // Executing a query to retrieve the last short URL from the database
        $stmt = $pdo->query("SELECT `short` FROM `shorts` ORDER BY `id` DESC LIMIT 1");
    
        // Returning the last short URL if found
        if ($row = $stmt->fetch()) {
            return $row['short'];
        } else {
            // Return null if no record is found
            return null;
        }
    
    } catch (\PDOException $e) {
        // Handling any exceptions during the database operation
        json_response_shortener("Error", false);
    }
}

// Function to process the 'get short gate' request
function f_get_short_gate(){    
    // Initializing the captcha success variable
    $captcha_success = null;

    // Checking if the 'short' parameter is provided in the POST request
    if(!isset($_POST['short'])){
        json_response_shortener("Bad request", false);
    }

    // Handling the reCAPTCHA validation if provided
    if(isset($_POST['captcha'])){
        // reCAPTCHA data
        $captcha = $_POST['captcha'];
        $secret = RECAPTCHA_V2_SECRET_KEY; // Replace with your secret key
        $ip = $_SERVER['REMOTE_ADDR'];

        // Sending the reCAPTCHA validation request
        $dav = file_get_contents("https://www.google.com/recaptcha/api/siteverify?secret=".$secret."&response=".$captcha."&remoteip=".$ip);

        // Checking the reCAPTCHA validation response
        if(json_decode($dav, true)['success'] === true){
            $captcha_success = true;
        } else {
            json_response_shortener("Captcha error", false);
        }
    }

    // Checking the password if provided
    $password = (isset($_POST['password']) && strlen($_POST['password']) > 5) ? hash('sha256', $_POST['password']) : null;

    // Retrieving the data for the provided short URL
    $sh_data = db_short_request($_POST['short']);

    // Validating the password and responding accordingly
    if($password != $sh_data['password']){
        json_response_shortener("Wrong password", false);
    }

    // Save visit
    log_visit($_POST['short']);

    // Sending the original URL in the response if validation is successful
    json_response_shortener(URL . $sh_data['url'], true);
}

function db_short_request($sh){

    try {
        $pdo = db_connect();

        // Paraméterezett lekérdezés használata
        $stmt = $pdo->prepare("SELECT * FROM `shorts` WHERE `short` = :sh");
        $stmt->execute(['sh' => $sh]);

        if ($row = $stmt->fetch()) {
            return $row;
        } else {
            return null;
        }

    } catch (\PDOException $e) {
        json_response_shortener("Error", false);
    }
}


function log_visit($short){

    $ip = $_SERVER['REMOTE_ADDR'];
    $ip_data = get_ip_details($ip);
    
    if($ip_data['status'] == "success"){
        try {
            // Establishing a new PDO connection
            $pdo = db_connect();
    
            // Preparing an SQL query to insert the new short URL record
            $sql = "INSERT INTO visits (short, ip, country_code, city_name) VALUES (:short, :ip, :country_code, :city_name)";
            $stmt = $pdo->prepare($sql);
    
            // Binding the input data to the prepared SQL statement
            $stmt->bindParam(':short', $short);
            $stmt->bindParam(':ip', $ip);
            $stmt->bindParam(':country_code', $ip_data['countryCode']);
            $stmt->bindParam(':city_name', $ip_data['city'], PDO::PARAM_BOOL);
    
            // Executing the SQL query
            $stmt->execute();
    
        } catch (\PDOException $e) {
            global $logger;
            $logger->critical("Error inserting VIEW, IP: " . $ip . " IPAPI Response: " . json_encode( $ip_data ));
        }
    } else {
        global $logger;
        $logger->critical("Error getting IP API INFO, IP: " . $ip . " Response: " . json_encode( $ip_data ));
    }
}

function get_ip_details($ip){
    $dav = file_get_contents("http://ip-api.com/json/$ip");

    try {
        return json_decode( $dav, true );
    } catch(Exception $e){
        return null;
    }
}