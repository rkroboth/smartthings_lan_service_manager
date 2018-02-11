<?php

class envisalink_service extends service {

    // config items
    private $controller_ip;
    private $controller_port;
    private $controller_web_password;
    private $code;

    // used to maintain state
    private $envisalink_socket;
    private $poll_interval = 60;
    private $last_poll;
    private $last_set_time_dow;


    /**
     * @var bool Tells whether alarm is armed in silent mode or not
     */
    private $alarm_armed_silent = false;

    public function initialize($config = null){
        $this->controller_ip = $config['controller_ip'];
        $this->controller_port = $config['controller_port'];
        $this->controller_web_password = $config['controller_web_password'];
        $this->code = $config['code'];
    }

    public function start(){

        $this->log_msg("Attempting to connect and log in to Envisalink...");
        $result = $this->login();
        if (!$result['success']){
            $this->log_msg("Could not log in to Envisalink: " . $result['error_msg']);
            exit;
        }
        $this->log_msg("logged in to Envisalink");

        $result = $this->update_system_status();
        if (!$result['success']){
            $this->log_msg("System status command failed: " . $result['error_msg']);
            exit;
        }

        $this->last_poll = time();
    }

    public function poll(){

        // poll the controller periodically, which basically forces it's device to trigger events with their
        // current state... in case the event somehow didn't get triggered when it happened i gues?
        if (time() - $this->last_poll > $this->poll_interval){
            $result = $this->poll_controller();
            if (!$result['success']){
                $this->log_msg("Envisalink poll failed: " . $result['error_msg']);
            }
            else {
                // success
            }
            $this->last_poll = time();
        }

        // set the time once per day, but not until 5 minutes after hour
        if (date("w") !== $this->last_set_time_dow && date("i") > 5){
            $result = $this->set_controller_time();
            if (!$result['success']){
                $this->log_msg("Time set");
            }
            else {
                $this->log_msg("Could not set time");
            }
            $this->last_set_time_dow = date("w");
        }

        // check for socket activity on the envisalink socket,
        // this is where the devices trigger events that happened to them
        $sockets_with_activity = array($this->envisalink_socket);
        $write = null;
        $except = null;
        if (socket_select($sockets_with_activity, $write, $except, 0) == 1){

            $result = socket_helper::read_line_from_socket($this->envisalink_socket);

            if (!$result['success']){
                $this->log_msg("Error reading from the Envisalink socket, so exiting: " . $result['error_msg']);
                exit;
            }

            $result = $this->process_envisalink_code($result['response']);
            if ($result && isset($result['zone_id']) && $result['zone_id']){
                if ($result['zone_id'] == "alarm"){
                    $this->on_alarm_change($result['value']);
                }
                else {
                    $device = $this->load_device_by_zone_id($result['zone_id']);
                    if ($device){
                        $device->on_change($result['value']);
                    }
                }
            }
        }
    }

    /**
     * Handle changes reported by Envisalink
     * @param $value
     */
    public function on_alarm_change($value){

        $alarm = $this->load_device_by_zone_id("alarm");

        if ($value == "off"){
            $alarm->on_alarm_triggered_change(false);

            // if in armed silent mode, then leave in armed silent mode
            if ($this->alarm_armed_silent){
                $alarm->on_change("silent");
            }
            else {
                $alarm->on_change("off");
            }
        }

        if ($value == "busy"){
            // if in armed silent mode, then leave in armed silent mode
            if (!$this->alarm_armed_silent){
                $alarm->on_change("busy");
            }
        }

        if ($value == "arming"){
            $alarm->on_change("arming");
        }

        if ($value == "stay"){
            $alarm->on_change("stay");
        }

        if ($value == "away"){
            $alarm->on_change("away");
        }

        if ($value == "triggered"){
            $alarm->on_alarm_triggered_change(true);
        }

        // ie reset from being triggered
        if ($value == "reset"){
            $alarm->on_alarm_triggered_change(false);
        }

    }

