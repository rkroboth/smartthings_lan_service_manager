<?php

class switch_device extends device {

    /**
     * Your service would call this function when switch is turned off or on, and the resulting event
     * would get sent to the SmartThings hub, so the hub will show the device in the new state
     * @param $new_value bool true = on, false = off
     */
    public function on_change($new_value){
        $this->create_event(
            "switch",
            $new_value ? "on" : "off"
        );
    }

    /**
     * The SmartThings hub will call this function when the user turns ON the switch using the app
     */
    public function on(){
        $this->get_service()->update_device($this, true);
    }

    /**
     * The SmartThings hub will call this function when the user turns OFF the switch using the app
     */
    public function off(){
        $this->get_service()->update_device($this, false);
    }

}

