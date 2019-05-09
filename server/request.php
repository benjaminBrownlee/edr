<?php
    $DBserver = getenv("IP");
    $DBusername = getenv("C9_USER");
    $DBpassword = "";
    $DBname = "EDR";
    $DBport = 3306;
    $DBconnection = mysqli_connect($DBserver, $DBusername, $DBpassword, $DBname, $DBport);
    
    session_start();
    
    if($_SERVER["REQUEST_METHOD"]  == "GET") {
        $type = $_REQUEST["type"];
        switch($type) {
            case "identify":
                $vin = $_REQUEST["vin"];
                $data = identify($vin);
                $data = $data->{"Results"};
                $model = $data[0]->{"Model"};
                $year = $data[0]->{"ModelYear"};
                $make = $data[0]->{"NCSAMake"};
                $vin = $data[0]->{"VIN"};
                echo json_encode(array(
                    "model"=>$model,
                    "make"=>$make,
                    "year"=>$year,
                    "vin"=>$vin
                ));
                break;
            case "login":
                if(!$_SESSION["phone"] || !$_SESSION["vehicle"]) {
                    $vin = $_REQUEST["vin"];
                    $uuid = $_REQUEST["uuid"];
                    $query = "select id from vehicles where vin = '$vin'";
                    $vehicle = extractData(mysqli_query($DBconnection, $query));
                    $query = "select id from users where uuid = '$uuid'";
                    $phone = extractData(mysqli_query($DBconnection, $query));
                    if(!sizeof($vehicle)) {
                        $data = identify($vin);
                        $data = $data->{"Results"};
                        $model = $data[0]->{"Model"};
                        $year = $data[0]->{"ModelYear"};
                        $make = $data[0]->{"NCSAMake"};
                        $vin = $data[0]->{"VIN"};
                        $query = "insert into vehicles (vin, year, model, make) values ('$vin', $year, '$model', '$make')";
                        mysqli_query($DBconnection, $query);
                        $vehicle = (int) mysqli_insert_id($DBconnection);
                    }
                    else $vehicle = (int) $vehicle[0]["id"];
                    if(!sizeof($phone)) {
                        $query = "insert into users (uuid) values ('$uuid')";
                        mysqli_query($DBconnection, $query);
                        $phone = (int) mysqli_insert_id($DBconnection);
                    }
                    else $phone = (int) $phone[0]["id"];
                    $_SESSION["vehicle"] = $vehicle;
                    $_SESSION["phone"] = $phone;
                }
                break;
            case "logout":
                session_unset();
                break;
            case "start":
                if($phone = $_SESSION["phone"] && $vehicle = $_SESSION["vehicle"] && !$_SESSION["drive"]) {
                    $query = "insert into drives (user, vehicle, starttime) values ($phone, $vehicle, now())";
                    mysqli_query($DBconnection, $query);
                    $drive = (int) mysqli_insert_id($DBconnection);
                    $_SESSION["drive"] = $drive;
                }
                break;
            case "update":
                if($drive = $_SESSION["drive"]) {
                    $distance = (int) $_REQUEST["distance"];
                    $runtime = (int) $_REQUEST["runtime"];
                    $speed = (int) $_REQUEST["speed"];
                    $events = $_REQUEST["events"];
                    echo var_dump($events);
                    $query = "insert into segments (time, drive, runtime, distance, speed, events) values (now(), $drive, $runtime, $distance, $speed, '$events')";
                    mysqli_query($DBconnection, $query);
                }
                break;
            case "stop":
                if($drive = $_SESSION["drive"]) {
                    $reason = $_REQUEST["reason"];
                    $query = "select * from segments where drive=$drive order by runtime asc";
                    $data = extractData(mysqli_query($DBconnection, $query));
                    $runtime = (int) $data[sizeof($data) - 1]["runtime"] - (int) $data[0]["runtime"];
                    $distance = (int) $data[sizeof($data) - 1]["distance"] - (int) $data[0]["distance"];
                    $query = "update drives set runtime=$runtime, distance=$distance, stoptime=now() where id=$drive";
                    mysqli_query($DBconnection, $query);
                    session_unset($_SESSION["drive"]);
                }
                break;
            case "history":
                if($phone = $_SESSION["phone"]) {
                    $query = "select drives.vehicle, drives.starttime, drives.stoptime, drives.distance, drives.runtime, vehicles.vin, vehicles.make, vehicles.year, vehicles.model from drives left join vehicles on drives.vehicle = vehicles.id where drives.user=$phone order by drives.starttime asc";
                    $data = extractData(mysqli_query($DBconnection, $query));
                    echo var_dump($data);
                    echo json_encode($data);
                }
                break;
            case "":
                
                break;
        }
    }
    
    mysqli_close($DBconnection);
    
    function identify($vin) {
        $postdata = http_build_query(array("format"=>"json", "data"=>$vin));
        $options = array("http"=>array("method"=>"GET", "content"=>$postdata));
        $url = "https://vpic.nhtsa.dot.gov/api/vehicles/DecodeVinValuesExtended/".$vin."?format=json";
        
        $context = stream_context_create($options);
        $request = fopen($url, 'rb', false, $context);
        $response = stream_get_contents($request);
        
        return json_decode($response);
    }
    
    function sanitize($variable) {
        switch(gettype($variable)) {
            case "string":
                $json = json_decode($variable);
                if(json_last_error() == JSON_ERROR_NONE) sanitize($json);
                else {
                    $variable = trim($variable);
                    $variable = stripslashes($variable);
                    $variable = htmlspecialchars($variable);
                    return $variable;
                }
                break;
            case "array":
                foreach($variable as $key => $property) $variable[$key] = sanitize($property);
                return $variable;
                break;
            case "object":
                foreach($variable as $key => $property) $variable->$key = sanitize($property);
                return $variable;
                break;
            case "boolean":
            case "integer":
                return $variable;
                break;
        }
    }
    
    function extractData($table) {
        $data = array();
        while($row = mysqli_fetch_assoc($table)) $data[] = $row;
        return $data;
    }
    
    function console($info) {
        echo "<script> console.log(JSON.parse(".json_encode($info).")); </script>";
    }
?>