    /**
     *
     * Handle commands to control the alarm from the SmartThings hub
     * @param $device device
     * @param $new_value string
     */
    public function update_device($device, $new_value){
        // the only device on the envisalink system that we get commands from is the alarm.
        // but just sanity check that here.
        if ($device->get_device_id() !== "envisalink_alarm"){
            return;
        }

        $this->log_msg("Received alarm '$new_value' command from SmartThings hub");

        // handle commands: disarm, stay, away, silent, panic

        if ($new_value == "disarm"){

            if ($this->alarm_armed_silent){
                $this->alarm_armed_silent = false;
                $alarm = $this->load_device_by_zone_id("alarm");
                $alarm->on_change("off");
                $this->log_msg("Changed Envisalink status from 'silent' to 'off'");
            }
            else {
                $result = $this->disarm();
                if ($result['success']){
                    $this->log_msg("Successfully disarmed the Envisalink");
                }
                else {
                    $this->log_msg("Error disarming the Envisalink: " . $result['error_msg']);
                    if ($result['error_msg'] == "System was not armed"){
                        $this->log_msg("System was not armed, going to tell the SmartThings hub that the system was disarmed");
                        $alarm = $this->load_device_by_zone_id("alarm");
                        $alarm->on_change("off");
                    }
                }
            }
        }

        if ($new_value == "stay"){
            $result = $this->arm_stay();
            if ($result['success']){
                $this->log_msg("Successfully set Envisalink to arm_stay");
            }
            else {
                $this->log_msg("Error setting Envisalink to arm_stay: " . $result['error_msg']);
            }
        }

        if ($new_value == "away"){
            $result = $this->arm_away();
            if ($result['success']){
                $this->log_msg("Successfully set Envisalink to arm_away");
            }
            else {
                $this->log_msg("Error setting Envisalink to arm_away: " . $result['error_msg']);
            }
        }

        // The envisalink doesn't support silent mode, so there's nothing to update on the actual device.
        // We just pass the event back to the SmartThings hub so it will display that it is in silent mode.
        if ($new_value == "silent"){
            $this->alarm_armed_silent = true;
            $alarm = $this->load_device_by_zone_id("alarm");
            $alarm->on_change("silent");
            $this->log_msg("Changed Envisalink status to 'silent'");
        }

        if ($new_value == "panic"){
            $result = $this->panic();
            if ($result['success']){
                $this->log_msg("Successfully triggered Envisalink alarm");
            }
            else {
                $this->log_msg("Error triggering Envisalink alarm: " . $result['error_msg']);
            }
        }


    }

    /**
     * @param $zone_id int
     * @return device
     */
    private function load_device_by_zone_id($zone_id){
        foreach ($this->get_devices() as $device){
            if ($device->get_config('zone_id') == $zone_id){
                return $device;
            }
        }
        return null;
    }

    public function stop(){
        socket_close($this->envisalink_socket);
        $this->log_msg("Closed connection to Envisalink controller");
    }

    private function build_checksum($command){
        $dec_total = 0;
        for ($i=0; $i < strlen($command); $i++){
            $dec_total += ord($command[$i]);
        }
        $checksum = strtoupper(dechex($dec_total % 256));
        return $checksum;
    }

    private function login(){

        // create socket and connect
        $this->envisalink_socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if ($this->envisalink_socket === false) {
            return array(
                "success" => false,
                "error_msg" => "socket_create() failed: reason: " . socket_strerror(socket_last_error())
            );
        }

        $result = socket_connect($this->envisalink_socket, $this->controller_ip, $this->controller_port);
        if ($result === false) {
            return array(
                "success" => false,
                "error_msg" => "socket_connect() failed. Reason: ($result) " . socket_strerror(socket_last_error($this->envisalink_socket))
            );
        }

        // get the hello string "5053CD" from the server
        $result = socket_helper::read_line_from_socket($this->envisalink_socket);
        if (!$result['success']){
            return array(
                "success" => false,
                "error_msg" => "Did not get expected HELLO response from server (maybe need to restart envisalink?): " .  $result['error_msg']
            );
        }
        if ($result['response'] != "5053CD"){
            socket_close($this->envisalink_socket);
            return array(
                "success" => false,
                "error_msg" => "Did not get expected HELLO response from server - got " . $result['response']
            );
        }


        // send 005 command (login command) and password
        $command = "005" . $this->controller_web_password;
        $command = $command . $this->build_checksum($command);
        $result = socket_helper::write_line_to_socket($this->envisalink_socket, $command);
        if (!$result['success']){
            return array(
                "success" => false,
                "error_msg" => "Error sending login command to envisalink: " . $result['error_msg']
            );
        }

        // get a confirmation of the message we just sent
        $result = socket_helper::read_line_from_socket($this->envisalink_socket);
        if (!$result['success']){
            return array(
                "success" => false,
                "error_msg" => "Did not get expected confirmation of login command from server: " .  $result['error_msg']
            );
        }
        if ($result['response'] != "5000052A"){
            socket_close ($this->envisalink_socket);
            return array(
                "success" => false,
                "error_msg" => "Did not get expected confirmation of login command from server - got " . trim($result['response'])
            );
        }

        // get a confirmation of whether the login was successful
        $result = socket_helper::read_line_from_socket($this->envisalink_socket);
        if (!$result['success']){
            return array(
                "success" => false,
                "error_msg" => "Did not get expected confirmation of login success from server: " .  $result['error_msg']
            );
        }
        if ($result['response'] != "5051CB"){
            socket_close ($this->envisalink_socket);
            return array(
                "success" => false,
                "error_msg" => "Did not get expected confirmation of login success from server - got " . trim($result['response'])
            );
        }

        return array(
            "success" => true
        );

    }

