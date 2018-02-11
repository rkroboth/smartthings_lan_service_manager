<?php

class alarm_device extends device {

    /**
     * Your service would call this function when alarm state is changed, and the resulting event
     * would get sent to the SmartThings hub, so the hub will show the alarm in the correct state
     * @param string $new_value "off", "busy", "arming", "stay", "away", or "silent"
     */
    public function on_change($new_value){
        $this->create_event(
            "alarm_arm_state",
            $new_value
        );
    }

    /**
     * Your service would call this function when alarm is either tripped, or reset from being tripped.
     * The resulting event would get sent to the SmartThings hub, so the hub will show correct alarm status.
     * @param bool $new_value true = tripped, false = reset
     */
    public function on_alarm_triggered_change($new_value){
        $this->create_event(
            "alarm_triggered",
            $new_value ? "on" : "off"
        );
    }


    /**
     * The SmartThings hub will call this function when the user disarms the alarm using the app.
     * It also resets the tripped alarm
     */
    public function disarm(){
        $this->get_service()->update_device($this, "disarm");
    }

    /**
     * The SmartThings hub will call this function when the user arms the alarm using the app
     */
    public function stay(){
        $this->get_service()->update_device($this, "stay");
    }

    /**
     * The SmartThings hub will call this function when the user arms the alarm using the app
     */
    public function away(){
        $this->get_service()->update_device($this, "away");
    }

    /**
     * The SmartThings hub will call this function when the user arms the alarm using the app
     */
    public function silent(){
        $this->get_service()->update_device($this, "silent");
    }

    /**
     * The SmartThings hub could call this function if the user presses the panic button
     */
    public function panic(){
        $this->get_service()->update_device($this, "panic");
    }

}

