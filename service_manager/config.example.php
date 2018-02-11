<?php

class config {

    /**
     * System name, could be used in various places.
     * @var string
     */
    public static $system_name = "LAN Service Manager for SmartThings";

    /**
     * The port the api listens on for incoming api requests from the SmartThings Hub.
     * The IP of this controller plus this port must be entered in the settings screen when installing the
     * LAN Service Manager SmartApp in the SmartThings mobile app.
     * @var int
     */
    public static $api_listener_port = 10051;

    /**
     * The key the api requires when receiving incoming request from the SmartThings Hub,
     * AND sends with api commands send TO the SmartThings Hub.
     *
     * Provides cursory protection against your kids wreaking havoc on your SmartThings hub or
     * LAN Service Manager controller, because they'd have to know the key to make valid API calls.
     *
     * This key must be entered in the settings screen when installing the
     * LAN Service Manager SmartApp in the SmartThings mobile app.
     * Leave blank to require no key for the APIs.
     * @var int
     */
    public static $api_key = "";

    /**
     * This is where the SmartThings API endpoint is stored. It is not necessary to set it here,
     * because it is set by the SmartThings mobile app when the app is installed, or the settings
     * are updated.
     *
     * However, this means that when the LAN controller software is restarted for some reason,
     * then you need to go into the settings of the SmartApp and click save. As stated above,
     * this triggers the app to send the endpoint to the LAN controller. Normally the controller
     * software runs as a daemon process, so once it gets the endpoint like this, it keeps it
     * and there's nothing more to do.
     *
     * HOWEVER, that might get old having to remember to go into the settings of the app and hit save
     * every time you needed to restart the controller software for some reason. So you can optionally
     * "prime the pump" by putting the SmartThings api endpoint here in advance. The format
     * would be something like "192.168.1.186:39500", but with the proper ip and port of your hub.
     * You can find this info in the logs of either the SmartThings mobile app or the LAN manager
     * when the SmartApp is installed or the settings updated.
     *
     * @var string
     */
    public static $smartthings_hub_endpoint = "";


    /**
     * Optionally have all messages logged. Leave this empty to skip logging.
     * @var string
     */
    public static $log_dir = "";

    /**
     * The configuration of services and devices that the controller manages.
     * A service might be a zwave controller, another might be a security system hub, etc etc.
     * Each service manages a list of devices.
     *
     * This array is the list of services. Inside each service item of the array is:
     * - some configuration like name, class name, custom configuration, etc etc
     * - a list of devices on that service. Inside each device item of the devices array is
     *   some configuration of the device, like it's name, type, id, class name, custom
     *   configuration, etc etc
     *
     * @var array
     */
    public static $services = array(
        array(
            "name" => "Example Service",
            "class_file" => "services/example.php",
            "class_name" => "example_service",

            // this is any custom configuration your service may need
            "config" => array(
                "data_file" => "/tmp/state.json"
            ),

            // each service has a list of devices
            "devices" => array(
                array(
                    "name" => "Test Door 1",
                    "class_file" => "devices/door.php",
                    "class_name" => "door_device",
                    "device_id" => "example_device_1",
                    "type" => "ContactSensorDeviceHandler",
                    "config" => array(
                        "zone_id" => 1,
                    ),
                ),
                array(
                    "name" => "Test Door 2",
                    "class_file" => "devices/door.php",
                    "class_name" => "door_device",
                    "device_id" => "example_device_2",
                    "type" => "ContactSensorDeviceHandler",
                    "config" => array(
                        "zone_id" => 2,
                    ),
                ),
                array(
                    "name" => "Test Motion Sensor 1",
                    "class_file" => "devices/motion_sensor.php",
                    "class_name" => "motion_sensor_device",
                    "device_id" => "example_device_3",
                    "type" => "ContactSensorDeviceHandler",
                    "config" => array(
                        "zone_id" => 2,
                    ),
                ),
                array(
                    "name" => "Test Switch 1",
                    "class_file" => "devices/switch.php",
                    "class_name" => "switch_device",
                    "device_id" => "example_device_4",
                    "type" => "SwitchDeviceHandler",
                    "config" => array(
                    ),
                ),
                array(
                    "name" => "Test Alarm",
                    "class_file" => "devices/alarm.php",
                    "class_name" => "alarm_device",
                    "device_id" => "example_device_5",
                    "type" => "AlarmDeviceHandler",
                    "config" => array(
                    ),
                ),
            ),
        ),

    );

}

