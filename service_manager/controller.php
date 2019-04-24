<?php

/**
 * Class controller
 *
 * This is the main program and runs as a daemon in a loop.  It does the following:
 *
 * - Starts up the services and initializes the devices on startup
 * - Polls the devices for new events, and sends them to the smartthings hub
 * - Listens for incoming API commands from the SmartThings hub, and sends the command to the right device
 */
class controller {

    private static $listening_socket;

    private static function load_config(){

        $pi = pathinfo(__FILE__);
        $lib_dir = $pi['dirname'];
        if (!file_exists($lib_dir . "/config.php")){
            print "\nConfig file \"" . $lib_dir . "/config2.php" . "\" does not exist.\n\nPlease copy the config.example.php to config.php, and then edit it to configure your setup.\n\n";
            exit;
        }

        require_once("config.php");
        require_once("socket_helper.php");
        require_once("service.php");
        require_once("device.php");
        require_once("event.php");

        if (!config::$log_dir){
            print "Log dir is not set in config.php; no log will be created.\n";
        }
        if (!config::$api_key){
            print "API Key is not set in config.php; No API key will be used in the communications between Smartthings hub and LAN controller.\n";
        }

    }


    public static $shutting_down = false;
    private static $device_list_synced = false;

    public static function run(){

        self::load_config();

        // start services
        service::load_services();
        service::start_all();

        // start api to handle incoming requests to the controller
        self::$listening_socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        socket_set_option(self::$listening_socket, SOL_SOCKET, SO_REUSEADDR, 1);
        socket_bind(self::$listening_socket, 0, config::$api_listener_port);
        socket_listen(self::$listening_socket);

        // shutdown function
        // (register_shutdown_function() doesn't work if script is interupted, ie ctrl+c; pcntl_signal() handles this)
        self::log_msg("Registered shutdown function");
        declare(ticks = 1);
        pcntl_signal(SIGINT, function(){
            controller::$shutting_down = true;
        });

        $watchdog_ping_file = "/tmp/service_manager_watchdog_ping";
        $last_watchdog_ping_time = null;
        $watchdog_ping_interval = 15;

        // main loop
        while (true) {

            usleep(10000); // run loop 100 times per second

            // every 30 seconds touch a tmp file so the watchdog script will know we're still running.
            // if the tmp file doesn't get touched for a few minutes, the watchdog script reboots the machine
            if (
                $last_watchdog_ping_time === null
                || time() >= $last_watchdog_ping_time + $watchdog_ping_interval
            ){
                touch($watchdog_ping_file);
                $last_watchdog_ping_time = time();
            }

            // sync the device list if we just started running, or if the app was just installed,
            // both of which would set this flag to false
            if (!self::$device_list_synced){
                self::send_devices_to_smartthings();
            }

            // handle commands coming in from the smartthings hub via the http api
            while (true){
                $sockets_with_activity = array(self::$listening_socket);
                $write = null;
                $except = null;
                if (socket_select($sockets_with_activity, $write, $except, 0) < 1){
                    // there is currently no incoming API request
                    break;
                }

                // we have an incoming request
                $incoming_socket = socket_accept(self::$listening_socket);

                // get the source ip of the incoming API request
                socket_getpeername($incoming_socket, $ip);

                $result = socket_helper::read_http_request($incoming_socket);
                if (!$result['success']){
                    self::log_msg("Incoming API request failed: " . $result['error_msg']);
                    continue;
                }

                self::process_api_command($incoming_socket, $result['request']);

            }
            // end of handling api requests

            // handle events coming in from the devices that we need to pass on to the smartthings hub
            if (self::$device_list_synced){
                service::poll_all();
                self::send_events_to_smartthings();
            }

            if (self::$shutting_down){
                break;
            }

        }

        self::log_msg("Shutting down controller");
        socket_close(self::$listening_socket);
        service::stop_all();

    }

    private static function send_events_to_smartthings(){
        $event_list = array();
        while ($event = service::get_event()){
            $event_properties = array(
                "deviceId" => $event->device->get_device_id(),
                "name" => $event->name,
                "value" => $event->value,
            );
            if ($event->description_text){
                $event_properties['descriptionText'] = $event->description_text;
            }
            if ($event->link_text){
                $event_properties['linkText'] = $event->description_text;
            }
            $event_list[] = $event_properties;
        }
        if (!$event_list){
            return;
        }

        $result = self::send_to_smartthings(array(
            "command" => "processEvents",
            "data" => $event_list
        ));

        if ($result['success']){
            self::log_msg("sent " . count($event_list) . " events to SmartThings hub");
        }
        else {
            self::log_msg("failed to send events " . $result['error_msg']);
        }

    }


