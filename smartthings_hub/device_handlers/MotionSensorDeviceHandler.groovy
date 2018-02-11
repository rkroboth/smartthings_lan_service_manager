/**
 *  A Motion Sensor Device Handler for use with the LAN Service Manager
 *
 */
metadata {
	definition (
        name: "MotionSensorDeviceHandler",
        namespace: "RustyKroboth",
        author: "Rusty Kroboth"
    ){
		capability "Motion Sensor"
	}

	simulator {
	}

    tiles(scale: 2){

		standardTile("status", "device.motion") {
			state "inactive", label: '${name}', icon: "st.motion.motion.inactive", backgroundColor: "#ffffff", defaultState: true
			state "active", label: '${name}', icon: "st.motion.motion.active", backgroundColor: "#00a0dc"
		}

        multiAttributeTile(name:"detailStatus", type: "generic", width: 6, height: 4){
			tileAttribute ("device.motion", key: "PRIMARY_CONTROL") {
				attributeState "inactive", label:'Inactive', icon:"st.motion.motion.inactive", backgroundColor:"#ffffff", defaultState: true
				attributeState "active", label:'Active', icon:"st.motion.motion.active", backgroundColor:"#00a0dc"
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