    private function poll_controller(){

        // send 000 command (poll command)
        $command = "000";
        $command = $command . $this->build_checksum($command);
        $result = socket_helper::write_line_to_socket($this->envisalink_socket, $command);
        if (!$result['success']){
            return array(
                "success" => false,
                "error_msg" => "Error sending poll command to envisalink: " . $result['error_msg']
            );
        }

        // get a confirmation of the message we just sent
        $result = socket_helper::read_line_from_socket($this->envisalink_socket);
        if (!$result['success']){
            return array(
                "success" => false,
                "error_msg" => "Did not get expected confirmation of poll command from server: " .  $result['error_msg']
            );
        }
        if ($result['response'] != "50000025"){
            return array(
                "success" => false,
                "error_msg" => "Did not get expected confirmation of poll command from server - got " . trim($result['response'])
            );
        }

        return array(
            "success" => true
        );

    }

    /**
     * Causes envisalink to send statuses of everything
     * @return array
     */
    private function update_system_status(){

        $command = "001";
        $command = $command . $this->build_checksum($command);

        $result = socket_helper::write_line_to_socket($this->envisalink_socket, $command);
        if (!$result['success']){
            return array(
                "success" => false,
                "error_msg" => "Error sending system status command to envisalink: " . $result['error_msg']
            );
        }

        // get a confirmation of the message we just sent
        $result = socket_helper::read_line_from_socket($this->envisalink_socket);
        if (!$result['success']){
            return array(
                "success" => false,
                "error_msg" => "Did not get expected confirmation of system status command from server: " .  $result['error_msg']
            );
        }
        if ($result['response'] != "50000126"){
            return array(
                "success" => false,
                "error_msg" => "Did not get expected confirmation of system status command from server - got " . trim($result['response'])
            );
        }

        return array(
            'success' => true,
        );
    }



    public function arm_stay(){

        $command = "0711*9" . $this->code;
        $command = $command . $this->build_checksum($command);
        $result = socket_helper::write_line_to_socket($this->envisalink_socket, $command);
        if (!$result['success']){
            return array(
                "success" => false,
                "error_msg" => "Error sending system arm stay command to envisalink: " . $result['error_msg']
            );
        }

        // get a confirmation of the message we just sent
        $result = socket_helper::read_line_from_socket($this->envisalink_socket);
        if (!$result['success']){
            return array(
                "success" => false,
                "error_msg" => "Did not get expected confirmation of system arm stay command from server: " .  $result['error_msg']
            );
        }
        if ($result['response'] == "5020242D"){
            return array(
                "success" => false,
                "error_msg" => "System is not ready to be armed.  Please close all doors and clear motion sensors, make sure system is not already armed, then try again."
            );
        }

        if ($result['response'] != "500071" . $this->build_checksum("500071")){
            return array(
                "success" => false,
                "error_msg" => "Did not get expected confirmation of system arm stay command from server - got " . $result['response']
            );
        }

        return array(
            'success' => true,
        );

    }


    public function arm_away(){

        $command = "0301";
        $command = $command . $this->build_checksum($command);
        $result = socket_helper::write_line_to_socket($this->envisalink_socket, $command);
        if (!$result['success']){
            return array(
                "success" => false,
                "error_msg" => "Error sending system arm away command to envisalink: " . $result['error_msg']
            );
        }

        // get a confirmation of the message we just sent
        $result = socket_helper::read_line_from_socket($this->envisalink_socket);
        if (!$result['success']){
            return array(
                "success" => false,
                "error_msg" => "Did not get expected confirmation of system arm away command from server: " .  $result['error_msg']
            );
        }
        if ($result['response'] == "5020242D"){
            return array(
                "success" => false,
                "error_msg" => "System is not ready to be armed.  Please close all doors and clear motion sensors, make sure system is not already armed, then try again."
            );
        }
        if ($result['response'] != "500030" . $this->build_checksum("500030")){
            return array(
                "success" => false,
                "error_msg" => "Did not get expected confirmation of system arm away command from server - got " . $result['response']
            );
        }

        return array(
            'success' => true,
        );

    }

