/**
 *  A Contact Sensor Device Handler for use with the LAN Service Manager
 *
 */
metadata {
	definition (
        name: "ContactSensorDeviceHandler",
        namespace: "RustyKroboth",
        author: "Rusty Kroboth"
    ){
		capability "Contact Sensor"
	}

	simulator {
	}

    tiles(scale: 2){

		standardTile("status", "device.contact") {
			state "closed", label: 'Closed', icon: "st.contact.contact.closed", backgroundColor: "#00a0dc", defaultState: true
			state "open", label: 'Open', icon: "st.contact.contact.open", backgroundColor: "#e86d13"
		}

        multiAttributeTile(name:"detailStatus", type: "generic", width: 6, height: 4){
			tileAttribute ("device.contact", key: "PRIMARY_CONTROL") {
				attributeState "closed", label:'Closed', icon:"st.contact.contact.closed", backgroundColor:"#00a0dc", defaultState: true
				attributeState "open", label:'Open', icon:"st.contact.contact.open", backgroundColor:"#e86d13"
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