    private static function send_devices_to_smartthings(){
        self::log_msg("Going to send device list to SmartThings hub");
        $devices_payload = array();
        foreach (service::get_all_devices() as $device){
            /** @var device $device */
            $devices_payload[] = array(
                "typeName" => $device->get_type(),
                "deviceId" => $device->get_device_id(),
                "name" => $device->get_name(),
            );
        }

        $result = self::send_to_smartthings(array(
            "command" => "updateDeviceList",
            "data" => $devices_payload
        ));
        if ($result['success']){
            self::log_msg("Successfully sent " . count($devices_payload) . " devices");
            self::$device_list_synced = true;
        }
        else {
            self::log_msg("Could not send devices: " . $result['error_msg']);
        }
    }


    private static function send_to_smartthings($payload){

        if (!config::$smartthings_hub_endpoint){
            return array(
                "success" => false,
                "error_msg" => "SmartThings hub endpoint is not set; nowhere to send payload",
            );
        }

        if (config::$api_key){
            $payload['key'] = config::$api_key;
        }

        $json_encoded_payload = json_encode($payload);
        $url = "http://" . config::$smartthings_hub_endpoint;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_USERAGENT, config::$system_name);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-length:' . strlen($json_encoded_payload))
        );
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json_encoded_payload);

        $response = curl_exec($ch);
        $info = curl_getinfo($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return array(
                "success" => false,
                "error_msg" => $error,
            );
        }
        else if (!preg_match('#^2\d\d$#', $info['http_code'])) {
            return array(
                "success" => false,
                "error_msg" => "HTTP response code of " . $info['http_code'],
            );
        }

        return array(
            "success" => true,
            "response" => $response,
        );

    }

    private static function process_api_command($socket, $request){

        self::log_msg("Got incoming API request");
//        self::log_msg("Got incoming API request: " . var_export($request, true));

        socket_helper::send_http_response($socket, "200 OK");

        $data = json_decode($request['content'], true);
        if (!$data){
            self::log_msg("Could not json_decode request content; ignoring");
            return;
        }

        if (config::$api_key){
            if (!isset($data['key']) || !$data['key']){
                self::log_msg("api key not included in request, ignoring: " . $request['content']);
                return;
            }
            if ($data['key'] !== config::$api_key){
                self::log_msg("api key included in request is not correct, ignoring: " . $request['content']);
                return;
            }
        }

        if (!isset($data['command']) || !$data['command']){
            self::log_msg("no command included in the json payload: " . $request['content']);
            return;
        }

        if ($data['command'] == "register_endpoint"){
            if (!isset($data['endpoint']) || !$data['endpoint']){
                self::log_msg("no endpoint to register included in json payload: " . $request['content']);
                return;
            }
            config::$smartthings_hub_endpoint = $data['endpoint'];

            // this command causes the device list to be resynced later
            self::$device_list_synced = false;

            self::log_msg("registered SmartThings Hub's api endpoint as: " . $data['endpoint']);
            return;
        }

        if ($data['command'] && isset($data['device_id'])){
            $device = service::get_device($data['device_id']);
            if (!$device){
                self::log_msg("Could not run command on unknown device with id '" . $data['device_id'] . "'; " . serialize($data));
                return;
            }
            if (!method_exists($device, $data['command'])){
                self::log_msg("Device id '" . $data['device_id'] . "' of class '" . get_class($device) . " does not support command '" . $data['command'] . "'; " . serialize($data));
                return;
            }
            $command = $data['command'];
            $device->log_msg("Running command: " . $data['command']);
            $device->$command();
            return;
        }

        self::log_msg("Unknown API request: " . serialize($data));

    }




    public static function log_msg($msg){
        $pid = getmypid();
        $msg = date("Y-m-d H:i:s") . ":\t" . $pid . "\t" . $msg . "\n";
        print $msg;

        if (config::$log_dir){
            $logfile = config::$log_dir . "/log_" . date("Ymd") . ".log";
            error_log($msg, 3, $logfile);
        }
    }


}