    public function disarm(){

        $command = "0401" . $this->code;
        $command = $command . $this->build_checksum($command);
        $result = socket_helper::write_line_to_socket($this->envisalink_socket, $command);
        if (!$result['success']){
            return array(
                "success" => false,
                "error_msg" => "Error sending system disarm command to envisalink: " . $result['error_msg']
            );
        }

        // get a confirmation of the message we just sent
        $result = socket_helper::read_line_from_socket($this->envisalink_socket);
        if (!$result['success']){
            return array(
                "success" => false,
                "error_msg" => "Did not get expected confirmation of system disarm command from server: " .  $result['error_msg']
            );
        }

        // if system was not armed, then we should get this return val
        if ($result['response'] == "502023" . $this->build_checksum("502023")){
            return array(
                "success" => false,
                "error_msg" => "System was not armed"
            );
        }

        if ($result['response'] != "500040" . $this->build_checksum("500040")){
            return array(
                "success" => false,
                "error_msg" => "Did not get expected confirmation of system disarm command from server - got " . $result['response']
            );
        }

        return array(
            "success" => true,
        );
    }


    public function panic(){

        $command = "0603";
        $command = $command . $this->build_checksum($command);
        $result = socket_helper::write_line_to_socket($this->envisalink_socket, $command);
        if (!$result['success']){
            return array(
                "success" => false,
                "error_msg" => "Error sending system panic command to envisalink: " . $result['error_msg']
            );
        }

        // get a confirmation of the message we just sent
        $result = socket_helper::read_line_from_socket($this->envisalink_socket);
        if (!$result['success']){
            return array(
                "success" => false,
                "error_msg" => "Did not get expected confirmation of system panic command from server: " .  $result['error_msg']
            );
        }
        if ($result['response'] != "500060" . $this->build_checksum("500060")){
            return array(
                "success" => false,
                "error_msg" => "Did not get expected confirmation of system panic command from server - got " . $result['response']
            );
        }

        return array(
            "success" => true,
        );
    }

    private function get_device_name_by_zone_id($zone_id){
        $device = $this->load_device_by_zone_id($zone_id);
        $device_name = "zone " . $zone_id;
        if ($device){
            $device_name = $device->get_name() . " (" . $device_name . ")";
        }
        return $device_name;
    }

