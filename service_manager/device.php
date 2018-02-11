<?php

/**
 * Class device_plugin
 *
 * A device represents the device, like a switch or sensor, just like in SmartThings.
 *
 * It provides event handler functions and command functions
 *
 */
abstract class device {

    // members specific to this control

    public function log_msg($msg){
        $msg = "[device: " . $this->name . "] " . $msg;
        controller::log_msg($msg);
    }

    /**
     * The deviceNetworkId passed to the SmartThings addChildDevice() api function:
     * http://docs.smartthings.com/en/latest/ref-docs/smartapp-ref.html#addchilddevice
     *
     * @var string $device_id
     */
    protected $device_id;
    public function get_device_id(){
        return $this->device_id;
    }

    /**
     * The service that manages this device
     *
     * @var service $service
     */
    protected $service;
    public function get_service(){
        return $this->service;
    }

    /**
     * The device typeName passed to the SmartThings addChildDevice() api function:
     * http://docs.smartthings.com/en/latest/ref-docs/smartapp-ref.html#addchilddevice
     *
     * @var string $type
     */
    protected $type;
    public function get_type(){
        return $this->type;
    }

    /**
     * The name shown for the device in the SmartThings mobile app.  Passed as a property
     * in the SmartThings addChildDevice() api function:
     * http://docs.smartthings.com/en/latest/ref-docs/smartapp-ref.html#addchilddevice
     *
     * @var string $name
     */
    protected $name;
    public function get_name(){
        return $this->name;
    }


    /**
     * Constructs a device with properties which will in turn be used in the
     * SmartApp addChildDevice() API function:
     * http://docs.smartthings.com/en/latest/ref-docs/smartapp-ref.html#addchilddevice
     *
     * @param $service service The service that manages this device
     * @param $id string deviceNetworkId in addChildDevice()
     * @param $type string typeName in addChildDevice()
     * @param $name string The name prop passed in the properties map to addChildDevice()
     * @return device
     */
    public function __construct($service, $name, $id, $type){
        $this->service = $service;
        $this->name = $name;
        $this->device_id = $id;
        $this->type = $type;
    }

    private $config;

    /**
     * Allows for initializing the device with any custom config used by the service that manages it.
     * Also could be overridden to do any custom device setup if needed.
     * @param $config
     */
    public function initialize($config = null){
        $this->config = $config;
    }

    public function get_config($key){
        if (isset($this->config[$key])){
            return $this->config[$key];
        }
        return null;
    }

    /**
     * Adds an event to the event queue.
     * See the event constructor for parameter details
     * @param $name string The name of the Event. Typically corresponds to an attribute name of a capability
     * @param $value string The value of the Event. Typically is the value to set the attribute to
     * @param $description_text string This appears in the mobile application activity for the device
     * @param $link_text string Name of the Event to show in the mobile application activity feed
     */
    protected function create_event($name, $value, $description_text = null, $link_text = null){

        $params = array(
            "name" => $name,
            "value" => $value,
        );
        if ($description_text){
            $params['description_text'] = $description_text;
        }
        if ($link_text){
            $params['link_text'] = $link_text;
        }
        $log_msg = array();
        foreach ($params as $k => $v){
            $log_msg[] = $k . ":" . $v;
        }
        $this->log_msg("created event: " . implode(", ", $log_msg));
        $e = new event(
            $this,
            $name,
            $value,
            $description_text,
            $link_text
        );
        service::add_event($e);
    }

    /**
     * Your service would call this function when the device changes. The device class
     * would then create the appropriate event (open/close, off/on, active/inactive, etc etc)
     * for that type of device.
     */
    public function on_change($new_value){}

}

