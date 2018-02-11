<?php

/**
 * Class event
 *
 * Intended to contain the properties of an event from a device that will be passed to the SmartThings sendEvent() API function:
 * http://docs.smartthings.com/en/latest/ref-docs/smartapp-ref.html#sendevent
 *
 */
class event {

    /**
     * @var $device device
     */
    public $device;

    /**
     * @var $name string
     */
    public $name;

    /**
     * @var $value string
     */
    public $value;

    /**
     * @var $description_text string
     */
    public $description_text;

    /**
     * @var $link_text string
     */
    public $link_text;

    /**
     * Constructs an event with properties which will in turn be used in the
     * SmartApp sendEvent() API function:
     * http://docs.smartthings.com/en/latest/ref-docs/smartapp-ref.html#sendevent
     *
     * @param $device device
     * @param $name string The name of the Event. Typically corresponds to an attribute name of a capability
     * @param $value string The value of the Event. Typically is the value to set the attribute to
     * @param $description_text string This appears in the mobile application activity for the device
     * @param $link_text string Name of the Event to show in the mobile application activity feed
     */
    public function __construct($device, $name, $value, $description_text = null, $link_text = null){
        $this->device = $device;
        $this->name = $name;
        $this->value = $value;
        $this->description_text = $description_text;
        $this->link_text = $link_text;
    }

}