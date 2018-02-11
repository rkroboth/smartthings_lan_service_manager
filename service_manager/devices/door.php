<?php

class door_device extends device {

    /**
     * Your service would call this function when door is opened or closed, and the resulting event
     * would get sent to the SmartThings hub, so the hub will show the device in the new state
     * @param $new_value bool true = open, false = closed
     */
    public function on_change($new_value){
        $this->create_event(
            "contact",
            $new_value ? "open" : "closed"
        );
    }

}

