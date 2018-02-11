/**
 *  An Alarm Device Handler for use with the LAN Service Manager
 *
 */
metadata {
	definition (name: "AlarmDeviceHandler", namespace: "RustyKroboth", author: "Rusty Kroboth") {
        
        // don't actually use the alarm attributes or commands, it's just here to allow us to select the device in smart apps using the input() call like so:
        // input "thealarm", "capability.alarm", title: "Which Alarm?"
		capability "Alarm"

        attribute "alarm_arm_state", "enum", ["off", "busy", "arming", "stay", "away", "silent", "working"]
        attribute "alarm_triggered", "enum", ["off", "on"]

        command "disarm"
        command "stay"
        command "away"
        command "silent"
        command "panic"
        
    }

	simulator {
	}

    tiles(scale: 2){

		standardTile("status", "device.alarm_arm_state") {
			state "off", label:'Off', action: "stay", backgroundColor: "#ffffff", icon:"st.alarm.alarm.alarm", defaultState: true
			state "working", label:'Working...', action: "disarm", backgroundColor: "#ffffff", icon:"st.alarm.alarm.alarm"
			state "busy", label:'Busy', backgroundColor: "#ffffff", icon:"st.alarm.alarm.alarm"
			state "arming", label:'Arming', action: "disarm", backgroundColor: "#00a0dc", icon:"st.alarm.alarm.alarm"
			state "stay", label:'Stay', action: "disarm", backgroundColor: "#00a0dc", icon:"st.alarm.alarm.alarm"
			state "away", label:'Away', action: "disarm", backgroundColor: "#00a0dc", icon:"st.alarm.alarm.alarm"
			state "silent", label:'Silent', action: "disarm", backgroundColor: "#00a0dc", icon:"st.alarm.alarm.alarm"
		}

        multiAttributeTile(name:"detailStatus", type: "generic", width: 6, height: 4){
			tileAttribute ("device.alarm_arm_state", key: "PRIMARY_CONTROL") {
				attributeState "off", label:'Off', icon:"st.alarm.alarm.alarm", backgroundColor:"#ffffff", defaultState: true
				attributeState "working", label:'Updating...', icon:"st.alarm.alarm.alarm", backgroundColor:"#ffffff"
				attributeState "busy", label:'Busy', icon:"st.alarm.alarm.alarm", backgroundColor:"#ffffff"
				attributeState "arming", label:'Arming', icon:"st.alarm.alarm.alarm", backgroundColor:"#00a0dc"
				attributeState "stay", label:'Armed Stay', icon:"st.alarm.alarm.alarm", backgroundColor:"#00a0dc"
				attributeState "away", label:'Armed Away', icon:"st.alarm.alarm.alarm", backgroundColor:"#00a0dc"
				attributeState "silent", label:'Armed Silent', icon:"st.alarm.alarm.alarm", backgroundColor:"#00a0dc"
			}
		}
        
		standardTile("armOff", "device.alarm_arm_state", width: 3, height: 3) {
			state "default", label:'Off', action: "disarm", backgroundColor: "#ffffff", icon:"st.alarm.alarm.alarm", defaultState: true
		}
		standardTile("armStay", "device.alarm_arm_state", width: 3, height: 3) {
			state "off", label:'Stay', action: "stay", backgroundColor: "#ffffff", icon:"st.alarm.alarm.alarm", defaultState: true
			state "working", label:'Stay', backgroundColor: "#ffffff", icon:"st.alarm.alarm.alarm"
			state "busy", label:'Stay', backgroundColor: "#ffffff", icon:"st.alarm.alarm.alarm"
			state "arming", label:'Stay', backgroundColor: "#ffffff", icon:"st.alarm.alarm.alarm"
			state "stay", label:'Stay', backgroundColor: "#ffffff", icon:"st.alarm.alarm.alarm"
			state "away", label:'Stay', backgroundColor: "#ffffff", icon:"st.alarm.alarm.alarm"
			state "silent", label:'Stay', backgroundColor: "#ffffff", icon:"st.alarm.alarm.alarm"
		}
		standardTile("armAway", "device.alarm_arm_state", width: 3, height: 3) {
			state "off", label:'Away', action: "away", backgroundColor: "#ffffff", icon:"st.alarm.alarm.alarm", defaultState: true
			state "working", label:'Away', backgroundColor: "#ffffff", icon:"st.alarm.alarm.alarm"
			state "busy", label:'Away', backgroundColor: "#ffffff", icon:"st.alarm.alarm.alarm"
			state "arming", label:'Away', backgroundColor: "#ffffff", icon:"st.alarm.alarm.alarm"
			state "stay", label:'Away', backgroundColor: "#ffffff", icon:"st.alarm.alarm.alarm"
			state "away", label:'Away', backgroundColor: "#ffffff", icon:"st.alarm.alarm.alarm"
			state "silent", label:'Away', backgroundColor: "#ffffff", icon:"st.alarm.alarm.alarm"
		}
		standardTile("armSilent", "device.alarm_arm_state", width: 3, height: 3) {
			state "off", label:'Silent', action: "silent", backgroundColor: "#ffffff", icon:"st.alarm.alarm.alarm", defaultState: true
			state "working", label:'Silent', backgroundColor: "#ffffff", icon:"st.alarm.alarm.alarm"
			state "busy", label:'Silent', backgroundColor: "#ffffff", icon:"st.alarm.alarm.alarm"
			state "arming", label:'Silent', backgroundColor: "#ffffff", icon:"st.alarm.alarm.alarm"
			state "stay", label:'Silent', backgroundColor: "#ffffff", icon:"st.alarm.alarm.alarm"
			state "away", label:'Silent', backgroundColor: "#ffffff", icon:"st.alarm.alarm.alarm"
			state "silent", label:'Silent', backgroundColor: "#ffffff", icon:"st.alarm.alarm.alarm"
		}
        
        multiAttributeTile(name:"panic", type: "generic", width: 6, height: 4){
			tileAttribute ("device.alarm_triggered", key: "PRIMARY_CONTROL") {
				attributeState "off", action: "panic", label:'PANIC', icon:"st.alarm.alarm.alarm", backgroundColor:"#ffffff", defaultState: true
				attributeState "on", label:'TRIGGERED', icon:"st.alarm.alarm.alarm", backgroundColor:"#bc2323"
			}
		}

        main "status"
        details(["detailStatus", "armOff", "armStay", "armAway", "armSilent", "panic"])
    }
}

