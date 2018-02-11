<?php

class example_service extends service {

    private $data_file;
    private $poll_interval = 1;
    private $last_poll;
    private $state;

    public function initialize($config = null){
        $this->data_file = $config['data_file'];
    }

    public function start(){
        $this->last_poll = time();
        $this->log_msg("Test interface started");
        $this->state = array();
    }

    public function poll(){

        // Poll the controller periodically.
        // In this service, that means reading a json file that lists the device current states,
        // and see if any of the device states have changed since we last read the json file.
        //
        // This function is called frequently (100 times/s), so we make the function only execute the poll
        // as often as is reasonable for this service.   Since this service polls by reading a file on the
        // file system, we'll do this no more than once per second or so.
        if (time() - $this->last_poll > $this->poll_interval){
            // read the data file
            $this->last_poll = time();
            if (file_exists($this->data_file)){
                $contents = trim(file_get_contents($this->data_file));
                $contents = json_decode($contents, true);
                foreach ($contents as $device_id => $value){
                    if (!isset($this->state[$device_id])){
                        $this->state[$device_id] = $value;
                        continue;
                    }
                    if ($this->state[$device_id] == $value){
                        // no change
                        continue;
                    }
                    $this->state[$device_id] = $value;
                    $device = self::get_device($device_id);
                    if ($device){
                        self::log_msg("device " . $device->get_name() . " changed in json file");
                        $device->on_change($value);
                    }

                }
            }
        }
    }

    public function stop(){
        // This particular example service doesn't have to do any shutdown.
        // But an example of a shutdown task might be closing a socket to a security system hub.
    }

    /**
     * Make the change to the physical device specified by the given value.
     * In this case, that means changing the json file where the states are maintained.
     * @param $device device
     * @param $new_value string
     */
    public function update_device($device, $new_value){

        if ($new_value == "panic"){
            return;
        }

        $device_id = $device->get_device_id();
        if (file_exists($this->data_file)){
            $contents = trim(file_get_contents($this->data_file));
            $contents = json_decode($contents, true);
            if ($contents){
                $contents[$device_id] = $new_value;
                $this->state[$device_id] = $new_value;
                file_put_contents($this->data_file, json_encode($contents));
            }
        }
    }

}