    private function process_envisalink_code($code){

        $response = array();

        // Keypad LED state change
        if (substr($code, 0,3) == "510"){
            //ignore
        }

        // Keypad LED flash state change (sent when an alarm goes off)
        else if (substr($code, 0,3) == "511"){
            //ignore
        }

        // zone has gone into alarm due to a zone being opened while armed.
        // the is duplicative of code 654, so not necessary beyond knowing which zone trigger the alarm.
        // so we log it only
        else if (substr($code, 0,3) == "601"){
            $triggered_zone = (int)substr($code,4,3);
            $this->log_msg("Envisalink reports that alarm has been triggered by zone: " . $this->get_device_name_by_zone_id($triggered_zone));
        }

        // zone open
        else if (substr($code, 0,3) == "609"){
            $zone_id = (int)substr($code,3,3);
            $this->log_msg("Envisalink reports that zone has opened: " . $this->get_device_name_by_zone_id($zone_id));
            $response = array(
                "zone_id" => $zone_id,
                "value" => "true",
            );
        }

        // zone closed
        else if (substr($code, 0,3) == "610"){
            $zone_id = (int)substr($code,3,3);
            $this->log_msg("Envisalink reports that zone has closed: " . $this->get_device_name_by_zone_id($zone_id));
            $response = array(
                "zone_id" => $zone_id,
                "value" => false,
            );
        }

        // zone has gone into alarm due to panic code being entered
        // the is duplicative of code 654, so not necessary beyond knowing what triggered the alarm.
        // so we log it only
        else if (substr($code, 0,3) == "625"){
            $this->log_msg("Envisalink reports that panic alarm has been activated");
        }

        // partition ready to be armed (not armed)
        else if (substr($code, 0,3) == "650" && (int)substr($code,3,1) == 1){
            $this->log_msg("Envisalink reports that partition ready to be armed (no zones are open)");
            $response = array(
                "zone_id" => "alarm",
                "value" => "off"
            );
        }

        // partition not ready (door or sensor is open)
        else if (substr($code, 0,3) == "651" && (int)substr($code,3,1) == 1){
            $this->log_msg("Envisalink reports that partition not ready to be armed (one or more zones are open)");
            $response = array(
                "zone_id" => "alarm",
                "value" => "busy"
            );
        }

        // partition armed
        else if (substr($code, 0,3) == "652" && (int)substr($code,3,1) == 1){
            if ((int)substr($code,4,1) == 0){
                // partition armed away
                $this->log_msg("Envisalink reports that partition is armed away");
                $response = array(
                    "zone_id" => "alarm",
                    "value" => "away"
                );
            }
            else {
                // partition armed stay
                $this->log_msg("Envisalink reports that partition is armed stay");
                $response = array(
                    "zone_id" => "alarm",
                    "value" => "stay"
                );
            }
        }

        // partition ready to be armed (even though a non-alarm zone like the overhead garage door is open) (force-arming enabled)
        else if (substr($code, 0,3) == "653" && (int)substr($code,3,1) == 1){
            $this->log_msg("Envisalink reports that partition ready to be armed (even though a non-alarm zone is open)");
            $response = array(
                "zone_id" => "alarm",
                "value" => "off"
            );
        }

        // partition has gone into alarm. tripped when a zone is tripped, or the panic button is tripped
        // this code always trips when the alarm is tripped (panic or opened door while armed)
        else if (substr($code, 0,3) == "654" && (int)substr($code,3,1) == 1){
            $this->log_msg("Envisalink reports that partition has gone into alarm or the panic button was activated");
            $response = array(
                "zone_id" => "alarm",
                "value" => "triggered"
            );
        }

        // partition has been disarmed. seems to be a notification only, it's followed up by the 650 command
        else if (substr($code, 0,3) == "655" && (int)substr($code,3,1) == 1){
            // ignore, since it's followed up by the 650 command
        }

        // partition is arming
        else if (substr($code, 0,3) == "656" && (int)substr($code,3,1) == 1){
            $this->log_msg("Envisalink reports that partition is arming");
            $response = array(
                "zone_id" => "alarm",
                "value" => "arming"
            );
        }

        // system triggered, but entry delay granted befor triggering alarm
        else if (substr($code, 0,3) == "657" && (int)substr($code,3,1) == 1){
            $this->log_msg("Envisalink reports that system trigger, but entry delay is in progress");
        }


        // partition is busy (seems to only affect the partitions that aren't set up)
        else if (substr($code, 0,3) == "673"){
            $this->log_msg("Envisalink reports that non-alarm partition " . substr($code,3,1) . " is busy");
        }

        // partition has been disarmed by a user. seems to be a notification of which user only, it's followed up by the 650 command
        else if (substr($code, 0,3) == "750" && (int)substr($code,3,1) == 1){
            $this->log_msg("Envisalink reports that partition has been disarmed by user " . substr($code,4,3));
        }

        // partition trouble LED on keypad is on
        else if (substr($code, 0,3) == "840"){
            $this->log_msg("Envisalink reports that partition " . substr($code,3,1) . " trouble LED is on");
        }

        // partition trouble LED on keypad is off
        else if (substr($code, 0,3) == "841"){
            $this->log_msg("Envisalink reports that partition " . substr($code,3,1) . " trouble LED is off");
        }

        else {
            $this->log_msg("Envisalink reports unknown code: " . $code);
        }

        return $response;
    }

    private function set_controller_time(){

        // send 010 command (set time)
        $command = "010" . date("Himdy");
        $command = $command . $this->build_checksum($command);
        $result = socket_helper::write_line_to_socket($this->envisalink_socket, $command);
        if (!$result['success']){
            return array(
                "success" => false,
                "error_msg" => "Error sending set time command to envisalink: " . $result['error_msg']
            );
        }

        // get a confirmation of the message we just sent
        $result = socket_helper::read_line_from_socket($this->envisalink_socket);
        if (!$result['success']){
            return array(
                "success" => false,
                "error_msg" => "Did not get expected confirmation of set time command from server: " .  $result['error_msg']
            );
        }
        if ($result['response'] != "500010" . $this->build_checksum("500010")){
            return array(
                "success" => false,
                "error_msg" => "Did not get expected confirmation of set time command from server - got " . $result['response']
            );
        }

        return array(
            "success" => true,
        );

    }

}