def handleEvent(eventProperties){
    log.debug "handling event: ${eventProperties}"
    sendEvent(eventProperties)
    
    // send a push alert when the alarm is triggered
    if (eventProperties.name == "alarm_triggered" && eventProperties.value == "on"){
        log.debug "Alarm triggered, sending push alert."
        parent.sendPush "Security System Alarm Tripped!"
    }
}

def disarm() {
	log.debug "Executing 'disarm'"
    sendEvent(name: "alarm_arm_state", value: "working")
    parent.sendCommand([command: "disarm", device_id: device.deviceNetworkId]);
}
def stay(){
	log.debug "Executing 'stay'"
    sendEvent(name: "alarm_arm_state", value: "working")
    parent.sendCommand([command: "stay", device_id: device.deviceNetworkId]);
}
def away(){
	log.debug "Executing 'away'"
    sendEvent(name: "alarm_arm_state", value: "working")
    parent.sendCommand([command: "away", device_id: device.deviceNetworkId]);
}
def silent(){
	log.debug "Executing 'silent'"
    sendEvent(name: "alarm_arm_state", value: "working")
    parent.sendCommand([command: "silent", device_id: device.deviceNetworkId]);
}
def panic(){
	log.debug "Executing 'panic'"
    parent.sendCommand([command: "panic", device_id: device.deviceNetworkId]);
}
