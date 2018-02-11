<?php

class motion_sensor_device extends device {

    /**
     * Your service would call this function when motion is detected or has stopped, and the resulting event
     * would get sent to the SmartThings hub, so the hub will show the device in the new state
     * @param $new_value bool true = active, false = inactive
     */
    public function on_change($new_value){
        $this->create_event(
            "motion",
            $new_value ? "active" : "inactive"
        );
    }


}

