/**
 *  A Switch Device Handler for use with the LAN Service Manager
 *
 */
metadata {
	definition (
        name: "SwitchDeviceHandler",
        namespace: "RustyKroboth",
        author: "Rusty Kroboth"
    ){
		capability "Switch"
	}

	simulator {
	}

    tiles(scale: 2){

		standardTile("status", "device.switch") {
			state "off", label: 'Off', action: "on", icon: "st.switches.switch.off", backgroundColor: "#ffffff", defaultState: true
			state "on", label: 'On', action: "off", icon: "st.switches.switch.on", backgroundColor: "#00a0dc"
		}

        multiAttributeTile(name:"detailStatus", type: "generic", width: 6, height: 4){
			tileAttribute ("device.switch", key: "PRIMARY_CONTROL") {
				attributeState "off", label:'Off', icon:"st.switches.switch.off", backgroundColor:"#ffffff", defaultState: true
				attributeState "on", label:'On', icon:"st.switches.switch.on", backgroundColor:"#00a0dc"
			}
		}

		main("status")
		details("detailStatus")

    }
    
}

def handleEvent(eventProperties){
    log.debug "sendEvent: ${eventProperties}"
    sendEvent(eventProperties)
}

def off() {
	log.debug "Executing 'off'"
    parent.sendCommand([command: "off", device_id: device.deviceNetworkId]);
}

def on() {
	log.debug "Executing 'on'"
    parent.sendCommand([command: "on", device_id: device.deviceNetworkId]);
}
