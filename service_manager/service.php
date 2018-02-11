<?php

/**
 * Class service
 *
 * A service handles and manages communication with devices of a common protocols.
 * For example, a service might be zwave, which would handle all communication with
 * your zwave devices via the zwave hub. Another might be a security system controller, and it would
 * know how to handle all communication with that security system devices via it's hub.
 */
class service {

    /**
     * A queue of events from all devices that need to be sent to the SmartThings hub
     * @var array
     */
    protected static $event_queue = array();
    public static function add_event($event){
        self::$event_queue[] = $event;
    }
    public static function get_event(){
        if (count(self::$event_queue) > 0){
            return array_shift(self::$event_queue);
        }
        return null;
    }

    // static list of all the registered services that the main controller can talk to
    public static $services = array();

    public static function load_services(){
        foreach (config::$services as $service_config){

            require_once($service_config['class_file']);
            $classname = $service_config['class_name'];
            /** @var $s service */
            $s = new $classname($service_config['name']);
            $custom_config = isset($service_config['config']) ? $service_config['config'] : null;
            $s->initialize($custom_config);

            foreach ($service_config['devices'] as $device_info){
                if (isset(self::$devices[$device_info['device_id']])){
                    throw new Exception("Duplicated device_id '" . $device_info['device_id'] . "'");
                }
                require_once($device_info['class_file']);
                $classname = $device_info['class_name'];
                /** @var $d device */
                $d = new $classname($s, $device_info['name'], $device_info['device_id'], $device_info['type']);
                $custom_config = isset($device_info['config']) ? $device_info['config'] : null;
                $d->initialize($custom_config);
                self::$devices[$device_info['device_id']] = $d;
            }
            self::$services[] = $s;
        }
    }

    protected function log_msg($msg){
        $msg = "[service: " . $this->name . "] " . $msg;
        controller::log_msg($msg);
    }

    public static function start_all(){
        foreach (service::$services as $service){
            /** @var service $service */
            $service->start();
        }
    }

    /**
     * Calls the poll function on eac service
     */
    public static function poll_all(){
        foreach (service::$services as $service){
            /** @var $service service */
            $service->poll();
        }
    }

    public static function stop_all(){
        foreach (service::$services as $service){
            /** @var service $service */
            $service->stop();
        }
    }

    protected $name;


    protected static $devices = array();

    /**
     * Gets all devices from all services
     * @return array
     */
    public static function get_all_devices(){
        return self::$devices;
    }

    /**
     * Gets devices managed by this service
     * @return array
     */
    public function get_devices(){
        $devices = array();
        foreach (self::$devices as $device){
            /** @var device $device */
            if ($device->get_service()->name === $this->name){
                $devices[] = $device;
            }
        }
        return $devices;
    }

    /**
     * Gets the device with the given device_id
     * @param string $device_id
     * @return device
     */
    public static function get_device($device_id){
        if (isset(self::$devices[$device_id])){
            return self::$devices[$device_id];
        }
        return null;
    }

    /**
     * @param $name
     * @return service
     */
    public function __construct($name){
        $this->name = $name;
    }





    // actual services should extend this class, and override the methods below.


    /**
     * Initialize the service with any config values passed in from the config
     * @param array $config
     */
    public function initialize($config = null){}


    /**
     * Starts the service
     */
    protected function start(){}

    /**
     * Performs any shutdown of the plugin (done in a shutdown function)
     */
    protected function stop(){}


    /**
     * Make the change to the physical device specified by the given value.
     * @param $device device
     * @param $new_value string
     */
    public function update_device($device, $new_value){
        // here the service actually make the change to the physical device

        // the service also triggers the on_change event on the device.
        // sometimes that means the service calls the on_change event directly like so:
        $device->on_change($new_value);
    }

    /**
     * Poll causes the service to queue up events that happened on any devices that the service controls,
     * These queued events are later passed on to the smartthings hub.
     *
     * It's also a place where your service can do maintenance in the service, or whatever else.
     *
     * This function gets called in the controller loop extremely frequently,
     * (100 times per second for example), so your plugin should keep a timer,
     * and only actually poll for its data as often as is reasonable for that service.
     * @return array
     */
    protected function poll(){}

}